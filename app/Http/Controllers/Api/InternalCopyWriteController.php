<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\InternalCopyRetireService;
use App\Services\Library\InternalCopyWriteException;
use App\Services\Library\InternalCopyWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InternalCopyWriteController extends Controller
{
    public function store(Request $request, InternalCopyWriteService $service): JsonResponse
    {
        $validated = $this->validateOrError($request, [
            'document_id' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid'],
            'sigla_id' => ['required', 'uuid'],
            'inventory_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registered_at' => ['sometimes', 'nullable', 'date'],
            'needs_review' => ['sometimes', 'boolean'],
            'review_reason_codes' => ['sometimes', 'array', 'max:20'],
            'review_reason_codes.*' => ['string', 'max:64'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        try {
            $result = $service->createCopy(
                payload: Arr::except($request->all(), ['actor_user_id', 'request_id', 'correlation_id']),
                context: $this->context($request, $validated),
            );
        } catch (InternalCopyWriteException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json([
            'success' => true,
        ] + $result, 201);
    }

    public function patch(string $copyId, Request $request, InternalCopyWriteService $service): JsonResponse
    {
        if (! Str::isUuid($copyId)) {
            return response()->json([
                'error' => 'invalid_copy_id',
                'message' => 'Copy id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $this->validateOrError($request, [
            'needs_review' => ['sometimes', 'boolean'],
            'review_reason_codes' => ['sometimes', 'array', 'max:20'],
            'review_reason_codes.*' => ['string', 'max:64'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        try {
            $result = $service->patchCopy(
                copyId: $copyId,
                payload: Arr::except($request->all(), ['actor_user_id', 'request_id', 'correlation_id']),
                context: $this->context($request, $validated),
            );
        } catch (InternalCopyWriteException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json([
            'success' => true,
        ] + $result);
    }

    public function retire(string $copyId, Request $request, InternalCopyRetireService $service): JsonResponse
    {
        if (! Str::isUuid($copyId)) {
            return response()->json([
                'error' => 'invalid_copy_id',
                'message' => 'Copy id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $this->validateOrError($request, [
            'reason_code' => ['required', 'string', 'in:' . implode(',', InternalCopyRetireService::ALLOWED_REASON_CODES)],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'actor_user_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $reasonCode = (string) $validated['reason_code'];
        $note = isset($validated['note']) ? (string) $validated['note'] : null;

        // When reason_code=OTHER, note is required.
        if ($reasonCode === 'OTHER' && ($note === null || trim($note) === '')) {
            return response()->json([
                'error' => 'note_required_for_other',
                'message' => 'A note is required when reason_code is OTHER.',
                'success' => false,
            ], 400);
        }

        $normalizedNote = ($note !== null && trim($note) !== '') ? trim($note) : null;

        try {
            $result = $service->retireCopy(
                copyId: $copyId,
                reasonCode: $reasonCode,
                note: $normalizedNote,
                context: $this->context($request, $validated),
            );
        } catch (InternalCopyWriteException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json([
            'success' => true,
        ] + $result);
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<string, mixed>|JsonResponse
     */
    private function validateOrError(Request $request, array $rules): array|JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'invalid_request_body',
                'message' => (string) $validator->errors()->first(),
                'success' => false,
            ], 400);
        }

        return $validator->validated();
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
}