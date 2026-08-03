<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for digital materials.
 *
 * Access is graded: cover → preview → full text. The per-record
 * `access_level` column narrows what the permission alone would allow, so a
 * user needs both the permission and a record open enough to use it.
 * Restricted records additionally require staff-level upload rights.
 */
class DigitalMaterialPolicy
{
    public function viewCover(User $user, mixed $material = null): bool
    {
        return $user->can('digital.view_cover');
    }

    public function viewPreview(User $user, mixed $material = null): bool
    {
        if (! $user->can('digital.view_preview')) {
            return false;
        }

        return $this->accessLevelAllows($user, $material);
    }

    public function readFull(User $user, mixed $material = null): bool
    {
        if (! $user->can('digital.read_full')) {
            return false;
        }

        if (! $this->isActive($material)) {
            return false;
        }

        return $this->accessLevelAllows($user, $material);
    }

    public function upload(User $user): bool
    {
        return $user->can('digital.upload');
    }

    public function setAccessFlags(User $user, mixed $material = null): bool
    {
        return $user->can('digital.set_access_flags');
    }

    public function delete(User $user, mixed $material = null): bool
    {
        return $user->can('digital.delete');
    }

    /**
     * `restricted` records are visible only to users who can manage digital
     * materials in the first place. `public` and `authenticated` records are
     * available to anyone holding the relevant read permission.
     */
    private function accessLevelAllows(User $user, mixed $material): bool
    {
        $level = $this->attribute($material, 'access_level');

        if ($level === null) {
            return true;
        }

        return match (mb_strtolower((string) $level)) {
            'restricted' => $user->can('digital.upload') || $user->can('digital.set_access_flags'),
            default => true,
        };
    }

    private function isActive(mixed $material): bool
    {
        $active = $this->attribute($material, 'is_active');

        return $active === null || (bool) $active;
    }

    private function attribute(mixed $material, string $key): mixed
    {
        if (is_array($material)) {
            return $material[$key] ?? null;
        }

        if (is_object($material)) {
            return $material->{$key} ?? null;
        }

        return null;
    }
}
