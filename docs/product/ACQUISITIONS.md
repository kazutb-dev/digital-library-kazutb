# Acquisitions and intake

The application has two related acquisition records with deliberately different effects: an order records the commercial request and receipt progress, while a confirmed intake batch posts stock into the library ledger.

## Acquisition orders

`AcquisitionOrder` and `AcquisitionOrderItem` are managed through `LibrarianWorkspaceService`.

- Creating an order currently creates one order line and calculates `total_amount` from its ordered quantity and unit price.
- A receipt is an increment to `quantity_received`, not an absolute replacement.
- Receipt cannot exceed `quantity_ordered` and is rejected for a cancelled order.
- A positive first receipt must identify a bibliographic record. Once a line has received stock, that record mapping cannot be changed by a later receipt.
- The order becomes `partially_received` when at least one line has been received and `received` when all lines are complete. `received_at` is set only on full receipt.
- Order creation and every receipt are audited.

Registering an order receipt does **not** allocate an inventory number, create a `BookCopy`, or post a KSU entry.

## Intake batches

`AcquisitionBatch` has the implemented statuses `draft`, `confirmed`, and `cancelled`. A draft contains a receipt date and source, optional supplier/currency/branch/fund/order link, and one or more items. Each item selects an existing bibliographic record and captures quantity, price, accounting type, condition, access restriction, numbering modes and placement fields. The seeded acquisitions role can also open the ordinary catalogue create screen from the intake workspace, save a minimal audited draft, and return directly to intake with that title selected for search; it cannot edit existing catalogue records or view raw MARC.

The service calculates and stores title count, copy count and total amount. Drafts can be edited or cancelled. Confirmed or cancelled batches cannot be edited through `AcquisitionService`.

### Atomic confirmation

`AcquisitionService::confirm` locks and re-reads the batch and its lines, then performs the following in one retried database transaction:

1. Returns the already-confirmed batch without duplicating its output.
2. Requires at least one line and an active `KSU-1` book.
3. Allocates the next KSU number for the receipt year.
4. Creates one posted KSU-1 `arrival` entry for the batch totals.
5. Allocates an inventory number and barcode for every physical copy.
6. Creates every `BookCopy` and its `KsuEntryItem` link.
7. Writes copy history, KSU audit events and an application audit event.
8. Sets the batch to `confirmed` with its KSU entry and confirming user.

An allocation, copy, KSU-item or audit failure rolls back the whole confirmation. Confirmation never silently posts a partial batch.

## Number allocation

`InventoryNumberAllocator` serializes automatic allocation on `InventorySequence` rows. The configured scope can be global or branch/year. It compares the sequence counter with observed copy maxima and checks uniqueness before returning a value. Inventory numbers support `auto`, an exact manual list, or a validated range. Barcodes support `auto`, an exact manual list, or `none`. Automatic modes respect `inventory_numbering_enabled` and `barcode_generation_enabled`; manual/none modes remain usable when the corresponding generator is disabled. Counts must exactly match the item quantity, and conflicts inside the batch or against existing copies abort the whole transaction.

The generated forms are:

```text
<inventory prefix>-<year>-<seven-digit sequence>
<barcode prefix><year><eight-digit sequence>
```

The KSU allocator separately returns `<number>/<year>`. Its attended allocation checks `ksu_numbering_enabled`; the legacy `KsuBook::auto_numbering_enabled` flag is intentionally not a gate for an operator-confirmed batch.

## Permissions

| Operation | Permission |
|---|---|
| View acquisition workspace | `acquisitions.view` |
| Create an order | `acquisitions.create_order` |
| Receive an order line | `acquisitions.receive` |
| General order management | `acquisitions.manage` |
| Create a minimal bibliographic draft and return to intake | `catalog.create_record` |
| Create/update/cancel an intake batch | `acquisitions.intake` or `acquisitions.manage` as accepted by the controller |
| Confirm an intake batch | `acquisitions.confirm` or `acquisitions.manage` as accepted by the controller |
| View KSU result | `ksu.view` |
| Acquisition reports | `reports.view_acquisitions`; export additionally requires `reports.export` |

The seeded `acquisitions` role has order, receipt, intake, confirmation, minimal bibliographic-draft creation, KSU view/manage and acquisition-report access. `senior_librarian` can intake and confirm batches, while an ordinary `librarian` is not seeded those two permissions.

## Current integration boundary

An intake batch may carry `acquisition_order_id`, but the code does not automatically synchronize a batch confirmation back into the order's receipt counters. Operators must treat the order receipt and stock-posting batch as two explicit records and reconcile their quantities.

## Code references

- [`LibrarianWorkspaceService`](../../app/Services/Library/LibrarianWorkspaceService.php)
- [`AcquisitionService`](../../app/Services/Operations/AcquisitionService.php)
- [`AcquisitionBatchController`](../../app/Http/Controllers/Librarian/AcquisitionBatchController.php)
- [`InventoryNumberAllocator`](../../app/Services/Operations/InventoryNumberAllocator.php)
- [`KsuNumberAllocator`](../../app/Services/Operations/KsuNumberAllocator.php)
- [`AcquisitionOrder`](../../app/Models/AcquisitionOrder.php)
- [`AcquisitionBatch`](../../app/Models/Operations/AcquisitionBatch.php)
