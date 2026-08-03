<?php

namespace Tests\Feature;

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
