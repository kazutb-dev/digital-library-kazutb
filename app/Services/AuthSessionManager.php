<?php

namespace App\Services;

use App\Exceptions\LibraryAuthenticationException;
use App\Http\Middleware\EnsureControlPlaneAccess;
use App\Models\User;
use App\Support\LocaleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthSessionManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Populate both authorization stacks while the CRM/session transition is
     * still active.
     *
     * @return array<string, mixed>
     */
    public function login(Request $request, User $user, ?string $profileType = null): array
    {
        $resolver = new LocaleResolver;
        $guestLocale = $resolver->isSupported($request->session()->get('locale'))
            ? $resolver->normalize($request->session()->get('locale'))
            : ($resolver->isSupported($request->cookie(LocaleResolver::COOKIE))
                ? $resolver->normalize($request->cookie(LocaleResolver::COOKIE))
                : LocaleResolver::DEFAULT);

        $user = DB::transaction(function () use ($user, $request, $guestLocale): ?User {
            $lockedUser = User::query()
                ->with('roles')
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Only an explicit false value represents a blocked account, so
            // installations waiting for the additive column remain usable.
            if ($lockedUser->is_active === false) {
                $this->audit->logRequired(
                    actionType: 'login.fail',
                    entityType: 'authentication',
                    entityId: $lockedUser->email,
                    newValues: ['reason' => 'inactive_account'],
                    scope: 'security',
                    actor: [
                        'id' => $lockedUser->getKey(),
                        'name' => $lockedUser->name,
                        'role' => $this->canonicalRole($lockedUser),
                    ],
                    request: $request,
                );

                return null;
            }

            $lockedUser->forceFill([
                'last_login_at' => now('UTC'),
                'locale' => $lockedUser->locale ?: $guestLocale,
            ])->save();
            $this->audit->logRequired(
                actionType: 'login.success',
                entityType: 'authentication',
                entityId: $lockedUser->getKey(),
                newValues: ['auth_provider' => $lockedUser->auth_provider],
                scope: 'security',
                actor: $lockedUser,
                request: $request,
            );

            return $lockedUser;
        });

        // The failed-login audit must commit, so the exception is deliberately
        // raised after the transaction has finished.
        if ($user === null) {
            throw new LibraryAuthenticationException(__('auth.account_inactive'), 403);
        }

        $canonicalRole = $this->canonicalRole($user);
        $legacyRole = match ($canonicalRole) {
            'admin' => 'admin',
            'member' => 'reader',
            default => 'librarian',
        };
        $externalIdentity = trim((string) $user->external_id);
        $sessionUser = [
            // Existing catalog/reservation code treats `id` as the upstream
            // CRM identity. Keep that contract and expose the local FK
            // separately for RBAC, messages and audit attribution.
            'id' => $externalIdentity !== '' ? $externalIdentity : (string) $user->getKey(),
            'local_id' => (int) $user->getKey(),
            'external_id' => $externalIdentity !== '' ? $externalIdentity : null,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'login' => (string) ($user->ad_login ?: $user->email),
            'ad_login' => (string) ($user->ad_login ?: ''),
            'role' => $legacyRole,
            'canonical_role' => $canonicalRole,
            'title' => '',
            'phone_extension' => '',
            'profile_type' => $profileType ?: ($canonicalRole === 'member' ? 'member' : 'staff'),
            'locale' => (string) ($user->locale ?: 'kk'),
        ];

        // Establish the authenticated session only after the mandatory audit
        // event and last-login timestamp have committed successfully.
        $request->session()->regenerate();
        Auth::login($user);
        $request->session()->put('library.user', $sessionUser);
        $request->session()->put('library.authenticated_at', now('UTC')->toIso8601String());
        $request->session()->put('locale', $sessionUser['locale']);

        return $sessionUser;
    }

    public function logout(Request $request, ?string $reason = null): void
    {
        $actor = $request->user() ?? $request->session()->get('library.user');

        $this->audit->logRequired(
            actionType: 'logout',
            entityType: 'authentication',
            entityId: $request->user()?->getKey() ?? data_get($actor, 'local_id', data_get($actor, 'id', 'anonymous')),
            newValues: $reason === null ? null : ['reason' => $reason],
            reason: $reason,
            scope: 'security',
            actor: is_array($actor) || $actor instanceof User ? $actor : null,
            request: $request,
        );

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function landing(User $user): string
    {
        $canonicalRole = $this->canonicalRole($user);

        return match ($canonicalRole) {
            'admin' => '/admin',
            'member' => '/dashboard',
            'librarian' => '/librarian',
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer' => '/librarian',
            default => $user->hasAnyPermission(EnsureControlPlaneAccess::PERMISSIONS)
                ? '/admin'
                : '/librarian',
        };
    }

    private function canonicalRole(User $user): string
    {
        $role = (string) ($user->getRoleNames()->first() ?: match ($user->role) {
            'admin' => 'admin',
            'librarian' => 'librarian',
            default => 'member',
        });
        $normalized = mb_strtolower($role);

        return in_array($normalized, ['admin', 'librarian', 'member'], true)
            ? $normalized
            : $role;
    }
}
