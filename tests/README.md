# Tests — Nhanh Data 2-Way Syncer

Unit tests run without a WordPress install. `tests/bootstrap.php` stubs the
WordPress, WooCommerce and `wpdb` APIs the plugin touches, so the suite is fast
and hermetic.

## Run

```bash
composer install
composer test
```

Or directly:

```bash
./vendor/bin/phpunit --configuration phpunit.xml
./vendor/bin/phpunit --configuration phpunit.xml --filter ImportServiceTest
./vendor/bin/phpunit --configuration phpunit.xml --testdox
```

## Layout

| File | Covers |
| --- | --- |
| `SchemaTest` | `dbDelta` table creation, version gating, `Schema::table()` |
| `JobRepositoryTest` | job create/start/finish/find/recent + log rows |
| `ProductMapRepositoryTest` | product mapping upsert, find by code/nhanh id, WC link, error |
| `TermMapRepositoryTest` | category/brand term mapping upsert, find, list + filter |
| `WooBridgeTest` | WooCommerce product/term upsert (create vs update) |
| `ImportServiceTest` | full Nhanh → Woo import: categories, brands, products, price/stock fallbacks, status rules, counters |
| `NhanhClientFactoryTest` | credential validation and client construction guards |
| `SyncCronServiceTest` | cron schedule/clear, recurrence identifiers, settings reschedule |
| `SettingsServiceTest` | defaults, merge, sanitize, clamping, connection test |
| `DefaultsTest` / `SettingsRepositoryTest` | default option shape, option read/write/merge |
| `SyncStatusResolverTest` | price/stock mismatch resolution order |
| `TermTaxonomyResolverTest` | category vs brand taxonomy resolution |
| `FormatterTest` | VND formatting, badges, log labels |
| `OAuthSettingsTest` | verify code generation, return link, OAuth URL params |
| `PluginLifecycleTest` | activation/deactivation, settings round-trip, cron job run |
| `AdminStructureTest` | sample data shape, submenu definitions, UI mode resolution |

## Fixtures

- `tests/fixtures/wp-admin/includes/upgrade.php` — `dbDelta()` stub required by
  `Schema::install()`.
- `tests/bootstrap.php` — WP/WC function + `wpdb` mock (an in-memory,
  SQL-subset store used by the repository tests).

## Notes

- `wpdb` mock parses the `SELECT ... WHERE ... ORDER BY ... LIMIT` shapes used by
  the plugin's repositories. It is intentionally small — if a repository adds a
  genuinely new query shape, extend `wpdb::select()` rather than loosening tests.
- `ImportService` accepts an injected client and settings array so the mapping
  logic can be exercised without hitting the Nhanh API.