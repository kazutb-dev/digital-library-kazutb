<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ReaderAccountCompletionTest extends TestCase
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
            'library.authenticated_at' => '2026-04-06T00:00:00+00:00',
        ];
    }

    // ──────────────────────────────────────────────────
    // /api/v1/me enrichment
    // ──────────────────────────────────────────────────

    public function test_me_returns_authenticated_at(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('authenticated_at', '2026-04-06T00:00:00+00:00')
            ->assertJsonPath('user.role', 'reader');
    }

    public function test_me_response_shape_is_backward_compatible(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonStructure([
                'authenticated',
                'user' => ['id', 'name', 'email', 'login', 'ad_login', 'role'],
                'authenticated_at',
            ]);
    }

    // ──────────────────────────────────────────────────
    // Controller uses middleware-provided user
    // ──────────────────────────────────────────────────

    public function test_account_summary_uses_middleware_user(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/summary');

        // Middleware provides user → controller uses it → returns authenticated=true
        $response->assertJsonPath('authenticated', true);
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_account_loans_uses_middleware_user(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/loans');

        $response->assertJsonPath('authenticated', true);
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_account_reservations_uses_middleware_user(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/reservations');

        $response->assertJsonPath('authenticated', true);
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────────
    // Loans empty state (no reader profile)
    // ──────────────────────────────────────────────────

    public function test_loans_returns_empty_data_when_no_reader_profile(): void
    {
        $session = $this->authenticatedSession();
        $session['library.user']['email'] = 'nonexistent-reader-unique-99@test.invalid';
        $session['library.user']['ad_login'] = 'nonexistent_ad_unique_99';

        $response = $this
            ->withSession($session)
            ->getJson('/api/v1/account/loans');

        $response->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'No linked reader profile found.');
    }

    // ──────────────────────────────────────────────────
    // Logout with CSRF
    // ──────────────────────────────────────────────────

    public function test_logout_with_session_succeeds(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/v1/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_logout_clears_session(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/v1/logout');

        $response->assertOk();

        // Verify session is cleared
        $meResponse = $this->getJson('/api/v1/me');
        $meResponse->assertStatus(401);
    }

    // ──────────────────────────────────────────────────
    // Account page server-rendered with session user
    // ──────────────────────────────────────────────────

    public function test_account_page_renders_session_user_name(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->get('/account');

        $response->assertOk();
        $response->assertSee('Test Reader', false);
        $response->assertSee('reader01', false);
    }

    public function test_account_page_server_renders_user_name_not_fallback(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->get('/account');

        $response->assertOk();
        // The profile name should be server-rendered from $sessionUser, not auth() fallback
        $response->assertSee('<h1 id="profile-name" class="profile-name">Test Reader</h1>', false);
    }

    // ──────────────────────────────────────────────────
    // Loans status filter
    // ──────────────────────────────────────────────────

    public function test_loans_accepts_status_filter(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/loans?status=active');

        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(422, $response->getStatusCode());
    }

    public function test_loans_ignores_invalid_status(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/loans?status=INVALID');

        // Should not error — invalid status is silently ignored (returns all loans)
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(422, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────────
    // Reservations status filter
    // ──────────────────────────────────────────────────

    public function test_reservations_accepts_status_filter(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->getJson('/api/v1/account/reservations?status=PENDING');

        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(422, $response->getStatusCode());
    }

    // ──────────────────────────────────────────────────
    // Logout redirect target in page source
    // ──────────────────────────────────────────────────

    public function test_account_page_logout_redirects_to_login(): void
    {
        $response = $this
            ->withSession($this->authenticatedSession())
            ->get('/account');

        $response->assertOk();
        $response->assertSee("window.location.href = withLang('/login')", false);
    }
}
