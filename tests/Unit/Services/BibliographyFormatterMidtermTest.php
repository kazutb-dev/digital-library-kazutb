<?php

namespace Tests\Unit\Services;

use App\Services\BibliographyFormatter;
use PHPUnit\Framework\TestCase;

class BibliographyFormatterMidtermTest extends TestCase
{
    private BibliographyFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new BibliographyFormatter();
    }

    public function test_numbered_format_handles_special_characters_without_breaking_structure(): void
    {
        $items = [
            [
                'type' => 'book',
                'title' => 'Data & Security <Guide> "2026"',
                'author' => 'QA Team #1',
                'publisher' => 'KazUTB Press',
                'year' => '2026',
            ],
        ];

        $result = $this->formatter->format($items, BibliographyFormatter::FORMAT_NUMBERED);

        $this->assertSame(BibliographyFormatter::FORMAT_NUMBERED, $result['format']);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('1. QA Team #1', $result['text']);
        $this->assertStringContainsString('Data & Security <Guide> "2026"', $result['text']);
        $this->assertStringContainsString('KazUTB Press, 2026', $result['text']);
    }

    public function test_grouped_format_with_external_only_creates_external_section_for_edge_payload(): void
    {
        $items = [
            [
                'type' => 'external_resource',
                'title' => str_repeat('A', 256),
                'provider' => 'Integration Index',
                'url' => 'https://example.org/resource?mode=stress&lang=en',
            ],
        ];

        $result = $this->formatter->format($items, BibliographyFormatter::FORMAT_GROUPED);

        $this->assertSame(BibliographyFormatter::FORMAT_GROUPED, $result['format']);
        $this->assertSame(1, $result['count']);
        $this->assertArrayHasKey('external', $result['sections']);
        $this->assertArrayNotHasKey('books', $result['sections']);
        $this->assertStringContainsString('Электронные ресурсы и базы данных', $result['text']);
    }
}
