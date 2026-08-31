<?php

namespace App\Services\Reports;

use App\Support\DatabaseSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Canonical datasets for accession, KSU and collection-accounting forms.
 *
 * The service intentionally uses the same live catalogue/KSU tables as the
 * operational workflows. Every optional recovery table/column is guarded so
 * a rolling deployment returns an explicit empty form instead of fabricated
 * figures. Queries use only SQL supported by PostgreSQL and SQLite.
 */
final class CollectionAccountingReportService
{
    public const CODES = [
        'ksu-part-1',
        'ksu-part-2',
        'ksu-part-3',
        'ksu-register',
        'acquisition-act',
        'inventory-book',
        'non-inventory-book',
        'new-arrivals',
        'fund-by-sigla',
        'fund-by-language',
        'fund-by-type',
        'fund-by-udc',
        'acquisitions-by-source-value',
        'writeoffs',
    ];

    /** @return list<string> */
    public static function filters(string $code): array
    {
        $filters = ['preset', 'from', 'to'];

        if (in_array($code, self::CODES, true)) {
            $filters = [...$filters, 'branch_id', 'fund_id'];
        }
        if (in_array($code, [
            'inventory-book', 'non-inventory-book', 'new-arrivals',
            'fund-by-sigla', 'fund-by-language', 'fund-by-type', 'fund-by-udc',
            'acquisitions-by-source-value', 'writeoffs',
        ], true)) {
            $filters = [...$filters, 'resource_type', 'language', 'udc'];
        }
        if (in_array($code, [
            'ksu-part-1', 'ksu-register', 'acquisition-act', 'inventory-book',
            'non-inventory-book', 'new-arrivals', 'acquisitions-by-source-value',
        ], true)) {
            $filters[] = 'acquisition_source';
        }
        if (in_array($code, ['ksu-part-1', 'ksu-part-2', 'ksu-register', 'acquisition-act', 'inventory-book', 'non-inventory-book'], true)) {
            $filters[] = 'status';
        }

        return array_values(array_unique($filters));
    }

    /** @return list<string> */
    public static function columns(string $code): array
    {
        return match ($code) {
            'ksu-part-1' => [
                'entry_number', 'entry_date', 'acquisition_source', 'supplier',
                'branch', 'fund', 'titles', 'copies', 'total_value', 'status',
            ],
            'ksu-part-2' => [
                'entry_number', 'entry_date', 'act_number', 'reason', 'branch',
                'fund', 'titles', 'copies', 'total_value', 'status',
            ],
            'ksu-part-3' => [
                'year', 'fund', 'arrivals_titles', 'arrivals_copies', 'arrivals_value',
                'writeoff_titles', 'writeoff_copies', 'writeoff_value', 'net_copies', 'net_value',
            ],
            'ksu-register' => [
                'part', 'entry_number', 'entry_date', 'operation', 'act_number',
                'acquisition_source', 'supplier', 'branch', 'fund', 'titles',
                'copies', 'total_value', 'status',
            ],
            'acquisition-act' => [
                'batch_number', 'received_date', 'status', 'acquisition_source',
                'supplier', 'branch', 'fund', 'ksu_number', 'titles', 'copies',
                'total_value', 'currency', 'confirmed_at',
            ],
            'inventory-book', 'non-inventory-book' => [
                'inventory_number', 'barcode', 'title', 'author', 'registration_date',
                'accounting_type', 'ksu_number', 'sigla', 'branch', 'fund',
                'language', 'resource_type', 'udc', 'price', 'status',
            ],
            'new-arrivals' => [
                'received_date', 'acquisition_source', 'branch', 'fund',
                'titles', 'copies', 'total_value',
            ],
            'fund-by-sigla' => ['sigla', 'titles', 'copies', 'total_value'],
            'fund-by-language' => ['language', 'titles', 'copies', 'total_value'],
            'fund-by-type' => ['resource_type', 'titles', 'copies', 'total_value'],
            'fund-by-udc' => ['udc', 'titles', 'copies', 'total_value'],
            'acquisitions-by-source-value' => ['acquisition_source', 'titles', 'copies', 'total_value'],
            'writeoffs' => [
                'writeoff_date', 'act_number', 'inventory_number', 'title', 'reason',
                'accounting_type', 'ksu_number', 'branch', 'fund', 'price',
            ],
            default => ['dimension', 'total'],
        };
    }

    /** @return list<string> */
    public static function totals(string $code): array
    {
        return match ($code) {
            'ksu-part-1', 'ksu-part-2' => ['entries', 'titles', 'copies', 'value'],
            'ksu-register' => ['entries', 'titles', 'copies', 'value', 'conflicts'],
            'ksu-part-3' => ['arrivals_copies', 'writeoff_copies', 'net_copies', 'net_value'],
            'acquisition-act' => ['batches', 'titles', 'copies', 'value'],
            'inventory-book', 'non-inventory-book', 'new-arrivals',
            'fund-by-sigla', 'fund-by-language', 'fund-by-type', 'fund-by-udc',
            'acquisitions-by-source-value' => ['titles', 'copies', 'value'],
            'writeoffs' => ['acts', 'copies', 'value'],
            default => ['total'],
        };
    }

