<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catalog enrichment pipeline: validates ISBNs, fetches metadata from
 * OpenLibrary, and produces enrichment suggestions for steward review.
 */
class CatalogEnrichmentService
{
    private const DOCUMENT_TABLE = 'app.documents';

    private IsbnService $isbnService;

    public function __construct(IsbnService $isbnService)
    {
        $this->isbnService = $isbnService;
    }

    /**
     * Validate a single document's ISBN and update isbn_is_valid.
     *
     * @return array{documentId: string, isbn: string|null, validation: array<string, mixed>, updated: bool}
     */
    public function validateDocumentIsbn(string $documentId): array
    {
        $row = DB::connection('pgsql')
            ->table(self::DOCUMENT_TABLE)
            ->select(['id', 'isbn_raw', 'isbn_normalized', 'isbn_is_valid'])
            ->where('id', $documentId)
            ->first();

        if ($row === null) {
            throw new \RuntimeException('Document not found: ' . $documentId);
        }

        $isbn = $row->isbn_normalized ?? $row->isbn_raw ?? null;
        if ($isbn === null || trim($isbn) === '') {
            return [
                'documentId' => $documentId,
                'isbn' => null,
                'validation' => ['valid' => false, 'error' => 'No ISBN present'],
                'updated' => false,
            ];
        }

        $result = $this->isbnService->validate($isbn);

        DB::connection('pgsql')
            ->table(self::DOCUMENT_TABLE)
            ->where('id', $documentId)
            ->update([
                'isbn_is_valid' => $result['valid'],
                'updated_at' => Carbon::now('UTC')->toDateTimeString(),
            ]);

        return [
            'documentId' => $documentId,
            'isbn' => $isbn,
            'validation' => $result,
            'updated' => true,
        ];
    }

