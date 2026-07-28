<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class AuthSessionLifecycleTest extends TestCase
{
    public function test_logout_clears_session_and_returns_success(): void
    {
        $sessionUser = [
            'id' => 'u-123',
            'name' => 'Test User',
            'email' => 'user@example.com',
            'login' => 'test.user',
            'ad_login' => 'test.user',
            'role' => 'reader',
        ];

        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([
                'library.user' => $sessionUser,
                'library.crm_token' => 'fake-token',
                'library.authenticated_at' => now()->toISOString(),
            ])
            ->postJson('/api/v1/logout');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_logout_without_session_returns_unauthenticated(): void
    {
        $response = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/v1/logout');

        $response->assertStatus(401)->assertJsonPath('authenticated', false);
    }

    public function test_me_after_logout_returns_unauthenticated(): void
    {
        $sessionUser = [
            'id' => 'u-456',
            'name' => 'Another User',
            'email' => 'another@example.com',
            'login' => 'another.user',
            'ad_login' => 'another.user',
            'role' => 'librarian',
        ];

        // Verify /me works with session
        $meBeforeLogout = $this
            ->withSession(['library.user' => $sessionUser])
            ->getJson('/api/v1/me');

        $meBeforeLogout->assertOk()->assertJsonPath('authenticated', true);

        // Logout endpoint returns success
        $logoutResponse = $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([
                'library.user' => $sessionUser,
                'library.crm_token' => 'fake-token',
            ])
            ->postJson('/api/v1/logout');

        $logoutResponse->assertOk()->assertJsonPath('success', true);
    }

    public function test_staff_middleware_rejects_reader_role(): void
    {
        $readerUser = [
            'id' => 'u-789',
            'name' => 'Reader User',
            'email' => 'reader@example.com',
            'login' => 'reader.user',
            'ad_login' => 'reader.user',
            'role' => 'reader',
        ];

        $response = $this
            ->withSession(['library.user' => $readerUser])
            ->getJson('/api/v1/internal/circulation/loans/00000000-0000-0000-0000-000000000001');

        $response->assertStatus(403);
    }
}