    public function supports(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }

    /** @return array<string, mixed> */
    public function build(string $code, ReportFilters $filters): array
    {
        return match ($code) {
            'ksu-part-1' => $this->ksuEntries($filters, 'KSU-1', self::columns($code), self::totals($code)),
            'ksu-part-2' => $this->ksuEntries($filters, 'KSU-2', self::columns($code), self::totals($code)),
            'ksu-part-3' => $this->ksuPartThree($filters),
            'ksu-register' => $this->ksuEntries($filters, null, self::columns($code), self::totals($code), true),
            'acquisition-act' => $this->acquisitionActs($filters),
            'inventory-book' => $this->inventoryBook($filters, false),
            'non-inventory-book' => $this->inventoryBook($filters, true),
            'new-arrivals' => $this->newArrivals($filters),
            'fund-by-sigla' => $this->fundFacet($filters, 'sigla'),
            'fund-by-language' => $this->fundFacet($filters, 'language'),
            'fund-by-type' => $this->fundFacet($filters, 'resource_type'),
            'fund-by-udc' => $this->fundFacet($filters, 'udc'),
            'acquisitions-by-source-value' => $this->acquisitionsBySource($filters),
            'writeoffs' => $this->writeoffs($filters),
        };
    }

    /**
     * @param  list<string>  $columnKeys
     * @param  list<string>  $metricKeys
     * @return array<string, mixed>
     */
    private function ksuEntries(
        ReportFilters $filters,
        ?string $part,
        array $columnKeys,
        array $metricKeys,
        bool $includePart = false,
    ): array {
        if (! $this->hasTable('ksu_entries') || ! $this->hasTable('ksu_books')) {
            return $this->empty($columnKeys, $metricKeys);
        }

        $query = $this->ksuQuery($filters);
        if ($part !== null) {
            $query->where('books.code', $part);
        }

        $rows = $this->rowsWithinLiveLimit($query
            ->orderBy('entries.year')
            ->orderBy('entries.number'))
            ->map(function (object $entry) use ($includePart): array {
                $part = (string) $entry->part;
                $operation = $this->ksuOperation($entry, $part);

                $row = [
                    'entry_number' => (string) $entry->entry_number,
                    'entry_date' => $this->dateValue($entry->entry_date),
                    'operation' => $this->optionLabel('analytics.operations', $operation),
                    'act_number' => $this->text($entry->act_number ?? null),
                    'reason' => $this->text($entry->operation_reason ?? null),
                    'acquisition_source' => $this->optionLabel('analytics.sources', $entry->acquisition_source ?? null),
                    'supplier' => $this->text($entry->supplier_name ?? null),
                    'branch' => $this->text($entry->branch_name ?? null),
                    'fund' => $this->text($entry->fund_name ?? null),
                    'titles' => (int) $entry->title_count,
                    'copies' => (int) $entry->copy_count,
                    'total_value' => round((float) ($entry->total_cost ?? 0), 2),
                    'status' => $this->optionLabel('analytics.statuses', $entry->status),
                ];
                if ($includePart) {
                    $row = ['part' => $part] + $row;
                }

                return $row;
            })->all();

        $metrics = [
            'entries' => count($rows),
            'titles' => (int) collect($rows)->sum('titles'),
            'copies' => (int) collect($rows)->sum('copies'),
            'value' => round((float) collect($rows)->sum('total_value'), 2),
        ];
        if ($includePart) {
            $metrics['conflicts'] = $this->openKsuConflicts();
        }

        return $this->report(
            $columnKeys,
            $rows,
            collect($metricKeys)->map(fn (string $key): array => $this->metric($key, $metrics[$key] ?? 0))->all(),
            $this->breakdown($rows, 'acquisition_source', 'copies'),
        );
    }

