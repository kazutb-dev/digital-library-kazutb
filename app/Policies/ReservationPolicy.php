<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ResolvesRecordOwner;

/**
 * Authorization for holds/reservations.
 *
 * The interesting rule is `cancel`: staff with `reservation.cancel_any` may
 * cancel anything, while an ordinary member may cancel only a reservation that
 * is theirs, and only while it is still cancellable.
 */
class ReservationPolicy
{
    use ResolvesRecordOwner;

    public function create(User $user): bool
    {
        return $user->can('reservation.create');
    }

    public function cancel(User $user, mixed $reservation): bool
    {
        if ($user->can('reservation.cancel_any')) {
            return true;
        }

        if (! $user->can('reservation.cancel_own')) {
            return false;
        }

        if (! $this->owns($user, $reservation, 'user_id', 'reader_id', 'owner_id')) {
            return false;
        }

        return $this->isCancellable($reservation);
    }

    public function confirm(User $user): bool
    {
        return $user->can('reservation.confirm');
    }

    public function overrideLimits(User $user): bool
    {
        return $user->can('reservation.override_limits');
    }

    /**
     * A reservation that has already been fulfilled or cancelled is terminal;
     * re-cancelling it is a no-op the caller should not be offered. Records
     * without a status are treated as open.
     */
    private function isCancellable(mixed $reservation): bool
    {
        $status = is_array($reservation)
            ? ($reservation['status'] ?? null)
            : (is_object($reservation) ? ($reservation->status ?? null) : null);

        if ($status === null) {
            return true;
        }

        return ! in_array(mb_strtolower((string) $status), ['cancelled', 'canceled', 'fulfilled', 'issued', 'expired'], true);
    }
}
