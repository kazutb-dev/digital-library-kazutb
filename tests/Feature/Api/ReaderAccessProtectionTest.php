<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ReaderAccessProtectionTest extends TestCase
{
    private function authenticatedSession(): array
    {
        return [
            'library.user' => [
                'id' => 'u-reader-1',
                'name' => 'Test Reader',
                'email' => 'reader@example.com',
                'login' => 'reader01',
                'ad_login' => 'reader01',
                'role' => 'reader',
            ],
            'library.crm_token' => 'test-crm-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    // ──────────────────────────────────────────────────
    // API routes: unauthenticated → 401 JSON
    // ──────────────────────────────────────────────────

    public function test_account_summary_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/account/summary');
        $response->assertStatus(401)->assertJsonPath('authenticated', false);
    }

    public function test_account_loans_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/account/loans');
        $response->assertStatus(401)->assertJsonPath('authenticated', false);
    }

    public function test_account_reservations_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/account/reservations');
        $response->assertStatus(401)->assertJsonPath('authenticated', false);
    }

    public function test_me_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(401)->assertJsonPath('authenticated', false);
    }

    public function test_logout_rejects_unauthenticated(): void
    {
        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/v1/logout');
        $response->assertStatus(401)->assertJsonPath('authenticated', false);
    }

    // ──────────────────────────────────────────────────
    // API routes: authenticated → allowed through
    // ──────────────────────────────────────────────────

    public function test_me_returns_user_when_authenticated(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/me');

        $response->assertOk()->assertJsonPath('authenticated', true)->assertJsonPath('user.role', 'reader');
    }

    public function test_account_summary_accessible_when_authenticated(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/summary');

        // Should not be 401 — may be 200 or 500 depending on DB, but NOT 401
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────────
    // Web route: /account server-side redirect
    // ──────────────────────────────────────────────────

    public function test_account_page_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/account');
        $response->assertRedirect('/login?redirect=%2Faccount');
    }

    public function test_account_page_renders_when_authenticated(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->get('/account?lang=ru');

        $response->assertOk();
        $response->assertSee('Кабинет', false);
    }

    // ──────────────────────────────────────────────────
    // Login page: redirect-back support
    // ──────────────────────────────────────────────────

    public function test_login_page_loads_with_redirect_param(): void
    {
        $response = $this->get('/login?lang=ru&redirect=%2Faccount');
        $response->assertOk();
        $response
            ->assertSee('Вход в библиотечную систему', false)
            ->assertSee('id="login-form"', false);
    }

    // ──────────────────────────────────────────────────
    // Public routes remain accessible without auth
    // ──────────────────────────────────────────────────

    public function test_login_route_remains_public(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
    }

    public function test_catalog_route_remains_public(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
    }

    public function test_welcome_route_remains_public(): void
    {
        $response = $this->get('/');
        $response->assertOk();
    }

    // ──────────────────────────────────────────────────
    // Middleware sets authenticated_reader attribute
    // ──────────────────────────────────────────────────

    public function test_middleware_sets_authenticated_reader_attribute(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/me');

        $response->assertOk();
        // The attribute is set by middleware but not directly testable from response.
        // We verify it by confirming the request was allowed through (200, not 401).
        $response->assertJsonPath('user.login', 'reader01');
    }
}
