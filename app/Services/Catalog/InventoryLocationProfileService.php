<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BookCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryLocationProfileService
{
    /** Read-only, copy-based location profile. Missing values are never inferred. */
    public function summary(): array
    {
        $copies = BookCopy::query();
        $missing = static fn ($query, string $column) => $query
            ->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''))
            ->count();

        return [
            'copies' => (clone $copies)->count(),
            'without_point' => (clone $copies)->whereNull('branch_id')->count(),
            'without_fund' => (clone $copies)->whereNull('fund_id')->count(),
            'without_room' => Schema::hasColumn('book_copies', 'room') ? $missing(clone $copies, 'room') : (clone $copies)->count(),
            'without_section' => Schema::hasColumn('book_copies', 'section') ? $missing(clone $copies, 'section') : (clone $copies)->count(),
            'without_shelf' => $missing(clone $copies, 'shelf_location'),
            'without_storage_code' => $missing(clone $copies, 'storage_sigla'),
            'point_fund_conflicts' => DB::table('book_copies as copies')
                ->join('funds', 'funds.id', '=', 'copies.fund_id')
                ->whereNotNull('copies.branch_id')
                ->whereColumn('copies.branch_id', '!=', 'funds.branch_id')
                ->count(),
            'pilot_ready' => (clone $copies)
                ->whereNotNull('branch_id')->whereNotNull('fund_id')
                ->whereNotNull('shelf_location')->where('shelf_location', '!=', '')
                ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))
                ->count(),
        ];
    }

    public function zones(int $limit = 50)
    {
        $hasRoom = Schema::hasColumn('book_copies', 'room');
        $hasSection = Schema::hasColumn('book_copies', 'section');

        return DB::table('book_copies as copies')
            ->leftJoin('branches', 'branches.id', '=', 'copies.branch_id')
            ->leftJoin('funds', 'funds.id', '=', 'copies.fund_id')
            ->selectRaw('branches.name as point, funds.name as fund')
            ->selectRaw($hasRoom ? 'copies.room as room' : 'NULL as room')
            ->selectRaw($hasSection ? 'copies.section as section' : 'NULL as section')
            ->addSelect(['copies.shelf_location as shelf', 'copies.storage_sigla as storage_code'])
            ->selectRaw('COUNT(*) as copies')
            ->selectRaw("SUM(CASE WHEN copies.branch_id IS NOT NULL AND copies.fund_id IS NOT NULL AND NULLIF(copies.shelf_location, '') IS NOT NULL THEN 1 ELSE 0 END) as location_complete")
            ->groupBy('branches.name', 'funds.name', 'copies.shelf_location', 'copies.storage_sigla')
            ->when($hasRoom, fn ($q) => $q->groupBy('copies.room'))
            ->when($hasSection, fn ($q) => $q->groupBy('copies.section'))
            ->orderByDesc('copies')
            ->limit($limit)
            ->get();
    }
}
