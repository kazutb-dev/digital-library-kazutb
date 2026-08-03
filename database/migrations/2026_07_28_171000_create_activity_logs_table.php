<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->timestampTz('occurred_at');
            $table->string('action_type', 64);
            $table->string('entity_type', 191);
            $table->string('entity_id', 191);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('reason')->nullable();
            $table->string('scope', 32)->default('administrative');
            $table->json('metadata')->nullable();

            $table->index('occurred_at', 'activity_logs_time_idx');
            $table->index(
                ['actor_id', 'occurred_at'],
                'activity_logs_actor_time_idx'
            );
            $table->index(
                ['action_type', 'occurred_at'],
                'activity_logs_action_time_idx'
            );
            $table->index(
                ['entity_type', 'entity_id', 'occurred_at'],
                'activity_logs_entity_time_idx'
            );
            $table->index(
                ['scope', 'occurred_at'],
                'activity_logs_scope_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
