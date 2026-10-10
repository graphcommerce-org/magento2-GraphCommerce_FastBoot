# FastBoot

## Installation

```sh
composer require graphcommerce/magento-fast-boot
bin/magento module:enable GraphCommerce_FastBootCache GraphCommerce_FastBoot GraphCommerce_FastBootGraphQl
```

## Configuration

No configuration is required. Cache namespaces use `pub/static/deployed_version.txt`. Each PHP/DI/config build needs a distinct static-content version, shared across its nodes. `setup:static-content:deploy --content-version="$BUILD_ID"` supplies that identity.

## Deployment

After DI compilation and static deployment, run on each node:

```sh
bin/magento fastboot:status
bin/magento fastboot:prepare
```

`status` checks the deployment identity and the cache directory, and prints the feature switches. `prepare` writes area DI differences, the GraphQL schema and store configuration to the local cache.

All optimizations are enabled by default. No database patches are installed.
