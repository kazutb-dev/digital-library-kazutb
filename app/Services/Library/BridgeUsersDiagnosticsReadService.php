<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class BridgeUsersDiagnosticsReadService
{
    /**
     * @return array{data: array{items: array<int, array<string, mixed>>}, meta: array<string, int>, warnings: array<int, string>, source: string}
     */
    public function list(int $page = 1, int $limit = 20): array
    {
        $page = max($page, 1);
        $limit = min(max($limit, 1), 100);

        $hasPublicUser = $this->tableExists('public', 'User');
        $hasAppReaderContacts = $this->tableExists('app', 'reader_contacts');

        if (! $hasPublicUser || ! $hasAppReaderContacts) {
            $warnings = [];
            if (! $hasPublicUser) {
                $warnings[] = 'public."User" table is not available';
            }
            if (! $hasAppReaderContacts) {
                $warnings[] = 'app.reader_contacts table is not available';
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
                'source' => 'public."User", app.reader_contacts',
            ];
        }

        $totalRow = DB::selectOne('SELECT COUNT(*)::bigint AS aggregate_count FROM public."User"');
        $total = (int) ($totalRow->aggregate_count ?? 0);

        $rows = DB::select(
            <<<'SQL'
            SELECT
              u.id AS public_user_id,
              u."fullName" AS public_full_name,
              u."email" AS public_email_raw,
              lower(btrim(coalesce(u."email", ''))) AS public_email_normalized,
              m.candidate_count,
              m.matched_reader_id,
              m.match_reason
            FROM public."User" u
            LEFT JOIN LATERAL (
              SELECT
                COUNT(DISTINCT rc.reader_id)::int AS candidate_count,
                                MIN(rc.reader_id::text) AS matched_reader_id,
                CASE
                  WHEN COUNT(DISTINCT rc.reader_id) = 0 THEN 'no_email_match'
                  WHEN COUNT(DISTINCT rc.reader_id) = 1 THEN 'single_email_match'
                  ELSE 'ambiguous_email_match'
                END AS match_reason
              FROM app.reader_contacts rc
              WHERE rc.contact_type = 'EMAIL'
                AND lower(btrim(coalesce(rc.value_normalized, ''))) = lower(btrim(coalesce(u."email", '')))
                AND btrim(coalesce(u."email", '')) <> ''
            ) m ON true
            ORDER BY u."createdAt" DESC, u.id DESC
            OFFSET ?
            LIMIT ?
            SQL,
            [($page - 1) * $limit, $limit]
        );

        $items = array_map(function (object $row): array {
            $candidateCount = (int) ($row->candidate_count ?? 0);
            $matched = $candidateCount > 0;
            $ambiguous = $candidateCount > 1;
            $emailRaw = (string) ($row->public_email_raw ?? '');
            $emailNormalized = (string) ($row->public_email_normalized ?? '');
            $hasEmail = trim($emailRaw) !== '';

            $normalizationWarning = null;
            if ($hasEmail && $emailNormalized === '') {
                $normalizationWarning = 'email_normalization_empty';
            } elseif ($hasEmail && mb_strtolower(trim($emailRaw)) !== $emailNormalized) {
                $normalizationWarning = 'email_normalized_differs_from_raw';
            }

            return [
                'publicUserId' => (string) ($row->public_user_id ?? ''),
                'publicUserFullName' => (string) ($row->public_full_name ?? ''),
                'email' => [
                    'raw' => $emailRaw,
                    'normalized' => $emailNormalized,
                    'isEmpty' => ! $hasEmail,
                ],
                'bridge' => [
                    'matched' => $matched,
                    'matchedAppReaderId' => $matched ? (string) ($row->matched_reader_id ?? '') : null,
                    'candidateCount' => $candidateCount,
                    'ambiguity' => $ambiguous,
                    'reason' => (string) ($row->match_reason ?? 'no_email_match'),
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
            $warnings[] = 'no matched users in current page';
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
            'source' => 'public."User", app.reader_contacts',
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
