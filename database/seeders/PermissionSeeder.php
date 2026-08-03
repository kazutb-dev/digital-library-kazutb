<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Canonical permission catalogue for the library RBAC layer.
 *
 * Names follow `domain.action`. This class is the single source of truth for
 * which permissions exist; RoleSeeder decides who gets them. Adding a
 * permission here without granting it anywhere is intentional and safe — it
 * simply denies until a role picks it up.
 */
class PermissionSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        // Catalogue — discovery and bibliographic record management.
        'catalog.search',
        'catalog.view_full_metadata',
        'catalog.view_udc',
        'catalog.create_record',
        'catalog.edit_record',
        'catalog.delete_record',
        'catalog.merge_duplicates',
        'catalog.import',

        // Physical copies attached to catalogue records.
        'copies.create',
        'copies.edit',
        'copies.delete',

        // Reservations / holds.
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

        // Circulation — issue, return and loan history.
        'circulation.issue',
        'circulation.return',
        'circulation.renew',
        'circulation.override_limits',
        'circulation.override_due_date',
        'circulation.view_own_history',
        'circulation.view_any_history',

        // Reader cabinet — explicit own-scope capabilities.
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

        // Attendance (ДИР §9.4) — recording a card scan at the entrance. Kept
        // separate from circulation.*: counting a visit is not lending a book.
        'visits.record',

        // Barcode-assisted stocktaking and machine-readable labels/cards.
        'inventory.view',
        'inventory.create',
        'inventory.scan',
        'inventory.review',
        'inventory.approve',
        'barcodes.print',
        'barcodes.print_batch',

        // Fines and debts (Master.md §14.4-14.5, §15.5).
        'fines.view',
        'fines.manage',
        'fines.waive',

        // Lost/damaged copy replacement workflow (ДИР §9.3).
        'incidents.view',
        'incidents.create',
        'incidents.review',
        'incidents.approve',
        'incidents.approve_exception',
        'incidents.register_replacement',
        'incidents.resolve',
        'incidents.view_reports',

        // Personal shortlist.
        'shortlist.manage_own',

        // Digital materials — graded access from cover to full text.
        'digital.view_cover',
        'digital.view_preview',
        'digital.read_full',
        'digital.upload',
        'digital.set_access_flags',
        'digital.delete',

        // News and announcements.
        'news.create',
        'news.edit_own',
        'news.edit_any',
        'news.delete',
        'news.publish',

        // Institutional repository of scholarly works.
        'repository.browse_metadata',
        'repository.read_full',
        'repository.upload',
        'repository.approve',
        'repository.publish',
        'repository.remove',

        // Subscribed external resources.
        'external_resources.view',
        'external_resources.manage',

        // Reader messages / support requests.
        'messages.submit',
        'messages.view_all',
        'messages.resolve',
        'messages.delete',

        // Reporting and analytics.
        'reports.view_ops',
        'reports.view_full',
        'reports.export',
        'staff_performance.view',

        // Acquisitions — order and accession workflows are implemented later,
        // but their authorization contract is defined up front.
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',

        // Administration.
        'users.manage',
        'roles.manage',
        'system.settings',
        'system.logs',
        'branches.manage',
        'data_cleanup.access',

        // Persistent data-quality control centre (ДИР §6, ТЗ §11).
        'data_quality.view',
        'data_quality.scan',
        'data_quality.triage',
        'data_quality.correct',
        'data_quality.assign',
        'data_quality.bulk_edit',
        'data_quality.approve_bulk',
        'data_quality.review_duplicates',
        'data_quality.merge',
        'data_quality.approve_merge',
        'data_quality.execute_merge',
        'data_quality.import',
        'data_quality.approve_import',
        'data_quality.manage_rules',
        'data_quality.view_reports',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        $this->command?->info(sprintf('Permissions ensured: %d', count(self::PERMISSIONS)));
    }
}
