<?php

namespace Tests\Feature;

use App\Services\AuthSessionManager;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class StaffProfileAndWorkspaceResolutionTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_workspace_resolution_prioritizes_staff_roles_over_a_retained_member_role(): void
    {
        $cataloguer = $this->makeControlPlaneUser('member');
        $cataloguer->syncRoles(['member', 'cataloguer']);
        $this->assertSame('cataloguer', $cataloguer->fresh()->effectiveRole());
        $this->assertSame('/librarian', app(AuthSessionManager::class)->landing($cataloguer->fresh()));

        $librarian = $this->makeControlPlaneUser('member');
        $librarian->syncRoles(['member', 'librarian']);
        $this->assertSame('/librarian', app(AuthSessionManager::class)->landing($librarian->fresh()));

        $member = $this->makeControlPlaneUser('member');
        $this->assertSame('/dashboard', app(AuthSessionManager::class)->landing($member));

        $admin = $this->makeControlPlaneUser('member');
        $admin->syncRoles(['member', 'admin']);
        $this->assertSame('/admin', app(AuthSessionManager::class)->landing($admin->fresh()));
    }

    public function test_staff_profile_is_current_user_only_and_contains_no_local_password_controls(): void
    {
        $staff = $this->makeControlPlaneUser('cataloguer', [
            'name' => 'Cataloguer Profile Test',
            'ad_login' => 'cataloguer_profile_test',
            'ad_samaccountname' => 'cataloguer_profile_test',
            'auth_source' => 'active_directory',
            'auth_provider' => 'ldap',
            'locale' => 'ru',
        ]);

        $response = $this->signInToLibraryAs($staff)->get('/librarian/profile?lang=ru');
        $response->assertOk()
            ->assertSee('data-staff-profile', false)
            ->assertSee('Cataloguer Profile Test')
            ->assertSee(__('brand.workspace.cataloguer', locale: 'ru'))
            ->assertSee('cataloguer_profile_test')
            ->assertDontSee('objectGUID')
            ->assertDontSee('LDAP')
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="email"', false);

        $this->signInToLibraryAs($staff)->get('/librarian/profile/'.$this->adminUser->getKey())->assertNotFound();
    }

    public function test_profile_access_and_role_labels_follow_the_staff_boundary_in_every_locale(): void
    {
        $this->get('/librarian/profile')->assertRedirect();
        $this->signInToLibraryAs($this->makeControlPlaneUser('member'))->get('/librarian/profile')->assertForbidden();

        foreach (['librarian', 'senior_librarian', 'cataloguer', 'director'] as $role) {
            foreach (['ru', 'kk', 'en'] as $locale) {
                $staff = $this->makeControlPlaneUser($role, ['locale' => $locale]);
                $response = $this->signInToLibraryAs($staff)->get('/librarian/profile?lang='.$locale);
                $response->assertOk()
                    ->assertSee(__('librarian.staff_profile.title', locale: $locale))
                    ->assertSee(__('brand.workspace.'.$role, locale: $locale))
                    ->assertDontSee('brand.workspace.'.$role)
                    ->assertDontSee('librarian.staff_profile.');
            }
        }
    }

    public function test_cataloguer_sidebar_and_routes_match_the_existing_role_contract(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer', ['locale' => 'ru']);
        $workspace = $this->signInToLibraryAs($cataloguer)->get('/librarian?lang=ru');

        $workspace->assertOk()
            ->assertSee(__('brand.workspace.cataloguer', locale: 'ru'))
            ->assertSee(route('librarian.catalog.index'), false)
            ->assertSee(route('librarian.copies.index'), false)
            ->assertSee(route('librarian.workspace.search'), false)
            ->assertSee(route('librarian.data-quality.index'), false)
            ->assertSee(route('librarian.profile.show'), false)
            ->assertDontSee(route('librarian.circulation'), false)
            ->assertDontSee(route('librarian.readers.index'), false)
            ->assertDontSee(route('librarian.visits.index'), false)
            ->assertDontSee(route('librarian.reservations.index'), false);

        foreach (['/librarian/catalog', '/librarian/copies', '/librarian/data-quality', '/librarian/profile'] as $path) {
            $this->signInToLibraryAs($cataloguer)->get($path)->assertOk();
        }
        $this->signInToLibraryAs($cataloguer)->get('/librarian/circulation')->assertForbidden();
        $this->signInToLibraryAs($cataloguer)->get('/admin')->assertForbidden();
        $this->signInToLibraryAs($cataloguer)->get('/librarian/executive/export/csv')->assertForbidden();
    }

    public function test_admin_can_return_to_the_admin_console_from_the_librarian_sidebar(): void
    {
        $admin = $this->makeControlPlaneUser('admin', ['locale' => 'ru']);

        $workspace = $this->signInToLibraryAs($admin)->get('/librarian?lang=ru');

        $workspace->assertOk()
            ->assertSee(route('admin.overview'), false)
            ->assertSee(__('admin.nav.admin_console', locale: 'ru'))
            ->assertDontSee(route('admin.profile.edit'), false);
    }
}
