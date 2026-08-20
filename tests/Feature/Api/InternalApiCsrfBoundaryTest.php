<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class InternalApiCsrfBoundaryTest extends TestCase
{
    public function test_every_session_based_internal_write_route_has_csrf_middleware(): void
    {
        $audited = [];
        $missing = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $route instanceof Route || ! str_starts_with($route->uri(), 'api/v1/internal/')) {
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
            if (array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']) === []) {
                continue;
            }

            $audited[] = implode('|', $methods).' '.$route->uri();
            if (! in_array(PreventRequestForgery::class, $route->gatherMiddleware(), true)) {
                $missing[] = implode('|', $methods).' '.$route->uri();
            }
        }

        $this->assertNotEmpty($audited, 'No internal session-based write routes were discovered.');
        $this->assertSame([], $missing, 'Internal write routes missing CSRF: '.implode(', ', $missing));
    }
}
