<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Shared ownership resolution for the library policies.
 *
 * Library records live in the Postgres `app.` schema and identify their owner
 * by reader identity, not by a `users.id` foreign key — readers are imported
 * from the CRM and the local `users` table mirrors them by AD login. Ownership
 * is therefore matched against any of the identifiers a user can legitimately
 * carry, and a record with no resolvable owner is never treated as owned.
 */
trait ResolvesRecordOwner
{
    /**
     * Identifiers under which the given user may appear as a record owner.
     *
     * @return list<string>
     */
    protected function ownerIdentifiers(User $user): array
    {
        return array_values(array_filter(array_unique([
            $user->external_id !== null ? (string) $user->external_id : null,
            $user->ad_login !== null ? (string) $user->ad_login : null,
            (string) $user->getKey(),
        ]), static fn (?string $value): bool => $value !== null && $value !== ''));
    }

    /**
     * Whether $record belongs to $user.
     *
     * Accepts models, arrays and plain objects so policies stay usable for the
     * parts of the domain that are still served as raw service payloads.
     */
    protected function owns(User $user, mixed $record, string ...$ownerKeys): bool
    {
        if ($record === null) {
            return false;
        }

        $keys = $ownerKeys !== [] ? $ownerKeys : ['user_id', 'reader_id', 'owner_id', 'created_by'];
        $identifiers = $this->ownerIdentifiers($user);

        if ($identifiers === []) {
            return false;
        }

        foreach ($keys as $key) {
            $value = $this->extract($record, $key);

            if ($value === null || $value === '') {
                continue;
            }

            if (in_array((string) $value, $identifiers, true)) {
                return true;
            }
        }

        return false;
    }

    private function extract(mixed $record, string $key): mixed
    {
        if (is_array($record)) {
            return $record[$key] ?? null;
        }

        if (is_object($record)) {
            // Eloquent returns null for absent attributes rather than throwing.
            return $record->{$key} ?? null;
        }

        return null;
    }
}
