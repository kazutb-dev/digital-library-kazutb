<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electronic_materials', function (Blueprint $table): void {
            $table->text('license_terms')->nullable()->after('access_level');
        });
    }

    public function down(): void
    {
        Schema::table('electronic_materials', function (Blueprint $table): void {
            $table->dropColumn('license_terms');
        });
    }
};
