<?php

namespace App\Services;

/**
 * Formats shortlist items into bibliography-style text output.
 *
 * Supports: numbered (flat list), grouped (by type), syllabus (teacher-friendly draft).
 */
class BibliographyFormatter
{
    public const FORMAT_NUMBERED = 'numbered';
    public const FORMAT_GROUPED = 'grouped';
    public const FORMAT_SYLLABUS = 'syllabus';

    public const VALID_FORMATS = [
        self::FORMAT_NUMBERED,
        self::FORMAT_GROUPED,
        self::FORMAT_SYLLABUS,
    ];

    /**
     * Format shortlist items into bibliography text.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{text: string, format: string, count: int, sections?: array<string, int>}
     */
    public function format(array $items, string $format = self::FORMAT_NUMBERED): array
    {
        if (! in_array($format, self::VALID_FORMATS, true)) {
            $format = self::FORMAT_NUMBERED;
        }

        if (count($items) === 0) {
            return [
                'text' => '',
                'format' => $format,
                'count' => 0,
            ];
        }

        return match ($format) {
            self::FORMAT_GROUPED => $this->formatGrouped($items),
            self::FORMAT_SYLLABUS => $this->formatSyllabus($items),
            default => $this->formatNumbered($items),
        };
    }

    /**
     * Plain numbered list — all items sequentially.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{text: string, format: string, count: int}
     */
    private function formatNumbered(array $items): array
    {
        $lines = [];
        foreach (array_values($items) as $idx => $item) {
            $lines[] = ($idx + 1) . '. ' . $this->formatSingleEntry($item);
        }

        return [
            'text' => implode("\n", $lines),
            'format' => self::FORMAT_NUMBERED,
            'count' => count($items),
        ];
    }

    /**
     * Grouped by resource type — books first, external resources second.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{text: string, format: string, count: int, sections: array<string, int>}
     */
    private function formatGrouped(array $items): array
    {
        $books = [];
        $external = [];

        foreach ($items as $item) {
            if (($item['type'] ?? 'book') === 'external_resource') {
                $external[] = $item;
            } else {
                $books[] = $item;
            }
        }

        $sections = [];
        $blocks = [];

        if (count($books) > 0) {
            $sections['books'] = count($books);
            $block = "Основная литература\n";
            foreach ($books as $idx => $item) {
                $block .= ($idx + 1) . '. ' . $this->formatSingleEntry($item) . "\n";
            }
            $blocks[] = trim($block);
        }

        if (count($external) > 0) {
            $sections['external'] = count($external);
            $block = "Электронные ресурсы и базы данных\n";
            foreach ($external as $idx => $item) {
                $block .= ($idx + 1) . '. ' . $this->formatExternalEntry($item) . "\n";
            }
            $blocks[] = trim($block);
        }

        return [
            'text' => implode("\n\n", $blocks),
            'format' => self::FORMAT_GROUPED,
            'count' => count($items),
            'sections' => $sections,
        ];
    }

    /**
     * Syllabus-ready format — structured for teacher document insertion.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{text: string, format: string, count: int, sections: array<string, int>}
     */
    private function formatSyllabus(array $items): array
    {
        $books = [];
        $external = [];

        foreach ($items as $item) {
            if (($item['type'] ?? 'book') === 'external_resource') {
                $external[] = $item;
            } else {
                $books[] = $item;
            }
        }

        $sections = [];
        $blocks = [];

        $blocks[] = 'СПИСОК ЛИТЕРАТУРЫ';
        $blocks[] = str_repeat('─', 40);

        if (count($books) > 0) {
            $sections['books'] = count($books);
            $block = "Основная литература:\n";
            foreach ($books as $idx => $item) {
                $block .= ($idx + 1) . '. ' . $this->formatSyllabusBookEntry($item) . "\n";
            }
            $blocks[] = trim($block);
        }

        if (count($external) > 0) {
            $sections['external'] = count($external);
            $block = "Электронные ресурсы:\n";
            foreach ($external as $idx => $item) {
                $block .= ($idx + 1) . '. ' . $this->formatSyllabusExternalEntry($item) . "\n";
            }
            $blocks[] = trim($block);
        }

        return [
            'text' => implode("\n\n", $blocks),
            'format' => self::FORMAT_SYLLABUS,
            'count' => count($items),
            'sections' => $sections,
        ];
    }

