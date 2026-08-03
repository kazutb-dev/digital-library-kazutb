<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ResolvesRecordOwner;

/**
 * Authorization for the institutional repository of scholarly works.
 *
 * Submission workflow: upload → approve → publish. Authors may always read
 * back their own submission even before it is approved; everyone else needs
 * `repository.read_full` and a published record.
 */
class RepositoryPolicy
{
    use ResolvesRecordOwner;

    public function browseMetadata(User $user): bool
    {
        return $user->can('repository.browse_metadata');
    }

    public function readFull(User $user, mixed $work = null): bool
    {
        if ($this->owns($user, $work, 'author_id', 'submitted_by', 'user_id', 'created_by')) {
            return true;
        }

        if (! $user->can('repository.read_full')) {
            return false;
        }

        // Staff who can approve see submissions still in the queue; ordinary
        // readers see published works only.
        if ($this->isPublished($work) || $work === null) {
            return true;
        }

        return $user->can('repository.approve');
    }

    public function upload(User $user): bool
    {
        return $user->can('repository.upload');
    }

    public function approve(User $user, mixed $work = null): bool
    {
        if (! $user->can('repository.approve')) {
            return false;
        }

        // Reviewing one's own submission is a conflict of interest.
        return ! $this->owns($user, $work, 'author_id', 'submitted_by', 'user_id', 'created_by');
    }

    public function publish(User $user, mixed $work = null): bool
    {
        return $user->can('repository.publish');
    }

    public function remove(User $user, mixed $work = null): bool
    {
        return $user->can('repository.remove');
    }

    private function isPublished(mixed $work): bool
    {
        $status = is_array($work)
            ? ($work['status'] ?? null)
            : (is_object($work) ? ($work->status ?? null) : null);

        if ($status === null) {
            return false;
        }

        return in_array(mb_strtolower((string) $status), ['published', 'public', 'approved'], true);
    }
}
