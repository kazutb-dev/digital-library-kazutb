# Library business processes

This document describes the workflows implemented in the current application code. It is not a target-state roadmap and does not assert production row counts.

## Domain map

| Process | Canonical records | Write owner | Detailed document |
|---|---|---|---|
| Cataloguing | `bibliographic_records`, translations, contributors, subjects, electronic materials | cataloguing controllers and data-quality services | catalogue forms and recovery runbook |
| Acquisition | `acquisition_orders`, `acquisition_batches`, batch items | `LibrarianWorkspaceService`, `AcquisitionService` | [ACQUISITIONS.md](./ACQUISITIONS.md) |
| Copy registry and stocktake | `book_copies`, copy history, inventory sessions/items/scans | copy, barcode, movement, write-off and inventory services | [INVENTORY.md](./INVENTORY.md) |
| KSU accounting | `ksu_books`, sequences, entries, entry items, conflicts and audit events | acquisition, write-off and KSU review services | [KSU.md](./KSU.md) |
| Circulation | reader profiles, loans, reservations, fines, incident cases and copy transfers | circulation, reservation, incident and transfer services | [CIRCULATION.md](./CIRCULATION.md) |
| Operational and official reporting | report definitions, live aggregates, immutable snapshots and export jobs | report services | [REPORTS.md](./REPORTS.md) |
| Recovered source evidence | legacy import batches, immutable MARC records/fields/copies, quarantine and conflicts | recovery tools; raw source is read-only | [Library recovery operations runbook](../operations/LIBRARY_RUNBOOK.md) |

## End-to-end physical-item flow

1. A bibliographic record exists in the catalogue. A draft record is not reservable from the member cabinet.
2. A purchase/request can be recorded as an acquisition order. Receipt against an order increments the received quantity and changes the order to partially or fully received; that receipt counter does not itself create copies.
3. An acquisition batch selects existing bibliographic records and supplies quantity, price, accounting type, access restriction and placement information. The batch remains editable while `draft`.
4. Confirmation of the batch atomically creates one posted KSU-1 arrival entry, allocates inventory numbers and barcodes, creates every copy and KSU item, records copy history and audit events, and sets the batch to `confirmed`. A failure rolls back the whole confirmation.
5. The copy registry manages physical placement, barcode evidence and controlled lifecycle changes. A compatibility `status` is mirrored into distinct inventory and circulation states by the `BookCopy` model.
6. A member can reserve an edition. Staff can assign a concrete copy, move the request through the reservation queue and, when necessary, operate an audited inter-branch transfer.
7. The circulation desk issues a concrete copy to an eligible reader. The due date is derived from configured scarcity tiers unless a specifically authorized, reasoned override is used.
8. Return closes the loan, offers an ordinary returned copy to the reservation queue before making it generally available, and can create overdue fines or a lost/damaged incident case.
9. Write-off is a controlled, irreversible copy transition in the current workflow. It cancels active holds, records copy/audit history and creates a posted KSU-2 withdrawal entry in the same transaction.
10. Operational reports read these same canonical tables. Official reports freeze a canonical JSON payload and SHA-256 evidence before approval and export.

## Recovery-to-operation flow

Recovered MARC and inventory source rows are evidence, not a second working catalogue.

- `legacy_marc_records`, `legacy_marc_fields` and `legacy_marc_copies` retain source hashes and payloads.
- Catalogue and copy records carry links and recovered attributes used by normal workflows.
- Ambiguous `T090w` / `fund_raw`, orphan copies and source/current differences remain review queues.
- The recovery administration control plane is read-only. Audited decisions are handed to the librarian recovery workflow.
- `INV.T990t` is the authoritative legacy KSU value. Missing legacy KSU values are not synthesized.

## Transaction and audit rules

The implemented write services use database transactions and row locks around contested state:

- issue, return and renewal lock the loan/copy or reader-dependent rows they mutate;
- acquisition confirmation locks the batch, KSU sequence and inventory sequences;
- stocktake start/scan/verification and approval lock the session and affected rows;
- KSU legacy-group resolution locks the group and target entries/copies;
- fund movement and write-off lock all resolved copies in a stable order;
- official-report approval locks a report lineage.

Material mutations write application audit records. Copy operations additionally write `copy_history`; KSU operations write `ksu_audit_events`; reservation transitions write reservation history. Recovery raw MARC is not rewritten by operational corrections.

## Responsibility and authorization

Routes enforce authentication, staff/member workspace boundaries and named permissions. Controllers and services add ownership or capability checks for sensitive decisions. The seeded role-to-permission truth is summarized in [ROLE_MATRIX.md](./ROLE_MATRIX.md).

The important separation-of-duties rules implemented in code are:

- a member can only renew their own loan and cancel their own reservation;
- circulation limit and due-date overrides require separate permissions and reasons;
- inventory creation, scanning, review and approval are separate permissions;
- acquisition intake and acquisition confirmation are separate permissions;
- KSU view, queue management and resolution are separate permissions;
- official report approval requires the `director` role, the approval permission, and a different person from the creator/submitter;
- recovered-source technical viewing and recovery decisions are separate permissions.

## Current boundaries that operators must understand

- Acquisition order receipt and acquisition batch confirmation are related but separate operations. The former does not create copies; the latter can reference an order and creates the operational records.
- The `InventorySession::STATUSES` constant retains a `completed` value, but the active service transition is `draft → running → review → approved`.
- KSU-3 reporting is calculated from KSU-1 arrivals and KSU-2 withdrawals grouped by year/fund; the operational services currently post new arrivals to KSU-1 and write-offs to KSU-2.
- The older `ReaderReservationService` accesses external `public.Book`/`public.Reservation` tables for integration compatibility. The `/dashboard` member cabinet and `/librarian` workflows use the canonical Eloquent reservation/loan domain described here.
- Optional datasets in reporting return empty/unavailable results when their tables are absent; report services do not invent figures.

## Code references

- [`AcquisitionService`](../../app/Services/Operations/AcquisitionService.php)
- [`CirculationService`](../../app/Services/Catalog/CirculationService.php)
- [`InventoryService`](../../app/Services/Catalog/InventoryService.php)
- [`KsuOperationsService`](../../app/Services/Operations/KsuOperationsService.php)
- [`ReportRegistry`](../../app/Services/Reports/ReportRegistry.php)
- [`RoleSeeder`](../../database/seeders/RoleSeeder.php)
