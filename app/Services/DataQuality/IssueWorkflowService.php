<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\DataQualityIssue;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IssueWorkflowService
{
    /** @var array<string,class-string<Model>> */
    private const ENTITIES = [
        'bibliographic_record' => BibliographicRecord::class,
        'book_copy' => BookCopy::class,
        'reader_profile' => ReaderProfile::class,
        'loan' => Loan::class,
        'fine' => Fine::class,
        'reservation' => Reservation::class,
    ];

    public function __construct(
        private readonly DataQualityScanner $scanner,
        private readonly AuditLogger $audit,
        private readonly DataQualityNotificationService $notifications,
    ) {}

    public function assign(DataQualityIssue $issue, User $assignee, User $actor): DataQualityIssue
    {
        $result = $this->mutate($issue, $actor, 'data_quality.issue_assigned', function (DataQualityIssue $locked) use ($assignee, $actor): void {
            $locked->update([
                'status' => 'assigned',
                'assigned_to' => $assignee->getKey(),
                'assigned_by' => $actor->getKey(),
            ]);
        });
        $this->notifications->assigned($assignee, $result->issue_number);

        return $result;
    }

    /** @param array<string,mixed> $changes */
    public function correct(DataQualityIssue $issue, array $changes, string $reason, User $actor): DataQualityIssue
    {
        return DB::transaction(function () use ($issue, $changes, $reason, $actor): DataQualityIssue {
            $issue = DataQualityIssue::query()->lockForUpdate()->findOrFail($issue->getKey());
            $entity = $this->entity($issue, true);
            $allowed = $this->allowedFields($entity);
            $unsafe = array_diff(array_keys($changes), $allowed);
            if ($changes === [] || $unsafe !== []) {
                throw new RuntimeException('The correction contains no allowed fields.');
            }
            $before = $entity->only(array_keys($changes));
            $entity->fill($changes)->save();
            $this->audit->logRequired(
                'data_quality.issue_corrected',
                $issue->entity_type,
                $issue->entity_id,
                oldValues: $before,
                newValues: $entity->fresh()->only(array_keys($changes)),
                reason: $reason,
                scope: 'operational',
                actor: $actor,
                metadata: ['issue_id' => $issue->getKey(), 'issue_number' => $issue->issue_number],
            );
            $this->scanner->scanModel($entity->fresh(), $issue->entity_type);
            $issue->refresh();
            if ($issue->status !== 'resolved') {
                $issue->update(['status' => 'in_review', 'resolution_notes' => $reason]);
            }

            return $issue->fresh();
        });
    }

    public function resolve(DataQualityIssue $issue, string $notes, User $actor): DataQualityIssue
    {
        return DB::transaction(function () use ($issue, $notes, $actor): DataQualityIssue {
            $issue = DataQualityIssue::query()->lockForUpdate()->findOrFail($issue->getKey());
            $entity = $this->entity($issue);
            if ($entity) {
                $this->scanner->scanModel($entity, $issue->entity_type);
                $issue->refresh();
            }
            if ($issue->status !== 'resolved') {
                throw new RuntimeException('The rule is still violated; correct it or use a documented false-positive decision.');
            }
            $issue->update(['resolution_notes' => $notes, 'resolved_by' => $actor->getKey()]);

            return $issue;
        });
    }

    public function ignoreUntil(DataQualityIssue $issue, \DateTimeInterface $until, string $reason, User $actor): DataQualityIssue
    {
        return $this->mutate($issue, $actor, 'data_quality.issue_ignored', function (DataQualityIssue $locked) use ($until, $reason, $actor): void {
            $locked->update([
                'status' => 'ignored',
                'ignored_until' => $until,
                'resolution_type' => 'temporarily_ignored',
                'resolution_notes' => $reason,
                'resolved_by' => $actor->getKey(),
            ]);
        }, $reason);
    }

    public function falsePositive(DataQualityIssue $issue, string $reason, User $actor): DataQualityIssue
    {
        return $this->mutate($issue, $actor, 'data_quality.issue_false_positive', function (DataQualityIssue $locked) use ($reason, $actor): void {
            $context = (array) ($locked->context ?? []);
            $context['suppression'] = [
                'current_value' => $locked->current_value,
                'rules_version' => DataQualityRuleRegistry::VERSION,
                'decided_at' => now()->toIso8601String(),
            ];
            $locked->update([
                'status' => 'false_positive',
                'false_positive_reason' => $reason,
                'resolution_type' => 'false_positive',
                'resolved_at' => now(),
                'resolved_by' => $actor->getKey(),
                'context' => $context,
            ]);
        }, $reason);
    }

    public function reopen(DataQualityIssue $issue, string $reason, User $actor): DataQualityIssue
    {
        return $this->mutate($issue, $actor, 'data_quality.issue_reopened', function (DataQualityIssue $locked) use ($reason): void {
            if (! in_array($locked->status, ['resolved', 'ignored', 'false_positive'], true)) {
                throw new RuntimeException('Only a closed issue can be reopened.');
            }
            $locked->update([
                'status' => 'reopened',
                'resolution_notes' => $reason,
                'resolved_at' => null,
                'resolved_by' => null,
                'ignored_until' => null,
            ]);
        }, $reason);
    }

    private function mutate(DataQualityIssue $issue, User $actor, string $event, callable $callback, ?string $reason = null): DataQualityIssue
    {
        return DB::transaction(function () use ($issue, $actor, $event, $callback, $reason): DataQualityIssue {
            $locked = DataQualityIssue::query()->lockForUpdate()->findOrFail($issue->getKey());
            $old = $locked->toArray();
            $callback($locked);
            $this->audit->logRequired($event, 'data_quality_issue', $locked->getKey(), oldValues: $old, newValues: $locked->fresh()->toArray(), reason: $reason, scope: 'operational', actor: $actor);

            return $locked->fresh();
        });
    }

    private function entity(DataQualityIssue $issue, bool $required = false): ?Model
    {
        $class = self::ENTITIES[$issue->entity_type] ?? null;
        $entity = $class === null ? null : $class::query()->find($issue->entity_id);
        if ($required && ! $entity) {
            throw new RuntimeException('The affected entity no longer exists.');
        }

        return $entity;
    }

    /** @return list<string> */
    private function allowedFields(Model $entity): array
    {
        return match (true) {
            $entity instanceof BibliographicRecord => ['title', 'subtitle', 'primary_author', 'publisher', 'publication_year', 'language', 'udc_code', 'author_mark', 'category', 'annotation', 'keywords', 'isbn', 'resource_type', 'notes'],
            $entity instanceof BookCopy => ['inventory_number', 'barcode', 'branch_id', 'fund_id', 'shelf_location', 'price', 'acquisition_date', 'condition', 'defect_description', 'status'],
            $entity instanceof ReaderProfile => ['ticket_number', 'barcode', 'category', 'status', 'block_reason'],
            default => [],
        };
    }
}
