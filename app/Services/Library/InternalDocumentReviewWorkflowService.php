<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalDocumentReviewWorkflowService
{
    private const DOCUMENT_TABLE = 'app.documents';

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, filters: array<string, string|null>, source: string}
     */
    public function listDocumentReviewQueue(?string $reasonCode = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $normalizedReason = $this->normalizeOptionalReasonCode($reasonCode);

        $builder = DB::connection('pgsql')
            ->table(self::DOCUMENT_TABLE . ' as d')
            ->select([
                'd.id',
                'd.isbn_raw',
                'd.isbn_normalized',
                'd.title_raw',
                'd.title_normalized',
                'd.needs_review',
                'd.review_reason_codes',
                'd.created_at',
                'd.updated_at',
            ])
            ->where('d.needs_review', true);

        if ($normalizedReason !== null && $this->hasDocumentColumn('review_reason_codes')) {
            $builder->whereRaw('? = ANY(d.review_reason_codes)', [$normalizedReason]);
        }

        $total = (clone $builder)->count();

        $rows = $builder
            ->orderByDesc('d.updated_at')
            ->orderBy('d.id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $data = $rows->map(function (object $row): array {
            return [
                'documentIdentity' => [
                    'id' => (string) $row->id,
                    'isbnRaw' => isset($row->isbn_raw) ? (string) $row->isbn_raw : null,
                    'isbnNormalized' => isset($row->isbn_normalized) ? (string) $row->isbn_normalized : null,
                ],
                'title' => [
                    'titleRaw' => isset($row->title_raw) ? (string) $row->title_raw : null,
                    'titleNormalized' => isset($row->title_normalized) ? (string) $row->title_normalized : null,
                ],
                'lifecycle' => [
                    'needsReview' => (bool) ($row->needs_review ?? false),
                    'reviewReasonCodes' => $this->normalizePgArray($row->review_reason_codes ?? null),
                    'createdAt' => $this->normalizeDateTime($row->created_at ?? null),
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
            'source' => self::DOCUMENT_TABLE,
        ];
    }

    /**
     * @return array{data: array<string, mixed>, source: string}
     */
    public function documentReviewSummary(int $topReasonCodesLimit = 5): array
    {
        $topReasonCodesLimit = min(max($topReasonCodesLimit, 1), 20);

        $totalDocuments = DB::connection('pgsql')->table(self::DOCUMENT_TABLE)->count();
        $needsReviewCount = DB::connection('pgsql')->table(self::DOCUMENT_TABLE)->where('needs_review', true)->count();
        $resolvedCount = max(0, $totalDocuments - $needsReviewCount);

        $topReasonCodes = [];
        if ($this->hasDocumentColumn('review_reason_codes')) {
            $rows = DB::connection('pgsql')->select(
                'SELECT reason_code, COUNT(*)::int AS aggregate_count
                 FROM (
                     SELECT UNNEST(review_reason_codes) AS reason_code
                     FROM app.documents
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
                'entity' => 'documents',
                'totalDocuments' => $totalDocuments,
                'needsReviewCount' => $needsReviewCount,
                'resolvedCount' => $resolvedCount,
                'topReasonCodes' => $topReasonCodes,
            ],
            'source' => self::DOCUMENT_TABLE,
        ];
    }

    /**
     * Flag a document for review with one or more reason codes.
     *
     * @param list<string> $reasonCodes
     * @param array<string, mixed> $context
     * @return array{data: array<string, mixed>, source: string}
     */
    public function flagDocumentForReview(string $documentId, array $reasonCodes, ?string $flagNote, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($documentId, $reasonCodes, $flagNote, $context): array {
            $row = DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new InternalDocumentReviewException('document_not_found', 404, 'Document not found.');
            }

            $before = $this->normalizeDocumentRecord($row);

            $existingCodes = $this->normalizePgArray($row->review_reason_codes ?? null);
            $normalizedNewCodes = array_values(array_filter(
                array_map(static fn (string $code): string => strtoupper(trim($code)), $reasonCodes),
                static fn (string $code): bool => $code !== '',
            ));

            $mergedCodes = array_values(array_unique(array_merge($existingCodes, $normalizedNewCodes)));
            $addedCodes = array_values(array_diff($normalizedNewCodes, $existingCodes));

            $update = ['needs_review' => true];
            if ($this->hasDocumentColumn('review_reason_codes')) {
                $pgLiteral = '{' . implode(',', $mergedCodes) . '}';
                $update['review_reason_codes'] = $pgLiteral;
            }
            if ($this->hasDocumentColumn('updated_at')) {
                $update['updated_at'] = Carbon::now('UTC')->toDateTimeString();
            }

            DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->update($update);

            $afterRow = DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->first();

            if ($afterRow === null) {
                throw new InternalDocumentReviewException('document_not_found', 404, 'Document not found.');
            }

            $after = $this->normalizeDocumentRecord($afterRow);

            $wasAlreadyFlagged = (bool) ($row->needs_review ?? false);

            CirculationAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_at' => Carbon::now('UTC'),
                'action' => 'internal_document_review_flagged',
                'entity_type' => 'document',
                'entity_id' => $documentId,
                'reader_id' => null,
                'actor_user_id' => $context['actorUserId'] ?? null,
                'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
                'request_id' => $context['requestId'] ?? null,
                'correlation_id' => $context['correlationId'] ?? null,
                'previous_state' => $before,
                'new_state' => $after,
                'metadata' => [
                    'details' => [
                        'flag_note' => $flagNote,
                        'flag_note_provided' => $flagNote !== null,
                        'requested_reason_codes' => $normalizedNewCodes,
                        'added_reason_codes' => $addedCodes,
                        'merged_reason_codes' => $mergedCodes,
                        'was_already_flagged' => $wasAlreadyFlagged,
                    ],
                ],
            ]);

            return [
                'data' => $after,
                'flagging' => [
                    'wasAlreadyFlagged' => $wasAlreadyFlagged,
                    'addedReasonCodes' => $addedCodes,
                    'mergedReasonCodes' => $mergedCodes,
                ],
                'source' => self::DOCUMENT_TABLE,
            ];
        });
    }

    /**
     * @param array<string, mixed> $context
     * @return array{data: array<string, mixed>, source: string}
     */
    public function resolveDocumentReview(string $documentId, ?string $resolutionNote, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($documentId, $resolutionNote, $context): array {
            $row = DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new InternalDocumentReviewException('document_not_found', 404, 'Document not found.');
            }

            $before = $this->normalizeDocumentRecord($row);

            if (! (bool) ($row->needs_review ?? false)) {
                throw new InternalDocumentReviewException(
                    'review_not_required',
                    409,
                    'Document is not marked for review.',
                );
            }

            $update = ['needs_review' => false];
            if ($this->hasDocumentColumn('review_reason_codes')) {
                $update['review_reason_codes'] = DB::raw("'{}'::text[]");
            }
            if ($this->hasDocumentColumn('updated_at')) {
                $update['updated_at'] = Carbon::now('UTC')->toDateTimeString();
            }

            DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->update($update);

            $afterRow = DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->first();

            if ($afterRow === null) {
                throw new InternalDocumentReviewException('document_not_found', 404, 'Document not found.');
            }

            $after = $this->normalizeDocumentRecord($afterRow);

            CirculationAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_at' => Carbon::now('UTC'),
                'action' => 'internal_document_review_resolved',
                'entity_type' => 'document',
                'entity_id' => $documentId,
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
                'source' => self::DOCUMENT_TABLE,
            ];
        });
    }

    private function hasDocumentColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::DOCUMENT_TABLE, $column);
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
    private function normalizeDocumentRecord(object $row): array
    {
        return [
            'documentIdentity' => [
                'id' => (string) $row->id,
                'isbnRaw' => isset($row->isbn_raw) ? (string) $row->isbn_raw : null,
                'isbnNormalized' => isset($row->isbn_normalized) ? (string) $row->isbn_normalized : null,
            ],
            'title' => [
                'titleRaw' => isset($row->title_raw) ? (string) $row->title_raw : null,
                'titleNormalized' => isset($row->title_normalized) ? (string) $row->title_normalized : null,
            ],
            'lifecycle' => [
                'needsReview' => (bool) ($row->needs_review ?? false),
                'reviewReasonCodes' => $this->normalizePgArray($row->review_reason_codes ?? null),
                'createdAt' => $this->normalizeDateTime($row->created_at ?? null),
            ],
            'updatedAt' => $this->normalizeDateTime($row->updated_at ?? null),
        ];
    }
}
