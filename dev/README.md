# Developing FastBoot

- [Architecture and design constraints](ARCHITECTURE.md)
- [Tests and package builds](tests/README.md)
- [Continuous integration](ci/README.md)
- [Benchmarks and reproduction](bench/README.md)
- [Measured results](bench/RESULTS.md)

## Feature switches

Production installs use the default-enabled optimizations. These switches support debugging, regression isolation and extension compatibility. Set PHP booleans under `fastboot` in `app/etc/env.php`, then clean configuration caches and restart PHP-FPM when changing deployment configuration.

| Switch | What it enables | Bypassed when Magento config cache is disabled? |
|---|---|---|
| `cache_files` | Local PHP values for covered Magento configuration caches. | Yes |
| `system_config_array` | Local system configuration loaded by requested scope. | Yes |
| `schema_array` | Local GraphQL schema arrays; also requires schema L1 configuration. | Yes |
| `schema_scalars` | Faster scalar lookup in the GraphQL schema. | No |
| `parsed_queries` | Parsed GraphQL document reuse. | Yes |
| `validated_queries` | Reuse of successful built-in structural validation. | Yes |
| `area_config_diff` | Compiled differences between global and area DI configuration. | No |
| `quote_without_connection` | SQL string quoting without opening a connection when the shortcut's conditions hold. | No |
| `deploy_config_unchanged` | Reuse of deployment-configuration checks, keyed by configuration contents. | Yes |
| `scopes_cache` | Cached scope data. | Yes |
| `website_stores` | Cached website-to-store relationships. | Yes |
| `default_store` | Default-store lookup through the store repository. | No |
| `view_config` | Reuse of theme/area view XML as PHP data. | Yes |
| `guest_tax_factor` | Reuse of guest tax-factor calculations. | Yes |
| `placeholder_url` | Cached placeholder URLs, including theme and transport identity. | Yes |
| `tax_rates` | Tax rates and applied rates per rate request from the cache. | Yes |
| `customer_groups` | Customer groups by id from the cache, with their excluded websites. | Yes |
| `currency_rates` | Currency rates from the cache; a rate import cleans them. | Yes |

These switches do not enable PHP class preload; that requires `opcache.preload` at FPM startup.

`validated_queries` retains Magento's query processor, request-specific security checks, custom validation rules and scalar literal coercion. Extensions that change schema definitions without configuration invalidation must disable this switch or integrate their schema changes with invalidation.
