<?php

namespace Tests\Feature;

use App\Directory\ActiveDirectoryClientInterface;
use App\Directory\ActiveDirectoryHealth;
use App\Directory\ActiveDirectoryUser;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ActiveDirectoryReaderProfileTest extends TestCase
{
    use BuildsAdminControlPlane;

    private ReaderProfileActiveDirectoryClient $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['active_directory.enabled' => true]);
        $this->directory = new ReaderProfileActiveDirectoryClient;
        $this->app->instance(ActiveDirectoryClientInterface::class, $this->directory);
    }

    public function test_ad_teacher_gets_reader_profile_and_actual_member_session_type(): void
    {
        $this->directory->identity = $this->identity(title: 'Senior Lecturer');

        $this->postJson('/api/login', [
            'login' => 'teacher01',
            'password' => 'DirectorySecret!',
        ])->assertOk()
            ->assertJsonPath('user.role', 'reader')
            ->assertJsonPath('user.canonical_role', 'member')
            ->assertJsonPath('user.profile_type', 'teacher')
            ->assertJsonPath('landing', '/dashboard');

        $user = User::query()->where('ad_object_guid', ReaderProfileActiveDirectoryClient::GUID)->firstOrFail();
        $this->assertTrue($user->hasExactRoles('member'));
        $this->assertSame('teacher', $user->readerProfile?->category);
        $this->assertNotNull($user->readerProfile?->ticket_number);
        $this->assertNotNull($user->readerProfile?->barcode);
    }

    public function test_ad_reader_category_does_not_replace_existing_staff_role(): void
    {
        $staff = $this->makeControlPlaneUser('librarian', [
            'email' => 'teacher01@example.test',
            'ad_login' => 'teacher01',
            'ad_object_guid' => ReaderProfileActiveDirectoryClient::GUID,
            'ad_samaccountname' => 'teacher01',
            'role' => 'librarian',
            'auth_source' => 'active_directory',
            'auth_provider' => 'ldap',
        ]);
        $this->directory->identity = $this->identity(title: 'Professor');

        $this->postJson('/api/login', [
            'login' => 'teacher01',
            'password' => 'DirectorySecret!',
        ])->assertOk()
            ->assertJsonPath('user.role', 'librarian')
            ->assertJsonPath('user.canonical_role', 'librarian')
            ->assertJsonPath('user.profile_type', 'staff')
            ->assertJsonPath('landing', '/librarian');

        $staff->refresh();
        $this->assertTrue($staff->hasExactRoles('librarian'));
        $this->assertSame('teacher', $staff->readerProfile?->category);
    }

    public function test_ad_sync_bridges_an_existing_legacy_staff_role_instead_of_downgrading_it(): void
    {
        $staff = $this->makeControlPlaneUser('cataloguer', [
            'email' => 'teacher01@example.test',
            'ad_login' => 'teacher01',
            'ad_object_guid' => ReaderProfileActiveDirectoryClient::GUID,
            'ad_samaccountname' => 'teacher01',
            'role' => 'cataloguer',
            'auth_source' => 'active_directory',
            'auth_provider' => 'ldap',
        ]);
        $staff->syncRoles([]);
        $this->directory->identity = $this->identity(title: 'Professor');

        $this->postJson('/api/login', [
            'login' => 'teacher01',
            'password' => 'DirectorySecret!',
        ])->assertOk()
            ->assertJsonPath('user.canonical_role', 'cataloguer')
            ->assertJsonPath('user.profile_type', 'staff')
            ->assertJsonPath('landing', '/librarian');

        $this->assertTrue($staff->refresh()->hasExactRoles('cataloguer'));
    }

    public function test_ad_identity_without_student_or_teacher_evidence_uses_canonical_staff_category(): void
    {
        $this->directory->identity = $this->identity();

        $this->postJson('/api/login', [
            'login' => 'teacher01',
            'password' => 'DirectorySecret!',
        ])->assertOk()
            ->assertJsonPath('user.canonical_role', 'member')
            ->assertJsonPath('user.profile_type', 'employee');

        $user = User::query()->where('ad_object_guid', ReaderProfileActiveDirectoryClient::GUID)->firstOrFail();
        $this->assertSame('staff', $user->readerProfile?->category);
    }

    public function test_unknown_member_profile_is_not_rendered_as_student(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'kk']);
        $response = $this->actingAs($member)->withSession([
            'library.user' => [
                'id' => (string) $member->getKey(),
                'local_id' => $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'login' => $member->ad_login,
                'role' => 'reader',
                'canonical_role' => 'member',
                'profile_type' => 'unmapped-directory-category',
                'locale' => 'kk',
            ],
            'library.authenticated_at' => now()->toIso8601String(),
            'locale' => 'kk',
        ])->get('/dashboard');

        $profileClass = 'text-[11px] uppercase tracking-widest text-on-surface-variant';
        $response->assertOk()
            ->assertSee('<span class="'.$profileClass.'">'.e(__('roles.names.member')).'</span>', false)
            ->assertDontSee('<span class="'.$profileClass.'">'.e(__('shell.member.profile_types.student')).'</span>', false);
    }

    /** @param list<string> $groups */
    private function identity(?string $department = null, ?string $title = null, array $groups = []): ActiveDirectoryUser
    {
        return new ActiveDirectoryUser(
            objectGuid: ReaderProfileActiveDirectoryClient::GUID,
            samAccountName: 'teacher01',
            distinguishedName: 'CN=Teacher One,OU=Users,DC=example,DC=test',
            enabled: true,
            userPrincipalName: 'teacher01@example.test',
            displayName: 'Teacher One',
            givenName: 'Teacher',
            surname: 'One',
            mail: 'teacher01@example.test',
            department: $department,
            title: $title,
            groups: $groups,
        );
    }
}

final class ReaderProfileActiveDirectoryClient implements ActiveDirectoryClientInterface
{
    public const GUID = '87654321-4321-4321-4321-cba987654321';

    public ?ActiveDirectoryUser $identity = null;

    public function healthCheck(): ActiveDirectoryHealth
    {
        return new ActiveDirectoryHealth(true, 1.0);
    }

    public function findByLogin(string $login): ?ActiveDirectoryUser
    {
        return $this->identity;
    }

    public function search(string $term, int $limit = 20): array
    {
        return $this->identity === null ? [] : [$this->identity];
    }

    public function verifyCredentials(string $distinguishedName, string $password): bool
    {
        return $password === 'DirectorySecret!';
    }
}
