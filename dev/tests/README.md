# Test and build FastBoot

These tools are for contributors working from the source repository. They are excluded from the runtime ZIP. Use a disposable Magento development/staging installation with this package registered and an appropriate PHPUnit version installed.

The [benchmark results](../bench/RESULTS.md) describe measured application behavior and its limits.

The [CI guide](../ci/README.md) describes the GitHub Actions matrix and its coverage limits.

## Unit tests

Run from the package repository root:

```sh
export MAGENTO_ROOT=/absolute/path/to/magento
php "$MAGENTO_ROOT/vendor/bin/phpunit" -c phpunit.xml.dist
```

`MAGENTO_ROOT` selects the installation used by the test bootstrap. Without it, the bootstrap attempts to locate the containing Magento project. The bootstrap loads the working package source so tests do not silently exercise an older installed archive.

| Target used in validation | Test runner |
|---|---|
| Mage-OS / PHP 8.4 and 8.5 | PHPUnit 13 |
| Magento 2.4.8 / PHP 8.3 and 8.4 | PHPUnit 11.5 with that target's test-runner configuration |

The Symfony cache-adapter and newer parser-policy fixtures skip on Magento 2.4.8 where those APIs are absent. The older Zend backend's expiry behavior is tested directly.

## Build a release artifact

Run from the package repository root:

```sh
python3 dev/release/build.py --version 0.2.0-rc5
```

The builder writes a deterministic ZIP and SHA-256 file under `dist/`. It includes the runtime modules, Composer metadata, license and customer documentation. `BUILD-MANIFEST.json` records a hash for each packaged file. Build a new version for subsequent releases; preserve previously distributed archives.

Verify installation using a separate, empty directory:

```sh
python3 dev/release/install-smoke.py \
  --archive dist/graphcommerce-magento-fast-boot-0.2.0-rc5.zip \
  --magento-root "$MAGENTO_ROOT" \
  --output /absolute/path/to/an/empty/install-check
```

This check verifies hashes, resolves the package with Composer against the existing Magento dependency versions, and checks all four module registrations and extracted class paths. It borrows the existing dependency tree; it does not provision a Magento database or run a complete storefront deployment.

## Application checks

Unit tests do not replace an application run. Exercise representative GraphQL operations, admin configuration saves, stores, customer sessions, tax, cross-node invalidation and rollback using the customer's extensions. Use a dedicated FPM service for comparisons and inspect both cold and warm memory.
