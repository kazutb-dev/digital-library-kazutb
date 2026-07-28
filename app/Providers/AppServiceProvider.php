<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $locale = in_array(app()->getLocale(), ['ru', 'kk', 'en'], true) ? app()->getLocale() : 'ru';

        app()->setLocale($locale);
        View::share('pageLang', $locale);

        // Login rate limiter — prevents brute force on /api/login
        $loginLimit = (int) env('LOGIN_RATE_LIMIT', 5);
        RateLimiter::for('login', function (Request $request) use ($loginLimit) {
            $key = 'login:'.($request->input('login') ?? $request->input('email') ?? '').'|'.$request->ip();

            return Limit::perMinute($loginLimit)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'message' => 'Слишком много попыток входа. Попробуйте через минуту.',
                    ], 429);
                });
        });

        // CRM integration rate limits — per client reference (bearer token hash)
        // Configurable via env: INTEGRATION_RATE_LIMIT (default 120),
        // INTEGRATION_MUTATE_RATE_LIMIT (default 30)
        $globalLimit = (int) env('INTEGRATION_RATE_LIMIT', 120);
        $mutateLimit = (int) env('INTEGRATION_MUTATE_RATE_LIMIT', 30);

        RateLimiter::for('integration', function (Request $request) use ($globalLimit) {
            $clientRef = $request->attributes->get('integration.authenticated_client_ref', 'unknown');

            return Limit::perMinute($globalLimit)->by('integration:'.$clientRef);
        });

        // Stricter limiter for mutation (write) endpoints
        RateLimiter::for('integration-mutate', function (Request $request) use ($mutateLimit) {
            $clientRef = $request->attributes->get('integration.authenticated_client_ref', 'unknown');

            return Limit::perMinute($mutateLimit)
                ->by('integration:mutate:'.$clientRef)
                ->response(function () use ($mutateLimit) {
                    return response()->json([
                        'error' => [
                            'error_code' => 'rate_limit_exceeded',
                            'reason_code' => 'mutation_rate_exceeded',
                            'message' => "Mutation rate limit exceeded. Max {$mutateLimit} write operations per minute.",
                        ],
                    ], 429);
                });
        });
    }
}
