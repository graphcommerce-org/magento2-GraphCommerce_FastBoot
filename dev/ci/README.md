# Continuous integration

`.github/workflows/ci.yml` runs on pull requests, pushes to `main`, version tags and manual dispatch. It uses read-only repository permissions and pinned GitHub Action revisions.

| Job | Checks |
|---|---|
| PHP 8.2–8.5 syntax | Composer metadata, Python tooling syntax and syntax for runtime PHP and test fixtures. |
| Magento 2.4.8 / PHP 8.3 and 8.4 | Unit regressions, fresh DI compilation, reproducible ZIPs and Composer artifact installation. |

The integration jobs download official public Magento 2.4.8 source and install its dependencies with Composer. They register a copy of the working package. No Adobe Marketplace or customer credentials are needed. Dependencies resolve against a PHP 8.3 baseline and run on both PHP versions; PHPUnit 11.5 supplies the older-target runner. The Symfony-adapter and newer parser-policy tests skip where the corresponding APIs are absent.

The fixture uses Magento's file cache and has no Redis service and no business database: CI verifies units, application DI and packaging, not end-to-end storefront behavior. Customer GraphQL, Luma rendering, configuration saves and performance measurements still require an installed application with representative data.

JUnit results, package artifacts and fixture logs are retained for seven days. CI artifacts use the synthetic version `0.0.0-rc0`; they are verification outputs, not published releases. CI does not tag, release, merge or deploy.

For a local fixture, run from the package repository root:

```sh
python3 dev/ci/prepare.py --root /absolute/path/to/an/empty/magento-fixture
```

Use a root outside the package directory so Composer's local package copy cannot include its own fixture. Supply `--archive /path/to/magento-2.4.8.tar.gz` to reuse an official source archive. Then set `MAGENTO_ROOT` to that fixture and run the workflow's test commands.
