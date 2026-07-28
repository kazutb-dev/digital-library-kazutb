<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class BridgeCopiesDiagnosticsReadService
{
    /**
     * @return array{data: array{items: array<int, array<string, mixed>>}, meta: array<string, int>, warnings: array<int, string>, source: string}
     */
    public function list(int $page = 1, int $limit = 20): array
    {
        $page = max($page, 1);
        $limit = min(max($limit, 1), 100);

        $hasPublicBookCopy = $this->tableExists('public', 'BookCopy');
        $hasAppBookCopies = $this->tableExists('app', 'book_copies');

        if (! $hasPublicBookCopy || ! $hasAppBookCopies) {
            $warnings = [];
            if (! $hasPublicBookCopy) {
                $warnings[] = 'public."BookCopy" table is not available';
            }
            if (! $hasAppBookCopies) {
                $warnings[] = 'app.book_copies table is not available';
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
                'source' => 'public."BookCopy", app.book_copies',
            ];
        }

        $totalRow = DB::selectOne('SELECT COUNT(*)::bigint AS aggregate_count FROM public."BookCopy"');
        $total = (int) ($totalRow->aggregate_count ?? 0);

        $rows = DB::select(
            <<<'SQL'
            SELECT
              bc.id AS public_book_copy_id,
              bc."inventoryNumber" AS inventory_number_raw,
              lower(btrim(coalesce(bc."inventoryNumber", ''))) AS inventory_number_normalized,
              bc."status" AS copy_status,
              m.candidate_count,
              m.matched_book_copy_id,
              m.match_reason
            FROM public."BookCopy" bc
            LEFT JOIN LATERAL (
              SELECT
                COUNT(DISTINCT abc.id)::int AS candidate_count,
                MIN(abc.id::text) AS matched_book_copy_id,
                CASE
                  WHEN COUNT(DISTINCT abc.id) = 0 THEN 'no_inv_match'
                  WHEN COUNT(DISTINCT abc.id) = 1 THEN 'single_inv_match'
                  ELSE 'ambiguous_inv_match'
                END AS match_reason
              FROM app.book_copies abc
              WHERE lower(btrim(coalesce(abc.inventory_number_normalized, ''))) = lower(btrim(coalesce(bc."inventoryNumber", '')))
                AND btrim(coalesce(bc."inventoryNumber", '')) <> ''
            ) m ON true
            ORDER BY bc."createdAt" DESC, bc.id DESC
            OFFSET ?
            LIMIT ?
            SQL,
            [($page - 1) * $limit, $limit]
        );

        $items = array_map(function (object $row): array {
            $candidateCount = (int) ($row->candidate_count ?? 0);
            $matched = $candidateCount > 0;
            $ambiguous = $candidateCount > 1;
            $inventoryNumberRaw = (string) ($row->inventory_number_raw ?? '');
            $inventoryNumberNormalized = (string) ($row->inventory_number_normalized ?? '');
            $hasInventoryNumber = trim($inventoryNumberRaw) !== '';

            $normalizationWarning = null;
            if ($hasInventoryNumber && $inventoryNumberNormalized === '') {
                $normalizationWarning = 'inventory_number_normalization_empty';
            } elseif ($hasInventoryNumber && mb_strtolower(trim($inventoryNumberRaw)) !== $inventoryNumberNormalized) {
                $normalizationWarning = 'inventory_number_normalized_differs_from_raw';
            }

            return [
                'publicBookCopyId' => (string) ($row->public_book_copy_id ?? ''),
                'copyStatus' => (string) ($row->copy_status ?? ''),
                'inventoryNumber' => [
                    'raw' => $inventoryNumberRaw,
                    'normalized' => $inventoryNumberNormalized,
                    'isEmpty' => ! $hasInventoryNumber,
                ],
                'bridge' => [
                    'matched' => $matched,
                    'matchedAppBookCopyId' => $matched ? (string) ($row->matched_book_copy_id ?? '') : null,
                    'candidateCount' => $candidateCount,
                    'ambiguity' => $ambiguous,
                    'reason' => (string) ($row->match_reason ?? 'no_inv_match'),
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
            $warnings[] = 'no matched copies in current page';
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
            'source' => 'public."BookCopy", app.book_copies',
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