    /**
     * Bulk-validate ISBNs for a batch of documents.
     *
     * @param list<string> $documentIds  Empty = all documents with ISBNs
     * @return array{processed: int, valid: int, invalid: int, noIsbn: int, results: list<array>}
     */
    public function bulkValidateIsbns(array $documentIds = [], int $limit = 500): array
    {
        $query = DB::connection('pgsql')
            ->table(self::DOCUMENT_TABLE)
            ->select(['id', 'isbn_raw', 'isbn_normalized']);

        if (! empty($documentIds)) {
            $query->whereIn('id', array_slice($documentIds, 0, $limit));
        } else {
            $query->where(function ($q) {
                $q->whereNotNull('isbn_raw')->where('isbn_raw', '!=', '')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('isbn_normalized')->where('isbn_normalized', '!=', '');
                    });
            })->limit($limit);
        }

        $rows = $query->get();

        $results = [];
        $valid = 0;
        $invalid = 0;
        $noIsbn = 0;

        foreach ($rows as $row) {
            $isbn = $row->isbn_normalized ?? $row->isbn_raw ?? null;
            if ($isbn === null || trim($isbn) === '') {
                $noIsbn++;

                continue;
            }

            $validation = $this->isbnService->validate($isbn);

            DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $row->id)
                ->update([
                    'isbn_is_valid' => $validation['valid'],
                    'updated_at' => Carbon::now('UTC')->toDateTimeString(),
                ]);

            if ($validation['valid']) {
                $valid++;
            } else {
                $invalid++;
            }

            $results[] = [
                'documentId' => (string) $row->id,
                'isbn' => $isbn,
                'valid' => $validation['valid'],
                'format' => $validation['format'],
            ];
        }

        return [
            'processed' => count($results),
            'valid' => $valid,
            'invalid' => $invalid,
            'noIsbn' => $noIsbn,
            'results' => $results,
        ];
    }

    /**
     * Look up metadata for a single document from OpenLibrary.
     * Returns enrichment suggestions (does NOT auto-apply).
     *
     * @return array{documentId: string, lookup: array, suggestions: list<array>, currentData: array}
     */
    public function lookupDocument(string $documentId): array
    {
        $row = DB::connection('pgsql')
            ->table(self::DOCUMENT_TABLE)
            ->select([
                'id', 'isbn_raw', 'isbn_normalized', 'isbn_is_valid',
                'title_raw', 'title_display', 'subtitle_raw',
                'publication_year', 'language_code', 'publisher_id',
            ])
            ->where('id', $documentId)
            ->first();

        if ($row === null) {
            throw new \RuntimeException('Document not found: ' . $documentId);
        }

        $currentData = [
            'title' => $row->title_display ?? $row->title_raw,
            'subtitle' => $row->subtitle_raw,
            'publicationYear' => $row->publication_year,
            'languageCode' => $row->language_code,
            'isbn' => $row->isbn_normalized ?? $row->isbn_raw,
        ];

        $isbn = $row->isbn_normalized ?? $row->isbn_raw ?? null;

        // Try ISBN lookup first
        if ($isbn !== null && trim($isbn) !== '') {
            $lookup = $this->isbnService->lookupByIsbn($isbn);
        } else {
            // Fall back to title search
            $title = $row->title_display ?? $row->title_raw ?? '';
            if (trim($title) === '') {
                return [
                    'documentId' => $documentId,
                    'lookup' => ['found' => false, 'source' => 'none', 'error' => 'No ISBN or title available'],
                    'suggestions' => [],
                    'currentData' => $currentData,
                ];
            }
            $lookup = $this->isbnService->searchByTitle($title, $row->publication_year);

            if ($lookup['found'] && ! empty($lookup['results'])) {
                // Convert search result to same format
                $best = $lookup['results'][0];
                $lookup = [
                    'found' => true,
                    'source' => 'openlibrary_search',
                    'metadata' => [
                        'title' => $best['title'],
                        'authors' => $best['authors'],
                        'publishYear' => $best['publishYear'],
                        'publishers' => $best['publishers'] ?? [],
                        'isbn' => $best['isbn'],
                        'numberOfPages' => $best['pages'],
                    ],
                    'error' => null,
                    'alternativeResults' => array_slice($lookup['results'], 1),
                ];
            }
        }

        $suggestions = [];
        if ($lookup['found'] && ! empty($lookup['metadata'])) {
            $suggestions = $this->buildSuggestions($row, $lookup['metadata']);
        }

        return [
            'documentId' => $documentId,
            'lookup' => $lookup,
            'suggestions' => $suggestions,
            'currentData' => $currentData,
        ];
    }

    /**
     * Apply selected enrichment suggestions to a document (with audit trail).
     *
     * @param array<string, mixed> $fields  Field => value to apply (e.g., ['publication_year' => 2020])
     * @param array<string, mixed> $context Actor/request context
     * @return array{documentId: string, applied: list<string>, skipped: list<string>}
     */
    public function applyEnrichment(string $documentId, array $fields, array $context = []): array
    {
        $allowedFields = [
            'title_display', 'title_normalized', 'subtitle_raw', 'subtitle_normalized',
            'publication_year', 'language_code', 'isbn_raw', 'isbn_normalized', 'isbn_is_valid',
        ];

        return DB::connection('pgsql')->transaction(function () use ($documentId, $fields, $context, $allowedFields): array {
            $row = DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new \RuntimeException('Document not found: ' . $documentId);
            }

            $before = $this->snapshotDocument($row);
            $update = ['updated_at' => Carbon::now('UTC')->toDateTimeString()];
            $applied = [];
            $skipped = [];

            foreach ($fields as $field => $value) {
                if (in_array($field, $allowedFields, true)) {
                    $update[$field] = $value;
                    $applied[] = $field;
                } else {
                    $skipped[] = $field;
                }
            }

            if (empty($applied)) {
                return ['documentId' => $documentId, 'applied' => [], 'skipped' => $skipped];
            }

            DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->update($update);

            $afterRow = DB::connection('pgsql')
                ->table(self::DOCUMENT_TABLE)
                ->where('id', $documentId)
                ->first();

            CirculationAuditEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_at' => Carbon::now('UTC'),
                'action' => 'catalog_enrichment_applied',
                'entity_type' => 'document',
                'entity_id' => $documentId,
                'reader_id' => null,
                'actor_user_id' => $context['actorUserId'] ?? null,
                'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
                'request_id' => $context['requestId'] ?? null,
                'correlation_id' => $context['correlationId'] ?? null,
                'previous_state' => $before,
                'new_state' => $this->snapshotDocument($afterRow),
                'metadata' => [
                    'details' => [
                        'enrichment_fields' => $applied,
                        'skipped_fields' => $skipped,
                        'source' => $context['enrichmentSource'] ?? 'openlibrary',
                    ],
                ],
            ]);

            return ['documentId' => $documentId, 'applied' => $applied, 'skipped' => $skipped];
        });
    }

    /**
     * Get enrichment statistics: how many documents could benefit from enrichment.
     */
    public function enrichmentStats(): array
    {
        $stats = DB::connection('pgsql')->select("
            SELECT
                COUNT(*) as total_documents,
                COUNT(CASE WHEN isbn_raw IS NULL OR isbn_raw = '' THEN 1 END) as missing_isbn,
                COUNT(CASE WHEN isbn_is_valid = false THEN 1 END) as invalid_isbn,
                COUNT(CASE WHEN isbn_is_valid = true THEN 1 END) as valid_isbn,
                COUNT(CASE WHEN title_display IS NULL OR title_display = '' THEN 1 END) as missing_title,
                COUNT(CASE WHEN publication_year IS NULL THEN 1 END) as missing_year,
                COUNT(CASE WHEN language_code IS NULL OR language_code = '' THEN 1 END) as missing_language,
                COUNT(CASE WHEN publisher_id IS NULL THEN 1 END) as missing_publisher
            FROM app.documents
        ");

        $row = $stats[0] ?? null;
        if ($row === null) {
            return ['totalDocuments' => 0, 'gaps' => []];
        }

        $enrichable = DB::connection('pgsql')->selectOne("
            SELECT COUNT(*) as cnt FROM app.documents
            WHERE isbn_is_valid = true
              AND (publication_year IS NULL OR publisher_id IS NULL OR subtitle_raw IS NULL OR subtitle_raw = '')
        ");

        return [
            'totalDocuments' => (int) $row->total_documents,
            'gaps' => [
                'missingIsbn' => (int) $row->missing_isbn,
                'invalidIsbn' => (int) $row->invalid_isbn,
                'validIsbn' => (int) $row->valid_isbn,
                'missingTitle' => (int) $row->missing_title,
                'missingYear' => (int) $row->missing_year,
                'missingLanguage' => (int) $row->missing_language,
                'missingPublisher' => (int) $row->missing_publisher,
            ],
            'enrichableByIsbn' => (int) ($enrichable->cnt ?? 0),
        ];
    }

    /**
     * Build enrichment suggestions by comparing current data with external metadata.
     *
     * @return list<array{field: string, column: string, current: mixed, suggested: mixed, confidence: string}>
     */
    private function buildSuggestions(object $row, array $metadata): array
    {
        $suggestions = [];

        // Publication year
        if ($row->publication_year === null && ! empty($metadata['publishYear'])) {
            $suggestions[] = [
                'field' => 'Год публикации',
                'column' => 'publication_year',
                'current' => null,
                'suggested' => (int) $metadata['publishYear'],
                'confidence' => 'high',
            ];
        }

        // Subtitle
        if ((($row->subtitle_raw ?? '') === '') && ! empty($metadata['subtitle'])) {
            $suggestions[] = [
                'field' => 'Подзаголовок',
                'column' => 'subtitle_raw',
                'current' => null,
                'suggested' => (string) $metadata['subtitle'],
                'confidence' => 'medium',
            ];
        }

        // ISBN (if document has no ISBN but search found one)
        $currentIsbn = $row->isbn_normalized ?? $row->isbn_raw ?? null;
        if (($currentIsbn === null || trim($currentIsbn) === '') && ! empty($metadata['isbn'])) {
            $suggestions[] = [
                'field' => 'ISBN',
                'column' => 'isbn_normalized',
                'current' => null,
                'suggested' => (string) $metadata['isbn'],
                'confidence' => 'medium',
            ];
        }

        // Title (only if current is empty)
        $currentTitle = $row->title_display ?? $row->title_raw ?? '';
        if (trim($currentTitle) === '' && ! empty($metadata['title'])) {
            $suggestions[] = [
                'field' => 'Название',
                'column' => 'title_display',
                'current' => null,
                'suggested' => (string) $metadata['title'],
                'confidence' => 'medium',
            ];
        }

        return $suggestions;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotDocument(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'title_display' => $row->title_display ?? null,
            'subtitle_raw' => $row->subtitle_raw ?? null,
            'isbn_raw' => $row->isbn_raw ?? null,
            'isbn_normalized' => $row->isbn_normalized ?? null,
            'isbn_is_valid' => $row->isbn_is_valid ?? null,
            'publication_year' => $row->publication_year ?? null,
            'language_code' => $row->language_code ?? null,
        ];
    }
}
