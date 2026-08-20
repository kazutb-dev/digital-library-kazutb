<?php

namespace Tests\Feature;

use App\Directory\ActiveDirectoryClientInterface;
use App\Directory\ActiveDirectoryUser;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Mockery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class PostLoginRedirectTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['active_directory.enabled' => true]);
    }

    public function test_admin_ad_login_redirects_to_admin_overview(): void
    {
        $this->assertAdRoleRedirectsTo('admin', '/admin');
    }

    public function test_librarian_ad_login_redirects_to_librarian_overview(): void
    {
        $this->assertAdRoleRedirectsTo('librarian', '/librarian');
    }

    public function test_member_ad_login_redirects_to_reader_dashboard(): void
    {
        $this->assertAdRoleRedirectsTo('member', '/dashboard');
    }

    public function test_login_redirect_accepts_local_paths_and_rejects_external_destinations(): void
    {
        $guid = fake()->uuid();
        $login = 'redirect_directory_user';
        $user = $this->makeControlPlaneUser('member', [
            'ad_login' => $login,
            'ad_samaccountname' => $login,
            'ad_object_guid' => $guid,
            'auth_source' => 'active_directory',
            'auth_provider' => 'ldap',
        ]);

        $directory = Mockery::mock(ActiveDirectoryClientInterface::class);
        $directory->shouldReceive('findByLogin')->times(3)->with($login)->andReturn(new ActiveDirectoryUser(
            $guid,
            $login,
            'CN='.$login.',OU=Users,DC=example,DC=test',
            true,
            $login.'@example.test',
            $user->name,
            mail: $user->email,
        ));
        $directory->shouldReceive('verifyCredentials')->times(3)->andReturnTrue();
        $this->app->instance(ActiveDirectoryClientInterface::class, $directory);

        foreach (['https://evil.example', '//evil.example'] as $redirect) {
            $this->post('/login', [
                'login' => $login,
                'password' => 'In-memory-directory-test-secret',
                'redirect' => $redirect,
            ])->assertRedirect('/dashboard');
        }

        $this->post('/login', [
            'login' => $login,
            'password' => 'In-memory-directory-test-secret',
            'redirect' => '/librarian/catalog',
        ])->assertRedirect('/librarian/catalog');
    }

    private function assertAdRoleRedirectsTo(string $role, string $landing): void
    {
        $guid = fake()->uuid();
        $login = $role.'_directory_user';
        $user = $this->makeControlPlaneUser($role, [
            'ad_login' => $login,
            'ad_samaccountname' => $login,
            'ad_object_guid' => $guid,
            'auth_source' => 'active_directory',
            'auth_provider' => 'ldap',
        ]);

        $directory = Mockery::mock(ActiveDirectoryClientInterface::class);
        $directory->shouldReceive('findByLogin')->once()->with($login)->andReturn(new ActiveDirectoryUser(
            $guid,
            $login,
            'CN='.$login.',OU=Users,DC=example,DC=test',
            true,
            $login.'@example.test',
            $user->name,
            mail: $user->email,
        ));
        $directory->shouldReceive('verifyCredentials')->once()->andReturnTrue();
        $this->app->instance(ActiveDirectoryClientInterface::class, $directory);

        $this->post('/login', [
            'login' => $login,
            'password' => 'In-memory-directory-test-secret',
            'device_name' => 'phpunit',
        ])->assertRedirect($landing);
    }
}
