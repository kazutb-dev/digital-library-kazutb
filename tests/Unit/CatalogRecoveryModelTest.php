<?php

namespace Tests\Unit;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\Contributor;
use App\Models\Catalog\Subject;
use Tests\TestCase;

class CatalogRecoveryModelTest extends TestCase
{
    public function test_recovery_metadata_is_mass_assignable_and_typed_without_exposing_raw_relations_as_attributes(): void
    {
        $record = new BibliographicRecord([
            'publication_place' => 'Алматы',
            'statement_of_responsibility' => 'составитель А. Автор',
            'edition_statement' => '2-е изд.',
            'issn' => '1234-5678',
            'bbk_code' => '78.34',
            'series_title' => 'Университетская библиотека',
            'part_number' => '2',
            'control_number' => 'KAZUTB-42',
            'legacy_import_batch_id' => '17',
            'legacy_modified_at' => '2026-08-28 12:00:00',
        ]);

        $this->assertSame('Алматы', $record->publication_place);
        $this->assertSame('KAZUTB-42', $record->control_number);
        $this->assertSame(17, $record->legacy_import_batch_id);
        $this->assertSame('2026-08-28', $record->legacy_modified_at->toDateString());
        $this->assertContains('material_designation', $record->getFillable());
        $this->assertContains('legacy_imported_at', $record->getFillable());
    }

    public function test_relation_dictionary_normalization_is_unicode_aware_and_stable(): void
    {
        $this->assertSame('әбілқасымова г. қ.', Contributor::normalizeName('  Әбілқасымова   Г. Қ.  '));
        $this->assertSame('теория вероятностей', Subject::normalizeTerm(" Теория   вероятностей\n"));
        $this->assertSame(['person', 'organisation', 'meeting'], Contributor::KINDS);
        $this->assertContains('translator', Contributor::ROLES);
        $this->assertContains('geographic', Subject::SCHEMES);
    }
}
