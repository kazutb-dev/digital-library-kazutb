<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DemoAuthTest extends TestCase
{
    // ── Login disabled (default) ──────────────────────────────────

    public function test_demo_login_returns_403_when_disabled(): void
    {
        Config::set('demo_auth.enabled', false);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/demo-auth/login', ['role' => 'student']);

        $response
            ->assertForbidden()
            ->assertJsonPath('message', 'Demo login is not available.');
    }

    public function test_identities_returns_empty_when_disabled(): void
    {
        Config::set('demo_auth.enabled', false);

        $response = $this->getJson('/api/demo-auth/identities');

        $response
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('identities', []);
    }

    // ── Login enabled ─────────────────────────────────────────────

    public function test_demo_login_succeeds_for_student(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', ['role' => 'student']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'reader')
            ->assertJsonPath('user.login', 'demo_student');
    }

    public function test_demo_login_succeeds_for_teacher(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', ['role' => 'teacher']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'reader')
            ->assertJsonPath('user.login', 'demo_teacher');
    }

    public function test_demo_login_succeeds_for_librarian(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', ['role' => 'librarian']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'librarian')
            ->assertJsonPath('user.login', 'zh.pankey')
            ->assertJsonPath('user.name', 'Панкей Ж.')
            ->assertJsonPath('user.email', 'zh.pankey@kaztbu.edu.kz')
            ->assertJsonPath('user.title', 'Директор')
            ->assertJsonPath('user.phone_extension', '112');
    }

    public function test_demo_login_succeeds_for_admin(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', ['role' => 'admin']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.login', 'demo_admin');
    }

    public function test_demo_login_sets_session_correctly(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', ['role' => 'librarian']);

        $response->assertOk();

        // Verify session state by calling the account summary (which reads session)
        $meResponse = $this->getJson('/api/v1/shortlist/summary');
        $meResponse->assertOk();
    }

    public function test_demo_login_rejects_unknown_slug(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', ['role' => 'superuser']);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Unknown demo identity.');
    }

    public function test_demo_login_validates_role_required(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->postJson('/api/demo-auth/login', []);

        $response->assertUnprocessable();
    }

    // ── Identities listing ────────────────────────────────────────

    public function test_identities_lists_all_roles_when_enabled(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->getJson('/api/demo-auth/identities');

        $response
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonCount(4, 'identities');

        $slugs = collect($response->json('identities'))->pluck('slug')->all();
        $this->assertEquals(['student', 'teacher', 'librarian', 'admin'], $slugs);
    }

    public function test_identities_include_label_and_description(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->getJson('/api/demo-auth/identities');

        $response->assertOk();

        $first = $response->json('identities.0');
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('icon', $first);
        $this->assertArrayHasKey('role', $first);
    }

    // ── Login page rendering ──────────────────────────────────────

    public function test_login_page_shows_demo_cards_when_enabled(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('demo-login-block', false)
            ->assertSee('demo-card', false)
            ->assertSee('Быстрый вход', false)
            ->assertSee('demoLogin(', false);
    }

    public function test_login_page_hides_demo_cards_when_disabled(): void
    {
        Config::set('demo_auth.enabled', false);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertDontSee('id="demo-login-block"', false)
            ->assertDontSee('data-demo-slug', false)
            ->assertSee('Вход в библиотечную систему');
    }

    public function test_login_page_form_still_works_with_demo_enabled(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSee('login-form', false)
            ->assertSee('submitLogin', false)
            ->assertSee('/api/login', false);
    }

    public function test_login_page_does_not_expose_quick_fill_credentials(): void
    {
        Config::set('demo_auth.enabled', true);

        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertDontSee('Quick fill demo credentials')
            ->assertDontSee('data-quick-fill="librarian"', false)
            ->assertDontSee('data-quick-fill="admin"', false);
    }

    // ── No regression in real auth ────────────────────────────────

    public function test_real_login_route_unaffected_by_demo_config(): void
    {
        Config::set('demo_auth.enabled', true);

        // Real login should still be available (will fail to connect to CRM, but route works)
        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'realuser',
                'password' => 'realpass',
            ]);

        // Should get 503 (CRM unavailable) not 403 or 404
        $this->assertContains($response->status(), [401, 503]);
    }
}
