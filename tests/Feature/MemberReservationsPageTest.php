<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MemberReservationsPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $member;
    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->adminUser->forceFill(['locale' => 'ru'])->save();
        $this->member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $this->librarian = $this->makeControlPlaneUser('librarian', ['locale' => 'ru']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard/reservations');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_student_can_view_reservations(): void
    {
        $this->signInToLibraryAs($this->member);

        $response = $this->get('/dashboard/reservations');

        $response->assertOk();
        $response->assertSee('Мои бронирования', false);
        $response->assertSee('Активные бронирования', false);
        $response->assertSee('Завершённые бронирования', false);
    }

    public function test_teacher_can_view_reservations(): void
    {
        $this->signInToLibraryAs($this->member);

        $response = $this->get('/dashboard/reservations');

        $response->assertOk();
        $response->assertSee('Мои бронирования', false);
    }

    public function test_librarian_is_redirected_to_staff_workspace(): void
    {
        $this->signInToLibraryAs($this->librarian);

        $response = $this->get('/dashboard/reservations');

        $response->assertRedirect('/librarian');
    }

    public function test_admin_is_redirected_to_admin_workspace(): void
    {
        $this->signInToLibraryAs($this->adminUser);

        $response = $this->get('/dashboard/reservations');

        $response->assertRedirect('/admin');
    }
}
