<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\CatalogEnrichmentService;
use App\Services\Library\IsbnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InternalEnrichmentController extends Controller
{
    private CatalogEnrichmentService $enrichmentService;

    private IsbnService $isbnService;

    public function __construct(CatalogEnrichmentService $enrichmentService, IsbnService $isbnService)
    {
        $this->enrichmentService = $enrichmentService;
        $this->isbnService = $isbnService;
    }

    /**
     * GET /api/v1/internal/enrichment/stats
     * Enrichment gap statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->enrichmentService->enrichmentStats();

        return response()->json(['data' => $stats, 'source' => 'catalog_enrichment']);
    }

    /**
     * POST /api/v1/internal/enrichment/validate-isbn
     * Validate ISBN for a single document.
     */
    public function validateIsbn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        try {
            $result = $this->enrichmentService->validateDocumentIsbn($request->input('document_id'));

            return response()->json(['data' => $result, 'source' => 'isbn_validation']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/internal/enrichment/bulk-validate
     * Bulk-validate ISBNs for multiple documents.
     */
    public function bulkValidate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_ids' => 'sometimes|array|max:200',
            'document_ids.*' => 'uuid',
            'limit' => 'sometimes|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        $result = $this->enrichmentService->bulkValidateIsbns(
            $request->input('document_ids', []),
            $request->input('limit', 500),
        );

        return response()->json(['data' => $result, 'source' => 'isbn_bulk_validation']);
    }

    /**
     * GET /api/v1/internal/enrichment/lookup/{documentId}
     * Look up metadata for a document from OpenLibrary.
     */
    public function lookup(string $documentId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $documentId)) {
            return response()->json(['error' => 'validation_failed', 'message' => 'Invalid document ID format'], 422);
        }

        try {
            $result = $this->enrichmentService->lookupDocument($documentId);

            return response()->json(['data' => $result, 'source' => 'catalog_enrichment_lookup']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/internal/enrichment/apply/{documentId}
     * Apply enrichment suggestions to a document (with audit trail).
     */
    public function apply(Request $request, string $documentId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $documentId)) {
            return response()->json(['error' => 'validation_failed', 'message' => 'Invalid document ID format'], 422);
        }

        $validator = Validator::make($request->all(), [
            'fields' => 'required|array|min:1',
            'fields.publication_year' => 'sometimes|integer|min:1000|max:2100',
            'fields.title_display' => 'sometimes|string|max:1000',
            'fields.title_normalized' => 'sometimes|string|max:1000',
            'fields.subtitle_raw' => 'sometimes|string|max:500',
            'fields.subtitle_normalized' => 'sometimes|string|max:500',
            'fields.isbn_raw' => 'sometimes|string|max:30',
            'fields.isbn_normalized' => 'sometimes|string|max:20',
            'fields.isbn_is_valid' => 'sometimes|boolean',
            'fields.language_code' => 'sometimes|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        $staffUser = $request->attributes->get('internal_staff_user');

        try {
            $result = $this->enrichmentService->applyEnrichment(
                $documentId,
                $request->input('fields'),
                [
                    'actorUserId' => $staffUser['id'] ?? null,
                    'actorType' => 'staff_operator',
                    'enrichmentSource' => $request->input('source', 'openlibrary'),
                    'requestId' => $request->header('X-Request-ID'),
                ],
            );

            return response()->json(['data' => $result, 'source' => 'catalog_enrichment_apply']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/internal/enrichment/check-isbn
     * Pure ISBN validation (no database lookup).
     */
    public function checkIsbn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'isbn' => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        $result = $this->isbnService->validate($request->input('isbn'));

        return response()->json(['data' => $result, 'source' => 'isbn_check']);
    }
}
