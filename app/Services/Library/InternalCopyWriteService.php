<?php

namespace App\Services\Library;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Library\CirculationAuditEvent;

class InternalCopyWriteService
{
    private const COPY_TABLE = 'app.book_copies';

    private const DOCUMENT_TABLE = 'app.documents';

    private const BRANCH_TABLE = 'app.branches';

    private const SIGLA_TABLE = 'app.siglas';

    /** @var array<string, string> */
    private const CREATE_FIELD_TO_COLUMN = [
        'document_id' => 'document_id',
        'branch_id' => 'branch_id',
        'sigla_id' => 'sigla_id',
        'inventory_number' => 'inventory_number_raw',
        'registered_at' => 'registered_at',
        'needs_review' => 'needs_review',
        'review_reason_codes' => 'review_reason_codes',
    ];

    /** @var array<string, string> */
    private const PATCH_FIELD_TO_COLUMN = [
        'needs_review' => 'needs_review',
        'review_reason_codes' => 'review_reason_codes',
    ];

    public function __construct(
        private readonly InternalCopyReadService $readService,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createCopy(array $payload, array $context): array
    {
        $this->rejectUnsupportedFields($payload, array_keys(self::CREATE_FIELD_TO_COLUMN), 'create');

        return DB::connection('pgsql')->transaction(function () use ($payload, $context): array {
            $document = $this->requireDocument((string) $payload['document_id']);
            $branch = $this->requireBranch((string) $payload['branch_id']);
            $sigla = $this->requireSigla((string) $payload['sigla_id']);

            $this->assertBranchLocationCoherence($branch, $sigla);

            $reviewState = $this->resolveReviewState(
                payload: $payload,
                currentNeedsReview: false,
                currentReviewReasonCodes: [],
            );

            $now = Carbon::now('UTC');
            $insert = [];

            if ($this->hasCopyColumn('id')) {
                $insert['id'] = (string) Str::uuid();
            }

            if ($this->hasCopyColumn('core_copy_id')) {
                $insert['core_copy_id'] = (string) Str::uuid();
            }

            if ($this->hasCopyColumn('legacy_inv_id')) {
                $insert['legacy_inv_id'] = $this->nextLegacyInventoryId();
            }

            $insert['document_id'] = (string) $document['id'];
            $insert['branch_id'] = (string) $branch['id'];
            $insert['sigla_id'] = (string) $sigla['id'];

            if ($this->hasCopyColumn('legacy_doc_id') && isset($document['legacy_doc_id']) && $document['legacy_doc_id'] !== null) {
                $insert['legacy_doc_id'] = (int) $document['legacy_doc_id'];
            }

            if (array_key_exists('inventory_number', $payload) && $this->hasCopyColumn('inventory_number_raw')) {
                $insert['inventory_number_raw'] = $this->normalizeNullableString($payload['inventory_number']);
            }

            if (array_key_exists('registered_at', $payload) && $this->hasCopyColumn('registered_at')) {
                $insert['registered_at'] = Carbon::parse((string) $payload['registered_at'], 'UTC')->toDateTimeString();
            }

            if ($this->hasCopyColumn('needs_review')) {
                $insert['needs_review'] = $reviewState['needsReview'];
            }

            if ($this->hasCopyColumn('review_reason_codes')) {
                $insert['review_reason_codes'] = $this->toPgTextArray($reviewState['reviewReasonCodes']);
            }

            $institutionUnitId = $this->resolveReferenceValue($branch, $sigla, 'institution_unit_id');
            if ($institutionUnitId !== null && $this->hasCopyColumn('institution_unit_id')) {
                $insert['institution_unit_id'] = $institutionUnitId;
            }

            $campusId = $this->resolveReferenceValue($branch, $sigla, 'campus_id');
            if ($campusId !== null && $this->hasCopyColumn('campus_id')) {
                $insert['campus_id'] = $campusId;
            }

            if ($this->hasCopyColumn('created_at')) {
                $insert['created_at'] = $now->toDateTimeString();
            }

            if ($this->hasCopyColumn('updated_at')) {
                $insert['updated_at'] = $now->toDateTimeString();
            }

            DB::connection('pgsql')->table(self::COPY_TABLE)->insert($insert);

            $copyId = (string) ($insert['id'] ?? '');
            if ($copyId === '') {
                throw new InternalCopyWriteException('server_error', 500, 'Copy was created but id is not available for response.');
            }

            $after = $this->requireCopyDetail($copyId);

            $this->recordAudit(
                action: 'internal_copy_created',
                copyId: $copyId,
                previousState: null,
                newState: $after,
                context: $context,
                metadata: [
                    'operation' => 'create',
                    'document_id' => (string) $document['id'],
                    'branch_id' => (string) $branch['id'],
                    'sigla_id' => (string) $sigla['id'],
                    'generated_legacy_inv_id' => $insert['legacy_inv_id'] ?? null,
                ],
            );

            return [
                'data' => $after,
                'source' => self::COPY_TABLE,
            ];
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function patchCopy(string $copyId, array $payload, array $context): array
    {
        $this->rejectUnsupportedFields($payload, array_keys(self::PATCH_FIELD_TO_COLUMN), 'patch');

        if ($payload === []) {
            throw new InternalCopyWriteException('no_mutable_fields_provided', 400, 'At least one mutable copy field must be provided.');
        }

        return DB::connection('pgsql')->transaction(function () use ($copyId, $payload, $context): array {
            $lockedRow = DB::connection('pgsql')
                ->table(self::COPY_TABLE)
                ->where('id', $copyId)
                ->lockForUpdate()
                ->first();

            if ($lockedRow === null) {
                throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
            }

            $before = $this->requireCopyDetail($copyId);
            $row = (array) $lockedRow;

            $reviewState = $this->resolveReviewState(
                payload: $payload,
                currentNeedsReview: (bool) ($row['needs_review'] ?? false),
                currentReviewReasonCodes: $this->normalizePgArray($row['review_reason_codes'] ?? null),
            );

            $update = [];

            if ($this->hasCopyColumn('needs_review') && array_key_exists('needs_review', $payload)) {
                $update['needs_review'] = $reviewState['needsReview'];
            }

            if ($this->hasCopyColumn('review_reason_codes') && (
                array_key_exists('review_reason_codes', $payload)
                || (array_key_exists('needs_review', $payload) && $reviewState['needsReview'] === false)
            )) {
                $update['review_reason_codes'] = $this->toPgTextArray($reviewState['reviewReasonCodes']);
            }

            if ($update === []) {
                throw new InternalCopyWriteException('no_mutable_fields_provided', 400, 'At least one mutable copy field must be provided.');
            }

            if ($this->hasCopyColumn('updated_at')) {
                $update['updated_at'] = Carbon::now('UTC')->toDateTimeString();
            }

            DB::connection('pgsql')
                ->table(self::COPY_TABLE)
                ->where('id', $copyId)
                ->update($update);

            $after = $this->requireCopyDetail($copyId);

            $this->recordAudit(
                action: 'internal_copy_updated',
                copyId: $copyId,
                previousState: $before,
                newState: $after,
                context: $context,
                metadata: [
                    'operation' => 'patch',
                    'updated_fields' => array_values(array_keys($payload)),
                ],
            );

            return [
                'data' => $after,
                'source' => self::COPY_TABLE,
            ];
        });
    }

    /**
     * @param list<string> $allowedFields
     * @param array<string, mixed> $payload
     */
    private function rejectUnsupportedFields(array $payload, array $allowedFields, string $operation): void
    {
        $unsupported = array_values(array_diff(array_keys($payload), $allowedFields));

        if ($unsupported === []) {
            return;
        }

        throw new InternalCopyWriteException(
            'unsupported_mutation_field',
            400,
            'Field "' . $unsupported[0] . '" is not allowed for copy ' . $operation . '.',
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $currentReviewReasonCodes
     * @return array{needsReview: bool, reviewReasonCodes: array<int, string>}
     */
    private function resolveReviewState(array $payload, bool $currentNeedsReview, array $currentReviewReasonCodes): array
    {
        $needsReview = array_key_exists('needs_review', $payload)
            ? (bool) $payload['needs_review']
            : $currentNeedsReview;

        $reviewReasonCodes = array_key_exists('review_reason_codes', $payload)
            ? array_values(array_map('strval', Arr::wrap($payload['review_reason_codes'] ?? [])))
            : $currentReviewReasonCodes;

        if ($needsReview === false && $reviewReasonCodes !== []) {
            throw new InternalCopyWriteException(
                'invalid_review_state',
                400,
                'review_reason_codes may only be provided when needs_review is true.',
            );
        }

        return [
            'needsReview' => $needsReview,
            'reviewReasonCodes' => $needsReview ? $reviewReasonCodes : [],
        ];
    }

    /**
     * @param array<string, mixed> $branch
     * @param array<string, mixed> $sigla
     */
    private function assertBranchLocationCoherence(array $branch, array $sigla): void
    {
        if ((string) ($sigla['branch_id'] ?? '') !== (string) ($branch['id'] ?? '')) {
            throw new InternalCopyWriteException(
                'branch_location_mismatch',
                409,
                'The requested location does not belong to the requested branch.',
            );
        }

        foreach (['institution_unit_id', 'campus_id'] as $column) {
            $branchValue = isset($branch[$column]) && $branch[$column] !== null ? (string) $branch[$column] : null;
            $siglaValue = isset($sigla[$column]) && $sigla[$column] !== null ? (string) $sigla[$column] : null;

            if ($branchValue !== null && $siglaValue !== null && $branchValue !== $siglaValue) {
                throw new InternalCopyWriteException(
                    'branch_location_mismatch',
                    409,
                    'The requested location is not coherent with branch ownership metadata.',
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $branch
     * @param array<string, mixed> $sigla
     */
    private function resolveReferenceValue(array $branch, array $sigla, string $column): ?string
    {
        $siglaValue = isset($sigla[$column]) && $sigla[$column] !== null ? (string) $sigla[$column] : null;
        $branchValue = isset($branch[$column]) && $branch[$column] !== null ? (string) $branch[$column] : null;

        return $siglaValue ?? $branchValue;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireDocument(string $documentId): array
    {
        $query = DB::connection('pgsql')
            ->table(self::DOCUMENT_TABLE)
            ->select(['id']);

        if (Schema::connection('pgsql')->hasColumn(self::DOCUMENT_TABLE, 'legacy_doc_id')) {
            $query->addSelect('legacy_doc_id');
        }

        $row = $query->where('id', $documentId)->first();

        if ($row === null) {
            throw new InternalCopyWriteException('document_not_found', 404, 'Document not found.');
        }

        return (array) $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireBranch(string $branchId): array
    {
        $row = DB::connection('pgsql')
            ->table(self::BRANCH_TABLE)
            ->select(['id', 'institution_unit_id', 'campus_id'])
            ->where('id', $branchId)
            ->first();

        if ($row === null) {
            throw new InternalCopyWriteException('branch_not_found', 404, 'Branch not found.');
        }

        return (array) $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireSigla(string $siglaId): array
    {
        $row = DB::connection('pgsql')
            ->table(self::SIGLA_TABLE)
            ->select(['id', 'branch_id', 'institution_unit_id', 'campus_id'])
            ->where('id', $siglaId)
            ->first();

        if ($row === null) {
            throw new InternalCopyWriteException('location_not_found', 404, 'Location not found.');
        }

        return (array) $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCopyDetail(string $copyId): array
    {
        $detail = $this->readService->findCopyDetail($copyId);

        if ($detail === null) {
            throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
        }

        return $detail;
    }

    private function nextLegacyInventoryId(): int
    {
        DB::connection('pgsql')->statement('LOCK TABLE ' . self::COPY_TABLE . ' IN EXCLUSIVE MODE');

        $max = DB::connection('pgsql')
            ->table(self::COPY_TABLE)
            ->max('legacy_inv_id');

        return ((int) $max) + 1;
    }

    private function hasCopyColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::COPY_TABLE, $column);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param array<int, string> $values
     */
    private function toPgTextArray(array $values): string
    {
        if ($values === []) {
            return '{}';
        }

        $escaped = array_map(static function (string $value): string {
            $normalized = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return '"' . $normalized . '"';
        }, $values);

        return '{' . implode(',', $escaped) . '}';
    }

    /**
     * @return array<int, string>
     */
    private function normalizePgArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (! is_string($value) || $value === '' || $value === '{}') {
            return [];
        }

        $trimmed = trim($value, '{}');
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item, ' "'),
            explode(',', $trimmed)
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param array<string, mixed>|null $previousState
     * @param array<string, mixed> $newState
     * @param array<string, mixed> $context
     * @param array<string, mixed> $metadata
     */
    private function recordAudit(
        string $action,
        string $copyId,
        ?array $previousState,
        array $newState,
        array $context,
        array $metadata,
    ): void {
        CirculationAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_at' => Carbon::now('UTC'),
            'action' => $action,
            'entity_type' => 'copy',
            'entity_id' => $copyId,
            'reader_id' => null,
            'actor_user_id' => $context['actorUserId'] ?? null,
            'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
            'request_id' => $context['requestId'] ?? null,
            'correlation_id' => $context['correlationId'] ?? null,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'metadata' => array_filter([
                'actor_role' => $context['actorRole'] ?? null,
                'actor_login' => $context['actorLogin'] ?? null,
                'details' => $metadata,
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }
}