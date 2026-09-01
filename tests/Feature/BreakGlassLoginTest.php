<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class BreakGlassLoginTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
        // Deterministic: primary providers unavailable so only break-glass can succeed.
        config([
            'active_directory.enabled' => false,
            'services.external_auth.login_url' => '',
        ]);
    }

    private function makeBreakGlassReader(string $password = 'Str0ng-Break-Glass!'): User
    {
        Role::findOrCreate('member', 'web');
        $user = User::query()->create([
            'name' => 'Break-glass Reader',
            'email' => 'bg-reader@kazutb.local',
            'password' => Hash::make($password),
            'auth_provider' => 'demo',
            'auth_source' => 'local_break_glass',
            'role' => 'reader',
            'role_source' => 'manual',
            'is_active' => true,
            'email_verified_at' => now(),
            'locale' => 'ru',
        ]);
        $user->syncRoles(['member']);
        ReaderProfile::query()->create([
            'user_id' => $user->getKey(),
            'ticket_number' => ReaderProfile::nextTicketNumber(),
            'barcode' => ReaderProfile::nextBarcode(),
            'category' => 'student',
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_break_glass_login_is_inert_when_the_flag_is_off(): void
    {
        config(['auth.break_glass.enabled' => false]);
        $this->makeBreakGlassReader();

        $this->post('/login', ['email' => 'bg-reader@kazutb.local', 'password' => 'Str0ng-Break-Glass!'])
            ->assertRedirect();

        $this->assertNull(session('library.user'));
        $this->assertGuest();
    }

    public function test_break_glass_login_succeeds_when_enabled_with_the_right_password(): void
    {
        config(['auth.break_glass.enabled' => true]);
        $this->makeBreakGlassReader();

        $response = $this->post('/login', [
            'email' => 'bg-reader@kazutb.local',
            'password' => 'Str0ng-Break-Glass!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertSame('member', session('library.user')['canonical_role']);
        $this->assertAuthenticated();
    }

    public function test_break_glass_login_rejects_a_wrong_password(): void
    {
        config(['auth.break_glass.enabled' => true]);
        $this->makeBreakGlassReader();

        $this->post('/login', ['email' => 'bg-reader@kazutb.local', 'password' => 'nope'])
            ->assertSessionHasErrors('login');

        $this->assertNull(session('library.user'));
        $this->assertGuest();
    }

    public function test_a_normal_account_with_a_password_still_cannot_use_local_login(): void
    {
        config(['auth.break_glass.enabled' => true]);
        Role::findOrCreate('member', 'web');
        // Same password, but NOT flagged as break-glass — must not authenticate locally.
        $user = User::query()->create([
            'name' => 'Ordinary Reader',
            'email' => 'ordinary@kazutb.local',
            'password' => Hash::make('Str0ng-Break-Glass!'),
            'auth_provider' => 'demo',
            'auth_source' => 'local_demo',
            'role' => 'reader',
            'role_source' => 'manual',
            'is_active' => true,
            'email_verified_at' => now(),
            'locale' => 'ru',
        ]);
        $user->syncRoles(['member']);

        $this->post('/login', ['email' => 'ordinary@kazutb.local', 'password' => 'Str0ng-Break-Glass!'])
            ->assertRedirect();

        $this->assertNull(session('library.user'));
        $this->assertGuest();
    }

    public function test_provisioning_command_creates_a_working_break_glass_reader(): void
    {
        config(['auth.break_glass.enabled' => true]);

        $this->artisan('library:break-glass-reader', [
            'email' => 'cmd-bg@kazutb.local',
            '--password' => 'Cmd-Break-Glass-123',
            '--name' => 'Command Reader',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'cmd-bg@kazutb.local',
            'auth_source' => 'local_break_glass',
        ]);
        $this->assertDatabaseHas('reader_profiles', ['category' => 'student']);

        $this->post('/login', ['email' => 'cmd-bg@kazutb.local', 'password' => 'Cmd-Break-Glass-123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }
}
