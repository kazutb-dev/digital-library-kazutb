# Full PHPUnit baseline — Phase 6

Date: 2026-07-31  
Evidence: `/tmp/phase6-baseline.txt` and `/tmp/phase6-baseline.xml` inside the `app` container.  
Command: `docker compose exec -T app php artisan test --log-junit /tmp/phase6-baseline.xml`

## Baseline before Phase 6 changes

The reproduced baseline is:

- 1,148 tests discovered;
- 759 passed;
- 241 failed/errors;
- 148 skipped;
- 2 risky;
- 3,752 assertions;
- 74.95 seconds.

The earlier report stated 755 passed and 242 failed. The working tree already contained uncommitted fixes from the preceding phase, so one failure had disappeared and four tests had become passing by the time this baseline was captured. The counts above are the reproducible evidence for the actual Phase 6 starting tree.

## Why the suite was not canonical

`phpunit.xml` and `tests/bootstrap.php` forced SQLite `:memory:` while the deployed application uses PostgreSQL. At the same time:

- integration services address `app.documents`, `app.book_copies`, `app.readers`, views and PostgreSQL functions directly;
- migrations create PostgreSQL schemas, partial indexes and constraints;
- many tests explicitly skipped whenever the `pgsql` connection was unavailable;
- several older page tests asserted retired HTML contracts or transitional `/internal/*` pages that now return intentional permanent redirects;
- integration tests expected an allow-listed bearer token that the isolated bootstrap did not consistently provide;
- multiple web tests created only a legacy session payload while newer middleware also resolves a local user, or vice versa.

Consequently, the old command mixed product regressions, incompatible infrastructure, obsolete expectations and missing fixtures in one red result.

## Evidence-based classification of 241 failures/errors

The table is produced by classifying every `<failure>`/`<error>` node in the JUnit file. Categories are mutually exclusive and total exactly 241.

| Category | Count | Evidence/example | Root cause | Module | Disposition |
|---|---:|---|---|---|---|
| SQLite missing legacy schema | 70 | `AccountRenewalTest::test_account_renew_requires_reader_profile`; `no such table: app.readers` | The default test connection is SQLite, but the read/write service uses the PostgreSQL `app` contract | account, circulation, review/stewardship | Move the canonical run to an isolated PostgreSQL database; do not weaken product SQL |
| Database fixture / SQL isolation | 10 | `AccountReservationsTest::test_reservations_with_pagination_params_success` | A database-backed endpoint runs without the tables/fixtures its test assumes, or leaks connection assumptions | account/reservations, identity | Provision explicit test fixtures and keep tests transactional |
| Integration authentication configuration | 45 | `DocumentManagementTest::test_list_success` expected 200, received 401 | Positive integration tests do not present the active allow-listed token/context accepted by the hardened boundary | integration API | Use the canonical bootstrap token and explicit governance headers; retain negative 401 tests |
| Authorization expectation drift | 33 | `CatalogEnrichmentTest::test_check_isbn_pure_validation` expected 200, received 403 | Tests expect pre-hardening access while routes now require staff permissions | stewardship/enrichment/internal API | Decide per route whether the operation is public or privileged; update expectations only to the approved RBAC contract |
| Retired UI/template expectation | 51 | `ResourcesPageTest`, `PublicHomepagePageTest`, renewal modal assertions | HTML assertions target sections/classes removed by later approved UI phases | public shell, resources, account | Reconcile against the current approved content contract; do not restore obsolete markup merely for tests |
| Intentional redirect treated as page | 18 | `/internal/*` tests expected 200/403, received 301 | Transitional internal pages intentionally redirect to canonical librarian pages | internal compatibility routes | Test redirect destination/status; canonical pages own functional assertions |
| Behaviour/response-shape defect or stale expectation | 14 | reservation error envelope, logout/session shape, `/account` versus `/dashboard` | Mixed genuine response-contract defects and superseded expectations | auth/session, reservation, resource ordering | Triage individually; fix product defects and update only demonstrably superseded assertions |

## Skipped tests

All 148 skips were inspected at class level. They are not external-network skips: most say “Live PostgreSQL not available” and cover catalog DB, circulation, copies, reader review, triage and digital materials. Therefore they are not acceptable evidence for a green canonical run. PostgreSQL is now mandatory for the canonical command.

The remaining architectural gap is explicit: migrations create only five `app.*` support tables (`circulation_loans`, `circulation_audit_events`, `integration_idempotency_keys`, `integration_api_log`, `digital_materials`). They do **not** provision the imported legacy `app.documents`, `app.book_copies`, `app.readers` tables and read views. Those tests need a versioned, synthetic legacy-contract fixture or must be migrated to the canonical public-domain models. Cloning development data is forbidden.

## Existing implementation audit

### Already implemented

