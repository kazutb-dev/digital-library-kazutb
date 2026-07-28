<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class IntegrationDocumentManagementService
{
    private const TABLE = 'app.documents';

    /**
     * @var array<string, string>
     */
    private const FIELD_TO_COLUMN = [
        'title' => 'title_raw',
        'isbn' => 'isbn_raw',
        'publisher_id' => 'publisher_id',
        'publication_year' => 'publication_year',
        'language' => 'language_code',
        'description' => 'summary',
    ];

    /**
     * @return array{data:list<array<string,mixed>>, meta:array<string,int>}
     */
    public function listDocuments(string $query, int $page, int $perPage): array
    {
        $page = max($page, 1);
        $perPage = min(max($perPage, 1), 100);

        $queryBuilder = DB::connection('pgsql')
            ->table(self::TABLE . ' as d');

        if ($query !== '') {
            $q = '%' . mb_strtolower($query) . '%';
            $queryBuilder->where(function ($inner) use ($q): void {
                $inner
                    ->whereRaw("LOWER(COALESCE(d.title_raw, '')) LIKE ?", [$q])
                    ->orWhereRaw("LOWER(COALESCE(d.isbn_raw, '')) LIKE ?", [$q]);
            });
        }

        $total = (clone $queryBuilder)->count();

        $rows = $queryBuilder
            ->select($this->listSelectColumns())
            ->when($this->hasColumn('created_at'), fn ($q) => $q->orderByDesc('d.created_at'))
            ->orderByDesc('d.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->map(fn (object $row): array => $this->toDocumentShape((array) $row))->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => (int) $total,
                'pages' => $total === 0 ? 1 : (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findDocument(string $id): ?array
    {
        $row = DB::connection('pgsql')
            ->table(self::TABLE . ' as d')
            ->select($this->detailSelectColumns())
            ->where('d.id', $id)
            ->first();

        return $row ? $this->toDocumentShape((array) $row) : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $context
     * @return array<string,mixed>
     */
    public function createDocument(array $payload, array $context): array
    {
        $supported = $this->supportedWritableColumns();
        $mapped = $this->mapPayloadToColumns($payload, $supported);

        if (! isset($mapped['title_raw']) || trim((string) $mapped['title_raw']) === '') {
            throw new IntegrationDocumentManagementException(
                errorCode: 'invalid_request',
                reasonCode: 'missing_required_metadata',
                message: 'Field "title" is required.',
                httpStatus: 400,
            );
        }

        $now = Carbon::now('UTC');

        if ($this->hasColumn('id') && ! isset($mapped['id'])) {
            $mapped['id'] = (string) Str::uuid();
        }

        // Some production-like schemas require legacy_doc_id as a unique non-null integer.
        if ($this->hasColumn('legacy_doc_id') && ! isset($mapped['legacy_doc_id'])) {
            $mapped['legacy_doc_id'] = $this->nextLegacyDocId();
        }

        if ($this->hasColumn('created_at')) {
            $mapped['created_at'] = $now->toDateTimeString();
        }

        if ($this->hasColumn('updated_at')) {
            $mapped['updated_at'] = $now->toDateTimeString();
        }

        DB::connection('pgsql')->table(self::TABLE)->insert($mapped);

        $id = (string) ($mapped['id'] ?? '');
        if ($id === '') {
            throw new IntegrationDocumentManagementException(
                errorCode: 'server_error',
                reasonCode: 'missing_document_identifier',
                message: 'Document was created but id is not available for response.',
                httpStatus: 500,
            );
        }

        $after = $this->requireDocument($id);

        $this->recordAudit(
            action: 'integration_document_created',
            documentId: $id,
            previousState: null,
            newState: $after,
            context: $context,
            metadata: ['operation' => 'create'],
        );

        return $after;
    }

    private function nextLegacyDocId(): int
    {
        $max = DB::connection('pgsql')
            ->table(self::TABLE)
            ->max('legacy_doc_id');

        return ((int) $max) + 1;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $context
     * @return array<string,mixed>
     */
    public function patchDocument(string $id, array $payload, array $context): array
    {
        $before = $this->requireDocument($id);
        $supported = $this->supportedWritableColumns();
        $mapped = $this->mapPayloadToColumns($payload, $supported);

        if ($mapped === []) {
            throw new IntegrationDocumentManagementException(
                errorCode: 'invalid_request',
                reasonCode: 'no_mutable_fields_provided',
                message: 'At least one supported mutable metadata field must be provided.',
                httpStatus: 400,
            );
        }

        if ($this->hasColumn('updated_at')) {
            $mapped['updated_at'] = Carbon::now('UTC')->toDateTimeString();
        }

        DB::connection('pgsql')->table(self::TABLE)->where('id', $id)->update($mapped);

        $after = $this->requireDocument($id);

        $this->recordAudit(
            action: 'integration_document_updated',
            documentId: $id,
            previousState: $before,
            newState: $after,
            context: $context,
            metadata: [
                'operation' => 'patch',
                'updated_fields' => array_values(array_keys($payload)),
            ],
        );

        return $after;
    }

    /**
     * @param array<string,string> $context
     * @return array<string,mixed>
     */
    public function archiveDocument(string $id, array $context): array
    {
        $before = $this->requireDocument($id);
        $now = Carbon::now('UTC');

        $update = [];

        if ($this->hasColumn('is_active')) {
            $update['is_active'] = false;
        }

        if ($this->hasColumn('status')) {
            $update['status'] = 'ARCHIVED';
        }

        if ($this->hasColumn('archived_at')) {
            $update['archived_at'] = $now->toDateTimeString();
        }

        if ($update === [] && $this->hasColumn('needs_review')) {
            $update['needs_review'] = true;

            if ($this->hasColumn('review_reason_codes')) {
                $existingCodes = Arr::wrap($before['review_reason_codes'] ?? []);
                $codes = array_values(array_unique(array_merge($existingCodes, ['ARCHIVED'])));
                $update['review_reason_codes'] = '{' . implode(',', $codes) . '}';
            }
        }

        if ($update === []) {
            throw new IntegrationDocumentManagementException(
                errorCode: 'server_error',
                reasonCode: 'archive_strategy_not_supported',
                message: 'Archive operation is not supported by current documents schema.',
                httpStatus: 500,
            );
        }

        if ($this->hasColumn('updated_at')) {
            $update['updated_at'] = $now->toDateTimeString();
        }

        DB::connection('pgsql')->table(self::TABLE)->where('id', $id)->update($update);

        $after = $this->requireDocument($id);

        $this->recordAudit(
            action: 'integration_document_archived',
            documentId: $id,
            previousState: $before,
            newState: $after,
            context: $context,
            metadata: [
                'operation' => 'archive',
                'archive_update_columns' => array_values(array_keys($update)),
            ],
        );

        return $after;
    }

    /**
     * @return list<string>
     */
    private function listSelectColumns(): array
    {
        $preferred = [
            'id',
            'title_raw',
            'isbn_raw',
            'publication_year',
            'language_code',
            'publisher_id',
            'summary',
            'needs_review',
            'review_reason_codes',
            'status',
            'is_active',
            'archived_at',
            'created_at',
            'updated_at',
        ];

        $available = [];
        foreach ($preferred as $column) {
            if ($this->hasColumn($column)) {
                $available[] = 'd.' . $column;
            }
        }

        return $available;
    }

    /**
     * @return list<string>
     */
    private function detailSelectColumns(): array
    {
        return $this->listSelectColumns();
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function toDocumentShape(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (string) $row['id'] : null,
            'title' => isset($row['title_raw']) ? (string) $row['title_raw'] : null,
            'isbn' => isset($row['isbn_raw']) ? (string) $row['isbn_raw'] : null,
            'publisher_id' => isset($row['publisher_id']) ? (string) $row['publisher_id'] : null,
            'publication_year' => isset($row['publication_year']) ? (int) $row['publication_year'] : null,
            'language' => isset($row['language_code']) ? (string) $row['language_code'] : null,
            'description' => isset($row['summary']) ? (string) $row['summary'] : null,
            'status' => isset($row['status']) ? (string) $row['status'] : null,
            'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : null,
            'archived_at' => $this->normalizeDateTime($row['archived_at'] ?? null),
            'needs_review' => isset($row['needs_review']) ? (bool) $row['needs_review'] : null,
            'review_reason_codes' => $this->normalizePgArray($row['review_reason_codes'] ?? null),
            'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
            'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
            'source' => self::TABLE,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,bool> $supported
     * @return array<string,mixed>
     */
    private function mapPayloadToColumns(array $payload, array $supported): array
    {
        $mapped = [];

        foreach (self::FIELD_TO_COLUMN as $apiField => $column) {
            if (! array_key_exists($apiField, $payload)) {
                continue;
            }

            if (! ($supported[$column] ?? false)) {
                throw new IntegrationDocumentManagementException(
                    errorCode: 'invalid_request',
                    reasonCode: 'unsupported_field_for_current_schema',
                    message: 'Field "' . $apiField . '" is not supported by current documents schema.',
                    httpStatus: 400,
                );
            }

            $mapped[$column] = $payload[$apiField];
        }

        return $mapped;
    }

    /**
     * @return array<string,bool>
     */
    private function supportedWritableColumns(): array
    {
        $supported = [];

        foreach (self::FIELD_TO_COLUMN as $column) {
            $supported[$column] = $this->hasColumn($column);
        }

        return $supported;
    }

    private function hasColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::TABLE, $column);
    }

    /**
     * @return array<string,mixed>
     */
    private function requireDocument(string $id): array
    {
        $document = $this->findDocument($id);

        if ($document === null) {
            throw new IntegrationDocumentManagementException(
                errorCode: 'not_found',
                reasonCode: 'document_not_found',
                message: 'Document not found.',
                httpStatus: 404,
            );
        }

        return $document;
    }

    /**
     * @param array<string,mixed>|null $previousState
     * @param array<string,mixed> $newState
     * @param array<string,string> $context
     * @param array<string,mixed> $metadata
     */
    private function recordAudit(
        string $action,
        string $documentId,
        ?array $previousState,
        array $newState,
        array $context,
        array $metadata,
    ): void {
        CirculationAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_at' => Carbon::now('UTC'),
            'action' => $action,
            'entity_type' => 'document',
            'entity_id' => $documentId,
            'reader_id' => null,
            'actor_user_id' => null,
            'actor_type' => 'integration_operator',
            'request_id' => $context['request_id'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'metadata' => [
                'operator_id' => $context['operator_id'] ?? null,
                'authenticated_client_ref' => $context['authenticated_client_ref'] ?? null,
                'details' => $metadata,
            ],
        ]);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')->toISOString();
    }

    /**
     * @return array<int,string>
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
}