    /** @return array<string, mixed> */
    private function ksuPartThree(ReportFilters $filters): array
    {
        $columns = self::columns('ksu-part-3');
        $metricKeys = self::totals('ksu-part-3');
        if (! $this->hasTable('ksu_entries') || ! $this->hasTable('ksu_books')) {
            return $this->empty($columns, $metricKeys);
        }

        $operationSql = "COALESCE(NULLIF(TRIM(entries.operation_type), ''), CASE WHEN books.code = 'KSU-2' THEN 'withdrawal' ELSE 'arrival' END)";
        $query = $this->ksuQuery($filters)
            ->whereIn('books.code', ['KSU-1', 'KSU-2'])
            ->select([
                'entries.year',
                'entries.fund_id',
                $this->hasTable('funds') ? 'funds.name as fund_name' : DB::raw('NULL as fund_name'),
                DB::raw("SUM(CASE WHEN {$operationSql} = 'arrival' THEN entries.title_count ELSE 0 END) as arrivals_titles"),
                DB::raw("SUM(CASE WHEN {$operationSql} = 'arrival' THEN entries.copy_count ELSE 0 END) as arrivals_copies"),
                DB::raw("SUM(CASE WHEN {$operationSql} = 'arrival' THEN COALESCE(entries.total_cost, 0) ELSE 0 END) as arrivals_value"),
                DB::raw("SUM(CASE WHEN {$operationSql} <> 'arrival' THEN entries.title_count ELSE 0 END) as writeoff_titles"),
                DB::raw("SUM(CASE WHEN {$operationSql} <> 'arrival' THEN entries.copy_count ELSE 0 END) as writeoff_copies"),
                DB::raw("SUM(CASE WHEN {$operationSql} <> 'arrival' THEN COALESCE(entries.total_cost, 0) ELSE 0 END) as writeoff_value"),
            ])
            ->groupBy('entries.year', 'entries.fund_id')
            ->when($this->hasTable('funds'), fn (Builder $builder): Builder => $builder->groupBy('funds.name'))
            ->orderBy('entries.year')
            ->orderBy('fund_name');

        $rows = $this->rowsWithinLiveLimit($query)->map(function (object $entry): array {
            $arrivalsValue = round((float) $entry->arrivals_value, 2);
            $writeoffValue = round((float) $entry->writeoff_value, 2);
            $arrivalsCopies = (int) $entry->arrivals_copies;
            $writeoffCopies = (int) $entry->writeoff_copies;

            return [
                'year' => (int) $entry->year,
                'fund' => $this->text($entry->fund_name ?? null),
                'arrivals_titles' => (int) $entry->arrivals_titles,
                'arrivals_copies' => $arrivalsCopies,
                'arrivals_value' => $arrivalsValue,
                'writeoff_titles' => (int) $entry->writeoff_titles,
                'writeoff_copies' => $writeoffCopies,
                'writeoff_value' => $writeoffValue,
                'net_copies' => $arrivalsCopies - $writeoffCopies,
                'net_value' => round($arrivalsValue - $writeoffValue, 2),
            ];
        })->all();

        $totals = [
            'arrivals_copies' => (int) collect($rows)->sum('arrivals_copies'),
            'writeoff_copies' => (int) collect($rows)->sum('writeoff_copies'),
            'net_copies' => (int) collect($rows)->sum('net_copies'),
            'net_value' => round((float) collect($rows)->sum('net_value'), 2),
        ];

        return $this->report(
            $columns,
            $rows,
            collect($metricKeys)->map(fn (string $key): array => $this->metric($key, $totals[$key]))->all(),
            $this->breakdown($rows, 'fund', 'net_copies'),
        );
    }

    /** @return array<string, mixed> */
    private function acquisitionActs(ReportFilters $filters): array
    {
        $columns = self::columns('acquisition-act');
        $metricKeys = self::totals('acquisition-act');
        if (! $this->hasTable('acquisition_batches')) {
            return $this->empty($columns, $metricKeys);
        }

        $query = DB::table('acquisition_batches as batches');
        if ($this->hasTable('ksu_entries')) {
            $query->leftJoin('ksu_entries as entries', 'entries.id', '=', 'batches.ksu_entry_id');
        }
        if ($this->hasTable('branches')) {
            $query->leftJoin('branches', 'branches.id', '=', 'batches.branch_id');
        }
        if ($this->hasTable('funds')) {
            $query->leftJoin('funds', 'funds.id', '=', 'batches.fund_id');
        }

        $query->whereBetween('batches.received_at', $this->localDates($filters));
        $this->applyIdentifiers($query, $filters, 'batches');
        if ($filters->acquisitionSource !== null) {
            $query->where('batches.acquisition_source', $filters->acquisitionSource);
        }
        $filters->status !== null
            ? $query->where('batches.status', $filters->status)
            : $query->where('batches.status', 'confirmed');

        $select = ['batches.*'];
        $select[] = $this->hasTable('ksu_entries') ? 'entries.entry_number as ksu_entry_number' : DB::raw('NULL as ksu_entry_number');
        $select[] = $this->hasTable('branches') ? 'branches.name as branch_name' : DB::raw('NULL as branch_name');
        $select[] = $this->hasTable('funds') ? 'funds.name as fund_name' : DB::raw('NULL as fund_name');

        $rows = $this->rowsWithinLiveLimit($query->select($select)->orderBy('batches.received_at')->orderBy('batches.id'))
            ->map(fn (object $batch): array => [
                'batch_number' => (string) $batch->batch_number,
                'received_date' => $this->dateValue($batch->received_at),
                'status' => $this->optionLabel('analytics.statuses', $batch->status),
                'acquisition_source' => $this->optionLabel('analytics.sources', $batch->acquisition_source),
                'supplier' => $this->text($batch->supplier_name),
                'branch' => $this->text($batch->branch_name),
                'fund' => $this->text($batch->fund_name),
                'ksu_number' => $this->text($batch->ksu_entry_number),
                'titles' => (int) $batch->title_count,
                'copies' => (int) $batch->copy_count,
                'total_value' => round((float) $batch->total_amount, 2),
                'currency' => (string) $batch->currency,
                'confirmed_at' => $batch->confirmed_at,
            ])->all();

        $totals = [
            'batches' => count($rows),
            'titles' => (int) collect($rows)->sum('titles'),
            'copies' => (int) collect($rows)->sum('copies'),
            'value' => round((float) collect($rows)->sum('total_value'), 2),
        ];

        return $this->report(
            $columns,
            $rows,
            collect($metricKeys)->map(fn (string $key): array => $this->metric($key, $totals[$key]))->all(),
            $this->breakdown($rows, 'acquisition_source', 'copies'),
        );
    }

