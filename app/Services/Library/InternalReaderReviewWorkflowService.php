<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalReaderReviewWorkflowService
{
    private const READER_TABLE = 'app.readers';

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, filters: array<string, string|null>, source: string}
     */
    public function listReaderReviewQueue(?string $reasonCode = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $normalizedReason = $this->normalizeOptionalReasonCode($reasonCode);

        $selectColumns = [
            'r.id',
            'r.full_name_raw',
            'r.legacy_code_normalized',
            'r.registration_at',
            'r.reregistration_at',
            'r.needs_review',
            'r.review_reason_codes',
        ];

        if ($this->hasReaderColumn('updated_at')) {
            $selectColumns[] = 'r.updated_at';
        }

        $builder = DB::connection('pgsql')
            ->table(self::READER_TABLE . ' as r')
            ->select($selectColumns)
            ->where('r.needs_review', true);

        if ($normalizedReason !== null && $this->hasReaderColumn('review_reason_codes')) {
            $builder->whereRaw('? = ANY(r.review_reason_codes)', [$normalizedReason]);
        }

        $total = (clone $builder)->count();

        if ($this->hasReaderColumn('updated_at')) {
            $builder->orderByDesc('r.updated_at');
        } else {
            $builder->orderByDesc('r.registration_at');
        }

        $rows = $builder
            ->orderBy('r.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $data = $rows->map(fn (object $row): array => $this->normalizeReaderRecord($row))->all();

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
            'source' => self::READER_TABLE,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, source: string}
     */
    public function readerReviewSummary(int $topReasonCodesLimit = 5): array
    {
        $topReasonCodesLimit = min(max($topReasonCodesLimit, 1), 20);

        $totalReaders = DB::connection('pgsql')->table(self::READER_TABLE)->count();
        $needsReviewCount = DB::connection('pgsql')->table(self::READER_TABLE)->where('needs_review', true)->count();
        $resolvedCount = max(0, $totalReaders - $needsReviewCount);

        $topReasonCodes = [];
        if ($this->hasReaderColumn('review_reason_codes')) {
            $rows = DB::connection('pgsql')->select(
                'SELECT reason_code, COUNT(*)::int AS aggregate_count
                 FROM (
                     SELECT UNNEST(review_reason_codes) AS reason_code
                     FROM app.readers
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
                'entity' => 'readers',
                'totalReaders' => $totalReaders,
                'needsReviewCount' => $needsReviewCount,
                'resolvedCount' => $resolvedCount,
                'topReasonCodes' => $topReasonCodes,
            ],
            'source' => self::READER_TABLE,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{data: array<string, mixed>, source: string}
     */
    public function resolveReaderReview(string $readerId, ?string $resolutionNote, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($readerId, $resolutionNote, $context): array {
            $row = DB::connection('pgsql')
                ->table(self::READER_TABLE)
                ->where('id', $readerId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new InternalReaderReviewException('reader_not_found', 404, 'Reader not found.');
            }

            $before = $this->normalizeReaderRecord($row);

            if (! (bool) ($row->needs_review ?? false)) {
                throw new InternalReaderReviewException(
                    'review_not_required',
                    409,
                    'Reader is not marked for review.',
                );
            }

            $update = ['needs_review' => false];
            if ($this->hasReaderColumn('review_reason_codes')) {
                $update['review_reason_codes'] = DB::raw("'{}'::text[]");
            }
            if ($this->hasReaderColumn('updated_at')) {
                $update['updated_at'] = Carbon::now('UTC')->toDateTimeString();
            }

            DB::connection('pgsql')
                ->table(self::READER_TABLE)
                ->where('id', $readerId)
                ->update($update);

            $afterRow = DB::connection('pgsql')
                ->table(self::READER_TABLE)
                ->where('id', $readerId)
                ->first();

            if ($afterRow === null) {
                throw new InternalReaderReviewException('reader_not_found', 404, 'Reader not found.');
            }

            $after = $this->normalizeReaderRecord($afterRow);

            CirculationAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_at' => Carbon::now('UTC'),
                'action' => 'internal_reader_review_resolved',
                'entity_type' => 'reader',
                'entity_id' => $readerId,
                'reader_id' => $readerId,
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
                'source' => self::READER_TABLE,
            ];
        });
    }

    private function hasReaderColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::READER_TABLE, $column);
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

    /**
     * @return array<string, mixed>
     */
    private function normalizeReaderRecord(object $row): array
    {
        return [
            'readerIdentity' => [
                'id' => (string) $row->id,
                'fullName' => isset($row->full_name_raw) ? (string) $row->full_name_raw : null,
                'legacyCode' => isset($row->legacy_code_normalized) ? (string) $row->legacy_code_normalized : null,
            ],
            'lifecycle' => [
                'needsReview' => (bool) ($row->needs_review ?? false),
                'reviewReasonCodes' => $this->normalizePgArray($row->review_reason_codes ?? null),
                'registrationAt' => $this->normalizeDateTime($row->registration_at ?? null),
                'reregistrationAt' => $this->normalizeDateTime($row->reregistration_at ?? null),
            ],
            'updatedAt' => $this->normalizeDateTime($row->updated_at ?? null),
        ];
    }
}
