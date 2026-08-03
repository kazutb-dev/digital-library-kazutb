<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reader profiles, loans, and fines (Master.md §2.2, §14, ТЗ Этап 2.3-2.4).
 * Loans reference users directly — a reader is a user with a reader profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('ticket_number', 32)->unique();
            $table->string('category', 32)->default('student');
            $table->string('status', 32)->default('active');
            $table->text('block_reason')->nullable();
            $table->json('limits_override')->nullable();
            $table->timestampsTz();
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestampTz('issued_at');
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            $table->unsignedTinyInteger('renewal_count')->default(0);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_on_return', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index(['copy_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        // A copy can carry at most one open loan. Partial unique index —
        // supported by both PostgreSQL and SQLite (tests).
        DB::statement(
            "CREATE UNIQUE INDEX ux_loans_open_copy ON loans (copy_id) WHERE status IN ('active', 'overdue') AND returned_at IS NULL"
        );

        Schema::create('fines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 32);
            $table->string('status', 32)->default('pending');
            $table->timestampTz('charged_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('reader_profiles');
    }
};
