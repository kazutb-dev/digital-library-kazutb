<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReplacementCandidate;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncidentCaseService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LibraryNotificationService $notifications,
    ) {}

    /**
     * Called by CirculationService inside its return transaction. It does not
     * start a parallel financial flow: the already-created incident fine is
     * linked to the case.
     *
     * @param  array<string, mixed>  $data
     */
    public function openForReturnedLoan(
        Loan $loan,
        BookCopy $copy,
        User $staff,
        string $incidentType,
        ?Fine $fine,
        array $data = [],
    ): CirculationIncidentCase {
        $existing = CirculationIncidentCase::query()->where('loan_id', $loan->getKey())->lockForUpdate()->first();
        if ($existing !== null) {
            return $existing;
        }

        $days = max(1, (int) Setting::valueFor('incident_resolution_days', 30));
        $severity = $incidentType === 'damaged' ? ($data['damage_severity'] ?? null) : null;
        $replacementRequested = $incidentType === 'lost'
            || ($data['open_replacement_case'] ?? false)
            || ($severity === 'severe' && (bool) Setting::valueFor('replacement_required_severe', true))
            || ($severity === 'irreparable' && (bool) Setting::valueFor('replacement_required_irreparable', true));

        $case = CirculationIncidentCase::query()->create([
            'case_number' => 'TMP-'.Str::upper(Str::random(20)),
            'incident_type' => $incidentType,
            'loan_id' => $loan->getKey(),
            'original_copy_id' => $copy->getKey(),
            'reader_id' => $loan->user_id,
            'opened_by' => $staff->getKey(),
            'assigned_to' => $staff->getKey(),
            'status' => $replacementRequested ? 'awaiting_reader' : 'open',
            'damage_severity' => $severity,
            'damage_description' => $data['damage_description'] ?? null,
            'condition_before' => $data['condition_before'] ?? $copy->getOriginal('condition'),
            'condition_after' => $data['condition_after'] ?? $loan->condition_on_return,
            'preliminary_action' => $data['preliminary_action'] ?? ($incidentType === 'lost' ? 'replacement' : null),
            'fine_id' => $fine?->getKey(),
            'opened_at' => now(),
            'resolution_due_at' => now()->addDays($days),
            'notes' => $data['notes'] ?? null,
        ]);
        $case->update(['case_number' => sprintf('INC-%s-%06d', now()->format('Y'), $case->getKey())]);

        $snapshot = $this->caseSnapshot($case);
        $this->audit->logRequired('incident.opened', 'circulation_incident_case', $case->getKey(), null, $snapshot, $data['notes'] ?? null, 'operational', $snapshot, $staff);
        if ($severity !== null) {
            $this->audit->logRequired('incident.damage_assessed', 'circulation_incident_case', $case->getKey(), null, [
                'severity' => $severity,
                'description' => $data['damage_description'] ?? null,
                'preliminary_action' => $data['preliminary_action'] ?? null,
            ], $data['damage_description'] ?? null, 'operational', $snapshot, $staff);
        }
        if ($fine !== null) {
            $this->audit->logRequired('incident.fine_linked', 'circulation_incident_case', $case->getKey(), null, [
                'fine_id' => $fine->getKey(), 'amount' => (float) $fine->amount,
            ], null, 'operational', $snapshot, $staff);
            if ($loan->reader !== null) {
                $this->notify($loan->reader, 'incident_fine_assigned', $case);
            }
        }

        if ($loan->reader !== null) {
            $this->notify($loan->reader, 'incident_opened', $case);
            if ($replacementRequested) {
                $this->notify($loan->reader, 'incident_awaiting_replacement', $case);
            }
        }

        return $case->refresh();
    }

    /** @param array<string, mixed> $data */
    public function propose(CirculationIncidentCase $case, User $actor, array $data): ReplacementCandidate
    {
        return DB::transaction(function () use ($case, $actor, $data): ReplacementCandidate {
            $case = $this->lockOpenCase($case);
            $record = isset($data['bibliographic_record_id'])
                ? BibliographicRecord::query()->findOrFail($data['bibliographic_record_id'])
                : null;

            $candidate = ReplacementCandidate::query()->create([
                'incident_case_id' => $case->getKey(),
                'bibliographic_record_id' => $record?->getKey(),
                'isbn' => $data['isbn'] ?? $record?->isbn,
                'author' => $data['author'] ?? $record?->primary_author,
                'title' => $data['title'] ?? $record?->title,
                'work_title' => $data['work_title'] ?? null,
                'publisher' => $data['publisher'] ?? $record?->publisher,
                'publication_year' => $data['publication_year'] ?? $record?->publication_year,
                'language' => $data['language'] ?? $record?->language,
                'resource_type' => $data['resource_type'] ?? $record?->resource_type,
                'udc_code' => $data['udc_code'] ?? $record?->udc_code,
                'content_description' => $data['content_description'] ?? $record?->annotation,
                'copy_condition' => $data['copy_condition'] ?? null,
                'estimated_value' => $data['estimated_value'] ?? null,
                'source' => $data['source'] ?? 'reader',
                'proposed_by' => $actor->getKey(),
                'status' => 'proposed',
            ]);
            $old = $this->caseSnapshot($case);
            $case->update(['status' => 'replacement_proposed']);
            $this->audit->logRequired('incident.replacement_proposed', 'circulation_incident_case', $case->getKey(), $old, [
                ...$this->caseSnapshot($case), 'candidate_id' => $candidate->getKey(),
            ], null, 'operational', $this->caseSnapshot($case), $actor);
            $this->notify($case->reader, 'incident_candidate_submitted', $case);

            return $candidate;
        });
    }

    /** @param array<string, bool|null> $criteria */
    public function review(ReplacementCandidate $candidate, User $reviewer, array $criteria, ?string $comment = null): ReplacementCandidate
    {
        return DB::transaction(function () use ($candidate, $reviewer, $criteria, $comment): ReplacementCandidate {
            $candidate = ReplacementCandidate::query()->whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
            $case = $this->lockOpenCase($candidate->incidentCase);
            $original = $case->originalCopy->bibliographicRecord;
            $tolerance = max(0, (int) Setting::valueFor('replacement_year_tolerance', 5));
            $yearDifference = ($original?->publication_year && $candidate->publication_year)
                ? abs((int) $original->publication_year - (int) $candidate->publication_year)
                : null;

            $advisory = [
                'isbn_matches' => $this->same($original?->isbn, $candidate->isbn),
                'publisher_matches' => $this->same($original?->publisher, $candidate->publisher),
                'year_difference' => $yearDifference,
                'year_within_tolerance' => $yearDifference !== null ? $yearDifference <= $tolerance : null,
                'language_matches' => $this->same($original?->language, $candidate->language),
                'resource_type_matches' => $this->same($original?->resource_type, $candidate->resource_type),
                'value_comparable' => $criteria['value_comparable'] ?? null,
                'complete_set' => $criteria['complete_set'] ?? null,
            ];
            $mandatory = collect(ReplacementCandidate::REQUIRED_CRITERIA)
                ->mapWithKeys(fn (string $key): array => [$key => (bool) ($criteria[$key] ?? false)])
                ->all();
            $scoreValues = [...array_values($mandatory), ...array_values(array_filter($advisory, 'is_bool'))];
            $score = $scoreValues === [] ? 0 : (int) round(100 * count(array_filter($scoreValues)) / count($scoreValues));

            $candidate->update([
                ...$mandatory, ...$advisory, 'match_score' => $score,
                'status' => 'under_review', 'reviewer_comment' => $comment,
                'reviewed_by' => $reviewer->getKey(), 'reviewed_at' => now(),
            ]);
            $old = $this->caseSnapshot($case);
            $case->update(['status' => 'under_review']);
            $this->audit->logRequired('incident.replacement_reviewed', 'circulation_incident_case', $case->getKey(), $old, [
                ...$this->caseSnapshot($case), 'candidate_id' => $candidate->getKey(),
                'failed_criteria' => $candidate->failedRequiredCriteria(), 'score' => $score,
                'year_tolerance' => $tolerance,
            ], $comment, 'operational', $this->caseSnapshot($case), $reviewer);

            return $candidate->refresh();
        });
    }

    public function decide(
        ReplacementCandidate $candidate,
        User $actor,
        string $decision,
        string $reason,
        bool $exception = false,
        array $exceptionCriteria = [],
        string $resolutionType = 'replacement',
        bool $fineRemains = false,
    ): CirculationIncidentCase {
        return DB::transaction(function () use ($candidate, $actor, $decision, $reason, $exception, $exceptionCriteria, $resolutionType, $fineRemains): CirculationIncidentCase {
            $candidate = ReplacementCandidate::query()->whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
            $case = $this->lockOpenCase($candidate->incidentCase);
            if ($candidate->status === 'approved') {
                throw CirculationException::because('replacement_already_approved');
            }
            if ($decision === 'approve' && $candidate->reviewed_at === null) {
                throw ValidationException::withMessages(['decision' => __('incidents.errors.review_required')]);
            }
            $failed = $candidate->failedRequiredCriteria();
            if ($decision === 'approve' && $failed !== [] && ! $exception) {
                throw ValidationException::withMessages(['decision' => __('incidents.errors.mandatory_failed')]);
            }
            if ($exception) {
                abort_unless($actor->can('incidents.approve_exception'), 403);
                if ($reason === '' || $exceptionCriteria === []) {
                    throw ValidationException::withMessages(['reason' => __('incidents.errors.exception_reason')]);
                }
            }
            $enabledResolutions = (array) Setting::valueFor('incident_resolution_types', CirculationIncidentCase::RESOLUTIONS);
            if ($decision === 'approve' && ! in_array($resolutionType, $enabledResolutions, true)) {
                throw ValidationException::withMessages(['resolution_type' => __('incidents.errors.resolution_disabled')]);
            }
            if ($decision === 'approve' && $resolutionType === 'monetary_compensation') {
                if (! (bool) Setting::valueFor('monetary_compensation_allowed', false)) {
                    throw ValidationException::withMessages(['resolution_type' => __('incidents.errors.monetary_disabled')]);
                }
                abort_unless($actor->can('incidents.approve_exception'), 403);
            }
            if ($decision === 'approve' && $resolutionType === 'no_charge') {
                abort_unless($actor->can('incidents.approve_exception'), 403);
            }

            $old = $this->caseSnapshot($case);
            if ($decision === 'approve') {
                $candidate->update([
                    'status' => 'approved',
                    'exception_criteria' => $exceptionCriteria ?: null,
                    'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(),
                    'reviewer_comment' => $reason,
                ]);
                $case->update([
                    'status' => 'awaiting_registration', 'resolution_type' => $resolutionType,
                    'decision_reason' => $reason, 'fine_remains' => $fineRemains,
                    'requires_director' => $exception,
                ]);
                $event = $exception ? 'incident.exception_approved' : 'incident.replacement_approved';
                $notification = 'incident_replacement_approved';
            } elseif ($decision === 'reject') {
                if ($reason === '') {
                    throw ValidationException::withMessages(['reason' => __('incidents.errors.reason_required')]);
                }
                $candidate->update(['status' => 'rejected', 'reviewer_comment' => $reason, 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now()]);
                $case->update(['status' => 'awaiting_reader', 'decision_reason' => $reason]);
                $event = 'incident.replacement_rejected';
                $notification = 'incident_replacement_rejected';
            } else {
                $candidate->update(['status' => 'clarification_requested', 'reviewer_comment' => $reason]);
                $case->update(['status' => 'awaiting_reader', 'decision_reason' => $reason]);
                $event = 'incident.replacement_rejected';
                $notification = 'incident_other_edition_required';
            }

            $this->audit->logRequired($event, 'circulation_incident_case', $case->getKey(), $old, [
                ...$this->caseSnapshot($case), 'candidate_id' => $candidate->getKey(),
                'failed_criteria' => $failed, 'exception_criteria' => $exceptionCriteria,
            ], $reason, 'operational', $this->caseSnapshot($case), $actor);
            $this->notify($case->reader, $notification, $case);

            return $case->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function registerReplacement(CirculationIncidentCase $case, User $actor, array $attributes): BookCopy
    {
        return DB::transaction(function () use ($case, $actor, $attributes): BookCopy {
            $case = CirculationIncidentCase::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'awaiting_registration', 409);
            if ($case->replacement_copy_id !== null) {
                throw CirculationException::because('replacement_already_registered');
            }
            $candidate = $case->candidates()->where('status', 'approved')->latest('reviewed_at')->firstOrFail();
            $recordId = $attributes['bibliographic_record_id'] ?? $candidate->bibliographic_record_id;
            if ($recordId === null) {
                throw ValidationException::withMessages(['bibliographic_record_id' => __('incidents.errors.record_required')]);
            }

            $copy = BookCopy::query()->create([
                'bibliographic_record_id' => $recordId,
                'inventory_number' => $attributes['inventory_number'],
                'barcode' => $attributes['barcode'],
                'branch_id' => $attributes['branch_id'] ?? null,
                'fund_id' => $attributes['fund_id'] ?? null,
                'shelf_location' => $attributes['shelf_location'] ?? null,
                'storage_sigla' => $attributes['storage_sigla'] ?? null,
                'condition' => $attributes['condition'],
                'registration_date' => $attributes['registration_date'] ?? today(),
                'acquisition_date' => $attributes['registration_date'] ?? today(),
                'acquisition_source' => 'reader_replacement',
                'price' => $attributes['price'] ?? null,
                'status' => 'available',
                'defect_description' => $attributes['notes'] ?? null,
            ]);
            $copy->recordHistory('replacement_registered', $case->reader_id, $actor->getKey(), null, [
                'incident_case_id' => $case->getKey(),
                'case_number' => $case->case_number,
                'replaces_copy_id' => $case->original_copy_id,
            ]);

            $old = $this->caseSnapshot($case);
            $case->update([
                'replacement_copy_id' => $copy->getKey(), 'status' => 'resolved',
                'resolved_at' => now(), 'resolved_by' => $actor->getKey(),
            ]);
            if ($case->fine_id !== null && ! $case->fine_remains) {
                $fine = Fine::query()->whereKey($case->fine_id)->lockForUpdate()->first();
                if ($fine?->status === 'pending') {
                    abort_unless($actor->can('fines.waive'), 403);
                    $fine->update(['status' => 'waived', 'resolved_at' => now(), 'resolved_by' => $actor->getKey(), 'notes' => trim(($fine->notes ? $fine->notes."\n" : '').__('incidents.fine_closed_by_replacement'))]);
                    $this->audit->logRequired('incident.fine_waived', 'circulation_incident_case', $case->getKey(), [
                        'fine_id' => $fine->getKey(), 'status' => 'pending',
                    ], ['fine_id' => $fine->getKey(), 'status' => 'waived'], $case->decision_reason, 'operational', $this->caseSnapshot($case), $actor);
                }
            }

            $meta = $this->caseSnapshot($case);
            $this->audit->logRequired('incident.replacement_copy_registered', 'circulation_incident_case', $case->getKey(), $old, [
                ...$meta, 'replacement_copy_id' => $copy->getKey(),
                'inventory_number' => $copy->inventory_number, 'barcode' => $copy->barcode,
            ], $attributes['notes'] ?? null, 'operational', $meta, $actor);
            $this->audit->logRequired('incident.resolved', 'circulation_incident_case', $case->getKey(), $old, $meta, $case->decision_reason, 'operational', $meta, $actor);
            $this->notify($case->reader, 'incident_resolved', $case);

            return $copy;
        });
    }

    public function reopen(CirculationIncidentCase $case, User $actor, string $reason): CirculationIncidentCase
    {
        return DB::transaction(function () use ($case, $actor, $reason): CirculationIncidentCase {
            $case = CirculationIncidentCase::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(in_array($case->status, ['resolved', 'cancelled'], true), 409);
            $old = $this->caseSnapshot($case);
            $case->update(['status' => 'disputed', 'resolved_at' => null, 'resolved_by' => null, 'decision_reason' => $reason]);
            $this->audit->logRequired('incident.reopened', 'circulation_incident_case', $case->getKey(), $old, $this->caseSnapshot($case), $reason, 'operational', $this->caseSnapshot($case), $actor);

            return $case;
        });
    }

    public function resolveWithoutReplacement(
        CirculationIncidentCase $case,
        User $actor,
        string $resolutionType,
        string $reason,
        bool $waiveFine = false,
    ): CirculationIncidentCase {
        return DB::transaction(function () use ($case, $actor, $resolutionType, $reason, $waiveFine): CirculationIncidentCase {
            $case = $this->lockOpenCase($case);
            $allowed = ['fine', 'repair', 'write_off', 'monetary_compensation', 'no_charge'];
            if (! in_array($resolutionType, $allowed, true)
                || ! in_array($resolutionType, (array) Setting::valueFor('incident_resolution_types', CirculationIncidentCase::RESOLUTIONS), true)) {
                throw ValidationException::withMessages(['resolution_type' => __('incidents.errors.resolution_disabled')]);
            }
            if (in_array($resolutionType, ['monetary_compensation', 'no_charge'], true)) {
                abort_unless($actor->can('incidents.approve_exception'), 403);
            }
            if ($resolutionType === 'monetary_compensation'
                && ! (bool) Setting::valueFor('monetary_compensation_allowed', false)) {
                throw ValidationException::withMessages(['resolution_type' => __('incidents.errors.monetary_disabled')]);
            }

            $old = $this->caseSnapshot($case);
            $copyStatus = match ($resolutionType) {
                'repair' => 'under_repair',
                'write_off' => 'written_off',
                default => $case->originalCopy->status,
            };
            $case->originalCopy->update(['status' => $copyStatus]);

            if ($waiveFine && $case->fine_id !== null) {
                abort_unless($actor->can('fines.waive'), 403);
                $fine = Fine::query()->whereKey($case->fine_id)->lockForUpdate()->first();
                if ($fine?->status === 'pending') {
                    $fine->update([
                        'status' => 'waived', 'resolved_at' => now(), 'resolved_by' => $actor->getKey(),
                        'notes' => trim(($fine->notes ? $fine->notes."\n" : '').$reason),
                    ]);
                    $this->audit->logRequired('incident.fine_waived', 'circulation_incident_case', $case->getKey(), [
                        'fine_id' => $fine->getKey(), 'status' => 'pending',
                    ], ['fine_id' => $fine->getKey(), 'status' => 'waived'], $reason, 'operational', $this->caseSnapshot($case), $actor);
                }
            }

            $case->update([
                'status' => 'resolved', 'resolution_type' => $resolutionType,
                'decision_reason' => $reason, 'resolved_at' => now(), 'resolved_by' => $actor->getKey(),
                'fine_remains' => ! $waiveFine && $case->fine?->status === 'pending',
            ]);
            $this->audit->logRequired('incident.resolved', 'circulation_incident_case', $case->getKey(), $old, $this->caseSnapshot($case), $reason, 'operational', $this->caseSnapshot($case), $actor);
            $this->notify($case->reader, 'incident_resolved', $case);

            return $case->refresh();
        });
    }

    public function cancel(CirculationIncidentCase $case, User $actor, string $reason): CirculationIncidentCase
    {
        return DB::transaction(function () use ($case, $actor, $reason): CirculationIncidentCase {
            $case = $this->lockOpenCase($case);
            $old = $this->caseSnapshot($case);
            $case->update([
                'status' => 'cancelled', 'decision_reason' => $reason,
                'resolved_at' => now(), 'resolved_by' => $actor->getKey(),
            ]);
            $this->audit->logRequired('incident.cancelled', 'circulation_incident_case', $case->getKey(), $old, $this->caseSnapshot($case), $reason, 'operational', $this->caseSnapshot($case), $actor);

            return $case;
        });
    }

    public function notifyDueSoon(): int
    {
        $count = 0;
        CirculationIncidentCase::query()->open()
            ->whereBetween('resolution_due_at', [now(), now()->addDays(3)])
            ->with('reader')
            ->chunkById(100, function ($cases) use (&$count): void {
                foreach ($cases as $case) {
                    $exists = ReaderNotification::query()
                        ->where('user_id', $case->reader_id)
                        ->where('event_type', 'incident_due_soon')
                        ->where('payload->incident_case_id', $case->getKey())
                        ->exists();
                    if (! $exists && $case->reader !== null) {
                        $this->notify($case->reader, 'incident_due_soon', $case);
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function lockOpenCase(CirculationIncidentCase $case): CirculationIncidentCase
    {
        $locked = CirculationIncidentCase::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
        if (! in_array($locked->status, CirculationIncidentCase::OPEN_STATUSES, true)) {
            throw CirculationException::because('incident_case_closed');
        }

        return $locked;
    }

    private function same(mixed $a, mixed $b): ?bool
    {
        if ($a === null || $b === null || trim((string) $a) === '' || trim((string) $b) === '') {
            return null;
        }

        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    /** @return array<string, mixed> */
    private function caseSnapshot(CirculationIncidentCase $case): array
    {
        return [
            'case_id' => $case->getKey(), 'case_number' => $case->case_number,
            'status' => $case->status, 'incident_type' => $case->incident_type,
            'loan_id' => $case->loan_id, 'copy_id' => $case->original_copy_id,
            'reader_id' => $case->reader_id, 'fine_id' => $case->fine_id,
            'replacement_copy_id' => $case->replacement_copy_id,
            'resolution_type' => $case->resolution_type,
        ];
    }

    private function notify(?User $reader, string $event, CirculationIncidentCase $case): void
    {
        if ($reader === null) {
            return;
        }
        $this->notifications->sendLocalized(
            $reader, $event, 'incidents.notifications.'.$event.'.title',
            'incidents.notifications.'.$event.'.body',
            ['case' => $case->case_number],
            ['incident_case_id' => $case->getKey(), 'case_number' => $case->case_number],
        );
    }
}
