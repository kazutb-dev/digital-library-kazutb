<?php

use App\Http\Middleware\EnsureAdminStaff;
use App\Http\Middleware\EnsureAuthenticatedReader;
use App\Http\Middleware\EnsureControlPlaneAccess;
use App\Http\Middleware\EnsureIntegrationBoundary;
use App\Http\Middleware\EnsureInternalCirculationStaff;
use App\Http\Middleware\EnsureLibrarianStaff;
use App\Http\Middleware\EnsureMemberReader;
use App\Http\Middleware\EnsureOperationalStaffPermission;
use App\Http\Middleware\LogIntegrationRequest;
use App\Http\Middleware\PrivateResponseHeaders;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\RequirePasswordChange;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RequestContext::class,
            SetRequestLocale::class,
            RequirePasswordChange::class,
        ]);
        $middleware->api(append: [
            RequestContext::class,
            // Public JSON representations must resolve metadata in exactly
            // the same locale as their Blade counterparts. API requests are
            // stateless, so the explicit `lang` query parameter is canonical.
            SetRequestLocale::class,
        ]);

        $middleware->alias([
            // Spatie RBAC — operates on the Laravel Auth guard.
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            // Legacy session-array guards, retained alongside the above while
            // the CRM login path still populates session('library.user').
            'admin.staff' => EnsureAdminStaff::class,
            'control.plane' => EnsureControlPlaneAccess::class,
            'internal.circulation.staff' => EnsureInternalCirculationStaff::class,
            'integration.boundary' => EnsureIntegrationBoundary::class,
            'integration.log' => LogIntegrationRequest::class,
            'librarian.staff' => EnsureLibrarianStaff::class,
            'library.auth' => EnsureAuthenticatedReader::class,
            'member.reader' => EnsureMemberReader::class,
            'operational.staff' => EnsureOperationalStaffPermission::class,
            'private.response' => PrivateResponseHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response): Response {
            // A long-lived sign-in tab can outlive its server-side session.
            // Refresh the form with a localized explanation instead of
            // exposing the framework's CSRF exception to the user.
            if ($response->getStatusCode() === 419 && request()->isMethod('POST') && request()->is('login')) {
                // CSRF runs before the appended locale middleware, so recover
                // the supported locale directly from this login request.
                $requestedLang = (string) (request()->input('lang') ?: request()->query('lang', app()->getLocale()));
                $lang = in_array($requestedLang, ['kk', 'ru', 'en'], true) ? $requestedLang : 'kk';
                $target = '/login'.($lang === 'kk' ? '' : '?lang='.$lang);
                $response = redirect($target)->withErrors([
                    'login' => trans('auth.messages.session_expired', [], $lang),
                ]);
            }

            $requestId = request()->attributes->get('request_id');
            $correlationId = request()->attributes->get('correlation_id');
            if (is_string($requestId) && $requestId !== '') {
                $response->headers->set('X-Request-Id', $requestId);
            }
            if (is_string($correlationId) && $correlationId !== '') {
                $response->headers->set('X-Correlation-Id', $correlationId);
            }
            Log::withoutContext(['request_id', 'correlation_id', 'route', 'method', 'path', 'actor_id', 'actor_role']);

            return $response;
        });
    })->create();
