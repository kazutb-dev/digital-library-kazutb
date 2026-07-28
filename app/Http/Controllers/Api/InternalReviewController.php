<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\InternalCopyWriteException;
use App\Services\Library\InternalDocumentReviewException;
use App\Services\Library\InternalDocumentReviewWorkflowService;
use App\Services\Library\InternalReaderReviewException;
use App\Services\Library\InternalReaderReviewWorkflowService;
use App\Services\Library\InternalReviewWorkflowService;
use App\Services\Library\InternalTriageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InternalReviewController extends Controller
{
    public function copyQueue(Request $request, InternalReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->listCopyReviewQueue(
            reasonCode: isset($validated['reason_code']) ? (string) $validated['reason_code'] : null,
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }

    public function copySummary(Request $request, InternalReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'top_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($service->copyReviewSummary(
            topReasonCodesLimit: (int) ($validated['top_limit'] ?? 5),
        ));
    }

    public function resolveCopy(string $copyId, Request $request, InternalReviewWorkflowService $service): JsonResponse
    {
        if (! Str::isUuid($copyId)) {
            return response()->json([
                'error' => 'invalid_copy_id',
                'message' => 'Copy id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        try {
            $result = $service->resolveCopyReview(
                copyId: $copyId,
                resolutionNote: isset($validated['resolution_note']) ? trim((string) $validated['resolution_note']) : null,
                context: $this->context($request, $validated),
            );
        } catch (InternalCopyWriteException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json(['success' => true] + $result);
    }

    public function documentQueue(Request $request, InternalDocumentReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->listDocumentReviewQueue(
            reasonCode: isset($validated['reason_code']) ? (string) $validated['reason_code'] : null,
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }

    public function documentSummary(Request $request, InternalDocumentReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'top_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($service->documentReviewSummary(
            topReasonCodesLimit: (int) ($validated['top_limit'] ?? 5),
        ));
    }

    public function flagDocument(string $documentId, Request $request, InternalDocumentReviewWorkflowService $service): JsonResponse
    {
        if (! Str::isUuid($documentId)) {
            return response()->json([
                'error' => 'invalid_document_id',
                'message' => 'Document id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $request->validate([
            'reason_codes' => ['required', 'array', 'min:1', 'max:10'],
            'reason_codes.*' => ['required', 'string', 'max:64'],
            'flag_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        try {
            $result = $service->flagDocumentForReview(
                documentId: $documentId,
                reasonCodes: (array) $validated['reason_codes'],
                flagNote: isset($validated['flag_note']) ? trim((string) $validated['flag_note']) : null,
                context: $this->context($request, $validated),
            );
        } catch (InternalDocumentReviewException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json(['success' => true] + $result);
    }

    public function resolveDocument(string $documentId, Request $request, InternalDocumentReviewWorkflowService $service): JsonResponse
    {
        if (! Str::isUuid($documentId)) {
            return response()->json([
                'error' => 'invalid_document_id',
                'message' => 'Document id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        try {
            $result = $service->resolveDocumentReview(
                documentId: $documentId,
                resolutionNote: isset($validated['resolution_note']) ? trim((string) $validated['resolution_note']) : null,
                context: $this->context($request, $validated),
            );
        } catch (InternalDocumentReviewException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json(['success' => true] + $result);
    }

    public function readerQueue(Request $request, InternalReaderReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->listReaderReviewQueue(
            reasonCode: isset($validated['reason_code']) ? (string) $validated['reason_code'] : null,
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }

    public function readerSummary(Request $request, InternalReaderReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'top_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($service->readerReviewSummary(
            topReasonCodesLimit: (int) ($validated['top_limit'] ?? 5),
        ));
    }

    public function resolveReader(string $readerId, Request $request, InternalReaderReviewWorkflowService $service): JsonResponse
    {
        if (! Str::isUuid($readerId)) {
            return response()->json([
                'error' => 'invalid_reader_id',
                'message' => 'Reader id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        try {
            $result = $service->resolveReaderReview(
                readerId: $readerId,
                resolutionNote: isset($validated['resolution_note']) ? trim((string) $validated['resolution_note']) : null,
                context: $this->context($request, $validated),
            );
        } catch (InternalReaderReviewException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json(['success' => true] + $result);
    }

    // ── Bulk operations ────────────────────────────────────────────────

    public function bulkResolveCopies(Request $request, InternalReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'uuid'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $context = $this->context($request, $validated);
        $note = isset($validated['resolution_note']) ? trim((string) $validated['resolution_note']) : null;

        $results = $this->executeBulk($validated['ids'], function (string $id) use ($service, $note, $context) {
            $service->resolveCopyReview(copyId: $id, resolutionNote: $note, context: $context);
        });

        return response()->json(['success' => true, 'summary' => $results['summary'], 'results' => $results['results']]);
    }

    public function bulkResolveDocuments(Request $request, InternalDocumentReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'uuid'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $context = $this->context($request, $validated);
        $note = isset($validated['resolution_note']) ? trim((string) $validated['resolution_note']) : null;

        $results = $this->executeBulk($validated['ids'], function (string $id) use ($service, $note, $context) {
            $service->resolveDocumentReview(documentId: $id, resolutionNote: $note, context: $context);
        });

        return response()->json(['success' => true, 'summary' => $results['summary'], 'results' => $results['results']]);
    }

    public function bulkFlagDocuments(Request $request, InternalDocumentReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'uuid'],
            'reason_codes' => ['required', 'array', 'min:1', 'max:10'],
            'reason_codes.*' => ['required', 'string', 'max:64'],
            'flag_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $context = $this->context($request, $validated);
        $reasonCodes = (array) $validated['reason_codes'];
        $flagNote = isset($validated['flag_note']) ? trim((string) $validated['flag_note']) : null;

        $results = $this->executeBulk($validated['ids'], function (string $id) use ($service, $reasonCodes, $flagNote, $context) {
            $service->flagDocumentForReview(documentId: $id, reasonCodes: $reasonCodes, flagNote: $flagNote, context: $context);
        });

        return response()->json(['success' => true, 'summary' => $results['summary'], 'results' => $results['results']]);
    }

    public function bulkResolveReaders(Request $request, InternalReaderReviewWorkflowService $service): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'uuid'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $context = $this->context($request, $validated);
        $note = isset($validated['resolution_note']) ? trim((string) $validated['resolution_note']) : null;

        $results = $this->executeBulk($validated['ids'], function (string $id) use ($service, $note, $context) {
            $service->resolveReaderReview(readerId: $id, resolutionNote: $note, context: $context);
        });

        return response()->json(['success' => true, 'summary' => $results['summary'], 'results' => $results['results']]);
    }

    /**
     * Process a list of IDs through a callback, collecting per-item results.
     *
     * @param  list<string>  $ids
     * @param  callable(string): void  $operation
     * @return array{summary: array{total: int, succeeded: int, failed: int}, results: list<array{id: string, success: bool, error?: string, error_code?: string}>}
     */
    private function executeBulk(array $ids, callable $operation): array
    {
        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $operation($id);
                $results[] = ['id' => $id, 'success' => true];
                $succeeded++;
            } catch (\Throwable $e) {
                $errorCode = method_exists($e, 'errorCode') ? $e->errorCode() : 'unexpected_error';
                $results[] = ['id' => $id, 'success' => false, 'error' => $e->getMessage(), 'error_code' => $errorCode];
                $failed++;
            }
        }

        return [
            'summary' => ['total' => count($ids), 'succeeded' => $succeeded, 'failed' => $failed],
            'results' => $results,
        ];
    }

    // ── Triage ──────────────────────────────────────────────────────────

    public function stewardshipMetrics(InternalTriageService $service): JsonResponse
    {
        return response()->json($service->stewardshipMetrics());
    }

    public function triageSummary(Request $request, InternalTriageService $service): JsonResponse
    {
        $validated = $request->validate([
            'top_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($service->triageSummary(
            topReasonCodesLimit: (int) ($validated['top_limit'] ?? 5),
        ));
    }

    public function triageReasonCodes(Request $request, InternalTriageService $service): JsonResponse
    {
        $validated = $request->validate([
            'top_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'include_per_entity' => ['nullable', 'string'],
        ]);

        $includePerEntity = in_array(
            mb_strtolower(trim((string) ($validated['include_per_entity'] ?? ''))),
            ['1', 'true', 'yes'],
            true
        );

        return response()->json($service->triageReasonCodes(
            topLimit: (int) ($validated['top_limit'] ?? 10),
            includePerEntity: $includePerEntity,
        ));
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function forbiddenActorOverrideResponse(Request $request, array $validated): ?JsonResponse
    {
        if (! array_key_exists('actor_user_id', $validated) || $validated['actor_user_id'] === null) {
            return null;
        }

        $staffUser = $request->attributes->get('internal_staff_user');
        if (! is_array($staffUser)) {
            return null;
        }

        $sessionUserId = (string) ($staffUser['id'] ?? '');
        $sessionRole = mb_strtolower(trim((string) ($staffUser['role'] ?? '')));
        $requestedActorUserId = (string) $validated['actor_user_id'];

        if ($requestedActorUserId === '' || $requestedActorUserId === $sessionUserId) {
            return null;
        }

        if ($sessionRole === 'admin') {
            return null;
        }

        return response()->json([
            'error' => 'insufficient_staff_role',
            'message' => 'Only admin staff can override actor_user_id.',
            'success' => false,
        ], 403);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function context(Request $request, array $validated): array
    {
        $staffUser = $request->attributes->get('internal_staff_user');
        $sessionUserId = is_array($staffUser) ? (string) ($staffUser['id'] ?? '') : '';
        $actorUserId = isset($validated['actor_user_id'])
            ? (string) $validated['actor_user_id']
            : (Str::isUuid($sessionUserId) ? $sessionUserId : null);

        return [
            'actorUserId' => $actorUserId,
            'actorType' => 'staff_operator',
            'actorRole' => is_array($staffUser) ? (string) ($staffUser['role'] ?? '') : null,
            'actorLogin' => is_array($staffUser) ? (string) ($staffUser['login'] ?? '') : null,
            'requestId' => isset($validated['request_id']) ? (string) $validated['request_id'] : null,
            'correlationId' => isset($validated['correlation_id']) ? (string) $validated['correlation_id'] : null,
        ];
    }
}
