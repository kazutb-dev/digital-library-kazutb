<?php

namespace Tests\Unit\Services;

use App\Services\BibliographyFormatter;
use PHPUnit\Framework\TestCase;

class BibliographyFormatterTest extends TestCase
{
    private BibliographyFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BibliographyFormatter();
    }

    public function test_empty_items_return_empty_text(): void
    {
        $result = $this->formatter->format([], 'numbered');

        $this->assertSame('', $result['text']);
        $this->assertSame(0, $result['count']);
        $this->assertSame('numbered', $result['format']);
    }

    public function test_numbered_format_single_book(): void
    {
        $items = [
            [
                'title' => 'Алгоритмы',
                'type' => 'book',
                'author' => 'Иванов А.',
                'publisher' => 'Наука',
                'year' => '2023',
                'isbn' => '978-5-000-00001-0',
            ],
        ];

        $result = $this->formatter->format($items, 'numbered');

        $this->assertSame('numbered', $result['format']);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('1.', $result['text']);
        $this->assertStringContainsString('Иванов А.', $result['text']);
        $this->assertStringContainsString('Алгоритмы', $result['text']);
        $this->assertStringContainsString('Наука, 2023', $result['text']);
        $this->assertStringContainsString('ISBN 978-5-000-00001-0', $result['text']);
    }

    public function test_numbered_format_external_resource(): void
    {
        $items = [
            [
                'title' => 'IPR SMART',
                'type' => 'external_resource',
                'provider' => 'IPR Media',
                'url' => 'https://example.com',
            ],
        ];

        $result = $this->formatter->format($items, 'numbered');

        $this->assertStringContainsString('[Электронный ресурс]', $result['text']);
        $this->assertStringContainsString('IPR SMART', $result['text']);
        $this->assertStringContainsString('IPR Media', $result['text']);
        $this->assertStringContainsString('URL: https://example.com', $result['text']);
    }

    public function test_grouped_format_separates_types(): void
    {
        $items = [
            ['title' => 'Книга 1', 'type' => 'book', 'author' => 'Автор'],
            ['title' => 'Ресурс 1', 'type' => 'external_resource', 'provider' => 'Test'],
        ];

        $result = $this->formatter->format($items, 'grouped');

        $this->assertSame('grouped', $result['format']);
        $this->assertSame(2, $result['count']);
        $this->assertSame(1, $result['sections']['books']);
        $this->assertSame(1, $result['sections']['external']);
        $this->assertStringContainsString('Основная литература', $result['text']);
        $this->assertStringContainsString('Электронные ресурсы', $result['text']);
    }

    public function test_grouped_format_books_only(): void
    {
        $items = [
            ['title' => 'Книга 1', 'type' => 'book'],
        ];

        $result = $this->formatter->format($items, 'grouped');

        $this->assertStringContainsString('Основная литература', $result['text']);
        $this->assertStringNotContainsString('Электронные ресурсы', $result['text']);
        $this->assertArrayNotHasKey('external', $result['sections']);
    }

    public function test_syllabus_format_has_header_and_sections(): void
    {
        $items = [
            [
                'title' => 'Алгоритмы',
                'type' => 'book',
                'author' => 'Иванов А.',
                'language' => 'ru',
            ],
            [
                'title' => 'IPR SMART',
                'type' => 'external_resource',
                'access_type' => 'campus',
            ],
        ];

        $result = $this->formatter->format($items, 'syllabus');

        $this->assertSame('syllabus', $result['format']);
        $this->assertStringContainsString('СПИСОК ЛИТЕРАТУРЫ', $result['text']);
        $this->assertStringContainsString('Основная литература:', $result['text']);
        $this->assertStringContainsString('Электронные ресурсы:', $result['text']);
        $this->assertStringContainsString('рус.', $result['text']);
        $this->assertStringContainsString('доступ из кампуса', $result['text']);
    }

    public function test_syllabus_format_language_labels(): void
    {
        $items = [
            ['title' => 'English Book', 'type' => 'book', 'language' => 'en'],
            ['title' => 'Казахская книга', 'type' => 'book', 'language' => 'kz'],
        ];

        $result = $this->formatter->format($items, 'syllabus');

        $this->assertStringContainsString('англ.', $result['text']);
        $this->assertStringContainsString('каз.', $result['text']);
    }

    public function test_invalid_format_falls_back_to_numbered(): void
    {
        $items = [
            ['title' => 'Test', 'type' => 'book'],
        ];

        $result = $this->formatter->format($items, 'unknown');

        $this->assertSame('numbered', $result['format']);
    }

    public function test_missing_optional_fields_handled_gracefully(): void
    {
        $items = [
            ['title' => 'Minimal Book'],
        ];

        $result = $this->formatter->format($items, 'numbered');

        $this->assertStringContainsString('Minimal Book', $result['text']);
        $this->assertSame(1, $result['count']);
    }

    public function test_book_entry_without_title_uses_fallback(): void
    {
        $items = [
            ['type' => 'book'],
        ];

        $result = $this->formatter->format($items, 'numbered');

        $this->assertStringContainsString('Без названия', $result['text']);
    }

    public function test_external_access_type_labels_in_syllabus(): void
    {
        $items = [
            ['title' => 'Open', 'type' => 'external_resource', 'access_type' => 'open'],
            ['title' => 'Auth', 'type' => 'external_resource', 'access_type' => 'remote_auth'],
        ];

        $result = $this->formatter->format($items, 'syllabus');

        $this->assertStringContainsString('открытый доступ', $result['text']);
        $this->assertStringContainsString('доступ по авторизации', $result['text']);
    }

    public function test_grouped_format_external_only_omits_books_section(): void
    {
        $items = [
            ['title' => 'EBSCO', 'type' => 'external_resource', 'provider' => 'EBSCO'],
        ];

        $result = $this->formatter->format($items, 'grouped');

        $this->assertSame('grouped', $result['format']);
        $this->assertSame(1, $result['count']);
        $this->assertArrayNotHasKey('books', $result['sections']);
        $this->assertSame(1, $result['sections']['external']);
        $this->assertStringContainsString('Электронные ресурсы и базы данных', $result['text']);
    }

    public function test_syllabus_preserves_unknown_language_and_access_labels(): void
    {
        $items = [
            ['title' => 'Deutsch Book', 'type' => 'book', 'language' => 'de'],
            ['title' => 'Custom Access', 'type' => 'external_resource', 'access_type' => 'vpn_proxy'],
        ];

        $result = $this->formatter->format($items, 'syllabus');

        $this->assertStringContainsString('[de]', $result['text']);
        $this->assertStringContainsString('(vpn_proxy)', $result['text']);
    }

    public function test_numbered_format_preserves_original_item_order(): void
    {
        $items = [
            ['title' => 'First item', 'type' => 'book'],
            ['title' => 'Second item', 'type' => 'external_resource'],
        ];

        $result = $this->formatter->format($items, 'numbered');

        $this->assertLessThan(
            strpos($result['text'], 'Second item'),
            strpos($result['text'], 'First item')
        );
    }
}