    /**
     * Single bibliography entry for numbered/default format.
     */
    private function formatSingleEntry(array $item): string
    {
        if (($item['type'] ?? 'book') === 'external_resource') {
            return $this->formatExternalEntry($item);
        }

        return $this->formatBookEntry($item);
    }

    /**
     * Book entry: Author. Title. — Publisher, Year. — ISBN.
     */
    private function formatBookEntry(array $item): string
    {
        $parts = [];

        $author = trim((string) ($item['author'] ?? ''));
        if ($author !== '') {
            $parts[] = $author;
        }

        $title = trim((string) ($item['title'] ?? 'Без названия'));
        $parts[] = $title;

        $pubParts = [];
        $publisher = trim((string) ($item['publisher'] ?? ''));
        if ($publisher !== '') {
            $pubParts[] = $publisher;
        }
        $year = trim((string) ($item['year'] ?? ''));
        if ($year !== '') {
            $pubParts[] = $year;
        }
        if (count($pubParts) > 0) {
            $parts[] = '— ' . implode(', ', $pubParts);
        }

        $isbn = trim((string) ($item['isbn'] ?? ''));
        if ($isbn !== '') {
            $parts[] = '— ISBN ' . $isbn;
        }

        return implode('. ', $parts) . '.';
    }

    /**
     * External resource entry: [Электронный ресурс] Title / Provider. — URL.
     */
    private function formatExternalEntry(array $item): string
    {
        $parts = [];
        $parts[] = $item['title'] ?? 'Без названия';

        $provider = trim((string) ($item['provider'] ?? ''));
        if ($provider !== '') {
            $parts[] = '/ ' . $provider;
        }

        $url = trim((string) ($item['url'] ?? ''));
        if ($url !== '') {
            $parts[] = '— URL: ' . $url;
        }

        return '[Электронный ресурс] ' . implode('. ', $parts) . '.';
    }

    /**
     * Syllabus book entry — more structured with explicit fields.
     */
    private function formatSyllabusBookEntry(array $item): string
    {
        $parts = [];

        $author = trim((string) ($item['author'] ?? ''));
        if ($author !== '') {
            $parts[] = $author;
        }

        $title = trim((string) ($item['title'] ?? 'Без названия'));
        $parts[] = $title;

        $detail = [];
        $publisher = trim((string) ($item['publisher'] ?? ''));
        if ($publisher !== '') {
            $detail[] = $publisher;
        }
        $year = trim((string) ($item['year'] ?? ''));
        if ($year !== '') {
            $detail[] = $year;
        }
        if (count($detail) > 0) {
            $parts[] = '— ' . implode(', ', $detail);
        }

        $isbn = trim((string) ($item['isbn'] ?? ''));
        if ($isbn !== '') {
            $parts[] = '— ISBN ' . $isbn;
        }

        $language = trim((string) ($item['language'] ?? ''));
        if ($language !== '') {
            $langLabels = ['ru' => 'рус.', 'kz' => 'каз.', 'en' => 'англ.'];
            $langLabel = $langLabels[$language] ?? $language;
            $parts[] = '— [' . $langLabel . ']';
        }

        return implode('. ', $parts) . '.';
    }

    /**
     * Syllabus external entry — with access info.
     */
    private function formatSyllabusExternalEntry(array $item): string
    {
        $parts = [];
        $parts[] = $item['title'] ?? 'Без названия';

        $provider = trim((string) ($item['provider'] ?? ''));
        if ($provider !== '') {
            $parts[] = '/ ' . $provider;
        }

        $url = trim((string) ($item['url'] ?? ''));
        if ($url !== '') {
            $parts[] = '— URL: ' . $url;
        }

        $accessType = trim((string) ($item['access_type'] ?? ''));
        if ($accessType !== '') {
            $accessLabels = [
                'campus' => 'доступ из кампуса',
                'remote_auth' => 'доступ по авторизации',
                'open' => 'открытый доступ',
            ];
            $accessLabel = $accessLabels[$accessType] ?? $accessType;
            $parts[] = '— (' . $accessLabel . ')';
        }

        return '[Электронный ресурс] ' . implode('. ', $parts) . '.';
    }
}
