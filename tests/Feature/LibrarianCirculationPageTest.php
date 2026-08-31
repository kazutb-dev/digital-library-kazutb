<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LibrarianCirculationPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->adminUser->forceFill(['locale' => 'ru'])->save();
        $this->librarian = $this->makeControlPlaneUser('librarian', ['locale' => 'ru']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/librarian/circulation');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_librarian_can_view_circulation(): void
    {
        $this->signInToLibraryAs($this->librarian);

        $response = $this->get('/librarian/circulation');

        $response->assertOk();
        $response->assertSee('Обслуживание читателей', false);
        $response->assertSee('Выдача материала', false);
        $response->assertSee('Возврат материала', false);
        $response->assertSee('Просроченные выдачи', false);
    }

    public function test_admin_can_view_circulation(): void
    {
        $this->signInToLibraryAs($this->adminUser);

        $response = $this->get('/librarian/circulation');

        $response->assertOk();
        $response->assertSee('Обслуживание читателей', false);
    }

    public function test_student_is_forbidden(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('member', ['locale' => 'ru']));

        $response = $this->get('/librarian/circulation');

        $response->assertForbidden();
    }

    public function test_teacher_is_forbidden(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('member', ['locale' => 'ru']));

        $response = $this->get('/librarian/circulation');

        $response->assertForbidden();
    }
}
