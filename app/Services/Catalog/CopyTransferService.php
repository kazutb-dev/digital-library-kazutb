<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CopyTransfer;
use App\Models\Catalog\Reservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CopyTransferService
{
    public function __construct(
        private readonly ReservationStateMachine $states,
        private readonly AuditLogger $audit,
        private readonly LibraryNotificationService $notifications,
    ) {}

    public function request(Reservation $reservation, User $actor): CopyTransfer
    {
        return DB::transaction(function () use ($reservation, $actor): CopyTransfer {
            $reservation = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $copy = BookCopy::query()->whereKey($reservation->assigned_copy_id)->lockForUpdate()->firstOrFail();
            if ($reservation->status !== 'confirmed' || $reservation->pickup_branch_id === null || $copy->branch_id === null
                || (int) $reservation->pickup_branch_id === (int) $copy->branch_id) {
                throw CirculationException::because('transfer_not_required');
            }

            $transfer = CopyTransfer::query()->create([
                'transfer_number' => 'TRF-'.now()->format('YmdHis').'-'.$reservation->getKey(),
                'copy_id' => $copy->getKey(),
                'reservation_id' => $reservation->getKey(),
                'source_branch_id' => $copy->branch_id,
                'destination_branch_id' => $reservation->pickup_branch_id,
                'status' => 'requested',
                'requested_by' => $actor->getKey(),
                'requested_at' => now(),
            ]);
            $this->states->transition($reservation, 'in_transit', 'transfer.requested', $actor);
            $this->audit->logRequired('transfer.requested', 'copy_transfer', $transfer->getKey(), newValues: $transfer->only(['copy_id', 'reservation_id', 'source_branch_id', 'destination_branch_id']), scope: 'library', actor: $actor);
            if ($reservation->reader !== null) {
                $this->notifications->sendLocalized($reservation->reader, 'reservation_in_transit', 'librarian.notifications.reservation_confirmed_title', 'librarian.notifications.reservation_confirmed_body', ['title' => (string) $reservation->bibliographicRecord?->title], ['reservation_id' => $reservation->getKey(), 'transfer_id' => $transfer->getKey()]);
            }

            return $transfer;
        });
    }

    public function approve(CopyTransfer $transfer, User $actor): CopyTransfer
    {
        return $this->change($transfer, $actor, 'requested', 'approved', ['approved_by' => $actor->getKey(), 'approved_at' => now()], 'transfer.approved');
    }

    public function send(CopyTransfer $transfer, User $actor): CopyTransfer
    {
        return $this->change($transfer, $actor, 'approved', 'in_transit', ['sent_by' => $actor->getKey(), 'sent_at' => now()], 'transfer.sent');
    }

    public function receive(CopyTransfer $transfer, User $actor, string $scannedCode, ReservationQueueService $reservations): CopyTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $scannedCode, $reservations): CopyTransfer {
            $transfer = CopyTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
            $copy = BookCopy::query()->whereKey($transfer->copy_id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'in_transit') {
                throw CirculationException::because('transfer_not_in_transit');
            }
            if (! in_array($scannedCode, [$copy->barcode, $copy->inventory_number], true)) {
                throw CirculationException::because('transfer_scan_mismatch');
            }
            $transfer->update([
                'status' => 'received', 'received_by' => $actor->getKey(), 'received_at' => now(),
                'actual_duration_minutes' => max(0, (int) $transfer->sent_at?->diffInMinutes(now())),
            ]);
            $copy->update(['branch_id' => $transfer->destination_branch_id]);
            $reservations->markReady($transfer->reservation, $actor);
            $this->audit->logRequired('transfer.received', 'copy_transfer', $transfer->getKey(), newValues: ['branch_id' => $transfer->destination_branch_id], scope: 'library', actor: $actor);

            return $transfer;
        });
    }

    public function cancel(CopyTransfer $transfer, User $actor, string $reason): CopyTransfer
    {
        if ($transfer->status === 'in_transit') {
            throw CirculationException::because('transfer_already_sent');
        }

        return $this->change($transfer, $actor, $transfer->status, 'cancelled', ['cancel_reason' => $reason], 'transfer.cancelled', $reason);
    }

    private function change(CopyTransfer $transfer, User $actor, string $from, string $to, array $values, string $event, ?string $reason = null): CopyTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $from, $to, $values, $event, $reason): CopyTransfer {
            $transfer = CopyTransfer::query()->whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();
            if ($transfer->status !== $from) {
                throw CirculationException::because('transfer_invalid_transition');
            }
            $transfer->update([...$values, 'status' => $to]);
            $this->audit->logRequired($event, 'copy_transfer', $transfer->getKey(), newValues: ['status' => $to], reason: $reason, scope: 'library', actor: $actor);

            return $transfer;
        });
    }
}
