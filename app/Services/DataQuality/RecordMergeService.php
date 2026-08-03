<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\DataQualityIssue;
use App\Models\DuplicateGroup;
use App\Models\RecordMergeOperation;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RecordMergeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DataQualityNotificationService $notifications,
    ) {}

    /** @param array<string,string> $fieldSelection */
    public function propose(
        DuplicateGroup $group,
        BibliographicRecord $target,
        BibliographicRecord $source,
        array $fieldSelection,
        string $reason,
        User $actor,
    ): RecordMergeOperation {
        $target->refresh();
        $source->refresh();
        if ($target->is($source) || $target->merge_status !== 'active' || $source->merge_status !== 'active') {
            throw new RuntimeException('Only two distinct active records can be merged.');
        }

        return DB::transaction(function () use ($group, $target, $source, $fieldSelection, $reason, $actor): RecordMergeOperation {
            $group = DuplicateGroup::query()->lockForUpdate()->findOrFail($group->getKey());
            if (! in_array($group->status, ['open', 'in_review'], true)) {
                throw new RuntimeException('This duplicate group is no longer open for a merge proposal.');
            }
            $memberIds = $group->members()->lockForUpdate()->pluck('bibliographic_record_id');
            if (! $memberIds->contains($target->getKey()) || ! $memberIds->contains($source->getKey())) {
                throw new RuntimeException('Both records must belong to the reviewed duplicate group.');
            }
            $operation = RecordMergeOperation::query()->create([
                'operation_number' => 'MRG-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'duplicate_group_id' => $group->getKey(),
                'target_record_id' => $target->getKey(),
                'source_record_id' => $source->getKey(),
                'status' => 'proposed',
                'field_selection' => $fieldSelection,
                'proposed_by' => $actor->getKey(),
                'reason' => $reason,
            ]);
            $group->update(['status' => 'merge_proposed', 'canonical_record_id' => $target->getKey()]);
            $this->audit->logRequired('data_quality.merge_proposed', 'record_merge_operation', $operation->getKey(), newValues: $operation->toArray(), reason: $reason, scope: 'operational', actor: $actor);
            $this->notifications->approvalDigest(
                'data_quality.approve_merge',
                'data_quality_merge_approval_required',
                'data_quality.notifications.merge_title',
                'data_quality.notifications.merge_body',
                ['number' => $operation->operation_number],
                ['merge_operation_id' => $operation->getKey()],
            );

            return $operation;
        });
    }

    public function approve(RecordMergeOperation $operation, User $actor): RecordMergeOperation
    {
        return DB::transaction(function () use ($operation, $actor): RecordMergeOperation {
            $operation = RecordMergeOperation::query()->lockForUpdate()->findOrFail($operation->getKey());
            if ($operation->status !== 'proposed') {
                throw new RuntimeException('This merge is no longer awaiting approval.');
            }
            if ((bool) Setting::valueFor('data_quality_merge_approval_required', true)
                && (int) $operation->proposed_by === (int) $actor->getKey()) {
                throw new RuntimeException('The merge proposer cannot perform the independent approval.');
            }
            $operation->update(['status' => 'approved', 'approved_by' => $actor->getKey(), 'approved_at' => now()]);
            $this->audit->logRequired('data_quality.merge_approved', 'record_merge_operation', $operation->getKey(), newValues: $operation->toArray(), reason: $operation->reason, scope: 'operational', actor: $actor);

            return $operation;
        });
    }

    public function execute(RecordMergeOperation $operation, User $actor): RecordMergeOperation
    {
        return DB::transaction(function () use ($operation, $actor): RecordMergeOperation {
            $operation = RecordMergeOperation::query()->lockForUpdate()->findOrFail($operation->getKey());
            if ($operation->status !== 'approved') {
                throw new RuntimeException('A merge must be approved exactly once before execution.');
            }
            $target = BibliographicRecord::withTrashed()->lockForUpdate()->findOrFail($operation->target_record_id);
            $source = BibliographicRecord::withTrashed()->lockForUpdate()->findOrFail($operation->source_record_id);
            if ($target->merge_status !== 'active' || $source->merge_status !== 'active' || $target->merged_into_id || $source->merged_into_id) {
                throw new RuntimeException('One of the records has already been merged.');
            }
            if ($this->wouldCreateCycle($target, $source)) {
                throw new RuntimeException('The requested merge would create a redirect cycle.');
            }

            $before = [
                'target' => $this->snapshot($target),
                'source' => $this->snapshot($source),
            ];
            $this->applyFieldSelection($target, $source, $operation->field_selection);
            $target->save();

            $source->copies()->update(['bibliographic_record_id' => $target->getKey()]);
            $source->electronicMaterials()->update(['bibliographic_record_id' => $target->getKey()]);
            $source->reservations()->update(['bibliographic_record_id' => $target->getKey()]);
            DB::table('bibliographic_record_relations')
                ->where('record_id', $source->getKey())
                ->whereIn('related_record_id', fn ($query) => $query->select('related_record_id')->from('bibliographic_record_relations')->where('record_id', $target->getKey()))
                ->delete();
            DB::table('bibliographic_record_relations')
                ->where('related_record_id', $source->getKey())
                ->whereIn('record_id', fn ($query) => $query->select('record_id')->from('bibliographic_record_relations')->where('related_record_id', $target->getKey()))
                ->delete();
            DB::table('bibliographic_record_relations')->where('record_id', $source->getKey())->update(['record_id' => $target->getKey()]);
            DB::table('bibliographic_record_relations')->where('related_record_id', $source->getKey())->update(['related_record_id' => $target->getKey()]);
            DB::table('bibliographic_record_relations')
                ->whereColumn('record_id', 'related_record_id')
                ->delete();
            DataQualityIssue::query()
                ->where('entity_type', 'bibliographic_record')
                ->where('entity_id', (string) $source->getKey())
                ->actionable()
                ->update([
                    'status' => 'resolved',
                    'resolution_type' => 'source_record_merged',
                    'resolution_notes' => "Merged into bibliographic record {$target->getKey()}",
                    'resolved_at' => now(),
                    'resolved_by' => $actor->getKey(),
                ]);

            $source->forceFill(['merge_status' => 'merged', 'merged_into_id' => $target->getKey()])->save();
            $source->delete();
            $after = ['target' => $this->snapshot($target->fresh()), 'source' => $this->snapshot($source->fresh())];
            $operation->update([
                'status' => 'executed',
                'before_snapshot' => $before,
                'after_snapshot' => $after,
                'executed_by' => $actor->getKey(),
                'executed_at' => now(),
            ]);
            $operation->duplicateGroup?->update(['status' => 'merged', 'canonical_record_id' => $target->getKey()]);
            $this->audit->logRequired(
                'data_quality.records_merged',
                'record_merge_operation',
                $operation->getKey(),
                oldValues: $before,
                newValues: $after,
                reason: $operation->reason,
                scope: 'operational',
                actor: $actor,
                metadata: ['target_record_id' => $target->getKey(), 'source_record_id' => $source->getKey()],
            );

            return $operation->fresh();
        }, 3);
    }

    public function rollbackSafety(RecordMergeOperation $operation): array
    {
        if ($operation->status !== 'executed') {
            return ['safe' => false, 'reason' => 'The merge has not been executed.'];
        }
        $source = BibliographicRecord::withTrashed()->find($operation->source_record_id);
        if (! $source || $source->updated_at?->gt($operation->executed_at)) {
            return ['safe' => false, 'reason' => 'The tombstone changed after the merge; controlled correction is required.'];
        }

        return ['safe' => false, 'reason' => 'Automatic rollback is disabled because post-merge circulation cannot be attributed safely.'];
    }

    private function wouldCreateCycle(BibliographicRecord $target, BibliographicRecord $source): bool
    {
        $cursor = $target;
        $visited = [];
        while ($cursor->merged_into_id !== null) {
            if ($cursor->merged_into_id === $source->getKey() || isset($visited[$cursor->merged_into_id])) {
                return true;
            }
            $visited[$cursor->merged_into_id] = true;
            $cursor = BibliographicRecord::withTrashed()->find($cursor->merged_into_id);
            if (! $cursor) {
                break;
            }
        }

        return false;
    }

    /** @param array<string,string> $selection */
    private function applyFieldSelection(BibliographicRecord $target, BibliographicRecord $source, array $selection): void
    {
        $allowed = ['title', 'subtitle', 'primary_author', 'additional_authors', 'publisher', 'publication_year', 'language', 'udc_code', 'author_mark', 'category', 'annotation', 'keywords', 'isbn', 'resource_type', 'notes'];
        foreach ($selection as $field => $choice) {
            if (! in_array($field, $allowed, true)) {
                continue;
            }
            if ($choice === 'source') {
                $target->{$field} = $source->{$field};
            } elseif ($choice === 'combine' && in_array($field, ['additional_authors', 'keywords'], true)) {
                $target->{$field} = collect([...(array) $target->{$field}, ...(array) $source->{$field}])->filter()->unique()->values()->all();
            } elseif (str_starts_with($choice, 'manual:')) {
                $target->{$field} = Str::after($choice, 'manual:');
            }
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(BibliographicRecord $record): array
    {
        return $record->toArray() + [
            'copy_ids' => $record->copies()->pluck('id')->all(),
            'reservation_ids' => $record->reservations()->pluck('id')->all(),
            'electronic_material_ids' => $record->electronicMaterials()->pluck('id')->all(),
        ];
    }
}
