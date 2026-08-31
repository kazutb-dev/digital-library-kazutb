<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Ksu\KsuConflict;
use App\Models\Recovery\LegacyImportQuarantine;
use App\Models\Recovery\LegacyMarcCopy;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrphanCopyResolutionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DataQualityScanner $scanner,
    ) {}

    public function link(LegacyImportQuarantine $quarantine, BibliographicRecord $record, User $actor, string $reason): BookCopy
    {
        try {
            $copy = DB::transaction(function () use ($quarantine, $record, $actor, $reason): BookCopy {
                $quarantine = LegacyImportQuarantine::query()->whereKey($quarantine->getKey())->lockForUpdate()->firstOrFail();
                if ($quarantine->kind !== 'orphan_copy' || $quarantine->status !== 'open') {
                    throw ValidationException::withMessages(['record_id' => __('recovery_quality.validation.orphan_not_open')]);
                }
                $source = LegacyMarcCopy::query()
                    ->where('legacy_import_batch_id', $quarantine->legacy_import_batch_id)
                    ->where('source_inv_id', $quarantine->source_inv_id)
                    ->lockForUpdate()->firstOrFail();
                if ($source->book_copy_id !== null) {
                    throw ValidationException::withMessages(['record_id' => __('recovery_quality.validation.orphan_already_linked')]);
                }
                $payload = $quarantine->payload ?? $source->canonical ?? [];
                $inventory = trim((string) data_get($payload, 'inventory_number'));
                if ($inventory === '') {
                    $inventory = 'MARC-'.$source->source_inv_id;
                }
                if (BookCopy::query()->where('inventory_number', $inventory)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['record_id' => __('recovery_quality.validation.inventory_taken', ['number' => $inventory])]);
                }
                $barcode = trim((string) data_get($payload, 'barcode')) ?: null;
                if ($barcode !== null && BookCopy::query()->where('barcode', $barcode)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['record_id' => __('recovery_quality.validation.barcode_taken', ['barcode' => $barcode])]);
                }
                $legacyState = (int) data_get($payload, 'state_raw', 1);
                $writeoffDate = data_get($payload, 'writeoff_date_iso');
                $status = $legacyState === 2 || filled($writeoffDate) ? 'written_off' : 'available';

                $copy = BookCopy::query()->create([
                    'bibliographic_record_id' => $record->getKey(),
                    'inventory_number' => $inventory,
                    'inventory_number_is_synthetic' => str_starts_with($inventory, 'MARC-'),
                    'legacy_inventory_number' => data_get($payload, 'inventory_number'),
                    'legacy_inv_id' => $source->source_inv_id,
                    'legacy_doc_id' => $source->source_doc_id,
                    'barcode' => $barcode,
                    'ksu_number' => data_get($payload, 'ksu_number_raw'),
                    'storage_sigla' => data_get($payload, 'sigla_code'),
                    'sigla_code' => data_get($payload, 'sigla_code'),
                    'legacy_sigla_id' => data_get($payload, 'sigla_id'),
                    'service_point_code' => data_get($payload, 'service_points'),
                    'local_library_code' => data_get($payload, 'local_library_code_raw'),
                    'shelf_index' => data_get($payload, 'shelf_index_raw'),
                    'fund_raw' => data_get($payload, 'fund_raw'),
                    'price' => data_get($payload, 'normalized_price'),
                    'price_raw' => data_get($payload, 'raw_price'),
                    'currency' => data_get($payload, 'currency'),
                    'acquisition_source' => data_get($payload, 'acquisition_source_raw'),
                    'registration_date' => data_get($payload, 'registration_date_iso'),
                    'accounting_mode_raw' => data_get($payload, 'accounting_mode_raw'),
                    'accounting_type' => match (strtoupper(trim((string) data_get($payload, 'accounting_mode_raw')))) {
                        'I' => 'inventory', 'U' => 'non_inventory', default => null,
                    },
                    'writeoff_date' => $writeoffDate,
                    'writeoff_act' => data_get($payload, 'writeoff_act'),
                    'writeoff_reason' => $legacyState === 2 ? data_get($payload, 'notes') : null,
                    'legacy_notes' => $legacyState === 2 ? null : data_get($payload, 'notes'),
                    'legacy_state_raw' => $legacyState,
                    'legacy_state_label' => data_get($payload, 'state_label'),
                    'condition' => 'good', 'access_restriction' => 'free', 'status' => $status,
                    'legacy_import_batch_id' => $source->legacy_import_batch_id,
                    'legacy_imported_at' => now('UTC'),
                ]);
                $source->update(['book_copy_id' => $copy->getKey(), 'relation_status' => 'linked', 'apply_status' => 'inserted']);
                $quarantine->update(['status' => 'resolved']);
                if (filled($copy->ksu_number)) {
                    KsuConflict::query()->create([
                        'kind' => 'unresolved_link', 'ksu_number_raw' => $copy->ksu_number,
                        'source_inv_id' => $source->source_inv_id, 'source_doc_id' => $source->source_doc_id,
                        'book_copy_id' => $copy->getKey(), 'reason' => 'Manually linked orphan INV retains unresolved source KSU.',
                        'payload' => ['legacy_import_quarantine_id' => $quarantine->getKey()], 'status' => 'open',
                    ]);
                }
                $copy->recordHistory('legacy_orphan_linked', null, $actor->getKey(), null, [
                    'legacy_import_quarantine_id' => $quarantine->getKey(), 'source_inv_id' => $source->source_inv_id,
                    'bibliographic_record_id' => $record->getKey(), 'reason' => $reason,
                ]);
                $this->audit->logRequired(
                    'legacy_recovery.orphan.linked', 'book_copy', $copy->getKey(),
                    newValues: ['source_inv_id' => $source->source_inv_id, 'bibliographic_record_id' => $record->getKey(), 'inventory_number' => $inventory],
                    reason: $reason, scope: 'operational', actor: $actor,
                    metadata: ['legacy_import_quarantine_id' => $quarantine->getKey()],
                );

                return $copy;
            }, 3);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['record_id' => __('recovery_quality.validation.concurrent_duplicate')]);
        }

        $this->scanner->scanModel($copy->fresh(), 'book_copy');
        $this->scanner->scanModel($record->fresh(), 'bibliographic_record');

        return $copy;
    }
}
