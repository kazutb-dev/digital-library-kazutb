<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library\BookCopy;
use App\Models\Library\Reader;
use App\Services\Library\CirculationLoanReadService;
use App\Services\Library\CirculationLoanWriteService;
use App\Services\Library\CirculationWriteException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InternalCirculationController extends Controller
{
    public function showLoan(string $loanId, CirculationLoanReadService $service): JsonResponse
    {
        $loan = $service->findLoan($loanId);

        if ($loan === null) {
            return response()->json([
                'error' => 'loan_not_found',
                'message' => 'Loan not found.',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $loan,
            'success' => true,
        ]);
    }

    public function showActiveLoanForCopy(string $copyId, CirculationLoanReadService $service): JsonResponse
    {
        if (! BookCopy::query()->whereKey($copyId)->exists()) {
            return response()->json([
                'error' => 'copy_not_found',
                'message' => 'Copy not found.',
                'success' => false,
            ], 404);
        }

        $loan = $service->findActiveLoanByCopy($copyId);

        if ($loan === null) {
            return response()->json([
                'error' => 'active_loan_not_found',
                'message' => 'Active loan not found for copy.',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $loan,
            'success' => true,
        ]);
    }

    public function listReaderLoans(string $readerId, Request $request, CirculationLoanReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:active,returned'],
        ]);

        if (! Reader::query()->whereKey($readerId)->exists()) {
            return response()->json([
                'error' => 'reader_not_found',
                'message' => 'Reader not found.',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $service->findLoansByReader(
                readerId: $readerId,
                status: isset($validated['status']) ? (string) $validated['status'] : null,
            ),
            'success' => true,
        ]);
    }

    public function checkout(Request $request, CirculationLoanWriteService $service): JsonResponse
    {
        $validated = $request->validate([
            'reader_id' => ['required', 'uuid'],
            'copy_id' => ['required', 'uuid'],
            'due_at' => ['nullable', 'date'],
            'actor_user_id' => ['nullable', 'uuid'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'correlation_id' => ['nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $sessionActorId = $this->sessionStaffActorId($request);

        try {
            $result = $service->checkout(
                readerId: (string) $validated['reader_id'],
                copyId: (string) $validated['copy_id'],
                dueAt: isset($validated['due_at']) ? (string) $validated['due_at'] : null,
                context: [
                    'actorUserId' => isset($validated['actor_user_id']) ? (string) $validated['actor_user_id'] : $sessionActorId,
                    'requestId' => isset($validated['request_id']) ? (string) $validated['request_id'] : null,
                    'correlationId' => isset($validated['correlation_id']) ? (string) $validated['correlation_id'] : null,
                    'actorType' => 'staff_operator',
                ],
            );
        } catch (CirculationWriteException $exception) {
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

    public function returnCopy(Request $request, CirculationLoanWriteService $service): JsonResponse
    {
        $validated = $request->validate([
            'copy_id' => ['required', 'uuid'],
            'actor_user_id' => ['nullable', 'uuid'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'correlation_id' => ['nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $sessionActorId = $this->sessionStaffActorId($request);

        try {
            $result = $service->returnCopy(
                copyId: (string) $validated['copy_id'],
                context: [
                    'actorUserId' => isset($validated['actor_user_id']) ? (string) $validated['actor_user_id'] : $sessionActorId,
                    'requestId' => isset($validated['request_id']) ? (string) $validated['request_id'] : null,
                    'correlationId' => isset($validated['correlation_id']) ? (string) $validated['correlation_id'] : null,
                    'actorType' => 'staff_operator',
                ],
            );
        } catch (CirculationWriteException $exception) {
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

    public function renewLoan(string $loanId, Request $request, CirculationLoanWriteService $service): JsonResponse
    {
        if (! Str::isUuid($loanId)) {
            return response()->json([
                'error' => 'invalid_loan_id',
                'message' => 'Loan id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $validated = $request->validate([
            'new_due_at' => ['nullable', 'date'],
            'actor_user_id' => ['nullable', 'uuid'],
            'request_id' => ['nullable', 'string', 'max:128'],
            'correlation_id' => ['nullable', 'string', 'max:128'],
        ]);

        $overrideViolation = $this->forbiddenActorOverrideResponse($request, $validated);
        if ($overrideViolation !== null) {
            return $overrideViolation;
        }

        $sessionActorId = $this->sessionStaffActorId($request);

        try {
            $result = $service->renew(
                loanId: $loanId,
                allowOverdue: true,
                newDueAt: isset($validated['new_due_at']) ? (string) $validated['new_due_at'] : null,
                context: [
                    'actorUserId' => isset($validated['actor_user_id']) ? (string) $validated['actor_user_id'] : $sessionActorId,
                    'requestId' => isset($validated['request_id']) ? (string) $validated['request_id'] : null,
                    'correlationId' => isset($validated['correlation_id']) ? (string) $validated['correlation_id'] : null,
                    'actorType' => 'staff_operator',
                ],
            );
        } catch (CirculationWriteException $exception) {
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

    private function sessionStaffActorId(Request $request): ?string
    {
        $staffUser = $request->attributes->get('internal_staff_user');
        if (! is_array($staffUser)) {
            return null;
        }

        $sessionUserId = (string) ($staffUser['id'] ?? '');

        return Str::isUuid($sessionUserId) ? $sessionUserId : null;
    }
}
