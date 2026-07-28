<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\Library\IntegrationReservationMutationException;
use App\Services\Library\IntegrationReservationWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ReservationMutateController extends Controller
{
    public function __construct(
        private readonly IntegrationReservationWriteService $service,
    ) {}

    public function approve(Request $request, string $id): JsonResponse
    {
        if (! $this->hasOperatorRole($request, 'reservations.approve')) {
            return $this->errorResponse(
                request: $request,
                status: 403,
                errorCode: 'forbidden',
                reasonCode: 'insufficient_operator_role',
                message: 'Operator role reservations.approve is required for approve command.',
            );
        }

        $operatorBranchId = $this->validatedOperatorBranchId($request);
        if ($operatorBranchId === null) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_operator_org_context',
                message: 'X-Operator-Org-Context must contain a valid UUID branch_id for mutate operations.',
            );
        }

        if (! Str::isUuid($id)) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_reservation_id',
                message: 'Reservation id must be a valid UUID.',
            );
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'missing_idempotency_key',
                message: 'Missing Idempotency-Key header.',
            );
        }

        try {
            $result = $this->service->approve(
                reservationId: $id,
                idempotencyKey: $idempotencyKey,
                context: $this->context($request),
            );

            return response()->json($result['body'], $result['status']);
        } catch (IntegrationReservationMutationException $e) {
            return $this->errorResponse(
                request: $request,
                status: $e->httpStatus,
                errorCode: $e->errorCode,
                reasonCode: $e->reasonCode,
                message: $e->getMessage(),
            );
        }
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        if (! $this->hasOperatorRole($request, 'reservations.reject')) {
            return $this->errorResponse(
                request: $request,
                status: 403,
                errorCode: 'forbidden',
                reasonCode: 'insufficient_operator_role',
                message: 'Operator role reservations.reject is required for reject command.',
            );
        }

        $operatorBranchId = $this->validatedOperatorBranchId($request);
        if ($operatorBranchId === null) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_operator_org_context',
                message: 'X-Operator-Org-Context must contain a valid UUID branch_id for mutate operations.',
            );
        }

        if (! Str::isUuid($id)) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_reservation_id',
                message: 'Reservation id must be a valid UUID.',
            );
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'missing_idempotency_key',
                message: 'Missing Idempotency-Key header.',
            );
        }

        $cancelOrigin = strtoupper(trim((string) $request->input('cancel_origin', '')));
        $cancelReasonCode = strtoupper(trim((string) $request->input('cancel_reason_code', '')));

        if ($cancelOrigin === '' || $cancelReasonCode === '') {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'missing_cancel_reason_or_origin',
                message: 'cancel_origin and cancel_reason_code are required for reject.',
            );
        }

        if ($cancelOrigin !== 'OPERATOR_REJECT') {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_cancel_origin',
                message: 'cancel_origin must be OPERATOR_REJECT for reject command.',
            );
        }

        try {
            $result = $this->service->reject(
                reservationId: $id,
                idempotencyKey: $idempotencyKey,
                cancelOrigin: $cancelOrigin,
                cancelReasonCode: $cancelReasonCode,
                context: $this->context($request),
            );

            return response()->json($result['body'], $result['status']);
        } catch (IntegrationReservationMutationException $e) {
            return $this->errorResponse(
                request: $request,
                status: $e->httpStatus,
                errorCode: $e->errorCode,
                reasonCode: $e->reasonCode,
                message: $e->getMessage(),
            );
        }
    }

    /**
     * @return array{authenticatedClientRef:string, operatorId:string, requestId:string, correlationId:string}
     */
    private function context(Request $request): array
    {
        return [
            'authenticatedClientRef' => (string) $request->attributes->get('integration.authenticated_client_ref'),
            'operatorId' => trim((string) $request->header('X-Operator-Id', '')),
            'operatorBranchId' => (string) $this->validatedOperatorBranchId($request),
            'requestId' => (string) $request->attributes->get('integration.request_id'),
            'correlationId' => (string) $request->attributes->get('integration.correlation_id'),
        ];
    }

    private function validatedOperatorBranchId(Request $request): ?string
    {
        $decoded = json_decode((string) $request->header('X-Operator-Org-Context', ''), true);
        if (! is_array($decoded)) {
            return null;
        }

        $branchId = (string) Arr::get($decoded, 'branch_id', '');

        return Str::isUuid($branchId) ? $branchId : null;
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

    private function hasOperatorRole(Request $request, string $requiredRole): bool
    {
        $roles = $request->attributes->get('integration.operator_roles');

        if (! is_array($roles)) {
            $roles = array_values(array_filter(array_map(
                static fn (string $part): string => mb_strtolower(trim($part)),
                explode(',', (string) $request->header('X-Operator-Roles', ''))
            ), static fn (string $role): bool => $role !== ''));
        }

        return in_array(mb_strtolower(trim($requiredRole)), $roles, true);
    }
}
