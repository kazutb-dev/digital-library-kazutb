<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BuildsAcquisitionOperations
{
    protected function setUpAcquisitionOperations(): void
    {
        config([
            'app.locale' => 'ru',
            'app.fallback_locale' => 'ru',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'permission.testing' => false,
            'session.driver' => 'array',
        ]);
        DB::purge();
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_03_26_110500_add_ad_login_and_role_to_users_table.php',
            'database/migrations/2026_07_28_170726_create_permission_tables.php',
            'database/migrations/2026_07_28_170800_add_auth_provider_fields_to_users_table.php',
            'database/migrations/2026_07_28_170900_add_admin_fields_to_users_table.php',
            'database/migrations/2026_07_28_171000_create_activity_logs_table.php',
            'database/migrations/2026_07_28_171300_create_settings_table.php',
            'database/migrations/2026_07_28_171400_create_branches_and_funds_tables.php',
            'database/migrations/2026_07_29_120000_create_bibliographic_domain_tables.php',
            'database/migrations/2026_07_29_121000_create_copies_domain_tables.php',
            'database/migrations/2026_07_30_130000_add_supplier_name_to_book_copies.php',
            'database/migrations/2026_08_13_010000_create_librarian_workspace_operations.php',
            'database/migrations/2026_08_28_100000_create_marc_recovery_model.php',
            'database/migrations/2026_08_28_100100_extend_catalogue_for_marc_recovery.php',
        ] as $path) {
            (require base_path($path))->up();
        }

        // These columns normally arrive with the physical-inventory migration,
        // whose unrelated session dependencies are intentionally not loaded in
        // this focused in-memory schema.
        Schema::table('book_copies', function (Blueprint $table): void {
            $table->string('room')->nullable();
            $table->string('section')->nullable();
        });
        Schema::create('reader_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('read_at')->nullable();
        });

        (require base_path('database/migrations/2026_08_29_120000_create_acquisition_batches_and_safe_number_sequences.php'))->up();
    }
}
