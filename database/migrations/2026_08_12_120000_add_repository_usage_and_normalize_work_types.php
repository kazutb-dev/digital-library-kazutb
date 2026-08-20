<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('repository_items')
            ->where('work_type', 'abstract_of_thesis')
            ->update(['work_type' => 'thesis_abstract']);

        Schema::create('repository_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained('repository_items')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_name', 96)->default('guest');
            $table->string('locale', 8)->default('ru');
            $table->date('occurred_on');
            $table->timestampsTz();

            $table->index(['occurred_on', 'event_type'], 'repository_usage_date_type_idx');
            $table->index(['repository_item_id', 'occurred_on'], 'repository_usage_item_date_idx');
            $table->index(['role_name', 'occurred_on'], 'repository_usage_role_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_access_events');

        DB::table('repository_items')
            ->where('work_type', 'thesis_abstract')
            ->update(['work_type' => 'abstract_of_thesis']);
    }
};
