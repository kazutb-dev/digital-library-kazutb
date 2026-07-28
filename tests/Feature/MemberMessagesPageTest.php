<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class MemberMessagesPageTest extends TestCase
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
        $response = $this->get('/dashboard/messages');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_student_can_view_messages(): void
    {
        $this->loginAs('student');

        $response = $this->get('/dashboard/messages');

        $response->assertOk();
        $response->assertSee('Messages', false);
        $response->assertSee('Draft a new message', false);
        $response->assertSee('Correspondence ledger', false);
        $response->assertSee('Request', false);
        $response->assertSee('Complaint', false);
        $response->assertSee('Improvement', false);
        $response->assertSee('Question', false);
    }

    public function test_teacher_can_view_messages(): void
    {
        $this->loginAs('teacher');

        $response = $this->get('/dashboard/messages');

        $response->assertOk();
        $response->assertSee('Draft a new message', false);
    }

    public function test_librarian_is_forbidden(): void
    {
        $this->loginAs('librarian');

        $response = $this->get('/dashboard/messages');

        $response->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $this->loginAs('admin');

        $response = $this->get('/dashboard/messages');

        $response->assertForbidden();
    }
}
