<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class DiagnosticApiAuthorizationTest extends TestCase
{
    use BuildsAdminControlPlane;

    /** @var list<string> */
    private const DIAGNOSTIC_ENDPOINTS = [
        '/api/v1/bridge/summary',
        '/api/v1/bridge/users',
        '/api/v1/bridge/copies',
        '/api/v1/bridge/books',
        '/api/v1/library/health-summary',
        '/api/v1/review/issues',
        '/api/v1/review/issues-summary',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_cannot_read_internal_diagnostic_apis(): void
    {
        foreach (self::DIAGNOSTIC_ENDPOINTS as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    public function test_member_cannot_read_internal_diagnostic_apis(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member);

        foreach (self::DIAGNOSTIC_ENDPOINTS as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
    }

    public function test_canonical_catalog_read_routes_remain_public(): void
    {
        foreach ([
            '/api/v1/book-db/9780000000000',
            '/api/v1/catalog-db',
            '/api/v1/catalog-facets',
            '/api/v1/subjects',
        ] as $endpoint) {
            $route = app('router')->getRoutes()->match(Request::create($endpoint, 'GET'));

            $this->assertInstanceOf(Route::class, $route);
            $this->assertNotContains('library.auth', $route->gatherMiddleware(), $endpoint);
            $this->assertNotContains('internal.circulation.staff', $route->gatherMiddleware(), $endpoint);
        }
    }

    public function test_each_diagnostic_route_has_the_least_privileged_permission_boundary(): void
    {
        $expectations = [
            '/api/v1/bridge/summary' => 'permission:legacy_recovery.view',
            '/api/v1/bridge/users' => 'permission:legacy_recovery.view',
            '/api/v1/bridge/copies' => 'permission:legacy_recovery.view',
            '/api/v1/bridge/books' => 'permission:legacy_recovery.view',
            '/api/v1/library/health-summary' => 'permission:data_quality.view',
            '/api/v1/review/issues' => 'permission:data_quality.triage',
            '/api/v1/review/issues-summary' => 'permission:data_quality.view',
        ];

        foreach ($expectations as $endpoint => $permission) {
            $route = app('router')->getRoutes()->match(Request::create($endpoint, 'GET'));
            $middleware = $route->gatherMiddleware();

            $this->assertContains('library.auth', $middleware, $endpoint);
            $this->assertContains('internal.circulation.staff', $middleware, $endpoint);
            $this->assertContains($permission, $middleware, $endpoint);
        }
    }
}
