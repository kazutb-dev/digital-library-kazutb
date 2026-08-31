<?php

namespace Tests\Unit;

use App\Services\Catalog\MarcAcademicFields;
use PHPUnit\Framework\TestCase;

class MarcAcademicFieldsTest extends TestCase
{
    public function test_it_extracts_952_subfields_and_008_provenance(): void
    {
        $rows = [
            ['tag' => '008', 'subfield_code' => null, 'value' => '241001s20||||||kz |||||||||||000|||rus|u'],
            ['tag' => '952', 'subfield_code' => 'a', 'value' => 'КУР'],
            ['tag' => '952', 'subfield_code' => 'd', 'value' => 'Факультет экономики и сервиса'],
            ['tag' => '952', 'subfield_code' => 'e', 'value' => 'Финансы и учет'],
            ['tag' => '952', 'subfield_code' => 'i', 'value' => 'Управленческий учет'],
            ['tag' => '952', 'subfield_code' => 'j', 'value' => '6В04108 - Финансы'],
        ];

        $result = MarcAcademicFields::fromFieldRows($rows);

        $this->assertSame('КУР', $result['ksu_literature_type']);
        $this->assertSame('Факультет экономики и сервиса', $result['faculty']);
        $this->assertSame('Финансы и учет', $result['department']);
        $this->assertSame('Управленческий учет', $result['disciplines']);
        $this->assertSame('6В04108 - Финансы', $result['specialty']);
        $this->assertSame('kz', $result['country_code']);
        $this->assertSame('2024-10-01', $result['record_created_on']);
    }

    public function test_it_joins_repeatable_subfields_and_deduplicates(): void
    {
        $rows = [
            ['tag' => '952', 'subfield_code' => 'j', 'value' => 'Все специальности'],
            ['tag' => '952', 'subfield_code' => 'j', 'value' => '6В04108 - Финансы'],
            ['tag' => '952', 'subfield_code' => 'j', 'value' => 'Все специальности'],
            ['tag' => '952', 'subfield_code' => 'i', 'value' => 'Стандартизация'],
            ['tag' => '952', 'subfield_code' => 'i', 'value' => 'Менеджмент'],
        ];

        $result = MarcAcademicFields::fromFieldRows($rows);

        $this->assertSame('Все специальности; 6В04108 - Финансы', $result['specialty']);
        $this->assertSame('Стандартизация; Менеджмент', $result['disciplines']);
    }

    public function test_it_strips_marc_fill_characters_from_country_code(): void
    {
        $result = MarcAcademicFields::fromFieldRows([
            ['tag' => '008', 'subfield_code' => null, 'value' => '131230s20                          rus u'],
        ]);

        $this->assertNull($result['country_code']);
        $this->assertSame('2013-12-30', $result['record_created_on']);
    }

    public function test_it_returns_nulls_when_no_academic_fields_present(): void
    {
        $result = MarcAcademicFields::fromFieldRows([
            ['tag' => '245', 'subfield_code' => 'a', 'value' => 'Заглавие'],
        ]);

        foreach ($result as $value) {
            $this->assertNull($value);
        }
    }

    public function test_it_rejects_impossible_dates(): void
    {
        $result = MarcAcademicFields::fromFieldRows([
            ['tag' => '008', 'subfield_code' => null, 'value' => '999999s20                          rus u'],
        ]);

        $this->assertNull($result['record_created_on']);
    }

    public function test_from_values_accepts_semicolon_joined_strings(): void
    {
        $result = MarcAcademicFields::fromValues(
            literatureTypes: 'КУР',
            faculty: 'Колледж',
            department: null,
            disciplines: 'Менеджмент; Стандартизация',
            specialties: 'Все специальности',
            fixed008: null,
        );

        $this->assertSame('КУР', $result['ksu_literature_type']);
        $this->assertSame('Колледж', $result['faculty']);
        $this->assertNull($result['department']);
        $this->assertSame('Менеджмент; Стандартизация', $result['disciplines']);
        $this->assertSame('Все специальности', $result['specialty']);
        $this->assertNull($result['country_code']);
    }
}
