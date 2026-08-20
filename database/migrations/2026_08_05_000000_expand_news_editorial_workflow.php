<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_kk');
            $table->string('name_ru')->nullable();
            $table->string('name_en')->nullable();
            $table->string('icon', 64)->nullable();
            $table->string('color_token', 32)->default('teal');
            $table->json('allowed_types')->nullable();
            $table->string('default_visibility', 32)->default('public');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('annual_content_plans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('title');
            $table->string('status', 32)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->string('type', 32)->default('update')->index();
            $table->foreignId('category_id')->nullable()->constrained('news_categories')->restrictOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('scheduled_publish_at')->nullable()->index();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('homepage_priority')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->string('visibility', 32)->default('public');
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('audience')->nullable();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Almaty');
            $table->string('venue')->nullable();
            $table->string('online_url', 2048)->nullable();
            $table->string('registration_url', 2048)->nullable();
            $table->boolean('registration_required')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('cover_image_alt')->nullable();
            $table->boolean('gallery_enabled')->default(false);
            $table->timestampTz('homepage_until')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('importance', 32)->default('normal');
            $table->string('source')->nullable();
            $table->string('organizer')->nullable();
            $table->string('title_kk')->nullable();
            $table->string('title_ru')->nullable();
            $table->string('title_en')->nullable();
            $table->text('excerpt_kk')->nullable();
            $table->text('excerpt_ru')->nullable();
            $table->text('excerpt_en')->nullable();
            $table->longText('content_kk')->nullable();
            $table->longText('content_ru')->nullable();
            $table->longText('content_en')->nullable();
            $table->string('slug_kk')->nullable()->unique();
            $table->string('slug_ru')->nullable()->unique();
            $table->string('slug_en')->nullable()->unique();
            $table->string('image_alt_kk')->nullable();
            $table->string('image_alt_ru')->nullable();
            $table->string('image_alt_en')->nullable();
            $table->string('venue_kk')->nullable();
            $table->string('venue_ru')->nullable();
            $table->string('venue_en')->nullable();
            $table->string('seo_title_kk')->nullable();
            $table->string('seo_title_ru')->nullable();
            $table->string('seo_title_en')->nullable();
            $table->text('seo_description_kk')->nullable();
            $table->text('seo_description_ru')->nullable();
            $table->text('seo_description_en')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('homepage_click_count')->default(0);
            $table->unsignedBigInteger('registration_click_count')->default(0);
        });

        Schema::create('annual_content_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('annual_content_plans')->cascadeOnDelete();
            $table->unsignedInteger('item_number');
            $table->string('type', 32);
            $table->string('title_kk');
            $table->string('title_ru')->nullable();
            $table->string('title_en')->nullable();
            $table->date('planned_date');
            $table->string('faculty')->nullable();
            $table->string('department')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience')->nullable();
            $table->text('expected_result')->nullable();
            $table->string('status', 32)->default('planned');
            $table->foreignId('publication_id')->nullable()->unique()->constrained('news')->nullOnDelete();
            $table->date('actual_date')->nullable();
            $table->text('completion_report')->nullable();
            $table->json('result_files')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['plan_id', 'item_number']);
        });

        Schema::table('news', function (Blueprint $table): void {
            $table->foreignId('annual_plan_item_id')->nullable()->unique()->constrained('annual_content_plan_items')->nullOnDelete();
        });

        Schema::create('news_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('reason')->nullable();
            $table->timestampsTz();
            $table->unique(['news_id', 'version']);
        });

        Schema::create('news_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->text('comment')->nullable();
            $table->json('issues')->nullable();
            $table->timestampsTz();
        });

        Schema::create('news_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('old_slug');
            $table->timestampsTz();
            $table->unique(['locale', 'old_slug']);
        });

        Schema::create('news_bibliographic_record', function (Blueprint $table): void {
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->foreignId('bibliographic_record_id')->constrained('bibliographic_records')->cascadeOnDelete();
            $table->primary(['news_id', 'bibliographic_record_id']);
        });

        Schema::create('news_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->date('viewed_on');
            $table->char('locale', 2);
            $table->string('visitor_hash', 64)->nullable();
            $table->unsignedInteger('views')->default(1);
            $table->timestampsTz();
            $table->unique(['news_id', 'viewed_on', 'locale', 'visitor_hash']);
        });

        DB::table('news')->orderBy('id')->get()->each(function (object $row): void {
            $locale = in_array($row->language, ['kk', 'ru', 'en'], true) ? $row->language : 'kk';
            DB::table('news')->where('id', $row->id)->update([
                'public_id' => (string) Str::uuid(),
                'type' => in_array($row->category, ['event', 'announcement', 'update', 'schedule'], true) ? $row->category : 'update',
                'title_'.$locale => $row->title,
                'excerpt_'.$locale => $row->excerpt,
                'content_'.$locale => $row->body,
                'slug_'.$locale => $row->slug,
                'published_at' => $row->status === 'published' ? ($row->publish_at ?? $row->updated_at) : null,
                'scheduled_publish_at' => $row->status === 'scheduled' ? $row->publish_at : null,
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE news DROP CONSTRAINT IF EXISTS news_status_check');
            DB::statement("ALTER TABLE news ADD CONSTRAINT news_status_check CHECK (status IN ('draft','pending_review','changes_requested','approved','scheduled','published','cancelled','archived'))");
        }

        if (Schema::hasTable('notification_settings')) {
            $now = now('UTC');
            foreach (['news_pending_review', 'news_changes_requested', 'news_approved', 'news_scheduled', 'news_archived', 'news_cancelled', 'news_emergency'] as $eventType) {
                DB::table('notification_settings')->insertOrIgnore(['event_type' => $eventType, 'in_app_enabled' => true, 'email_enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_views');
        Schema::dropIfExists('news_bibliographic_record');
        Schema::dropIfExists('news_slug_redirects');
        Schema::dropIfExists('news_reviews');
        Schema::dropIfExists('news_revisions');
        Schema::table('news', fn (Blueprint $table) => $table->dropConstrainedForeignId('annual_plan_item_id'));
        Schema::dropIfExists('annual_content_plan_items');
        Schema::table('news', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            foreach (['editor_id', 'reviewer_id', 'approved_by'] as $foreign) {
                $table->dropConstrainedForeignId($foreign);
            }
            $table->dropColumn(['public_id', 'type', 'approved_at', 'published_at', 'scheduled_publish_at', 'archived_at', 'cancelled_at', 'cancellation_reason', 'homepage_priority', 'is_featured', 'is_pinned', 'visibility', 'branch_id', 'audience', 'starts_at', 'ends_at', 'timezone', 'venue', 'online_url', 'registration_url', 'registration_required', 'capacity', 'contact_name', 'contact_email', 'contact_phone', 'cover_image_alt', 'gallery_enabled', 'homepage_until', 'expires_at', 'importance', 'source', 'organizer', 'title_kk', 'title_ru', 'title_en', 'excerpt_kk', 'excerpt_ru', 'excerpt_en', 'content_kk', 'content_ru', 'content_en', 'slug_kk', 'slug_ru', 'slug_en', 'image_alt_kk', 'image_alt_ru', 'image_alt_en', 'venue_kk', 'venue_ru', 'venue_en', 'seo_title_kk', 'seo_title_ru', 'seo_title_en', 'seo_description_kk', 'seo_description_ru', 'seo_description_en', 'view_count', 'homepage_click_count', 'registration_click_count']);
        });
        Schema::dropIfExists('annual_content_plans');
        Schema::dropIfExists('news_categories');
    }
};
