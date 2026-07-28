<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS app');

        Schema::create('app.circulation_audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('event_at')->index();
            $table->string('action', 64);
            $table->string('entity_type', 32);
            $table->uuid('entity_id');
            $table->uuid('reader_id')->nullable()->index();
            $table->uuid('actor_user_id')->nullable()->index();
            $table->string('actor_type', 32);
            $table->string('request_id', 128)->nullable()->index();
            $table->string('correlation_id', 128)->nullable()->index();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['entity_type', 'entity_id', 'event_at'], 'idx_circ_audit_entity_event_at');
            $table->index(['actor_user_id', 'event_at'], 'idx_circ_audit_actor_event_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app.circulation_audit_events');
    }
};
