# KSU registers

KSU accounting is stored in `KsuBook`, `KsuSequence`, `KsuEntry`, `KsuEntryItem`, `KsuConflict` and `KsuAuditEvent`. The operational register shows entries, item links, sequence state and the legacy-review queue.

## Implemented parts

| Register/report | Implemented source |
|---|---|
| KSU-1, arrivals | A confirmed acquisition batch creates one posted `arrival` entry and one item link per new copy. |
| KSU-2, withdrawals | A controlled copy write-off creates one posted `withdrawal` entry and one item link per written-off copy. |
| KSU-3, fund movement summary | `CollectionAccountingReportService` calculates arrivals, write-offs, net copies and net value from KSU entries. There is no current service that posts a separate KSU-3 ledger entry. |

`KsuOperationsService::recordWithdrawal` only records KSU-2 data. It deliberately does not change copy or reservation state; `CopyWriteOffService` owns those changes and calls it inside the same transaction.

## Numbering

`KsuNumberAllocator` locks the sequence for a KSU book and year, compares `last_number` with the maximum numeric entry already observed for that book/year, advances the counter, and returns `number/year`.

- Allocation is rejected when the global `ksu_numbering_enabled` setting is false.
- Years must be between 1900 and 9999.
- Imported entry rows remain authoritative if the cached sequence is stale.
- Attended acquisition/write-off allocation is not blocked by the legacy book's `auto_numbering_enabled` flag.

The allocator writes `allocation_enabled = true` when advancing a sequence. That field therefore describes the current attended allocation row; it is not a promise that unattended legacy repair is enabled.

## Legacy values and conflict review

The recovered `INV.T990t` value is authoritative source evidence. Missing values are not synthesized. Ambiguous or unlinked source values are held in `ksu_conflicts` for review.

The default grouped queue presents one group per untouched raw KSU value. `KsuLegacyReviewService` supports four group decisions:

| Decision | Effect |
|---|---|
| `link_existing` | Link the group's copies to an existing KSU-1 legacy/posted entry. |
| `create_historical` | Create a historical KSU-1 entry from a strictly valid legacy number, then link the copies. |
| `ignore` | Close the group as intentionally ignored with a reason. |
| `leave_unresolved` | Return without a query, timestamp, audit event or mutation. |

Historical creation accepts only a strict positive-number/four-digit-year value in the form `N/YYYY`. It does not repair punctuation or infer a year. Mutation paths require a resolution note, lock the affected rows and write both KSU and application audit evidence.

The individual conflict workflow can set an open conflict to `resolved` or `ignored`. Resolving an `unresolved_link` conflict requires a copy link. A closed conflict cannot be resolved again.

## Register access

The register can filter entries by year, book, status and search text. Entry details expose the KSU header and linked copies/bibliographic records. The page also summarizes books, recent sequences and the open-conflict count.

| Capability | Permission |
|---|---|
| View register, entry and conflict queue | `ksu.view` |
| Queue-management access exposed by the register | `ksu.manage` |
| Apply group or individual resolutions | `ksu.resolve` |

The seeded `acquisitions` role has `ksu.view` and `ksu.manage`, but not `ksu.resolve`. `senior_librarian` and `admin` have all three.

## Audit and invariants

- New acquisition entries and their copy links are written with KSU audit events.
- Withdrawal entries include the act, reason, copy count and linked inventory numbers.
- Legacy review records the raw source value and the operator's reason.
- KSU number allocation is serialized and checks observed rows before incrementing.
- Operational code never rewrites raw MARC or recovered source evidence to make a conflict disappear.

## Code references

- [`KsuRegisterController`](../../app/Http/Controllers/Librarian/KsuRegisterController.php)
- [`KsuNumberAllocator`](../../app/Services/Operations/KsuNumberAllocator.php)
- [`KsuOperationsService`](../../app/Services/Operations/KsuOperationsService.php)
- [`KsuLegacyReviewService`](../../app/Services/Operations/KsuLegacyReviewService.php)
- [`CollectionAccountingReportService`](../../app/Services/Reports/CollectionAccountingReportService.php)
- [`KsuEntry`](../../app/Models/Ksu/KsuEntry.php)
- [Library recovery operations runbook](../operations/LIBRARY_RUNBOOK.md)
