<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ResolvesRecordOwner;

/**
 * Authorization for the institutional repository of scholarly works.
 *
 * Submission workflow: authorised library employee → responsible reviewer →
 * library director. Final approval and publication are deliberately tied to
 * the director role rather than to the system-administrator role.
 */
class RepositoryPolicy
{
    use ResolvesRecordOwner;

    public function browseMetadata(User $user): bool
    {
        return $user->is_active && $user->can('repository.browse_metadata');
    }

    public function readFull(User $user, mixed $work = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($this->owns($user, $work, 'uploaded_by', 'author_id', 'submitted_by', 'user_id', 'created_by')) {
            return true;
        }

        if (! $user->can('repository.read_full')) {
            return false;
        }

        // Operational repository staff see submissions in the queue;
        // ordinary readers see released records only.
        if ($this->isPublished($work) || $work === null) {
            return true;
        }

        return $user->can('repository.view_internal');
    }

    public function upload(User $user): bool
    {
        return $this->canContribute($user) && $user->can('repository.upload');
    }

    public function create(User $user): bool
    {
        return $this->canContribute($user) && $user->canAny(['repository.create', 'repository.upload']);
    }

    public function edit(User $user, mixed $work = null): bool
    {
        return $this->canContribute($user) && $user->canAny(['repository.edit', 'repository.upload']);
    }

    public function reviewMetadata(User $user, mixed $work = null): bool
    {
        return $this->canContribute($user) && $user->can('repository.review_metadata');
    }

    public function reviewRights(User $user, mixed $work = null): bool
    {
        return $this->canContribute($user) && $user->can('repository.review_rights');
    }

    public function requestChanges(User $user, mixed $work = null): bool
    {
        return ($this->canContribute($user) || ($user->is_active && $user->hasRole('director')))
            && $user->can('repository.request_changes');
    }

    public function withdraw(User $user, mixed $work = null): bool
    {
        return $user->is_active
            && $user->hasRole('director')
            && $user->canAny(['repository.withdraw', 'repository.remove']);
    }

    public function manageVersions(User $user, mixed $work = null): bool
    {
        return $this->canContribute($user) && $user->can('repository.manage_versions');
    }

    public function approve(User $user, mixed $work = null): bool
    {
        if (! $user->is_active || ! $user->hasRole('director') || ! $user->can('repository.approve')) {
            return false;
        }

        // `uploaded_by` is the repository's canonical conflict-of-interest
        // field. A director cannot approve a record they uploaded themselves.
        return ! $this->owns($user, $work, 'uploaded_by');
    }

    public function publish(User $user, mixed $work = null): bool
    {
        if (! $user->is_active || ! $user->hasRole('director') || ! $user->can('repository.publish')) {
            return false;
        }

        return ! $this->owns($user, $work, 'uploaded_by');
    }

    public function remove(User $user, mixed $work = null): bool
    {
        return $user->is_active && $user->hasRole('director') && $user->can('repository.remove');
    }

    private function isPublished(mixed $work): bool
    {
        $status = is_array($work)
            ? ($work['status'] ?? null)
            : (is_object($work) ? ($work->status ?? null) : null);

        if ($status === null) {
            return false;
        }

        return mb_strtolower((string) $status) === 'published';
    }

    /** System administration alone never implies editorial responsibility. */
    private function canContribute(User $user): bool
    {
        return $user->is_active
            && (! $user->hasRole('admin') || $user->hasAnyRole(['librarian', 'senior_librarian']));
    }
}
