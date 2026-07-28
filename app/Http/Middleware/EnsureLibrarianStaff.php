<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request comes from a librarian or administrator.
 *
 * Librarian surfaces live under /librarian/*. Admins inherit librarian
 * operational access by policy (PROJECT_CONTEXT §5). Ordinary members and
 * guests are denied.
 */
class EnsureLibrarianStaff
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

        if (! in_array($role, ['librarian', 'admin'], true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->forbiddenResponse();
            }

            abort(403);
        }

        $request->attributes->set('librarian_staff_user', $user);

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
            'error' => 'librarian_authorization_required',
            'message' => 'Librarian routes require librarian or administrator authorization.',
            'success' => false,
        ], 403);
    }
}
