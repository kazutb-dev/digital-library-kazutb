<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservations (Master.md §13: a reservation targets an EDITION, the system
 * assigns copies) and in-app notifications (§15.6, delivery layer for the
 * notification_settings matrix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('bibliographic_record_id')->constrained('bibliographic_records')->restrictOnDelete();
            $table->foreignId('assigned_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('queue_position')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['bibliographic_record_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('reader_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('title', 500);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_notifications');
        Schema::dropIfExists('reservations');
    }
};
