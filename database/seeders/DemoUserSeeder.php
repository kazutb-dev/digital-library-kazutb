<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the configured demo accounts backing the quick-login cards on /login.
 *
 * Idempotent: re-running updates the existing rows rather than duplicating
 * them, and re-syncs roles so a changed matrix takes effect on the next seed.
 * Skipped entirely when APP_DEMO_LOGIN_ENABLED is off, so seeding a real
 * deployment never plants accounts with published passwords.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('demo_users.enabled')) {
            $this->command?->warn('APP_DEMO_LOGIN_ENABLED is off — demo users not seeded.');

            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $password = (string) config('demo_users.password');

        foreach (config('demo_users.identities', []) as $slug => $identity) {
            $user = User::firstOrNew(['email' => $identity['email']]);
            $isNew = ! $user->exists;
            $user->fill([
                'name' => $identity['name'],
                'password' => Hash::make($password),
                'ad_login' => $identity['ad_login'],
                'role' => $identity['legacy_role'],
                'auth_provider' => 'demo',
                'external_id' => null,
                'role_source' => 'manual',
                'email_verified_at' => now(),
            ]);
            if ($isNew) {
                $user->locale = 'ru';
            }
            $user->save();

            $user->syncRoles([$identity['role']]);

            $this->command?->info(sprintf(
                'Demo user %-10s %-28s role=%s',
                $slug,
                $identity['email'],
                $identity['role']
            ));
        }
    }
}
