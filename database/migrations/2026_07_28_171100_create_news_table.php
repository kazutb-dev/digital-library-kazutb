<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category', 32);
            $table->text('body');
            $table->text('excerpt')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestampTz('publish_at')->nullable();
            $table->boolean('show_on_homepage')->default(false);
            $table->char('language', 2)->default('ru');
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(
                ['status', 'publish_at'],
                'news_status_publish_at_idx'
            );
            $table->index(
                ['category', 'language'],
                'news_category_language_idx'
            );
            $table->index(
                ['show_on_homepage', 'status'],
                'news_homepage_status_idx'
            );
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE news ADD CONSTRAINT news_status_check CHECK (status IN ('draft', 'scheduled', 'published', 'archived'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE news ADD CONSTRAINT news_language_check CHECK (language IN ('ru', 'kk', 'en'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
