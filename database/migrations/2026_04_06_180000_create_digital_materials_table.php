<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS app');

        Schema::create('app.digital_materials', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('document_id')->index();
            $table->string('title', 500);
            $table->string('file_type', 20); // pdf, epub, etc.
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 1000);
            $table->string('original_filename', 500);
            $table->bigInteger('file_size_bytes')->default(0);
            $table->string('access_level', 20)->default('authenticated');
            // access_level: 'authenticated' (logged-in users), 'campus' (IP-restricted), 'open' (anyone)
            $table->boolean('allow_download')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['document_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.digital_materials');
    }
};
