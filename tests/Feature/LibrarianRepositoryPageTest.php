<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class LibrarianRepositoryPageTest extends TestCase
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
        $response = $this->get('/librarian/repository');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_librarian_can_view_repository(): void
    {
        $this->loginAs('librarian');

        $response = $this->get('/librarian/repository');

        $response->assertOk();
        $response->assertSee('Moderation Queue', false);
        $response->assertSee('Under Review', false);
        $response->assertSee('Pending Initial', false);
    }

    public function test_admin_can_view_repository(): void
    {
        $this->loginAs('admin');

        $response = $this->get('/librarian/repository');

        $response->assertOk();
        $response->assertSee('Moderation Queue', false);
    }

    public function test_student_is_forbidden(): void
    {
        $this->loginAs('student');

        $response = $this->get('/librarian/repository');

        $response->assertForbidden();
    }

    public function test_teacher_is_forbidden(): void
    {
        $this->loginAs('teacher');

        $response = $this->get('/librarian/repository');

        $response->assertForbidden();
    }
}
