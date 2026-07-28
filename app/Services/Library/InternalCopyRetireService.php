<?php

namespace App\Services\Library;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Library\CirculationAuditEvent;

class InternalCopyRetireService
{
    private const COPY_TABLE = 'app.book_copies';

    private const LOAN_TABLE = 'app.circulation_loans';

    private const RESERVATION_TABLE = 'public."Reservation"';

    /** @var list<string> */
    public const ALLOWED_REASON_CODES = [
        'LOST',
        'DAMAGED_BEYOND_REPAIR',
        'WRITTEN_OFF',
        'MISSING_AFTER_AUDIT',
        'OTHER',
    ];

    /**
     * Blocking reservation statuses: copy-bound reservations in these states prevent retirement.
     *
     * @var list<string>
     */
    private const BLOCKING_RESERVATION_STATUSES = ['PENDING', 'READY'];

    public function __construct(
        private readonly InternalCopyReadService $readService,
    ) {}

    /**
     * Retire a copy with an explicit reason code and optional note.
     *
     * Hard-blocks:
     *   - copy does not exist → 404
     *   - already retired → 409 already_retired
     *   - active loan exists → 409 copy_on_loan
     *   - copy-bound reservation in PENDING or READY status → 409 active_reservation_conflict
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function retireCopy(string $copyId, string $reasonCode, ?string $note, array $context): array
    {
        // Check reservation table availability BEFORE the main transaction.
        // If the check itself fails (table missing), it must not abort the copy transaction.
        $checkReservations = $this->reservationTableAvailable();

        return DB::connection('pgsql')->transaction(function () use ($copyId, $reasonCode, $note, $context, $checkReservations): array {
            // Lock the row for update to prevent concurrent retire attempts.
            $lockedRow = DB::connection('pgsql')
                ->table(self::COPY_TABLE)
                ->where('id', $copyId)
                ->lockForUpdate()
                ->first();

            if ($lockedRow === null) {
                throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
            }

            $row = (array) $lockedRow;

            // Gate 1: already retired?
            if (isset($row['retired_at']) && $row['retired_at'] !== null) {
                throw new InternalCopyWriteException(
                    'already_retired',
                    409,
                    'Copy is already retired.',
                );
            }

            // Gate 2: active loan?
            $activeLoanExists = DB::connection('pgsql')
                ->table(self::LOAN_TABLE)
                ->where('copy_id', $copyId)
                ->where('status', 'active')
                ->whereNull('returned_at')
                ->exists();

            if ($activeLoanExists) {
                throw new InternalCopyWriteException(
                    'copy_on_loan',
                    409,
                    'Copy has an active loan and cannot be retired until the loan is returned.',
                );
            }

            // Gate 3: copy-bound reservation in blocking status?
            // Use raw SQL to avoid PostgreSQL identifier quoting issues with mixed-case table/column names.
            if ($checkReservations) {
                $reservationBlock = DB::connection('pgsql')->selectOne(
                    'SELECT 1 FROM public."Reservation" WHERE "copyId"::text = ? AND status::text = ANY(?::text[]) LIMIT 1',
                    [$copyId, '{' . implode(',', self::BLOCKING_RESERVATION_STATUSES) . '}']
                );

                if ($reservationBlock !== null) {
                    throw new InternalCopyWriteException(
                        'active_reservation_conflict',
                        409,
                        'Copy has an active copy-bound reservation (PENDING or READY) and cannot be retired until the reservation is resolved.',
                    );
                }
            }

            $before = $this->requireCopyDetail($copyId);

            $retiredAt = Carbon::now('UTC');

            $update = [
                'retired_at' => $retiredAt->toDateTimeString(),
                'retirement_reason_code' => $reasonCode,
                'retirement_note' => $note,
            ];

            if ($this->hasCopyColumn('updated_at')) {
                $update['updated_at'] = $retiredAt->toDateTimeString();
            }

            DB::connection('pgsql')
                ->table(self::COPY_TABLE)
                ->where('id', $copyId)
                ->update($update);

            $after = $this->requireCopyDetail($copyId);

            $this->recordAudit(
                copyId: $copyId,
                previousState: $before,
                newState: $after,
                context: $context,
                metadata: [
                    'operation' => 'retire',
                    'reason_code' => $reasonCode,
                    'note_provided' => $note !== null,
                    'document_id' => $before['parentDocument']['documentId'] ?? null,
                    'branch_id' => $before['branch']['branchId'] ?? null,
                ],
            );

            return [
                'data' => $after,
                'source' => self::COPY_TABLE,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCopyDetail(string $copyId): array
    {
        $detail = $this->readService->findCopyDetail($copyId);

        if ($detail === null) {
            throw new InternalCopyWriteException('copy_not_found', 404, 'Copy not found.');
        }

        return $detail;
    }

    private function hasCopyColumn(string $column): bool
    {
        return Schema::connection('pgsql')->hasColumn(self::COPY_TABLE, $column);
    }

    private function reservationTableAvailable(): bool
    {
        try {
            $result = DB::connection('pgsql')->selectOne(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'Reservation' LIMIT 1"
            );

            return $result !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $previousState
     * @param array<string, mixed> $newState
     * @param array<string, mixed> $context
     * @param array<string, mixed> $metadata
     */
    private function recordAudit(
        string $copyId,
        array $previousState,
        array $newState,
        array $context,
        array $metadata,
    ): void {
        CirculationAuditEvent::query()->create([
            'id' => (string) Str::uuid(),
            'event_at' => Carbon::now('UTC'),
            'action' => 'internal_copy_retired',
            'entity_type' => 'copy',
            'entity_id' => $copyId,
            'reader_id' => null,
            'actor_user_id' => $context['actorUserId'] ?? null,
            'actor_type' => (string) ($context['actorType'] ?? 'staff_operator'),
            'request_id' => $context['requestId'] ?? null,
            'correlation_id' => $context['correlationId'] ?? null,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'metadata' => array_filter([
                'actor_role' => $context['actorRole'] ?? null,
                'actor_login' => $context['actorLogin'] ?? null,
                'details' => $metadata,
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }
}
