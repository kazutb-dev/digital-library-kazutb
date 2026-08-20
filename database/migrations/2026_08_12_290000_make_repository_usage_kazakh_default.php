<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repository_usage_daily')
            || ! Schema::hasColumn('repository_usage_daily', 'locale')) {
            return;
        }

        // Keep the already-deployed hardening migration immutable. This
        // additive correction aligns implicit analytics rows with Kazakh as
        // the platform-wide default locale; explicit ru/en values are kept.
        Schema::table('repository_usage_daily', function (Blueprint $table): void {
            $table->string('locale', 8)->default('kk')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repository_usage_daily')
            || ! Schema::hasColumn('repository_usage_daily', 'locale')) {
            return;
        }

        Schema::table('repository_usage_daily', function (Blueprint $table): void {
            $table->string('locale', 8)->default('ru')->change();
        });
    }
};
