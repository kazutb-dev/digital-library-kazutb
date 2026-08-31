<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional intake batches and numeric allocation metadata.
 *
 * This migration is deliberately additive. Existing recovered catalogue and
 * KSU rows remain untouched; numeric sequence columns are nullable on legacy
 * copies and are populated only for new arrivals confirmed by the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_recovery_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('review_type', 64);
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('source_table', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('raw_value')->nullable();
            $table->string('decision', 32)->default('pending');
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['review_type', 'entity_type', 'entity_id'],
                'legacy_recovery_reviews_entity_unique',
            );
            $table->index(['review_type', 'decision'], 'legacy_recovery_reviews_queue_idx');
            $table->index(['source_table', 'source_id'], 'legacy_recovery_reviews_source_idx');
        });

        Schema::create('inventory_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 80);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('inventory_prefix', 24)->default('INV');
            $table->string('barcode_prefix', 24)->default('KAZUTB');
            $table->unsignedBigInteger('last_inventory_number')->default(0);
            $table->unsignedBigInteger('last_barcode_number')->default(0);
            $table->timestampsTz();

            $table->unique(['scope_key', 'year'], 'inventory_sequences_scope_year_unique');
        });

        Schema::create('acquisition_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 64)->unique();
            $table->string('status', 24)->default('draft');
            $table->date('received_at');
            $table->string('acquisition_source', 32)->default('purchase');
            $table->string('supplier_name')->nullable();
            $table->char('currency', 3)->default('KZT');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('acquisition_order_id')->nullable()->constrained('acquisition_orders')->nullOnDelete();
            $table->foreignId('ksu_entry_id')->nullable()->constrained('ksu_entries')->restrictOnDelete();
            $table->unsignedInteger('title_count')->default(0);
            $table->unsignedInteger('copy_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'received_at'], 'acquisition_batches_status_date_idx');
        });

        Schema::create('acquisition_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('acquisition_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bibliographic_record_id')->constrained()->restrictOnDelete();
            $table->string('title_snapshot', 500);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->string('accounting_type', 32)->default('inventory');
            $table->string('condition', 32)->default('new');
            $table->string('access_restriction', 32)->default('free');
            $table->string('storage_sigla', 64)->nullable();
            $table->string('service_point_code', 64)->nullable();
            $table->string('room', 128)->nullable();
            $table->string('section', 128)->nullable();
            $table->string('shelf_location')->nullable();
            $table->string('shelf_index')->nullable();
            $table->string('inventory_number_mode', 24)->default('auto');
            $table->json('manual_inventory_numbers')->nullable();
            $table->string('inventory_range_start', 64)->nullable();
            $table->string('inventory_range_end', 64)->nullable();
            $table->string('barcode_mode', 24)->default('auto');
            $table->json('manual_barcodes')->nullable();
            $table->string('inventory_prefix', 24)->default('INV');
            $table->string('barcode_prefix', 24)->default('KAZUTB');
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(
                ['acquisition_batch_id', 'bibliographic_record_id'],
                'acquisition_batch_items_record_idx',
            );
        });

        Schema::table('book_copies', function (Blueprint $table): void {
            $table->foreignId('acquisition_batch_id')
                ->nullable()
                ->constrained('acquisition_batches')
                ->restrictOnDelete();
            $table->foreignId('acquisition_batch_item_id')
                ->nullable()
                ->constrained('acquisition_batch_items')
                ->restrictOnDelete();
            $table->foreignId('ksu_entry_id')
                ->nullable()
                ->constrained('ksu_entries')
                ->restrictOnDelete();
            $table->string('inventory_sequence_scope', 80)->nullable();
            $table->unsignedSmallInteger('inventory_sequence_year')->nullable();
            $table->unsignedBigInteger('inventory_sequence_number')->nullable();
            $table->unsignedBigInteger('barcode_sequence_number')->nullable();
            $table->string('inventory_status', 32)->nullable();
            $table->string('circulation_status', 32)->nullable();
        });

        Schema::table('book_copies', function (Blueprint $table): void {
            $table->index('acquisition_batch_id', 'copies_acquisition_batch_idx');
            $table->index('acquisition_batch_item_id', 'copies_acquisition_item_idx');
            $table->index('ksu_entry_id', 'copies_ksu_entry_idx');
            $table->unique(
                ['inventory_sequence_scope', 'inventory_sequence_year', 'inventory_sequence_number'],
                'copies_inventory_sequence_unique',
            );
            $table->unique(
                ['inventory_sequence_scope', 'inventory_sequence_year', 'barcode_sequence_number'],
                'copies_barcode_sequence_unique',
            );
            $table->index(['inventory_status', 'circulation_status'], 'copies_inventory_circulation_idx');
        });

        // entry_number remains the presentation value. Allocation authority is
        // the numeric (book, year, number) tuple, which cannot have variants.
        Schema::table('ksu_entries', function (Blueprint $table): void {
            $table->string('operation_type', 32)->default('arrival');
            $table->string('act_number', 128)->nullable();
            $table->text('operation_reason')->nullable();
            $table->unique(['ksu_book_id', 'year', 'number'], 'ksu_entries_numeric_unique');
            $table->index(['operation_type', 'entry_date'], 'ksu_entries_operation_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ksu_entries', function (Blueprint $table): void {
            $table->dropIndex('ksu_entries_operation_date_idx');
            $table->dropUnique('ksu_entries_numeric_unique');
            $table->dropColumn(['operation_type', 'act_number', 'operation_reason']);
        });

        Schema::table('book_copies', function (Blueprint $table): void {
            $table->dropIndex('copies_acquisition_batch_idx');
            $table->dropIndex('copies_acquisition_item_idx');
            $table->dropIndex('copies_ksu_entry_idx');
            $table->dropUnique('copies_inventory_sequence_unique');
            $table->dropUnique('copies_barcode_sequence_unique');
            $table->dropIndex('copies_inventory_circulation_idx');
            $table->dropForeign(['acquisition_batch_id']);
            $table->dropForeign(['acquisition_batch_item_id']);
            $table->dropForeign(['ksu_entry_id']);
            $table->dropColumn([
                'acquisition_batch_id',
                'acquisition_batch_item_id',
                'ksu_entry_id',
                'inventory_sequence_scope',
                'inventory_sequence_year',
                'inventory_sequence_number',
                'barcode_sequence_number',
                'inventory_status',
                'circulation_status',
            ]);
        });

        Schema::dropIfExists('acquisition_batch_items');
        Schema::dropIfExists('acquisition_batches');
        Schema::dropIfExists('inventory_sequences');
        Schema::dropIfExists('legacy_recovery_reviews');
    }
};
