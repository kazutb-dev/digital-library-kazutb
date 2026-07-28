<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class MemberLogoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo_auth.enabled', true);
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    private function loginAs(string $identitySlug): void
    {
        $identity = config("demo_auth.identities.{$identitySlug}");

        $this->get('/login');
        $this->post('/login', [
            '_token' => csrf_token(),
            'login' => $identity['login'],
            'password' => $identity['password'],
            'device_name' => 'phpunit',
        ]);
    }

    public function test_authenticated_member_post_logout_redirects_to_login(): void
    {
        $this->loginAs('student');

        $this->assertIsArray(session('library.user'));

        $response = $this->post('/logout', ['_token' => csrf_token()]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');

        $this->assertNull(session('library.user'));
        $this->assertNull(session('library.crm_token'));
        $this->assertNull(session('library.authenticated_at'));
        $this->assertNull(session('library.demo_identity'));
    }

    public function test_logout_form_rendered_in_member_dashboard(): void
    {
        $this->loginAs('student');

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('action="/logout"', false);
        $response->assertSee('Sign out', false);
    }

    public function test_after_logout_dashboard_requires_login_again(): void
    {
        $this->loginAs('student');
        $this->post('/logout', ['_token' => csrf_token()]);

        $response = $this->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }
}
