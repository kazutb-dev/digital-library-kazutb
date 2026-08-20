<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_items', function (Blueprint $table): void {
            // ТЗ §15.6: new intake starts as full-public, but the responsible
            // employee must still confirm rights and may deliberately choose
            // a narrower policy before the director approves publication.
            $table->string('access_policy', 64)->default('full_public')->change();
        });
    }

    public function down(): void
    {
        Schema::table('repository_items', function (Blueprint $table): void {
            $table->string('access_policy', 64)->default('metadata_only')->change();
        });
    }
};
