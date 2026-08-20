<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => [
                'nullable',
                'string',
                'max:80',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'auth_provider' => ['nullable', Rule::in(['demo', 'ldap'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['name', 'email', 'created_at', 'last_login_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $users = $this->filteredQuery($filters)
            ->with(['roles', 'readerProfile'])
            ->orderBy($sort, $direction)
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'managedUser' => new User(['is_active' => true, 'auth_provider' => 'demo', 'locale' => 'ru']),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateUser($request);

        $user = DB::transaction(function () use ($validated, $audit): User {
            $role = Role::findByName($validated['role'], 'web');
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'ad_login' => $validated['ad_login'] ?: null,
                'department' => $validated['department'] ?: null,
                'password' => Hash::make($validated['password'] ?? str()->random(48)),
                'auth_provider' => $validated['auth_provider'],
                'external_id' => $validated['external_id'] ?: null,
                'role_source' => 'manual',
                'role' => $this->legacyRole($role->name),
                'locale' => $validated['locale'],
                'is_active' => $validated['is_active'],
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);
            $user->load('roles');

            $audit->logRequired(
                actionType: 'create',
                entityType: 'user',
                entityId: $user->getKey(),
                newValues: $this->auditSnapshot($user),
                scope: 'security',
            );

            return $user;
        });

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('common.created_successfully'));
    }

    public function show(User $user): View
    {
        $user->load('roles.permissions');

        return view('admin.users.show', [
            'managedUser' => $user,
            'effectivePermissions' => $user->getAllPermissions()->sortBy('name')->values(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'managedUser' => $user->load('roles'),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        $role = Role::findByName($validated['role'], 'web');
        DB::transaction(function () use ($validated, $role, $user, $request, $audit): void {
            User::query()
                ->role('admin')
                ->where('is_active', true)
                ->orderBy('users.id')
                ->lockForUpdate()
                ->get(['users.id']);
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $user->refresh()->load('roles');
            $old = $this->auditSnapshot($user);
            $this->guardLastAdministrator(
                target: $user,
                nextRole: $role->name,
                nextActive: $validated['is_active'],
            );

            $values = [
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'ad_login' => $validated['ad_login'] ?: null,
                'department' => $validated['department'] ?: null,
                'auth_provider' => $validated['auth_provider'],
                'external_id' => $validated['external_id'] ?: null,
                'role_source' => 'manual',
                'role' => $this->legacyRole($role->name),
                'locale' => $validated['locale'],
                'is_active' => $validated['is_active'],
            ];

            if (! empty($validated['password'])) {
                $values['password'] = Hash::make($validated['password']);
            }

            if (! $validated['is_active'] && $user->is_active) {
                $values['deactivated_at'] = now();
                $values['deactivated_by'] = auth()->id();
            } elseif ($validated['is_active']) {
                $values['deactivated_at'] = null;
                $values['deactivated_by'] = null;
            }

            $user->update($values);
            $user->syncRoles([$role]);
            if (! $validated['is_active']) {
                $this->revokeAuthenticationCredentials($user);
            }
            $user->refresh()->load('roles');
            $new = $this->auditSnapshot($user);
            $action = $old['is_active'] && ! $new['is_active']
                ? 'deactivate'
                : ($old['roles'] !== $new['roles'] ? 'role.update' : 'update');

            $audit->logRequired(
                actionType: $action,
                entityType: 'user',
                entityId: $user->getKey(),
                oldValues: $old,
                newValues: $new,
                reason: $request->string('reason')->trim()->value() ?: null,
                scope: 'security',
            );
        });

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', __('common.updated_successfully'));
    }

    public function toggleActive(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        DB::transaction(function () use ($user, $request, $validated, $audit): void {
            User::query()
                ->role('admin')
                ->where('is_active', true)
                ->orderBy('users.id')
                ->lockForUpdate()
                ->get(['users.id']);
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $user->refresh()->load('roles');
            $nextActive = ! $user->is_active;
            $currentRole = (string) ($user->getRoleNames()->first() ?: $user->role);
            $old = $this->auditSnapshot($user);
            $this->guardLastAdministrator($user, $currentRole, $nextActive);
            $user->update([
                'is_active' => $nextActive,
                'deactivated_at' => $nextActive ? null : now(),
                'deactivated_by' => $nextActive ? null : $request->user()?->getKey(),
            ]);
            if (! $nextActive) {
                $this->revokeAuthenticationCredentials($user);
            }
            $user->refresh()->load('roles');

            $audit->logRequired(
                actionType: $nextActive ? 'activate' : 'deactivate',
                entityType: 'user',
                entityId: $user->getKey(),
                oldValues: $old,
                newValues: $this->auditSnapshot($user),
                reason: $validated['reason'],
                scope: 'security',
            );
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * Admin-driven password reset: sets a temporary password (given or
     * generated), flags the account for a forced change on next sign-in,
     * and shows the temporary password exactly once via the flash.
     */
    public function resetPassword(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['password' => __('admin.users.password_reset.self_forbidden')]);
        }

        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:12', 'max:255'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $temporaryPassword = ($validated['password'] ?? null) ?: str()->password(16, symbols: false);

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $audit->logRequired(
            actionType: 'password.reset',
            entityType: 'user',
            entityId: $user->getKey(),
            newValues: ['must_change_password' => true, 'generated' => empty($validated['password'] ?? null)],
            reason: $validated['reason'],
            scope: 'security',
        );

        return back()
            ->with('success', __('admin.users.password_reset.done'))
            ->with('temporary_password', $temporaryPassword);
    }

    /**
     * Force sign-out everywhere: removes the user's database sessions,
     * remember token, and API tokens without touching account status.
     */
    public function revokeSessions(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['sessions' => __('admin.users.sessions.self_forbidden')]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $revokedSessions = DB::table('sessions')->where('user_id', $user->getKey())->delete();
        $user->setRememberToken(null);
        $user->save();
        $revokedTokens = $user->tokens()->delete();

        $audit->logRequired(
            actionType: 'session.revoke',
            entityType: 'user',
            entityId: $user->getKey(),
            newValues: ['revoked_sessions' => $revokedSessions, 'revoked_api_tokens' => $revokedTokens],
            reason: $validated['reason'],
            scope: 'security',
        );

        return back()->with('success', __('admin.users.sessions.revoked', ['count' => $revokedSessions]));
    }

    /** Deactivation takes effect immediately for web, remember-me and API auth. */
    private function revokeAuthenticationCredentials(User $user): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }
        $user->setRememberToken(null);
        $user->saveQuietly();
        if (Schema::hasTable('personal_access_tokens')) {
            $user->tokens()->delete();
        }
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => [
                'nullable',
                'string',
                'max:80',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'auth_provider' => ['nullable', Rule::in(['demo', 'ldap'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $users = $this->filteredQuery($filters)->with('roles')->orderBy('name')->cursor();

        $audit->logRequired(
            actionType: 'export',
            entityType: 'report',
            entityId: 'users',
            newValues: ['format' => 'csv', 'filters' => $filters],
            scope: 'system',
        );

        return response()->streamDownload(function () use ($users): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            Csv::writeRow($output, [
                __('reports.columns.id'),
                __('reports.columns.name'),
                __('reports.columns.email'),
                __('reports.columns.role'),
                __('reports.columns.auth_provider'),
                __('reports.columns.active'),
                __('reports.columns.registered_at'),
                __('reports.columns.last_login_utc'),
            ]);

            foreach ($users as $user) {
                $roleLabels = $user->getRoleNames()->map(function (string $role): string {
                    $key = 'roles.names.'.$role;

                    return trans()->has($key) ? __($key) : $role;
                });
                Csv::writeRow($output, [
                    $user->getKey(),
                    $user->name,
                    $user->email,
                    $roleLabels->join(', '),
                    $user->auth_provider,
                    $user->is_active ? __('common.boolean.yes') : __('common.boolean.no'),
                    $user->created_at?->utc()->toIso8601String(),
                    $user->last_login_at?->utc()->toIso8601String(),
                ]);
            }

            fclose($output);
        }, 'users-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = User::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(ad_login, \'\')) LIKE ?', [$needle]);
            });
        }

        if ($role = ($filters['role'] ?? null)) {
            $query->role($role);
        }

        if ($provider = ($filters['auth_provider'] ?? null)) {
            $query->where('auth_provider', $provider);
        }

        if ($status = ($filters['status'] ?? null)) {
            $query->where('is_active', $status === 'active');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'ad_login' => ($login = mb_strtolower(trim((string) $request->input('ad_login')))) !== '' ? $login : null,
            'external_id' => ($externalId = trim((string) $request->input('external_id'))) !== '' ? $externalId : null,
        ]);

        $passwordRules = $user === null
            ? ['nullable', 'required_if:auth_provider,demo', 'string', 'min:12', 'max:255', 'confirmed']
            : ['nullable', 'string', 'min:12', 'max:255', 'confirmed'];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'ad_login' => ['nullable', 'string', 'max:255', Rule::unique('users', 'ad_login')->ignore($user)],
            'department' => ['nullable', 'string', 'max:255'],
            'auth_provider' => ['required', Rule::in(['demo', 'ldap'])],
            'external_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'external_id')
                    ->where(fn ($query) => $query->where('auth_provider', $request->input('auth_provider')))
                    ->ignore($user),
            ],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'locale' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'is_active' => ['required', 'boolean'],
            'password' => $passwordRules,
            'reason' => [
                Rule::requiredIf(
                    fn (): bool => $user !== null
                        && (bool) $user->is_active
                        && ! $request->boolean('is_active')
                ),
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function guardLastAdministrator(
        User $target,
        string $nextRole,
        bool $nextActive,
    ): void {
        $targetIsAdmin = $target->hasRole('admin');
        $removesAdminAccess = $targetIsAdmin && ($nextRole !== 'admin' || ! $nextActive);

        if (! $removesAdminAccess) {
            return;
        }

        $activeAdmins = User::query()->role('admin')->where('is_active', true)->count();

        if ($activeAdmins <= 1) {
            throw ValidationException::withMessages([
                'role' => __('roles.errors.last_active_admin'),
            ]);
        }
    }

    private function legacyRole(string $role): string
    {
        return match ($role) {
            'admin' => 'admin',
            'member' => 'reader',
            default => 'librarian',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(User $user): array
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
            'deactivated_at' => $user->deactivated_at?->utc()->toIso8601String(),
            'deactivated_by' => $user->deactivated_by,
        ];
    }
}
