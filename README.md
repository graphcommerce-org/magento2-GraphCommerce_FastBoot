# GraphCommerce FastBoot

FastBoot reduces repeated Magento bootstrap and GraphQL work using node-local PHP data caches with shared invalidation. The package includes an independent PHP class preload module.

```sh
composer require graphcommerce/magento-fast-boot
```

| Module | Documentation |
|---|---|
| FastBoot | [Installation and deployment](src/FastBoot/README.md) |
| FastBootCache | [Cache configuration and behavior](src/FastBootCache/README.md) |
| FastBootGraphQl | [GraphQL optimizations](src/FastBootGraphQl/README.md) |
| FastBootInventory | [The stock id of a website from the cache](src/FastBootInventory/README.md) |
| FastBootPreload | [Class preloading](src/FastBootPreload/README.md) |

## Online benchmarks

Median PHP execution time on a deployed AMD EPYC server, with 600 measured requests per workload and configuration. Savings are Native − (FastBoot + preload).

| Workload | Native | FastBoot | FastBoot + preload | Saved vs native |
|---|---:|---:|---:|---:|
| Store configuration | 32.88 ms | 19.32 ms | 14.45 ms | 18.43 ms |
| 24 products, including price ranges | 535.22 ms | 522.46 ms | 499.62 ms | 35.60 ms |
| Luma category, 8 products | 160.89 ms | 145.74 ms | 128.76 ms | 32.13 ms |

[Full online results](dev/bench/online/RESULTS.md), including confidence intervals, memory usage and public HTTPS timings. [Local M5 Mac results](dev/bench/RESULTS.md).

## Requirements

- Magento 2.4.8 or compatible Mage-OS APIs.
- PHP 8.2–8.5, subject to the installed Magento version.
- A writable Redis primary for schema L1.
- Private, node-local cache storage.

Release candidate.
