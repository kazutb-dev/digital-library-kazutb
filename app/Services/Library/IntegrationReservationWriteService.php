<?php

namespace App\Services\Library;

use App\Models\Library\CirculationAuditEvent;
use App\Models\Library\IntegrationIdempotencyKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IntegrationReservationWriteService
{
    /**
     * Approve command: PENDING -> READY.
     *
    * @param array{authenticatedClientRef:string, operatorId:string, operatorBranchId:string, requestId:string, correlationId:string} $context
     * @return array{status:int, body:array<string,mixed>, replayed:bool}
     */
    public function approve(string $reservationId, string $idempotencyKey, array $context): array
    {
        $payload = [
            'command' => 'approve',
            'reservation_id' => $reservationId,
        ];

        return $this->runCommand(
            operation: 'reservation_approve',
            reservationId: $reservationId,
            idempotencyKey: $idempotencyKey,
            semanticPayload: $payload,
            context: $context,
            transition: function (array $row, Carbon $now) use ($context): array {
                if ((string) $row['status'] !== 'PENDING') {
                    throw new IntegrationReservationMutationException(
                        errorCode: 'conflict',
                        reasonCode: 'invalid_state_transition',
                        message: 'Approve is only allowed when reservation status is PENDING.',
                        httpStatus: 409,
                    );
                }

                if ($this->isCopyRetired(isset($row['copyId']) ? (string) $row['copyId'] : null)) {
                    throw new IntegrationReservationMutationException(
                        errorCode: 'conflict',
                        reasonCode: 'copy_retired',
                        message: 'Reservation is bound to a retired copy and cannot be approved.',
                        httpStatus: 409,
                    );
                }

                DB::connection('pgsql')
                    ->table(DB::raw('"Reservation"'))
                    ->where('id', $row['id'])
                    ->update([
                        'status' => 'READY',
                        'processedAt' => $now->toDateTimeString(),
                        'updatedAt' => $now->toDateTimeString(),
                    ]);

                $newState = array_merge($row, [
                    'status' => 'READY',
                    'processedAt' => $now->toDateTimeString(),
                    'updatedAt' => $now->toDateTimeString(),
                ]);

                $this->recordAudit(
                    action: 'integration_reservation_approved',
                    reservationId: (string) $row['id'],
                    readerId: isset($row['userId']) ? (string) $row['userId'] : null,
                    previousState: $this->toAuditState($row),
                    newState: $this->toAuditState($newState),
                    metadata: [
                        'operation' => 'approve',
                        'source_system' => 'crm',
                    ],
                    context: $context,
                );

                return [
                    'id' => (string) $row['id'],
                    'status' => 'READY',
                    'processed_at' => $now->toISOString(),
                    'updated_at' => $now->toISOString(),
                ];
            },
        );
    }

    /**
     * Reject command: PENDING -> CANCELLED.
     *
    * @param array{authenticatedClientRef:string, operatorId:string, operatorBranchId:string, requestId:string, correlationId:string} $context
     * @return array{status:int, body:array<string,mixed>, replayed:bool}
     */
    public function reject(
        string $reservationId,
        string $idempotencyKey,
        string $cancelOrigin,
        string $cancelReasonCode,
        array $context
    ): array {
        $payload = [
            'command' => 'reject',
            'reservation_id' => $reservationId,
            'cancel_origin' => $cancelOrigin,
            'cancel_reason_code' => $cancelReasonCode,
        ];

        return $this->runCommand(
            operation: 'reservation_reject',
            reservationId: $reservationId,
            idempotencyKey: $idempotencyKey,
            semanticPayload: $payload,
            context: $context,
            transition: function (array $row, Carbon $now) use ($cancelOrigin, $cancelReasonCode, $context): array {
                if ((string) $row['status'] !== 'PENDING') {
                    throw new IntegrationReservationMutationException(
                        errorCode: 'conflict',
                        reasonCode: 'invalid_state_transition',
                        message: 'Reject is only allowed when reservation status is PENDING.',
                        httpStatus: 409,
                    );
                }

                $legacyNotes = $this->mergedRejectNotes(
                    existingNotes: isset($row['notes']) ? (string) $row['notes'] : null,
                    cancelOrigin: $cancelOrigin,
                    cancelReasonCode: $cancelReasonCode,
                );

                DB::connection('pgsql')
                    ->table(DB::raw('"Reservation"'))
                    ->where('id', $row['id'])
                    ->update([
                        'status' => 'CANCELLED',
                        'processedAt' => $now->toDateTimeString(),
                        'updatedAt' => $now->toDateTimeString(),
                        'notes' => json_encode($legacyNotes, JSON_UNESCAPED_SLASHES),
                    ]);

                $newState = array_merge($row, [
                    'status' => 'CANCELLED',
                    'processedAt' => $now->toDateTimeString(),
                    'updatedAt' => $now->toDateTimeString(),
                        'notes' => json_encode($legacyNotes, JSON_UNESCAPED_SLASHES),
                ]);

                $this->recordAudit(
                    action: 'integration_reservation_rejected',
                    reservationId: (string) $row['id'],
                    readerId: isset($row['userId']) ? (string) $row['userId'] : null,
                    previousState: $this->toAuditState($row),
                    newState: $this->toAuditState($newState),
                    metadata: [
                        'operation' => 'reject',
                        'cancel_origin' => $cancelOrigin,
                        'cancel_reason_code' => $cancelReasonCode,
                        'source_system' => 'crm',
                    ],
                    context: $context,
                );

                return [
                    'id' => (string) $row['id'],
                    'status' => 'CANCELLED',
                    'cancel_origin' => $cancelOrigin,
                    'cancel_reason_code' => $cancelReasonCode,
                    'processed_at' => $now->toISOString(),
                    'updated_at' => $now->toISOString(),
                ];
            },
        );
    }

    /**
     * @param array<string,mixed> $semanticPayload
    * @param array{authenticatedClientRef:string, operatorId:string, operatorBranchId:string, requestId:string, correlationId:string} $context
     * @param callable(array<string,mixed>, Carbon): array<string,mixed> $transition
     * @return array{status:int, body:array<string,mixed>, replayed:bool}
     */
    private function runCommand(
        string $operation,
        string $reservationId,
        string $idempotencyKey,
        array $semanticPayload,
        array $context,
        callable $transition
    ): array {
        return DB::connection('pgsql')->transaction(function () use (
            $operation,
            $reservationId,
            $idempotencyKey,
            $semanticPayload,
            $context,
            $transition
        ): array {
            $requestHash = hash('sha256', json_encode($semanticPayload, JSON_UNESCAPED_SLASHES));

            $existingKey = IntegrationIdempotencyKey::query()
                ->where('client_ref', $context['authenticatedClientRef'])
                ->where('operation', $operation)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingKey) {
                if ((string) $existingKey->request_hash !== $requestHash) {
                    throw new IntegrationReservationMutationException(
                        errorCode: 'conflict',
                        reasonCode: 'idempotency_key_reused_with_different_payload',
                        message: 'The same Idempotency-Key was used with a different semantic payload.',
                        httpStatus: 409,
                    );
                }

                return [
                    'status' => (int) $existingKey->status_code,
                    'body' => (array) $existingKey->response_body,
                    'replayed' => true,
                ];
            }

            $reservation = DB::connection('pgsql')
                ->table(DB::raw('"Reservation"'))
                ->where('id', $reservationId)
                ->where('libraryBranchId', $context['operatorBranchId'])
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                throw new IntegrationReservationMutationException(
                    errorCode: 'not_found',
                    reasonCode: 'reservation_not_found',
                    message: 'Reservation not found or not accessible in your org context.',
                    httpStatus: 404,
                );
            }

            $row = (array) $reservation;
            $now = Carbon::now('UTC');
            $data = $transition($row, $now);

            $body = [
                'data' => $data,
                'request_id' => $context['requestId'],
                'correlation_id' => $context['correlationId'],
                'timestamp' => $now->toISOString(),
            ];

            IntegrationIdempotencyKey::query()->create([
                'id' => (string) Str::uuid(),
                'client_ref' => $context['authenticatedClientRef'],
                'operation' => $operation,
                'idempotency_key' => $idempotencyKey,
                'reservation_id' => $reservationId,
                'request_hash' => $requestHash,
                'status_code' => 200,
                'response_body' => $body,
            ]);

            return [
                'status' => 200,
                'body' => $body,
                'replayed' => false,
            ];
        });
    }

    /**
     * @param array<string,mixed> $previousState
     * @param array<string,mixed> $newState
     * @param array<string,mixed> $metadata
    * @param array{authenticatedClientRef:string, operatorId:string, operatorBranchId:string, requestId:string, correlationId:string, idempotencyKey?:string} $context
     */
    private function recordAudit(
        string $action,
        string $reservationId,
        ?string $readerId,
        array $previousState,
        array $newState,
        array $metadata,
        array $context
    ): void {
        CirculationAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_at' => Carbon::now('UTC'),
            'action' => $action,
            'entity_type' => 'reservation',
            'entity_id' => $reservationId,
            'reader_id' => Str::isUuid((string) $readerId) ? $readerId : null,
            'actor_user_id' => null,
            'actor_type' => 'integration_operator',
            'request_id' => $context['requestId'],
            'correlation_id' => $context['correlationId'],
            'previous_state' => $previousState,
            'new_state' => $newState,
            'metadata' => [
                'operator_id' => $context['operatorId'],
                'operator_branch_id' => $context['operatorBranchId'],
                'authenticated_client_ref' => $context['authenticatedClientRef'],
                'idempotency_key' => $context['idempotencyKey'] ?? null,
                'details' => $metadata,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergedRejectNotes(?string $existingNotes, string $cancelOrigin, string $cancelReasonCode): array
    {
        $result = [
            'cancel_origin' => $cancelOrigin,
            'cancel_reason_code' => $cancelReasonCode,
        ];

        $normalized = trim((string) $existingNotes);
        if ($normalized === '') {
            return $result;
        }

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return array_merge($decoded, $result);
        }

        $result['legacy_note'] = $normalized;

        return $result;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function toAuditState(array $state): array
    {
        return [
            'id' => isset($state['id']) ? (string) $state['id'] : null,
            'status' => isset($state['status']) ? (string) $state['status'] : null,
            'reservedAt' => $this->normalizeDateTime($state['reservedAt'] ?? null),
            'expiresAt' => $this->normalizeDateTime($state['expiresAt'] ?? null),
            'processedAt' => $this->normalizeDateTime($state['processedAt'] ?? null),
            'copyId' => isset($state['copyId']) ? (string) $state['copyId'] : null,
            'userId' => isset($state['userId']) ? (string) $state['userId'] : null,
            'libraryBranchId' => isset($state['libraryBranchId']) ? (string) $state['libraryBranchId'] : null,
            'notes' => isset($state['notes']) ? (string) $state['notes'] : null,
        ];
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')->toISOString();
    }

    private function isCopyRetired(?string $copyId): bool
    {
        if ($copyId === null || trim($copyId) === '') {
            return false;
        }

        return DB::connection('pgsql')
            ->table('app.book_copies')
            ->where(function ($query) use ($copyId): void {
                $query->whereRaw('id::text = ?', [$copyId])
                    ->orWhereRaw('core_copy_id::text = ?', [$copyId]);
            })
            ->whereNotNull('retired_at')
            ->exists();
    }
}
