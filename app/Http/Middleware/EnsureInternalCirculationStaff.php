<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalCirculationStaff
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('library.user');

        if (! is_array($user)) {
            return $this->forbiddenResponse();
        }

        $role = mb_strtolower(trim((string) ($user['role'] ?? '')));

        if (! in_array($role, ['librarian', 'admin'], true)) {
            return $this->forbiddenResponse();
        }

        $request->attributes->set('internal_staff_user', $user);

        return $next($request);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'error' => 'staff_authorization_required',
            'message' => 'Internal circulation routes require staff authorization.',
            'success' => false,
        ], 403);
    }
}
