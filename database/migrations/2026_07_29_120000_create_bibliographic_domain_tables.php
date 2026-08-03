<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical bibliographic core (Master.md §6-§7, §10). Built fresh in the
 * public schema: the legacy app.documents / app.book_copies catalog was never
 * provisioned in this database and its read services fall back to demo data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('udc_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description');
            $table->string('description_kk')->nullable();
            $table->string('description_en')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('udc_codes')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('bibliographic_records', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 1000);
            $table->string('subtitle', 1000)->nullable();
            $table->string('primary_author')->nullable();
            $table->json('additional_authors')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 8)->default('ru');
            $table->string('udc_code', 64)->nullable();
            $table->string('author_mark', 16)->nullable();
            $table->string('category', 128)->nullable();
            $table->text('annotation')->nullable();
            $table->json('keywords')->nullable();
            $table->string('isbn', 32)->nullable();
            $table->string('resource_type', 32)->default('book');
            $table->string('cover_path')->nullable();
            $table->text('notes')->nullable();
            // Draft = saved with required fields missing; surfaces in the
            // Data Cleanup queue instead of blocking the librarian (§11.3).
            $table->boolean('is_draft')->default(false);
            $table->foreignId('responsible_librarian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('udc_code');
            $table->index('resource_type');
            $table->index('publication_year');
            $table->index('isbn');
            $table->index('is_draft');
        });

        Schema::create('bibliographic_record_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_id')->constrained('bibliographic_records')->cascadeOnDelete();
            $table->foreignId('related_record_id')->constrained('bibliographic_records')->cascadeOnDelete();
            $table->unique(['record_id', 'related_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bibliographic_record_relations');
        Schema::dropIfExists('bibliographic_records');
        Schema::dropIfExists('udc_codes');
    }
};
