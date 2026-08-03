<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_resources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('resource_type', 32);
            $table->text('description');
            $table->string('logo_path')->nullable();
            $table->json('available_roles');
            $table->date('license_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('access_instructions')->nullable();
            $table->string('url', 2048);
            $table->string('provider')->nullable();
            $table->string('access_type', 32)->nullable();
            $table->string('category', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(
                ['is_active', 'resource_type'],
                'external_resources_active_type_idx'
            );
            $table->index(
                ['license_expires_at', 'is_active'],
                'external_resources_expiry_active_idx'
            );
            $table->index(
                ['is_active', 'sort_order'],
                'external_resources_active_sort_idx'
            );
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE external_resources ADD CONSTRAINT external_resources_type_check CHECK (resource_type IN ('licensed', 'open', 'partner', 'internal'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('external_resources');
    }
};
