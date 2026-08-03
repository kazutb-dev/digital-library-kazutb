<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\Reservation;
use App\Models\User;

class ReservationStateMachine
{
    /**
     * The only supported status mutation path. Callers must already be inside
     * the transaction that locks the reservation and any assigned copy.
     */
    public function transition(
        Reservation $reservation,
        string $to,
        string $event,
        User|array|null $actor = null,
        ?string $reason = null,
        array $changes = [],
    ): Reservation {
        $from = (string) $reservation->status;
        if (! in_array($to, Reservation::TRANSITIONS[$from] ?? [], true)) {
            throw CirculationException::because('reservation_invalid_transition', ['from' => $from, 'to' => $to]);
        }

        $old = $reservation->only(array_keys($changes));
        $reservation->forceFill([...$changes, 'status' => $to])->save();
        $reservation->history()->create([
            'event_type' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor instanceof User ? $actor->getKey() : null,
            'source' => $actor instanceof User ? 'web' : 'system',
            'reason' => $reason,
            'old_values' => $old ?: null,
            'new_values' => $changes ?: null,
            'created_at' => now(),
        ]);

        return $reservation;
    }

    public function record(
        Reservation $reservation,
        string $event,
        User|array|null $actor = null,
        ?string $reason = null,
        array $old = [],
        array $new = [],
    ): void {
        $reservation->history()->create([
            'event_type' => $event,
            'from_status' => $reservation->status,
            'to_status' => $reservation->status,
            'actor_id' => $actor instanceof User ? $actor->getKey() : null,
            'source' => $actor instanceof User ? 'web' : 'system',
            'reason' => $reason,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'created_at' => now(),
        ]);
    }
}