    /** @return array<string, mixed> */
    private function inventoryBook(ReportFilters $filters, bool $nonInventory): array
    {
        $code = $nonInventory ? 'non-inventory-book' : 'inventory-book';
        $columns = self::columns($code);
        $metricKeys = self::totals($code);
        if (! $this->hasTable('book_copies')) {
            return $this->empty($columns, $metricKeys);
        }

        $query = $this->copyQuery($filters, 'period');
        if ($this->hasColumn('book_copies', 'accounting_type')) {
            if ($nonInventory) {
                $query->where('copies.accounting_type', 'non_inventory');
            } else {
                $query->where(function (Builder $accounting): void {
                    $accounting->whereNull('copies.accounting_type')
                        ->orWhere('copies.accounting_type', '<>', 'non_inventory');
                });
            }
        } elseif ($nonInventory) {
            return $this->empty($columns, $metricKeys);
        }

        $rows = $this->rowsWithinLiveLimit($query->orderBy('copies.registration_date')->orderBy('copies.inventory_number'))
            ->map(fn (object $copy): array => $this->copyRow($copy))->all();

        return $this->report(
            $columns,
            $rows,
            [
                $this->metric('titles', $this->uniqueTitles($rows)),
                $this->metric('copies', count($rows)),
                $this->metric('value', round((float) collect($rows)->sum('price'), 2)),
            ],
            $this->breakdown($rows, 'accounting_type', 'copies', true),
        );
    }

    /** @return array<string, mixed> */
    private function newArrivals(ReportFilters $filters): array
    {
        $columns = self::columns('new-arrivals');
        $metricKeys = self::totals('new-arrivals');
        if (! $this->hasTable('book_copies')) {
            return $this->empty($columns, $metricKeys);
        }

        $query = $this->copyQuery($filters, 'period');
        $totals = $this->copyTotals($query);
        $dateSql = 'COALESCE(copies.registration_date, copies.acquisition_date)';
        $sourceSql = "COALESCE(NULLIF(TRIM(copies.acquisition_source), ''), '—')";
        $rows = $this->rowsWithinLiveLimit($query
            ->select([
                DB::raw("{$dateSql} as received_date"),
                DB::raw("{$sourceSql} as acquisition_source"),
                'copies.branch_id',
                'copies.fund_id',
                $this->hasTable('branches') ? 'branches.name as branch_name' : DB::raw('NULL as branch_name'),
                $this->hasTable('funds') ? 'funds.name as fund_name' : DB::raw('NULL as fund_name'),
                DB::raw('COUNT(DISTINCT copies.bibliographic_record_id) as titles'),
                DB::raw('COUNT(*) as copies'),
                DB::raw('COALESCE(SUM(copies.price), 0) as total_value'),
            ])
            ->groupByRaw($dateSql)
            ->groupByRaw($sourceSql)
            ->groupBy('copies.branch_id', 'copies.fund_id')
            ->when($this->hasTable('branches'), fn (Builder $builder): Builder => $builder->groupBy('branches.name'))
            ->when($this->hasTable('funds'), fn (Builder $builder): Builder => $builder->groupBy('funds.name'))
            ->orderBy('received_date')
            ->orderBy('acquisition_source'))
            ->map(fn (object $row): array => [
                'received_date' => $this->dateValue($row->received_date),
                'acquisition_source' => $this->optionLabel('analytics.sources', $row->acquisition_source),
                'branch' => $this->text($row->branch_name ?? null),
                'fund' => $this->text($row->fund_name ?? null),
                'titles' => (int) $row->titles,
                'copies' => (int) $row->copies,
                'total_value' => round((float) $row->total_value, 2),
            ])->all();

        return $this->report(
            $columns,
            $rows,
            [
                $this->metric('titles', (int) $totals->titles),
                $this->metric('copies', (int) $totals->copies),
                $this->metric('value', round((float) $totals->total_value, 2)),
            ],
            $this->breakdown($rows, 'acquisition_source', 'copies'),
        );
    }

