<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for bibliographic records.
 *
 * Note that plain catalogue *search* is deliberately not gated here: guests
 * browse a limited catalogue without any role at all, so public routes must
 * not consult this policy. `search()` exists for authenticated surfaces that
 * expose the richer member-facing search.
 */
class CatalogPolicy
{
    public function search(User $user): bool
    {
        return $user->can('catalog.search');
    }

    public function viewFullMetadata(User $user): bool
    {
        return $user->can('catalog.view_full_metadata');
    }

    public function viewUdc(User $user): bool
    {
        return $user->can('catalog.view_udc');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.create_record');
    }

    public function update(User $user, mixed $record = null): bool
    {
        return $user->can('catalog.edit_record');
    }

    public function delete(User $user, mixed $record = null): bool
    {
        return $user->can('catalog.delete_record');
    }

    public function mergeDuplicates(User $user): bool
    {
        return $user->can('catalog.merge_duplicates');
    }

    public function import(User $user): bool
    {
        return $user->can('catalog.import');
    }

    public function createCopy(User $user): bool
    {
        return $user->can('copies.create');
    }

    public function updateCopy(User $user, mixed $copy = null): bool
    {
        return $user->can('copies.edit');
    }

    public function deleteCopy(User $user, mixed $copy = null): bool
    {
        return $user->can('copies.delete');
    }
}
