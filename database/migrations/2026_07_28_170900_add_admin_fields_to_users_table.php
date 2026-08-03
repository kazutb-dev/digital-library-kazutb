<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('last_login_at')->nullable()->index();
            $table->char('locale', 2)->nullable()->default('kk');
            $table->string('department')->nullable()->index();
            $table->timestampTz('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE users ADD CONSTRAINT users_locale_check CHECK (locale IN ('ru', 'kk', 'en'))"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_locale_check'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_login_at']);
            $table->dropIndex(['department']);
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn([
                'is_active',
                'last_login_at',
                'locale',
                'department',
                'deactivated_at',
            ]);
        });
    }
};
