<?php

namespace Tests\Feature\Api;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Library\DigitalReadingProgress;
use Database\Seeders\Support\DemoPdfBuilder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * End-to-end cover for reading library-held material: what opens in the
 * controlled viewer, who may stream or download the bytes, and where a reader's
 * position is kept.
 */
class DigitalReadingTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Storage::fake('local');
    }

    private function makeMaterial(array $overrides = []): ElectronicMaterial
    {
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);
        $path = 'electronic-materials/test-'.$record->getKey().'.pdf';

        $contents = (new DemoPdfBuilder)->build(
            'Test material',
            DemoPdfBuilder::samplePages('Test material', 6),
        );

        Storage::disk('local')->put($path, $contents);

        return ElectronicMaterial::query()->create(array_merge([
            'bibliographic_record_id' => $record->getKey(),
            'title' => 'Электронная версия',
            'file_path' => $path,
            'file_type' => 'pdf',
            'file_size' => strlen($contents),
            'access_level' => 'public',
            'allow_download' => false,
            'is_active' => true,
        ], $overrides));
    }

    private function asReader(string $id = 'reader-1'): static
    {
        return $this->withSession(['library.user' => [
            'id' => $id,
            'name' => 'Тест Тестов',
            'email' => 'reader@example.test',
            'role' => 'reader',
        ]]);
    }

    // ── Listing ───────────────────────────────────────────────────

    public function test_locally_held_material_is_offered_through_the_controlled_viewer_not_the_raw_stream(): void
    {
        $material = $this->makeMaterial();

        $response = $this->getJson(
            "/api/v1/documents/{$material->bibliographic_record_id}/digital-materials"
        );

        $response->assertOk();
        $item = $response->json('data.0');

        $this->assertTrue($item['canAccess']);
        $this->assertSame("/digital-viewer/{$material->getKey()}", $item['viewerUrl']);
        $this->assertFalse($item['isExternal']);
    }

    public function test_externally_hosted_material_links_to_its_source(): void
    {
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $record->getKey(),
            'title' => 'Внешний ресурс',
            'external_url' => 'https://example.org/full-text.pdf',
            'file_type' => 'pdf',
            'access_level' => 'public',
            'is_active' => true,
        ]);

        $item = $this->getJson("/api/v1/documents/{$record->getKey()}/digital-materials")
            ->assertOk()
            ->json('data.0');

        $this->assertTrue($item['isExternal']);
        $this->assertSame('https://example.org/full-text.pdf', $item['viewerUrl']);
    }

    public function test_guest_is_told_why_authenticated_material_is_closed(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $item = $this->getJson("/api/v1/documents/{$material->bibliographic_record_id}/digital-materials")
            ->assertOk()
            ->json('data.0');

        $this->assertFalse($item['canAccess']);
        $this->assertNull($item['viewerUrl']);
        $this->assertNotEmpty($item['accessDeniedReason']);
    }

    public function test_signed_in_reader_may_open_authenticated_material(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $item = $this->asReader()
            ->getJson("/api/v1/documents/{$material->bibliographic_record_id}/digital-materials")
            ->assertOk()
            ->json('data.0');

        $this->assertTrue($item['canAccess']);
        $this->assertSame("/digital-viewer/{$material->getKey()}", $item['viewerUrl']);
    }

    public function test_restricted_material_stays_closed_to_a_plain_reader_but_opens_for_digital_staff(): void
    {
        $material = $this->makeMaterial(['access_level' => 'restricted']);
        $url = "/api/v1/documents/{$material->bibliographic_record_id}/digital-materials";

        $asReader = $this->asReader()->getJson($url)->assertOk()->json('data.0');
        $this->assertFalse($asReader['canAccess']);

        $staff = $this->makeControlPlaneUser('librarian');
        $staff->givePermissionTo('digital.upload');

        $asStaff = $this->actingAs($staff)->getJson($url)->assertOk()->json('data.0');
        $this->assertTrue($asStaff['canAccess']);
    }

    public function test_campus_material_is_closed_when_no_campus_range_is_configured(): void
    {
        config(['digital_access.campus_ranges' => []]);
        $material = $this->makeMaterial(['access_level' => 'campus']);

        $item = $this->asReader()
            ->getJson("/api/v1/documents/{$material->bibliographic_record_id}/digital-materials")
            ->assertOk()
            ->json('data.0');

        $this->assertFalse(
            $item['canAccess'],
            'An unconfigured campus range must fail closed, not open the licence to everyone.'
        );
    }

    public function test_campus_material_opens_from_a_configured_campus_address(): void
    {
        config(['digital_access.campus_ranges' => ['127.0.0.0/8']]);
        $material = $this->makeMaterial(['access_level' => 'campus']);

        $item = $this->asReader()
            ->getJson("/api/v1/documents/{$material->bibliographic_record_id}/digital-materials")
            ->assertOk()
            ->json('data.0');

        $this->assertTrue($item['canAccess']);
    }

    // ── Streaming ─────────────────────────────────────────────────

    public function test_stream_serves_the_pdf_inline_and_advertises_range_support(): void
    {
        $material = $this->makeMaterial();

        $response = $this->get("/api/v1/digital-materials/{$material->getKey()}/stream");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_stream_response_is_not_cacheable_by_shared_caches(): void
    {
        $material = $this->makeMaterial();

        $cacheControl = (string) $this->get("/api/v1/digital-materials/{$material->getKey()}/stream")
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_stream_honours_a_range_request(): void
    {
        $material = $this->makeMaterial();

        $response = $this->get(
            "/api/v1/digital-materials/{$material->getKey()}/stream",
            ['Range' => 'bytes=0-7'],
        );

        $response->assertStatus(206);
        $this->assertSame('%PDF-1.4', $response->streamedContent());
    }

    public function test_stream_is_refused_without_access(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $this->getJson("/api/v1/digital-materials/{$material->getKey()}/stream")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_stream_of_an_inactive_material_is_not_found(): void
    {
        $material = $this->makeMaterial(['is_active' => false]);

        $this->getJson("/api/v1/digital-materials/{$material->getKey()}/stream")
            ->assertStatus(404);
    }

    public function test_stream_reports_not_found_when_the_row_exists_but_the_file_does_not(): void
    {
        $material = $this->makeMaterial();
        Storage::disk('local')->delete($material->file_path);

        $this->getJson("/api/v1/digital-materials/{$material->getKey()}/stream")
            ->assertStatus(404);
    }

    // ── Downloading ───────────────────────────────────────────────

    public function test_download_is_refused_when_the_licence_forbids_it(): void
    {
        $material = $this->makeMaterial(['allow_download' => false]);

        $this->getJson("/api/v1/digital-materials/{$material->getKey()}/download")
            ->assertStatus(403)
            ->assertJsonPath('error', __('ui.digital.denied_download'));
    }

    public function test_download_is_allowed_when_the_licence_permits_it(): void
    {
        $material = $this->makeMaterial(['allow_download' => true]);

        $response = $this->get("/api/v1/digital-materials/{$material->getKey()}/download");

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_download_url_is_only_published_when_the_licence_permits_it(): void
    {
        $closed = $this->makeMaterial(['allow_download' => false]);
        $open = $this->makeMaterial(['allow_download' => true]);

        $this->assertNull(
            $this->getJson("/api/v1/documents/{$closed->bibliographic_record_id}/digital-materials")
                ->json('data.0.downloadUrl')
        );

        $this->assertSame(
            "/api/v1/digital-materials/{$open->getKey()}/download",
            $this->getJson("/api/v1/documents/{$open->bibliographic_record_id}/digital-materials")
                ->json('data.0.downloadUrl')
        );
    }

    public function test_download_of_material_the_reader_cannot_read_is_refused_before_the_licence_check(): void
    {
        $material = $this->makeMaterial([
            'access_level' => 'authenticated',
            'allow_download' => true,
        ]);

        $this->getJson("/api/v1/digital-materials/{$material->getKey()}/download")
            ->assertStatus(403)
            ->assertJsonPath('error', __('ui.digital.denied_authenticated'));
    }

    // ── Reading position ──────────────────────────────────────────

    public function test_reader_position_is_stored_and_returned(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $this->asReader()
            ->putJson("/api/v1/digital-materials/{$material->getKey()}/progress", [
                'page' => 4,
                'totalPages' => 6,
                'zoom' => 'fit',
            ])
            ->assertOk()
            ->assertJsonPath('data.stored', true);

        $this->assertDatabaseHas('digital_reading_progress', [
            'user_id' => 'reader-1',
            'material_ref' => 'em:'.$material->getKey(),
            'page' => 4,
            'total_pages' => 6,
        ]);

        $this->asReader()
            ->getJson("/api/v1/digital-materials/{$material->getKey()}/progress")
            ->assertOk()
            ->assertJsonPath('data.page', 4)
            ->assertJsonPath('data.totalPages', 6);
    }

    public function test_reader_position_is_overwritten_rather_than_appended(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);
        $url = "/api/v1/digital-materials/{$material->getKey()}/progress";

        $this->asReader()->putJson($url, ['page' => 2])->assertOk();
        $this->asReader()->putJson($url, ['page' => 5])->assertOk();

        $this->assertSame(1, DigitalReadingProgress::query()->count());
        $this->assertSame(5, DigitalReadingProgress::query()->first()->page);
    }

    public function test_positions_of_different_readers_do_not_collide(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);
        $url = "/api/v1/digital-materials/{$material->getKey()}/progress";

        $this->asReader('reader-a')->putJson($url, ['page' => 2])->assertOk();
        $this->asReader('reader-b')->putJson($url, ['page' => 6])->assertOk();

        $this->assertSame(2, DigitalReadingProgress::query()->count());
        $this->assertSame(
            2,
            $this->asReader('reader-a')->getJson($url)->json('data.page'),
        );
        $this->assertSame(
            6,
            $this->asReader('reader-b')->getJson($url)->json('data.page'),
        );
    }

    public function test_guest_reading_public_material_is_told_no_position_was_stored(): void
    {
        $material = $this->makeMaterial(['access_level' => 'public']);

        $this->putJson("/api/v1/digital-materials/{$material->getKey()}/progress", ['page' => 3])
            ->assertOk()
            ->assertJsonPath('data.stored', false);

        $this->assertSame(0, DigitalReadingProgress::query()->count());
    }

    public function test_position_cannot_be_written_for_material_the_reader_cannot_read(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $this->putJson("/api/v1/digital-materials/{$material->getKey()}/progress", ['page' => 3])
            ->assertStatus(403);

        $this->assertSame(0, DigitalReadingProgress::query()->count());
    }

    public function test_position_page_is_validated(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $this->asReader()
            ->putJson("/api/v1/digital-materials/{$material->getKey()}/progress", ['page' => 0])
            ->assertStatus(422);
    }

    // ── Viewer page ───────────────────────────────────────────────

    public function test_viewer_page_boots_the_reader_for_readable_material(): void
    {
        $material = $this->makeMaterial();

        $response = $this->get("/digital-viewer/{$material->getKey()}");

        $response->assertOk();
        $response->assertSee('viewer-root', false);
        $response->assertSee('viewer-canvas', false);
        // The reader must be driven by the bundled pdf.js, not an external CDN.
        $response->assertSee('/vendor/pdfjs/build/pdf.min.mjs', false);
        // The stream URL reaches the page inside a @json blob, so slashes arrive escaped.
        $response->assertSee(
            trim((string) json_encode("/api/v1/digital-materials/{$material->getKey()}/stream"), '"'),
            false,
        );
    }

    public function test_viewer_page_refuses_material_the_reader_may_not_read(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        $response = $this->get("/digital-viewer/{$material->getKey()}");

        $response->assertOk();
        $response->assertSee('viewer-denied', false);
        $response->assertSee(__('ui.digital.denied_authenticated'), false);
        // No reader is booted, so the stream endpoint is never even named.
        $response->assertDontSee('/vendor/pdfjs/build/pdf.min.mjs', false);
    }

    public function test_viewer_page_reports_an_unknown_material(): void
    {
        $response = $this->get('/digital-viewer/999999');

        $response->assertOk();
        $response->assertSee(__('ui.digital.not_found_title'), false);
    }

    public function test_viewer_page_resumes_from_the_stored_position(): void
    {
        $material = $this->makeMaterial(['access_level' => 'authenticated']);

        DigitalReadingProgress::query()->create([
            'user_id' => 'reader-1',
            'material_ref' => 'em:'.$material->getKey(),
            'page' => 4,
            'total_pages' => 6,
        ]);

        $response = $this->asReader()->get("/digital-viewer/{$material->getKey()}");

        $response->assertOk();
        $response->assertSee(__('ui.digital.resume_hint', ['page' => 4]), false);
        $response->assertSee('"resumePage":4', false);
    }

    public function test_viewer_page_sends_an_externally_hosted_material_to_its_source(): void
    {
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);
        $material = ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $record->getKey(),
            'title' => 'Внешний ресурс',
            'external_url' => 'https://example.org/full-text.pdf',
            'file_type' => 'pdf',
            'access_level' => 'public',
            'is_active' => true,
        ]);

        $response = $this->get("/digital-viewer/{$material->getKey()}");

        $response->assertOk();
        $response->assertSee(__('ui.digital.external_title'), false);
        $response->assertSee('https://example.org/full-text.pdf', false);
    }

    public function test_viewer_page_offers_a_download_only_when_the_licence_permits_it(): void
    {
        $closed = $this->makeMaterial(['allow_download' => false]);
        $open = $this->makeMaterial(['allow_download' => true]);

        $this->get("/digital-viewer/{$closed->getKey()}")
            ->assertOk()
            ->assertDontSee("/api/v1/digital-materials/{$closed->getKey()}/download", false);

        $this->get("/digital-viewer/{$open->getKey()}")
            ->assertOk()
            ->assertSee("/api/v1/digital-materials/{$open->getKey()}/download", false);
    }
}
