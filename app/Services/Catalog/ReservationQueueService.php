<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\CopyTransfer;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Reservation lifecycle (Master.md 13): a reader reserves an EDITION, the
 * system works with copies. Covers all three canonical scenarios — free copy,
 * queue, and no-show expiry — plus the return-desk handoff to the queue.
 */
class ReservationQueueService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LibraryNotificationService $notifications,
        private readonly ReservationInsightService $insights,
        private readonly ReservationStateMachine $states,
    ) {}

    public function create(User $reader, BibliographicRecord $record, ?User $actor = null, ?int $pickupBranchId = null, string $source = 'web'): Reservation
    {
        return DB::transaction(function () use ($reader, $record, $actor, $pickupBranchId, $source): Reservation {
            // Serialise all allocation decisions for one edition. The database
            // partial unique indexes remain the final safety net.
            $record = BibliographicRecord::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $profile = ReaderProfile::forUser($reader);
            if ($profile->status !== 'active') {
                throw CirculationException::because('reader_blocked', ['reason' => (string) $profile->block_reason]);
            }

            // 13.3: no duplicate active reservation on the same edition.
            $duplicate = Reservation::query()
                ->where('user_id', $reader->getKey())
                ->where('bibliographic_record_id', $record->getKey())
                ->active()
                ->exists();
            if ($duplicate) {
                throw CirculationException::because('reservation_duplicate');
            }

            // Lock the reader's active reservations, then count them in PHP:
            // PostgreSQL rejects FOR UPDATE combined with an aggregate.
            $activeCount = Reservation::query()
                ->where('user_id', $reader->getKey())
                ->active()
                ->lockForUpdate()
                ->get(['id'])
                ->count();
            $maxReservations = $profile->effectiveLimit('max_active_reservations', (int) Setting::valueFor('max_active_reservations', 3));
            if ($activeCount >= $maxReservations) {
                throw CirculationException::because('reservation_limit_reached');
            }

            if ((bool) Setting::valueFor('overdue_blocking_enabled', true)) {
                $hasOverdue = Loan::query()->open()->where('user_id', $reader->getKey())
                    ->get()
                    ->contains(fn (Loan $loan): bool => $loan->status === 'overdue' || $loan->isOverdue());
                if ($hasOverdue) {
                    throw CirculationException::because('reader_has_overdue');
                }
            }
            if ((bool) Setting::valueFor('reservation_blocking_on_fines', true)
                && Fine::query()->where('user_id', $reader->getKey())->where('status', 'pending')->lockForUpdate()->exists()) {
                throw CirculationException::because('reader_has_pending_debt');
            }
            if (CirculationIncidentCase::query()->open()->where('reader_id', $reader->getKey())->lockForUpdate()->exists()) {
                throw CirculationException::because('reader_has_open_incident');
            }

            // Scenario 1: a free copy exists — pin it immediately so nobody
            // else walks away with it; the librarian still confirms manually.
            $copy = BookCopy::query()
                ->where('bibliographic_record_id', $record->getKey())
                ->where('status', 'available')
                ->whereNotIn('condition', ['damaged'])
                ->where('access_restriction', '!=', 'reading_room')
                ->when($pickupBranchId, fn ($query) => $query->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$pickupBranchId]))
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($copy !== null) {
                $manualConfirmation = (bool) Setting::valueFor('reservation_manual_confirmation_required', false);
                $reservation = Reservation::query()->create([
                    'reservation_number' => $this->nextReservationNumber(),
                    'user_id' => $reader->getKey(),
                    'bibliographic_record_id' => $record->getKey(),
                    'assigned_copy_id' => $copy->getKey(),
                    'pickup_branch_id' => $pickupBranchId ?? $copy->branch_id,
                    'current_branch_id' => $copy->branch_id,
                    'status' => $manualConfirmation ? 'pending' : 'confirmed',
                    'confirmed_at' => $manualConfirmation ? null : now(),
                    'copy_assigned_at' => now(),
                    'source' => $source,
                    'created_by' => ($actor ?? $reader)->getKey(),
                ]);
                $copy->update(['status' => 'reserved']);
                $copy->recordHistory('reserved', $reader->getKey(), $actor?->getKey());
                $this->states->record($reservation, 'reservation.created', $actor ?? $reader, new: ['assigned_copy_id' => $copy->getKey()]);
                if (! $manualConfirmation) {
                    $this->states->record($reservation, 'reservation.confirmed', $actor ?? $reader);
                }

                if ((int) $reservation->pickup_branch_id === (int) $copy->branch_id
                    && ! $manualConfirmation) {
                    $this->markReady($reservation, $actor ?? $reader);
                }
            } else {
                // Scenario 2: all copies busy — join the queue.
                if (! (bool) Setting::valueFor('reservation_queue_enabled', true)) {
                    throw CirculationException::because('reservation_queue_disabled');
                }
                $sequence = (int) Reservation::query()->where('bibliographic_record_id', $record->getKey())->max('queue_sequence') + 1;
                $reservation = Reservation::query()->create([
                    'reservation_number' => $this->nextReservationNumber(),
                    'user_id' => $reader->getKey(),
                    'bibliographic_record_id' => $record->getKey(),
                    'pickup_branch_id' => $pickupBranchId,
                    'status' => 'queued',
                    'queued_at' => now(),
                    'queue_sequence' => $sequence,
                    'source' => $source,
                    'created_by' => ($actor ?? $reader)->getKey(),
                ]);
                $this->states->record($reservation, 'reservation.created', $actor ?? $reader);
                $this->states->record($reservation, 'reservation.queued', $actor ?? $reader, new: ['queue_sequence' => $sequence]);
            }

            $this->audit->logRequired(
                actionType: 'reservation.create',
                entityType: 'reservation',
                entityId: $reservation->getKey(),
                oldValues: ['exists' => false],
                newValues: ['exists' => true] + $this->reservationSnapshot($reservation),
                metadata: [
                    'reader_id' => $reader->getKey(),
                    'record_id' => $record->getKey(),
                ],
                scope: 'library',
                actor: $actor ?? $reader,
            );

            $notificationEvent = $reservation->status === 'queued' ? 'reservation_queued' : 'reservation_created';
            $this->notifications->sendLocalized(
                $reader,
                $notificationEvent,
                'librarian.notifications.reservation_created_title',
                'librarian.notifications.reservation_created_body',
                ['title' => $record->title],
                ['reservation_id' => $reservation->getKey(), 'status' => $reservation->status],
            );

            return $reservation->refresh();
        });
    }

    /**
     * 8 action "assign a copy": when $chosenCopy is given the librarian has
     * picked a specific copy by hand instead of letting the system take the
     * first free one.
     */
    public function confirm(Reservation $reservation, User $staff, ?BookCopy $chosenCopy = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff, $chosenCopy): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, ['pending', 'queued'], true)) {
                throw CirculationException::because('reservation_not_pending');
            }
            $oldValues = $this->reservationSnapshot($reservation);

            // A hand-picked copy replaces whatever the system pinned earlier,
            // so the librarian's choice wins even on an already-assigned row.
            if ($chosenCopy !== null && (int) $chosenCopy->getKey() !== (int) $reservation->assigned_copy_id) {
                $this->assignCopy($reservation, $chosenCopy, $staff);
            }

            if ($reservation->assigned_copy_id === null) {
                $copy = BookCopy::query()
                    ->where('bibliographic_record_id', $reservation->bibliographic_record_id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();
                if ($copy === null) {
                    throw CirculationException::because('reservation_no_copy');
                }
                $reservation->assigned_copy_id = $copy->getKey();
                $copy->update(['status' => 'reserved']);
                $copy->recordHistory('reserved', $reservation->user_id, $staff->getKey());
            }

            $this->states->transition($reservation, 'confirmed', 'reservation.confirmed', $staff, changes: [
                'queue_position' => null,
                'confirmed_at' => now(),
                'copy_assigned_at' => $reservation->copy_assigned_at ?? now(),
            ]);

            $this->audit->logRequired(
                actionType: 'reservation.confirm',
                entityType: 'reservation',
                entityId: $reservation->getKey(),
                oldValues: $oldValues,
                newValues: $this->reservationSnapshot($reservation->fresh()),
                scope: 'library',
                actor: $staff,
            );

            if ($reservation->reader !== null) {
                $this->notifications->sendLocalized(
                    $reservation->reader,
                    'reservation_confirmed',
                    'librarian.notifications.reservation_confirmed_title',
                    'librarian.notifications.reservation_confirmed_body',
                    ['title' => (string) $reservation->bibliographicRecord?->title],
                    ['reservation_id' => $reservation->getKey()],
                );
            }

            return $reservation;
        });
    }

    /**
     * The copy has been physically pulled from the stacks and waits at the
     * desk. Starts the pickup-hold countdown.
     */
    public function markReady(Reservation $reservation, User|array|null $staff = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, ['confirmed', 'in_transit'], true) || $reservation->assigned_copy_id === null) {
                throw CirculationException::because('reservation_not_confirmable');
            }
            $oldValues = $this->reservationSnapshot($reservation);

            $lifespanDays = (int) Setting::valueFor('reservation_hold_days', Setting::valueFor('reservation_lifespan_days', 1));
            $this->states->transition($reservation, 'ready_for_pickup', 'reservation.ready', $staff, changes: [
                'queue_position' => null,
                'ready_at' => now(),
                'expires_at' => now()->addDays(max(1, $lifespanDays)),
                'notified_at' => now(),
                'current_branch_id' => $reservation->pickup_branch_id ?? $reservation->current_branch_id,
            ]);

            $this->audit->logRequired(
                actionType: 'reservation.ready',
                entityType: 'reservation',
                entityId: $reservation->getKey(),
                oldValues: $oldValues,
                newValues: $this->reservationSnapshot($reservation->fresh()),
                scope: 'library',
                actor: $staff,
            );

            if ($reservation->reader !== null) {
                $this->notifications->sendLocalized(
                    $reservation->reader,
                    'reservation_ready',
                    'librarian.notifications.reservation_ready_title',
                    'librarian.notifications.reservation_ready_body',
                    [
                        'title' => (string) $reservation->bibliographicRecord?->title,
                        'until' => ['_date' => (string) $reservation->expires_at?->toIso8601String()],
                    ],
                    ['reservation_id' => $reservation->getKey(), 'expires_at' => $reservation->expires_at?->toIso8601String()],
                );
            }

            return $reservation;
        });
    }

    public function cancel(Reservation $reservation, User $actor, ?string $reason = null, bool $byStaff = false): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor, $reason, $byStaff): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if (! $reservation->isCancellable()) {
                throw CirculationException::because('reservation_not_cancellable');
            }
            if (! $byStaff && (int) $reservation->user_id !== (int) $actor->getKey()) {
                throw CirculationException::because('reservation_not_own');
            }
            $oldValues = $this->reservationSnapshot($reservation);

            $transfer = CopyTransfer::query()
                ->where('reservation_id', $reservation->getKey())
                ->whereIn('status', CopyTransfer::OPEN_STATUSES)
                ->lockForUpdate()
                ->latest('id')
                ->first();
            if ($transfer?->status === 'in_transit') {
                throw CirculationException::because('transfer_already_sent');
            }
            if ($transfer !== null) {
                $transferBefore = $transfer->only(['status', 'cancel_reason']);
                $transfer->update(['status' => 'cancelled', 'cancel_reason' => $reason ?? 'Reservation cancelled']);
                $this->audit->logRequired(
                    actionType: 'transfer.cancel', entityType: 'copy_transfer', entityId: $transfer->getKey(),
                    oldValues: $transferBefore,
                    newValues: $transfer->only(['status', 'cancel_reason']), reason: $reason, scope: 'library', actor: $actor,
                );
            }

            $copy = $reservation->assignedCopy;
            $this->states->transition($reservation, 'cancelled', 'reservation.cancelled', $actor, $reason, [
                'assigned_copy_id' => null,
                'queue_position' => null,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $actor->getKey(),
            ]);
            if ($copy !== null) {
                $this->releaseCopy($copy, $actor);
            }

            $this->audit->logRequired(
                actionType: 'reservation.cancel',
                entityType: 'reservation',
                entityId: $reservation->getKey(),
                oldValues: $oldValues,
                newValues: $this->reservationSnapshot($reservation->fresh()) + [
                    'cancelled_by_role' => $byStaff ? 'staff' : 'reader',
                ],
                reason: $reason,
                scope: 'library',
                actor: $actor,
            );

            if ($byStaff && $reservation->reader !== null) {
                $this->notifications->sendLocalized(
                    $reservation->reader,
                    'reservation_cancelled',
                    'librarian.notifications.reservation_cancelled_title',
                    'librarian.notifications.reservation_cancelled_body',
                    ['title' => (string) $reservation->bibliographicRecord?->title],
                    ['reservation_id' => $reservation->getKey()],
                );
            }

            return $reservation;
        });
    }

    /**
     * 8.3 — stretch the pickup hold by another lifespan window. Allowed only
     * when nobody is next in line: the queue outranks a reader who has not
     * turned up yet.
     */
    public function extend(Reservation $reservation, User $staff): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if ($reservation->status !== 'ready_for_pickup') {
                throw CirculationException::because('reservation_not_extendable');
            }
            if ($this->insights->hasWaitingQueue($reservation)) {
                throw CirculationException::because('reservation_queue_waiting');
            }

            $maximum = max(0, (int) Setting::valueFor('reservation_max_extensions', 1));
            if ($reservation->extension_count >= $maximum) {
                throw CirculationException::because('reservation_extension_limit');
            }
            $extensionHours = max(1, (int) Setting::valueFor('reservation_extension_hours', 24));
            $previous = $reservation->expires_at;

            // Extend from whichever is later — an already-lapsed hold restarts
            // from today rather than landing in the past.
            $base = ($previous !== null && $previous->isFuture()) ? $previous : now();
            $reservation->fill([
                'expires_at' => $base->copy()->addHours($extensionHours),
                'extension_count' => $reservation->extension_count + 1,
            ])->save();
            $this->states->record($reservation, 'reservation.extended', $staff, old: ['expires_at' => $previous], new: ['expires_at' => $reservation->expires_at]);

            $this->audit->logRequired(
                actionType: 'reservation.extend',
                entityType: 'reservation',
                entityId: $reservation->getKey(),
                oldValues: ['expires_at' => $previous?->toIso8601String()],
                newValues: ['expires_at' => $reservation->expires_at?->toIso8601String()],
                scope: 'library',
                actor: $staff,
            );

            if ($reservation->reader !== null) {
                $this->notifications->sendLocalized(
                    $reservation->reader,
                    'reservation_extended',
                    'librarian.notifications.reservation_extended_title',
                    'librarian.notifications.reservation_extended_body',
                    [
                        'title' => (string) $reservation->bibliographicRecord?->title,
                        'until' => ['_date' => (string) $reservation->expires_at?->toIso8601String()],
                    ],
                    ['reservation_id' => $reservation->getKey(), 'expires_at' => $reservation->expires_at?->toIso8601String()],
                );
            }

            return $reservation;
        });
    }

    /**
     * 8 action "hand it to the next in line": the current holder has declined
     * or failed to appear and the librarian releases the copy early, without
     * waiting for the hold to lapse. Refuses when nobody is waiting — use
     * cancel() for that, which returns the copy to the shelf.
     */
    public function passToNext(Reservation $reservation, User $staff, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $staff, $reason): Reservation {
            $reservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($reservation->status, ['confirmed', 'ready_for_pickup'], true) || $reservation->assigned_copy_id === null) {
                throw CirculationException::because('reservation_not_passable');
            }
            if (! $this->insights->hasWaitingQueue($reservation)) {
                throw CirculationException::because('reservation_no_next_in_queue');
            }
            $oldValues = $this->reservationSnapshot($reservation);

            $copy = BookCopy::query()->whereKey($reservation->assigned_copy_id)->lockForUpdate()->first();

            // Released first, so the handover below cannot re-offer the copy to
            // the reservation it was just taken from.
            $this->states->transition($reservation, 'cancelled', 'reservation.passed_to_next', $staff, $reason, [
                'assigned_copy_id' => null,
                'queue_position' => null,
                'expires_at' => null,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $staff->getKey(),
            ]);

            $this->audit->logRequired(
                actionType: 'reservation.pass_to_next',
                entityType: 'reservation',
                entityId: $reservation->getKey(),
                oldValues: $oldValues,
                newValues: $this->reservationSnapshot($reservation->fresh()) + ['released_copy_id' => $copy?->getKey()],
                reason: $reason,
                scope: 'library',
                actor: $staff,
            );

            if ($reservation->reader !== null) {
                $this->notifications->sendLocalized(
                    $reservation->reader,
                    'reservation_cancelled',
                    'librarian.notifications.reservation_passed_title',
                    'librarian.notifications.reservation_passed_body',
                    ['title' => (string) $reservation->bibliographicRecord?->title],
                    ['reservation_id' => $reservation->getKey()],
                );
            }

            if ($copy !== null && $copy->status === 'reserved' && ! $this->offerReturnedCopy($copy, $staff)) {
                $copy->update(['status' => 'available']);
            }

            return $reservation;
        });
    }

    /**
     * 8.2 — record or clear the manual "this copy is travelling from branch X"
     * marker. Informational only: no stock is moved and no workflow starts.
     */
    public function setTransferNote(Reservation $reservation, User $staff, ?int $branchId): Reservation
    {
        $previous = $reservation->pending_transfer_branch_id;
        $reservation->fill(['pending_transfer_branch_id' => $branchId])->save();

        $this->audit->log(
            actionType: 'reservation.transfer_note',
            entityType: 'reservation',
            entityId: $reservation->getKey(),
            oldValues: ['pending_transfer_branch_id' => $previous],
            newValues: ['pending_transfer_branch_id' => $branchId],
            scope: 'library',
            actor: $staff,
        );

        return $reservation->fresh() ?? $reservation;
    }

    /**
     * Called from the issue flow when the assigned reader collects the copy.
     */
    public function fulfill(Reservation $reservation, User $staff, Loan $loan): Reservation
    {
        $oldValues = $this->reservationSnapshot($reservation);
        $this->states->transition($reservation, 'fulfilled', 'reservation.fulfilled', $staff, changes: [
            'queue_position' => null, 'fulfilled_at' => now(), 'expires_at' => null,
        ]);

        $this->audit->logRequired(
            actionType: 'reservation.fulfill',
            entityType: 'reservation',
            entityId: $reservation->getKey(),
            oldValues: $oldValues,
            newValues: $this->reservationSnapshot($reservation->fresh()) + ['loan_id' => $loan->getKey()],
            scope: 'library',
            actor: $staff,
        );

        return $reservation;
    }

    /**
     * Return-desk handoff (13.2 scenario 2): the first queued reservation
     * for this edition claims the returned copy and becomes ready for pickup.
     * Returns false when nobody is waiting.
     */
    public function offerReturnedCopy(BookCopy $copy, User|array|null $staff = null): bool
    {
        $next = Reservation::query()
            ->where('bibliographic_record_id', $copy->bibliographic_record_id)
            ->where('status', 'queued')
            ->whereNull('assigned_copy_id')
            ->orderByDesc('priority')
            ->orderBy('queued_at')
            ->orderBy('queue_sequence')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($next === null) {
            return false;
        }

        $reader = User::query()->whereKey($next->user_id)->lockForUpdate()->first();
        $profile = ReaderProfile::query()->where('user_id', $next->user_id)->lockForUpdate()->first();
        $resolutionReason = null;
        if ($reader === null || ! $reader->is_active || $profile === null || $profile->status !== 'active') {
            $resolutionReason = 'reader_inactive';
        } elseif (Loan::query()->open()->where('user_id', $next->user_id)->lockForUpdate()->get()
            ->contains(fn (Loan $loan): bool => $loan->status === 'overdue' || $loan->isOverdue())) {
            $resolutionReason = 'reader_has_overdue';
        } elseif ((bool) Setting::valueFor('reservation_blocking_on_fines', true)
            && Fine::query()->where('user_id', $next->user_id)->where('status', 'pending')->lockForUpdate()->exists()) {
            $resolutionReason = 'reader_has_pending_debt';
        } elseif (CirculationIncidentCase::query()->open()->where('reader_id', $next->user_id)->lockForUpdate()->exists()) {
            $resolutionReason = 'reader_has_open_incident';
        }

        if ($resolutionReason !== null) {
            // Strict FIFO: do not silently skip an ineligible first reader.
            // Hold the returned copy for an explicit staff decision instead.
            $oldValues = [
                'reservation' => $this->reservationSnapshot($next),
                'copy' => $copy->only(['status', 'condition']),
            ];
            $next->update(['requires_resolution' => true, 'resolution_reason' => $resolutionReason]);
            $copy->update(['status' => 'reserved_stock']);
            $this->states->record($next, 'reservation.requires_resolution', $staff, new: ['reason' => $resolutionReason]);
            $this->audit->logRequired(
                actionType: 'reservation.requires_resolution', entityType: 'reservation', entityId: $next->getKey(),
                oldValues: $oldValues,
                newValues: [
                    'reservation' => $this->reservationSnapshot($next->fresh()),
                    'copy' => $copy->fresh()->only(['status', 'condition']),
                    'reason' => $resolutionReason,
                ], scope: 'library', actor: $staff,
            );
            if ($reader !== null) {
                $this->notifications->sendLocalized(
                    $reader, 'reservation_requires_resolution',
                    'librarian.notifications.reservation_created_title',
                    'librarian.notifications.reservation_created_body',
                    ['title' => (string) $next->bibliographicRecord?->title],
                    ['reservation_id' => $next->getKey(), 'requires_resolution' => true],
                );
            }

            return true;
        }

        $this->states->transition($next, 'confirmed', 'reservation.copy_assigned', $staff, changes: [
            'assigned_copy_id' => $copy->getKey(), 'copy_assigned_at' => now(),
            'confirmed_at' => now(), 'current_branch_id' => $copy->branch_id,
            'requires_resolution' => false, 'resolution_reason' => null,
        ]);
        $copy->update(['status' => 'reserved']);
        $copy->recordHistory('reserved', $next->user_id, $staff instanceof User ? $staff->getKey() : null);
        if ($next->pickup_branch_id !== null && (int) $next->pickup_branch_id !== (int) $copy->branch_id
            && (bool) Setting::valueFor('reservation_interbranch_transfer_enabled', true)) {
            // A real transfer must be explicitly requested/sent/received by staff.
            return true;
        }
        $this->markReady($next, $staff);

        return true;
    }

    /**
     * Scheduled sweep (13.2 scenario 3): expired pickup holds are released —
     * the copy goes to the next reader in the queue or back to the shelf.
     *
     * @return array{expired: int}
     */
    public function sweepExpired(): array
    {
        $expired = 0;

        $reminderHours = max(1, (int) Setting::valueFor('reservation_expiry_reminder_hours', 24));
        Reservation::query()->where('status', 'ready_for_pickup')
            ->whereBetween('expires_at', [now(), now()->addHours($reminderHours)])
            ->with(['reader', 'bibliographicRecord'])
            ->chunkById(50, function ($holds): void {
                foreach ($holds as $hold) {
                    if ($hold->reader === null) {
                        continue;
                    }
                    $this->notifications->sendLocalized(
                        $hold->reader, 'reservation_expiry_reminder',
                        'librarian.notifications.reservation_ready_title',
                        'librarian.notifications.reservation_ready_body',
                        ['title' => (string) $hold->bibliographicRecord?->title, 'until' => ['_date' => (string) $hold->expires_at?->toIso8601String(), 'format' => 'datetime']],
                        ['reservation_id' => $hold->getKey(), 'expires_at' => $hold->expires_at?->toIso8601String()],
                    );
                }
            });

        Reservation::query()
            ->where('status', 'ready_for_pickup')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with(['reader', 'bibliographicRecord', 'assignedCopy'])
            ->chunkById(50, function ($reservations) use (&$expired): void {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, &$expired): void {
                        $locked = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->first();
                        if ($locked === null || $locked->status !== 'ready_for_pickup' || $locked->expires_at?->isFuture()) {
                            return;
                        }
                        $copy = $locked->assignedCopy !== null
                            ? BookCopy::query()->whereKey($locked->assigned_copy_id)->lockForUpdate()->first()
                            : null;
                        if ($copy?->activeLoan()->exists()) {
                            return;
                        }
                        $oldValues = [
                            'reservation' => $this->reservationSnapshot($locked),
                            'copy' => $copy?->only(['status', 'condition']),
                        ];
                        $this->states->transition($locked, 'expired', 'reservation.expired', ['name' => 'Scheduler', 'role' => 'system'], changes: [
                            'assigned_copy_id' => null, 'expired_at' => now(), 'expires_at' => null,
                        ]);
                        $expired++;

                        $this->audit->logRequired(
                            actionType: 'reservation.expire',
                            entityType: 'reservation',
                            entityId: $locked->getKey(),
                            oldValues: $oldValues,
                            newValues: [
                                'reservation' => $this->reservationSnapshot($locked->fresh()),
                                'copy' => $copy?->fresh()?->only(['status', 'condition']),
                            ],
                            scope: 'library',
                            actor: ['name' => 'Scheduler', 'role' => 'system'],
                        );

                        if ($locked->reader !== null) {
                            $this->notifications->sendLocalized(
                                $locked->reader,
                                'reservation_expired',
                                'librarian.notifications.reservation_expired_title',
                                'librarian.notifications.reservation_expired_body',
                                ['title' => (string) $locked->bibliographicRecord?->title],
                                ['reservation_id' => $locked->getKey()],
                            );
                        }

                        if ($copy !== null) {
                            $copy->refresh();
                            if ($copy->status === 'reserved'
                                && ! $this->offerReturnedCopy($copy, ['name' => 'Scheduler', 'role' => 'system'])) {
                                $copy->update(['status' => 'available']);
                            }
                        }
                    });
                }
            });

        return ['expired' => $expired];
    }

    /**
     * Pin a librarian-chosen copy to the reservation, returning whatever the
     * system had pinned before to the queue or the shelf.
     */
    private function assignCopy(Reservation $reservation, BookCopy $chosen, User $staff): void
    {
        $chosen = BookCopy::query()->whereKey($chosen->getKey())->lockForUpdate()->firstOrFail();

        if ((int) $chosen->bibliographic_record_id !== (int) $reservation->bibliographic_record_id) {
            throw CirculationException::because('reservation_copy_mismatch');
        }
        if ($chosen->status !== 'available') {
            throw CirculationException::because('reservation_copy_unavailable');
        }

        $previousId = $reservation->assigned_copy_id;

        $reservation->fill(['assigned_copy_id' => $chosen->getKey(), 'copy_assigned_at' => now(), 'current_branch_id' => $chosen->branch_id])->save();
        $chosen->update(['status' => 'reserved']);
        $chosen->recordHistory('reserved', $reservation->user_id, $staff->getKey());

        $this->audit->logRequired(
            actionType: 'reservation.assign_copy',
            entityType: 'reservation',
            entityId: $reservation->getKey(),
            oldValues: ['assigned_copy_id' => $previousId],
            newValues: ['assigned_copy_id' => $chosen->getKey()],
            scope: 'library',
            actor: $staff,
        );

        if ($previousId === null) {
            return;
        }

        $previous = BookCopy::query()->whereKey($previousId)->lockForUpdate()->first();
        if ($previous !== null && $previous->status === 'reserved' && ! $this->offerReturnedCopy($previous, $staff)) {
            $previous->update(['status' => 'available']);
        }
    }

    private function releaseCopy(BookCopy $copy, User $actor): void
    {
        $copy = BookCopy::query()->whereKey($copy->getKey())->lockForUpdate()->first();
        if ($copy !== null && $copy->status === 'reserved') {
            if (! $this->offerReturnedCopy($copy, $actor)) {
                $copy->update(['status' => 'available']);
            }
        }
    }

    /** @return array<string, mixed> */
    private function reservationSnapshot(Reservation $reservation): array
    {
        return [
            'status' => $reservation->status,
            'assigned_copy_id' => $reservation->assigned_copy_id,
            'queue_position' => $reservation->queue_position,
            'queue_sequence' => $reservation->queue_sequence,
            'requires_resolution' => (bool) $reservation->requires_resolution,
            'resolution_reason' => $reservation->resolution_reason,
            'ready_at' => $reservation->ready_at?->toIso8601String(),
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'fulfilled_at' => $reservation->fulfilled_at?->toIso8601String(),
            'cancelled_at' => $reservation->cancelled_at?->toIso8601String(),
            'expired_at' => $reservation->expired_at?->toIso8601String(),
        ];
    }

    private function nextReservationNumber(): string
    {
        return 'RSV-'.now()->format('YmdHisv').'-'.strtoupper(bin2hex(random_bytes(3)));
    }
}
