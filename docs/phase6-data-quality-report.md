# Phase 6 — Data Quality Control Center

Date: 2026-07-31

## 1. Initial state and reused components

The project already had `DataCleanupController`, calculated
`DataQualityQueues`, `DuplicateRecordFinder`, record-level audit, CSV import,
MARC source-ID mapping, catalog/copy/reader domain models, UDC reference data,
global search, settings and Spatie RBAC.

Those components were retained. The new center is the persistent workflow above
them; it does not replace circulation, cataloguing or the existing audit log.

Partial areas before this phase were live counters without issue history,
one-shot duplicate warnings without review/merge, immediate bulk changes without
an approval batch, and completed MARC mappings without pre-production staging.
There was no scan-run model, stable issue fingerprint, assignment/SLA workflow,
merge tombstone, safe batch rollback or checksum-protected staging framework.

Potentially dangerous operations identified during the audit were direct bulk
updates, deletion during deduplication, import directly into production,
guessed encoding conversion, and running `migrate:fresh` against an unresolved
database name.

## 2. Database changes

Migration `2026_07_31_090000_create_data_quality_control_center.php` creates:

- `data_quality_scan_runs`;
- `data_quality_issues`;
- `data_quality_issue_comments`;
- `duplicate_groups` and `duplicate_group_members`;
- `record_merge_operations`;
- `data_correction_batches` and `data_correction_batch_items`;
- `data_import_mapping_profiles`;
- `data_import_batches` and `data_import_staging_rows`.

Bibliographic records receive `merged_into_id`, `merge_status` and
`legacy_external_id`. Indexes cover queue status/severity/due date, entity/rule,
scan runs, checksum, batch status and duplicate-group membership. Existing
inventory/barcode uniqueness remains a database constraint and cannot be
disabled by a quality setting.

## 3. Scan and issue lifecycle

`DataQualityScanner` supports full/scoped/record scans, chunking, incremental
post-save scans, scheduled weekly scans, cancellation, per-record error
continuation, persisted progress and prevention of concurrent equal-scope runs.
Commands:

```text
library:data-quality:scan
library:data-quality:scan-record
library:data-quality:recheck
library:data-quality:stats
```

The fingerprint is SHA-256 over canonical entity type, entity ID, rule code and
field. Repeated detection updates occurrence/time instead of inserting another
row. A rule no longer violated resolves on recheck; a later recurrence reopens
the same row and creates a dedicated audit event and notification. Full and
post-save scans now use the same singular entity type.

Issue actions include assign/take, correction with an allowed-field diff and
immediate recheck, false positive with reason, ignore-until, reopen and comment.

## 4. Rule registry and encoding

The versioned registry currently contains 31 executable rules covering:

- bibliographic title, author, year, ISBN, UDC and language;
- missing copy identifiers/location, state conflicts and negative price;
- reader profile/block consistency;
- loan date, fine and reservation process integrity;
- replacement character, null/control bytes, NBSP, mojibake, mixed alphabets
  and legacy Kazakh glyphs.

ISBN-10/13 checksum validation and normalization reuse the existing
`IsbnService`. Suggested normalization is preview-only. The encoding inspector
preserves the source, shows Unicode code points and bytes, and marks only
unambiguous legacy-glyph/whitespace transformations as eligible for a preview.
It never guesses a missing Kazakh letter.

Not every requested heuristic is executable yet; see “Remaining work”.

## 5. Duplicate detection and merge

Duplicate scoring compares normalized ISBN, title, author, year, publisher,
language, UDC and legacy ID. Exact/probable thresholds are settings and the
score is advisory. Volume/part markers and different language editions prevent
an automatic exact classification.

The merge workflow is propose → independent approve → execute. It verifies both
records are members of the reviewed group, locks the operation and records,
rejects already-merged records/cycles and duplicate execution, applies explicit
field choices, moves copies, reservations, electronic materials and relations,
resolves source issues, and soft-deletes the source as a redirecting tombstone.
Before/after snapshots and audit events are stored. Automatic rollback is
deliberately disabled when later circulation cannot be attributed safely.

## 6. Bulk correction

Supported operations are explicitly allow-listed: whitespace/control cleanup,
ISBN/language normalization, branch/fund/category assignment and fill-empty.
The flow persists selection, preview snapshots, warnings, approval, per-item
execution/result and errors. One row failure does not undo successful rows.
Rollback is allowed only while every successful entity still exactly matches
its after-snapshot. System IDs and deletion are not bulk-editable. When
independent approval is enabled, the initiator cannot approve their own batch.

## 7. Import staging / MARC-SQL readiness

