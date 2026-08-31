<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderNotification;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class RoleNavigationIntegrityTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_is_redirected_from_every_protected_shell(): void
    {
        foreach (['/dashboard', '/librarian', '/admin'] as $path) {
            $this->get($path)->assertRedirect();
        }
    }

    public function test_every_role_landing_and_visible_navigation_link_avoids_server_errors(): void
    {
        $matrix = [
            'member' => '/dashboard',
            'librarian' => '/librarian',
            'senior_librarian' => '/librarian',
            'director' => '/librarian',
            'acquisitions' => '/librarian',
            'cataloguer' => '/librarian',
            'bibliographer' => '/librarian',
            'admin' => '/admin',
        ];

        foreach ($matrix as $role => $landing) {
            $user = $this->makeControlPlaneUser($role);
            $response = $this->signInToLibraryAs($user)->get($landing);
            $response->assertOk();
            $this->assertHealthyBody($response->getContent(), $role.' '.$landing);

            foreach ($this->visibleStaticRoleLinks($response->getContent()) as $path) {
                $linked = $this->signInToLibraryAs($user)->get($path);
                $this->assertSame(200, $linked->getStatusCode(), $role.' visible navigation link '.$path);
                $this->assertHealthyBody($linked->getContent(), $role.' '.$path);
            }
        }
    }

    public function test_member_get_routes_and_cross_role_denials_have_deliberate_statuses(): void
    {
        $member = $this->makeControlPlaneUser('member');
        foreach ([
            '/dashboard', '/dashboard/loans', '/dashboard/reservations', '/dashboard/history',
            '/dashboard/fines', '/dashboard/incidents', '/dashboard/notifications',
            '/dashboard/digital-materials', '/dashboard/collections', '/dashboard/messages',
            '/dashboard/card', '/dashboard/profile', '/dashboard/search',
        ] as $path) {
            $this->signInToLibraryAs($member)->get($path)->assertOk();
        }

        $this->signInToLibraryAs($member)->get('/librarian')->assertForbidden();
        $this->signInToLibraryAs($member)->get('/admin')->assertForbidden();

        $staff = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($staff)->get('/dashboard')->assertRedirect('/librarian');
    }

    public function test_repository_edit_permission_has_a_live_route_and_sidebar_destination(): void
    {
        $role = Role::query()->create(['name' => 'repository-editor', 'guard_name' => 'web']);
        $role->syncPermissions(['repository.edit']);
        $editor = $this->makeControlPlaneUser('member');
        $editor->syncRoles([$role]);

        $this->signInToLibraryAs($editor)
            ->get('/librarian')
            ->assertOk()
            ->assertSee(route('librarian.repository'), false);

        $this->signInToLibraryAs($editor)->get(route('librarian.repository'))->assertOk();
        $this->signInToLibraryAs($editor)->get(route('librarian.catalog.index'))->assertForbidden();
    }

    public function test_implemented_staff_entry_permissions_admit_custom_roles_to_live_destinations(): void
    {
        $destinations = [
            'visits.record' => 'librarian.visits.index',
            // The compact control-plane concern intentionally omits the new
            // acquisition_batches migration; the shell still proves this
            // capability is accepted by the operational boundary.
            'acquisitions.view' => 'librarian.overview',
            'tasks.view' => 'librarian.workspace.tasks',
            'inventory.view' => 'librarian.inventory.index',
            'repository.review_metadata' => 'librarian.repository',
            'digital.review_metadata' => 'librarian.digital-materials.index',
            'external_resources.review' => 'librarian.external-resources.review',
            'integrations.view' => 'admin.integrations.index',
        ];

        foreach ($destinations as $permission => $routeName) {
            $role = Role::query()->create([
                'name' => 'custom-'.str_replace(['.', '_'], '-', $permission),
                'guard_name' => 'web',
            ]);
            $role->syncPermissions([$permission]);
            $staff = $this->makeControlPlaneUser('member');
            $staff->syncRoles([$role]);

            $this->signInToLibraryAs($staff)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    public function test_sidebar_does_not_link_action_only_permissions_to_unreachable_indexes(): void
    {
        $role = Role::query()->create(['name' => 'action-only-staff', 'guard_name' => 'web']);
        $role->syncPermissions([
            'visits.record',
            'catalog.view_udc',
            'copies.delete',
            'acquisitions.intake',
        ]);
        $staff = $this->makeControlPlaneUser('member');
        $staff->syncRoles([$role]);

        $response = $this->signInToLibraryAs($staff)->get(route('librarian.overview'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('librarian.visits.index').'"', false)
            ->assertDontSee('href="'.route('librarian.catalog.index').'"', false)
            ->assertDontSee('href="'.route('librarian.copies.index').'"', false)
            ->assertDontSee('href="'.route('librarian.acquisitions.index').'"', false);
    }

    public function test_member_own_scope_routes_declare_their_exact_permissions(): void
    {
        $expected = [
            'member.dashboard' => ['permission:member.dashboard.view'],
            'member.loans' => ['permission:loans.view_own'],
            'member.card' => ['permission:reader_card.view_own'],
            'member.card.printed' => ['permission:reader_card.print_own'],
            'member.loans.renew' => ['permission:loans.renew_own'],
            'member.reservations' => ['permission:reservation.view_own'],
            'member.reservations.cancel' => ['permission:reservation.cancel_own'],
            'member.collections.index' => ['permission:collections.manage_own|collections.view_public'],
            'member.collections.store' => ['permission:collections.manage_own'],
            'member.collections.show' => ['permission:collections.manage_own|collections.view_public'],
            'member.collections.update' => ['permission:collections.manage_own'],
            'member.collections.destroy' => ['permission:collections.manage_own'],
            'member.collections.items.add' => ['permission:collections.manage_own'],
            'member.collections.items.remove' => ['permission:collections.manage_own'],
            'member.collections.reorder' => ['permission:collections.manage_own'],
            'member.collections.follow' => ['permission:collections.view_public'],
            'member.collections.copy' => ['permission:collections.view_public', 'permission:collections.manage_own'],
            'member.history' => ['permission:circulation.view_own_history'],
            'member.fines' => ['permission:fines.view_own'],
            'member.incidents.index' => ['permission:incidents.view_own'],
            'member.incidents.show' => ['permission:incidents.view_own'],
            'member.notifications' => ['permission:notifications.view_own'],
            'member.notifications.read-all' => ['permission:notifications.manage_own'],
            'member.notifications.read' => ['permission:notifications.manage_own'],
            'member.profile.update' => ['permission:profile.update_own'],
            'member.messages' => ['permission:messages.view_own'],
            'member.messages.show' => ['permission:messages.view_own'],
            'member.messages.attachments.show' => ['permission:messages.view_own'],
        ];

        foreach ($expected as $name => $middleware) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertNotNull($route, 'Missing member route '.$name);

            foreach ($middleware as $expectedMiddleware) {
                $this->assertContains($expectedMiddleware, $route->gatherMiddleware(), $name);
            }
        }

        $apiExpected = [
            ['GET', 'api/v1/account/summary', 'permission:member.dashboard.view'],
            ['GET', 'api/v1/account/loans', 'permission:loans.view_own'],
            ['GET', 'api/v1/account/loans/summary', 'permission:loans.view_own'],
            ['POST', 'api/v1/account/loans/{loanId}/renew', 'permission:loans.renew_own'],
            ['GET', 'api/v1/account/reservations', 'permission:reservation.view_own'],
            ['POST', 'api/v1/account/reservations', 'permission:reservation.create'],
            ['POST', 'api/v1/account/reservations/{id}/cancel', 'permission:reservation.cancel_own'],
            ['GET', 'api/v1/account/reservations/check', 'permission:reservation.view_own'],
        ];

        foreach ($apiExpected as [$method, $uri, $expectedMiddleware]) {
            $route = collect(RouteFacade::getRoutes()->getRoutes())->first(
                fn ($route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true),
            );
            $this->assertNotNull($route, 'Missing member API route '.$method.' '.$uri);
            $this->assertContains($expectedMiddleware, $route->gatherMiddleware(), $method.' '.$uri);
        }
    }

    public function test_custom_member_permission_revocation_blocks_reads_mutations_and_dead_sidebar_links(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $role = Role::findByName('member', 'web');
        foreach ([
            'loans.view_own',
            'reservation.view_own',
            'fines.view_own',
            'incidents.view_own',
            'notifications.view_own',
            'notifications.manage_own',
            'collections.manage_own',
            'collections.view_public',
            'messages.view_own',
            'reader_card.view_own',
            'shortlist.manage_own',
        ] as $permission) {
            $role->revokePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $member = $member->fresh();
        ReaderNotification::query()->create([
            'user_id' => $member->getKey(),
            'event_type' => 'security-regression',
            'title' => 'REVOKED-NOTIFICATION-MUST-STAY-HIDDEN',
        ]);

        $dashboard = $this->signInToLibraryAs($member)->get(route('member.dashboard'));
        $dashboard->assertOk()->assertDontSee('REVOKED-NOTIFICATION-MUST-STAY-HIDDEN', false);
        foreach ([
            'member.loans',
            'member.reservations',
            'member.fines',
            'member.incidents.index',
            'member.notifications',
            'member.collections.index',
            'member.messages',
            'member.card',
        ] as $routeName) {
            $dashboard->assertDontSee(route($routeName), false);
        }

        $this->signInToLibraryAs($member)->get(route('member.loans'))->assertForbidden();
        $this->signInToLibraryAs($member)->getJson('/api/v1/account/loans')->assertForbidden();
        $this->signInToLibraryAs($member)->post(route('member.notifications.read-all'))->assertForbidden();
        $this->signInToLibraryAs($member)->getJson('/api/v1/shortlist')->assertForbidden();
        $this->signInToLibraryAs($member)->postJson('/api/v1/shortlist', [
            'identifier' => 'permission-revoked',
            'title' => 'Must not be persisted',
        ])->assertForbidden();
    }

    public function test_standard_member_permissions_allow_reader_reads_and_mutations(): void
    {
        $member = $this->makeControlPlaneUser('member');

        $this->signInToLibraryAs($member)->get(route('member.loans'))->assertOk();
        $this->signInToLibraryAs($member)->getJson('/api/v1/account/loans')->assertOk();
        $this->signInToLibraryAs($member)->post(route('member.notifications.read-all'))->assertRedirect();
        $this->signInToLibraryAs($member)->getJson('/api/v1/shortlist')->assertOk();
        $this->signInToLibraryAs($member)->postJson('/api/v1/shortlist', [
            'identifier' => 'standard-member',
            'title' => 'Allowed shortlist item',
        ])->assertCreated();
    }

    public function test_every_account_api_route_enforces_its_canonical_member_permission(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $role = Role::findByName('member', 'web');

        foreach ($this->accountPermissionCases() as $case) {
            $this->assertTrue(
                $member->can($case['permission']),
                $case['uri'].' must be granted to the canonical member before the route-level negative probe',
            );

            $role->revokePermissionTo($case['permission']);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $member->unsetRelation('roles')->unsetRelation('permissions');

            $this->signInToLibraryAs($member);
            $this->requestAccountCase($case)->assertForbidden();

            $role->givePermissionTo($case['permission']);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $member->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function test_session_only_account_api_identity_fails_closed_at_the_real_permission_middleware(): void
    {
        $session = [
            'library.user' => [
                'id' => 'legacy-session-only',
                'name' => 'Legacy Session Only',
                'email' => 'legacy-session-only@example.test',
                'role' => 'reader',
            ],
        ];

        foreach ($this->accountPermissionCases() as $case) {
            $this->withSession($session);
            $this->requestAccountCase($case)->assertForbidden();
        }
    }

    public function test_guest_shortlist_remains_session_scoped(): void
    {
        $this->getJson('/api/v1/shortlist')->assertOk();
    }

    /** @return list<array{method: string, uri: string, permission: string, payload?: array<string, mixed>}> */
    private function accountPermissionCases(): array
    {
        return [
            ['method' => 'GET', 'uri' => '/api/v1/account/summary', 'permission' => 'member.dashboard.view'],
            ['method' => 'GET', 'uri' => '/api/v1/account/loans', 'permission' => 'loans.view_own'],
            ['method' => 'GET', 'uri' => '/api/v1/account/loans/summary', 'permission' => 'loans.view_own'],
            ['method' => 'POST', 'uri' => '/api/v1/account/loans/not-a-uuid/renew', 'permission' => 'loans.renew_own'],
            ['method' => 'GET', 'uri' => '/api/v1/account/reservations', 'permission' => 'reservation.view_own'],
            ['method' => 'GET', 'uri' => '/api/v1/account/reservations/check', 'permission' => 'reservation.view_own'],
            [
                'method' => 'POST',
                'uri' => '/api/v1/account/reservations',
                'permission' => 'reservation.create',
                'payload' => ['bookId' => 'not-a-uuid'],
            ],
            ['method' => 'POST', 'uri' => '/api/v1/account/reservations/not-a-uuid/cancel', 'permission' => 'reservation.cancel_own'],
        ];
    }

    /** @param array{method: string, uri: string, permission: string, payload?: array<string, mixed>} $case */
    private function requestAccountCase(array $case): TestResponse
    {
        return $case['method'] === 'POST'
            ? $this->postJson($case['uri'], $case['payload'] ?? [])
            : $this->getJson($case['uri']);
    }

    /** @return list<string> */
    private function visibleStaticRoleLinks(string $html): array
    {
        preg_match_all('/<a\b[^>]*href="([^"]+)"/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $href): string => html_entity_decode($href, ENT_QUOTES))
            ->filter(fn (string $href): bool => (bool) preg_match('#^/(dashboard|librarian|admin)(?:/|$)#', $href))
            ->reject(fn (string $href): bool => str_contains($href, '/export') || (bool) preg_match('#/\d+(?:/|$)#', parse_url($href, PHP_URL_PATH) ?: ''))
            ->unique()
            ->values()
            ->all();
    }

    private function assertHealthyBody(string $body, string $context): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/SQLSTATE|Stack trace|Service temporarily unavailable|Сервис временно недоступен|Whoops, looks like something went wrong/i',
            $body,
            $context,
        );
    }
}
