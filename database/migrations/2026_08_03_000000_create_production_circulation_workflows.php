<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** PostgreSQL rolls the whole schema/data upgrade back on any preflight failure. */
    public $withinTransaction = true;

    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('reservation_number', 40)->nullable()->after('id');
            $table->foreignId('pickup_branch_id')->nullable()->after('pending_transfer_branch_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('current_branch_id')->nullable()->after('pickup_branch_id')->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('queue_sequence')->nullable()->after('queue_position');
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('copy_assigned_at')->nullable();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->unsignedSmallInteger('extension_count')->default(0);
            $table->string('cancel_reason_code', 40)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('estimated_available_at')->nullable();
            $table->string('source', 20)->default('web');
            $table->smallInteger('priority')->default(0);
            $table->text('priority_reason')->nullable();
            $table->boolean('requires_resolution')->default(false);
            $table->text('resolution_reason')->nullable();

            $table->index(['status', 'pickup_branch_id'], 'ix_reservations_status_pickup_branch');
            $table->index(['bibliographic_record_id', 'status', 'priority', 'queue_sequence'], 'ix_reservations_fifo');
            $table->index('queue_sequence');
        });

        $sequence = 0;
        foreach (DB::table('reservations')->orderBy('created_at')->orderBy('id')->get(['id', 'assigned_copy_id', 'status', 'created_at']) as $row) {
            $sequence++;
            $changes = [
                'reservation_number' => sprintf('RSV-%s-%06d', now()->format('Ymd'), $row->id),
                'queue_sequence' => $sequence,
            ];
            if ($row->status === 'pending' && $row->assigned_copy_id === null) {
                $changes['status'] = 'queued';
                $changes['queued_at'] = $row->created_at;
            }
            DB::table('reservations')->where('id', $row->id)->update($changes);
        }

        Schema::table('reservations', function (Blueprint $table): void {
            $table->unique('reservation_number');
        });

        $activeStatuses = ['pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup'];
        $duplicateReaderEdition = DB::table('reservations')
            ->select('user_id', 'bibliographic_record_id')
            ->whereIn('status', $activeStatuses)
            ->groupBy('user_id', 'bibliographic_record_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        $duplicateCopy = DB::table('reservations')
            ->select('assigned_copy_id')
            ->whereNotNull('assigned_copy_id')
            ->whereIn('status', $activeStatuses)
            ->groupBy('assigned_copy_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();
        if ($duplicateReaderEdition !== null || $duplicateCopy !== null) {
            throw new RuntimeException('Active reservation duplicates must be resolved explicitly before enabling production uniqueness constraints. No rows were changed.');
        }

        DB::statement("CREATE UNIQUE INDEX ux_reservations_active_reader_record ON reservations (user_id, bibliographic_record_id) WHERE status IN ('pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup')");
        DB::statement("CREATE UNIQUE INDEX ux_reservations_active_copy ON reservations (assigned_copy_id) WHERE assigned_copy_id IS NOT NULL AND status IN ('pending', 'confirmed', 'in_transit', 'ready_for_pickup')");

        Schema::create('reservation_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('web');
            $table->text('reason')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['reservation_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('copy_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('transfer_number', 40)->unique();
            $table->foreignId('copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('source_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('destination_branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('status', 24)->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('requested_at');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('expected_at')->nullable();
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'destination_branch_id']);
            $table->index(['copy_id', 'status']);
            $table->index(['reservation_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX ux_copy_transfers_open_copy ON copy_transfers (copy_id) WHERE status IN ('requested', 'approved', 'in_transit')");
        DB::statement('CREATE UNIQUE INDEX ux_fines_loan_reason ON fines (loan_id, reason) WHERE loan_id IS NOT NULL');

        Schema::table('reader_notifications', function (Blueprint $table): void {
            $table->string('channel', 20)->default('in_app');
            $table->string('delivery_status', 20)->default('sent');
            $table->string('idempotency_key', 190)->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('last_error')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->unique('idempotency_key');
            $table->index(['delivery_status', 'created_at']);
        });

        Schema::create('inventory_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_number', 40)->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->string('room')->nullable();
            $table->string('shelf_range')->nullable();
            $table->date('inventory_date');
            $table->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('misplaced_count')->default(0);
            $table->unsignedInteger('unknown_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('inventory_session_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignId('copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->foreignId('expected_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('expected_fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->string('expected_shelf')->nullable();
            $table->string('expected_status', 32);
            $table->string('result', 32)->default('missing');
            $table->timestampTz('first_scanned_at')->nullable();
            $table->timestampsTz();
            $table->unique(['inventory_session_id', 'copy_id'], 'ux_inventory_snapshot_copy');
            $table->index(['inventory_session_id', 'result']);
        });

        Schema::create('inventory_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignId('copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->restrictOnDelete();
            $table->string('code', 128);
            $table->string('classification', 40);
            $table->boolean('is_duplicate')->default(false);
            $table->json('details')->nullable();
            $table->timestampTz('scanned_at');
            $table->index(['inventory_session_id', 'scanned_at']);
            $table->index(['inventory_session_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_scans');
        Schema::dropIfExists('inventory_session_items');
        Schema::dropIfExists('inventory_sessions');
        Schema::table('reader_notifications', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['channel', 'delivery_status', 'idempotency_key', 'attempts', 'last_error', 'sent_at']);
        });
        Schema::dropIfExists('copy_transfers');
        DB::statement('DROP INDEX IF EXISTS ux_fines_loan_reason');
        Schema::dropIfExists('reservation_history');
        DB::statement('DROP INDEX IF EXISTS ux_reservations_active_copy');
        DB::statement('DROP INDEX IF EXISTS ux_reservations_active_reader_record');
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropUnique(['reservation_number']);
            $table->dropIndex('ix_reservations_status_pickup_branch');
            $table->dropIndex('ix_reservations_fifo');
            $table->dropIndex(['queue_sequence']);
            $table->dropConstrainedForeignId('pickup_branch_id');
            $table->dropConstrainedForeignId('current_branch_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'reservation_number', 'queue_sequence', 'queued_at', 'confirmed_at', 'copy_assigned_at',
                'ready_at', 'fulfilled_at', 'cancelled_at', 'expired_at', 'extension_count',
                'cancel_reason_code', 'cancel_reason', 'estimated_available_at', 'source', 'priority',
                'priority_reason', 'requires_resolution', 'resolution_reason',
            ]);
        });
    }
};
