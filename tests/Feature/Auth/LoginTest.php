<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_user_can_login_with_email(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'crm-token-1',
                'user' => [
                    'id' => 'crm-user-1',
                    'name' => 'Test Reader',
                    'email' => 'user@example.com',
                    'login' => 'reader01',
                    'ad_login' => 'reader01',
                    'role' => 'reader',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'secret',
            'device_name' => 'web',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', 'crm-user-1')
            ->assertJsonPath('user.email', 'user@example.com')
            ->assertJsonPath('user.ad_login', 'reader01')
            ->assertJsonPath('user.role', 'reader');

        $this->assertTrue(session()->has('library.crm_token'));
        $this->assertTrue(session()->has('library.user'));
    }

    public function test_user_can_login_with_login_field(): void
    {
        Http::fake([
            '*' => Http::response([
                'token' => 'crm-token-2',
                'user' => [
                    'id' => 'crm-user-2',
                    'name' => 'Test Librarian',
                    'email' => 'librarian@example.com',
                    'login' => 'ad_login',
                    'ad_login' => 'ad_login',
                    'role' => 'librarian',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'ad_login',
            'password' => 'secret',
            'device_name' => 'web',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', 'crm-user-2')
            ->assertJsonPath('user.email', 'librarian@example.com')
            ->assertJsonPath('user.ad_login', 'ad_login')
            ->assertJsonPath('user.role', 'librarian');

        $this->assertTrue(session()->has('library.crm_token'));
        $this->assertTrue(session()->has('library.user'));
    }
}
