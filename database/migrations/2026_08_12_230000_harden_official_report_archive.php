<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_report_lineages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('lineage_id')->unique();
            $table->timestampsTz();
        });

        DB::table('official_report_snapshots')
            ->select('lineage_id')
            ->distinct()
            ->orderBy('lineage_id')
            ->chunk(500, function ($rows): void {
                $now = now('UTC');
                $records = collect($rows)->map(fn (object $row): array => [
                    'lineage_id' => (string) $row->lineage_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                DB::table('official_report_lineages')->insertOrIgnore($records);
            });

        Schema::table('report_export_jobs', function (Blueprint $table): void {
            $table->char('active_key', 64)->nullable()->after('idempotency_key')->unique();
            $table->uuid('lease_token')->nullable()->after('attempts');
            $table->timestampTz('lease_expires_at')->nullable()->after('lease_token')->index();
            $table->timestampTz('dispatch_after')->nullable()->after('lease_expires_at')->index();
            $table->timestampTz('dispatched_at')->nullable()->after('dispatch_after');
            $table->timestampTz('last_heartbeat_at')->nullable()->after('dispatched_at');
            $table->string('public_error_code', 64)->nullable()->after('error_message');
            $table->timestampTz('file_deleted_at')->nullable()->after('retention_until');
            $table->index(['status', 'dispatch_after', 'dispatched_at'], 'report_export_dispatch_idx');
            $table->index(['status', 'lease_expires_at'], 'report_export_lease_idx');
        });

        $this->reconcileLegacyExports();
        $this->reconcileApprovedRevisions();
        $this->installDatabaseGuards();
    }

    public function down(): void
    {
        $this->dropDatabaseGuards();

        Schema::table('report_export_jobs', function (Blueprint $table): void {
            $table->dropIndex('report_export_dispatch_idx');
            $table->dropIndex('report_export_lease_idx');
            $table->dropIndex(['lease_expires_at']);
            $table->dropIndex(['dispatch_after']);
            $table->dropUnique(['active_key']);
            $table->dropColumn([
                'active_key', 'lease_token', 'lease_expires_at', 'dispatch_after', 'dispatched_at',
                'last_heartbeat_at', 'public_error_code', 'file_deleted_at',
            ]);
        });

        Schema::dropIfExists('official_report_lineages');
    }

    private function reconcileLegacyExports(): void
    {
        if (! Schema::hasTable('official_report_exports')) {
            return;
        }

        DB::table('official_report_exports')->orderBy('id')->chunkById(250, function ($rows): void {
            foreach ($rows as $row) {
                $record = (array) $row;
                unset($record['id']);
                $record['status'] = match ((string) $row->status) {
                    'running' => 'generating',
                    'completed' => 'ready',
                    default => $row->status,
                };
                $record['retention_until'] = $row->completed_at === null
                    ? null
                    : now('UTC')->addDays(max(1, (int) config('library.reports.export_retention_days', 365)));
                $record['dispatch_after'] = $record['status'] === 'queued' ? now('UTC') : null;

                foreach ([
                    'active_key', 'lease_token', 'lease_expires_at', 'dispatched_at', 'last_heartbeat_at',
                    'public_error_code', 'file_deleted_at',
                ] as $column) {
                    $record[$column] = null;
                }

                DB::table('report_export_jobs')->updateOrInsert(
                    ['idempotency_key' => $row->idempotency_key],
                    $record,
                );
            }
        });

        DB::table('report_export_jobs')
            ->where('status', 'queued')
            ->whereNull('dispatch_after')
            ->update(['dispatch_after' => now('UTC')]);
    }

    private function reconcileApprovedRevisions(): void
    {
        DB::table('official_report_snapshots')
            ->select('lineage_id')
            ->where('status', 'approved')
            ->groupBy('lineage_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('lineage_id')
            ->cursor()
            ->each(function (object $duplicate): void {
                $winner = DB::table('official_report_snapshots')
                    ->where('lineage_id', $duplicate->lineage_id)
                    ->where('status', 'approved')
                    ->orderByDesc('revision')
                    ->orderByDesc('id')
                    ->first(['id']);
                if ($winner === null) {
                    return;
                }
                DB::table('official_report_snapshots')
                    ->where('lineage_id', $duplicate->lineage_id)
                    ->where('status', 'approved')
                    ->where('id', '!=', $winner->id)
                    ->update([
                        'status' => 'superseded',
                        'superseded_by_snapshot_id' => $winner->id,
                        'updated_at' => now('UTC'),
                    ]);
            });
    }

    private function installDatabaseGuards(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX official_report_one_approved_per_lineage ON official_report_snapshots (lineage_id) WHERE status = 'approved'");
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION guard_official_report_snapshot_immutability()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status IN ('approved', 'superseded', 'archived') THEN
            RAISE EXCEPTION 'locked official report snapshots cannot be deleted';
        END IF;
        RETURN OLD;
    END IF;
    IF NEW.public_id IS DISTINCT FROM OLD.public_id OR
       NEW.report_number IS DISTINCT FROM OLD.report_number OR
       NEW.lineage_id IS DISTINCT FROM OLD.lineage_id OR
       NEW.revision IS DISTINCT FROM OLD.revision OR
       NEW.previous_snapshot_id IS DISTINCT FROM OLD.previous_snapshot_id OR
       NEW.report_type IS DISTINCT FROM OLD.report_type OR
       NEW.period_preset IS DISTINCT FROM OLD.period_preset OR
       NEW.period_from IS DISTINCT FROM OLD.period_from OR
       NEW.period_to IS DISTINCT FROM OLD.period_to OR
       NEW.filters::text IS DISTINCT FROM OLD.filters::text OR
       NEW.source_data::text IS DISTINCT FROM OLD.source_data::text OR
       NEW.source_hash IS DISTINCT FROM OLD.source_hash OR
       NEW.schema_version IS DISTINCT FROM OLD.schema_version OR
       NEW.archive_disk IS DISTINCT FROM OLD.archive_disk OR
       NEW.archive_path IS DISTINCT FROM OLD.archive_path OR
       NEW.archive_hash IS DISTINCT FROM OLD.archive_hash OR
       NEW.archive_size IS DISTINCT FROM OLD.archive_size OR
       NEW.created_by IS DISTINCT FROM OLD.created_by OR
       NEW.retention_until IS DISTINCT FROM OLD.retention_until OR
       NEW.created_at IS DISTINCT FROM OLD.created_at THEN
        RAISE EXCEPTION 'official report source fields are immutable';
    END IF;
    IF OLD.status IN ('approved', 'superseded', 'archived') THEN
        IF OLD.status = 'approved' AND NEW.status = 'superseded' AND
           (to_jsonb(NEW) - ARRAY['status', 'superseded_by_snapshot_id', 'updated_at']) =
           (to_jsonb(OLD) - ARRAY['status', 'superseded_by_snapshot_id', 'updated_at']) AND
           EXISTS (
               SELECT 1 FROM official_report_snapshots successor
               WHERE successor.id = NEW.superseded_by_snapshot_id
                 AND successor.lineage_id = OLD.lineage_id
                 AND successor.revision > OLD.revision
                 AND successor.status IN ('pending_review', 'approved')
           ) THEN
            RETURN NEW;
        END IF;
        IF OLD.status IN ('approved', 'superseded') AND NEW.status = 'archived' AND
           (to_jsonb(NEW) - ARRAY['status', 'archived_by', 'archived_at', 'updated_at']) =
           (to_jsonb(OLD) - ARRAY['status', 'archived_by', 'archived_at', 'updated_at']) THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'locked official report snapshots are immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
            DB::unprepared(<<<'SQL'
CREATE TRIGGER official_report_snapshot_immutable_update
BEFORE UPDATE ON official_report_snapshots
FOR EACH ROW EXECUTE FUNCTION guard_official_report_snapshot_immutability();
CREATE TRIGGER official_report_snapshot_immutable_delete
BEFORE DELETE ON official_report_snapshots
FOR EACH ROW EXECUTE FUNCTION guard_official_report_snapshot_immutability();
SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX official_report_one_approved_per_lineage ON official_report_snapshots (lineage_id) WHERE status = 'approved'");
            DB::unprepared(<<<'SQL'
CREATE TRIGGER official_report_snapshot_immutable_update
BEFORE UPDATE ON official_report_snapshots
FOR EACH ROW
WHEN OLD.status IN ('approved', 'superseded', 'archived') AND (
    NOT (
        OLD.status = 'approved' AND NEW.status = 'superseded' AND
        NEW.revision_note IS OLD.revision_note AND NEW.submitted_by IS OLD.submitted_by AND
        NEW.submitted_at IS OLD.submitted_at AND NEW.approved_by IS OLD.approved_by AND
        NEW.approved_at IS OLD.approved_at AND NEW.rejected_by IS OLD.rejected_by AND
        NEW.rejected_at IS OLD.rejected_at AND NEW.decision_note IS OLD.decision_note AND
        NEW.archived_by IS OLD.archived_by AND NEW.archived_at IS OLD.archived_at AND
        EXISTS (
            SELECT 1 FROM official_report_snapshots successor
            WHERE successor.id = NEW.superseded_by_snapshot_id
              AND successor.lineage_id = OLD.lineage_id
              AND successor.revision > OLD.revision
              AND successor.status IN ('pending_review', 'approved')
        )
    ) AND NOT (
        OLD.status IN ('approved', 'superseded') AND NEW.status = 'archived' AND
        NEW.revision_note IS OLD.revision_note AND NEW.submitted_by IS OLD.submitted_by AND
        NEW.submitted_at IS OLD.submitted_at AND NEW.approved_by IS OLD.approved_by AND
        NEW.approved_at IS OLD.approved_at AND NEW.rejected_by IS OLD.rejected_by AND
        NEW.rejected_at IS OLD.rejected_at AND NEW.decision_note IS OLD.decision_note AND
        NEW.superseded_by_snapshot_id IS OLD.superseded_by_snapshot_id
    )
)
BEGIN
    SELECT RAISE(ABORT, 'invalid locked official report transition');
END;
CREATE TRIGGER official_report_snapshot_source_immutable
BEFORE UPDATE ON official_report_snapshots
FOR EACH ROW WHEN
    NEW.public_id IS NOT OLD.public_id OR NEW.report_number IS NOT OLD.report_number OR
    NEW.lineage_id IS NOT OLD.lineage_id OR NEW.revision IS NOT OLD.revision OR
    NEW.previous_snapshot_id IS NOT OLD.previous_snapshot_id OR NEW.report_type IS NOT OLD.report_type OR
    NEW.period_preset IS NOT OLD.period_preset OR NEW.period_from IS NOT OLD.period_from OR
    NEW.period_to IS NOT OLD.period_to OR NEW.filters IS NOT OLD.filters OR
    NEW.source_data IS NOT OLD.source_data OR NEW.source_hash IS NOT OLD.source_hash OR
    NEW.schema_version IS NOT OLD.schema_version OR NEW.archive_disk IS NOT OLD.archive_disk OR
    NEW.archive_path IS NOT OLD.archive_path OR NEW.archive_hash IS NOT OLD.archive_hash OR
    NEW.archive_size IS NOT OLD.archive_size OR NEW.created_by IS NOT OLD.created_by OR
    NEW.retention_until IS NOT OLD.retention_until OR NEW.created_at IS NOT OLD.created_at
BEGIN
    SELECT RAISE(ABORT, 'official report source fields are immutable');
END;
CREATE TRIGGER official_report_snapshot_immutable_delete
BEFORE DELETE ON official_report_snapshots
FOR EACH ROW WHEN OLD.status IN ('approved', 'superseded', 'archived')
BEGIN
    SELECT RAISE(ABORT, 'locked official report snapshots cannot be deleted');
END;
SQL);
        }
    }

    private function dropDatabaseGuards(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        DB::statement('DROP INDEX IF EXISTS official_report_one_approved_per_lineage');

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS official_report_snapshot_immutable_update ON official_report_snapshots; DROP TRIGGER IF EXISTS official_report_snapshot_immutable_delete ON official_report_snapshots; DROP FUNCTION IF EXISTS guard_official_report_snapshot_immutability();');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS official_report_snapshot_source_immutable; DROP TRIGGER IF EXISTS official_report_snapshot_immutable_update; DROP TRIGGER IF EXISTS official_report_snapshot_immutable_delete;');
        }
    }
};
