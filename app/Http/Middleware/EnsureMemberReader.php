<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request comes from an ordinary member (role === 'reader').
 *
 * The member module under /dashboard/* is shared by all ordinary users —
 * students, teachers and employees — who all carry role='reader' (profile_type
 * distinguishes them). Librarians and administrators MUST use their own
 * operational shells (/librarian, /admin) and are denied here.
 */
class EnsureMemberReader
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('library.user');

        if (! is_array($user)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->unauthenticatedResponse();
            }

            return redirect('/login?redirect=' . urlencode($request->getRequestUri()));
        }

        $role = mb_strtolower(trim((string) ($user['role'] ?? '')));

        if ($role !== 'reader') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->forbiddenResponse();
            }

            // Redirect staff/admin users to their canonical shells instead of returning 403.
            return match ($role) {
                'admin'     => redirect('/admin'),
                'librarian' => redirect('/librarian'),
                default     => redirect('/login'),
            };
        }

        $request->attributes->set('member_reader', $user);

        return $next($request);
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return response()->json([
            'authenticated' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'authenticated' => true,
            'authorized' => false,
            'message' => 'Forbidden — the member dashboard is reserved for ordinary library users.',
        ], 403);
    }
}
