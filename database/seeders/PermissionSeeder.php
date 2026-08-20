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

        // Attendance (ДИР 9.4) — recording a card scan at the entrance. Kept
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

        // Fines and debts (Master.md 14.4-14.5, 15.5).
        'fines.view',
        'fines.manage',
        'fines.waive',

        // Lost/damaged copy replacement workflow (ДИР 9.3).
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
        'digital.view_metadata',
        'digital.preview',
        'digital.read',
        'digital.download',
        'digital.review_metadata',
        'digital.review_rights',
        'digital.process',
        'digital.approve',
        'digital.publish',
        'digital.restrict',
        'digital.withdraw',
        'digital.manage_policies',
        'digital.view_analytics',

        // News and announcements.
        'news.create',
        'news.view_internal',
        'news.edit_own',
        'news.edit_any',
        'news.submit_for_review',
        'news.review',
        'news.request_changes',
        'news.approve',
        'news.schedule',
        'news.publish',
        'news.publish_emergency',
        'news.archive',
        'news.cancel',
        'news.delete_draft',
        'news.manage_categories',
        'news.manage_annual_plan',
        'news.manage_homepage',
        'news.view_analytics',
        'news.delete',

        // Institutional repository of scholarly works.
        'repository.browse_metadata',
        'repository.read_full',
        'repository.upload',
        'repository.approve',
        'repository.publish',
        'repository.remove',
        'repository.view_public',
        'repository.view_internal',
        'repository.create',
        'repository.edit',
        'repository.review_metadata',
        'repository.review_rights',
        'repository.request_changes',
        'repository.withdraw',
        'repository.manage_versions',
        'repository.view_analytics',

        // Subscribed external resources.
        'external_resources.view',
        'external_resources.manage',
        'external_resources.review',
        'external_resources.publish',
        'external_resources.manage_contracts',
        'external_resources.view_contracts',
        'external_resources.view_analytics',

        // Reader messages / support requests.
        'messages.submit',
        'messages.reply_own',
        'messages.view_all',
        'messages.view_assigned',
        'messages.assign',
        'messages.reassign',
        'messages.change_priority',
        'messages.add_internal_note',
        'messages.request_clarification',
        'messages.prepare_response',
        'messages.approve_response',
        'messages.reject',
        'messages.close',
        'messages.reopen',
        'messages.manage_categories',
        'messages.manage_routing',
        'messages.manage_sla',
        'messages.view_analytics',
        'messages.view_sensitive_complaints',
        'messages.download_attachments',
        'messages.resolve',

        // Reporting and analytics.
        'reports.view_acquisitions',
        'reports.view_ops',
        'reports.view_full',
        'reports.export',
        'reports.official.create',
        'reports.official.submit',
        'reports.official.approve',
        'reports.official.archive',
        'reports.official.export',
        'reports.official.delete_draft',
        'staff_performance.view',

        // Acquisitions — order and accession workflows are implemented later,
        // but their authorization contract is defined up front.
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',

        // Daily librarian workspace queues.
        'tasks.view',
        'tasks.manage_own',
        'tasks.assign',
        'acquisitions.view',
        'edd.view',
        'edd.manage',
        'periodicals.view',
        'periodicals.manage',
        'calendar.view',

        // Integration Hub. Secrets themselves are never readable through a permission.
        'integrations.view',
        'integrations.health',
        'integrations.manage',
        'integrations.sync',
        'integrations.reconcile',
        'integrations.retry',
        'integrations.view_logs',
        'integrations.view_conflicts',
        'integrations.resolve_conflicts',
        'integrations.manage_mapping',
        'integrations.manage_secrets_reference',

        // Administration.
        'users.manage',
        'roles.manage',
        'system.settings',
        'system.logs',
        'branches.manage',
        'data_cleanup.access',

        // Persistent data-quality control centre (ДИР 6, ТЗ 11).
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
