<?php

namespace App\Services\Library;

/**
 * ISBN validation (checksum verification for ISBN-10 and ISBN-13)
 * and OpenLibrary metadata lookup.
 */
class IsbnService
{
    private const OPENLIBRARY_API = 'https://openlibrary.org/api/books';
    private const OPENLIBRARY_SEARCH = 'https://openlibrary.org/search.json';
    private const HTTP_TIMEOUT = 8;

    /**
     * Normalize an ISBN string: strip hyphens, spaces, lowercase x → X.
     */
    public function normalize(string $isbn): string
    {
        $cleaned = preg_replace('/[^0-9xX]/', '', $isbn);

        return strtoupper($cleaned);
    }

    /**
     * Validate an ISBN (supports both ISBN-10 and ISBN-13).
     */
    public function validate(string $isbn): array
    {
        $normalized = $this->normalize($isbn);
        $length = strlen($normalized);

        if ($length === 13) {
            $valid = $this->validateIsbn13($normalized);

            return [
                'isbn' => $normalized,
                'format' => 'ISBN-13',
                'valid' => $valid,
                'error' => $valid ? null : 'Invalid ISBN-13 checksum',
            ];
        }

        if ($length === 10) {
            $valid = $this->validateIsbn10($normalized);

            return [
                'isbn' => $normalized,
                'format' => 'ISBN-10',
                'valid' => $valid,
                'error' => $valid ? null : 'Invalid ISBN-10 checksum',
            ];
        }

        return [
            'isbn' => $normalized,
            'format' => null,
            'valid' => false,
            'error' => "Invalid length ({$length}), expected 10 or 13",
        ];
    }

    /**
     * Validate ISBN-13 checksum (EAN-13 algorithm).
     */
    private function validateIsbn13(string $isbn): bool
    {
        if (! preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $isbn[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $isbn[12];
    }

    /**
     * Validate ISBN-10 checksum.
     */
    private function validateIsbn10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $isbn[$i] * (10 - $i);
        }

        $lastChar = $isbn[9];
        $sum += ($lastChar === 'X') ? 10 : (int) $lastChar;

        return $sum % 11 === 0;
    }

    /**
     * Convert ISBN-10 to ISBN-13.
     */
    public function isbn10to13(string $isbn10): ?string
    {
        $normalized = $this->normalize($isbn10);
        if (strlen($normalized) !== 10) {
            return null;
        }

        $base = '978' . substr($normalized, 0, 9);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $base[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $check = (10 - ($sum % 10)) % 10;

        return $base . $check;
    }

    /**
     * Look up book metadata from OpenLibrary by ISBN.
     *
     * @return array{found: bool, source: string, metadata: array<string, mixed>|null, error: string|null}
     */
    public function lookupByIsbn(string $isbn): array
    {
        $normalized = $this->normalize($isbn);
        if ($normalized === '') {
            return ['found' => false, 'source' => 'openlibrary', 'metadata' => null, 'error' => 'Empty ISBN'];
        }

        $key = 'ISBN:' . $normalized;
        $url = self::OPENLIBRARY_API . '?' . http_build_query([
            'bibkeys' => $key,
            'format' => 'json',
            'jscmd' => 'data',
        ]);

        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => self::HTTP_TIMEOUT,
                    'header' => "Accept: application/json\r\nUser-Agent: DigitalLibrary/1.0\r\n",
                ],
            ]);

            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                return ['found' => false, 'source' => 'openlibrary', 'metadata' => null, 'error' => 'HTTP request failed'];
            }

            $data = json_decode($response, true);
            if (! is_array($data) || ! isset($data[$key])) {
                return ['found' => false, 'source' => 'openlibrary', 'metadata' => null, 'error' => null];
            }

            $book = $data[$key];

            return [
                'found' => true,
                'source' => 'openlibrary',
                'metadata' => $this->normalizeOpenLibraryResponse($book, $normalized),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['found' => false, 'source' => 'openlibrary', 'metadata' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Search OpenLibrary by title (fallback when no ISBN available).
     *
     * @return array{found: bool, source: string, results: list<array<string, mixed>>, error: string|null}
     */
    public function searchByTitle(string $title, ?int $year = null, int $limit = 3): array
    {
        $params = ['title' => $title, 'limit' => $limit, 'fields' => 'key,title,author_name,first_publish_year,isbn,publisher,language,number_of_pages_median'];
        if ($year !== null) {
            $params['first_publish_year'] = $year;
        }

        $url = self::OPENLIBRARY_SEARCH . '?' . http_build_query($params);

        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => self::HTTP_TIMEOUT,
                    'header' => "Accept: application/json\r\nUser-Agent: DigitalLibrary/1.0\r\n",
                ],
            ]);

            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                return ['found' => false, 'source' => 'openlibrary_search', 'results' => [], 'error' => 'HTTP request failed'];
            }

            $data = json_decode($response, true);
            $docs = $data['docs'] ?? [];

            if (empty($docs)) {
                return ['found' => false, 'source' => 'openlibrary_search', 'results' => [], 'error' => null];
            }

            $results = [];
            foreach (array_slice($docs, 0, $limit) as $doc) {
                $results[] = [
                    'title' => $doc['title'] ?? null,
                    'authors' => $doc['author_name'] ?? [],
                    'publishYear' => $doc['first_publish_year'] ?? null,
                    'isbn' => ! empty($doc['isbn']) ? $doc['isbn'][0] : null,
                    'publishers' => $doc['publisher'] ?? [],
                    'languages' => $doc['language'] ?? [],
                    'pages' => $doc['number_of_pages_median'] ?? null,
                ];
            }

            return ['found' => true, 'source' => 'openlibrary_search', 'results' => $results, 'error' => null];
        } catch (\Throwable $e) {
            return ['found' => false, 'source' => 'openlibrary_search', 'results' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOpenLibraryResponse(array $book, string $isbn): array
    {
        $authors = [];
        foreach ($book['authors'] ?? [] as $author) {
            $authors[] = $author['name'] ?? null;
        }

        $publishers = [];
        foreach ($book['publishers'] ?? [] as $pub) {
            $publishers[] = $pub['name'] ?? null;
        }

        $subjects = [];
        foreach ($book['subjects'] ?? [] as $subj) {
            $subjects[] = $subj['name'] ?? null;
        }

        return [
            'title' => $book['title'] ?? null,
            'subtitle' => $book['subtitle'] ?? null,
            'authors' => array_filter($authors),
            'publishers' => array_filter($publishers),
            'publishDate' => $book['publish_date'] ?? null,
            'publishYear' => $this->extractYear($book['publish_date'] ?? null),
            'numberOfPages' => $book['number_of_pages'] ?? null,
            'subjects' => array_values(array_slice(array_filter($subjects), 0, 10)),
            'isbn' => $isbn,
            'coverUrl' => $book['cover']['medium'] ?? $book['cover']['small'] ?? null,
            'openLibraryUrl' => $book['url'] ?? null,
        ];
    }

    private function extractYear(?string $dateStr): ?int
    {
        if ($dateStr === null) {
            return null;
        }

        if (preg_match('/\b(1[89]\d{2}|2[01]\d{2})\b/', $dateStr, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
