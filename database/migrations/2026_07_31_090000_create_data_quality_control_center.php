<?php

use App\Models\NotificationSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_scan_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('run_number', 32)->unique();
            $table->string('scope', 64)->index();
            $table->string('status', 32)->default('queued')->index();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rules_version', 32);
            $table->unsignedBigInteger('records_scanned')->default(0);
            $table->unsignedBigInteger('issues_found')->default(0);
            $table->unsignedBigInteger('issues_created')->default(0);
            $table->unsignedBigInteger('issues_reopened')->default(0);
            $table->unsignedBigInteger('issues_resolved_automatically')->default(0);
            $table->unsignedBigInteger('duration_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('data_quality_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('issue_number', 32)->unique();
            $table->string('entity_type', 64)->index();
            $table->string('entity_id', 191)->index();
            $table->string('rule_code', 96)->index();
            $table->string('category', 64)->index();
            $table->string('severity', 16)->index();
            $table->string('status', 32)->default('open')->index();
            $table->string('field_name', 128)->nullable();
            $table->text('current_value')->nullable();
            $table->text('expected_format')->nullable();
            $table->text('description');
            $table->text('suggested_action')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->foreignId('scan_run_id')->nullable()->constrained('data_quality_scan_runs')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_type', 32)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('first_detected_at');
            $table->timestampTz('last_detected_at');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('ignored_until')->nullable();
            $table->text('false_positive_reason')->nullable();
            $table->timestampTz('due_at')->nullable()->index();
            $table->json('context')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'severity', 'last_detected_at'], 'dq_issues_work_queue');
            $table->index(['entity_type', 'entity_id', 'status'], 'dq_issues_entity_status');
        });

        Schema::create('data_quality_issue_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('data_quality_issues')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestampsTz();
        });

        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->foreignId('merged_into_id')->nullable()->after('responsible_librarian_id')
                ->constrained('bibliographic_records')->nullOnDelete();
            $table->string('merge_status', 24)->default('active')->after('merged_into_id')->index();
            $table->string('legacy_external_id', 191)->nullable()->after('merge_status')->index();
        });

        Schema::create('duplicate_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('group_number', 32)->unique();
            $table->string('status', 32)->default('open')->index();
            $table->decimal('score', 5, 2)->default(0);
            $table->string('match_level', 24)->index();
            $table->foreignId('canonical_record_id')->nullable()->constrained('bibliographic_records')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('fingerprint', 64)->unique();
            $table->timestampsTz();
        });

        Schema::create('duplicate_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('duplicate_group_id')->constrained('duplicate_groups')->cascadeOnDelete();
            $table->foreignId('bibliographic_record_id')->constrained('bibliographic_records')->cascadeOnDelete();
            $table->boolean('is_recommended_canonical')->default(false);
            $table->json('match_details')->nullable();
            $table->timestampsTz();
            $table->unique(['duplicate_group_id', 'bibliographic_record_id'], 'dq_duplicate_member_unique');
        });

        Schema::create('record_merge_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_number', 32)->unique();
            $table->foreignId('duplicate_group_id')->nullable()->constrained('duplicate_groups')->nullOnDelete();
            $table->foreignId('target_record_id')->constrained('bibliographic_records')->restrictOnDelete();
            $table->foreignId('source_record_id')->constrained('bibliographic_records')->restrictOnDelete();
            $table->string('status', 32)->default('proposed')->index();
            $table->json('field_selection');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->text('rollback_block_reason')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();
            $table->unique(['source_record_id', 'status'], 'dq_one_source_merge_state');
        });

        Schema::create('data_correction_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 32)->unique();
            $table->string('operation_type', 64)->index();
            $table->string('entity_type', 64)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->json('selection_filter');
            $table->json('operation_config');
            $table->boolean('dry_run')->default(true);
            $table->boolean('high_risk')->default(false);
            $table->unsignedInteger('records_selected')->default(0);
            $table->unsignedInteger('records_succeeded')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('data_correction_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('data_correction_batches')->cascadeOnDelete();
            $table->string('entity_id', 191);
            $table->string('status', 32)->default('pending')->index();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
            $table->unique(['batch_id', 'entity_id']);
        });

        Schema::create('data_import_mapping_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('source_format', 32);
            $table->unsignedInteger('version')->default(1);
            $table->json('mapping');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['name', 'version']);
        });

        Schema::create('data_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 32)->unique();
            $table->string('source_format', 32)->index();
            $table->string('source_filename');
            $table->string('checksum', 64)->index();
            $table->string('status', 32)->default('uploaded')->index();
            $table->string('detected_encoding', 32)->nullable();
            $table->decimal('encoding_confidence', 5, 2)->nullable();
            $table->string('selected_encoding', 32)->nullable();
            $table->foreignId('mapping_profile_id')->nullable()->constrained('data_import_mapping_profiles')->nullOnDelete();
            $table->unsignedInteger('mapping_version')->nullable();
            $table->unsignedBigInteger('rows_total')->default(0);
            $table->unsignedBigInteger('rows_valid')->default(0);
            $table->unsignedBigInteger('rows_error')->default(0);
            $table->unsignedBigInteger('rows_imported')->default(0);
            $table->unsignedBigInteger('rows_skipped')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('reconciliation')->nullable();
            $table->timestampsTz();

            $table->unique(['checksum', 'source_format'], 'dq_import_checksum_format_unique');
        });

        Schema::create('data_import_staging_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('data_import_batches')->cascadeOnDelete();
            $table->string('source_row_id', 191);
            $table->json('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->json('mapping_result')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('duplicate_candidates')->nullable();
            $table->string('proposed_action', 32)->default('review');
            $table->string('status', 32)->default('staged')->index();
            $table->foreignId('final_entity_id')->nullable()->constrained('bibliographic_records')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
            $table->unique(['batch_id', 'source_row_id']);
        });

        if (Schema::hasTable('notification_settings')) {
            $now = now('UTC');
            DB::table('notification_settings')->insertOrIgnore(array_map(
                static fn (string $eventType): array => [
                    'event_type' => $eventType,
                    'in_app_enabled' => true,
                    'email_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                array_values(array_filter(
                    NotificationSetting::EVENT_TYPES,
                    static fn (string $eventType): bool => str_starts_with($eventType, 'data_quality_'),
                )),
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_staging_rows');
        Schema::dropIfExists('data_import_batches');
        Schema::dropIfExists('data_import_mapping_profiles');
        Schema::dropIfExists('data_correction_batch_items');
        Schema::dropIfExists('data_correction_batches');
        Schema::dropIfExists('record_merge_operations');
        Schema::dropIfExists('duplicate_group_members');
        Schema::dropIfExists('duplicate_groups');
        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn(['merged_into_id', 'merge_status', 'legacy_external_id']);
        });
        Schema::dropIfExists('data_quality_issue_comments');
        Schema::dropIfExists('data_quality_issues');
        Schema::dropIfExists('data_quality_scan_runs');
    }
};
