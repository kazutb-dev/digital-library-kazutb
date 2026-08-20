<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_report_snapshots', function (Blueprint $table): void {
            $table->string('report_number', 64)->nullable()->after('public_id');
            $table->foreignId('superseded_by_snapshot_id')->nullable()->after('previous_snapshot_id')
                ->constrained('official_report_snapshots')->restrictOnDelete();
            $table->foreignId('archived_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable()->after('archived_by');
            $table->timestampTz('retention_until')->nullable()->after('archived_at')->index();
        });

        DB::table('official_report_snapshots')->orderBy('id')->get()->each(function (object $row): void {
            $prefix = match ((string) $row->report_type) {
                'acquisitions' => 'ACQ',
                'fund-usage' => 'FUND',
                'users' => 'USR',
                'electronic-resources' => 'ERES',
                default => 'RPT',
            };
            $year = substr((string) ($row->created_at ?? ''), 0, 4) ?: gmdate('Y');
            $lineage = strtoupper(substr(str_replace('-', '', (string) $row->lineage_id), 0, 8));
            DB::table('official_report_snapshots')->where('id', $row->id)->update([
                'report_number' => sprintf('%s-%s-%s-R%03d', $prefix, $year, $lineage, (int) $row->revision),
                'status' => match ((string) $row->status) {
                    'draft' => 'generated',
                    'pending_approval' => 'pending_review',
                    default => $row->status,
                },
                'retention_until' => now('UTC')->addYears(10),
            ]);
        });
        Schema::table('official_report_snapshots', function (Blueprint $table): void {
            $table->unique('report_number', 'official_report_number_unique');
        });

        // Canonical job table is copied rather than renamed so an already
        // deployed node running the previous release can finish its workers
        // during a rolling rollout. Old rows are migrated once below.
        Schema::create('report_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('snapshot_id')->constrained('official_report_snapshots')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('format', 8);
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->char('idempotency_key', 64)->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('file_disk', 32)->nullable();
            $table->string('file_path', 1024)->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('retention_until')->nullable()->index();
            $table->timestampsTz();
            $table->index(['snapshot_id', 'status', 'created_at'], 'report_export_jobs_lookup_idx');
        });
        if (Schema::hasTable('official_report_exports')) {
            DB::table('official_report_exports')->orderBy('id')->get()->each(function (object $row): void {
                $record = (array) $row;
                $record['status'] = match ((string) $row->status) {
                    'running' => 'generating',
                    'completed' => 'ready',
                    default => $row->status,
                };
                $record['retention_until'] = $row->completed_at === null ? null : now('UTC')->addDays(365);
                DB::table('report_export_jobs')->insert($record);
            });
        }

        if (Schema::hasTable('notification_settings')) {
            foreach (['report_export_ready', 'report_export_failed'] as $eventType) {
                DB::table('notification_settings')->updateOrInsert(
                    ['event_type' => $eventType],
                    ['in_app_enabled' => true, 'email_enabled' => false, 'updated_at' => now('UTC'), 'created_at' => now('UTC')],
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_settings')) {
            DB::table('notification_settings')->whereIn('event_type', ['report_export_ready', 'report_export_failed'])->delete();
        }
        Schema::dropIfExists('report_export_jobs');

        DB::table('official_report_snapshots')->where('status', 'generated')->update(['status' => 'draft']);
        DB::table('official_report_snapshots')->where('status', 'pending_review')->update(['status' => 'pending_approval']);
        Schema::table('official_report_snapshots', function (Blueprint $table): void {
            $table->dropUnique('official_report_number_unique');
            $table->dropConstrainedForeignId('superseded_by_snapshot_id');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['report_number', 'archived_at', 'retention_until']);
        });
    }
};
