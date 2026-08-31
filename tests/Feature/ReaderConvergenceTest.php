<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReaderConvergenceTest extends TestCase
{
    public function test_reader_page_loads_with_isbn(): void
    {
        $response = $this->get('/book/978-601-7659-00-0/read');

        $response->assertOk();
        $response->assertSee('Читалка');
    }

    public function test_reader_page_uses_canonical_api_endpoint(): void
    {
        $response = $this->get('/book/978-601-7659-00-0/read');

        $response->assertOk();
        $content = $response->getContent();

        // Reader should use canonical /api/v1/book-db/ as primary source
        $this->assertStringContainsString('/api/v1/book-db/', $content);
    }

    public function test_reader_page_has_fallback_to_external_proxy(): void
    {
        $response = $this->get('/book/978-601-7659-00-0/read');

        $response->assertOk();
        $content = $response->getContent();

        // Fallback to external proxy should still exist for compatibility
        $this->assertStringContainsString('catalog-external', $content);
        $this->assertStringContainsString('falling back to external proxy', $content);
    }

    public function test_reader_page_normalizes_publication_year(): void
    {
        $response = $this->get('/book/978-601-7659-00-0/read');

        $response->assertOk();
        $content = $response->getContent();

        // Reader should normalize publicationYear → year for canonical API
        $this->assertStringContainsString('publicationYear', $content);
    }

    public function test_external_catalog_proxy_never_exposes_transport_details(): void
    {
        Http::fake(static function (): never {
            throw new ConnectionException('connect failed: internal-catalog.service:5173 secret-route');
        });

        $response = $this->getJson('/api/v1/catalog-external?lang=en')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('message');

        $this->assertStringNotContainsString('internal-catalog.service', $response->getContent());
        $this->assertStringNotContainsString('secret-route', $response->getContent());
    }
}
