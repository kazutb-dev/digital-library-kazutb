<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class BridgeBooksDocumentsDiagnosticsReadService
{
    /**
     * @return array{data: array{items: array<int, array<string, mixed>>}, meta: array<string, int>, warnings: array<int, string>, source: string}
     */
    public function list(int $page = 1, int $limit = 20): array
    {
        $page = max($page, 1);
        $limit = min(max($limit, 1), 100);

        $hasPublicBook = $this->tableExists('public', 'Book');
        $hasAppDocuments = $this->tableExists('app', 'documents');

        if (! $hasPublicBook || ! $hasAppDocuments) {
            $warnings = [];
            if (! $hasPublicBook) {
                $warnings[] = 'public."Book" table is not available';
            }
            if (! $hasAppDocuments) {
                $warnings[] = 'app.documents table is not available';
            }

            return [
                'data' => ['items' => []],
                'meta' => [
                    'page' => $page,
                    'per_page' => $limit,
                    'total' => 0,
                    'total_pages' => 1,
                    'totalPages' => 1,
                ],
                'warnings' => $warnings,
                'source' => 'public."Book", app.documents',
            ];
        }

        $totalRow = DB::selectOne('SELECT COUNT(*)::bigint AS aggregate_count FROM public."Book"');
        $total = (int) ($totalRow->aggregate_count ?? 0);

        $rows = DB::select(
            <<<'SQL'
            SELECT
              b.id AS public_book_id,
              b."title" AS book_title,
              b."isbn" AS isbn_raw,
              regexp_replace(lower(coalesce(b."isbn", '')), '[^0-9x]', '', 'g') AS isbn_normalized,
              m.candidate_count,
              m.matched_document_id,
              m.match_reason
            FROM public."Book" b
            LEFT JOIN LATERAL (
              SELECT
                COUNT(DISTINCT d.id)::int AS candidate_count,
                MIN(d.id::text) AS matched_document_id,
                CASE
                  WHEN COUNT(DISTINCT d.id) = 0 THEN 'no_isbn_match'
                  WHEN COUNT(DISTINCT d.id) = 1 THEN 'single_isbn_match'
                  ELSE 'ambiguous_isbn_match'
                END AS match_reason
              FROM app.documents d
              WHERE regexp_replace(lower(coalesce(d.isbn_normalized, '')), '[^0-9x]', '', 'g')
                    = regexp_replace(lower(coalesce(b."isbn", '')), '[^0-9x]', '', 'g')
                AND btrim(coalesce(b."isbn", '')) <> ''
                AND btrim(coalesce(d.isbn_normalized, '')) <> ''
            ) m ON true
            ORDER BY b."createdAt" DESC, b.id DESC
            OFFSET ?
            LIMIT ?
            SQL,
            [($page - 1) * $limit, $limit]
        );

        $items = array_map(function (object $row): array {
            $candidateCount = (int) ($row->candidate_count ?? 0);
            $matched = $candidateCount > 0;
            $ambiguous = $candidateCount > 1;
            $isbnRaw = (string) ($row->isbn_raw ?? '');
            $isbnNormalized = (string) ($row->isbn_normalized ?? '');
            $hasIsbn = trim($isbnRaw) !== '';

            $normalizationWarning = null;
            if ($hasIsbn && $isbnNormalized === '') {
                $normalizationWarning = 'isbn_normalization_empty';
            } elseif ($hasIsbn) {
                // Check if normalization produced different result
                $rawStripped = preg_replace('/[^0-9x]/', '', mb_strtolower(trim($isbnRaw)));
                if ($rawStripped !== $isbnNormalized) {
                    $normalizationWarning = 'isbn_normalized_differs_from_raw';
                }
            }

            return [
                'publicBookId' => (string) ($row->public_book_id ?? ''),
                'title' => (string) ($row->book_title ?? ''),
                'isbn' => [
                    'raw' => $isbnRaw,
                    'normalized' => $isbnNormalized,
                    'isEmpty' => ! $hasIsbn,
                ],
                'bridge' => [
                    'matched' => $matched,
                    'matchedAppDocumentId' => $matched ? (string) ($row->matched_document_id ?? '') : null,
                    'candidateCount' => $candidateCount,
                    'ambiguity' => $ambiguous,
                    'reason' => $matched ? (string) ($row->match_reason ?? 'single_isbn_match') : 'no_isbn_match',
                ],
                'normalization' => [
                    'warning' => $normalizationWarning,
                ],
            ];
        }, $rows);

        $totalPages = max(1, (int) ceil($total / $limit));
        $matchedCountInPage = count(array_filter($items, static fn (array $item): bool => (bool) ($item['bridge']['matched'] ?? false)));

        $warnings = [];
        if ($total > 0 && $matchedCountInPage === 0) {
            $warnings[] = 'no matched books in current page';
        }

        return [
            'data' => [
                'items' => array_values($items),
            ],
            'meta' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'totalPages' => $totalPages,
            ],
            'warnings' => $warnings,
            'source' => 'public."Book", app.documents',
        ];
    }

    private function tableExists(string $schema, string $table): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_name = ?
            ) AS table_exists
            SQL,
            [$schema, $table]
        );

        return (bool) ($row->table_exists ?? false);
    }
}
