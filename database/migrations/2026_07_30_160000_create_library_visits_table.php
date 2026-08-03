<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ДИР §9.4 — "это влияет на посещаемость в библиотеки, читатель пришел".
 * Attendance is deliberately separate from circulation: scanning a card at the
 * door records a visit whether or not the reader borrows anything.
 *
 * scanned_by is nullable so an unattended kiosk or turnstile can post visits
 * later without a staff account behind them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestampTz('scanned_at');
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 32)->default('desk');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index('scanned_at');
            $table->index(['user_id', 'scanned_at']);
            $table->index(['branch_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_visits');
    }
};
