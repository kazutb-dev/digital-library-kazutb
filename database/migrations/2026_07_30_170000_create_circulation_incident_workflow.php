<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circulation_incident_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('case_number', 32)->unique();
            $table->string('incident_type', 16);
            $table->foreignId('loan_id')->unique()->constrained('loans')->restrictOnDelete();
            $table->foreignId('original_copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->foreignId('reader_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('damage_severity', 16)->nullable();
            $table->text('damage_description')->nullable();
            $table->string('condition_before', 32)->nullable();
            $table->string('condition_after', 32)->nullable();
            $table->string('preliminary_action', 32)->nullable();
            $table->string('resolution_type', 32)->nullable();
            $table->foreignId('fine_id')->nullable()->constrained('fines')->nullOnDelete();
            $table->foreignId('replacement_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->timestampTz('opened_at');
            $table->timestampTz('resolution_due_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('requires_director')->default(false);
            $table->boolean('fine_remains')->default(false);
            $table->timestampsTz();

            $table->index(['status', 'resolution_due_at']);
            $table->index(['reader_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['incident_type', 'opened_at']);
        });

        Schema::create('replacement_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_case_id')->constrained('circulation_incident_cases')->cascadeOnDelete();
            $table->foreignId('bibliographic_record_id')->nullable()->constrained('bibliographic_records')->nullOnDelete();
            $table->string('isbn', 32)->nullable();
            $table->string('author')->nullable();
            $table->string('title');
            $table->string('work_title')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 16)->nullable();
            $table->string('resource_type', 32)->nullable();
            $table->string('udc_code', 64)->nullable();
            $table->text('content_description')->nullable();
            $table->string('copy_condition', 32)->nullable();
            $table->decimal('estimated_value', 12, 2)->nullable();
            $table->string('source', 64)->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('proposed');

            // Searchable/reportable mandatory review criteria.
            $table->boolean('work_matches')->nullable();
            $table->boolean('title_matches_or_equivalent')->nullable();
            $table->boolean('author_matches_or_approved')->nullable();
            $table->boolean('content_matches')->nullable();
            $table->boolean('academic_purpose_matches')->nullable();
            $table->boolean('usable_condition')->nullable();
            $table->boolean('no_serious_damage')->nullable();
            $table->boolean('not_library_copy')->nullable();

            // Advisory criteria and human-readable comparison result.
            $table->boolean('isbn_matches')->nullable();
            $table->boolean('publisher_matches')->nullable();
            $table->smallInteger('year_difference')->nullable();
            $table->boolean('year_within_tolerance')->nullable();
            $table->boolean('language_matches')->nullable();
            $table->boolean('resource_type_matches')->nullable();
            $table->boolean('value_comparable')->nullable();
            $table->boolean('complete_set')->nullable();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('exception_criteria')->nullable();
            $table->text('reviewer_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['incident_case_id', 'status']);
            $table->index('isbn');
            $table->index(['title', 'author']);
        });

        Schema::create('incident_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_case_id')->constrained('circulation_incident_cases')->cascadeOnDelete();
            $table->string('kind', 32)->default('damage_photo');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX ux_incident_single_replacement_copy ON circulation_incident_cases (replacement_copy_id) WHERE replacement_copy_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
        Schema::dropIfExists('replacement_candidates');
        Schema::dropIfExists('circulation_incident_cases');
    }
};
