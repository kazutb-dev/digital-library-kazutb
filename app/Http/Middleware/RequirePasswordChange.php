<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an admin password reset the account carries must_change_password;
 * every authenticated web request is redirected to the change-password
 * screen until the user sets their own password.
 */
class RequirePasswordChange
{
    /**
     * Routes that must stay reachable while the flag is set — the password
     * screen itself plus sign-out and locale switching.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.update',
        'locale.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)
            || $request->is('logout')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
