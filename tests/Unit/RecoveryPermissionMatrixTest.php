<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureOperationalStaffPermission;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RecoveryPermissionMatrixTest extends TestCase
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'ksu.view',
        'ksu.manage',
        'ksu.resolve',
        'acquisitions.intake',
        'acquisitions.confirm',
        'catalog.view_raw_marc',
        'legacy_recovery.view',
        'legacy_recovery.review',
        'legacy_recovery.resolve',
        'legacy_recovery.manage',
        'copies.movements.view',
        'copies.movements.create',
        'copies.write_off',
    ];

    /** @var array<string, list<string>> */
    private const EXPECTED_ROLES = [
        'ksu.view' => ['senior_librarian', 'acquisitions', 'admin'],
        'ksu.manage' => ['senior_librarian', 'acquisitions', 'admin'],
        'ksu.resolve' => ['senior_librarian', 'admin'],
        'acquisitions.intake' => ['senior_librarian', 'acquisitions', 'admin'],
        'acquisitions.confirm' => ['senior_librarian', 'acquisitions', 'admin'],
        'catalog.view_raw_marc' => ['senior_librarian', 'cataloguer', 'admin'],
        'legacy_recovery.view' => ['admin'],
        'legacy_recovery.review' => ['senior_librarian', 'admin'],
        'legacy_recovery.resolve' => ['senior_librarian', 'admin'],
        'legacy_recovery.manage' => ['admin'],
        'copies.movements.view' => ['librarian', 'senior_librarian', 'admin'],
        'copies.movements.create' => ['librarian', 'senior_librarian', 'admin'],
        'copies.write_off' => ['senior_librarian', 'admin'],
    ];

    public function test_recovery_permissions_exist_and_are_granted_only_to_expected_roles(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $this->assertContains($permission, PermissionSeeder::PERMISSIONS);

            $actualRoles = [];
            foreach (RoleSeeder::matrix() as $role => $permissions) {
                if (in_array($permission, $permissions, true)) {
                    $actualRoles[] = $role;
                }
            }

            $this->assertSame(self::EXPECTED_ROLES[$permission], $actualRoles, $permission);
        }
    }

    public function test_new_capabilities_admit_custom_operational_roles_to_the_staff_boundary(): void
    {
        $constant = (new ReflectionClass(EnsureOperationalStaffPermission::class))
            ->getReflectionConstant('STAFF_PERMISSIONS');

        $this->assertNotFalse($constant);
        $operationalPermissions = $constant->getValue();

        foreach (self::PERMISSIONS as $permission) {
            $this->assertContains($permission, $operationalPermissions, $permission);
        }
    }
}
