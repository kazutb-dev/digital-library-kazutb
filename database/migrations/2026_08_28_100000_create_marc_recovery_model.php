<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MARC-SQL recovery model — part A: new tables only.
 *
 * Deliberately contains no ALTER TABLE on bibliographic_records/book_copies so it
 * can be applied without an AccessExclusiveLock on the live catalogue tables.
 *
 * Extends the existing catalogue rather than creating a parallel one:
 *   1. bibliographic_records / book_copies gain the attributes the 2026-08-12
 *      import dropped;
 *   2. contributors and subjects become real relations;
 *   3. legacy_* tables hold the complete raw MARC so the .bak is no longer the
 *      only place old fields exist;
 *   4. ksu_* tables make КСУ a first-class module.
 *
 * Nothing here drops or rewrites existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 2. Contributors ──────────────────────────────────────────────
        Schema::create('contributors', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 500);
            $t->string('normalized_name', 500)->index();
            $t->string('kind', 32)->default('person'); // person | organisation | meeting
            $t->timestampsTz();
            $t->unique('normalized_name', 'contributors_normalized_unique');
        });

        Schema::create('bibliographic_record_contributor', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('bibliographic_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contributor_id')->constrained()->cascadeOnDelete();
            $t->string('role', 32)->default('author'); // author | editor | translator | compiler | other
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('marc_tag', 8)->nullable();
            $t->timestampsTz();
            $t->unique(['bibliographic_record_id', 'contributor_id', 'role'], 'bib_contributor_unique');
        });

        // ── 3. Subjects / rubrics ────────────────────────────────────────
        Schema::create('subjects', function (Blueprint $t): void {
            $t->id();
            $t->string('term', 500);
            $t->string('normalized_term', 500)->index();
            $t->string('scheme', 32)->default('topical'); // topical | geographic | genre | local
            $t->timestampsTz();
            $t->unique(['normalized_term', 'scheme'], 'subjects_term_scheme_unique');
        });

        Schema::create('bibliographic_record_subject', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('bibliographic_record_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('marc_tag', 8)->nullable();
            $t->timestampsTz();
            $t->unique(['bibliographic_record_id', 'subject_id'], 'bib_subject_unique');
        });

        // ── 5. Legacy raw MARC store ─────────────────────────────────────
        Schema::create('legacy_import_batches', function (Blueprint $t): void {
            $t->id();
            $t->string('package_name', 255);
            $t->string('package_sha256', 64);
            $t->unsignedBigInteger('package_bytes')->nullable();
            $t->string('source_system', 64)->default('MARC-SQL');
            $t->string('source_database', 64)->nullable();
            $t->string('status', 32)->default('loading'); // loading|loaded|reconciled|applied|failed
            $t->unsignedInteger('documents_expected')->default(0);
            $t->unsignedInteger('documents_loaded')->default(0);
            $t->unsignedInteger('copies_expected')->default(0);
            $t->unsignedInteger('copies_loaded')->default(0);
            $t->unsignedInteger('fields_expected')->default(0);
            $t->unsignedInteger('fields_loaded')->default(0);
            $t->json('validation')->nullable();
            $t->json('reconciliation')->nullable();
            $t->json('apply_stats')->nullable();
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('loaded_at')->nullable();
            $t->timestampTz('applied_at')->nullable();
            $t->timestampsTz();
            $t->unique(['package_sha256'], 'legacy_batch_sha_unique');
        });

        Schema::create('legacy_marc_records', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_doc_id')->index();
            $t->string('source_hash', 64);
            $t->string('leader', 255)->nullable();
            $t->string('record_type', 8)->nullable();
            $t->string('bibliographic_level', 8)->nullable();
            $t->string('control_number', 128)->nullable()->index();
            $t->string('fixed_008_raw', 255)->nullable();
            $t->string('modified_raw', 64)->nullable();
            $t->json('canonical')->nullable();   // full canonical payload
            $t->json('raw')->nullable();         // full raw payload
            $t->string('mapping_status', 64)->nullable();
            $t->unsignedBigInteger('bibliographic_record_id')->nullable()->index();
            $t->string('apply_status', 32)->default('pending'); // pending|inserted|updated|conflict|skipped|quarantined
            $t->timestampsTz();
            $t->unique(['legacy_import_batch_id', 'source_doc_id'], 'legacy_marc_rec_unique');
        });

        Schema::create('legacy_marc_fields', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_doc_id')->index();
            $t->string('tag', 8)->index();
            $t->string('indicator1', 4)->nullable();
            $t->string('indicator2', 4)->nullable();
            $t->string('subfield_code', 4)->nullable();
            $t->text('value')->nullable();
            $t->unsignedSmallInteger('occurrence')->default(0);
            $t->boolean('is_known_tag')->default(true);
            $t->json('raw')->nullable();
        });
        Schema::create('legacy_marc_copies', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_inv_id')->index();
            $t->unsignedBigInteger('source_doc_id')->nullable()->index();
            $t->string('source_hash', 64);
            $t->string('relation_status', 32)->default('linked'); // linked|orphan
            $t->json('canonical')->nullable();
            $t->json('raw')->nullable();
            $t->unsignedBigInteger('book_copy_id')->nullable()->index();
            $t->string('apply_status', 32)->default('pending');
            $t->timestampsTz();
            $t->unique(['legacy_import_batch_id', 'source_inv_id'], 'legacy_marc_copy_unique');
        });

        Schema::create('legacy_import_conflicts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $t->string('entity_type', 64);           // bibliographic_record | book_copy
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('field_name', 128);
            $t->text('current_value')->nullable();
            $t->text('incoming_value')->nullable();
            $t->string('reason', 128);               // manual_edit_after_import | duplicate_inventory | ...
            $t->string('status', 32)->default('open'); // open|kept_current|applied_incoming|ignored
            $t->text('resolution_note')->nullable();
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->timestampTz('resolved_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'entity_type'], 'legacy_conflict_status_idx');
        });

        Schema::create('legacy_import_quarantine', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('legacy_import_batch_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 64)->index();        // orphan_copy | malformed_marc | invalid_date | invalid_price | duplicate_inventory | unknown_marc_tag
            $t->unsignedBigInteger('source_doc_id')->nullable();
            $t->unsignedBigInteger('source_inv_id')->nullable();
            $t->text('reason')->nullable();
            $t->json('payload')->nullable();
            $t->string('status', 32)->default('open');
            $t->timestampsTz();
        });

        // ── 6. КСУ module ────────────────────────────────────────────────
        Schema::create('ksu_books', function (Blueprint $t): void {
            $t->id();
            $t->string('code', 32)->unique();        // KSU-1, KSU-2, KSU-3
            $t->string('name', 255);
            $t->text('description')->nullable();
            $t->string('legacy_source_table', 64)->nullable();
            $t->string('numbering_format', 64)->default('number/year');
            $t->string('reset_period', 32)->nullable();
            // Automatic allocation stays OFF until the legacy rule is proven.
            $t->boolean('auto_numbering_enabled')->default(false);
            $t->text('numbering_rule_evidence')->nullable();
            $t->boolean('requires_manual_decision')->default(true);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestampsTz();
        });

        Schema::create('ksu_sequences', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('ksu_book_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('last_number')->default(0);
            $t->unsignedInteger('min_observed')->nullable();
            $t->unsignedInteger('max_observed')->nullable();
            $t->json('missing_numbers')->nullable();
            $t->json('duplicate_numbers')->nullable();
            $t->boolean('allocation_enabled')->default(false);
            $t->timestampsTz();
            $t->unique(['ksu_book_id', 'year'], 'ksu_sequence_unique');
        });

        Schema::create('ksu_entries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('ksu_book_id')->constrained()->cascadeOnDelete();
            $t->string('entry_number', 64);           // "36/2025"
            $t->unsignedInteger('number');            // 36
            $t->unsignedSmallInteger('year');         // 2025
            $t->date('entry_date')->nullable();
            $t->string('acquisition_source', 255)->nullable();
            $t->string('supplier_name', 255)->nullable();
            $t->unsignedInteger('title_count')->default(0);
            $t->unsignedInteger('copy_count')->default(0);
            $t->decimal('total_cost', 14, 2)->nullable();
            $t->string('total_cost_raw', 64)->nullable();
            $t->unsignedBigInteger('fund_id')->nullable();
            $t->unsignedBigInteger('branch_id')->nullable();
            $t->string('status', 32)->default('legacy'); // legacy|draft|posted
            $t->string('legacy_ksu_id', 64)->nullable()->index();
            $t->string('legacy_source_table', 64)->nullable();
            $t->json('legacy_breakdown')->nullable();  // m7..m33 raw
            $t->string('source_row_hash', 64)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestampsTz();
            $t->unique(['ksu_book_id', 'entry_number'], 'ksu_entry_unique');
        });

        Schema::create('ksu_entry_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('ksu_entry_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('book_copy_id')->nullable()->index();
            $t->unsignedBigInteger('bibliographic_record_id')->nullable()->index();
            $t->unsignedBigInteger('source_inv_id')->nullable()->index();
            $t->unsignedBigInteger('source_doc_id')->nullable();
            $t->string('inventory_number', 128)->nullable();
            $t->decimal('price', 14, 2)->nullable();
            $t->date('registration_date')->nullable();
            $t->string('link_method', 128)->nullable();
            $t->string('link_confidence', 16)->default('high'); // high|medium|low
            $t->timestampsTz();
            $t->unique(['ksu_entry_id', 'source_inv_id'], 'ksu_entry_item_unique');
        });

        Schema::create('ksu_conflicts', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('ksu_book_id')->nullable();
            $t->string('kind', 64)->index();  // unresolved_link | duplicate_number | missing_number | ambiguous_number
            $t->string('ksu_number_raw', 64)->nullable();
            $t->unsignedBigInteger('source_inv_id')->nullable()->index();
            $t->unsignedBigInteger('source_doc_id')->nullable();
            $t->unsignedBigInteger('book_copy_id')->nullable();
            $t->text('reason')->nullable();
            $t->json('payload')->nullable();
            $t->string('status', 32)->default('open'); // open|resolved|ignored
            $t->text('resolution_note')->nullable();
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->timestampTz('resolved_at')->nullable();
            $t->timestampsTz();
        });

        Schema::create('ksu_audit_events', function (Blueprint $t): void {
            $t->id();
            $t->string('event_type', 64)->index(); // entry.created|number.allocated|item.linked|conflict.resolved|legacy.imported
            $t->unsignedBigInteger('ksu_book_id')->nullable();
            $t->unsignedBigInteger('ksu_entry_id')->nullable();
            $t->unsignedBigInteger('book_copy_id')->nullable();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name', 255)->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->text('reason')->nullable();
            $t->timestampTz('occurred_at');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksu_audit_events');
        Schema::dropIfExists('ksu_conflicts');
        Schema::dropIfExists('ksu_entry_items');
        Schema::dropIfExists('ksu_entries');
        Schema::dropIfExists('ksu_sequences');
        Schema::dropIfExists('ksu_books');
        Schema::dropIfExists('legacy_import_quarantine');
        Schema::dropIfExists('legacy_import_conflicts');
        Schema::dropIfExists('legacy_marc_copies');
        Schema::dropIfExists('legacy_marc_fields');
        Schema::dropIfExists('legacy_marc_records');
        Schema::dropIfExists('legacy_import_batches');
        Schema::dropIfExists('bibliographic_record_subject');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('bibliographic_record_contributor');
        Schema::dropIfExists('contributors');

    }
};
