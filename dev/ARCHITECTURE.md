# FastBoot implementation notes

FastBoot keeps Magento cache entries, and data derived from them, as private PHP-array files on each server, reused through OPcache. Magento's own cache is authoritative and shared by the servers; the local files are copies of it. The stitched GraphQL schema is one of these local caches and uses the same storage, version token and write fence as the others. No FastBoot cache needs a connection of its own: everything goes through Magento's cache API, so any cache backend works.

Customer setup and operating instructions belong in the [module READMEs](../README.md). This document records implementation behavior and constraints for future development.

## The version token

`FastBootCache/Model/Version` holds a random token in Magento's cache under the `config` tag. Every local index path contains that token, so a new token makes every server stop reading the files of the old one. These operations change the token on every server, because the token lives in the shared cache:

| Operation | How the token changes |
|---|---|
| A save, remove or clean on a covered cache type (`config`, `eav`, `translate`, `db_ddl`, `reflection`, `collections`) | `Plugin/OpcacheLayer` calls `Version::bump()`. |
| A clean of the `config` tag in the default frontend, a flush of the cache | The token entry is removed; the next request that finds no token starts a new one. |
| A direct reset of the GraphQL schema data | `FastBootGraphQl/Plugin/Schema/Reset` bumps before and after the reset. |

A request reads the token once and keeps it until the request ends (`ResetAfterRequestInterface`). A positive `grace` (di.xml argument of `Version`) keeps the token in a local stamp file for that many seconds, which trades immediate cross-server freshness for fewer cache reads. The default is zero.

## The write fence

A writer captures the version (`PhpFiles::generation()`) before it reads or computes its data, and passes it to `PhpFiles::write(..., $expectedVersion)`. The write is refused when the current version differs. A clean on any server while a build is in progress thus never leaves the old build's data under the new version. The request that built the data still finishes with it.

## The GraphQL schema

`FastBootGraphQl/Model/Config/OpcacheData` is the class behind the `Magento\Framework\GraphQl\Config\Data` virtual type. Magento's own path loads about 1 MB of JSON from the config cache and decodes it on every request. With the `schema_array` switch on and the `config` cache type enabled:

1. Read the array from the local files (group `SCHEMA`, id = the schema cache id). A hit is an include from OPcache; the config cache is not read.
2. On a miss, capture the version, run Magento's path (the config cache, else the schema reader and a save to the config cache) and write the array under the captured version.

A save of the schema to the config cache is a save on a covered type, so it changes the version. The request that builds the schema from the reader therefore writes no file; the next request reads the config cache and writes the file. `fastboot:prepare` reads the schema twice for that reason.

A blob is content-addressed: the same schema under a new version reuses the same PHP file and its compiled OPcache entry; only a small index file is written. The schema blob is subject to `max_entry_bytes` (2 MiB of PHP source by default; a real schema is about 1.1 MiB). A larger schema is a miss on every request and is served by Magento's path.

The schema is written without an expiry: it derives from the code of the release, which is part of the namespace, and from the config cache, whose changes change the version.

## OPcache and memory

Local files contain arrays and scalar values. Their names are based on content hashes, and writes use atomic rename. Identical payloads reuse the same filename; different payloads use new files. This also works with `opcache.validate_timestamps=0` because runtime data is not overwritten at an already compiled path. An index file holds the hash of its blob and the expiry of the entry, so an expiry change does not change the compiled file.

These files are **not preloaded**. PHP class preload is a separate optimization whose definitions remain fixed until the FPM master/service restarts. Cache freshness is decided by the version token and the index expiry, not by OPcache's source-file timestamp validation.

Source size is not compiled-memory usage. Include shared OPcache, worker allocations and the preload master's footprint in capacity planning. Deleting files alone does not reclaim compiled memory; retire cache namespaces with the release/FPM lifecycle.

Whole-object serialization is not used here. It would require reconstructing object graphs in each request; the cache instead stores the reusable data from which Magento operates.

## Failure behavior

| Failure or limit | Result |
|---|---|
| Local file missing or syntactically damaged | Read Magento's cache again and write a new local copy. |
| Local directory cannot be written | Use Magento's cache without saving a local copy. |
| Local admission limit reached | Use Magento's cache; existing valid local files remain available. |
| The cache backend fails to answer the token | The error of Magento's cache propagates; a backend that answers no token gives a new version. Old local files are not served in either case. |
| The token is evicted or flushed | A new token; the old local files are not read again. |
| Invalidation overtakes a build | The write of that build is refused. |

Deleting keys in the cache backend outside Magento's cache API does not change the token. Integrate external writers with the supported cache APIs.

The local directory is trusted application storage. Warm reads do not hash the complete PHP file on every include. Keep the directory private and writable only by trusted application users.

## Deployment identity

`FastBootCache/Model/Release` reads Magento's static-content deployment version once per request. There is no manual FastBoot release override. A missing or empty version prevents local cache writes; area DI differences can fall back to hashing their compiled source files. Local paths follow Magento's configured cache directory.

The local namespace includes this build identity, so a new build never reads the files of another. The token is shared by both builds during a rolling deployment, so a clean reaches the files of both.

## Module responsibilities

- `FastBootCache` owns value-file storage, the version token and the invalidation plugins on the covered cache types.
- `FastBoot` owns compiled area DI differences, system/scope configuration and storefront helper optimizations. Capture the version before deriving values and fence the write with it.
- `FastBootGraphQl` owns the schema array, parsed-document and structural-validation reuse. Never bypass Magento's query processor, custom validation, request limits or scalar coercion. Schema invalidation must retire cached validation proofs.
- `FastBootPreload` records class definitions during a bounded window and loads them at master startup. It never preloads runtime cache values. Recording paths follow Magento's cache directory; the startup entry point remains in the package.

Avoid cache interception on broad bootstrap decorators such as TagScope: compiled plugin-list loading uses those decorators before interceptors can be resolved. Test changes in fresh compiled Magento, including cache-disabled paths and cross-node invalidation.
