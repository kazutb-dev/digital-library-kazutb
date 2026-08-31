<?php

namespace Tests\Feature\Api;

use App\Services\AuditLogger;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminPrivilegeNegativePathTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guests_are_redirected_from_every_control_plane_section(): void
    {
        foreach ($this->protectedPages() as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_member_is_forbidden_from_every_control_plane_section(): void
    {
        $member = $this->makeControlPlaneUser('member');

        foreach ($this->protectedPages() as $uri) {
            $this->signInToLibraryAs($member)->get($uri)->assertForbidden();
        }
    }

    public function test_librarian_is_forbidden_from_the_administrative_control_plane(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');

        foreach ($this->protectedPages() as $uri) {
            $this->signInToLibraryAs($librarian)->get($uri)->assertForbidden();
        }

        // These read-only operational tools are deliberately delegated and
        // do not grant general access to the administrative control plane.
        $this->signInToLibraryAs($librarian)
            ->get('/admin/external-resources')
            ->assertOk()
            ->assertDontSee('href="'.route('admin.overview').'"', false);
        $this->signInToLibraryAs($librarian)
            ->get('/admin/integrations')
            ->assertOk()
            ->assertDontSee('href="'.route('admin.overview').'"', false);
    }

    public function test_custom_role_can_enter_only_its_delegated_control_plane_scope(): void
    {
        $audit = app(AuditLogger::class);
        $audit->logRequired(
            actionType: 'security.secret',
            entityType: 'security_probe',
            entityId: 'hidden-security-event',
            scope: 'security',
            actor: $this->adminUser,
        );
        $audit->logRequired(
            actionType: 'operational.visible',
            entityType: 'operational_probe',
            entityId: 'visible-operational-event',
            scope: 'operational',
            actor: $this->adminUser,
        );

        $role = Role::query()->create([
            'name' => 'audit-reviewer',
            'guard_name' => 'web',
        ]);
        $role->syncPermissions(['system.logs']);
        $reviewer = $this->makeControlPlaneUser('member');
        $reviewer->syncRoles([$role]);

        $this->signInToLibraryAs($reviewer)
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('admin.audit.title'), false)
            ->assertDontSee(__('admin.nav.users'), false)
            ->assertSee('visible-operational-event', false)
            ->assertDontSee('hidden-security-event', false);

        $this->signInToLibraryAs($reviewer)
            ->get('/admin/logs')
            ->assertOk()
            ->assertSee('visible-operational-event', false)
            ->assertDontSee('hidden-security-event', false)
            ->assertDontSee('security.secret', false);
        $this->signInToLibraryAs($reviewer)->get('/admin/users')->assertForbidden();
        $this->signInToLibraryAs($reviewer)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_can_access_every_control_plane_section(): void
    {
        foreach ($this->protectedPages() as $uri) {
            $this->signInToLibraryAs($this->adminUser)->get($uri)->assertOk();
        }
    }

    public function test_deactivated_staff_session_is_rejected_by_internal_api(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian);
        $librarian->update(['is_active' => false]);

        $this->getJson('/api/v1/internal/circulation/loans/1')
            ->assertForbidden()
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    /**
     * @return list<string>
     */
    private function protectedPages(): array
    {
        return [
            '/admin',
            '/admin/users',
            '/admin/roles',
            '/admin/logs',
            '/admin/news',
            '/admin/feedback',
            '/admin/reports',
            '/admin/settings',
            '/admin/branches',
        ];
    }
}
