<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AuthSessionMeTest extends TestCase
{
    public function test_me_returns_401_when_session_is_missing(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response
            ->assertStatus(401)
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_me_returns_user_context_from_session(): void
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
            ->withSession(['library.user' => $sessionUser])
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('user.id', 'u-123')
            ->assertJsonPath('user.name', 'Test User')
            ->assertJsonPath('user.ad_login', 'test.user')
            ->assertJsonPath('user.role', 'reader');
    }
}
