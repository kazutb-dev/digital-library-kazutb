<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderProfile;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MemberListPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard/list');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_member_can_view_localized_shortlist(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        ReaderProfile::forUser($member);

        $legacyResponse = $this->signInToLibraryAs($member)->get('/dashboard/list?lang=ru');
        $legacyResponse->assertRedirect('/dashboard/collections');

        $response = $this->get('/dashboard/collections?lang=ru');

        $response->assertOk();
        $response->assertSee('Мои подборки', false);
        $response->assertSee('Личные подборки', false);
        $response->assertSee('Создайте первую подборку.', false);
        $response->assertDontSee('My collections', false);
    }

    public function test_librarian_is_redirected_to_staff_workspace(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('librarian'))
            ->get('/dashboard/list')
            ->assertRedirect('/librarian');
    }

    public function test_admin_is_redirected_to_admin_workspace(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/dashboard/list')
            ->assertRedirect('/admin');
    }
}
