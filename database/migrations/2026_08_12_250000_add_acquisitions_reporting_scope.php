<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'reports.view_acquisitions';

    public function up(): void
    {
        $tables = config('permission.table_names');
        if (! is_array($tables)
            || ! Schema::hasTable($tables['permissions'])
            || ! Schema::hasTable($tables['roles'])
            || ! Schema::hasTable($tables['role_has_permissions'])) {
            return;
        }

        $now = now('UTC');
        DB::table($tables['permissions'])->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $permissionId = DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');
        $roleId = DB::table($tables['roles'])
            ->where('name', 'acquisitions')
            ->where('guard_name', 'web')
            ->value('id');
        if ($permissionId !== null && $roleId !== null) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        // The acquisitions role is explicitly allowed to materialize only its
        // own live report. The registry still prevents all other datasets.
        $exportId = DB::table($tables['permissions'])
            ->where('name', 'reports.export')
            ->where('guard_name', 'web')
            ->value('id');
        if ($exportId !== null && $roleId !== null) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $exportId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        if (! is_array($tables)
            || ! Schema::hasTable($tables['permissions'])
            || ! Schema::hasTable($tables['roles'])
            || ! Schema::hasTable($tables['role_has_permissions'])) {
            return;
        }
        $roleId = DB::table($tables['roles'])
            ->where('name', 'acquisitions')
            ->where('guard_name', 'web')
            ->value('id');
        $permissionId = DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');
        $exportId = DB::table($tables['permissions'])
            ->where('name', 'reports.export')
            ->where('guard_name', 'web')
            ->value('id');
        if ($roleId !== null) {
            DB::table($tables['role_has_permissions'])
                ->where('role_id', $roleId)
                ->whereIn('permission_id', array_values(array_filter([$permissionId, $exportId])))
                ->delete();
        }
        if ($permissionId !== null) {
            DB::table($tables['permissions'])->where('id', $permissionId)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
