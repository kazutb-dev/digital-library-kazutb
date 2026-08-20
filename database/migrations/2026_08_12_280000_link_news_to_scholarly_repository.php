<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news') || Schema::hasColumn('news', 'repository_item_id')) {
            return;
        }

        Schema::table('news', function (Blueprint $table): void {
            // News and repository records remain distinct governed entities;
            // this optional link only lets an editorial announcement point to
            // the already reviewed scholarly record.
            $table->foreignId('repository_item_id')->nullable()
                ->after('annual_plan_item_id')
                ->constrained('repository_items')
                ->nullOnDelete();
            $table->index(['repository_item_id', 'status'], 'news_repository_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('news') || ! Schema::hasColumn('news', 'repository_item_id')) {
            return;
        }

        Schema::table('news', function (Blueprint $table): void {
            $table->dropIndex('news_repository_status_idx');
            $table->dropConstrainedForeignId('repository_item_id');
        });
    }
};
