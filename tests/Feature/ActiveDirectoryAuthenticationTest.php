<?php

namespace Tests\Feature;

use App\Directory\ActiveDirectoryClientInterface;
use App\Directory\ActiveDirectoryHealth;
use App\Directory\ActiveDirectoryUser;
use App\Directory\LdapActiveDirectoryClient;
use App\Exceptions\ActiveDirectoryException;
use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ActiveDirectoryAuthenticationTest extends TestCase
{
    use BuildsAdminControlPlane;

    private FakeActiveDirectoryClient $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['active_directory.enabled' => true]);
        $this->directory = new FakeActiveDirectoryClient;
        $this->app->instance(ActiveDirectoryClientInterface::class, $this->directory);
    }

    public function test_valid_ad_credentials_create_member_without_storing_password(): void
    {
        $response = $this->postJson('/api/login', ['login' => 'DOMAIN\\reader01', 'password' => 'DirectorySecret!']);
        $response->assertOk()->assertJsonPath('user.ad_login', 'reader01')->assertJsonPath('user.role', 'reader');
        $user = User::query()->where('ad_object_guid', FakeActiveDirectoryClient::GUID)->firstOrFail();
        $this->assertTrue($user->hasRole('member'));
        $this->assertSame('active_directory', $user->auth_source);
        $this->assertFalse(Hash::check('DirectorySecret!', $user->password));
        $this->assertDatabaseMissing('activity_logs', ['new_values' => '%DirectorySecret%']);
    }

    public function test_successful_login_rotates_the_anonymous_session_identifier(): void
    {
        $this->app['session']->start();
        $anonymousSessionId = $this->app['session']->getId();

        $this->postJson('/api/login', [
            'login' => 'reader01',
            'password' => 'DirectorySecret!',
        ])->assertOk();

        $this->assertNotSame($anonymousSessionId, $this->app['session']->getId());
    }

    public function test_second_login_links_by_guid_and_preserves_local_privileged_role_and_reader_profile(): void
    {
        $this->postJson('/api/login', ['login' => 'reader01', 'password' => 'DirectorySecret!'])->assertOk();
        $user = User::query()->where('ad_object_guid', FakeActiveDirectoryClient::GUID)->firstOrFail();
        $user->syncRoles(['librarian']);
        $user->readerProfile()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['ticket_number' => 'AD-READER-0001', 'barcode' => 'ADR00000001', 'category' => 'faculty', 'status' => 'blocked'],
        );
        $this->directory->identity = new ActiveDirectoryUser(FakeActiveDirectoryClient::GUID, 'reader01', 'CN=Reader One,OU=Users,DC=example,DC=test', true, 'reader01@example.test', 'Reader Renamed', mail: 'renamed@example.test');

        $this->postJson('/api/login', ['login' => 'reader01@example.test', 'password' => 'DirectorySecret!'])->assertOk();
        $this->assertSame(1, User::query()->where('ad_object_guid', FakeActiveDirectoryClient::GUID)->count());
        $user->refresh();
        $this->assertTrue($user->hasRole('librarian'));
        $this->assertSame('blocked', $user->readerProfile->status);
        $this->assertSame('AD-READER-0001', $user->readerProfile->ticket_number);
    }

    public function test_invalid_unknown_and_disabled_accounts_use_generic_failure(): void
    {
        $this->directory->passwordValid = false;
        $invalid = $this->postJson('/api/login', ['login' => 'reader01', 'password' => 'wrong'])->assertUnauthorized();
        $this->directory->identity = null;
        $unknown = $this->postJson('/api/login', ['login' => 'unknown', 'password' => 'wrong'])->assertUnauthorized();
        $this->directory->identity = new ActiveDirectoryUser(FakeActiveDirectoryClient::GUID, 'reader01', 'CN=Reader One,OU=Users,DC=example,DC=test', false);
        $disabled = $this->postJson('/api/login', ['login' => 'reader01', 'password' => 'wrong'])->assertUnauthorized();

        $this->assertSame($invalid->json('message'), $unknown->json('message'));
        $this->assertSame($invalid->json('message'), $disabled->json('message'));
    }

    public function test_local_demo_password_cannot_bypass_active_directory(): void
    {
        config(['demo_users.enabled' => true]);
        User::query()->create([
            'name' => 'Legacy demo account',
            'email' => 'legacy-demo@example.test',
            'ad_login' => 'legacy_demo',
            'password' => Hash::make('PublishedDemoPassword!'),
            'auth_provider' => 'demo',
            'role' => 'librarian',
            'role_source' => 'manual',
            'is_active' => true,
        ])->syncRoles(['librarian']);
        $this->directory->identity = null;

        $this->postJson('/api/login', [
            'login' => 'legacy_demo',
            'password' => 'PublishedDemoPassword!',
        ])->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_directory_outage_is_sanitized_and_ldap_injection_is_rejected_before_client(): void
    {
        $this->directory->throwUnavailable = true;
        $this->postJson('/api/login', ['login' => 'reader01', 'password' => 'secret'])->assertStatus(503)->assertJsonMissing(['host', 'dn', 'port']);
        $this->directory->throwUnavailable = false;
        $calls = $this->directory->findCalls;
        $this->postJson('/api/login', ['login' => '*)(|(objectClass=*)', 'password' => 'secret'])->assertUnauthorized();
        $this->assertSame($calls, $this->directory->findCalls);
    }

    public function test_all_quick_login_routes_are_absent_but_faculty_reader_category_remains(): void
    {
        $this->assertNull(config('demo_users.identities.teacher'));
        $this->assertNull(config('demo_auth.identities.teacher'));
        $this->assertContains('faculty', ReaderProfile::CATEGORIES);
        $this->get('/login')->assertDontSee('data-demo-slug="teacher"', false);
        $this->post('/login/demo/teacher')->assertStatus(405);
        $this->post('/login/demo/director')->assertStatus(405);
        $this->getJson('/api/demo-auth/identities')->assertNotFound();
    }

    public function test_admin_health_surface_never_displays_bind_credentials(): void
    {
        config(['active_directory.bind_dn' => 'CN=Service Secret,DC=example,DC=test', 'active_directory.bind_password' => 'NeverRenderThisSecret']);
        $this->signInToLibraryAs($this->adminUser)->get('/admin/integrations')
            ->assertOk()->assertSee('Active Directory')->assertDontSee('NeverRenderThisSecret')->assertDontSee('CN=Service Secret');
    }

    public function test_required_directory_ca_path_fails_closed_when_certificate_is_missing(): void
    {
        config([
            'active_directory.host' => 'directory.example.test',
            'active_directory.require_cert' => true,
            'active_directory.ca_cert_path' => '/missing/kazutb-directory-ca.crt',
        ]);

        $health = (new LdapActiveDirectoryClient)->healthCheck();

        $this->assertFalse($health->connected);
        $this->assertSame('configuration_invalid', $health->errorCategory);
    }

    public function test_plaintext_or_unverified_directory_configuration_fails_closed(): void
    {
        config([
            'active_directory.host' => 'directory.example.test',
            'active_directory.use_ssl' => false,
            'active_directory.require_cert' => true,
            'active_directory.ca_cert_path' => '',
        ]);

        $plaintext = (new LdapActiveDirectoryClient)->healthCheck();
        $this->assertFalse($plaintext->connected);
        $this->assertSame('configuration_invalid', $plaintext->errorCategory);

        config([
            'active_directory.use_ssl' => true,
            'active_directory.require_cert' => false,
        ]);

        $unverified = (new LdapActiveDirectoryClient)->healthCheck();
        $this->assertFalse($unverified->connected);
        $this->assertSame('configuration_invalid', $unverified->errorCategory);
    }
}

