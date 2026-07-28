<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class MemberHistoryPageTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard/history');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_student_can_view_history(): void
    {
        $this->loginAs('student');

        $response = $this->get('/dashboard/history');

        $response->assertOk();
        $response->assertSee('Borrowing history', false);
        $response->assertSee('Currently on loan', false);
        $response->assertSee('Returned', false);
    }

    public function test_teacher_can_view_history(): void
    {
        $this->loginAs('teacher');

        $response = $this->get('/dashboard/history');

        $response->assertOk();
        $response->assertSee('Borrowing history', false);
    }

    public function test_librarian_is_forbidden(): void
    {
        $this->loginAs('librarian');

        $response = $this->get('/dashboard/history');

        $response->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $this->loginAs('admin');

        $response = $this->get('/dashboard/history');

        $response->assertForbidden();
    }
}
