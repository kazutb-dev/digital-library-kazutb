<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitalMaterialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function withAuthSession(array $overrides = []): static
    {
        $defaults = [
            'id' => 'test-user-1',
            'name' => 'Тест Тестов',
            'email' => 'test@digital-library.test',
            'role' => 'reader',
        ];

        return $this->withSession(['library.user' => array_merge($defaults, $overrides)]);
    }

    private function canUseLivePgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function seedTestMaterial(string $accessLevel = 'authenticated', bool $isActive = true): string
    {
        // Find a real document
        $doc = DB::table('app.documents')->select('id')->first();

        if (! $doc) {
            $this->markTestSkipped('No documents in app.documents');
        }

        // Ensure sample file exists
        $disk = Storage::disk('local');
        $path = 'digital-materials/test-sample.pdf';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('digital-materials');
            $disk->put($path, '%PDF-1.4 test content');
        }

        $materialId = Str::uuid()->toString();

        DB::table('app.digital_materials')->insert([
            'id' => $materialId,
            'document_id' => $doc->id,
            'title' => 'Test Material',
            'file_type' => 'pdf',
            'storage_disk' => 'local',
            'storage_path' => $path,
            'original_filename' => 'test.pdf',
            'file_size_bytes' => $disk->size($path),
            'access_level' => $accessLevel,
            'allow_download' => false,
            'is_active' => $isActive,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $materialId;
    }

    // ═══════════════════════════════════════════════════════════
    // 1. Viewer page renders
    // ═══════════════════════════════════════════════════════════

    public function test_viewer_page_renders(): void
    {
        $response = $this->get('/digital-viewer/some-fake-id');
        $response->assertOk();
        $response->assertSee('viewer-root', false);
        $response->assertSee('initViewer', false);
    }

    public function test_viewer_page_passes_material_id(): void
    {
        $response = $this->get('/digital-viewer/test-uuid-123');
        $response->assertOk();
        $response->assertSee('test-uuid-123', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 2. Book page has digital materials slot
    // ═══════════════════════════════════════════════════════════

    public function test_book_page_has_digital_materials_slot(): void
    {
        $response = $this->get('/book/9781471573880');
        $response->assertOk();
        $response->assertSee('digital-materials-slot', false);
        $response->assertSee('loadDigitalMaterials', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 3. API - list digital materials for document
    // ═══════════════════════════════════════════════════════════

    public function test_digital_materials_api_returns_empty_for_unknown_doc(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $response = $this->getJson('/api/v1/documents/00000000-0000-0000-0000-000000000000/digital-materials');
        $response->assertOk();
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('data', []);
    }

    public function test_digital_materials_api_returns_materials_for_auth_user(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $materialId = $this->seedTestMaterial('authenticated');
        $doc = DB::table('app.digital_materials')->where('id', $materialId)->first();

        try {
            $response = $this->withAuthSession()
                ->getJson("/api/v1/documents/{$doc->document_id}/digital-materials");

            $response->assertOk();
            $response->assertJsonPath('meta.total', fn ($v) => $v >= 1);

            $found = collect($response->json('data'))->firstWhere('id', $materialId);
            $this->assertNotNull($found);
            $this->assertTrue($found['canAccess']);
            $this->assertNotNull($found['viewerUrl']);
        } finally {
            DB::table('app.digital_materials')->where('id', $materialId)->delete();
        }
    }

    public function test_digital_materials_api_denies_guest_for_auth_level(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $materialId = $this->seedTestMaterial('authenticated');
        $doc = DB::table('app.digital_materials')->where('id', $materialId)->first();

        try {
            $response = $this->getJson("/api/v1/documents/{$doc->document_id}/digital-materials");
            $response->assertOk();

            $found = collect($response->json('data'))->firstWhere('id', $materialId);
            $this->assertNotNull($found);
            $this->assertFalse($found['canAccess']);
            $this->assertNull($found['viewerUrl']);
            $this->assertNotEmpty($found['accessDeniedReason']);
        } finally {
            DB::table('app.digital_materials')->where('id', $materialId)->delete();
        }
    }

    public function test_digital_materials_api_allows_guest_for_open_level(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $materialId = $this->seedTestMaterial('open');
        $doc = DB::table('app.digital_materials')->where('id', $materialId)->first();

        try {
            $response = $this->getJson("/api/v1/documents/{$doc->document_id}/digital-materials");
            $response->assertOk();

            $found = collect($response->json('data'))->firstWhere('id', $materialId);
            $this->assertNotNull($found);
            $this->assertTrue($found['canAccess']);
        } finally {
            DB::table('app.digital_materials')->where('id', $materialId)->delete();
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 4. Stream endpoint access control
    // ═══════════════════════════════════════════════════════════

    public function test_stream_returns_404_for_nonexistent_material(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $response = $this->getJson('/api/v1/digital-materials/00000000-0000-0000-0000-000000000000/stream');
        $response->assertStatus(404);
    }

    public function test_stream_returns_403_for_unauthenticated_user(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $materialId = $this->seedTestMaterial('authenticated');

        try {
            $response = $this->getJson("/api/v1/digital-materials/{$materialId}/stream");
            $response->assertStatus(403);
            $response->assertJsonPath('success', false);
        } finally {
            DB::table('app.digital_materials')->where('id', $materialId)->delete();
        }
    }

    public function test_stream_returns_pdf_for_authenticated_user(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $materialId = $this->seedTestMaterial('authenticated');

        try {
            $response = $this->withAuthSession()
                ->get("/api/v1/digital-materials/{$materialId}/stream");

            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        } finally {
            DB::table('app.digital_materials')->where('id', $materialId)->delete();
        }
    }

    public function test_stream_inactive_material_returns_404(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('PostgreSQL not available');
        }

        $materialId = $this->seedTestMaterial('open', false);

        try {
            $response = $this->getJson("/api/v1/digital-materials/{$materialId}/stream");
            $response->assertStatus(404);
        } finally {
            DB::table('app.digital_materials')->where('id', $materialId)->delete();
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 5. No regressions
    // ═══════════════════════════════════════════════════════════

    public function test_book_page_still_renders(): void
    {
        $response = $this->get('/book/9781471573880');
        $response->assertOk();
    }

    public function test_catalog_still_renders(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
    }

    public function test_account_still_works(): void
    {
        $response = $this->withAuthSession()->get('/account');
        $response->assertOk();
    }
}
