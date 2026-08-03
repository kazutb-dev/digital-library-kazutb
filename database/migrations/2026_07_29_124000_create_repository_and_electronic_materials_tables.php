<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scientific repository (Master.md §20) and electronic materials attached to
 * bibliographic records (§18, §15.7). Files live on the local disk under a
 * non-public path; access always goes through a permission-checked route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_items', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 1000);
            $table->json('authors');
            $table->string('work_type', 32);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('department')->nullable();
            $table->string('udc_code', 64)->nullable();
            $table->text('abstract')->nullable();
            $table->json('keywords')->nullable();
            $table->string('language', 8)->default('ru');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'work_type']);
            $table->index('year');
        });

        Schema::create('electronic_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bibliographic_record_id')->constrained('bibliographic_records')->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('file_path')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->string('file_type', 32)->default('pdf');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('access_level', 32)->default('authenticated');
            $table->boolean('allow_download')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['bibliographic_record_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_materials');
        Schema::dropIfExists('repository_items');
    }
};
