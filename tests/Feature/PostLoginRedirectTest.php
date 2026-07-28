<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class PostLoginRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Demo quick-login must be enabled so the POST /login handler
        // routes through the demo-auth branch without hitting the CRM.
        config()->set('demo_auth.enabled', true);

        // POST /login is a web-session route with CSRF protection; skip it
        // in tests so we can focus on the role-based redirect behaviour.
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    private function postLogin(string $login, string $password)
    {
        // Prime the session so a CSRF token exists, then submit the form with it.
        $this->get('/login');

        return $this->post('/login', [
            '_token' => csrf_token(),
            'login' => $login,
            'password' => $password,
            'device_name' => 'phpunit',
        ]);
    }

    public function test_admin_login_redirects_to_admin_overview(): void
    {
        $identity = config('demo_auth.identities.admin');

        $this->postLogin($identity['login'], $identity['password'])
            ->assertRedirect('/admin');
    }

    public function test_librarian_login_redirects_to_librarian_overview(): void
    {
        $identity = config('demo_auth.identities.librarian');

        $this->postLogin($identity['login'], $identity['password'])
            ->assertRedirect('/librarian');
    }

    public function test_student_login_redirects_to_dashboard(): void
    {
        $identity = config('demo_auth.identities.student');

        $this->postLogin($identity['login'], $identity['password'])
            ->assertRedirect('/dashboard');
    }

    public function test_teacher_login_redirects_to_dashboard(): void
    {
        $identity = config('demo_auth.identities.teacher');

        $this->postLogin($identity['login'], $identity['password'])
            ->assertRedirect('/dashboard');
    }
}