- `DataCleanupController` and `DataQualityQueues` calculate queues for drafts, missing UDC/author/ISBN/year, language mismatch, likely duplicate titles, manual review, unplaced copies and legacy Kazakh glyphs.
- `DuplicateRecordFinder` warns before creating a likely duplicate.
- `CatalogController` audits individual metadata changes and supports record-level retyping.
- CSV admin import has upload, validation preview, cached plan, transactional commit and CSV-safe exports.
- MARC import tracking maps source document/copy IDs to canonical records.
- bibliography, copies, readers, UDC, audit logging, settings, RBAC and global search already have canonical public-domain models.

### Partial

- quality queues were live SQL counts, not persistent issues;
- duplicate detection was exact-title/ISBN oriented and had no review/merge workflow;
- bulk editing changed records immediately without a persisted dry-run/approval batch;
- MARC tracking recorded completed mappings but had no staging rows, encoding decision, validation or reconciliation;
- encoding tools supported a small glyph list but not persistent occurrence history;
- reports exposed aggregate quality counters but not scan runs, SLA or correction throughput.

### Stubs / absent

- no scan run entity, issue fingerprint or assignee;
- no issue lifecycle, comments, SLA, false-positive or reopen action;
- no transactional merge tombstone/operation record;
- no safe batch rollback;
- no checksum-protected staging import framework;
- no canonical PostgreSQL test wrapper.

### Potentially dangerous operations

- direct legacy bulk update;
- record deletion instead of tombstoning during deduplication;
- importing directly into production tables;
- guessing Kazakh replacements or source encoding;
- rerunning `migrate:fresh` against an unresolved database name;
- using development data as a test fixture.

## Canonical PostgreSQL mode

The new command is:

```bash
composer test:postgres
```

It invokes `scripts/dev/test-postgres.sh`, which:

1. accepts only the explicit database `digital_library_test`;
2. refuses a name without `_test`;
3. refuses equality with the runtime database;
4. resets the `app` schema only after those guards;
5. runs `migrate:fresh --seed`;
6. runs PHPUnit with `phpunit.postgres.xml`;
7. uses per-test transactions through `RefreshDatabase`.

The first clean-migration verification passed. The Phase 6 focused suite passed
8 tests / 51 assertions before the scanner scope regression test was added.

## Full canonical run after introducing the PostgreSQL mode

Command:

```bash
bash scripts/dev/test-postgres.sh --log-junit /tmp/phase6-pg-full.xml
```

Initial PostgreSQL result:

- 1,158 tests;
- 4,118 assertions;
- 93 errors;
- 253 failures;
- 21 risky;
- 0 skipped;
- 99.94 seconds.

This run proves that merely changing the database driver does not make the
historic suite canonical. Every result was retained in JUnit rather than
excluded. The largest mutually identifiable groups are:

| Observable cause | Count | Evidence |
|---|---:|---|
| Missing versioned `app.*` legacy tables/views | 91 | PostgreSQL `42P01`, principally `app.readers`, `app.documents`, `app.book_copies` and related views |
| Positive request received 403 | 65 | pre-RBAC-hardening internal/API expectations |
| Positive request received 401 | 45 | integration token/session contract mismatch |
| Expected page received 302 | 39 | current authentication and canonical dashboard redirects |
| Retired `/internal/*` page received 301 | 18 | intentional compatibility redirects |
| Remaining response/content/fixture differences | 88 | obsolete page markup, seeded-content assumptions and individual response contracts |

The groups total 346 red test cases. The first five groups are classified by
the actual failure text in the JUnit file; the remaining group is the exact
remainder, not a “legacy” label.

This is still a failing quality gate. It must not be represented as green and
the missing imported-schema contract must not be fabricated from production
data. A follow-up stabilization change needs either:

1. a versioned synthetic fixture that implements the documented `app.*`
   integration contract; or
2. migration of those integration services/tests to the canonical public
   domain tables, followed by explicit reconciliation of the approved RBAC and
   current UI contracts.

No tests were excluded or changed merely to hide these failures.

The final repeat on the completed Phase 6 tree discovered two additional Phase
6 regression tests and produced:

- 1,160 tests;
- 4,125 assertions;
- 93 errors;
- 253 failures;
- 21 risky;
- 0 skipped;
- 100.33 seconds.

The red-result classification was unchanged: 91 missing `app.*` relations,
65 responses with 403, 45 with 401, 39 with 302, 18 with 301 and 88 remaining
content/fixture/response-contract differences.

## Focused repeat and random-order evidence

After formatting and the workflow-integrity additions, the Phase 6 suite plus
the environment-isolation guard passed in random order with seed `260731`:
11 tests, 63 assertions, 0 failures. The added regressions ensure:

- full scans and record-level scans use the same canonical entity type and
  therefore the same persistent issue fingerprint;
- merge proposals cannot name records outside the reviewed duplicate group;
- the proposer cannot perform an approval configured as independent.
