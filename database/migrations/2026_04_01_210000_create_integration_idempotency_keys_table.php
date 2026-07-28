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

        Schema::create('app.integration_idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('client_ref', 128);
            $table->string('operation', 64);
            $table->string('idempotency_key', 128);
            $table->uuid('reservation_id')->nullable();
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->unique(['client_ref', 'operation', 'idempotency_key'], 'uq_intg_idem_client_op_key');
            $table->index(['operation', 'reservation_id'], 'idx_intg_idem_op_reservation');
            $table->index('created_at', 'idx_intg_idem_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app.integration_idempotency_keys');
    }
};
