<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminStaff
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

        if ($role !== 'admin') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->forbiddenResponse();
            }

            abort(403);
        }

        $request->attributes->set('admin_staff_user', $user);

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
            'error' => 'admin_authorization_required',
            'message' => 'Admin routes require administrator authorization.',
            'success' => false,
        ], 403);
    }
}
