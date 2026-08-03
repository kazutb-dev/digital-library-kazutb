<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical copy / inventory domain (Master.md §12). Inventory number and
 * barcode uniqueness are enforced at the database level, not only in forms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bibliographic_record_id')->constrained('bibliographic_records')->restrictOnDelete();
            $table->string('inventory_number', 64)->unique();
            // Part of the legacy fond has no barcodes (§11.2) — nullable,
            // but unique whenever present.
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('accounting_type', 32)->nullable();
            $table->string('ksu_number', 64)->nullable();
            $table->string('storage_sigla', 64)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->string('shelf_location')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('acquisition_source')->nullable();
            $table->date('acquisition_date')->nullable();
            $table->date('registration_date')->nullable();
            $table->string('condition', 32)->default('new');
            $table->text('defect_description')->nullable();
            $table->string('status', 32)->default('available');
            $table->string('access_restriction', 32)->default('free');
            $table->unsignedInteger('issue_count')->default(0);
            $table->timestampsTz();

            $table->index(['bibliographic_record_id', 'status']);
            $table->index(['branch_id', 'fund_id']);
            $table->index('status');
            $table->index('condition');
        });

        Schema::create('copy_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('copy_id')->constrained('book_copies')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('loan_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['copy_id', 'occurred_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_history');
        Schema::dropIfExists('book_copies');
    }
};