    /** @return array<string, mixed> */
    private function fundFacet(ReportFilters $filters, string $dimension): array
    {
        $code = match ($dimension) {
            'sigla' => 'fund-by-sigla',
            'language' => 'fund-by-language',
            'resource_type' => 'fund-by-type',
            default => 'fund-by-udc',
        };
        $columns = self::columns($code);
        $metricKeys = self::totals($code);
        if (! $this->hasTable('book_copies')) {
            return $this->empty($columns, $metricKeys);
        }

        $query = $this->copyQuery($filters, 'as_of')
            ->whereNotIn('copies.status', ['written_off', 'lost']);
        $totals = $this->copyTotals($query);
        $dimensionSql = $this->facetSql($dimension);
        $rows = $this->rowsWithinLiveLimit($query
            ->select([
                DB::raw("{$dimensionSql} as dimension"),
                DB::raw('COUNT(DISTINCT copies.bibliographic_record_id) as titles'),
                DB::raw('COUNT(*) as copies'),
                DB::raw('COALESCE(SUM(copies.price), 0) as total_value'),
            ])
            ->groupByRaw($dimensionSql)
            ->orderByDesc('copies'))
            ->map(fn (object $row): array => [
                $dimension => $this->text($row->dimension),
                'titles' => (int) $row->titles,
                'copies' => (int) $row->copies,
                'total_value' => round((float) $row->total_value, 2),
            ])->all();

        return $this->report(
            $columns,
            $rows,
            [
                $this->metric('titles', (int) $totals->titles),
                $this->metric('copies', (int) $totals->copies),
                $this->metric('value', round((float) $totals->total_value, 2)),
            ],
            $this->breakdown($rows, $dimension, 'copies'),
        );
    }

    /** @return array<string, mixed> */
    private function acquisitionsBySource(ReportFilters $filters): array
    {
        $columns = self::columns('acquisitions-by-source-value');
        $metricKeys = self::totals('acquisitions-by-source-value');
        if (! $this->hasTable('book_copies')) {
            return $this->empty($columns, $metricKeys);
        }

        $query = $this->copyQuery($filters, 'period');
        $totals = $this->copyTotals($query);
        $sourceSql = "COALESCE(NULLIF(TRIM(copies.acquisition_source), ''), '—')";
        $rows = $this->rowsWithinLiveLimit($query
            ->select([
                DB::raw("{$sourceSql} as acquisition_source"),
                DB::raw('COUNT(DISTINCT copies.bibliographic_record_id) as titles'),
                DB::raw('COUNT(*) as copies'),
                DB::raw('COALESCE(SUM(copies.price), 0) as total_value'),
            ])
            ->groupByRaw($sourceSql)
            ->orderByDesc('copies'))
            ->map(fn (object $row): array => [
                'acquisition_source' => $this->optionLabel('analytics.sources', $row->acquisition_source),
                'titles' => (int) $row->titles,
                'copies' => (int) $row->copies,
                'total_value' => round((float) $row->total_value, 2),
            ])->all();

        return $this->report(
            $columns,
            $rows,
            [
                $this->metric('titles', (int) $totals->titles),
                $this->metric('copies', (int) $totals->copies),
                $this->metric('value', round((float) $totals->total_value, 2)),
            ],
            $this->breakdown($rows, 'acquisition_source', 'copies'),
        );
    }

