<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderProfile;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MemberDashboardPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirectContains('/login');
    }

    public function test_member_can_view_the_current_reader_dashboard(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        ReaderProfile::forUser($member);

        $this->signInToLibraryAs($member)
            ->get('/dashboard?lang=ru')
            ->assertOk()
            ->assertSee(__('librarian.member.common.eyebrow'), false)
            ->assertSee(__('librarian.member.dashboard.subtitle'), false)
            ->assertSee(__('librarian.member.dashboard.ticket_card'), false)
            ->assertDontSee('Member dashboard', false)
            ->assertDontSee('Research nodes', false);
    }

    public function test_a_second_member_uses_the_same_reader_workspace_contract(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'kk']);
        ReaderProfile::forUser($member);

        $this->signInToLibraryAs($member)
            ->get('/dashboard?lang=kk')
            ->assertOk()
            ->assertSee(__('librarian.member.dashboard.services.catalog'), false);
    }

    public function test_staff_and_admin_do_not_use_the_reader_dashboard_as_their_workspace(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('librarian'))
            ->get('/dashboard')
            ->assertRedirect('/librarian');

        $this->signInToLibraryAs($this->adminUser)
            ->get('/dashboard')
            ->assertRedirect('/admin');
    }

    public function test_sidebar_links_to_canonical_member_routes(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        ReaderProfile::forUser($member);

        $this->signInToLibraryAs($member)
            ->get('/dashboard?lang=ru')
            ->assertOk()
            ->assertSee(route('member.dashboard'), false)
            ->assertSee(route('member.reservations'), false)
            ->assertSee(route('member.collections.index'), false);
    }
}
