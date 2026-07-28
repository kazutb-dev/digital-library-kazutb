<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\Library\IntegrationDocumentManagementException;
use App\Services\Library\IntegrationDocumentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class DocumentManagementController extends Controller
{
    public function __construct(
        private readonly IntegrationDocumentManagementService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $this->validateOrError($request, [
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], 'invalid_filter_value');

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $result = $this->service->listDocuments(
                query: trim((string) ($validated['q'] ?? '')),
                page: (int) ($validated['page'] ?? 1),
                perPage: (int) ($validated['per_page'] ?? 20),
            );
        } catch (Throwable) {
            return $this->internalErrorResponse($request, 'Unexpected server error while listing documents.');
        }

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_document_id',
                message: 'Document id must be a valid UUID.',
            );
        }

        try {
            $document = $this->service->findDocument($id);
        } catch (Throwable) {
            return $this->internalErrorResponse($request, 'Unexpected server error while reading document.');
        }

        if ($document === null) {
            return $this->errorResponse(
                request: $request,
                status: 404,
                errorCode: 'not_found',
                reasonCode: 'document_not_found',
                message: 'Document not found.',
            );
        }

        return response()->json([
            'data' => $document,
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateOrError($request, [
            'title' => ['required', 'string', 'max:500'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'publisher_id' => ['nullable', 'uuid'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'language' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], 'invalid_request_body');

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $document = $this->service->createDocument(
                payload: $validated,
                context: $this->context($request),
            );
        } catch (IntegrationDocumentManagementException $e) {
            return $this->errorResponse($request, $e->httpStatus, $e->errorCode, $e->reasonCode, $e->getMessage());
        } catch (Throwable) {
            return $this->internalErrorResponse($request, 'Unexpected server error while creating document.');
        }

        return response()->json([
            'data' => $document,
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ], 201);
    }

    public function patch(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_document_id',
                message: 'Document id must be a valid UUID.',
            );
        }

        $validated = $this->validateOrError($request, [
            'title' => ['sometimes', 'string', 'max:500'],
            'isbn' => ['sometimes', 'nullable', 'string', 'max:32'],
            'publisher_id' => ['sometimes', 'nullable', 'uuid'],
            'publication_year' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:2100'],
            'language' => ['sometimes', 'nullable', 'string', 'max:32'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ], 'invalid_request_body');

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        if (array_key_exists('title', $validated) && trim((string) $validated['title']) === '') {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_request_body',
                message: 'Field "title" cannot be empty when provided.',
            );
        }

        if ($validated === []) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'no_mutable_fields_provided',
                message: 'At least one mutable metadata field must be provided.',
            );
        }

        try {
            $document = $this->service->patchDocument(
                id: $id,
                payload: $validated,
                context: $this->context($request),
            );
        } catch (IntegrationDocumentManagementException $e) {
            return $this->errorResponse($request, $e->httpStatus, $e->errorCode, $e->reasonCode, $e->getMessage());
        } catch (Throwable) {
            return $this->internalErrorResponse($request, 'Unexpected server error while updating document.');
        }

        return response()->json([
            'data' => $document,
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_document_id',
                message: 'Document id must be a valid UUID.',
            );
        }

        try {
            $document = $this->service->archiveDocument(
                id: $id,
                context: $this->context($request),
            );
        } catch (IntegrationDocumentManagementException $e) {
            return $this->errorResponse($request, $e->httpStatus, $e->errorCode, $e->reasonCode, $e->getMessage());
        } catch (Throwable) {
            return $this->internalErrorResponse($request, 'Unexpected server error while archiving document.');
        }

        return response()->json([
            'data' => $document,
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * @param array<string,mixed> $rules
     * @return array<string,mixed>|JsonResponse
     */
    private function validateOrError(Request $request, array $rules, string $reasonCode): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: $reasonCode,
                message: (string) $validator->errors()->first(),
            );
        }

        return $validator->validated();
    }

    /**
     * @return array<string,string>
     */
    private function context(Request $request): array
    {
        return [
            'authenticated_client_ref' => (string) $request->attributes->get('integration.authenticated_client_ref'),
            'operator_id' => trim((string) $request->header('X-Operator-Id', '')),
            'request_id' => (string) $request->attributes->get('integration.request_id'),
            'correlation_id' => (string) $request->attributes->get('integration.correlation_id'),
        ];
    }

    private function errorResponse(
        Request $request,
        int $status,
        string $errorCode,
        string $reasonCode,
        string $message,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'error_code' => $errorCode,
                'reason_code' => $reasonCode,
                'message' => $message,
            ],
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    private function internalErrorResponse(Request $request, string $message): JsonResponse
    {
        return $this->errorResponse(
            request: $request,
            status: 500,
            errorCode: 'server_error',
            reasonCode: 'internal_failure',
            message: $message,
        );
    }
}
