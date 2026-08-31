<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\LegacyMarcField;
use App\Models\DataQualityIssue;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuEntryItem;
use App\Models\Recovery\LegacyImportConflict;
use App\Models\Recovery\LegacyImportQuarantine;
use App\Models\Recovery\LegacyMarcCopy;
use App\Models\Recovery\LegacyRecoveryReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Read-only, source-aware classification of recovered-data work queues. */
final class RecoveryQualityService
{
    /** @return Collection<int,array{code:string,taxonomy:string,severity:string,count:int,route:string}> */
    public function categories(): Collection
    {
        $reviewedFundRaw = Schema::hasTable('legacy_recovery_reviews')
            ? LegacyRecoveryReview::query()->where('review_type', 'fund_raw')->whereNotNull('resolved_at')->count()
            : 0;
        $malformedKsu = KsuConflict::query()->whereNotNull('ksu_number_raw')->get(['ksu_number_raw'])
            ->filter(static fn (KsuConflict $conflict): bool => preg_match('/^[1-9]\d*\/(?:19|20)\d{2}$/', trim((string) $conflict->ksu_number_raw)) !== 1)
            ->count();

        return collect([
            $this->category('ksu_unresolved', 'MIGRATION_CONFLICT', 'high', KsuConflict::query()->where('status', 'open')->count(), 'ksu'),
            $this->category('invalid_ksu', 'DATA_ERROR', 'high', $malformedKsu, 'ksu'),
            $this->category('orphan_copy', 'MIGRATION_CONFLICT', 'high', LegacyImportQuarantine::query()->where('status', 'open')->where('kind', 'orphan_copy')->count(), 'quarantine'),
            $this->category('duplicate_inventory', 'MIGRATION_CONFLICT', 'critical', LegacyImportQuarantine::query()->where('status', 'open')->where('kind', 'duplicate_inventory')->count() + LegacyImportConflict::query()->where('status', 'open')->where('reason', 'duplicate_inventory')->count(), 'conflicts'),
            $this->category('source_current_conflict', 'MIGRATION_CONFLICT', 'high', LegacyImportConflict::query()->where('status', 'open')->count(), 'conflicts'),
            $this->category('fund_raw', 'LEGACY_OPTIONAL', 'medium', max(0, BookCopy::query()->whereNotNull('fund_raw')->where('fund_raw', '!=', '')->count() - $reviewedFundRaw), 'fund_raw'),
            $this->category('missing_barcode', 'LEGACY_OPTIONAL', 'info', BookCopy::query()->whereNotNull('legacy_imported_at')->where(fn ($query) => $query->whereNull('barcode')->orWhere('barcode', ''))->count(), 'copies'),
            $this->category('synthetic_inventory', 'SOURCE_EMPTY', 'medium', BookCopy::query()->where('inventory_number_is_synthetic', true)->count(), 'copies'),
            $this->category('missing_classification', 'MODERN_ENRICHMENT_NEEDED', 'low', BibliographicRecord::query()->where(fn ($query) => $query->whereNull('udc_code')->orWhere('udc_code', ''))->count(), 'catalog'),
            $this->category('invalid_isbn', 'DATA_ERROR', 'high', DataQualityIssue::query()->actionable()->where('rule_code', 'bib.isbn.invalid')->count(), 'issues'),
            $this->category('legacy_language', 'MODERN_ENRICHMENT_NEEDED', 'low', DataQualityIssue::query()->actionable()->where('rule_code', 'bib.language.legacy_code')->count(), 'issues'),
            $this->category('legacy_encoding', 'DATA_ERROR', 'high', DataQualityIssue::query()->actionable()->where('rule_code', 'like', 'encoding.%')->count(), 'issues'),
            $this->category('unmapped_marc', 'LEGACY_OPTIONAL', 'info', LegacyMarcField::query()->where('is_known_tag', false)->count(), 'raw_marc'),
            $this->category('legacy_without_ksu', 'LEGACY_OPTIONAL', 'info', $this->legacyWithoutKsuCount(), 'without_ksu'),
        ])->sortByDesc(static fn (array $category): array => [
            match ($category['severity']) {'critical'=>5,'high'=>4,'medium'=>3,'low'=>2,default=>1},
            $category['count'],
        ])->values();
    }

    /** @return array{linked:int,unresolved:int,without_ksu:int,total:int,source_total:int,balanced:bool} */
    public function ksuReconciliation(): array
    {
        $linked = KsuEntryItem::query()->where('link_method', 'like', 'exact INV.T990t%')->count();
        $unresolved = KsuConflict::query()->whereNotNull('source_inv_id')->count();
        $without = $this->legacyWithoutKsuCount();
        $sourceTotal = LegacyMarcCopy::query()->count();

        return [
            'linked' => $linked,
            'unresolved' => $unresolved,
            'without_ksu' => $without,
            'total' => $linked + $unresolved + $without,
            'source_total' => $sourceTotal,
            'balanced' => $linked + $unresolved + $without === $sourceTotal,
        ];
    }

    private function legacyWithoutKsuCount(): int
    {
        $linkedWithoutKsu = BookCopy::query()->whereNotNull('legacy_imported_at')
            ->where(fn ($query) => $query->whereNull('ksu_number')->orWhere('ksu_number', ''))->count();
        $orphanWithoutKsu = LegacyImportQuarantine::query()->where('kind', 'orphan_copy')->count();

        return $linkedWithoutKsu + $orphanWithoutKsu;
    }

    /** @return array{code:string,taxonomy:string,severity:string,count:int,route:string} */
    private function category(string $code, string $taxonomy, string $severity, int $count, string $route): array
    {
        return compact('code', 'taxonomy', 'severity', 'count', 'route');
    }
}
