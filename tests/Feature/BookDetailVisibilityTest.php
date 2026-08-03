<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\Loan;
use App\Models\Catalog\UdcCode;
use App\Services\Library\BookDetailReadService;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class BookDetailVisibilityTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guest_receives_only_general_fund_while_reader_receives_exact_location_and_own_due_date(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $record = BibliographicRecord::factory()->create([
            'notes' => 'INTERNAL-NOTE-MUST-STAY-HIDDEN',
            'is_draft' => false,
        ]);
        $copy = BookCopy::factory()->issued()->create([
            'bibliographic_record_id' => $record->getKey(),
            'shelf_location' => 'SECRET-SHELF-7B',
            'storage_sigla' => 'SECRET-SIGLA-NB',
        ]);
        $loan = Loan::query()->create([
            'user_id' => $reader->getKey(),
            'copy_id' => $copy->getKey(),
            'status' => 'active',
            'issued_at' => now()->subWeek(),
            'due_at' => now()->addWeek(),
        ]);

        $service = app(BookDetailReadService::class);
        $guest = $service->findByIdentifier((string) $record->getKey());
        $authenticated = $service->findByIdentifier((string) $record->getKey(), $reader);

        $this->assertFalse($guest['viewer']['authenticated']);
        $this->assertSame('', $guest['availability']['locations'][0]['campus']['name']);
        $this->assertSame('', $guest['availability']['locations'][0]['servicePoint']['name']);
        $this->assertSame('', $guest['availability']['locations'][0]['storageSigla']);
        $this->assertSame('', $guest['availability']['locations'][0]['shelf']);
        $this->assertSame('', $guest['notes']);
        $this->assertNull($guest['viewer']['activeLoan']);

        $this->assertTrue($authenticated['viewer']['authenticated']);
        $this->assertSame('SECRET-SIGLA-NB', $authenticated['availability']['locations'][0]['storageSigla']);
        $this->assertSame('SECRET-SHELF-7B', $authenticated['availability']['locations'][0]['shelf']);
        $this->assertSame($loan->due_at->toIso8601String(), $authenticated['viewer']['activeLoan']['dueAt']);
        $this->assertSame('', $authenticated['notes'], 'Internal notes are not reader-visible.');
    }

    public function test_book_page_embeds_exact_location_and_due_date_only_for_authenticated_reader(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);
        $copy = BookCopy::factory()->issued()->create([
            'bibliographic_record_id' => $record->getKey(),
            'shelf_location' => 'AUTH-ONLY-SHELF-42',
        ]);
        Loan::query()->create([
            'user_id' => $reader->getKey(),
            'copy_id' => $copy->getKey(),
            'status' => 'active',
            'issued_at' => now()->subDay(),
            'due_at' => now()->addDays(10),
        ]);

        $this->get('/book/'.$record->getKey())
            ->assertOk()
            ->assertDontSee('AUTH-ONLY-SHELF-42')
            ->assertDontSee($copy->barcode);

        $this->signInToLibraryAs($reader)
            ->get('/book/'.$record->getKey())
            ->assertOk()
            ->assertSee('AUTH-ONLY-SHELF-42')
            ->assertDontSee($copy->inventory_number)
            ->assertDontSee($copy->barcode)
            ->assertSee('data-reader-due-date', false)
            ->assertSee('data-exact-location', false);
    }

    public function test_reader_receives_an_explicit_pending_marker_when_available_copy_has_no_shelf(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);
        BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'available',
            'shelf_location' => null,
        ]);

        $service = app(BookDetailReadService::class);
        $guest = $service->findByIdentifier((string) $record->getKey());
        $authenticated = $service->findByIdentifier((string) $record->getKey(), $reader);

        $this->assertFalse($guest['availability']['locations'][0]['exactLocationPending']);
        $this->assertSame('', $guest['availability']['locations'][0]['shelf']);
        $this->assertTrue($authenticated['availability']['locations'][0]['exactLocationPending']);
        $this->assertSame('', $authenticated['availability']['locations'][0]['shelf']);
    }

    public function test_numeric_catalog_record_uses_canonical_electronic_materials_instead_of_legacy_uuid_table(): void
    {
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $record->getKey(),
            'title' => 'Licensed online edition',
            'external_url' => 'https://example.test/library/item',
            'file_type' => 'pdf',
            'access_level' => 'public',
            'license_terms' => 'University subscription',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/documents/'.$record->getKey().'/digital-materials')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.licenseTerms', 'University subscription')
            ->assertJsonPath('data.0.viewerUrl', 'https://example.test/library/item');
    }

    public function test_guest_sees_udc_description_while_reader_sees_full_code_and_description(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        UdcCode::query()->updateOrCreate(['code' => '004'], [
            'description' => 'Информационные технологии',
            'is_verified' => true,
        ]);
        $record = BibliographicRecord::factory()->create([
            'udc_code' => '004.8',
            'is_draft' => false,
        ]);

        $service = app(BookDetailReadService::class);
        $guest = $service->findByIdentifier((string) $record->getKey());
        $authenticated = $service->findByIdentifier((string) $record->getKey(), $reader);

        $this->assertSame('', $guest['udc']['raw']);
        $this->assertSame('Искусственный интеллект', $guest['udc']['display']);
        $this->assertSame('004.8', $authenticated['udc']['raw']);
        $this->assertSame('004.8 — Искусственный интеллект', $authenticated['udc']['display']);
    }

    public function test_detail_recommends_other_materials_in_the_same_udc_section(): void
    {
        $record = BibliographicRecord::factory()->create(['udc_code' => '004.8']);
        $similar = BibliographicRecord::factory()->create(['udc_code' => '004.8', 'title' => 'Похожая книга']);
        BibliographicRecord::factory()->create(['udc_code' => '33', 'title' => 'Другая тема']);

        $payload = app(BookDetailReadService::class)->findByIdentifier((string) $record->getKey());

        $this->assertContains((string) $similar->getKey(), array_column($payload['similarMaterials'], 'id'));
        $this->assertNotContains('Другая тема', array_column($payload['similarMaterials'], 'title'));
    }
}
