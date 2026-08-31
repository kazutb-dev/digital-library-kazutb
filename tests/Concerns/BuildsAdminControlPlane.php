<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ExternalResourceSeeder;
use Database\Seeders\LibraryStructureSeeder;
use Database\Seeders\MessageCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\UdcCodeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

trait BuildsAdminControlPlane
{
    protected User $adminUser;

    protected function setUpAdminControlPlane(): void
    {
        config([
            'app.locale' => 'kk',
            'app.fallback_locale' => 'kk',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'permission.testing' => false,
            'session.driver' => 'array',
        ]);
        DB::purge();
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        foreach ($this->adminMigrationPaths() as $path) {
            $migration = require base_path($path);
            $migration->up();
        }

        foreach ([
            PermissionSeeder::class,
            RoleSeeder::class,
            DemoUserSeeder::class,
            SettingsSeeder::class,
            LibraryStructureSeeder::class,
            ExternalResourceSeeder::class,
            MessageCategorySeeder::class,
            UdcCodeSeeder::class,
        ] as $seeder) {
            app($seeder)->run();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->adminUser = $this->makeControlPlaneUser('admin');
    }

    protected function makeControlPlaneUser(string $role, array $attributes = []): User
    {
        $token = str()->lower(str()->random(10));
        $legacyRole = match ($role) {
            'admin' => 'admin',
            'librarian' => 'librarian',
            default => 'reader',
        };

        $user = User::query()->create(array_merge([
            'name' => ucfirst($role).' Test User',
            'email' => $role.'.'.$token.'@example.test',
            'ad_login' => $role.'_'.$token,
            'role' => $legacyRole,
            'password' => Hash::make('AcceptanceTest2026!'),
            'auth_provider' => 'demo',
            'role_source' => 'manual',
            'is_active' => true,
            'locale' => 'kk',
            'email_verified_at' => now(),
        ], $attributes));

        $user->syncRoles([$role]);

        return $user->refresh();
    }

    protected function signInToLibraryAs(User $user): static
    {
        $role = $user->effectiveRole();
        $legacyRole = match ($role) {
            'admin' => 'admin',
            'member' => 'reader',
            default => 'librarian',
        };

        $this->actingAs($user);
        $this->withSession([
            'library.user' => [
                'id' => (string) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'login' => $user->ad_login,
                'ad_login' => $user->ad_login,
                'role' => $legacyRole,
                'canonical_role' => $role,
            ],
            'library.crm_token' => 'test-control-plane-token',
            'library.authenticated_at' => now()->toIso8601String(),
            'locale' => in_array($user->locale, ['kk', 'ru', 'en'], true)
                ? $user->locale
                : (string) config('app.locale', 'kk'),
        ]);

        return $this;
    }

    /**
     * The project currently has unrelated legacy migrations that cannot be
     * executed against a blank database. Admin tests intentionally load only
     * the canonical dependencies and the control-plane migrations.
     *
     * @return list<string>
     */
    private function adminMigrationPaths(): array
    {
        return [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_03_26_105401_create_personal_access_tokens_table.php',
            'database/migrations/2026_03_26_110500_add_ad_login_and_role_to_users_table.php',
            'database/migrations/2026_04_06_160000_create_literature_drafts_tables.php',
            'database/migrations/2026_07_28_170726_create_permission_tables.php',
            'database/migrations/2026_07_28_170800_add_auth_provider_fields_to_users_table.php',
            'database/migrations/2026_07_28_170900_add_admin_fields_to_users_table.php',
            'database/migrations/2026_07_28_171000_create_activity_logs_table.php',
            'database/migrations/2026_07_28_171100_create_news_table.php',
            'database/migrations/2026_07_28_171200_create_contact_messages_table.php',
            'database/migrations/2026_07_28_171300_create_settings_table.php',
            'database/migrations/2026_07_28_171400_create_branches_and_funds_tables.php',
            'database/migrations/2026_07_28_171500_create_external_resources_table.php',
            'database/migrations/2026_07_28_171600_add_unique_external_identity_to_users_table.php',
            'database/migrations/2026_07_28_171700_normalize_integer_setting_values.php',
            'database/migrations/2026_07_29_090000_create_notification_settings_table.php',
            'database/migrations/2026_07_29_091000_add_must_change_password_to_users_table.php',
            'database/migrations/2026_07_29_120000_create_bibliographic_domain_tables.php',
            'database/migrations/2026_07_29_121000_create_copies_domain_tables.php',
            'database/migrations/2026_07_29_122000_create_readers_circulation_tables.php',
            'database/migrations/2026_07_29_123000_create_reservations_notifications_tables.php',
            'database/migrations/2026_07_29_124000_create_repository_and_electronic_materials_tables.php',
            'database/migrations/2026_07_29_140000_add_license_terms_to_electronic_materials.php',
            'database/migrations/2026_07_29_150000_extend_udc_codes_for_cataloguing.php',
            'database/migrations/2026_07_29_151000_link_funds_to_academic_directions.php',
            'database/migrations/2026_07_29_160000_add_quality_flags_to_catalog_and_readers.php',
            'database/migrations/2026_07_29_170000_add_review_category_to_bibliographic_records.php',
            'database/migrations/2026_07_30_120000_create_digital_reading_progress_table.php',
            'database/migrations/2026_07_30_130000_add_supplier_name_to_book_copies.php',
            'database/migrations/2026_07_30_140000_add_pending_transfer_branch_to_reservations.php',
            'database/migrations/2026_07_30_150000_add_barcode_to_reader_profiles.php',
            'database/migrations/2026_07_30_160000_create_library_visits_table.php',
            // ДИР §9.3: circulation now persists lost/damaged obligations.
            'database/migrations/2026_07_30_170000_create_circulation_incident_workflow.php',
            'database/migrations/2026_07_31_090000_create_data_quality_control_center.php',
            'database/migrations/2026_08_03_000000_create_production_circulation_workflows.php',
            'database/migrations/2026_08_04_000000_build_full_reader_cabinet.php',
            'database/migrations/2026_08_05_000000_expand_news_editorial_workflow.php',
            'database/migrations/2026_08_06_000000_build_message_appeals_workflow.php',
            // Unified digital services also completes the scholarly repository
            // and supplies the access-event tables used by the four canonical
            // library reports.
            'database/migrations/2026_08_07_000000_unify_digital_library_services.php',
            'database/migrations/2026_08_11_100000_make_scholarly_repository_public_by_default.php',
            'database/migrations/2026_08_12_140000_allow_incomplete_external_resources.php',
            'database/migrations/2026_08_12_220000_harden_external_resource_operations.php',
            'database/migrations/2026_08_13_010000_create_librarian_workspace_operations.php',
            'database/migrations/2026_08_13_020000_add_active_directory_identity_to_users.php',
            'database/migrations/2026_08_13_030000_create_integration_hub.php',
            'database/migrations/2026_08_13_040000_add_multilingual_catalogue_and_executive_controls.php',
            'database/migrations/2026_08_19_000000_extend_inventory_physical_verification.php',
            'database/migrations/2026_08_29_121000_extend_inventory_sessions_for_recovered_scopes.php',
        ];
    }
}
