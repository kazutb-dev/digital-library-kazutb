<?php

namespace Tests\Feature;

use App\Directory\ActiveDirectoryClientInterface;
use App\Directory\ActiveDirectoryHealth;
use App\Directory\ActiveDirectoryUser;
use App\Services\Admin\VerifiedBackupService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Mockery\MockInterface;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminDirectoryAndSystemControlPlaneTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_admin_can_check_ad_and_search_only_safe_attributes(): void
    {
        config(['active_directory.enabled' => true, 'active_directory.host' => 'configured.invalid', 'active_directory.base_dn' => 'DC=example,DC=test']);
        $this->mock(ActiveDirectoryClientInterface::class, function (MockInterface $mock): void {
            $identity = new ActiveDirectoryUser('12345678-1234-1234-1234-123456789abc', 'reader01', 'CN=Reader,DC=example,DC=test', true, 'reader@example.test', 'Reader One', department: 'Faculty', title: 'Researcher');
            $mock->shouldReceive('healthCheck')->once()->andReturn(new ActiveDirectoryHealth(true, 4.2));
            $mock->shouldReceive('search')->once()->with('reader', 20)->andReturn([$identity]);
        });

        $this->signInToLibraryAs($this->adminUser)
            ->post('/admin/integrations/check', ['integration' => 'active_directory'])
            ->assertRedirect();
        $this->get('/admin/integrations?directory_q=reader')
            ->assertOk()->assertSee('reader01')->assertSee('Reader One')->assertDontSee('distinguishedName');
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'integration.ad.health_checked']);
    }

    public function test_system_panel_is_admin_only_and_restore_requires_typed_confirmation(): void
    {
        $this->mock(VerifiedBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('backups')->once()->andReturn([]);
            $mock->shouldNotReceive('restoreToTest');
        });
        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/system')
            ->assertOk()
            ->assertSee('Тексерілген сақтық көшірмелер', false)
            ->assertSee('SHA-256', false)
            ->assertDontSee('pg_dump custom format', false);
        $this->post('/admin/system/backups/sample.dump/restore-test', ['confirmation' => 'WRONG'])->assertSessionHasErrors('confirmation');

        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member)->get('/admin/system')->assertForbidden();
    }

    public function test_librarian_reader_directory_is_permission_gated(): void
    {
        config(['active_directory.enabled' => false]);
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)->get('/librarian/readers')->assertOk()->assertSee('data-reader-directory', false);
        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member)->get('/librarian/readers')->assertForbidden();
    }
}
