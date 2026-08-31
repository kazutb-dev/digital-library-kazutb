# Copy registry, inventory and stock movement

The physical collection is represented by `BookCopy`. Stocktake, barcode marking, placement movement and write-off are controlled workflows layered on that registry; operators should not emulate them by directly editing status or barcode fields.

## Copy state

The model retains a compatibility `status` and mirrors it into two explicit state dimensions:

| Dimension | Values used by the model |
|---|---|
| Compatibility status | `available`, `reserved`, `issued`, `overdue`, `lost`, `written_off`, `under_repair`, `in_processing`, `on_display`, `reserved_stock` |
| Inventory status | `active`, `damaged`, `repair`, `lost`, `written_off` |
| Circulation status | `available`, `reserved`, `on_hold`, `on_loan`, `in_transfer`, `unavailable` |

The copy controller supports filtering, single creation and bulk intake (up to 100 rows), metadata/placement edits and controlled lifecycle actions. A direct barcode replacement and a direct compatibility-status edit are rejected by the update workflow. A written-off copy cannot be restored by the implemented status action.

## Barcode marking

`BarcodeMarkingService` provides the controlled operations:

- assign a generated or operator-scanned value to an unmarked active copy;
- enforce the barcode character/length rule and uniqueness;
- prepare a batch of at most 100 copies, skipping already marked, missing-inventory and inactive copies;
- verify that the physically scanned value exactly matches the stored barcode;
- record that a label was printed.

Assignments, confirmations and printed-label events are written to copy history and the application audit log. The service also re-runs data-quality scanning after assignment.

## Inventory sessions

The active service flow is:

```text
draft ── start ──► running ── complete ──► review ── approve ──► approved
```

`InventorySession::STATUSES` also contains `completed` and `cancelled`, but `InventoryService` does not transition a session into those states in the current start/complete/approve workflow.

### Scope and immutable baseline

A session can target all copies or a branch, fund, storage sigla or service point, with optional room/section/shelf narrowing and a pilot limit. A selected fund must belong to the selected branch. Starting a session:

1. locks the draft;
2. rejects overlap with another `running` or `review` session;
3. selects in-scope copies except written-off copies;
4. saves expected branch, fund, shelf and status into session items in bounded batches;
5. moves the session to `running`.

The rows created at start are the stocktake baseline, and later copy changes do not rewrite their expected fields. Physical verification of an out-of-zone copy can add a `misplaced` item with the evidence observed during the running session.

### Scan and physical verification

An ordinary scan resolves a barcode or inventory number and classifies it as `duplicate`, `unknown`, `written_off`, `misplaced`, `status_conflict` or `found`.

Physical verification deliberately looks up by inventory number, not ISBN. The operator records `visible`, `db_only`, `unreadable` or `mismatch`; a mismatch requires the observed number. The service retains location evidence and handling time. Only a `visible` copy is marked physically verified.

Location confirmation is separate. If the observed session zone differs from the copy record, correction requires both an explicit `apply_correction` confirmation and `copies.edit`. It writes copy history/audit evidence and re-runs data-quality scanning.

Completing a running session recounts outcomes and moves it to `review`. Approval is only accepted from `review`. The export is CSV and contains the baseline/result rows plus unknown, misplaced and written-off scans.

## Fund movement

`FundMovementService` changes confirmed storage placement for one or more exact barcode/inventory codes. It is distinct from a reservation transfer, which transports a reader's held copy between branches.

The movement service:

- resolves every supplied code unambiguously and locks copies in stable ID order;
- validates branch/fund consistency and requires an actual destination change;
- rejects copies with an active loan or active reservation;
- rejects `lost`, `written_off`, `under_repair`, `issued` and `overdue` copies;
- requires a reason and writes a shared movement-batch UUID, copy history and audit events;
- re-runs data-quality scanning after commit.

## Write-off

`CopyWriteOffService` is the canonical multi-copy write-off path. It resolves and locks every requested copy, rejects missing/ambiguous codes, active loans and copies already written off, then:

1. changes each copy to `written_off` with date, act and reason;
2. cancels its active reservations through the reservation service;
3. creates the KSU-2 withdrawal and item links;
4. records copy history and application audit evidence.

All four effects are in one transaction. There is no implemented write-off restore operation.

## Permissions

| Capability | Permission |
|---|---|
| View sessions | `inventory.view` |
| Create/start a session | `inventory.create` |
| Scan and verify | `inventory.scan` |
| Complete/review | `inventory.review` |
| Approve | `inventory.approve` |
| Create/edit copies | `copies.create`, `copies.edit` |
| View/create fund movements | `copies.movements.view`, `copies.movements.create` |
| Write off | `copies.write_off` |
| Mark/print one barcode | `barcodes.print` |
| Batch barcode work | `barcodes.print_batch` |

The seeded ordinary `librarian` can view/scan inventory, edit copies, create movements and perform single barcode work. `senior_librarian` additionally receives inventory create/review/approve, batch barcode and write-off permissions. `admin` has the same controlled capabilities.

## Code references

- [`BookCopy`](../../app/Models/Catalog/BookCopy.php)
- [`CopyController`](../../app/Http/Controllers/Librarian/CopyController.php)
- [`InventoryController`](../../app/Http/Controllers/Librarian/InventoryController.php)
- [`InventoryService`](../../app/Services/Catalog/InventoryService.php)
- [`BarcodeMarkingService`](../../app/Services/Catalog/BarcodeMarkingService.php)
- [`FundMovementService`](../../app/Services/Catalog/FundMovementService.php)
- [`CopyWriteOffService`](../../app/Services/Catalog/CopyWriteOffService.php)
