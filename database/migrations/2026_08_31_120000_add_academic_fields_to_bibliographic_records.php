<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the academic-targeting and record-provenance attributes that the
 * 2026-08-12 degraded import dropped from the source MARC (tag 952 + 008):
 *
 *   ksu_literature_type — 952$a — тип литературы для КСУ
 *   faculty             — 952$d — факультет
 *   department          — 952$e — кафедра
 *   disciplines         — 952$i — дисциплины
 *   specialty           — 952$j — специальность
 *   record_created_on   — 008/00-05 — дата создания записи
 *
 * country_code (008/15-17 — код страны) already exists from the recovery
 * model migration; it is only populated by the importers, not added here.
 *
 * Additive and nullable: nothing existing is rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bibliographic_records', function (Blueprint $t): void {
            $t->string('ksu_literature_type', 128)->nullable()->after('material_designation');
            $t->string('faculty', 255)->nullable()->after('ksu_literature_type');
            $t->string('department', 255)->nullable()->after('faculty');
            $t->string('disciplines', 500)->nullable()->after('department');
            $t->string('specialty', 1000)->nullable()->after('disciplines');
            $t->date('record_created_on')->nullable()->after('specialty');
        });

        Schema::table('bibliographic_records', function (Blueprint $t): void {
            $t->index('faculty', 'bib_faculty_idx');
            $t->index('department', 'bib_department_idx');
            $t->index('ksu_literature_type', 'bib_ksu_lit_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bibliographic_records', function (Blueprint $t): void {
            foreach (['bib_faculty_idx', 'bib_department_idx', 'bib_ksu_lit_type_idx'] as $index) {
                $t->dropIndex($index);
            }
            $t->dropColumn([
                'ksu_literature_type', 'faculty', 'department',
                'disciplines', 'specialty', 'record_created_on',
            ]);
        });
    }
};
