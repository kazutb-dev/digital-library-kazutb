<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\Recovery\LegacyImportConflict;
use App\Models\Recovery\LegacyImportQuarantine;
use App\Models\Recovery\LegacyRecoveryReview;
use App\Services\AuditLogger;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\DataQuality\OrphanCopyResolutionService;
use App\Services\DataQuality\RecoveryQualityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecoveryQualityController extends Controller
{
    /** @var array<string,list<string>> */
    private const CONFLICT_FIELDS = [
        'bibliographic_record' => [
            'title', 'subtitle', 'primary_author', 'additional_authors', 'publisher',
            'publication_place', 'publication_year', 'edition_statement', 'language',
            'isbn', 'issn', 'udc_code', 'bbk_code', 'local_classification',
            'author_mark', 'keywords', 'annotation', 'physical_extent', 'series_title',
            'volume', 'issue', 'part_number', 'part_title', 'control_number', 'notes',
        ],
        'book_copy' => [
            'inventory_number', 'barcode', 'accounting_type', 'ksu_number',
            'storage_sigla', 'service_point_code', 'shelf_index', 'price',
            'acquisition_source', 'registration_date', 'status',
        ],
    ];

    public function index(Request $request, RecoveryQualityService $quality): View
    {
        $filters = $request->validate([
            'queue' => ['nullable', Rule::in(['fund_raw', 'quarantine', 'conflicts', 'without_ksu'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $queue = $filters['queue'] ?? 'fund_raw';
        $search = trim((string) ($filters['q'] ?? ''));

        $rows = match ($queue) {
            'quarantine' => LegacyImportQuarantine::query()->with('batch:id,package_name')
                ->where('status', 'open')
                ->when($search !== '', fn ($query) => $query->where(fn ($scope) => $scope
                    ->where('reason', 'like', '%'.$search.'%')->orWhere('source_inv_id', 'like', '%'.$search.'%')))
                ->latest('id')->paginate(30)->withQueryString(),
            'conflicts' => LegacyImportConflict::query()->with(['batch:id,package_name', 'resolver:id,name'])
                ->where('status', 'open')
                ->when($search !== '', fn ($query) => $query->where(fn ($scope) => $scope
                    ->where('field_name', 'like', '%'.$search.'%')->orWhere('current_value', 'like', '%'.$search.'%')
                    ->orWhere('incoming_value', 'like', '%'.$search.'%')))
                ->latest('id')->paginate(30)->withQueryString(),
            'without_ksu' => BookCopy::query()->with('bibliographicRecord:id,title,primary_author')
                ->whereNotNull('legacy_imported_at')->where(fn ($query) => $query->whereNull('ksu_number')->orWhere('ksu_number', ''))
                ->when($search !== '', fn ($query) => $query->where(fn ($scope) => $scope
                    ->where('inventory_number', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%')))
                ->orderBy('id')->paginate(30)->withQueryString(),
            default => $this->fundRawRows($search),
        };

        return view('librarian.data-quality.recovery', [
            'categories' => $quality->categories(),
            'reconciliation' => $quality->ksuReconciliation(),
            'queue' => $queue,
            'rows' => $rows,
            'filters' => $filters,
            'funds' => Fund::query()->where('is_active', true)->with('branch:id,name')->orderBy('name')->get(),
            'quarantinedOrphans' => LegacyImportQuarantine::query()->where('kind', 'orphan_copy')->count(),
        ]);
    }

    public function resolveFundRaw(
        Request $request,
        BookCopy $copy,
        AuditLogger $audit,
        DataQualityScanner $scanner,
    ): RedirectResponse {
        abort_unless($request->user()->can('legacy_recovery.resolve'), 403);
        abort_unless(filled($copy->fund_raw), 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['map_fund', 'note', 'ignore'])],
            'fund_id' => ['nullable', 'required_if:decision,map_fund', 'integer', 'exists:funds,id'],
            'decision_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $copy, $data, $audit): void {
            $copy = BookCopy::query()->whereKey($copy->getKey())->lockForUpdate()->firstOrFail();
            $old = $copy->only(['branch_id', 'fund_id', 'fund_raw']);
            $targetId = null;
            if ($data['decision'] === 'map_fund') {
                $fund = Fund::query()->whereKey($data['fund_id'])->firstOrFail();
                $copy->update([
                    'fund_id' => $fund->getKey(),
                    'branch_id' => $copy->branch_id ?? $fund->branch_id,
                ]);
                $targetId = $fund->getKey();
            }

            LegacyRecoveryReview::query()->updateOrCreate(
                ['review_type' => 'fund_raw', 'entity_type' => 'book_copy', 'entity_id' => $copy->getKey()],
                [
                    'source_table' => 'dbo.INV', 'source_id' => $copy->legacy_inv_id,
                    'raw_value' => $copy->fund_raw, 'decision' => $data['decision'],
                    'target_type' => $targetId ? 'fund' : null, 'target_id' => $targetId,
                    'decision_note' => $data['decision_note'], 'resolved_by' => $request->user()->getKey(),
                    'resolved_at' => now('UTC'),
                ],
            );
            $audit->logRequired(
                'legacy_recovery.fund_raw.resolved', 'book_copy', $copy->getKey(),
                oldValues: $old,
                newValues: $copy->fresh()->only(['branch_id', 'fund_id', 'fund_raw']) + ['decision' => $data['decision']],
                reason: $data['decision_note'], scope: 'operational', actor: $request->user(),
            );
        });
        $scanner->scanModel($copy->fresh(), 'book_copy');

        return back()->with('success', __('recovery_quality.messages.fund_reviewed'));
    }

    public function resolveConflict(Request $request, LegacyImportConflict $conflict, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('legacy_recovery.resolve'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['keep_current', 'use_legacy', 'custom'])],
            'custom_value' => ['nullable', 'required_if:decision,custom', 'string', 'max:5000'],
            'resolution_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $conflict, $data, $audit): void {
            $conflict = LegacyImportConflict::query()->whereKey($conflict->getKey())->lockForUpdate()->firstOrFail();
            if ($conflict->status !== 'open') {
                throw ValidationException::withMessages(['decision' => __('recovery_quality.validation.already_resolved')]);
            }
            if (! in_array($conflict->field_name, self::CONFLICT_FIELDS[$conflict->entity_type] ?? [], true)) {
                throw ValidationException::withMessages(['decision' => __('recovery_quality.validation.field_not_supported')]);
            }

            $entity = $this->conflictEntity($conflict, true);
            $oldEntity = $entity->only([$conflict->field_name]);
            if ($data['decision'] !== 'keep_current') {
                $rawValue = $data['decision'] === 'custom' ? $data['custom_value'] : $conflict->incoming_value;
                $entity->setAttribute($conflict->field_name, $this->decodeConflictValue($rawValue));
                $entity->save();
            }
            $status = $data['decision'] === 'keep_current' ? 'kept_current' : 'applied_incoming';
            $conflict->update([
                'status' => $status, 'resolution_note' => $data['resolution_note'],
                'resolved_by' => $request->user()->getKey(), 'resolved_at' => now('UTC'),
            ]);
            $audit->logRequired(
                'legacy_recovery.conflict.resolved', $conflict->entity_type, $entity->getKey(),
                oldValues: $oldEntity + ['conflict_status' => 'open'],
                newValues: $entity->fresh()->only([$conflict->field_name]) + ['conflict_status' => $status, 'decision' => $data['decision']],
                reason: $data['resolution_note'], scope: 'operational', actor: $request->user(),
                metadata: ['legacy_import_conflict_id' => $conflict->getKey(), 'source_id' => $conflict->source_id],
            );
        });

        return back()->with('success', __('recovery_quality.messages.conflict_resolved'));
    }

    public function linkOrphan(
        Request $request,
        LegacyImportQuarantine $quarantine,
        OrphanCopyResolutionService $orphans,
    ): RedirectResponse {
        abort_unless($request->user()->can('legacy_recovery.resolve'), 403);
        $data = $request->validate([
            'record_id' => ['required', 'integer', 'exists:bibliographic_records,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $copy = $orphans->link(
            $quarantine,
            BibliographicRecord::query()->findOrFail($data['record_id']),
            $request->user(),
            $data['reason'],
        );

        return redirect()->route('librarian.copies.show', $copy)
            ->with('success', __('recovery_quality.messages.orphan_linked'));
    }

    private function fundRawRows(string $search)
    {
        $query = BookCopy::query()->with(['bibliographicRecord:id,title,primary_author', 'branch:id,name', 'fund:id,name'])
            ->whereNotNull('fund_raw')->where('fund_raw', '!=', '');
        if (Schema::hasTable('legacy_recovery_reviews')) {
            $query->whereNotExists(fn ($review) => $review->selectRaw('1')->from('legacy_recovery_reviews')
                ->whereColumn('legacy_recovery_reviews.entity_id', 'book_copies.id')
                ->where('legacy_recovery_reviews.entity_type', 'book_copy')
                ->where('legacy_recovery_reviews.review_type', 'fund_raw')
                ->whereNotNull('legacy_recovery_reviews.resolved_at'));
        }
        if ($search !== '') {
            $query->where(fn ($scope) => $scope->where('inventory_number', 'like', '%'.$search.'%')
                ->orWhere('fund_raw', 'like', '%'.$search.'%')->orWhere('storage_sigla', 'like', '%'.$search.'%'));
        }

        return $query->orderBy('id')->paginate(30)->withQueryString();
    }

    private function conflictEntity(LegacyImportConflict $conflict, bool $lock = false): Model
    {
        $query = match ($conflict->entity_type) {
            'bibliographic_record' => BibliographicRecord::query(),
            'book_copy' => BookCopy::query(),
            default => throw ValidationException::withMessages(['decision' => __('recovery_quality.validation.entity_not_supported')]),
        };

        return $query->when($lock, fn ($builder) => $builder->lockForUpdate())->findOrFail($conflict->entity_id);
    }

    private function decodeConflictValue(?string $value): mixed
    {
        if ($value === null || trim($value) === 'null') {
            return null;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
