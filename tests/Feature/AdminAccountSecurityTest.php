<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminAccountSecurityTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_password_reset_sets_flag_and_shows_temporary_password_once(): void
    {
        $target = $this->makeControlPlaneUser('member');

        $response = $this
            ->signInToLibraryAs($this->adminUser)
            ->post(route('admin.users.reset-password', $target), [
                'reason' => 'Suspected credential compromise',
            ]);

        $response->assertRedirect()->assertSessionHas('temporary_password');

        $temporary = session('temporary_password');
        $target->refresh();
        $this->assertTrue($target->must_change_password);
        $this->assertTrue(Hash::check($temporary, $target->password));

        ActivityLog::query()
            ->where('action_type', 'password.reset')
            ->where('entity_id', (string) $target->getKey())
            ->firstOrFail();
    }

    public function test_admin_cannot_reset_own_password_via_reset_action(): void
    {
        $this
            ->signInToLibraryAs($this->adminUser)
            ->post(route('admin.users.reset-password', $this->adminUser), [
                'reason' => 'Trying to reset my own password',
            ])
            ->assertSessionHasErrors('password');

        $this->assertFalse($this->adminUser->fresh()->must_change_password);
    }

    public function test_forced_password_change_gates_every_page_until_completed(): void
    {
        $target = $this->makeControlPlaneUser('admin', [
            'password' => Hash::make('TemporaryPass2026!'),
            'must_change_password' => true,
        ]);

        $this->signInToLibraryAs($target);

        $this->get('/admin')->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();

        $this->post(route('password.change.update'), [
            'current_password' => 'TemporaryPass2026!',
            'password' => 'MyOwnStrongPass2026!',
            'password_confirmation' => 'MyOwnStrongPass2026!',
        ])->assertRedirect('/admin');

        $target->refresh();
        $this->assertFalse($target->must_change_password);
        $this->assertTrue(Hash::check('MyOwnStrongPass2026!', $target->password));

        $this->get('/admin')->assertOk();
    }

    public function test_forced_change_rejects_wrong_temporary_password(): void
    {
        $target = $this->makeControlPlaneUser('admin', [
            'password' => Hash::make('TemporaryPass2026!'),
            'must_change_password' => true,
        ]);

        $this->signInToLibraryAs($target)
            ->post(route('password.change.update'), [
                'current_password' => 'wrong-temporary',
                'password' => 'MyOwnStrongPass2026!',
                'password_confirmation' => 'MyOwnStrongPass2026!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($target->fresh()->must_change_password);
    }

    public function test_revoke_sessions_deletes_sessions_and_tokens(): void
    {
        $target = $this->makeControlPlaneUser('member');
        DB::table('sessions')->insert([
            'id' => str()->random(40),
            'user_id' => $target->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);
        $target->createToken('test-token');

        $this
            ->signInToLibraryAs($this->adminUser)
            ->post(route('admin.users.revoke-sessions', $target), [
                'reason' => 'Session hijack suspicion',
            ])
            ->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->getKey())->count());
        $this->assertSame(0, $target->tokens()->count());
        $this->assertTrue($target->fresh()->is_active, 'Revoking sessions must not deactivate the account.');

        ActivityLog::query()
            ->where('action_type', 'session.revoke')
            ->where('entity_id', (string) $target->getKey())
            ->firstOrFail();
    }

    public function test_admin_cannot_revoke_own_sessions(): void
    {
        $this
            ->signInToLibraryAs($this->adminUser)
            ->post(route('admin.users.revoke-sessions', $this->adminUser), [
                'reason' => 'Trying to revoke my own sessions',
            ])
            ->assertSessionHasErrors('sessions');
    }

    public function test_profile_update_and_password_change(): void
    {
        $this->signInToLibraryAs($this->adminUser);

        $this->get(route('admin.profile.edit'))->assertOk();

        $this->patch(route('admin.profile.update'), [
            'name' => 'Renamed Admin',
            'email' => 'renamed.admin@example.test',
            'locale' => 'kk',
        ])->assertRedirect();

        $this->adminUser->refresh();
        $this->assertSame('Renamed Admin', $this->adminUser->name);
        $this->assertSame('kk', trim((string) $this->adminUser->locale));

        $this->patch(route('admin.profile.password'), [
            'current_password' => 'AcceptanceTest2026!',
            'password' => 'BrandNewSecret2026!',
            'password_confirmation' => 'BrandNewSecret2026!',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('BrandNewSecret2026!', $this->adminUser->fresh()->password));
    }

    public function test_profile_password_change_requires_current_password(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->patch(route('admin.profile.password'), [
                'current_password' => 'not-my-password',
                'password' => 'BrandNewSecret2026!',
                'password_confirmation' => 'BrandNewSecret2026!',
            ])
            ->assertSessionHasErrors('current_password');
    }
}
