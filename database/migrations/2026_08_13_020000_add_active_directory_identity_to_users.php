<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('ad_object_guid')->nullable()->unique();
            $table->string('ad_samaccountname')->nullable()->unique();
            $table->string('ad_user_principal_name')->nullable()->unique();
            $table->text('ad_distinguished_name')->nullable();
            $table->timestampTz('ad_last_synced_at')->nullable();
            $table->timestampTz('ad_last_login_at')->nullable();
            $table->string('auth_source', 32)->default('local_demo')->index();
            $table->string('given_name')->nullable();
            $table->string('surname')->nullable();
            $table->string('telephone_number', 64)->nullable();
            $table->string('job_title')->nullable();
            $table->string('employee_id')->nullable()->index();
        });

        DB::table('users')->where('auth_provider', 'ldap')->update(['auth_source' => 'active_directory']);
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_auth_source_check CHECK (auth_source IN ('active_directory','local_demo','local_break_glass'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_auth_source_check');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['ad_object_guid', 'ad_samaccountname', 'ad_user_principal_name', 'ad_distinguished_name', 'ad_last_synced_at', 'ad_last_login_at', 'auth_source', 'given_name', 'surname', 'telephone_number', 'job_title', 'employee_id']);
        });
    }
};
