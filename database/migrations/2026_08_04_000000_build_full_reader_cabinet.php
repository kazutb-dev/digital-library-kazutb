<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public $withinTransaction = true;

    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table): void {
            $table->string('phone', 40)->nullable();
            $table->string('additional_email')->nullable();
            $table->string('faculty')->nullable();
            $table->string('department')->nullable();
            $table->string('study_group')->nullable();
            $table->foreignId('preferred_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('valid_until')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->json('accessibility_preferences')->nullable();
        });

        Schema::table('literature_drafts', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('collection_type', 40)->default('favourites');
            $table->string('visibility', 24)->default('private');
            $table->string('status', 24)->default('draft');
            $table->string('owner_type', 24)->default('reader');
            $table->string('target_audience')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('subject')->nullable();
            $table->string('udc', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->index(['user_id', 'collection_type']);
            $table->index(['visibility', 'status', 'collection_type']);
        });

        foreach (DB::table('literature_drafts')->orderBy('id')->get(['id', 'user_id', 'title']) as $draft) {
            DB::table('literature_drafts')->where('id', $draft->id)->update([
                'slug' => 'favourites-'.$draft->id.'-'.Str::lower(Str::random(8)),
                'title' => $draft->title ?: 'Избранное',
                'collection_type' => 'favourites',
                'visibility' => 'private',
                'status' => 'published',
            ]);
        }

        Schema::table('literature_draft_items', function (Blueprint $table): void {
            $table->foreignId('bibliographic_record_id')->nullable()->constrained('bibliographic_records')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('inclusion_reason')->nullable();
            $table->index(['draft_id', 'sort_order']);
        });

        Schema::create('literature_collection_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained('literature_drafts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();
            $table->unique(['collection_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('literature_collection_follows');
        Schema::table('literature_draft_items', function (Blueprint $table): void {
            $table->dropIndex(['draft_id', 'sort_order']);
            $table->dropConstrainedForeignId('bibliographic_record_id');
            $table->dropColumn(['sort_order', 'inclusion_reason']);
        });
        Schema::table('literature_drafts', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'collection_type']);
            $table->dropIndex(['visibility', 'status', 'collection_type']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'cover_path', 'collection_type', 'visibility', 'status', 'owner_type', 'target_audience', 'language', 'subject', 'udc', 'published_at']);
            $table->unique('user_id');
        });
        Schema::table('reader_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preferred_branch_id');
            $table->dropColumn(['phone', 'additional_email', 'faculty', 'department', 'study_group', 'valid_until', 'notification_preferences', 'accessibility_preferences']);
        });
    }
};
