<?php

namespace Tests\Feature;

use Tests\TestCase;

class BookPageTest extends TestCase
{
    public function test_book_page_renders_successfully(): void
    {
        $response = $this->get('/book/978-5-358-09150-5');

        $response
            ->assertOk()
            ->assertSee('Просмотр книги', false)
            ->assertSee('/api/v1/book-db/', false);
    }

    public function test_book_page_uses_real_api_data_fields(): void
    {
        $response = $this->get('/book/test-isbn');

        $response
            ->assertOk()
            ->assertSee('availability', false)
            ->assertSee('locations', false)
            ->assertSee('authors', false)
            ->assertSee('needsReview', false)
            ->assertSee('reviewReasonCodes', false)
            ->assertSee('institutionUnit', false)
            ->assertSee('servicePoint', false);
    }

    public function test_book_page_has_no_fake_content(): void
    {
        $response = $this->get('/book/test-isbn');
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

    public function test_book_page_shows_locations_table(): void
    {
        $response = $this->get('/book/test-isbn');

        $response
            ->assertOk()
            ->assertSee('locations-table', false)
            ->assertSee('Наличие по пунктам выдачи', false)
            ->assertSee('Подразделение', false)
            ->assertSee('Кампус', false)
            ->assertSee('Пункт выдачи', false);
    }

    public function test_book_page_has_catalog_back_link(): void
    {
        $response = $this->get('/book/test-isbn');

        $response
            ->assertOk()
            ->assertSee('href="/catalog"', false)
            ->assertSee('Вернуться в каталог', false);
    }

    public function test_book_page_has_exported_detail_structure_and_real_ctas(): void
    {
        $response = $this->get('/book/test-isbn');

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
        $response = $this->get('/book/test-isbn?lang=en');

        $response
            ->assertOk()
            ->assertSee('Back to Catalog')
            ->assertSee('Abstract')
            ->assertSee('/catalog?lang=en', false);
    }
}
