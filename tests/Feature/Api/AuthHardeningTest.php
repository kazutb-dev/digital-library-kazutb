<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    // ──────────────────────────────────────────────────
    // Login: information leak prevention
    // ──────────────────────────────────────────────────

    public function test_login_failure_does_not_leak_crm_response_details(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Invalid credentials', 'debug' => 'internal-detail'], 401),
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'bad-user',
                'password' => 'bad-pass',
            ]);

        $response->assertStatus(401);

        $json = $response->json();
        $this->assertArrayNotHasKey('details', $json, 'CRM response details must not be leaked');
        $this->assertArrayNotHasKey('status', $json, 'CRM status code must not be leaked');
        $this->assertArrayNotHasKey('debug', $json);
    }

    public function test_login_crm_unavailable_does_not_leak_exception_message(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection refused for http://10.0.1.47/api/login');
            },
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'user',
                'password' => 'pass',
            ]);

        $response->assertStatus(503);

        $json = $response->json();
        $this->assertArrayNotHasKey('error', $json, 'Exception message must not be leaked');
        $this->assertArrayHasKey('message', $json);
        $this->assertStringNotContainsString('10.0.1.47', $json['message']);
        $this->assertStringNotContainsString('Connection refused', $json['message']);
    }

    public function test_login_success_returns_user_without_token(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'crm-test-bearer-abc123',
                'user' => [
                    'id' => 'u-1',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'login' => 'testuser',
                    'ad_login' => 'testuser',
                    'role' => 'reader',
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'testuser',
                'password' => 'correct-pass',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        // Bearer token must never be returned to the client
        $json = $response->json();
        $this->assertArrayNotHasKey('token', $json);
        $this->assertArrayNotHasKey('crm_token', $json);
        $this->assertArrayNotHasKey('access_token', $json);
    }

    public function test_login_success_without_token_returns_502_and_does_not_authenticate_session(): void
    {
        Http::fake([
            '*' => Http::response([
                'user' => [
                    'id' => 'u-missing-token',
                    'name' => 'Tokenless User',
                    'email' => 'tokenless@example.com',
                    'role' => 'reader',
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'tokenless',
                'password' => 'correct-pass',
            ]);

        $response
            ->assertStatus(502)
            ->assertJsonPath('message', 'Authentication service returned an unexpected response.');

        $this->assertNull(session('library.crm_token'));
        $this->assertNull(session('library.user'));
    }

    public function test_login_normalizes_unknown_role_to_reader(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'crm-test-bearer-abc123',
                'user' => [
                    'id' => 'u-unknown-role',
                    'name' => 'Unknown Role',
                    'email' => 'unknown-role@example.com',
                    'login' => 'unknown-role',
                    'ad_login' => 'unknown-role',
                    'role' => 'supervisor',
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'unknown-role',
                'password' => 'correct-pass',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'reader');
    }

    public function test_login_normalizes_whitespace_padded_librarian_role(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'crm-test-bearer-abc123',
                'user' => [
                    'id' => 'u-librarian',
                    'name' => 'Librarian User',
                    'email' => 'librarian@example.com',
                    'login' => 'librarian-user',
                    'ad_login' => 'librarian-user',
                    'role' => ' Librarian ',
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'librarian-user',
                'password' => 'correct-pass',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'librarian');
    }

    public function test_browser_login_redirects_to_account_and_stores_session(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'crm-browser-token-123',
                'user' => [
                    'id' => 'u-browser',
                    'name' => 'Browser User',
                    'email' => 'browser@example.com',
                    'login' => 'browser-user',
                    'role' => 'reader',
                ],
            ], 200),
        ]);

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/login', [
                'login' => 'browser-user',
                'password' => 'correct-pass',
            ]);

        $response->assertRedirect('/dashboard');
        $this->assertSame('crm-browser-token-123', session('library.crm_token'));
        $this->assertSame('browser@example.com', session('library.user.email'));
    }

    // ──────────────────────────────────────────────────
    // Login: failure logging
    // ──────────────────────────────────────────────────

    public function test_failed_login_is_logged(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Invalid credentials'], 401),
        ]);

        Log::spy();

        $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'bad-user',
                'password' => 'wrong-pass',
            ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) {
                return $message === 'Library CRM login failed'
                    && ($context['login'] ?? '') === 'bad-user'
                    && ($context['crm_status'] ?? 0) === 401;
            })
            ->once();
    }

    public function test_crm_unavailable_is_logged_as_error(): void
    {
        Http::fake([
            '*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        Log::spy();

        $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'login' => 'user',
                'password' => 'pass',
            ]);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) {
                return $message === 'CRM auth service unavailable'
                    && ($context['login'] ?? '') === 'user'
                    && isset($context['error']);
            })
            ->once();
    }

    // ──────────────────────────────────────────────────
    // Login: rate limiting
    // ──────────────────────────────────────────────────

    public function test_login_has_rate_limiting(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Invalid credentials'], 401),
        ]);

        // Verify the login route has the throttle middleware applied
        $routes = Route::getRoutes();
        $loginRoute = $routes->match(
            Request::create('/api/login', 'POST')
        );

        $middleware = $loginRoute->gatherMiddleware();
        $hasThrottle = collect($middleware)->contains(function ($m) {
            return str_contains((string) $m, 'throttle:login');
        });

        $this->assertTrue($hasThrottle, 'Login route must have throttle:login middleware');
    }

    public function test_login_validation_rejects_blank_credentials(): void
    {
        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/login', [
                'email' => '',
                'login' => '',
                'password' => '',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'login', 'password']);
    }

    // ──────────────────────────────────────────────────
    // Integration boundary: token allowlist
    // ──────────────────────────────────────────────────

    public function test_integration_rejects_invalid_token_when_allowlist_configured(): void
    {
        Config::set('services.integration.allowed_tokens', 'valid-token-abc,valid-token-def');

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer wrong-token',
                'X-Request-Id' => 'req-t1',
                'X-Correlation-Id' => 'corr-t1',
                'X-Source-System' => 'crm',
                'X-Operator-Id' => 'op-1',
                'X-Operator-Roles' => 'reservations.approve',
                'X-Operator-Org-Context' => '{"branch":"main"}',
            ])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertStatus(401)
            ->assertJsonPath('error.reason_code', 'invalid_bearer_token');
    }

    public function test_integration_accepts_valid_token_when_allowlist_configured(): void
    {
        Config::set('services.integration.allowed_tokens', 'valid-token-abc,valid-token-def');

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer valid-token-abc',
                'X-Request-Id' => 'req-t2',
                'X-Correlation-Id' => 'corr-t2',
                'X-Source-System' => 'crm',
                'X-Operator-Id' => 'op-1',
                'X-Operator-Roles' => 'reservations.approve',
                'X-Operator-Org-Context' => '{"branch":"main"}',
            ])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response->assertOk()->assertJsonPath('ok', true);
    }

    public function test_integration_accepts_any_token_when_allowlist_not_configured(): void
    {
        Config::set('services.integration.allowed_tokens', '');

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer any-random-token',
                'X-Request-Id' => 'req-t3',
                'X-Correlation-Id' => 'corr-t3',
                'X-Source-System' => 'crm',
                'X-Operator-Id' => 'op-1',
                'X-Operator-Roles' => 'reservations.approve',
                'X-Operator-Org-Context' => '{"branch":"main"}',
            ])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response->assertOk()->assertJsonPath('ok', true);
    }

    // ──────────────────────────────────────────────────
    // Login view: no CRM URL exposure
    // ──────────────────────────────────────────────────

    public function test_login_page_does_not_expose_crm_api_url(): void
    {
        $response = $this->get('/login');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('10.0.1.47', $content, 'CRM internal IP must not be in login page HTML');
        $this->assertStringNotContainsString('http://10.0.1.47', $content);
    }
}
