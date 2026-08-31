<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MARC-SQL recovery model — part B: catalogue column extensions.
 *
 * Separated from part A because ADD COLUMN needs an AccessExclusiveLock on
 * bibliographic_records and book_copies. Part A can be applied while the
 * catalogue is under read load; this one needs a quiet moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Bibliographic attributes ──────────────────────────────────
        Schema::table('bibliographic_records', function (Blueprint $t): void {
            $t->string('publication_place', 255)->nullable();
            $t->string('statement_of_responsibility', 1000)->nullable();
            $t->string('edition_statement', 255)->nullable();
            $t->string('issn', 32)->nullable();
            $t->string('bbk_code', 64)->nullable();
            $t->string('local_classification', 128)->nullable();
            $t->string('physical_extent', 255)->nullable();
            $t->string('physical_details', 255)->nullable();
            $t->string('dimensions', 64)->nullable();
            $t->string('accompanying_material', 255)->nullable();
            $t->string('series_title', 500)->nullable();
            $t->string('series_number', 64)->nullable();
            $t->string('volume', 64)->nullable();
            $t->string('issue', 64)->nullable();
            $t->string('part_number', 64)->nullable();
            $t->string('part_title', 500)->nullable();
            $t->string('control_number', 128)->nullable();
            $t->string('country_code', 8)->nullable();
            $t->string('cataloging_language', 16)->nullable();
            $t->string('source_agency', 128)->nullable();
            $t->string('material_designation', 128)->nullable();
            $t->string('legacy_language_code', 32)->nullable();
            $t->timestampTz('legacy_modified_at')->nullable();
            $t->string('legacy_local_path', 500)->nullable();
            // Provenance: which import batch last touched this row.
            $t->unsignedBigInteger('legacy_import_batch_id')->nullable();
            $t->timestampTz('legacy_imported_at')->nullable();
        });
        Schema::table('bibliographic_records', function (Blueprint $t): void {
            $t->index('control_number', 'bib_control_number_idx');
            $t->index('issn', 'bib_issn_idx');
            $t->index('publication_place', 'bib_pub_place_idx');
            $t->index('series_title', 'bib_series_title_idx');
        });

        // ── 4. Copy attributes ───────────────────────────────────────────
        Schema::table('book_copies', function (Blueprint $t): void {
            $t->unsignedBigInteger('legacy_inv_id')->nullable();
            $t->unsignedBigInteger('legacy_doc_id')->nullable();
            $t->boolean('inventory_number_is_synthetic')->default(false);
            $t->string('legacy_inventory_number', 128)->nullable();
            $t->string('shelf_index', 128)->nullable();
            $t->string('rack', 64)->nullable();
            $t->string('sigla_code', 64)->nullable();
            $t->unsignedInteger('legacy_sigla_id')->nullable();
            $t->string('service_point_code', 64)->nullable();
            $t->string('local_library_code', 64)->nullable();
            $t->string('fund_raw', 128)->nullable();
            $t->string('price_raw', 64)->nullable();
            $t->string('currency', 8)->nullable();
            $t->string('accounting_mode_raw', 16)->nullable();
            $t->date('writeoff_date')->nullable();
            $t->string('writeoff_act', 128)->nullable();
            $t->string('writeoff_reason', 255)->nullable();
            $t->unsignedSmallInteger('legacy_state_raw')->nullable();
            $t->string('legacy_state_label', 128)->nullable();
            $t->text('legacy_notes')->nullable();
            $t->unsignedBigInteger('legacy_import_batch_id')->nullable();
            $t->timestampTz('legacy_imported_at')->nullable();
        });
        Schema::table('book_copies', function (Blueprint $t): void {
            $t->unique('legacy_inv_id', 'copies_legacy_inv_unique');
            $t->index('barcode', 'copies_barcode_idx');
            $t->index('ksu_number', 'copies_ksu_idx');
            $t->index('sigla_code', 'copies_sigla_idx');
            $t->index('registration_date', 'copies_regdate_idx');
            $t->index('writeoff_date', 'copies_writeoff_idx');
        });

    }

    public function down(): void
    {
        Schema::table('book_copies', function (Blueprint $t): void {
            $t->dropUnique('copies_legacy_inv_unique');
            foreach (['copies_barcode_idx','copies_ksu_idx','copies_sigla_idx','copies_regdate_idx','copies_writeoff_idx'] as $i) {
                $t->dropIndex($i);
            }
            $t->dropColumn([
                'legacy_inv_id', 'legacy_doc_id', 'inventory_number_is_synthetic', 'legacy_inventory_number',
                'shelf_index', 'rack', 'sigla_code', 'legacy_sigla_id', 'service_point_code', 'local_library_code',
                'fund_raw', 'price_raw', 'currency', 'accounting_mode_raw', 'writeoff_date', 'writeoff_act',
                'writeoff_reason', 'legacy_state_raw', 'legacy_state_label', 'legacy_notes',
                'legacy_import_batch_id', 'legacy_imported_at',
            ]);
        });

        Schema::table('bibliographic_records', function (Blueprint $t): void {
            foreach (['bib_control_number_idx','bib_issn_idx','bib_pub_place_idx','bib_series_title_idx'] as $i) {
                $t->dropIndex($i);
            }
            $t->dropColumn([
                'publication_place', 'statement_of_responsibility', 'edition_statement', 'issn', 'bbk_code',
                'local_classification', 'physical_extent', 'physical_details', 'dimensions', 'accompanying_material',
                'series_title', 'series_number', 'volume', 'issue', 'part_number', 'part_title', 'control_number',
                'country_code', 'cataloging_language', 'source_agency', 'material_designation', 'legacy_language_code',
                'legacy_modified_at', 'legacy_local_path', 'legacy_import_batch_id', 'legacy_imported_at',
            ]);
        });
    }
};
