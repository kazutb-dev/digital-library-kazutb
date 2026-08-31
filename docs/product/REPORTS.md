# Reports and official snapshots

Reporting has four distinct surfaces: permission-scoped librarian reports, collection/KSU accounting forms, immutable official snapshots, and a separate admin system overview. The director dashboard is an executive aggregate, not a replacement for those operational datasets.

## Report registry

`ReportRegistry` is the source of truth for a report's code, dataset description, supported filters/columns/totals/charts, export formats, permission expression, sensitivity class and snapshot support.

### Official definitions

The four definitions that support official snapshots are:

| Code | Canonical subject |
|---|---|
| `acquisitions` | Received stock grouped by source, type, branch, fund, supplier and KSU attributes |
| `fund-usage` | Copy, circulation, reservation and visit aggregates |
| `users` | Restricted user-segment activity aggregates |
| `electronic-resources` | Digital, external-resource and repository usage aggregates |

`acquisitions` allows `reports.view_acquisitions`, `reports.view_ops` or `reports.view_full`; the other official definitions allow `reports.view_ops` or `reports.view_full`.

### Operational definitions

The registry includes:

- circulation/service: `loans`, `returns`, `renewals`, `overdue`, `fines`, `reservations`, `queue`, `incidents`, `lost-damaged`, `inventory`, `visits`;
- governance/content: `data-quality`, `news-events`, `messages`, `staff`, `audit-summary`;
- resources: `repository`, `external-resources`, `electronic-materials`;
- stock operations: `fund-movement`, `new-acquisitions`, `write-offs`;
- collection accounting: `ksu-part-1`, `ksu-part-2`, `ksu-part-3`, `ksu-register`, `acquisition-act`, `inventory-book`, `non-inventory-book`, `new-arrivals`, `fund-by-sigla`, `fund-by-language`, `fund-by-type`, `fund-by-udc`, `acquisitions-by-source-value`, `writeoffs`.

Some similarly named codes are intentionally separate. For example, `write-offs` is the operational copy-history view, while `writeoffs` is the collection-accounting form.

## Librarian report access and exports

The librarian controller validates the requested code against the registry and checks that report's permission expression **before** building its dataset. The route does not use a blanket operations permission because acquisitions, incident, quality and analytics users may receive only their specific aggregate.

Canonical registry reports export to CSV, PDF, XLSX or DOCX. Export and print routes additionally require `reports.export`, re-check the definition permission and audit the operation. The compatibility exports `popular`, `dynamics`, `udc-fund` and `circulation` remain CSV-only and require operations/full report access.

`ReportFilters` normalizes the period and supported dimensions. A definition advertises which additional filters it accepts; unsupported fields are not silently treated as new report semantics.

## Collection accounting

`CollectionAccountingReportService` reads the same catalogue, acquisition and KSU tables used by operational writes.

- KSU-1 and KSU-2 display the posted/legacy entry rows.
- KSU-3 is calculated from arrivals and withdrawals by year/fund; it is not a separately posted register.
- Acquisition acts read intake batches.
- Inventory/non-inventory books and fund facets read copy and bibliographic attributes.
- Write-off forms use controlled copy write-off fields.

Rolling-deployment guards check optional tables and columns. When a required optional source is absent, the service returns an empty canonical form rather than fabricated figures.

## Official snapshot lifecycle

The implemented lifecycle is:

```text
generated ── submit ──► pending_review ── approve ──► approved ── archive ──► archived
                                   └──── reject ────► rejected
approved/rejected/superseded ── revise ──► new generated revision
older approved revision ── when newer revision is approved ──► superseded
```

The model retains a `draft` status for compatibility, but `OfficialReportSnapshotService::create` persists a generated canonical snapshot.

### Evidence and immutability

Creation builds canonical JSON containing schema version, report identity/revision, normalized filters and period, metrics, columns, rows, breakdowns and generation time. It:

1. sorts object keys deterministically and preserves JSON number semantics;
2. calculates SHA-256 over the canonical payload;
3. writes the same JSON to the configured local archive path;
4. stores source/archive hashes and size with the snapshot;
5. verifies database payload and archive file before sensitive transitions/downloads.

Source identity, filters, data, hashes and archive location are immutable after persistence. Approved, superseded and archived rows cannot be updated or deleted through the model. A correction is a new revision, not an edit to approved evidence.

Approval serializes the report lineage. When a newer revision is approved, an older approved revision is atomically marked `superseded`, leaving at most one approved winner in that lineage.

Draft/generated deletion is limited to the creator with `reports.official.delete_draft`; the service verifies integrity and compensates the archive file if the database/audit transaction fails. Approved evidence cannot be deleted.

### Approval policy

| Action | Implemented authorization |
|---|---|
| Create | `reports.official.create` |
| Submit | `reports.official.submit`, status `generated`, and creator or `senior_librarian` |
| Revise | `reports.official.create` and an approved/rejected/superseded source |
| Approve/reject | status `pending_review`, `director` role, `reports.official.approve`, and actor differs from creator and submitter |
| Archive/source access | archive permissions and applicable view policy |
| Export | approved/superseded/archived status and `reports.official.export` |

The seed grants approval only to `director`. `admin` has official create/submit/archive/export/delete-draft permissions but intentionally not approval.

## Official export jobs

Official exports are asynchronous `ReportExportJob` rows with `queued`, `generating`, `ready` or `failed` status.

- A snapshot can be exported only after approval (including superseded/archived evidence).
- Requests derive an idempotency key from snapshot, user, format and optional client key.
- An equivalent queued/generating export is reused instead of duplicated.
- The per-user active limit is configurable; the code default is four.
- Only failed jobs can be retried, and not while an equivalent job is active.
- Download requires a ready job, unexpired retention, an existing file and matching hash/size.
- Queue, retry and download operations are audited.

## Director and admin reporting

`DirectorAnalyticsService` supplies executive cards, trends, distributions, resource usage, staff workload, bottlenecks and alerts. It excludes reader names/IDs, titles from an individual's history, IP addresses and raw event payloads. Staff workload may include staff names. Budget output is explicitly `available: false` with `integration_required`; the service does not invent financial data.

The admin `ReportController` is a separate system overview with the types `user-activity`, `roles`, `news`, `messages`, `digital-materials`, `external-resources`, `repository`, `integrations`, `branches-funds`, `circulation` and `catalog`. It is gated by `reports.view_full`; export additionally requires `reports.export` and supports CSV/PDF. Circulation/catalog integration rows are empty when their application-schema sources are unavailable.

## Code references

- [`ReportRegistry`](../../app/Services/Reports/ReportRegistry.php)
- [`LibraryReportService`](../../app/Services/Reports/LibraryReportService.php)
- [`CollectionAccountingReportService`](../../app/Services/Reports/CollectionAccountingReportService.php)
- [`Librarian ReportController`](../../app/Http/Controllers/Librarian/ReportController.php)
- [`OfficialReportSnapshotService`](../../app/Services/Reports/OfficialReportSnapshotService.php)
- [`OfficialReportExportService`](../../app/Services/Reports/OfficialReportExportService.php)
- [`OfficialReportSnapshotPolicy`](../../app/Policies/OfficialReportSnapshotPolicy.php)
- [`DirectorAnalyticsService`](../../app/Services/Reports/DirectorAnalyticsService.php)
- [`Admin ReportController`](../../app/Http/Controllers/Admin/ReportController.php)
