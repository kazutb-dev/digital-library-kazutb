<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ДИР §6.3 "пометка записи как проблемной" and §6.4 critical reader fields.
 *
 * `is_draft` is derived automatically from REQUIRED_FOR_COMPLETE, so a
 * librarian who spots a typo in an otherwise complete record has no way to
 * queue it for review. `needs_manual_review` is that manual lever, kept
 * separate so recomputing is_draft on save never clears a human's judgement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->boolean('needs_manual_review')->default(false)->after('is_draft');
            $table->string('review_note', 500)->nullable()->after('needs_manual_review');
        });

        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->index(['needs_manual_review'], 'bibliographic_records_manual_review_index');
        });

        // §6.4 / Master.md §11.4 — reader identity fields. Nullable because no
        // reader data has been imported yet; these exist so manual registration
        // and a future MARC-READERS import have somewhere to write.
        Schema::table('reader_profiles', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->after('category');
            $table->date('registered_at')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->dropIndex('bibliographic_records_manual_review_index');
            $table->dropColumn(['needs_manual_review', 'review_note']);
        });

        Schema::table('reader_profiles', function (Blueprint $table): void {
            $table->dropColumn(['birth_date', 'registered_at']);
        });
    }
};
