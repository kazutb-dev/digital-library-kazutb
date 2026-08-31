<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * The librarian copy registry doubles as the MARC-style advanced search:
 * bibliographic attributes (faculty, department, discipline, specialty,
 * publisher, place, series, language, keywords) plus copy attributes
 * (inventory, barcode, KSU number, sigla, shelf index, received from, invoice)
 * must all narrow the result set.
 */
class CopyAdvancedSearchTest extends TestCase
{
    use BuildsAdminControlPlane;

    /**
     * The shared control-plane fixture ships a trimmed migration set; the MARC
     * recovery and academic columns this search relies on are applied on top.
     */
    private function bootAdvancedSearchSchema(): void
    {
        $this->setUpAdminControlPlane();

        foreach ([
            'database/migrations/2026_08_28_100100_extend_catalogue_for_marc_recovery.php',
            'database/migrations/2026_08_31_120000_add_academic_fields_to_bibliographic_records.php',
        ] as $path) {
            (require base_path($path))->up();
        }
    }

    private function seedCopy(): BookCopy
    {
        $branch = Branch::query()->create([
            'code' => 'ADV-SCI',
            'name' => 'Научная библиотека (поиск)',
            'type' => 'library',
            'is_active' => true,
        ]);
        $fund = Fund::query()->create([
            'branch_id' => $branch->getKey(),
            'code' => 'ADV-MAIN',
            'name' => 'Основной фонд (поиск)',
            'fund_type' => 'main',
            'institutional_scope' => 'general',
            'is_active' => true,
        ]);

        $record = BibliographicRecord::factory()->create([
            'title' => 'Управленческий учет',
            'primary_author' => 'Қауашев С.Қ.',
            'publisher' => 'Фолиант',
            'publication_place' => 'Алматы',
            'publication_year' => 2020,
            'series_title' => 'Cambridge',
            'language' => 'kk',
            'keywords' => ['логистика', 'финансы'],
            'faculty' => 'Факультет экономики и сервиса',
            'department' => 'Финансы и учет',
            'disciplines' => 'Управленческий учет',
            'specialty' => '6В04108 - Финансы',
            'udc_code' => '657',
            'annotation' => 'Учебное пособие.',
            'resource_type' => 'textbook',
        ]);

        return BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'INV-MARC-1',
            'barcode' => 'BC-MARC-1',
            'ksu_number' => '12/2025',
            'storage_sigla' => 'НБ',
            'shelf_index' => '5-3',
            'acquisition_source' => 'Дар кафедры',
            'supplier_name' => 'ТОО Академкнига',
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
            'status' => 'available',
            'access_restriction' => 'free',
            'condition' => 'good',
        ]);
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    public static function matchingFilters(): array
    {
        return [
            'faculty' => ['faculty', 'экономик'],
            'department' => ['department', 'Финансы'],
            'discipline' => ['discipline', 'Управленческий'],
            'specialty' => ['specialty', '6В04108'],
            'publisher' => ['publisher', 'Фолиант'],
            'publication_place' => ['publication_place', 'Алматы'],
            'series' => ['series', 'Cambridge'],
            'language' => ['language', 'kk'],
            'keywords' => ['keywords', 'логистика'],
            'publication_year' => ['publication_year', '2020'],
            'ksu_number' => ['ksu_number', '12/2025'],
            'storage_sigla' => ['storage_sigla', 'НБ'],
            'shelf_index' => ['shelf_index', '5-3'],
            'received_from' => ['received_from', 'Дар'],
            'invoice' => ['invoice', 'Академкнига'],
        ];
    }

    #[DataProvider('matchingFilters')]
    public function test_each_advanced_attribute_matches_the_copy(string $filter, string $value): void
    {
        $this->bootAdvancedSearchSchema();
        $this->seedCopy();

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.copies.index', [$filter => $value]))
            ->assertOk()
            ->assertSee('INV-MARC-1');
    }

    public function test_a_non_matching_attribute_excludes_the_copy(): void
    {
        $this->bootAdvancedSearchSchema();
        $this->seedCopy();

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.copies.index', ['faculty' => 'несуществующий факультет']))
            ->assertOk()
            ->assertDontSee('INV-MARC-1');
    }
}
