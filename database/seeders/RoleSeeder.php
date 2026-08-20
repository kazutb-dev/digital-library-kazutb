<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role — permission matrix.
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
        'messages.reply_own',
        'reader_card.view_own',
        'reader_card.print_own',
        'shortlist.manage_own',
        'digital.view_cover',
        'digital.view_preview',
        'digital.read_full',
        'digital.view_metadata',
        'digital.preview',
        'digital.read',
        'digital.download',
        'repository.browse_metadata',
        'repository.read_full',
        'repository.view_public',
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
        // 9.4 — the desk records attendance alongside issuing.
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
        'digital.review_metadata',
        'digital.review_rights',
        'digital.process',
        'news.create',
        'news.view_internal',
        'news.edit_own',
        'news.submit_for_review',
        'repository.upload',
        'repository.view_internal',
        'repository.create',
        'repository.edit',
        'repository.review_metadata',
        'repository.review_rights',
        'repository.request_changes',
        'repository.manage_versions',
        'external_resources.view',
        'messages.view_assigned',
        'messages.resolve',
        'messages.add_internal_note',
        'messages.request_clarification',
        'messages.prepare_response',
        'messages.download_attachments',
        'reports.view_ops',
        'reports.export',
        'reports.official.create',
        'reports.official.submit',
        'reports.official.archive',
        'reports.official.export',
        'reports.official.delete_draft',
        'data_cleanup.access',
        'data_quality.view',
        'data_quality.triage',
        'data_quality.correct',
        'tasks.view',
        'tasks.manage_own',
        'edd.view',
        'edd.manage',
        'periodicals.view',
        'calendar.view',
        'integrations.view',
        'integrations.health',
    ];

    /** @var list<string> */
    public const DIRECTOR = [
        'news.view_internal',
        'news.create',
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
        'reports.view_full',
        'reports.export',
        'reports.official.approve',
        'reports.official.archive',
        'reports.official.export',
        'repository.publish',
        'repository.approve',
        'repository.request_changes',
        'repository.withdraw',
        'repository.read_full',
        'repository.view_internal',
        'repository.view_analytics',
        'digital.approve',
        'digital.publish',
        'digital.restrict',
        'digital.withdraw',
        'digital.manage_policies',
        'digital.view_analytics',
        'external_resources.review',
        'external_resources.publish',
        'external_resources.manage_contracts',
        'external_resources.view_contracts',
        'external_resources.view_analytics',
        'messages.view_all',
        'messages.assign',
        'messages.reassign',
        'messages.change_priority',
        'messages.approve_response',
        'messages.reject',
        'messages.close',
        'messages.reopen',
        'messages.view_analytics',
        'messages.view_sensitive_complaints',
        'messages.download_attachments',
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
        'tasks.view',
        'tasks.assign',
        'acquisitions.view',
        'edd.view',
        'periodicals.view',
        'calendar.view',
        'integrations.view',
    ];

    /** @var list<string> */
    public const SENIOR_LIBRARIAN_EXTRA = [
        'messages.view_all',
        'messages.assign',
        'messages.reassign',
        'messages.change_priority',
        'messages.reject',
        'messages.close',
        'messages.reopen',
        'messages.view_sensitive_complaints',
        'news.view_internal',
        'news.edit_any',
        'news.review',
        'news.request_changes',
        'news.manage_annual_plan',
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
        'tasks.assign',
        'acquisitions.view',
        'periodicals.manage',
    ];

    /** @var list<string> */
    public const ACQUISITIONS = [
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',
        'reports.view_acquisitions',
        'reports.export',
        'copies.create',
        'catalog.view_full_metadata',
        'catalog.search',
        'tasks.view',
        'tasks.manage_own',
        'acquisitions.view',
        'periodicals.view',
        'periodicals.manage',
        'calendar.view',
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
        'messages.view_assigned',
        'messages.resolve',
        'messages.prepare_response',
        'messages.request_clarification',
        'messages.download_attachments',
        'tasks.view',
        'tasks.manage_own',
        'edd.view',
        'edd.manage',
        'periodicals.view',
        'calendar.view',
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
        'news.create',
        'news.view_internal',
        'news.edit_own',
        'news.edit_any',
        'news.delete',
        'news.submit_for_review',
        'news.review',
        'news.request_changes',
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
        'repository.browse_metadata',
        'repository.read_full',
        'repository.view_public',
        'repository.view_analytics',
        'external_resources.view',
        'external_resources.manage',
        'external_resources.review',
        'external_resources.publish',
        'external_resources.manage_contracts',
        'external_resources.view_contracts',
        'external_resources.view_analytics',
        'messages.submit',
        'messages.view_assigned',
        'messages.assign',
        'messages.reassign',
        'messages.change_priority',
        'messages.manage_categories',
        'messages.manage_routing',
        'messages.manage_sla',
        'messages.view_analytics',
        'messages.download_attachments',
        'reports.view_ops',
        'reports.view_full',
        'reports.view_acquisitions',
        'reports.export',
        'reports.official.create',
        'reports.official.submit',
        'reports.official.archive',
        'reports.official.export',
        'reports.official.delete_draft',
        'staff_performance.view',
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',
        'tasks.view',
        'tasks.manage_own',
        'tasks.assign',
        'acquisitions.view',
        'edd.view',
        'edd.manage',
        'periodicals.view',
        'periodicals.manage',
        'calendar.view',
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
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            'member' => self::MEMBER,
            'librarian' => array_values(array_unique([
                ...self::MEMBER,
                ...self::LIBRARIAN_EXTRA,
                'external_resources.manage',
            ])),
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

            $this->command?->info(sprintf('Role %-10s — %d permissions', $roleName, count($permissions)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
