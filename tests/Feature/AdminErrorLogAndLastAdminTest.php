<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminErrorLogAndLastAdminTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_error_log_page_renders_for_admin_and_is_permission_gated(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/error-log')
            ->assertOk()
            ->assertSee('Журнал ошибок');

        $editor = $this->makeControlPlaneUser('member');
        $editor->givePermissionTo('news.edit_any');
        $this->signInToLibraryAs($editor)->get('/admin/error-log')->assertForbidden();
    }

    public function test_error_log_level_filter_is_validated(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/error-log?level=error')
            ->assertOk();

        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/error-log?level=nonsense')
            ->assertSessionHasErrors('level');
    }

    /**
     * The guard itself: with two active admins, demoting one succeeds;
     * demoting the final active admin fails. Under PostgreSQL the update
     * path first locks all active-admin rows (lockForUpdate) so two
     * concurrent demotions serialize and the second re-reads a world where
     * only one active admin remains — the same state this sequential test
     * asserts on.
     */
    public function test_admin_role_can_be_removed_only_while_another_active_admin_remains(): void
    {
        $demoAdmin = $this->makeControlPlaneUser('admin');

        $payload = fn (User $user): array => [
            'name' => $user->name,
            'email' => $user->email,
            'ad_login' => $user->ad_login,
            'department' => $user->department,
            'auth_provider' => $user->auth_provider,
            'external_id' => $user->external_id,
            'role' => 'member',
            'locale' => 'ru',
            'is_active' => '1',
        ];

        // Two active admins: demoting the first one is allowed.
        $this->signInToLibraryAs($this->adminUser)
            ->patch(route('admin.users.update', $demoAdmin), $payload($demoAdmin))
            ->assertRedirect(route('admin.users.show', $demoAdmin));
        $this->assertFalse($demoAdmin->fresh()->hasRole('admin'));

        // Now only $this->adminUser remains — removing their role must fail.
        $this->signInToLibraryAs($this->adminUser)
            ->patch(route('admin.users.update', $this->adminUser), $payload($this->adminUser))
            ->assertSessionHasErrors('role');
        $this->assertTrue($this->adminUser->fresh()->hasRole('admin'));

        // Deactivating the last active admin must fail the same way.
        $this->signInToLibraryAs($this->adminUser)
            ->patch(route('admin.users.active', $this->adminUser), [
                'reason' => 'Trying to deactivate the last admin',
            ])
            ->assertSessionHasErrors('role');
        $this->assertTrue((bool) $this->adminUser->fresh()->is_active);
    }
}
