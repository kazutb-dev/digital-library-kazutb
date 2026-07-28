<?php

namespace App\Services\Library;

use App\Models\Library\BookCopy;
use App\Models\Library\CirculationAuditEvent;
use App\Models\Library\CirculationLoan;
use App\Models\Library\Reader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CirculationLoanWriteService
{
    /**
     * @param array{actorUserId?: string|null, actorType?: string|null, requestId?: string|null, correlationId?: string|null, metadata?: array<string, mixed>} $context
     * @return array<string, mixed>
     */
    public function checkout(string $readerId, string $copyId, ?string $dueAt = null, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($readerId, $copyId, $dueAt, $context): array {
            $reader = Reader::query()->find($readerId);
            if (! $reader) {
                throw new CirculationWriteException('reader_not_found', 404, 'Reader not found.');
            }

            $copy = BookCopy::query()->find($copyId);
            if (! $copy) {
                throw new CirculationWriteException('copy_not_found', 404, 'Copy not found.');
            }

            if ($copy->retired_at !== null) {
                throw new CirculationWriteException('copy_retired', 409, 'Retired copy is not checkout-eligible.');
            }

            $activeLoan = $this->findActiveLoanModelByCopy($copyId);
            if ($activeLoan) {
                throw new CirculationWriteException('copy_already_on_loan', 409, 'Copy already has an active loan.');
            }

            $issuedAt = Carbon::now('UTC');
            $resolvedDueAt = $this->resolveDueAt($issuedAt, $dueAt);

            $loan = CirculationLoan::query()->create([
                'id' => (string) Str::uuid(),
                'reader_id' => $readerId,
                'copy_id' => $copyId,
                'status' => 'active',
                'issued_at' => $issuedAt,
                'due_at' => $resolvedDueAt,
                'returned_at' => null,
                'renew_count' => 0,
            ]);

            $this->recordAuditEvent(
                action: 'checkout_created',
                entityType: 'loan',
                entityId: (string) $loan->id,
                readerId: $readerId,
                previousState: null,
                newState: $this->loanSnapshot($loan),
                context: $context + [
                    'copyId' => $copyId,
                ],
            );

            return $this->toResult($loan);
        });
    }

    public const MAX_RENEWALS = 3;

    public const RENEWAL_DAYS = 14;

    /**
     * @param array{actorUserId?: string|null, actorType?: string|null, requestId?: string|null, correlationId?: string|null, metadata?: array<string, mixed>} $context
     * @return array<string, mixed>
     */
    public function renew(string $loanId, bool $allowOverdue = false, ?string $newDueAt = null, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($loanId, $allowOverdue, $newDueAt, $context): array {
            $loan = CirculationLoan::query()->lockForUpdate()->find($loanId);
            if (! $loan) {
                throw new CirculationWriteException('loan_not_found', 404, 'Loan not found.');
            }

            if ($loan->status !== 'active' || $loan->returned_at !== null) {
                throw new CirculationWriteException('loan_not_active', 409, 'Only active loans can be renewed.');
            }

            if ($loan->renew_count >= self::MAX_RENEWALS) {
                throw new CirculationWriteException('max_renewals_reached', 409,
                    'Maximum renewals (' . self::MAX_RENEWALS . ') reached.');
            }

            $isOverdue = $loan->due_at !== null && Carbon::parse($loan->due_at)->isPast();
            if ($isOverdue && ! $allowOverdue) {
                throw new CirculationWriteException('loan_overdue', 409,
                    'Overdue loans cannot be self-renewed. Contact the library.');
            }

            $previousState = $this->loanSnapshot($loan);
            $currentDueAt = $loan->due_at ? Carbon::parse($loan->due_at) : Carbon::now('UTC');

            if ($newDueAt !== null && trim($newDueAt) !== '') {
                try {
                    $resolvedDueAt = Carbon::parse($newDueAt, 'UTC');
                } catch (\Throwable) {
                    throw new CirculationWriteException('invalid_due_at', 422, 'The new_due_at value is invalid.');
                }
                if ($resolvedDueAt->lte($currentDueAt)) {
                    throw new CirculationWriteException('invalid_due_at', 422,
                        'New due date must be after current due date.');
                }
            } else {
                $resolvedDueAt = $currentDueAt->copy()->addDays(self::RENEWAL_DAYS);
            }

            $loan->forceFill([
                'renew_count' => $loan->renew_count + 1,
                'due_at' => $resolvedDueAt,
            ])->save();

            $loan->refresh();

            $this->recordAuditEvent(
                action: 'renewal_completed',
                entityType: 'loan',
                entityId: (string) $loan->id,
                readerId: (string) $loan->reader_id,
                previousState: $previousState,
                newState: $this->loanSnapshot($loan),
                context: $context + [
                    'copyId' => (string) $loan->copy_id,
                ],
            );

            return $this->toResult($loan);
        });
    }

    /**
     * @param array{actorUserId?: string|null, actorType?: string|null, requestId?: string|null, correlationId?: string|null, metadata?: array<string, mixed>} $context
     * @return array<string, mixed>
     */
    public function returnCopy(string $copyId, array $context = []): array
    {
        return DB::connection('pgsql')->transaction(function () use ($copyId, $context): array {
            $copy = BookCopy::query()->find($copyId);
            if (! $copy) {
                throw new CirculationWriteException('copy_not_found', 404, 'Copy not found.');
            }

            $loan = $this->findActiveLoanModelByCopy($copyId);
            if (! $loan) {
                throw new CirculationWriteException('active_loan_not_found', 404, 'Active loan not found for copy.');
            }

            $previousState = $this->loanSnapshot($loan);
            $returnedAt = Carbon::now('UTC');

            $loan->forceFill([
                'status' => 'returned',
                'returned_at' => $returnedAt,
            ])->save();

            $loan->refresh();

            $this->recordAuditEvent(
                action: 'return_completed',
                entityType: 'loan',
                entityId: (string) $loan->id,
                readerId: (string) $loan->reader_id,
                previousState: $previousState,
                newState: $this->loanSnapshot($loan),
                context: $context + [
                    'copyId' => $copyId,
                ],
            );

            return $this->toResult($loan);
        });
    }

    private function findActiveLoanModelByCopy(string $copyId): ?CirculationLoan
    {
        return CirculationLoan::query()
            ->where('copy_id', $copyId)
            ->where('status', 'active')
            ->whereNull('returned_at')
            ->orderByDesc('issued_at')
            ->first();
    }

    private function resolveDueAt(Carbon $issuedAt, ?string $dueAt): Carbon
    {
        if ($dueAt === null || trim($dueAt) === '') {
            return $issuedAt->copy()->addDays(14);
        }

        try {
            $resolved = Carbon::parse($dueAt, 'UTC');
        } catch (\Throwable) {
            throw new CirculationWriteException('invalid_due_at', 422, 'The due_at value is invalid.');
        }

        if ($resolved->lt($issuedAt)) {
            throw new CirculationWriteException('invalid_due_at', 422, 'The due_at value must not be earlier than issued_at.');
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed>|null $previousState
     * @param array<string, mixed> $newState
     * @param array{actorUserId?: string|null, actorType?: string|null, requestId?: string|null, correlationId?: string|null, metadata?: array<string, mixed>, copyId?: string|null} $context
     */
    private function recordAuditEvent(
        string $action,
        string $entityType,
        string $entityId,
        ?string $readerId,
        ?array $previousState,
        array $newState,
        array $context
    ): void {
        CirculationAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_at' => Carbon::now('UTC'),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'reader_id' => $readerId,
            'actor_user_id' => $context['actorUserId'] ?? null,
            'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
            'request_id' => $context['requestId'] ?? null,
            'correlation_id' => $context['correlationId'] ?? null,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'metadata' => array_filter([
                'copyId' => $context['copyId'] ?? null,
                'metadata' => $context['metadata'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toResult(CirculationLoan $loan): array
    {
        return [
            'data' => $this->loanSnapshot($loan),
            'source' => 'app.circulation_loans',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loanSnapshot(CirculationLoan $loan): array
    {
        return [
            'id' => (string) $loan->id,
            'readerId' => (string) $loan->reader_id,
            'copyId' => (string) $loan->copy_id,
            'status' => (string) $loan->status,
            'issuedAt' => $loan->issued_at?->toAtomString(),
            'dueAt' => $loan->due_at?->toAtomString(),
            'returnedAt' => $loan->returned_at?->toAtomString(),
            'renewCount' => (int) $loan->renew_count,
            'maxRenewals' => self::MAX_RENEWALS,
        ];
    }
}