final class FakeActiveDirectoryClient implements ActiveDirectoryClientInterface
{
    public const GUID = '12345678-1234-1234-1234-123456789abc';

    public ?ActiveDirectoryUser $identity;

    public bool $passwordValid = true;

    public bool $throwUnavailable = false;

    public int $findCalls = 0;

    public function __construct()
    {
        $this->identity = new ActiveDirectoryUser(self::GUID, 'reader01', 'CN=Reader One,OU=Users,DC=example,DC=test', true, 'reader01@example.test', 'Reader One', 'Reader', 'One', 'reader01@example.test', department: 'Faculty');
    }

    public function healthCheck(): ActiveDirectoryHealth
    {
        return new ActiveDirectoryHealth(! $this->throwUnavailable, 2.5, $this->throwUnavailable ? 'connection_failed' : null);
    }

    public function findByLogin(string $login): ?ActiveDirectoryUser
    {
        $this->findCalls++;
        if ($this->throwUnavailable) {
            throw new ActiveDirectoryException('connection_failed');
        }

        return $this->identity;
    }

    public function search(string $term, int $limit = 20): array
    {
        if ($this->throwUnavailable) {
            throw new ActiveDirectoryException('connection_failed');
        }

        return $this->identity ? [$this->identity] : [];
    }

    public function verifyCredentials(string $distinguishedName, string $password): bool
    {
        return $this->passwordValid;
    }
}
