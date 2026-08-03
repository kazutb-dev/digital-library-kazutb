<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a reader left off inside a digital material, so the controlled viewer
 * can reopen at the right page instead of restarting at page 1.
 *
 * `material_ref` is a prefixed key rather than a foreign key because materials
 * come from two tables: the canonical `electronic_materials` ("em:<id>") and the
 * legacy uuid-keyed `app.digital_materials` ("dm:<uuid>"). A real FK could only
 * point at one of them, and the legacy table lives in a different schema that is
 * absent outside PostgreSQL.
 *
 * `user_id` is a string to match the session identity used by the rest of the
 * reader surface (see ShortlistStorageService::getUserId) — reader ids are not
 * always `users.id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_reading_progress', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 255);
            $table->string('material_ref', 64);
            $table->unsignedInteger('page')->default(1);
            $table->unsignedInteger('total_pages')->nullable();
            $table->string('zoom', 16)->nullable();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampsTz();

            // One row per reader per material: progress is overwritten, not
            // appended, so the viewer can upsert on every page turn.
            $table->unique(['user_id', 'material_ref']);
            $table->index(['user_id', 'last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_reading_progress');
    }
};
