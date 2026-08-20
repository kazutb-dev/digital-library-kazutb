<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LibrarianOverviewPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/librarian')->assertRedirectContains('/login');
    }

    public function test_librarian_can_view_current_operational_overview(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian', ['locale' => 'ru']);

        $this->signInToLibraryAs($librarian)
            ->get('/librarian?lang=ru')
            ->assertOk()
            ->assertSee(__('librarian.overview.eyebrow'), false)
            ->assertSee(__('librarian.overview.title'), false)
            ->assertSee(__('librarian.overview.subtitle'), false)
            ->assertDontSee('Morning Briefing', false)
            ->assertDontSee('Operational Status', false);
    }

    public function test_admin_can_open_overview_when_the_existing_permission_contract_allows_it(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/librarian?lang=ru')
            ->assertOk()
            ->assertSee(__('librarian.overview.title'), false);
    }

    public function test_member_is_forbidden(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('member'))
            ->get('/librarian')
            ->assertForbidden();
    }

    public function test_sidebar_renders_canonical_overview_link(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian', ['locale' => 'ru']);

        $this->signInToLibraryAs($librarian)
            ->get('/librarian?lang=ru')
            ->assertOk()
            ->assertSee(__('brand.workspace.librarian'), false)
            ->assertSee(route('librarian.overview'), false);
    }
}
