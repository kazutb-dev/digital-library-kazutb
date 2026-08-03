# Runtime stabilization evidence

## Confirmed failures and root causes

| URL / role | Exception | Root cause | Fix | Regression evidence |
|---|---|---|---|---|
| `/dashboard`, student/faculty | `PDOException`, `SQLSTATE[42703]`, missing `reservations.queue_sequence` | production schema was three migrations behind the deployed code | validated on a production clone, then applied pending migrations | live 200; role crawler |
| `/dashboard`, `/dashboard/collections`, shortlist API, student/faculty | `PDOException`, `SQLSTATE[42703]`, missing `literature_drafts.collection_type` and `visibility` | cabinet migration had not been deployed | applied `2026_08_04_000000_build_full_reader_cabinet` after snapshot and clone upgrade | live 200; `FullReaderCabinetTest` |
| scheduled quality scan | `PDOException`, `SQLSTATE[42P01]`, missing `data_quality_scan_runs` | data-quality migration pending | applied `2026_07_31_090000_create_data_quality_control_center` | pending migrations 0 |
| transfer pages/services | `PDOException`, `SQLSTATE[42P01]`, missing `copy_transfers` | circulation workflow migration pending | applied `2026_08_03_000000_create_production_circulation_workflows` | circulation regression suite |
| `/dashboard/search`, student/faculty | `TypeError`: `htmlspecialchars()` received array in `member/search.blade.php` | rich catalogue DTO has nested `title`, `isbn` and `copies`, while compact member view expected scalars | explicit controller-side member search view model | dedicated feature test; live crawler 200 |

No exception was hidden by a catch-all and no feature was replaced with an empty result.

## Environment and deployment

- app and PostgreSQL containers: healthy;
- PHP 8.4.23; Laravel 13.2.0; Node 22.22.1;
- disk: 372 GiB free (16% used);
- storage/logs and bootstrap/cache writable by `www-data`;
- Vite manifest and node_modules present;
- PostgreSQL backup: `/tmp/kazutb-pre-stabilization-20260803T081500Z.dump` inside the postgres container;
- backup size: 4,362,948 bytes;
- SHA-256: `b27632488c5b87eec59e79d80ee77713720942066f356bf66403a050e0ab4355`.

The dump was restored into `digital_library_upgrade_test`; all three pending migrations applied there before production was touched. Production `migrate:status` now has zero pending migrations. `optimize:clear`, compiled-view clearing, permission-cache reset and `queue:restart` completed successfully.

## Data preservation

Counts before and after the production upgrade were identical:

| Table | Before | After |
|---|---:|---:|
| bibliographic_records | 9,562 | 9,562 |
| book_copies | 50,907 | 50,907 |
| users | 9 | 9 |
| reader_profiles | 3 | 3 |
| loans | 5 | 5 |
| reservations | 7 | 7 |
| fines | 1 | 1 |
| reader_notifications | 19 | 19 |
| literature_drafts | 1 | 1 |
| literature_draft_items | 2 | 2 |

The migration backfilled collection metadata and reservation sequencing only. It did not delete loans, holds, fines, incidents, notifications, profiles, catalogue records, copies, barcodes or collection items.

## Automated evidence

- `library:smoke-roles`: 113 requests, HTTP 500 = 0, new ERROR/CRITICAL = 0;
- Chromium live smoke: 9/9 roles passed, including logout and every visible role navigation link;
- final targeted regression suite: 102 tests, 564 assertions, run twice with identical success;
- `RoleNavigationIntegrityTest` + `ProductionSchemaParityTest`: 5 tests, 105 assertions;
- `FullReaderCabinetTest`: 12 tests, 73 assertions;
- `RequestContextMiddlewareTest`: safe production-style 500 response, correlation headers and invalid-ID replacement covered;
- clean PostgreSQL migration and seed are executed through `scripts/dev/test-postgres.sh`;
- Vite production build, Blade view compilation and Pint checks for the stabilization files passed;
- route registry: 353 total; 35 member-surface routes (34 `/dashboard*` plus `/internal/dashboard` redirect).

The final post-smoke interval contains no Laravel `ERROR`/`CRITICAL`, no SQLSTATE, and no nginx/PHP access response with status 500.

## Error handling and observability

`RequestContext` attaches validated `X-Request-Id` and `X-Correlation-Id` values to the request, structured log context and response. Generated context contains route, method, path, actor ID and actor role, but no tokens or personal profile data. Unhandled exceptions still reach Laravel's exception pipeline and the safe production `errors/500.blade.php` response; exception messages and stack traces are not returned to the browser.

The live response check returned HTTP 200 with the supplied `final-live-check` request and correlation IDs. The role crawler additionally fails if it detects a generic error page, HTTP 500, or a newly written Laravel `ERROR`/`CRITICAL` record.

## Controlled live-smoke side effects

The crawler and browser suite are read-only for library business records. Demo authentication and logout updated normal session/audit state (`login.success`, `logout`, `reader_card.viewed`) and demo-user `last_login_at` values. Counts for reader profiles, loans, reservations, fines, notifications, incidents, follows, catalogue records and copies were not changed by the stabilization smoke run.