    /** @return array<string, mixed> */
    private function writeoffs(ReportFilters $filters): array
    {
        $columns = self::columns('writeoffs');
        $metricKeys = self::totals('writeoffs');
        if (! $this->hasTable('book_copies')) {
            return $this->empty($columns, $metricKeys);
        }

        $query = $this->copyQuery($filters, 'none');
        $hasWriteoffDate = $this->hasColumn('book_copies', 'writeoff_date');
        $query->where(function (Builder $writtenOff) use ($hasWriteoffDate): void {
            $writtenOff->where('copies.status', 'written_off');
            if ($hasWriteoffDate) {
                $writtenOff->orWhereNotNull('copies.writeoff_date');
            }
        });
        [$from, $to] = $this->localDates($filters);
        if ($hasWriteoffDate) {
            $query->where(function (Builder $period) use ($from, $to): void {
                $period->whereBetween('copies.writeoff_date', [$from, $to])
                    ->orWhere(function (Builder $fallback) use ($from, $to): void {
                        $fallback->whereNull('copies.writeoff_date')
                            ->whereBetween('copies.updated_at', [$from.' 00:00:00', $to.' 23:59:59']);
                    });
            });
        } else {
            $query->whereBetween('copies.updated_at', [$from.' 00:00:00', $to.' 23:59:59']);
        }

        $rows = $this->rowsWithinLiveLimit($query->orderBy($hasWriteoffDate ? 'copies.writeoff_date' : 'copies.updated_at')->orderBy('copies.id'))
            ->map(fn (object $copy): array => [
                'writeoff_date' => $this->dateValue($copy->writeoff_date ?? $copy->updated_at),
                'act_number' => $this->text($copy->writeoff_act ?? null),
                'inventory_number' => (string) $copy->inventory_number,
                'title' => $this->text($copy->record_title ?? null),
                'reason' => $this->text($copy->writeoff_reason ?? null),
                'accounting_type' => $this->text($copy->accounting_type ?? null),
                'ksu_number' => $this->text($copy->ksu_number ?? null),
                'branch' => $this->text($copy->branch_name ?? null),
                'fund' => $this->text($copy->fund_name ?? null),
                'price' => round((float) ($copy->price ?? 0), 2),
            ])->all();

        return $this->report(
            $columns,
            $rows,
            [
                $this->metric('acts', collect($rows)->pluck('act_number')->reject(fn (string $act): bool => $act === '—')->unique()->count()),
                $this->metric('copies', count($rows)),
                $this->metric('value', round((float) collect($rows)->sum('price'), 2)),
            ],
            $this->breakdown($rows, 'reason', 'copies', true),
        );
    }

    private function ksuQuery(ReportFilters $filters): Builder
    {
        $query = DB::table('ksu_entries as entries')
            ->join('ksu_books as books', 'books.id', '=', 'entries.ksu_book_id');
        if ($this->hasTable('branches')) {
            $query->leftJoin('branches', 'branches.id', '=', 'entries.branch_id');
        }
        if ($this->hasTable('funds')) {
            $query->leftJoin('funds', 'funds.id', '=', 'entries.fund_id');
        }

        $query->whereBetween('entries.entry_date', $this->localDates($filters));
        $this->applyIdentifiers($query, $filters, 'entries');
        if ($filters->acquisitionSource !== null) {
            $query->where('entries.acquisition_source', $filters->acquisitionSource);
        }
        $filters->status !== null
            ? $query->where('entries.status', $filters->status)
            : $query->where('entries.status', '<>', 'draft');

        $select = ['entries.*', 'books.code as part'];
        $select[] = $this->hasTable('branches') ? 'branches.name as branch_name' : DB::raw('NULL as branch_name');
        $select[] = $this->hasTable('funds') ? 'funds.name as fund_name' : DB::raw('NULL as fund_name');

        return $query->select($select);
    }

    private function copyQuery(ReportFilters $filters, string $dateMode): Builder
    {
        $query = DB::table('book_copies as copies');
        $hasRecords = $this->hasTable('bibliographic_records');
        if ($hasRecords) {
            $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
        }
        if ($this->hasTable('branches')) {
            $query->leftJoin('branches', 'branches.id', '=', 'copies.branch_id');
        }
        if ($this->hasTable('funds')) {
            $query->leftJoin('funds', 'funds.id', '=', 'copies.fund_id');
        }

        $this->applyIdentifiers($query, $filters, 'copies');
        if ($filters->status !== null) {
            $query->where('copies.status', $filters->status);
        }
        if ($filters->acquisitionSource !== null) {
            $query->where('copies.acquisition_source', $filters->acquisitionSource);
        }
        if ($hasRecords) {
            if ($filters->resourceType !== null) {
                $query->where('records.resource_type', $filters->resourceType);
            }
            if ($filters->language !== null) {
                $query->where('records.language', $filters->language);
            }
            if ($filters->udc !== null) {
                $query->where('records.udc_code', 'like', $filters->udc.'%');
            }
        }

        if ($dateMode === 'period') {
            $this->applyCopyPeriod($query, $filters);
        } elseif ($dateMode === 'as_of') {
            $this->applyCopyAsOf($query, $filters);
        }

        // Keep live detail rows narrow. The recovered copy table carries large
        // provenance/note columns which are not report fields and multiplying
        // those by the live row cap creates avoidable PHP memory pressure.
        $select = [
            'copies.id',
            'copies.bibliographic_record_id',
            'copies.inventory_number',
            'copies.barcode',
            'copies.accounting_type',
            'copies.ksu_number',
            'copies.storage_sigla',
            'copies.branch_id',
            'copies.fund_id',
            'copies.price',
            'copies.acquisition_source',
            'copies.acquisition_date',
            'copies.registration_date',
            'copies.status',
            'copies.updated_at',
            $this->hasColumn('book_copies', 'sigla_code') ? 'copies.sigla_code' : DB::raw('NULL as sigla_code'),
            $this->hasColumn('book_copies', 'writeoff_date') ? 'copies.writeoff_date' : DB::raw('NULL as writeoff_date'),
            $this->hasColumn('book_copies', 'writeoff_act') ? 'copies.writeoff_act' : DB::raw('NULL as writeoff_act'),
            $this->hasColumn('book_copies', 'writeoff_reason') ? 'copies.writeoff_reason' : DB::raw('NULL as writeoff_reason'),
        ];
        if ($hasRecords) {
            $select = [...$select,
                'records.title as record_title',
                'records.primary_author as record_author',
                'records.language as record_language',
                'records.resource_type as record_resource_type',
                'records.udc_code as record_udc',
            ];
        } else {
            $select = [...$select,
                DB::raw('NULL as record_title'), DB::raw('NULL as record_author'),
                DB::raw('NULL as record_language'), DB::raw('NULL as record_resource_type'),
                DB::raw('NULL as record_udc'),
            ];
        }
        $select[] = $this->hasTable('branches') ? 'branches.name as branch_name' : DB::raw('NULL as branch_name');
        $select[] = $this->hasTable('funds') ? 'funds.name as fund_name' : DB::raw('NULL as fund_name');

        return $query->select($select);
    }

