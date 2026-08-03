<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role → permission matrix.
 *
 * `guest` is deliberately absent: an unauthenticated visitor is the absence of
 * a role, not a stored record. Guest-visible surfaces must therefore never be
 * gated on a permission check — they are simply public routes.
 *
 * `admin` is enumerated in full rather than granted by wildcard. Checks in
 * policies and middleware ask for concrete permissions only, so an operator can
 * remove a single line below to withdraw one capability from administrators
 * without touching application code.
 *
 * Operational roles from the specification are explicit entries in matrix().
 */
class RoleSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * Ordinary library user — students, faculty and staff readers alike.
     * Maps onto the legacy session role `reader`.
     *
     * @var list<string>
     */
    public const MEMBER = [
        'catalog.search',
        'catalog.view_full_metadata',
        'catalog.view_udc',
        'reservation.create',
        'reservation.cancel_own',
        'circulation.view_own_history',
        'circulation.renew',
        'member.dashboard.view',
        'profile.update_own',
        'loans.view_own',
        'loans.renew_own',
        'reservation.view_own',
        'fines.view_own',
        'incidents.view_own',
        'notifications.view_own',
        'notifications.manage_own',
        'collections.manage_own',
        'collections.view_public',
        'messages.view_own',
        'reader_card.view_own',
        'reader_card.print_own',
        'shortlist.manage_own',
        'digital.view_cover',
        'digital.view_preview',
        'digital.read_full',
        'repository.browse_metadata',
        'repository.read_full',
        'external_resources.view',
        'messages.submit',
    ];

    /**
     * Operational library staff. Inherits the full member set and adds
     * cataloguing, circulation, digital upload and reporting duties.
     * Deliberately excludes destructive and system-level permissions.
     *
     * @var list<string>
     */
    public const LIBRARIAN_EXTRA = [
        'catalog.create_record',
        'catalog.edit_record',
        'catalog.merge_duplicates',
        'catalog.import',
        'copies.create',
        'copies.edit',
        'reservation.confirm',
        'reservation.assign_copy',
        'reservation.fulfill',
        'reservation.cancel_any',
        'circulation.issue',
        'circulation.return',
        'circulation.renew',
        'circulation.view_any_history',
        // §9.4 — the desk records attendance alongside issuing.
        'visits.record',
        'inventory.view',
        'inventory.scan',
        'barcodes.print',
        'fines.view',
        'fines.manage',
        'fines.waive',
        'incidents.view',
        'incidents.create',
        'incidents.register_replacement',
        'digital.upload',
        'digital.set_access_flags',
        'news.create',
        'news.edit_own',
        'news.publish',
        'repository.upload',
        'repository.approve',
        'external_resources.view',
        'messages.view_all',
        'messages.resolve',
        'reports.view_ops',
        'reports.export',
        'data_cleanup.access',
        'data_quality.view',
        'data_quality.triage',
        'data_quality.correct',
    ];

    /** @var list<string> */
    public const DIRECTOR = [
        'reports.view_full',
        'reports.export',
        'repository.publish',
        'messages.view_all',
        'staff_performance.view',
        'incidents.view',
        'incidents.review',
        'incidents.approve',
        'incidents.approve_exception',
        'incidents.resolve',
        'incidents.view_reports',
        'data_quality.view',
        'data_quality.view_reports',
        'reservation.override_queue',
    ];

    /** @var list<string> */
    public const SENIOR_LIBRARIAN_EXTRA = [
        'circulation.override_limits',
        'circulation.override_due_date',
        'reservation.extend',
        'reservation.manage_transfer',
        'reservation.override_queue',
        'inventory.create',
        'inventory.review',
        'inventory.approve',
        'barcodes.print_batch',
        'catalog.delete_record',
        'copies.delete',
        'incidents.review',
        'incidents.approve',
        'data_quality.scan',
        'data_quality.assign',
        'data_quality.bulk_edit',
        'data_quality.approve_bulk',
        'data_quality.review_duplicates',
        'data_quality.approve_merge',
        'data_quality.execute_merge',
        'data_quality.approve_import',
        'data_quality.view_reports',
    ];

    /** @var list<string> */
    public const ACQUISITIONS = [
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',
        'copies.create',
        'catalog.view_full_metadata',
        'catalog.search',
    ];

    /** @var list<string> */
    public const CATALOGUER = [
        // ДИР: the cataloguer creates and edits records, classifies them and
        // keeps them to standard. The original set omitted catalogue search, so
        // the role could edit a record it had no way to find.
        //
        // `data_cleanup.access` is deliberately NOT granted: it also gates the
        // transitional /internal/review and /internal/ai-chat surfaces, which
        // this role must not reach. The Data Quality workbench admits the
        // cataloguer through `catalog.edit_record` instead — see the
        // /librarian/data-cleanup route.
        'catalog.search',
        'catalog.view_full_metadata',
        'catalog.create_record',
        'catalog.edit_record',
        'catalog.view_udc',
        'catalog.merge_duplicates',
        'catalog.import',
        'copies.edit',
        'data_quality.view',
        'data_quality.triage',
        'data_quality.correct',
        'data_quality.review_duplicates',
        'data_quality.merge',
        'data_quality.import',
    ];

    /** @var list<string> */
    public const BIBLIOGRAPHER = [
        'catalog.search',
        'catalog.view_full_metadata',
        'catalog.view_udc',
        'external_resources.view',
        'repository.browse_metadata',
        'repository.read_full',
        'shortlist.manage_own',
        'messages.view_all',
    ];

    /**
     * Full administrator set, written out explicitly so individual capabilities
     * can be revoked by deleting a line.
     *
     * @var list<string>
     */
    public const ADMIN = [
        'catalog.search',
        'catalog.view_full_metadata',
        'catalog.view_udc',
        'catalog.create_record',
        'catalog.edit_record',
        'catalog.delete_record',
        'catalog.merge_duplicates',
        'catalog.import',
        'copies.create',
        'copies.edit',
        'copies.delete',
        'reservation.create',
        'reservation.cancel_own',
        'reservation.cancel_any',
        'reservation.confirm',
        'reservation.assign_copy',
        'reservation.extend',
        'reservation.override_queue',
        'reservation.manage_transfer',
        'reservation.fulfill',
        'reservation.override_limits',
        'circulation.issue',
        'circulation.return',
        'circulation.renew',
        'circulation.override_limits',
        'circulation.override_due_date',
        'circulation.view_own_history',
        'circulation.view_any_history',
        'visits.record',
        'inventory.view',
        'inventory.create',
        'inventory.scan',
        'inventory.review',
        'inventory.approve',
        'barcodes.print',
        'barcodes.print_batch',
        'fines.view',
        'fines.manage',
        'fines.waive',
        'incidents.view',
        'incidents.create',
        'incidents.review',
        'incidents.approve',
        'incidents.approve_exception',
        'incidents.register_replacement',
        'incidents.resolve',
        'incidents.view_reports',
        'shortlist.manage_own',
        'digital.view_cover',
        'digital.view_preview',
        'digital.read_full',
        'digital.upload',
        'digital.set_access_flags',
        'digital.delete',
        'news.create',
        'news.edit_own',
        'news.edit_any',
        'news.delete',
        'news.publish',
        'repository.browse_metadata',
        'repository.read_full',
        'repository.upload',
        'repository.approve',
        'repository.publish',
        'repository.remove',
        'external_resources.view',
        'external_resources.manage',
        'messages.submit',
        'messages.view_all',
        'messages.resolve',
        'messages.delete',
        'reports.view_ops',
        'reports.view_full',
        'reports.export',
        'staff_performance.view',
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',
        'users.manage',
        'roles.manage',
        'system.settings',
        'system.logs',
        'branches.manage',
        'data_cleanup.access',
        'data_quality.view',
        'data_quality.scan',
        'data_quality.triage',
        'data_quality.correct',
        'data_quality.assign',
        'data_quality.import',
        'data_quality.manage_rules',
        'data_quality.view_reports',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            'member' => self::MEMBER,
            'librarian' => array_values(array_unique([...self::MEMBER, ...self::LIBRARIAN_EXTRA])),
            'director' => self::DIRECTOR,
            'senior_librarian' => array_values(array_unique([
                ...self::MEMBER,
                ...self::LIBRARIAN_EXTRA,
                ...self::SENIOR_LIBRARIAN_EXTRA,
            ])),
            'acquisitions' => self::ACQUISITIONS,
            'cataloguer' => self::CATALOGUER,
            'bibliographer' => self::BIBLIOGRAPHER,
            'admin' => self::ADMIN,
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::matrix() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, self::GUARD);

            $missing = array_diff($permissions, PermissionSeeder::PERMISSIONS);

            if ($missing !== []) {
                throw new \RuntimeException(sprintf(
                    'Role "%s" references permissions absent from PermissionSeeder: %s',
                    $roleName,
                    implode(', ', $missing)
                ));
            }

            $role->syncPermissions(
                Permission::whereIn('name', $permissions)
                    ->where('guard_name', self::GUARD)
                    ->get()
            );

            $this->command?->info(sprintf('Role %-10s → %d permissions', $roleName, count($permissions)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
