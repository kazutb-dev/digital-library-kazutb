<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry boundary for the administrative shell.
 *
 * Individual routes still require their exact permission. This outer check
 * only ensures that a canonical administrator or a future delegated role has
 * at least one genuine control-plane capability.
 */
class EnsureControlPlaneAccess
{
    public const PERMISSIONS = [
        // Identities and access policy.
        'users.manage',
        'roles.manage',

        // Audit, settings and institutional structure.
        'system.logs',
        'system.settings',
        'branches.manage',

        // Content and licensed resources.
        'news.create',
        'news.edit_own',
        'news.edit_any',
        'news.delete',
        'news.publish',
        'external_resources.manage',

        // Reader requests.
        'messages.view_all',
        'messages.view_assigned',
        'messages.resolve',

        // System-wide analytics and exports.
        'reports.view_full',
        'reports.export',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->hasRole('admin') && $user->hasAnyRole([
            'librarian',
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer',
        ])) {
            abort(403);
        }

        if (! $user->hasRole('admin') && ! $user->hasAnyPermission(self::PERMISSIONS)) {
            abort(403);
        }

        return $next($request);
    }
}