Actually parsed formats are CSV and a JSON mapping fixture used by tests.
MARCXML, ISO 2709/MARC21, XLSX and SQL-export are adapter-ready concepts only;
they are not advertised as supported without a verified sample/parser.

Upload computes a checksum, records encoding detection/confidence and manual
selection, converts only from an allowed encoding, maps and normalizes into
staging, validates each row, finds duplicate candidates and leaves production
unchanged. Possible duplicates require an explicit create/update/skip/review
decision. Approval and import are separate roles; import continues per row and
produces reconciliation counts. Re-upload of identical bytes is rejected.

## 8. RBAC

Added permissions:

```text
data_quality.view
data_quality.scan
data_quality.triage
data_quality.correct
data_quality.assign
data_quality.bulk_edit
data_quality.approve_bulk
data_quality.review_duplicates
data_quality.merge
data_quality.approve_merge
data_quality.execute_merge
data_quality.import
data_quality.approve_import
data_quality.manage_rules
data_quality.view_reports
```

Librarians handle individual issues; cataloguers review/propose bibliographic
merges; senior librarians assign and approve/execute controlled operations;
admins operate technical scan/import/mapping functions but do not approve
library merge decisions; directors have analytics without mutation rights.
Members cannot enter the center. Batch snapshots require bulk-edit permission.

## 9. UI, reports, audit and notifications

The `/librarian/data-quality` center provides queue statistics and filters,
issue detail/history/correction, duplicate groups and comparison, correction
batches, staging batches/row decisions and scan tracking. Twenty-six protected
routes are registered.

CSV exports exist for issues, statistics, scan runs, correction batches,
duplicate groups and import reconciliation. The common CSV writer neutralizes
formula-leading cells. Director/librarian overview metrics reuse persistent
quality data.

All required scan, issue, duplicate, merge, bulk and import state changes use
separate `data_quality.*` activity-log events with actor, reason and snapshots.
Notifications include assignment, scan digest, reopen, merge approval, bulk
approval, import review and import-completed-with-errors. Bulk events are sent
as batch/digest notifications, not one notification per issue.

UI and notification labels are present in RU/KK/EN. Rule descriptions currently
fall back to the complete Russian catalogue for untranslated entries.

## 10. Settings

Admin settings expose scan chunk size, bulk limit, duplicate thresholds,
publication-year bounds, rescan interval, staging retention, severity SLA,
bulk/merge approval and allowed import encodings. Defaults are explicitly
described as proposed and configurable, not as library policy.

## 11. Test infrastructure and results

`composer test:postgres` calls `scripts/dev/test-postgres.sh`. The wrapper:

- accepts only `digital_library_test`;
- requires the `_test` suffix;
- refuses equality with the runtime database;
- resets only the isolated database/schema after those checks;
- performs `migrate:fresh --seed`;
- starts PHPUnit with `phpunit.postgres.xml`;
- uses transactions through `RefreshDatabase`.

Clean PostgreSQL migration: **PASS**.

Focused Phase 6 plus environment-isolation suite, random seed `260731`:
**11 tests, 63 assertions, 0 failures**.

Pint for the 29 Phase 6 PHP files: **PASS**.

Live command on the isolated database scanned 13 bibliographic records,
found/created 17 issues and completed in 136 ms. Test/E2E data is transaction
controlled or confined to `digital_library_test`; no production data was used
or deleted.

The final full historic PostgreSQL run is **not green**:

```text
1160 tests, 4125 assertions, 93 errors, 253 failures, 21 risky, 0 skipped
```

Exact classification and examples are in
`docs/testing/full-suite-baseline.md`. The largest causes are 91 missing
versioned `app.*` legacy relations, 110 old auth/RBAC expectations and 57 old
redirect expectations. No tests were excluded or weakened to manufacture a
green result.

## 12. Remaining work / required source material

The following requested items remain incomplete:

- a green full historic quality gate and repeat/random full run;
- a versioned synthetic `app.documents/app.book_copies/app.readers` integration
  contract, or an approved migration of those services to canonical tables;
- reconciliation of retired UI/auth expectations against an approved current
  contract;
- the remaining fine-grained title/author/year/UDC/reader/process heuristics;
- per-rule enable/severity and resource-type ISBN/UDC settings UI;
- scan filters for branch/fund/source and a persisted quality-score trend;
- safe staging rollback/resume and retention cleanup;
- overdue/score-decline scheduled notification digests;
- a parser/adapter for a real MARC-SQL export.

The library must provide a sanitized MARC-SQL schema/export sample, encoding
information, field semantics, stable source keys and examples of volumes,
multilingual editions, copies and relationships before a real SQL/MARC adapter
can be implemented safely.

No commit or push was performed.
