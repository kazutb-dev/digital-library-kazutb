<?php

namespace Tests\Feature;

use Database\Seeders\MessageCategorySeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MemberMessagesPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        (require base_path('database/migrations/2026_08_06_000000_build_message_appeals_workflow.php'))->up();
        app(MessageCategorySeeder::class)->run();
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    private function loginAs(string $identitySlug): void
    {
        $role = in_array($identitySlug, ['student', 'teacher'], true) ? 'member' : $identitySlug;
        $this->signInToLibraryAs($this->makeControlPlaneUser($role));
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
        $response->assertSee('Входящие обращения', false);
        $response->assertSee('Новое обращение', false);
        $response->assertSee('Запрос', false);
        $response->assertSee('Жалоба', false);
        $response->assertSee('Предложение', false);
        $response->assertSee('Вопрос', false);
    }

    public function test_teacher_can_view_messages(): void
    {
        $this->loginAs('teacher');

        $response = $this->get('/dashboard/messages');

        $response->assertOk();
        $response->assertSee('Новое обращение', false);
    }

    public function test_librarian_is_forbidden(): void
    {
        $this->loginAs('librarian');

        $response = $this->get('/dashboard/messages');

        $response->assertRedirect();
    }

    public function test_admin_is_forbidden(): void
    {
        $this->loginAs('admin');

        $response = $this->get('/dashboard/messages');

        $response->assertRedirect();
    }
}
