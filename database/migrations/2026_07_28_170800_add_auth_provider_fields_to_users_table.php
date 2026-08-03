<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds provenance fields to `users` so the RBAC layer can distinguish how an
 * account was created and how its roles were assigned.
 *
 * `auth_provider` — how the account authenticates today: `demo` for seeded
 * demo identities, `ldap` for accounts that will be provisioned from Active
 * Directory. `external_id` carries the AD object identifier once that login
 * path exists. `role_source` records whether the current role assignment was
 * made by hand or derived from an LDAP group mapping, so a future sync job can
 * safely overwrite `ldap_mapped` rows while leaving `manual` ones alone.
 *
 * Stored as plain strings with CHECK constraints rather than native enums:
 * Postgres enum types require a separate ALTER TYPE migration to extend, and
 * more auth providers are expected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'auth_provider')) {
                $table->string('auth_provider', 32)->default('demo')->after('role');
            }

            if (! Schema::hasColumn('users', 'external_id')) {
                $table->string('external_id')->nullable()->after('auth_provider');
            }

            if (! Schema::hasColumn('users', 'role_source')) {
                $table->string('role_source', 32)->default('manual')->after('external_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('auth_provider');
            $table->index('external_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE users ADD CONSTRAINT users_auth_provider_check CHECK (auth_provider IN ('demo', 'ldap'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_source_check CHECK (role_source IN ('manual', 'ldap_mapped'))"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_auth_provider_check');
            Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_source_check');
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'auth_provider')) {
                $table->dropIndex(['auth_provider']);
            }

            if (Schema::hasColumn('users', 'external_id')) {
                $table->dropIndex(['external_id']);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['auth_provider', 'external_id', 'role_source'],
                static fn (string $column): bool => Schema::hasColumn('users', $column)
            )));
        });
    }
};
