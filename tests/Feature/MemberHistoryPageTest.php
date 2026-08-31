<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderProfile;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MemberHistoryPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard/history')->assertRedirectContains('/login');
    }

    public function test_member_can_view_localized_borrowing_history(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        ReaderProfile::forUser($member);

        $this->signInToLibraryAs($member)->get('/dashboard/history?lang=ru')->assertOk()
            ->assertSee('История выдач', false)
            ->assertSee('Фильтры истории операций', false)
            ->assertSee('Закрытых выдач пока нет.', false)
            ->assertDontSee('Borrowing history', false);
    }

    public function test_staff_and_admin_cannot_use_reader_history(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('librarian'))
            ->get('/dashboard/history')->assertRedirect('/librarian');

        $this->signInToLibraryAs($this->adminUser)
            ->get('/dashboard/history')->assertRedirect('/admin');
    }
}
