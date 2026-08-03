<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->get();
        $permissions = Permission::query()->where('guard_name', 'web')->orderBy('name')->get();
        $domainOrder = [
            'catalog',
            'acquisitions',
            'circulation',
            'reservation',
            'digital',
            'repository',
            'news',
            'messages',
            'reports',
            'system',
        ];
        $domainMap = [
            'catalog' => 'catalog',
            'copies' => 'catalog',
            'data_cleanup' => 'catalog',
            'acquisitions' => 'acquisitions',
            'circulation' => 'circulation',
            'reservation' => 'reservation',
            'shortlist' => 'reservation',
            'digital' => 'digital',
            'repository' => 'repository',
            'news' => 'news',
            'messages' => 'messages',
            'reports' => 'reports',
            'staff_performance' => 'reports',
            'users' => 'system',
            'roles' => 'system',
            'system' => 'system',
            'branches' => 'system',
            'external_resources' => 'system',
        ];
        $permissionGroups = $permissions
            ->groupBy(function (Permission $permission) use ($domainMap): string {
                $prefix = str((string) $permission->name)->before('.')->value();

                return $domainMap[$prefix] ?? 'system';
            })
            ->sortBy(fn ($items, string $domain): int => array_search($domain, $domainOrder, true) ?: 0);

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);

        $role = DB::transaction(function () use ($validated, $audit): Role {
            $role = Role::query()->create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);
            $role->syncPermissions($validated['permissions'] ?? []);
            $role->load('permissions');

            $audit->logRequired(
                actionType: 'create',
                entityType: 'role',
                entityId: $role->getKey(),
                newValues: [
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                ],
                scope: 'security',
            );

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', __('common.created_successfully'));
    }

    public function update(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);

        $validated = $this->validated($request, $role);
        if (in_array($role->name, [
            'member',
            'librarian',
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer',
            'admin',
        ], true)
            && $validated['name'] !== $role->name) {
            throw ValidationException::withMessages([
                'name' => __('roles.messages.name_reserved'),
            ]);
        }
        if ($role->name === 'admin') {
            $required = ['roles.manage', 'users.manage'];
            $missing = array_diff($required, $validated['permissions'] ?? []);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'permissions' => __('roles.errors.admin_required_permissions'),
                ]);
            }
        }
        DB::transaction(function () use ($validated, $role, $audit): void {
            Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $role->refresh()->load('permissions');
            $old = [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ];
            $role->update(['name' => $validated['name']]);
            $role->syncPermissions($validated['permissions'] ?? []);
            $role->refresh()->load('permissions');

            $audit->logRequired(
                actionType: 'update',
                entityType: 'role',
                entityId: $role->getKey(),
                oldValues: $old,
                newValues: [
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                ],
                scope: 'security',
            );
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * @return array{name: string, permissions?: list<string>}
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::notIn(['guest']),
                'regex:/^[\\pL\\pN][\\pL\\pN._ -]*$/u',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($role),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);
    }
}
