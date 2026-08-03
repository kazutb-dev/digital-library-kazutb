<?php

namespace App\Services\Admin;

use App\Models\ExternalResource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Parses and validates admin CSV imports (users, external resources) into a
 * normalized row plan: each row becomes create / update / error with the
 * attribute set that a later commit applies verbatim. Parsing never touches
 * the database — the commit step re-validates inside one transaction, so a
 * stale preview can never cause a partial import.
 */
class CsvImportService
{
    public const TYPES = ['users', 'external-resources'];

    private const MAX_ROWS = 1000;

    /**
     * @return array{rows: list<array<string, mixed>>, error: ?string}
     */
    public function parse(string $type, UploadedFile $file, User $importer): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return ['rows' => [], 'error' => __('admin.imports.errors.unreadable')];
        }

        try {
            $first = (string) fgets($handle);
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            $header = array_map(
                static fn (?string $column): string => mb_strtolower(trim((string) $column)),
                str_getcsv($first, $delimiter, '"', '\\'),
            );

            $required = $type === 'users' ? ['email', 'name', 'role'] : ['title', 'url', 'resource_type', 'description'];
            $missing = array_diff($required, $header);
            if ($missing !== []) {
                return ['rows' => [], 'error' => __('admin.imports.errors.missing_columns', ['columns' => implode(', ', $missing)])];
            }

            $rows = [];
            $line = 1;
            while (($raw = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $line++;
                if ($raw === [null] || implode('', array_map(strval(...), $raw)) === '') {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    return ['rows' => [], 'error' => __('admin.imports.errors.too_many_rows', ['max' => self::MAX_ROWS])];
                }
                $record = [];
                foreach ($header as $index => $column) {
                    $record[$column] = trim((string) ($raw[$index] ?? ''));
                }
                $rows[] = ['line' => $line, 'record' => $record];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            return ['rows' => [], 'error' => __('admin.imports.errors.no_rows')];
        }

        $planned = $type === 'users'
            ? $this->planUsers($rows, $importer)
            : $this->planExternalResources($rows);

        return ['rows' => $planned, 'error' => null];
    }

    /**
     * @param  list<array{line: int, record: array<string, string>}>  $rows
     * @return list<array<string, mixed>>
     */
    private function planUsers(array $rows, User $importer): array
    {
        $roleNames = Role::query()->where('guard_name', 'web')->pluck('name')->all();
        $seenEmails = [];
        $planned = [];

        foreach ($rows as $row) {
            $record = $row['record'];
            $errors = [];

            $email = mb_strtolower($record['email'] ?? '');
            $name = $record['name'] ?? '';
            $role = mb_strtolower($record['role'] ?? '');
            $locale = mb_strtolower($record['locale'] ?? '') ?: 'kk';
            $adLogin = mb_strtolower($record['ad_login'] ?? '') ?: null;
            $department = ($record['department'] ?? '') ?: null;

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('admin.imports.row_errors.invalid_email');
            } elseif (isset($seenEmails[$email])) {
                $errors[] = __('admin.imports.row_errors.duplicate_in_file', ['line' => $seenEmails[$email]]);
            }
            $seenEmails[$email] = $row['line'];

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = __('admin.imports.row_errors.invalid_name');
            }

            if (! in_array($role, $roleNames, true)) {
                $errors[] = __('admin.imports.row_errors.unknown_role', ['role' => $role !== '' ? $role : '—']);
            }

            // Privilege-escalation guard: granting the admin role through a
            // CSV requires the same permission as granting it through the UI.
            if ($role === 'admin' && ! $importer->can('roles.manage')) {
                $errors[] = __('admin.imports.row_errors.admin_role_forbidden');
            }

            if (! in_array($locale, ['ru', 'kk', 'en'], true)) {
                $errors[] = __('admin.imports.row_errors.invalid_locale', ['locale' => $locale]);
            }

            $existing = $email !== '' ? User::query()->where('email', $email)->first() : null;

            if ($existing !== null && $existing->hasRole('admin') && $role !== 'admin' && $role !== '') {
                $activeAdmins = User::query()->role('admin')->where('is_active', true)->count();
                if ($existing->is_active && $activeAdmins <= 1) {
                    $errors[] = __('roles.errors.last_active_admin');
                }
            }

            $planned[] = [
                'line' => $row['line'],
                'action' => $errors !== [] ? 'error' : ($existing !== null ? 'update' : 'create'),
                'errors' => $errors,
                'target_id' => $existing?->getKey(),
                'attributes' => [
                    'email' => $email,
                    'name' => $name,
                    'role' => $role,
                    'locale' => $locale,
                    'ad_login' => $adLogin,
                    'department' => $department,
                ],
            ];
        }

        return $planned;
    }

    /**
     * @param  list<array{line: int, record: array<string, string>}>  $rows
     * @return list<array<string, mixed>>
     */
    private function planExternalResources(array $rows): array
    {
        $validRoles = ['guest', ...Role::query()->where('guard_name', 'web')->pluck('name')->all()];
        $seenSlugs = [];
        $planned = [];

        foreach ($rows as $row) {
            $record = $row['record'];
            $errors = [];

            $title = $record['title'] ?? '';
            $url = $record['url'] ?? '';
            $resourceType = mb_strtolower($record['resource_type'] ?? '');
            $description = $record['description'] ?? '';
            $slug = mb_strtolower($record['slug'] ?? '') ?: Str::slug($title);
            $licenseExpiresAt = ($record['license_expires_at'] ?? '') ?: null;
            $isActiveRaw = mb_strtolower($record['is_active'] ?? '');

            if ($title === '' || mb_strlen($title) > 255) {
                $errors[] = __('admin.imports.row_errors.invalid_title');
            }

            if ($url === '' || ! preg_match('/^https?:\/\//i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors[] = __('admin.imports.row_errors.invalid_url');
            }

            if (! in_array($resourceType, ['licensed', 'open', 'partner', 'internal'], true)) {
                $errors[] = __('admin.imports.row_errors.invalid_resource_type', ['type' => $resourceType !== '' ? $resourceType : '—']);
            }

            if ($description === '') {
                $errors[] = __('admin.imports.row_errors.missing_description');
            }

            if ($slug === '') {
                $errors[] = __('admin.imports.row_errors.invalid_title');
            } elseif (isset($seenSlugs[$slug])) {
                $errors[] = __('admin.imports.row_errors.duplicate_in_file', ['line' => $seenSlugs[$slug]]);
            }
            $seenSlugs[$slug] = $row['line'];

            if ($licenseExpiresAt !== null && strtotime($licenseExpiresAt) === false) {
                $errors[] = __('admin.imports.row_errors.invalid_date', ['value' => $licenseExpiresAt]);
            }

            $availableRoles = array_values(array_filter(array_map(
                static fn (string $value): string => mb_strtolower(trim($value)),
                preg_split('/[|,]/', $record['available_roles'] ?? '') ?: [],
            )));
            if ($availableRoles === []) {
                $availableRoles = ['member', 'librarian', 'admin'];
            }
            foreach ($availableRoles as $availableRole) {
                if (! in_array($availableRole, $validRoles, true)) {
                    $errors[] = __('admin.imports.row_errors.unknown_role', ['role' => $availableRole]);
                }
            }

            $existing = ExternalResource::query()->where('slug', $slug)->first();

            $planned[] = [
                'line' => $row['line'],
                'action' => $errors !== [] ? 'error' : ($existing !== null ? 'update' : 'create'),
                'errors' => $errors,
                'target_id' => $existing?->getKey(),
                'attributes' => [
                    'slug' => $slug,
                    'title' => $title,
                    'url' => $url,
                    'resource_type' => $resourceType,
                    'description' => $description,
                    'provider' => ($record['provider'] ?? '') ?: null,
                    'category' => ($record['category'] ?? '') ?: null,
                    'access_instructions' => ($record['access_instructions'] ?? '') ?: null,
                    'license_expires_at' => $licenseExpiresAt,
                    'available_roles' => $availableRoles,
                    'is_active' => $isActiveRaw === '' || in_array($isActiveRaw, ['1', 'true', 'yes', 'да'], true),
                ],
            ];
        }

        return $planned;
    }

    /**
     * Applies one planned user row. Must run inside the commit transaction.
     *
     * @param  array<string, mixed>  $row
     */
    public function applyUserRow(array $row): void
    {
        $attributes = $row['attributes'];
        $role = Role::findByName($attributes['role'], 'web');
        $legacyRole = match ($role->name) {
            'admin' => 'admin',
            'member' => 'reader',
            default => 'librarian',
        };

        if ($row['action'] === 'update') {
            $user = User::query()->whereKey($row['target_id'])->lockForUpdate()->firstOrFail();
            $user->update(array_filter([
                'name' => $attributes['name'],
                'role' => $legacyRole,
                'role_source' => 'manual',
                'locale' => $attributes['locale'],
                'ad_login' => $attributes['ad_login'],
                'department' => $attributes['department'],
            ], static fn (mixed $value): bool => $value !== null));
        } else {
            // Imported accounts get an unusable random password: sign-in
            // works via LDAP or after an explicit admin password reset.
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'ad_login' => $attributes['ad_login'],
                'department' => $attributes['department'],
                'password' => Hash::make(Str::random(48)),
                'auth_provider' => $attributes['ad_login'] !== null ? 'ldap' : 'demo',
                'role_source' => 'manual',
                'role' => $legacyRole,
                'locale' => $attributes['locale'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $user->syncRoles([$role]);
    }

    /**
     * Applies one planned external-resource row. Must run inside the commit
     * transaction.
     *
     * @param  array<string, mixed>  $row
     */
    public function applyExternalResourceRow(array $row): void
    {
        $attributes = $row['attributes'];

        if ($row['action'] === 'update') {
            ExternalResource::query()
                ->whereKey($row['target_id'])
                ->lockForUpdate()
                ->firstOrFail()
                ->update($attributes);
        } else {
            ExternalResource::query()->create($attributes);
        }
    }
}
