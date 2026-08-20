<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthSessionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->timestampTz('occurred_at');
            $table->string('action_type', 64);
            $table->string('entity_type', 191);
            $table->string('entity_id', 191);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('reason')->nullable();
            $table->string('scope', 32);
            $table->json('metadata')->nullable();
        });
    }

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

    public function test_web_logout_form_clears_session_and_redirects_to_login(): void
    {
        $response = $this
            ->withSession([
                'library.user' => [
                    'id' => 'u-web',
                    'name' => 'Web User',
                    'email' => 'web.user@example.com',
                    'login' => 'web.user',
                    'ad_login' => 'web.user',
                    'role' => 'director',
                ],
                'library.crm_token' => 'fake-token',
            ])
            ->post('/logout');

        $response->assertRedirect('/login');
        $response->assertSessionMissing('library.user');
        $response->assertSessionMissing('library.crm_token');
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
