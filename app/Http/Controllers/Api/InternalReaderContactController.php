<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\ReaderContactNormalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InternalReaderContactController extends Controller
{
    private ReaderContactNormalizationService $contactService;

    public function __construct(ReaderContactNormalizationService $contactService)
    {
        $this->contactService = $contactService;
    }

    /**
     * GET /api/v1/internal/reader-contacts/stats
     */
    public function stats(): JsonResponse
    {
        $stats = $this->contactService->contactStats();

        return response()->json(['data' => $stats, 'source' => 'reader_contacts']);
    }

    /**
     * GET /api/v1/internal/reader-contacts/{readerId}
     */
    public function contacts(string $readerId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $readerId)) {
            return response()->json(['error' => 'validation_failed', 'message' => 'Invalid reader ID'], 422);
        }

        try {
            $result = $this->contactService->readerContacts($readerId);

            return response()->json(['data' => $result, 'source' => 'reader_contacts']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * PUT /api/v1/internal/reader-contacts/{contactId}
     */
    public function update(Request $request, string $contactId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $contactId)) {
            return response()->json(['error' => 'validation_failed', 'message' => 'Invalid contact ID'], 422);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        $staffUser = $request->attributes->get('internal_staff_user');

        try {
            $result = $this->contactService->updateContact(
                $contactId,
                $request->input('value'),
                [
                    'actorUserId' => $staffUser['id'] ?? null,
                    'actorType' => 'staff_operator',
                    'requestId' => $request->header('X-Request-ID'),
                ],
            );

            return response()->json(['data' => $result, 'source' => 'reader_contacts']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/internal/reader-contacts/{readerId}/add
     */
    public function add(Request $request, string $readerId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $readerId)) {
            return response()->json(['error' => 'validation_failed', 'message' => 'Invalid reader ID'], 422);
        }

        $validator = Validator::make($request->all(), [
            'contact_type' => 'required|string|in:EMAIL,PHONE,email,phone',
            'value' => 'required|string|max:500',
            'is_primary' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        $staffUser = $request->attributes->get('internal_staff_user');

        try {
            $result = $this->contactService->addContact(
                $readerId,
                $request->input('contact_type'),
                $request->input('value'),
                $request->boolean('is_primary', false),
                [
                    'actorUserId' => $staffUser['id'] ?? null,
                    'actorType' => 'staff_operator',
                    'requestId' => $request->header('X-Request-ID'),
                ],
            );

            return response()->json(['data' => $result, 'source' => 'reader_contacts'], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'not_found', 'message' => $e->getMessage()], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/internal/reader-contacts/bulk-normalize
     */
    public function bulkNormalize(Request $request): JsonResponse
    {
        $limit = min((int) ($request->input('limit', 500)), 500);
        $result = $this->contactService->bulkNormalizeContacts($limit);

        return response()->json(['data' => $result, 'source' => 'reader_contact_normalization']);
    }

    /**
     * POST /api/v1/internal/reader-contacts/validate
     * Pure validation (no database).
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_type' => 'required|string|in:EMAIL,PHONE,email,phone',
            'value' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'validation_failed', 'details' => $validator->errors()], 422);
        }

        $result = $this->contactService->validateContact(
            $request->input('contact_type'),
            $request->input('value'),
        );

        return response()->json(['data' => $result, 'source' => 'contact_validation']);
    }
}
