<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookDetailDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_book_db_endpoint_returns_existing_book(): void
    {
        $isbn = $this->firstExistingIsbn();

        $response = $this->getJson('/api/v1/book-db/' . urlencode($isbn));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title' => ['display', 'raw', 'subtitle'],
                    'primaryAuthor',
                    'authors',
                    'publisher' => ['name'],
                    'publicationYear',
                    'language' => ['code', 'raw'],
                    'isbn' => ['raw', 'isValid'],
                    'copies' => ['available', 'total'],
                    'availability' => ['isAvailable', 'availableCopies', 'totalCopies', 'locations'],
                    'quality' => ['needsReview', 'reviewReasonCodes'],
                    'udc' => ['raw', 'source'],
                    'source',
                ],
            ]);

        $this->assertSame($isbn, $response->json('data.isbn.raw'));
        $this->assertIsArray($response->json('data.availability.locations'));
    }

    public function test_book_db_endpoint_returns_404_for_missing_book(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only endpoint test.');
        }

        $response = $this->getJson('/api/v1/book-db/does-not-exist-999999');

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Book not found');
    }

    private function firstExistingIsbn(): string
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only endpoint test.');
        }

        $isbn = DB::table('app.document_detail_v')
            ->whereNotNull('isbn_normalized')
            ->value('isbn_normalized');

        if (! is_string($isbn) || $isbn === '') {
            $this->markTestSkipped('No ISBN found in app.document_detail_v for endpoint test.');
        }

        return $isbn;
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
}
