<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ReaderProfile;
use App\Models\DataCorrectionBatch;
use App\Models\DataCorrectionBatchItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Library\IsbnService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BulkCorrectionService
{
    /** @var array<string,class-string<Model>> */
    private const ENTITIES = [
        'bibliographic_record' => BibliographicRecord::class,
        'book_copy' => BookCopy::class,
        'reader_profile' => ReaderProfile::class,
    ];

    /** @var list<string> */
    private const OPERATIONS = [
        'normalize_spaces', 'remove_control_characters', 'normalize_isbn',
        'normalize_language', 'set_branch', 'set_fund', 'set_category', 'fill_value',
    ];

    public function __construct(
        private readonly IsbnService $isbn,
        private readonly AuditLogger $audit,
        private readonly DataQualityNotificationService $notifications,
    ) {}

    /**
     * @param  list<int|string>  $ids
     * @param  array<string,mixed>  $config
     */
    public function preview(string $entityType, array $ids, string $operation, array $config, string $reason, User $actor): DataCorrectionBatch
    {
        $modelClass = self::ENTITIES[$entityType] ?? throw new RuntimeException('Unsupported bulk entity.');
        if (! in_array($operation, self::OPERATIONS, true)) {
            throw new RuntimeException('Unsupported or unsafe bulk operation.');
        }
        $ids = array_values(array_unique($ids));
        $limit = min(10000, max(1, (int) Setting::valueFor('data_quality_bulk_batch_limit', 1000)));
        if ($ids === [] || count($ids) > $limit) {
            throw new RuntimeException("A batch must contain between 1 and {$limit} records.");
        }

        return DB::transaction(function () use ($modelClass, $entityType, $ids, $operation, $config, $reason, $actor): DataCorrectionBatch {
            $batch = DataCorrectionBatch::query()->create([
                'batch_number' => 'DQB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'operation_type' => $operation,
                'entity_type' => $entityType,
                'status' => 'previewed',
                'selection_filter' => ['ids' => $ids],
                'operation_config' => $config,
                'dry_run' => true,
                'high_risk' => in_array($operation, ['set_branch', 'set_fund', 'set_category', 'fill_value'], true),
                'records_selected' => count($ids),
                'initiated_by' => $actor->getKey(),
                'reason' => $reason,
            ]);
            $modelClass::query()->whereKey($ids)->orderBy((new $modelClass)->getQualifiedKeyName())->each(function (Model $model) use ($batch): void {
                $before = $this->snapshot($model);
                $after = $this->transform($model, $batch->operation_type, $batch->operation_config);
                DataCorrectionBatchItem::query()->create([
                    'batch_id' => $batch->getKey(),
                    'entity_id' => (string) $model->getKey(),
                    'status' => 'previewed',
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                ]);
            });
            $this->audit->logRequired('data_quality.bulk_previewed', 'data_correction_batch', $batch->getKey(), newValues: $batch->toArray(), reason: $reason, scope: 'operational', actor: $actor);
            if ($batch->high_risk || (bool) Setting::valueFor('data_quality_bulk_approval_required', true)) {
                $this->notifications->approvalDigest(
                    'data_quality.approve_bulk',
                    'data_quality_bulk_approval_required',
                    'data_quality.notifications.bulk_title',
                    'data_quality.notifications.bulk_body',
                    ['number' => $batch->batch_number],
                    ['correction_batch_id' => $batch->getKey()],
                );
            }

            return $batch->load('items');
        });
    }

    public function approve(DataCorrectionBatch $batch, User $actor): DataCorrectionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DataCorrectionBatch {
            $batch = DataCorrectionBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if ($batch->status !== 'previewed') {
                throw new RuntimeException('Only a previewed batch can be approved.');
            }
            if ((bool) Setting::valueFor('data_quality_bulk_approval_required', true)
                && (int) $batch->initiated_by === (int) $actor->getKey()) {
                throw new RuntimeException('The batch initiator cannot perform the independent approval.');
            }
            $batch->update(['status' => 'approved', 'approved_by' => $actor->getKey(), 'approved_at' => now()]);

            return $batch;
        });
    }

    public function execute(DataCorrectionBatch $batch, User $actor): DataCorrectionBatch
    {
        $batch = DB::transaction(function () use ($batch): DataCorrectionBatch {
            $batch = DataCorrectionBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            $approvalRequired = $batch->high_risk || (bool) Setting::valueFor('data_quality_bulk_approval_required', true);
            if (($approvalRequired && $batch->status !== 'approved') || (! $approvalRequired && ! in_array($batch->status, ['previewed', 'approved'], true))) {
                throw new RuntimeException('This batch has not passed its required approval step.');
            }
            $batch->update(['status' => 'running', 'dry_run' => false]);

            return $batch;
        });

        $success = 0;
        $failed = 0;
        foreach ($batch->items()->orderBy('id')->cursor() as $item) {
            try {
                DB::transaction(function () use ($batch, $item): void {
                    $modelClass = self::ENTITIES[$batch->entity_type];
                    $model = $modelClass::query()->lockForUpdate()->findOrFail($item->entity_id);
                    if ($this->snapshot($model) !== $item->before_snapshot) {
                        throw new RuntimeException('Record changed after preview; re-preview is required.');
                    }
                    $model->forceFill($item->after_snapshot)->save();
                    $item->update(['status' => 'succeeded']);
                });
                $success++;
            } catch (Throwable $exception) {
                $item->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 65000)]);
                $failed++;
            }
        }
        $batch->update([
            'status' => $failed === 0 ? 'completed' : ($success > 0 ? 'partially_completed' : 'failed'),
            'records_succeeded' => $success,
            'records_failed' => $failed,
            'executed_at' => now(),
        ]);
        $this->audit->logRequired('data_quality.bulk_executed', 'data_correction_batch', $batch->getKey(), newValues: $batch->fresh()->toArray(), reason: $batch->reason, scope: 'operational', actor: $actor);

        return $batch->fresh('items');
    }

    public function rollback(DataCorrectionBatch $batch, User $actor): DataCorrectionBatch
    {
        return DB::transaction(function () use ($batch, $actor): DataCorrectionBatch {
            $batch = DataCorrectionBatch::query()->lockForUpdate()->findOrFail($batch->getKey());
            if (! in_array($batch->status, ['completed', 'partially_completed'], true)) {
                throw new RuntimeException('Only a completed batch can be rolled back.');
            }
            $modelClass = self::ENTITIES[$batch->entity_type];
            foreach ($batch->items()->where('status', 'succeeded')->lockForUpdate()->get() as $item) {
                $model = $modelClass::query()->lockForUpdate()->findOrFail($item->entity_id);
                if ($this->snapshot($model) !== $item->after_snapshot) {
                    throw new RuntimeException("Record {$item->entity_id} changed after the batch; automatic rollback is unsafe.");
                }
            }
            foreach ($batch->items()->where('status', 'succeeded')->get() as $item) {
                $modelClass::query()->whereKey($item->entity_id)->update($item->before_snapshot);
                $item->update(['status' => 'rolled_back']);
            }
            $batch->update(['status' => 'rolled_back', 'rolled_back_at' => now()]);
            $this->audit->logRequired('data_quality.bulk_rolled_back', 'data_correction_batch', $batch->getKey(), newValues: $batch->toArray(), reason: $batch->reason, scope: 'operational', actor: $actor);

            return $batch;
        });
    }

    /** @return array<string,mixed> */
    private function transform(Model $model, string $operation, array $config): array
    {
        $field = (string) ($config['field'] ?? '');
        $allowed = $this->allowedFields($model);
        if ($field !== '' && ! in_array($field, $allowed, true)) {
            throw new RuntimeException('The selected field is not bulk-editable.');
        }
        $after = $this->snapshot($model);
        if ($operation === 'normalize_spaces') {
            $after[$field] = trim(preg_replace('/\s+/u', ' ', (string) $model->{$field}) ?? (string) $model->{$field});
        } elseif ($operation === 'remove_control_characters') {
            $after[$field] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $model->{$field});
        } elseif ($operation === 'normalize_isbn' && $model instanceof BibliographicRecord) {
            $result = $this->isbn->validate((string) $model->isbn);
            if (! $result['valid']) {
                throw new RuntimeException('An invalid ISBN cannot be normalized automatically.');
            }
            $after['isbn'] = $result['isbn'];
        } elseif ($operation === 'normalize_language' && $model instanceof BibliographicRecord) {
            $map = ['kaz' => 'kk', 'kz' => 'kk', 'rus' => 'ru', 'eng' => 'en'];
            $after['language'] = $map[mb_strtolower((string) $model->language)] ?? mb_strtolower((string) $model->language);
        } elseif ($operation === 'set_branch' && $model instanceof BookCopy) {
            $after['branch_id'] = (int) $config['value'];
        } elseif ($operation === 'set_fund' && $model instanceof BookCopy) {
            $after['fund_id'] = (int) $config['value'];
        } elseif ($operation === 'set_category' && $model instanceof ReaderProfile) {
            $after['category'] = (string) $config['value'];
        } elseif ($operation === 'fill_value') {
            if ($model->{$field} !== null && $model->{$field} !== '') {
                throw new RuntimeException('fill_value never overwrites an existing value.');
            }
            $after[$field] = $config['value'] ?? null;
        } else {
            throw new RuntimeException('Operation and entity are incompatible.');
        }

        return $after;
    }

    /** @return list<string> */
    private function allowedFields(Model $model): array
    {
        return match (true) {
            $model instanceof BibliographicRecord => ['title', 'subtitle', 'primary_author', 'publisher', 'language', 'isbn', 'udc_code', 'category', 'annotation'],
            $model instanceof BookCopy => ['branch_id', 'fund_id', 'shelf_location', 'storage_sigla', 'condition'],
            $model instanceof ReaderProfile => ['category'],
            default => [],
        };
    }

    /** @return array<string,mixed> */
    private function snapshot(Model $model): array
    {
        return collect($model->only($this->allowedFields($model)))
            ->map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value)
            ->all();
    }
}
