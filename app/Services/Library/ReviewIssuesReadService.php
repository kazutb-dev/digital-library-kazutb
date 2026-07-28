<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class ReviewIssuesReadService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>, filters: array<string, string|null>}
     */
    public function list(?string $severity = null, ?string $status = null, ?string $issueCode = null, int $page = 1, int $limit = 20): array
    {
        $page = max($page, 1);
        $limit = min(max($limit, 1), 100);

        $severity = $this->normalizeOptionalFilter($severity);
        $status = $this->normalizeOptionalFilter($status);
        $issueCode = $this->normalizeOptionalFilter($issueCode);

        $builder = DB::table('review.quality_issues as qi')
            ->select([
                'qi.id',
                'qi.issue_code',
                'qi.severity',
                'qi.status',
                'qi.source_schema',
                'qi.source_table',
                'qi.source_key',
                'qi.summary',
                'qi.created_at',
            ]);

        if ($severity !== null) {
            $builder->whereRaw('LOWER(qi.severity) = ?', [$severity]);
        }

        if ($status !== null) {
            $builder->whereRaw('LOWER(qi.status) = ?', [$status]);
        }

        if ($issueCode !== null) {
            $builder->whereRaw('LOWER(qi.issue_code) = ?', [$issueCode]);
        }

        $total = (clone $builder)->count();

        $rows = $builder
            ->orderByDesc('qi.created_at')
            ->orderByDesc('qi.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $data = $rows->map(function (object $row): array {
            return [
                'id' => (string) $row->id,
                'issueCode' => (string) ($row->issue_code ?? ''),
                'severity' => (string) ($row->severity ?? ''),
                'status' => (string) ($row->status ?? ''),
                'sourceSchema' => (string) ($row->source_schema ?? ''),
                'sourceTable' => (string) ($row->source_table ?? ''),
                'sourceKey' => (string) ($row->source_key ?? ''),
                'summary' => (string) ($row->summary ?? ''),
                'createdAt' => $this->normalizeTimestamp($row->created_at ?? null),
                'updatedAt' => null,
                'source' => 'review.quality_issues',
            ];
        })->all();

        $totalPages = max(1, (int) ceil($total / $limit));

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'totalPages' => $totalPages,
            ],
            'filters' => [
                'severity' => $severity,
                'status' => $status,
                'issue_code' => $issueCode,
            ],
        ];
    }

    /**
     * @return array{data: array<string, mixed>, source: string}
     */
    public function summary(int $topIssueCodesLimit = 5): array
    {
        $topIssueCodesLimit = min(max($topIssueCodesLimit, 1), 20);

        $total = DB::table('review.quality_issues')->count();

        $bySeverityRows = DB::table('review.quality_issues')
            ->selectRaw('severity, COUNT(*) as aggregate_count')
            ->groupBy('severity')
            ->orderBy('severity')
            ->get();

        $byStatusRows = DB::table('review.quality_issues')
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $topIssueCodes = DB::table('review.quality_issues')
            ->selectRaw('issue_code, COUNT(*) as aggregate_count')
            ->groupBy('issue_code')
            ->orderByDesc('aggregate_count')
            ->orderBy('issue_code')
            ->limit($topIssueCodesLimit)
            ->get()
            ->map(fn (object $row): array => [
                'issueCode' => (string) ($row->issue_code ?? ''),
                'count' => (int) ($row->aggregate_count ?? 0),
            ])
            ->all();

        $bySeverity = $bySeverityRows
            ->mapWithKeys(fn (object $row): array => [
                (string) ($row->severity ?? '') => (int) ($row->aggregate_count ?? 0),
            ])
            ->all();

        $byStatus = $byStatusRows
            ->mapWithKeys(fn (object $row): array => [
                (string) ($row->status ?? '') => (int) ($row->aggregate_count ?? 0),
            ])
            ->all();

        return [
            'data' => [
                'total' => $total,
                'bySeverity' => $bySeverity,
                'byStatus' => $byStatus,
                'topIssueCodes' => $topIssueCodes,
                'criticalCount' => $bySeverity['CRITICAL'] ?? 0,
                'highCount' => $bySeverity['HIGH'] ?? 0,
                'openCount' => $byStatus['OPEN'] ?? 0,
            ],
            'source' => 'review.quality_issues',
        ];
    }

    private function normalizeOptionalFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
