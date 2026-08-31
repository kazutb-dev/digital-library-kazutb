<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Issue / return / renewal workflow (Master.md 14, reference scenario 31.2).
 * All limits come from the admin-managed settings; per-reader overrides live
 * on the reader profile. Every mutation is wrapped in a transaction, writes
 * copy history, and lands in the audit log.
 */
class CirculationService
{
    /** Renewals per loan (Historical 13.1: once, unless overridden). */
    public const MAX_RENEWALS = 1;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LibraryNotificationService $notifications,
        private readonly ReservationQueueService $reservations,
        private readonly LoanPeriodPolicy $loanPeriods,
        private readonly IncidentCaseService $incidents,
    ) {}

    /**
     * Pre-flight snapshot for the issue screen: limits, open loans, debts,
     * blocks — everything 14.1 asks the librarian to check before scanning.
     *
     * @return array<string, mixed>
     */
    public function readerSummary(User $reader): array
    {
        $profile = ReaderProfile::forUser($reader);
        $openLoans = Loan::query()->open()->where('user_id', $reader->getKey())
            ->with('copy.bibliographicRecord')
            ->orderBy('due_at')
            ->get();
        $overdueCount = $openLoans->filter(fn (Loan $loan): bool => $loan->isOverdue() || $loan->status === 'overdue')->count();
        $pendingFines = Fine::query()->where('user_id', $reader->getKey())->where('status', 'pending')->get();
        $openIncidentCases = CirculationIncidentCase::query()->open()->where('reader_id', $reader->getKey())->count();
        $maxLoans = $profile->effectiveLimit('max_active_loans', (int) Setting::valueFor('max_active_loans', 5));

        return [
            'profile' => $profile,
            'open_loans' => $openLoans,
            'overdue_count' => $overdueCount,
            'pending_fines' => $pendingFines,
            'pending_fines_total' => (float) $pendingFines->sum('amount'),
            'max_loans' => $maxLoans,
            'loans_remaining' => max(0, $maxLoans - $openLoans->count()),
            'blocked' => $profile->status !== 'active',
            'overdue_blocked' => $overdueCount > 0 && (bool) Setting::valueFor('overdue_blocking_enabled', true),
            'open_incident_cases' => $openIncidentCases,
            'incident_blocked' => $openIncidentCases > 0 && (bool) Setting::valueFor('incident_blocks_issues', true),
        ];
    }

    public function issue(
        User $reader,
        BookCopy $copy,
        User $staff,
        bool $override = false,
        ?string $overrideReason = null,
        ?string $manualDueAt = null,
        ?string $dueDateReason = null,
    ): Loan {
        return DB::transaction(function () use ($reader, $copy, $staff, $override, $overrideReason, $manualDueAt, $dueDateReason): Loan {
            // A stable reader row serialises the cross-copy loan limit. Row
            // locks on existing loans alone do not protect the zero-row case.
            $reader = User::query()->whereKey($reader->getKey())->lockForUpdate()->firstOrFail();
            $profile = ReaderProfile::forUser($reader);
            $copy = BookCopy::query()->whereKey($copy->getKey())->lockForUpdate()->firstOrFail();
            $copyBefore = $copy->only(['status', 'condition', 'issue_count']);

            if ($profile->status !== 'active') {
                throw CirculationException::because('reader_blocked', ['reason' => (string) $profile->block_reason]);
            }
            if ((bool) Setting::valueFor('incident_blocks_issues', true)
                && CirculationIncidentCase::query()->open()->where('reader_id', $reader->getKey())->lockForUpdate()->exists()) {
                throw CirculationException::because('reader_has_open_incident');
            }
            if (Fine::query()->where('user_id', $reader->getKey())->where('status', 'pending')->lockForUpdate()->exists()) {
                throw CirculationException::because('reader_has_pending_debt');
            }

            $openLoans = Loan::query()->open()->where('user_id', $reader->getKey())->lockForUpdate()->get();
            $maxLoans = $profile->effectiveLimit('max_active_loans', (int) Setting::valueFor('max_active_loans', 5));
            $hasOverdue = $openLoans->contains(fn (Loan $loan): bool => $loan->status === 'overdue' || $loan->isOverdue());

            $violations = [];
            if ($openLoans->count() >= $maxLoans) {
                $violations[] = 'loan_limit_reached';
            }
            if ($hasOverdue && (bool) Setting::valueFor('overdue_blocking_enabled', true)) {
                $violations[] = 'reader_has_overdue';
            }

            if ($violations !== []) {
                if (! $override) {
                    throw CirculationException::because($violations[0]);
                }
                if (! $staff->can('circulation.override_limits')) {
                    throw CirculationException::because('override_not_permitted');
                }
                // 14.3 manual correction — a separate, always-reasoned audit trail.
                $this->audit->logRequired(
                    actionType: 'circulation.override_limits',
                    entityType: 'loan',
                    entityId: 'reader:'.$reader->getKey(),
                    newValues: ['violations' => $violations, 'copy_id' => $copy->getKey()],
                    reason: $overrideReason ?: 'Manual limit override at circulation desk',
                    scope: 'library',
                    actor: $staff,
                );
            }

            // 13.3: a copy held for another reader can never be issued past
            // the queue.
            $reservation = $copy->activeReservation;
            if ($reservation !== null && (int) $reservation->user_id !== (int) $reader->getKey()) {
                throw CirculationException::because('copy_reserved_for_other');
            }
            if ($reservation !== null && $reservation->status !== 'ready_for_pickup') {
                throw CirculationException::because('reservation_not_ready');
            }

            if (! in_array($copy->status, BookCopy::ISSUABLE_STATUSES, true) || ! $copy->isCirculatable()) {
                throw CirculationException::because('copy_not_available', ['status' => $copy->status]);
            }
            if ($copy->access_restriction === 'reading_room') {
                throw CirculationException::because('reading_room_home_issue_forbidden');
            }

            // 9.3 — the period now scales with how many copies the library
            // holds of this edition; reading-room stock keeps its own rule.
            $periodDays = $this->loanPeriods->daysForCopy($copy);
            $calculatedDueAt = now()->addDays(max(1, $periodDays))->endOfDay();
            $dueAt = $calculatedDueAt;
            if ($manualDueAt !== null) {
                if (! $staff->can('circulation.override_due_date') || trim((string) $dueDateReason) === '') {
                    throw CirculationException::because('due_date_override_not_permitted');
                }
                $candidate = Carbon::parse($manualDueAt)->endOfDay();
                $maximum = now()->addDays(max(1, (int) Setting::valueFor('manual_due_date_max_days', 30)))->endOfDay();
                if ($candidate->lessThanOrEqualTo(now()) || $candidate->greaterThan($maximum)) {
                    throw CirculationException::because('due_date_out_of_range');
                }
                if ($reservation === null && Reservation::query()->where('bibliographic_record_id', $copy->bibliographic_record_id)->where('status', 'queued')->exists()
                    && $candidate->greaterThan($calculatedDueAt)) {
                    throw CirculationException::because('due_date_queue_conflict');
                }
                $dueAt = $candidate;
            }

            $loan = Loan::query()->create([
                'user_id' => $reader->getKey(),
                'copy_id' => $copy->getKey(),
                'status' => 'active',
                'issued_at' => now(),
                'due_at' => $dueAt,
                'issued_by' => $staff->getKey(),
            ]);

            $copy->update(['status' => 'issued', 'issue_count' => $copy->issue_count + 1]);
            $copy->recordHistory('issued', $reader->getKey(), $staff->getKey(), $loan->getKey());

            if ($reservation !== null) {
                $this->reservations->fulfill($reservation, $staff, $loan);
            }

            if ($manualDueAt !== null) {
                $this->audit->logRequired(
                    actionType: 'circulation.override_due_date', entityType: 'loan', entityId: $loan->getKey(),
                    oldValues: ['due_at' => $calculatedDueAt->toIso8601String()],
                    newValues: ['due_at' => $dueAt->toIso8601String()], reason: $dueDateReason,
                    scope: 'operational', actor: $staff,
                );
            }

            $this->audit->logRequired(
                actionType: 'circulation.issue',
                entityType: 'loan',
                entityId: $loan->getKey(),
                oldValues: [
                    'copy' => $copyBefore,
                    'reader' => [
                        'id' => $reader->getKey(),
                        'open_loans' => $openLoans->count(),
                    ],
                ],
                newValues: [
                    'loan' => [
                        'status' => $loan->status,
                        'reader_id' => $reader->getKey(),
                        'copy_id' => $copy->getKey(),
                        'inventory_number' => $copy->inventory_number,
                        'issued_at' => $loan->issued_at?->toIso8601String(),
                        'due_at' => $loan->due_at?->toIso8601String(),
                        // The period is derived, not fixed — record what drove it.
                        'loan_period_days' => $periodDays,
                    ],
                    'copy' => $copy->only(['status', 'condition', 'issue_count']),
                    'reader' => [
                        'id' => $reader->getKey(),
                        'open_loans' => $openLoans->count() + 1,
                    ],
                ],
                scope: 'library',
                actor: $staff,
            );

            return $loan;
        }, 3);
    }

    /**
     * 14.2 return flow. $incident: none | damaged | lost. A fine is charged
     * for overdue days automatically and for damage/loss when an amount is
     * given by the librarian.
     */
    public function returnCopy(
        BookCopy $copy,
        User $staff,
        ?string $conditionOnReturn = null,
        string $incident = 'none',
        ?float $incidentFineAmount = null,
        ?string $notes = null,
        array $incidentData = [],
    ): Loan {
        return DB::transaction(function () use ($copy, $staff, $conditionOnReturn, $incident, $incidentFineAmount, $notes, $incidentData): Loan {
            $copy = BookCopy::query()->whereKey($copy->getKey())->lockForUpdate()->firstOrFail();
            $loan = Loan::query()->open()->where('copy_id', $copy->getKey())->lockForUpdate()->first();

            if ($loan === null) {
                throw CirculationException::because('no_open_loan');
            }

            $loanBefore = $loan->only(['status', 'returned_at', 'condition_on_return']);
            $copyBefore = $copy->only(['status', 'condition', 'defect_description']);
            $conditionBefore = $copy->condition;
            $overdueDays = $loan->overdueDays();

            $loan->update([
                'status' => $incident === 'lost' ? 'lost' : 'returned',
                'returned_at' => now(),
                'returned_to' => $staff->getKey(),
                'condition_on_return' => $conditionOnReturn,
                'notes' => trim(($loan->notes ? $loan->notes."\n" : '').($notes ?? '')) ?: null,
            ]);

            $finesCharged = [];
            $incidentFine = null;

            $finePerDay = (int) Setting::valueFor('fine_per_overdue_day', 0);
            if ($overdueDays > 0 && $finePerDay > 0) {
                $finesCharged[] = Fine::query()->firstOrCreate([
                    'loan_id' => $loan->getKey(), 'reason' => 'overdue',
                ], [
                    'user_id' => $loan->user_id,
                    'copy_id' => $copy->getKey(),
                    'amount' => $overdueDays * $finePerDay,
                    'reason' => 'overdue',
                    'status' => 'pending',
                    'charged_at' => now(),
                    'notes' => __('librarian.fines.auto_overdue_note', ['days' => $overdueDays, 'rate' => $finePerDay]),
                ]);
            }

            if ($incident === 'damaged') {
                $copy->fill([
                    'condition' => 'damaged',
                    'defect_description' => trim(($copy->defect_description ? $copy->defect_description."\n" : '').($notes ?? '')) ?: $copy->defect_description,
                ]);
                if ($incidentFineAmount !== null && $incidentFineAmount > 0) {
                    $incidentFine = Fine::query()->create([
                        'user_id' => $loan->user_id,
                        'loan_id' => $loan->getKey(),
                        'copy_id' => $copy->getKey(),
                        'amount' => $incidentFineAmount,
                        'reason' => 'damaged',
                        'status' => 'pending',
                        'charged_at' => now(),
                        'notes' => $notes,
                    ]);
                    $finesCharged[] = $incidentFine;
                }
                $copy->recordHistory('incident', $loan->user_id, $staff->getKey(), $loan->getKey(), ['type' => 'damaged', 'notes' => $notes]);
            } elseif ($incident === 'lost') {
                if ($incidentFineAmount !== null && $incidentFineAmount > 0) {
                    $incidentFine = Fine::query()->create([
                        'user_id' => $loan->user_id,
                        'loan_id' => $loan->getKey(),
                        'copy_id' => $copy->getKey(),
                        'amount' => $incidentFineAmount,
                        'reason' => 'lost',
                        'status' => 'pending',
                        'charged_at' => now(),
                        'notes' => $notes,
                    ]);
                    $finesCharged[] = $incidentFine;
                }
                $copy->recordHistory('incident', $loan->user_id, $staff->getKey(), $loan->getKey(), ['type' => 'lost', 'notes' => $notes]);
            }

            // Decide where the copy goes next: lost/damaged copies leave
            // circulation; otherwise the reservation queue gets first claim.
            if ($incident === 'lost') {
                $copy->status = 'lost';
                $copy->save();
            } elseif ($incident === 'damaged') {
                $copy->status = match ($incidentData['preliminary_action'] ?? 'repair') {
                    'return_to_fund' => 'available',
                    default => 'under_repair',
                };
                $copy->save();
            } else {
                $copy->save();
                $handedToQueue = $this->reservations->offerReturnedCopy($copy, $staff);
                if (! $handedToQueue) {
                    $copy->update(['status' => 'available']);
                }
            }

            $copy->recordHistory('returned', $loan->user_id, $staff->getKey(), $loan->getKey(), array_filter([
                'condition_on_return' => $conditionOnReturn,
                'overdue_days' => $overdueDays ?: null,
            ]));

            if (in_array($incident, ['lost', 'damaged'], true) && ($incidentData['open_case'] ?? true)) {
                $this->incidents->openForReturnedLoan(
                    $loan->refresh(),
                    $copy->refresh(),
                    $staff,
                    $incident,
                    $incidentFine,
                    [...$incidentData, 'condition_before' => $conditionBefore, 'notes' => $notes],
                );
            }

            $this->audit->logRequired(
                actionType: 'circulation.return',
                entityType: 'loan',
                entityId: $loan->getKey(),
                oldValues: [
                    'loan' => $loanBefore,
                    'copy' => $copyBefore,
                ],
                newValues: [
                    'loan' => $loan->only(['status', 'returned_at', 'condition_on_return']),
                    'copy' => $copy->only(['status', 'condition', 'defect_description']),
                    'reader_id' => $loan->user_id,
                    'copy_id' => $copy->getKey(),
                    'incident' => $incident,
                    'overdue_days' => $overdueDays,
                    'fines_charged' => array_map(fn (Fine $fine): array => ['id' => $fine->getKey(), 'amount' => (float) $fine->amount, 'reason' => $fine->reason], $finesCharged),
                ],
                scope: 'library',
                actor: $staff,
            );

            return $loan->refresh();
        });
    }

    /**
     * 5.3 renewal: once per loan, blocked when overdue or when someone is
     * waiting for this edition (Historical 13.1).
     */
    public function renew(Loan $loan, User $actor, bool $byStaff = false, ?string $expectedDueAt = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $byStaff, $expectedDueAt): Loan {
            $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            if ($expectedDueAt !== null && $loan->due_at?->toDateString() !== \Illuminate\Support\Carbon::parse($expectedDueAt)->toDateString()) {
                throw CirculationException::because('renewal_stale_request');
            }

            if (! in_array($loan->status, Loan::OPEN_STATUSES, true) || $loan->returned_at !== null) {
                throw CirculationException::because('loan_not_open');
            }
            if (! (bool) Setting::valueFor('renewal_allowed', true)) {
                throw CirculationException::because('renewal_disabled');
            }
            if ($loan->status === 'overdue' || $loan->isOverdue()) {
                throw CirculationException::because('renewal_overdue');
            }
            if ($loan->renewal_count >= max(0, (int) Setting::valueFor('max_renewals', self::MAX_RENEWALS))) {
                throw CirculationException::because('renewal_limit_reached');
            }
            if (! $byStaff && (int) $loan->user_id !== (int) $actor->getKey()) {
                throw CirculationException::because('renewal_not_own');
            }
            $profile = ReaderProfile::forUser($loan->reader);
            if ($profile->status !== 'active') {
                throw CirculationException::because('reader_blocked', ['reason' => (string) $profile->block_reason]);
            }
            if (Fine::query()->where('user_id', $loan->user_id)->where('status', 'pending')->lockForUpdate()->exists()) {
                throw CirculationException::because('reader_has_pending_debt');
            }
            if (CirculationIncidentCase::query()->open()->where('reader_id', $loan->user_id)->lockForUpdate()->exists()) {
                throw CirculationException::because('reader_has_open_incident');
            }

            $copy = $loan->copy;
            $recordId = $copy?->bibliographic_record_id;
            $queueWaiting = $recordId !== null && Reservation::query()
                ->where('bibliographic_record_id', $recordId)
                ->whereIn('status', Reservation::ACTIVE_STATUSES)
                ->where('user_id', '!=', $loan->user_id)
                ->exists();
            if ($queueWaiting) {
                throw CirculationException::because('renewal_reserved');
            }

            $renewalDays = (int) Setting::valueFor('renewal_period_days', (int) Setting::valueFor('standard_loan_period_days', 14));
            $loan->update([
                'due_at' => $loan->due_at->addDays(max(1, $renewalDays)),
                'renewal_count' => $loan->renewal_count + 1,
            ]);

            $this->audit->logRequired(
                actionType: $byStaff ? 'circulation.renew' : 'loan.renewed_by_reader',
                entityType: 'loan',
                entityId: $loan->getKey(),
                newValues: ['new_due_at' => $loan->due_at?->toIso8601String(), 'renewal_count' => $loan->renewal_count],
                scope: 'library',
                actor: $actor,
            );

            if ($loan->reader !== null) {
                $this->notifications->sendLocalized(
                    $loan->reader,
                    'loan_renewed',
                    'librarian.notifications.loan_renewed_title',
                    'librarian.notifications.loan_renewed_body',
                    [
                        'title' => (string) $copy?->bibliographicRecord?->title,
                        'due' => ['_date' => $loan->due_at->toIso8601String()],
                    ],
                    ['loan_id' => $loan->getKey()],
                );
            }

            return $loan;
        });
    }

    /**
     * Scheduled sweep: flag overdue loans (+ copies), notify once, and warn
     * readers whose return date is close.
     *
     * @return array{overdue: int, due_soon: int}
     */
    public function sweepOverdue(): array
    {
        $markedOverdue = 0;
        $dueSoonNotified = 0;

        Loan::query()
            ->where('status', 'active')
            ->whereNull('returned_at')
            ->where('due_at', '<', now())
            ->with(['copy.bibliographicRecord', 'reader'])
            ->chunkById(100, function ($loans) use (&$markedOverdue): void {
                foreach ($loans as $loan) {
                    DB::transaction(function () use ($loan, &$markedOverdue): void {
                        $oldValues = [
                            'loan' => $loan->only(['status', 'due_at', 'returned_at']),
                            'copy' => $loan->copy?->only(['status', 'condition']),
                        ];
                        $loan->update(['status' => 'overdue']);
                        $loan->copy?->update(['status' => 'overdue']);
                        $markedOverdue++;

                        $this->audit->logRequired(
                            actionType: 'circulation.overdue_marked',
                            entityType: 'loan',
                            entityId: $loan->getKey(),
                            oldValues: $oldValues,
                            newValues: [
                                'loan' => $loan->fresh()->only(['status', 'due_at', 'returned_at']),
                                'copy' => $loan->copy?->fresh()?->only(['status', 'condition']),
                            ],
                            scope: 'library',
                            actor: ['name' => 'Scheduler', 'role' => 'system'],
                        );

                        if ($loan->reader !== null) {
                            $this->notifications->sendLocalized(
                                $loan->reader,
                                'loan_overdue',
                                'librarian.notifications.loan_overdue_title',
                                'librarian.notifications.loan_overdue_body',
                                [
                                    'title' => (string) $loan->copy?->bibliographicRecord?->title,
                                    'due' => ['_date' => $loan->due_at->toIso8601String()],
                                ],
                                ['loan_id' => $loan->getKey()],
                            );
                        }
                    });
                }
            });

        $dueSoon = Loan::query()
            ->where('status', 'active')
            ->whereNull('returned_at')
            ->whereBetween('due_at', [now(), now()->addDays(2)])
            ->with(['copy.bibliographicRecord', 'reader'])
            ->get();

        foreach ($dueSoon as $loan) {
            $alreadyWarned = ReaderNotification::query()
                ->where('user_id', $loan->user_id)
                ->where('event_type', 'loan_due_soon')
                ->where('payload->loan_id', $loan->getKey())
                ->exists();
            if ($alreadyWarned || $loan->reader === null) {
                continue;
            }
            $this->notifications->sendLocalized(
                $loan->reader,
                'loan_due_soon',
                'librarian.notifications.loan_due_soon_title',
                'librarian.notifications.loan_due_soon_body',
                [
                    'title' => (string) $loan->copy?->bibliographicRecord?->title,
                    'due' => ['_date' => $loan->due_at->toIso8601String()],
                ],
                ['loan_id' => $loan->getKey()],
            );
            $dueSoonNotified++;
        }

        if ($markedOverdue > 0) {
            $this->audit->logRequired(
                actionType: 'circulation.overdue_sweep',
                entityType: 'loan',
                entityId: 'sweep',
                oldValues: ['marked_overdue' => 0],
                newValues: ['marked_overdue' => $markedOverdue],
                scope: 'library',
                actor: ['name' => 'Scheduler', 'role' => 'system'],
            );
        }

        return ['overdue' => $markedOverdue, 'due_soon' => $dueSoonNotified];
    }
}
