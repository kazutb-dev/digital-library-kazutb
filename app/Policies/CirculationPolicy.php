<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ResolvesRecordOwner;

/**
 * Authorization for issue/return operations and loan history.
 *
 * Members see only their own borrowing history; staff with
 * `circulation.view_any_history` see everyone's.
 */
class CirculationPolicy
{
    use ResolvesRecordOwner;

    public function issue(User $user): bool
    {
        return $user->can('circulation.issue');
    }

    public function return(User $user): bool
    {
        return $user->can('circulation.return');
    }

    public function overrideLimits(User $user): bool
    {
        return $user->can('circulation.override_limits');
    }

    /**
     * Viewing a specific loan record.
     */
    public function view(User $user, mixed $loan): bool
    {
        if ($user->can('circulation.view_any_history')) {
            return true;
        }

        return $user->can('circulation.view_own_history')
            && $this->owns($user, $loan, 'reader_id', 'user_id', 'owner_id');
    }

    /**
     * Listing a history feed. Without a subject the user is asking for their
     * own; with one, they are asking for somebody else's.
     */
    public function viewHistory(User $user, mixed $subject = null): bool
    {
        if ($user->can('circulation.view_any_history')) {
            return true;
        }

        if (! $user->can('circulation.view_own_history')) {
            return false;
        }

        if ($subject === null) {
            return true;
        }

        if (is_string($subject) || is_int($subject)) {
            return in_array((string) $subject, $this->ownerIdentifiers($user), true);
        }

        return $this->owns($user, $subject, 'reader_id', 'user_id', 'id');
    }

    public function renew(User $user, mixed $loan): bool
    {
        if ($user->can('circulation.override_limits')) {
            return true;
        }

        return $this->owns($user, $loan, 'reader_id', 'user_id')
            && $user->can('circulation.view_own_history');
    }
}
