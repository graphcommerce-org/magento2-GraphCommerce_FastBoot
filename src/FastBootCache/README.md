# FastBoot cache configuration

Settings in `app/etc/env.php`.

## Local cache limits

Defaults under `fastboot.files`:

```php
'files' => [
    'max_files' => 2048,
    'max_bytes' => 67108864,       // 64 MiB of PHP source
    'max_entry_bytes' => 2097152, // 2 MiB per entry
    'max_queries' => 256,
],
```

`max_files` and `max_bytes` apply to immutable blobs in a local release namespace. `max_queries` limits each of the parsed-query and validated-query index groups per generation. Once a limit is reached, new entries use their native computation or authoritative data path. Existing valid entries remain usable.

The stitched GraphQL schema is one entry. A schema larger than `max_entry_bytes` as PHP source is not kept locally, and each request decodes it from Magento's config cache; raise the limit to keep it local.

Limits bound admitted PHP source per namespace. There is no LRU eviction. Retired namespaces remain until deployment cleanup.

## Local paths

Paths below are relative to Magento's configured cache directory, normally `var/cache`:

| Data | Path |
|---|---|
| Generic PHP blobs and indexes | `fastboot/v2/<namespace>/` |
| Compiled area differences | `fastboot/metadata/` |

Files can contain decrypted configuration. Removing them causes repopulation; deleting source files does not reclaim their compiled OPcache memory.

## Freshness

Magento's cache is authoritative; the local files hold copies of its entries and of data derived from them, the stitched GraphQL schema included. All local files of a namespace belong to one version token, which FastBoot keeps in Magento's cache under the `config` tag. Each of these operations, on any server, gives every server a new version:

- a save, remove or clean on a covered cache type (`config`, `eav`, `translate`, `db_ddl`, `reflection`, `collections`);
- a clean of the `config` tag, or a flush of the cache;
- a direct reset of the GraphQL schema data.

A request reads the token from Magento's cache once and uses that version until it ends. A write captures the version before it computes its data and is refused when the version changed in between, so a clean during a build does not leave old data under the new version. An in-flight request can finish with data it already loaded.

The invalidation plugins must remain active on every web, admin and CLI node. A change made outside Magento's cache API, such as deleting keys in the cache backend directly, does not change the version.

A local disk failure falls back to Magento's cache.