    private function applyCopyPeriod(Builder $query, ReportFilters $filters): void
    {
        [$from, $to] = $this->localDates($filters);
        $query->where(function (Builder $period) use ($from, $to): void {
            $period->whereBetween('copies.registration_date', [$from, $to])
                ->orWhere(function (Builder $fallback) use ($from, $to): void {
                    $fallback->whereNull('copies.registration_date')
                        ->whereBetween('copies.acquisition_date', [$from, $to]);
                });
        });
    }

    private function applyCopyAsOf(Builder $query, ReportFilters $filters): void
    {
        [, $to] = $this->localDates($filters);
        $query->where(function (Builder $asOf) use ($to): void {
            $asOf->where('copies.registration_date', '<=', $to)
                ->orWhere(function (Builder $fallback) use ($to): void {
                    $fallback->whereNull('copies.registration_date')
                        ->where(function (Builder $acquired) use ($to): void {
                            $acquired->whereNull('copies.acquisition_date')
                                ->orWhere('copies.acquisition_date', '<=', $to);
                        });
                });
        });
    }

    private function applyIdentifiers(Builder $query, ReportFilters $filters, string $alias): void
    {
        if ($filters->branchId !== null) {
            $query->where($alias.'.branch_id', $filters->branchId);
        }
        if ($filters->fundId !== null) {
            $query->where($alias.'.fund_id', $filters->fundId);
        }
    }

    /**
     * Execute a report-row query without ever hydrating more than the configured
     * live limit. The bounded scalar probe preserves the existing fail-loudly
     * contract instead of returning a plausible-looking truncated report.
     *
     * @return Collection<int, object>
     */
    private function rowsWithinLiveLimit(Builder $query): Collection
    {
        $maximum = max(100, (int) config('library.reports.max_live_rows', 10000));
        $probe = clone $query;
        $probe->select([DB::raw('1 as report_row')])
            ->reorder()
            ->limit($maximum + 1);

        $rowCount = (int) DB::query()->fromSub($probe, 'bounded_report_rows')->count();
        if ($rowCount > $maximum) {
            throw new ReportLimitExceeded("The report contains more than {$maximum} aggregate rows.");
        }

        return $query->limit($maximum)->get();
    }

    /** Return canonical copy totals as scalars without hydrating copy rows. */
    private function copyTotals(Builder $query): object
    {
        return (clone $query)->reorder()->select([
            DB::raw('COUNT(DISTINCT copies.bibliographic_record_id) as titles'),
            DB::raw('COUNT(*) as copies'),
            DB::raw('COALESCE(SUM(copies.price), 0) as total_value'),
        ])->first();
    }

    private function facetSql(string $dimension): string
    {
        if ($dimension === 'sigla') {
            $sigla = $this->hasColumn('book_copies', 'sigla_code')
                ? "NULLIF(TRIM(copies.sigla_code), '')"
                : 'NULL';

            return "COALESCE({$sigla}, NULLIF(TRIM(copies.storage_sigla), ''), '—')";
        }

        if (! $this->hasTable('bibliographic_records')) {
            return "'—'";
        }

        $column = match ($dimension) {
            'language' => 'language',
            'resource_type' => 'resource_type',
            default => 'udc_code',
        };

        return "COALESCE(NULLIF(TRIM(records.{$column}), ''), '—')";
    }

