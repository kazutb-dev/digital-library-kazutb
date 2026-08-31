# Circulation and reservations

This document describes the canonical member and librarian workflows backed by `App\Models\Catalog` models and `App\Services\Catalog` services.

## Core records and states

| Record | Implemented states |
|---|---|
| Loan | `active`, `overdue`, `returned`, `lost` |
| Reservation | `pending`, `queued`, `confirmed`, `in_transit`, `ready_for_pickup`, `fulfilled`, `cancelled`, `expired` |
| Fine | pending amounts are resolved as `paid` or `waived` by the fines workflow |
| Copy compatibility status | `available`, `reserved`, `issued`, `overdue`, `lost`, `written_off`, `under_repair`, `in_processing`, `on_display`, `reserved_stock` |
| Copy inventory state | `active`, `damaged`, `repair`, `lost`, `written_off` |
| Copy circulation state | `available`, `reserved`, `on_hold`, `on_loan`, `in_transfer`, `unavailable` |

The `BookCopy` model mirrors changes from the compatibility status into the separated inventory/circulation states. A copy is circulatable only when its inventory state is active and its circulation state is available or reserved.

## Issue workflow

The circulation desk searches readers by name, email, AD login, ticket number or reader-card barcode. Copies are resolved by exact barcode or inventory number. An ISBN lookup can show matching editions, but an ISBN is not accepted as the physical copy code for issue.

`CirculationService::issue` performs the following in one transaction:

1. Locks the copy.
2. Loads or creates the reader profile.
3. Rejects a blocked reader, an open incident when incident blocking is enabled, or any pending debt.
4. Checks the reader's active-loan limit and overdue state. A limit/overdue bypass requires `circulation.override_limits` and is audited with a reason.
5. Rejects a hold owned by another reader or a hold that is not ready for pickup.
6. Verifies both copy state machines and rejects reading-room material for home issue.
7. Calculates the due date from `LoanPeriodPolicy`.
8. Creates the loan, marks the copy issued, increments `issue_count`, writes copy history and fulfills the matching reservation.
9. Writes the audit event.

### Loan-period policy

The default configured tiers are:

| Circulating copies of an edition | Default period | Setting keys |
|---|---:|---|
| 1–2 | 3 days | `loan_period_scarce_max_copies`, `loan_period_scarce_days` |
| 3–5 | 5 days | `loan_period_standard_max_copies`, `loan_period_standard_days` |
| 6+ | 7 days | `loan_period_abundant_days` |

Lost and written-off copies are excluded from the scarcity count. Reading-room stock uses `reference_loan_period_days`, but the issue workflow currently prevents home issue of `reading_room` material.

A manual due date requires `circulation.override_due_date`, a reason and a future date within `manual_due_date_max_days`. When the issue is not fulfilling an assigned reservation and an edition-level queued reservation exists, the manual date cannot extend past the calculated date.

## Return workflow

The return desk requires the concrete copy code, return condition and incident classification (`none`, `damaged` or `lost`). The service locks the copy and its open loan.

- The loan becomes `returned`, or `lost` for a lost-copy return.
- Overdue charges are created once per loan when `fine_per_overdue_day` is greater than zero.
- An operator-supplied positive amount can create a damage or loss fine.
- A lost copy becomes unavailable/lost.
- A damaged copy becomes available, under repair or written off according to the preliminary action.
- A normal returned copy is offered to the reservation queue before it becomes generally available.
- Lost/damaged returns open an incident case by default and retain condition/evidence details.
- Copy history and audit records are written in the transaction.

Fine resolution is a separate operation. `paid` closes the debt; `waived` requires a reason. Both are audited. A pending fine blocks issue and renewal.

## Renewal

Both staff and the member cabinet call `CirculationService::renew`.

Renewal is rejected when:

- the member submits a stale expected due date;
- the loan is closed or overdue;
- renewals are disabled;
- `max_renewals` has been reached (code default: one);
- a member attempts to renew someone else's loan;
- the reader is blocked, has pending debt or has an open incident;
- another reader has an active reservation for the same edition.

The extension uses `renewal_period_days`, falling back to `standard_loan_period_days`. The service writes an audit event and a localized notification.

## Reservation queue

The member cabinet enforces ownership in the controller and uses `ReservationQueueService` for domain rules. A member can reserve a non-draft bibliographic record and choose a pickup branch. Active requests count toward the configured limit.

The implemented transition graph is:

```text
pending ──► queued ──► confirmed ──► in_transit ──► ready_for_pickup ──► fulfilled
   └──────────────────► confirmed ─────────────────► ready_for_pickup
ready_for_pickup ──► expired
every active state ──► cancelled
```

More precisely, the allowed transitions are defined by `Reservation::TRANSITIONS`; fulfilled, cancelled and expired are terminal.

Staff operations include:

- confirm and optionally choose a concrete eligible copy;
- mark a pulled copy ready and start the configured pickup-hold countdown;
- extend a ready hold only when no reader is waiting behind it;
- pass the copy to the next queued reader with a reason;
- cancel a reservation with a reason;
- request, approve, send, receive or cancel an inter-branch transfer.

Transfer receipt verifies the scanned barcode/inventory code before changing the copy's physical branch and continuing the reservation workflow. Transfer and reservation state changes are transactional, audited and recorded in reservation history.

Scheduled `sweepExpired` processing expires elapsed pickup holds and advances the queue. `CirculationService::sweepOverdue` marks loans/copies overdue, notifies overdue readers, and sends a single due-soon notification per loan.

## Member cabinet boundary

The canonical `/dashboard` cabinet scopes loans, reservations, fines, incidents and notifications by the authenticated user's `user_id`. It does not rely on UI filtering for ownership. Reader actions are also rate-limited at the route boundary.

`ReaderReservationService` is a separate integration-compatibility service for external `public.Book`, `public.BookCopy` and `public.Reservation` tables. It is not the service used by the canonical cabinet workflows documented above.

## Permissions

| Capability | Permission |
|---|---|
| Issue / return / staff renewal | `circulation.issue`, `circulation.return`, `circulation.renew` |
| Any-reader history | `circulation.view_any_history` |
| Member own history / renewal | `circulation.view_own_history`, `loans.view_own`, `loans.renew_own` plus controller ownership |
| Limit / due-date override | `circulation.override_limits`, `circulation.override_due_date` |
| Member reservation / own cancellation | `reservation.create`, `reservation.cancel_own` plus ownership |
| Staff queue operations | `reservation.confirm`, `reservation.assign_copy`, `reservation.fulfill`, `reservation.cancel_any` |
| Extend / override queue / transfer | `reservation.extend`, `reservation.override_queue`, `reservation.manage_transfer` |
| View / resolve fines | `fines.view`, `fines.manage`; `fines.waive` is seeded for roles allowed to waive |

The current route for fine resolution is gated by `fines.manage`; the controller itself requires a reason for a waiver.

## Code references

- [`CirculationController`](../../app/Http/Controllers/Librarian/CirculationController.php)
- [`CirculationService`](../../app/Services/Catalog/CirculationService.php)
- [`LoanPeriodPolicy`](../../app/Services/Catalog/LoanPeriodPolicy.php)
- [`ReservationQueueService`](../../app/Services/Catalog/ReservationQueueService.php)
- [`ReservationController`](../../app/Http/Controllers/Librarian/ReservationController.php)
- [`Member cabinet controller`](../../app/Http/Controllers/Member/CabinetController.php)
