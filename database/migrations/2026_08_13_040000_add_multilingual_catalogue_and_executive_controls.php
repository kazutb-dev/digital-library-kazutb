<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bibliographic_record_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bibliographic_record_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('title', 1000);
            $table->text('annotation')->nullable();
            $table->json('keywords')->nullable();
            $table->string('translation_status', 24)->default('draft');
            $table->string('source', 32)->default('manual_translation');
            $table->foreignId('translated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['bibliographic_record_id', 'locale'], 'catalog_translation_record_locale_unique');
            $table->index(['locale', 'translation_status'], 'catalog_translation_public_lookup_idx');
        });

        Schema::create('executive_alert_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->string('alert_key', 120);
            $table->string('scope_hash', 64);
            $table->foreignId('acknowledged_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('acknowledged_at');
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->unique(['alert_key', 'scope_hash', 'acknowledged_by'], 'executive_alert_ack_unique');
            $table->index(['scope_hash', 'acknowledged_at'], 'executive_alert_scope_time_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bibliographic_record_translations ADD CONSTRAINT catalog_translation_locale_check CHECK (locale IN ('kk','ru','en'))");
            DB::statement("ALTER TABLE bibliographic_record_translations ADD CONSTRAINT catalog_translation_status_check CHECK (translation_status IN ('draft','reviewed','approved','needs_review'))");
            DB::statement("ALTER TABLE bibliographic_record_translations ADD CONSTRAINT catalog_translation_source_check CHECK (source IN ('original','manual_translation','imported','legacy'))");
            DB::statement("ALTER TABLE bibliographic_record_translations ADD CONSTRAINT catalog_translation_title_check CHECK (btrim(title) <> '')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_alert_acknowledgements');
        Schema::dropIfExists('bibliographic_record_translations');
    }
};
