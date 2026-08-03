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
        'copies.create',
        'copies.edit',
        'copies.delete',
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
        'digital.delete',
        'news.create',
        'news.edit_own',
        'news.edit_any',
        'news.delete',
        'news.publish',
        'repository.upload',
        'repository.approve',
        'repository.publish',
        'repository.remove',
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
        'users.manage',
        'roles.manage',
        'system.settings',
        'system.logs',
        'branches.manage',
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
