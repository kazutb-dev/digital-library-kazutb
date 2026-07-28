<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ad_login')) {
                $table->string('ad_login')->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable()->after('ad_login');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }

            if (Schema::hasColumn('users', 'ad_login')) {
                $table->dropUnique(['ad_login']);
                $table->dropColumn('ad_login');
            }
        });
    }
};
