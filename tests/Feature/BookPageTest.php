<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class BookPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    private const KNOWN_ISBN = '978-5-358-09150-5';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        $record = BibliographicRecord::factory()->create([
            'title' => 'Verified public detail fixture',
            'primary_author' => 'Public Test Author',
            'isbn' => self::KNOWN_ISBN,
            'language' => 'ru',
            'is_draft' => false,
        ]);
        BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'available',
        ]);
    }

    public function test_book_page_renders_successfully(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN);

        $response
            ->assertOk()
            ->assertSee('Кітапты қарау', false)
            ->assertSee('/api/v1/book-db/', false);
    }

    public function test_book_page_uses_public_api_fields_without_internal_quality_requirements(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN.'?lang=ru');

        $response
            ->assertOk()
            ->assertSee('availability', false)
            ->assertSee('locations', false)
            ->assertSee('authors', false)
            ->assertSee('viewerAuthenticated', false)
            ->assertSee('formatLocationLabel', false)
            ->assertDontSee('needsReview', false)
            ->assertDontSee('reviewReasonCodes', false);
    }

    public function test_book_page_has_no_fake_content(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN);
        $content = $response->getContent();

        $response->assertOk();

        // No hardcoded generic description
        $this->assertStringNotContainsString('Это издание представляет собой ценный ресурс', $content);
        // No fake format claim
        $this->assertStringNotContainsString('Печатная + электронная', $content);
        // No fake loan period
        $this->assertStringNotContainsString('14 дней', $content);
        // No fake e-version references
        $this->assertStringNotContainsString('электронная версия для зарегистрированных', $content);
        // No non-functional mini-actions
        $this->assertStringNotContainsString('PDF версия', $content);
        // No hardcoded genre badge
        $this->assertStringNotContainsString('Учебное издание', $content);
        // No fake default location
        $this->assertStringNotContainsString('Основной фонд, зал №1', $content);
    }

    public function test_book_page_shows_privacy_aware_locations_table_with_totals(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN.'?lang=ru');

        $response
            ->assertOk()
            ->assertSee('locations-table', false)
            ->assertSee('Наличие по пунктам выдачи', false)
            ->assertSee('Локация', false)
            ->assertSee('Всего экземпляров', false)
            ->assertSee('Доступно сейчас', false)
            ->assertSee('Выдано', false)
            ->assertSee('viewerAuthenticated', false)
            ->assertSee('data-exact-location', false);
    }

    public function test_book_page_has_catalog_back_link(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN.'?lang=ru');

        $response
            ->assertOk()
            ->assertSee('href="/catalog?lang=ru"', false)
            ->assertSee('Вернуться в каталог', false);
    }

    public function test_book_page_has_exported_detail_structure_and_real_ctas(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN);

        $response
            ->assertOk()
            ->assertSee('id="book-detail-page"', false)
            ->assertSee('id="detail-bibliographic-grid"', false)
            ->assertSee('id="detail-metadata-panel"', false)
            ->assertSee('id="detail-availability-summary"', false)
            ->assertSee('id="detail-abstract"', false)
            ->assertSee('id="detail-actions"', false)
            ->assertSee('data-detail-cover', false)
            ->assertSee('digital-materials-slot', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_book_page_supports_locale_specific_detail_copy(): void
    {
        $response = $this->get('/book/'.self::KNOWN_ISBN.'?lang=en');

        $response
            ->assertOk()
            ->assertSee('Back to Catalog')
            ->assertSee('Abstract')
            ->assertSee('/catalog?lang=en', false);
    }

    public function test_unknown_book_returns_not_found_instead_of_a_synthetic_detail(): void
    {
        $this->get('/book/test-isbn?lang=en')
            ->assertNotFound()
            ->assertDontSee('id="book-detail-page"', false);
    }
}