    /** @return array<string, int|float|string> */
    private function copyRow(object $copy): array
    {
        return [
            'copy_id' => (int) $copy->id,
            'record_id' => $copy->bibliographic_record_id === null ? null : (int) $copy->bibliographic_record_id,
            'inventory_number' => (string) $copy->inventory_number,
            'barcode' => $this->text($copy->barcode ?? null),
            'title' => $this->text($copy->record_title ?? null),
            'author' => $this->text($copy->record_author ?? null),
            'registration_date' => $this->copyDate($copy),
            'accounting_type' => $this->text($copy->accounting_type ?? null),
            'ksu_number' => $this->text($copy->ksu_number ?? null),
            'sigla' => $this->facetValue($copy, 'sigla'),
            'branch' => $this->text($copy->branch_name ?? null),
            'fund' => $this->text($copy->fund_name ?? null),
            'language' => $this->text($copy->record_language ?? null),
            'resource_type' => $this->text($copy->record_resource_type ?? null),
            'udc' => $this->text($copy->record_udc ?? null),
            'price' => round((float) ($copy->price ?? 0), 2),
            'status' => $this->optionLabel('analytics.statuses', $copy->status),
        ];
    }

    private function facetValue(object $copy, string $dimension): string
    {
        $value = match ($dimension) {
            'sigla' => ($copy->sigla_code ?? null) ?: ($copy->storage_sigla ?? null),
            'language' => $copy->record_language ?? null,
            'resource_type' => $copy->record_resource_type ?? null,
            default => $copy->record_udc ?? null,
        };

        return $this->text($value);
    }

    private function ksuOperation(object $entry, string $part): string
    {
        $operation = trim((string) ($entry->operation_type ?? ''));
        if ($operation !== '') {
            return $operation;
        }

        return $part === 'KSU-2' ? 'withdrawal' : 'arrival';
    }

    private function openKsuConflicts(): int
    {
        return $this->hasTable('ksu_conflicts')
            ? (int) DB::table('ksu_conflicts')->where('status', 'open')->count()
            : 0;
    }

    /** @param list<array<string, mixed>> $rows */
    private function uniqueTitles(array $rows): int
    {
        return collect($rows)->pluck('record_id')->filter()->unique()->count();
    }

    private function copyDate(object $copy): string
    {
        return $this->dateValue(($copy->registration_date ?? null) ?: ($copy->acquisition_date ?? null));
    }

    private function dateValue(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : substr($value, 0, 10);
    }

    private function text(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '—' : $value;
    }

    private function optionLabel(string $prefix, mixed $value): string
    {
        $value = $this->text($value);
        if ($value === '—') {
            return $value;
        }
        $value = $value === 'gift' ? 'donation' : $value;

        return $this->label($prefix.'.'.$value, Str::headline($value));
    }

    /** @return array{0:string,1:string} */
    private function localDates(ReportFilters $filters): array
    {
        $timezone = (string) config('app.library_timezone', 'Asia/Almaty');

        return [
            $filters->from->copy()->timezone($timezone)->toDateString(),
            $filters->to->copy()->timezone($timezone)->toDateString(),
        ];
    }

    /**
     * @param  list<string>  $columnKeys
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{key:string,label:string,value:int|float}>  $metrics
     * @param  list<array<string, mixed>>  $breakdowns
     * @return array<string, mixed>
     */
    private function report(array $columnKeys, array $rows, array $metrics, array $breakdowns = []): array
    {
        return [
            'metrics' => $metrics,
            'columns' => collect($columnKeys)->map(fn (string $key): array => $this->column($key))->all(),
            'rows' => $rows,
            'breakdowns' => $breakdowns,
        ];
    }

    /** @param list<string> $columnKeys @param list<string> $metricKeys @return array<string, mixed> */
    private function empty(array $columnKeys, array $metricKeys): array
    {
        return $this->report(
            $columnKeys,
            [],
            collect($metricKeys)->map(fn (string $key): array => $this->metric($key, 0))->all(),
        );
    }

    /** @return array{key:string,label:string} */
    private function column(string $key): array
    {
        return ['key' => $key, 'label' => $this->label('analytics.columns.'.$key, Str::headline($key))];
    }

    /** @return array{key:string,label:string,value:int|float} */
    private function metric(string $key, int|float $value): array
    {
        return ['key' => $key, 'label' => $this->label('analytics.metrics.'.$key, Str::headline($key)), 'value' => $value];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function breakdown(array $rows, string $labelKey, string $valueKey, bool $countRows = false): array
    {
        if ($rows === []) {
            return [];
        }

        $items = collect($rows)->groupBy(fn (array $row): string => $this->text($row[$labelKey] ?? null))
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'value' => $countRows ? $group->count() : (float) $group->sum($valueKey),
            ])->sortByDesc('value')->values()->all();

        return [[
            'key' => $labelKey,
            'label' => $this->label('analytics.columns.'.$labelKey, Str::headline($labelKey)),
            'items' => $items,
        ]];
    }

    private function label(string $key, string $fallback): string
    {
        return trans()->has($key) ? (string) __($key) : $fallback;
    }

    private function hasTable(string $table): bool
    {
        return DatabaseSchema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }
}
