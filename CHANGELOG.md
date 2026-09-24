# Changelog

## Unreleased

- The configuration values a request read are answered from a request memo on the second read, under the `config_memo` switch: no scope resolution and no type lookup per read.

## Unreleased

- The stock id of the current website as a cache entry in the local files, from the new FastBootInventory module: no sales channel select per request. A change of a stock's sales channels cleans it.

## 0.2.0-rc8

- Tax rates, customer groups and currency rates as cache entries in the local files: no select per request for them. A tax rule, rate, class or group save cleans the tax entries; a rate import cleans the currency entries.
- A process keeps the entries it read in memory until they expire; a worker answers them without the include.

## 0.2.0-rc7

- Promote entries that Mage-OS stores through its compression decorator to the local files; the lifetime rule reads the packed record as the frontend answers it.

## 0.2.0-rc6

- Read the di.xml files through the runtime loader of Magento when the installation has no compiled metadata, so a developer-mode install serves requests.

## 0.2.0-rc5

- Use Credis when the PHP Redis extension is unavailable; phpredis remains the preferred transport when installed.
- Keep schema publication, TTL and invalidation atomic with either client.
- Test units, Redis invariants, authentication, timeout recovery and Composer installation with and without the extension.
- Keep user documentation in the root/module READMEs and internal design/testing notes under `dev/`.
- Remove manual FastBoot release overrides; Magento's static-content deployment version is the only build identity.
- Keep customer READMEs limited to package-specific setup and behavior.

## 0.2.0-rc4

- Use Magento's static-content deployment version as the default FastBoot build identity.
- Store generated FastBoot and preload data under Magento's cache directory.
- Separate FastBoot, optional preload and generic Magento performance documentation.
- Add CI for PHP syntax, Magento units, Redis behavior, DI compilation and Composer packaging.

## 0.2.0-rc3

Combined release candidate: strict node-local caches, shared Redis invalidation, configuration and GraphQL optimizations, optional class preload, deployment commands and reproducible Composer packaging.
