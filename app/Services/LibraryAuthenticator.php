<?php

namespace App\Services;

use App\Directory\ActiveDirectoryProvisioner;
use App\Directory\ActiveDirectoryService;
use App\Exceptions\ActiveDirectoryException;
use App\Exceptions\LibraryAuthenticationException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class LibraryAuthenticator
{
    public function __construct(
        private readonly AuthSessionManager $sessions,
        private readonly AuditLogger $audit,
        private readonly ActiveDirectoryService $activeDirectory,
        private readonly ActiveDirectoryProvisioner $activeDirectoryProvisioner,
    ) {}

    /**
     * @return array{user: User, session_user: array<string, mixed>, landing: string}
     */
    public function authenticate(
        Request $request,
        string $identifier,
        string $password,
        string $deviceName = 'web',
    ): array {
        $identifier = trim($identifier);
        $breakGlass = $this->authenticateWithBreakGlass($request, $identifier, $password);
        if ($breakGlass !== null) {
            return $breakGlass;
        }
        if ((bool) config('active_directory.enabled')) {
            return $this->authenticateWithActiveDirectory($request, $identifier, $password);
        }

        $authApiUrl = trim((string) config('services.external_auth.login_url', ''));
        if ($authApiUrl === '') {
            $this->failed($identifier, 'provider_not_configured', $request);
            throw new LibraryAuthenticationException(__('auth.provider_unavailable'), 503);
        }

        try {
            $payload = str_contains($identifier, '@')
                ? ['email' => $identifier]
                : ['login' => $identifier];
            $response = Http::timeout(12)->acceptJson()->post($authApiUrl, $payload + [
                'password' => $password,
                'device_name' => $deviceName,
            ]);
        } catch (\Throwable) {
            $this->failed($identifier, 'provider_unavailable', $request);
            throw new LibraryAuthenticationException(__('auth.provider_unavailable'), 503);
        }

        if (! $response->successful()) {
            $this->failed($identifier, 'invalid_credentials', $request, ['provider_status' => $response->status()]);
            throw new LibraryAuthenticationException(__('auth.invalid_credentials'));
        }

        $payload = $response->json();
        $token = (string) ($payload['token'] ?? $payload['access_token'] ?? '');
        $rawUser = $payload['user'] ?? $payload['data']['user'] ?? [];

        if ($token === '' || ! is_array($rawUser)) {
            $this->failed($identifier, 'invalid_provider_response', $request);
            throw new LibraryAuthenticationException(__('auth.provider_invalid_response'), 502);
        }

        try {
            $user = $this->provisionLdapUser($rawUser, $identifier, $request);
        } catch (LibraryAuthenticationException $exception) {
            $this->failed($identifier, 'local_identity_rejected', $request, [
                'local_status' => $exception->status,
            ]);

            throw $exception;
        }
        $sessionUser = $this->sessions->login(
            $request,
            $user,
            isset($rawUser['profile_type']) ? (string) $rawUser['profile_type'] : null,
        );
        $request->session()->put('library.crm_token', $token);

        return [
            'user' => $user,
            'session_user' => $sessionUser,
            'landing' => $this->sessions->landing($user),
        ];
    }

    /**
     * Emergency/local password login for accounts explicitly flagged with
     * auth_source = 'local_break_glass'. Returns null (defer to the normal
     * provider) when the feature is off or no break-glass identity matches;
     * a matching identity with a wrong password is rejected outright.
     *
     * @return array{user: User, session_user: array<string,mixed>, landing: string}|null
     */
    private function authenticateWithBreakGlass(Request $request, string $identifier, string $password): ?array
    {
        if (! (bool) config('auth.break_glass.enabled') || $identifier === '') {
            return null;
        }

        $needle = mb_strtolower($identifier);
        $user = User::query()
            ->where('auth_source', 'local_break_glass')
            ->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(email) = ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(ad_login, \'\')) = ?', [$needle]);
            })
            ->first();

        if ($user === null) {
            return null;
        }

        if ($user->is_active === false
            || $user->password === null
            || ! Hash::check($password, (string) $user->password)) {
            $this->failed($identifier, 'break_glass_invalid_credentials', $request, ['provider' => 'break_glass']);

            throw new LibraryAuthenticationException(__('auth.invalid_credentials'), 401);
        }

        $sessionUser = $this->sessions->login($request, $user);

        return ['user' => $user, 'session_user' => $sessionUser, 'landing' => $this->sessions->landing($user)];
    }

    /** @return array{user: User, session_user: array<string,mixed>, landing: string} */
    private function authenticateWithActiveDirectory(Request $request, string $identifier, string $password): array
    {
        try {
            $identity = $this->activeDirectory->authenticate($identifier, $password);
            $user = $this->activeDirectoryProvisioner->provision($identity, $request);
            $sessionUser = $this->sessions->login($request, $user);

            return ['user' => $user, 'session_user' => $sessionUser, 'landing' => $this->sessions->landing($user)];
        } catch (ActiveDirectoryException $exception) {
            $invalid = $exception->category === 'invalid_credentials';
            $reason = $invalid ? 'invalid_credentials' : 'provider_unavailable';
            $this->failed($identifier, $reason, $request, ['provider' => 'active_directory', 'category' => $exception->category]);
            throw new LibraryAuthenticationException(
                $invalid ? __('auth.invalid_credentials') : __('auth.provider_unavailable'),
                $invalid ? 401 : 503,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rawUser
     */
    private function provisionLdapUser(array $rawUser, string $identifier, Request $request): User
    {
        $email = mb_strtolower(trim((string) ($rawUser['email'] ?? '')));
        $login = trim((string) ($rawUser['ad_login'] ?? $rawUser['login'] ?? $identifier));
        $externalId = trim((string) ($rawUser['id'] ?? $rawUser['external_id'] ?? ''));

        if ($email === '') {
            $email = str_contains($identifier, '@')
                ? mb_strtolower($identifier)
                : mb_strtolower($login).'@ldap.kazutb.local';
        }

        return DB::transaction(function () use ($rawUser, $identifier, $request, $email, $login, $externalId): User {
            $activeAdminIds = User::query()
                ->role('admin')
                ->where('is_active', true)
                ->orderBy('users.id')
                ->lockForUpdate()
                ->pluck('users.id');

            $candidates = collect([
                $externalId !== ''
                    ? User::query()
                        ->where('auth_provider', 'ldap')
                        ->where('external_id', $externalId)
                        ->lockForUpdate()
                        ->first()
                    : null,
                User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first(),
                $login !== ''
                    ? User::query()
                        ->whereRaw('LOWER(ad_login) = ?', [mb_strtolower($login)])
                        ->lockForUpdate()
                        ->first()
                    : null,
            ])->filter()->unique(fn (User $candidate): int|string => $candidate->getKey())->values();

            if ($candidates->count() > 1) {
                throw new LibraryAuthenticationException(__('auth.identity_conflict'), 409);
            }

            $user = $candidates->first() ?? new User;
            $isNew = ! $user->exists;
            $old = $isNew ? null : $this->identitySnapshot($user->load('roles'));
            $canonicalRole = $this->canonicalRole((string) ($rawUser['role'] ?? 'member'));
            $effectiveRole = ! $isNew && $user->role_source === 'manual'
                ? $user->effectiveRole()
                : $canonicalRole;

            if (
                ! $isNew
                && $user->is_active
                && $user->hasRole('admin')
                && $effectiveRole !== 'admin'
                && $activeAdminIds->count() <= 1
            ) {
                throw new LibraryAuthenticationException(__('roles.errors.last_active_admin'), 409);
            }

            $user->fill([
                'name' => trim((string) ($rawUser['name'] ?? $login ?: $email)),
                'email' => $email,
                'ad_login' => $login ?: null,
                'department' => trim((string) ($rawUser['department'] ?? '')) ?: null,
                'auth_provider' => 'ldap',
                'external_id' => $externalId !== '' ? $externalId : null,
                'role' => match ($effectiveRole) {
                    'admin' => 'admin',
                    'member' => 'reader',
                    default => 'librarian',
                },
                'locale' => in_array(($rawUser['locale'] ?? null), ['ru', 'kk', 'en'], true)
                    ? $rawUser['locale']
                    : ($user->locale ?: 'ru'),
            ]);

            if ($isNew) {
                $user->password = Hash::make(Str::random(64));
                $user->role_source = 'ldap_mapped';
                $user->is_active = true;
                $user->email_verified_at = now();
            }

            $user->save();

            if ($isNew || $user->role_source === 'ldap_mapped') {
                $user->syncRoles([$canonicalRole]);
            }

            $user->refresh()->load('roles');
            $new = $this->identitySnapshot($user);

            if ($isNew || $old !== $new) {
                $action = $isNew
                    ? 'create'
                    : (($old['roles'] ?? []) !== $new['roles'] ? 'role.update' : 'update');
                $this->audit->logRequired(
                    actionType: $action,
                    entityType: 'user',
                    entityId: $user->getKey(),
                    oldValues: $old,
                    newValues: $new,
                    scope: 'security',
                    metadata: ['source' => 'ldap', 'login_identifier' => $identifier],
                    actor: ['name' => 'LDAP identity bridge', 'role' => 'system'],
                    request: $request,
                );
            }

            return $user;
        });
    }

    private function canonicalRole(string $rawRole): string
    {
        $normalized = mb_strtolower(trim($rawRole));
        $normalized = match ($normalized) {
            'reader', 'student', 'teacher', 'employee', '' => 'member',
            default => $normalized,
        };

        return (string) (Role::query()
            ->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->value('name') ?: 'member');
    }

    /**
     * @return array<string, mixed>
     */
    private function identitySnapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'ad_login' => $user->ad_login,
            'department' => $user->department,
            'auth_provider' => $user->auth_provider,
            'external_id' => $user->external_id,
            'role_source' => $user->role_source,
            'roles' => $user->getRoleNames()->values()->all(),
            'is_active' => (bool) $user->is_active,
            'locale' => $user->locale,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function failed(
        string $identifier,
        string $reason,
        Request $request,
        array $metadata = [],
    ): void {
        $this->audit->logRequired(
            actionType: 'login.fail',
            entityType: 'authentication',
            entityId: $identifier,
            newValues: ['reason' => $reason],
            scope: 'security',
            metadata: $metadata,
            actor: ['name' => $identifier, 'role' => 'guest'],
            request: $request,
        );
    }
}
