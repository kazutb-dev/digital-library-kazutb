<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalReviewWorkflowService
{
    private const COPY_TABLE = 'app.book_copies';

    public function __construct(
        private readonly InternalCopyReadService $copyReadService,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, filters: array<string, string|null>, source: string}
     */
    public function listCopyReviewQueue(?string $reasonCode = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $normalizedReason = $this->normalizeOptionalReasonCode($reasonCode);

        $builder = DB::connection('pgsql')
            ->table(self::COPY_TABLE . ' as bc')
            ->leftJoin('app.documents as d', 'd.id', '=', 'bc.document_id')
            ->select([
                'bc.id',
                'bc.document_id',
                'bc.branch_id',
                'bc.sigla_id',
                'bc.needs_review',
                'bc.review_reason_codes',
                'bc.registered_at',
                'bc.retired_at',
                'bc.updated_at',
                'd.title_raw',
            ])
            ->where('bc.needs_review', true);

        if ($normalizedReason !== null && $this->hasCopyColumn('review_reason_codes')) {
            $builder->whereRaw('? = ANY(bc.review_reason_codes)', [$normalizedReason]);
        }

        $total = (clone $builder)->count();

        $rows = $builder
            ->orderByDesc('bc.updated_at')
            ->orderBy('bc.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $data = $rows->map(function (object $row): array {
            return [
                'copyIdentity' => [
                    'id' => (string) $row->id,
                ],
                'parentDocument' => [
                    'documentId' => isset($row->document_id) ? (string) $row->document_id : null,
                    'titleRaw' => isset($row->title_raw) ? (string) $row->title_raw : null,
                ],
                'branch' => [
                    'branchId' => isset($row->branch_id) ? (string) $row->branch_id : null,
                ],
                'location' => [
                    'siglaId' => isset($row->sigla_id) ? (string) $row->sigla_id : null,
                ],
                'lifecycle' => [
                    'needsReview' => (bool) ($row->needs_review ?? false),
                    'reviewReasonCodes' => $this->normalizePgArray($row->review_reason_codes ?? null),
                    'registeredAt' => $this->normalizeDateTime($row->registered_at ?? null),
                    'retiredAt' => $this->normalizeDateTime($row->retired_at ?? null),
                ],
                'updatedAt' => $this->normalizeDateTime($row->updated_at ?? null),
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
                'reason_code' => $normalizedReason,
            ],
            'source' => self::COPY_TABLE,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, source: string}
     */
    public function copyReviewSummary(int $topReasonCodesLimit = 5): array
    {
        $topReasonCodesLimit = min(max($topReasonCodesLimit, 1), 20);

        $totalCopies = DB::connection('pgsql')->table(self::COPY_TABLE)->count();
        $needsReviewCount = DB::connection('pgsql')->table(self::COPY_TABLE)->where('needs_review', true)->count();
        $resolvedCount = max(0, $totalCopies - $needsReviewCount);

        $topReasonCodes = [];
        if ($this->hasCopyColumn('review_reason_codes')) {
            $rows = DB::connection('pgsql')->select(
                'SELECT reason_code, COUNT(*)::int AS aggregate_count
                 FROM (
                     SELECT UNNEST(review_reason_codes) AS reason_code
                     FROM app.book_copies
                     WHERE needs_review IS TRUE AND review_reason_codes IS NOT NULL
                 ) reason_rows
                 GROUP BY reason_code
                 ORDER BY aggregate_count DESC, reason_code ASC
                 LIMIT ?',
                [$topReasonCodesLimit]
            );

            $topReasonCodes = array_map(static fn (object $row): array => [
                'reasonCode' => (string) ($row->reason_code ?? ''),
                'count' => (int) ($row->aggregate_count ?? 0),
            ], $rows);
        }

        return [
            'data' => [
                'entity' => 'copies',
                'totalCopies' => $totalCopies,
                'needsReviewCount' => $needsReviewCount,
                'resolvedCount' => $resolvedCount,
                'topReasonCodes' => $topReasonCodes,
            ],
            'source' => self::COPY_TABLE,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{data: array<string, mixed>, source: string}
     */
    public function resolveCopyReview(string $copyId, ?string $resolutionNote, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($copyId, $resolutionNote, $context): array {
            $row = DB::connection('pgsql')
                ->table(self::COPY_TABLE)
                ->where('id', $copyId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
            }

            $before = $this->copyReadService->findCopyDetail($copyId);
            if ($before === null) {
                throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
            }

            if (! (bool) ($row->needs_review ?? false)) {
                throw new InternalCopyWriteException(
                    'review_not_required',
                    409,
                    'Copy is not marked for review.',
                );
            }

            $update = ['needs_review' => false];
            if ($this->hasCopyColumn('review_reason_codes')) {
                $update['review_reason_codes'] = DB::raw("'{}'::text[]");
            }
            if ($this->hasCopyColumn('updated_at')) {
                $update['updated_at'] = Carbon::now('UTC')->toDateTimeString();
            }

            DB::connection('pgsql')
                ->table(self::COPY_TABLE)
                ->where('id', $copyId)
                ->update($update);

            $after = $this->copyReadService->findCopyDetail($copyId);
            if ($after === null) {
                throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
            }

            CirculationAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_at' => Carbon::now('UTC'),
                'action' => 'internal_copy_review_resolved',
                'entity_type' => 'copy',
                'entity_id' => $copyId,
                'reader_id' => null,
                'actor_user_id' => $context['actorUserId'] ?? null,
                'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
                'request_id' => $context['requestId'] ?? null,
                'correlation_id' => $context['correlationId'] ?? null,
                'previous_state' => $before,
                'new_state' => $after,
                'metadata' => [
                    'details' => [
                        'resolution_note' => $resolutionNote,
                        'resolution_note_provided' => $resolutionNote !== null,
                        'resolved_reason_codes_count' => count($before['lifecycle']['reviewReasonCodes'] ?? []),
                    ],
                ],
            ]);

            return [
                'data' => $after,
                'source' => self::COPY_TABLE,
            ];
        });
    }

    private function hasCopyColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::COPY_TABLE, $column);
    }

    private function normalizeOptionalReasonCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return list<string>
     */
    private function normalizePgArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (! is_string($value)) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed === '' || $trimmed === '{}') {
            return [];
        }

        $trimmed = trim($trimmed, '{}');

        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item, "\" \t\n\r\0\x0B"),
            explode(',', $trimmed)
        ), static fn (string $item): bool => $item !== ''));
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        try {
            return Carbon::parse((string) $value, 'UTC')->toISOString();
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
