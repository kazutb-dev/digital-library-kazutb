<?php

use App\Http\Middleware\EnsureAdminStaff;
use App\Http\Middleware\EnsureAuthenticatedReader;
use App\Http\Middleware\EnsureIntegrationBoundary;
use App\Http\Middleware\EnsureInternalCirculationStaff;
use App\Http\Middleware\EnsureLibrarianStaff;
use App\Http\Middleware\EnsureMemberReader;
use App\Http\Middleware\LogIntegrationRequest;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetRequestLocale::class,
        ]);

        $middleware->alias([
            'admin.staff' => EnsureAdminStaff::class,
            'internal.circulation.staff' => EnsureInternalCirculationStaff::class,
            'integration.boundary' => EnsureIntegrationBoundary::class,
            'integration.log' => LogIntegrationRequest::class,
            'librarian.staff' => EnsureLibrarianStaff::class,
            'library.auth' => EnsureAuthenticatedReader::class,
            'member.reader' => EnsureMemberReader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
