<?php

namespace App\Http\Middleware;

use App\Services\AuthSessionManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the request comes from an authenticated library user (any role).
 *
 * For API routes: returns 401 JSON.
 * For web routes: redirects to /login with ?redirect= back to the current URL.
 */
class EnsureAuthenticatedReader
{
    public function __construct(private readonly AuthSessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->session()->get('library.user');

        if ($request->user() !== null && ! $request->user()->is_active) {
            $this->sessions->logout($request, 'inactive_account');
            $user = null;
        }

        if (! is_array($user)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->jsonResponse();
            }

            return redirect('/login?redirect='.urlencode($request->getRequestUri()));
        }

        $request->attributes->set('authenticated_reader', $user);

        return $next($request);
    }

    private function jsonResponse(): JsonResponse
    {
        return response()->json([
            'authenticated' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }
}
