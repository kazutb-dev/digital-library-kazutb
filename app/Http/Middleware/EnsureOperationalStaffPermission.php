<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets future custom library roles enter the operational workspace without
 * weakening its boundary for ordinary members.
 */
class EnsureOperationalStaffPermission
{
    /**
     * Permissions that distinguish an operational staff profile from a reader.
     *
     * @var list<string>
     */
    private const STAFF_PERMISSIONS = [
        'catalog.create_record',
        'catalog.edit_record',
        'catalog.delete_record',
        'catalog.merge_duplicates',
        'catalog.import',
        'catalog.view_raw_marc',
        'copies.create',
        'copies.edit',
        'copies.delete',
        'copies.movements.view',
        'copies.movements.create',
        'copies.write_off',
        'visits.record',
        'inventory.view',
        'fines.view',
        'incidents.view',
        'ksu.view',
        'ksu.manage',
        'ksu.resolve',
        'reservation.cancel_any',
        'reservation.confirm',
        'reservation.override_limits',
        'circulation.issue',
        'circulation.return',
        'circulation.renew',
        'circulation.override_limits',
        'circulation.view_any_history',
        'digital.upload',
        'digital.set_access_flags',
        'digital.review_metadata',
        'digital.review_rights',
        'digital.approve',
        'digital.publish',
        'digital.delete',
        'news.create',
        'news.edit_own',
        'news.edit_any',
        'news.delete',
        'news.publish',
        'repository.upload',
        'repository.edit',
        'repository.review_metadata',
        'repository.review_rights',
        'repository.request_changes',
        'repository.approve',
        'repository.publish',
        'repository.withdraw',
        'repository.remove',
        'messages.view_all',
        'messages.view_assigned',
        'messages.resolve',
        'reports.view_ops',
        'reports.view_full',
        'reports.view_acquisitions',
        'reports.export',
        'staff_performance.view',
        'acquisitions.view',
        'acquisitions.create_order',
        'acquisitions.receive',
        'acquisitions.manage',
        'acquisitions.intake',
        'acquisitions.confirm',
        'tasks.view',
        'tasks.manage_own',
        'tasks.assign',
        'edd.view',
        'edd.manage',
        'periodicals.view',
        'periodicals.manage',
        'calendar.view',
        'news.manage_annual_plan',
        'data_cleanup.access',
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
        'external_resources.manage',
        'external_resources.review',
        'external_resources.publish',
        'integrations.view',
        'integrations.health',
        'users.manage',
        'roles.manage',
        'system.settings',
        'library.settings.manage',
        'system.logs',
        'branches.manage',
        'legacy_recovery.view',
        'legacy_recovery.review',
        'legacy_recovery.resolve',
        'legacy_recovery.manage',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->hasAnyRole([
            'librarian',
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer',
            'admin',
        ])
            && ! $user->hasAnyPermission(self::STAFF_PERMISSIONS)) {
            abort(403);
        }

        return $next($request);
    }
}
