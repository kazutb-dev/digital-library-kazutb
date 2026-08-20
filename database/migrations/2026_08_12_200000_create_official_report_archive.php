<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('lineage_id')->index();
            $table->unsignedInteger('revision');
            $table->foreignId('previous_snapshot_id')->nullable()->constrained('official_report_snapshots')->restrictOnDelete();
            $table->string('report_type', 64)->index();
            $table->string('period_preset', 16);
            $table->timestampTz('period_from');
            $table->timestampTz('period_to');
            $table->json('filters');
            $table->json('source_data');
            $table->char('source_hash', 64);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('status', 32)->default('draft')->index();
            $table->text('revision_note')->nullable();
            $table->string('archive_disk', 32)->default('local');
            $table->string('archive_path', 1024);
            $table->char('archive_hash', 64);
            $table->unsignedBigInteger('archive_size');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestampsTz();

            $table->unique(['lineage_id', 'revision'], 'official_reports_lineage_revision_unique');
            $table->index(['report_type', 'status', 'period_from'], 'official_reports_lookup_idx');
        });

        Schema::create('official_report_exports', function (Blueprint $table): void {
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
            $table->timestampsTz();

            $table->index(['snapshot_id', 'status', 'created_at'], 'official_report_exports_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_report_exports');
        Schema::dropIfExists('official_report_snapshots');
    }
};
