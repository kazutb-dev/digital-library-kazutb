<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the single `needs_manual_review` boolean into typed queues.
 *
 * `needs_manual_review` stays the "is it still open" flag; `review_category`
 * says *what kind* of judgement is required, because the three kinds are worked
 * in completely different UIs — bulk retagging, one-at-a-time retyping, or a
 * duplicate merge. It also survives resolution: a record settled as a parallel
 * edition keeps the category as a permanent annotation while its flag clears,
 * so the same record is not re-raised by the next detection pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->string('review_category', 32)->nullable()->after('needs_manual_review');
            $table->index(['review_category'], 'bibliographic_records_review_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->dropIndex('bibliographic_records_review_category_index');
            $table->dropColumn('review_category');
        });
    }
};
