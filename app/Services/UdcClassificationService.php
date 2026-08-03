<?php

namespace App\Services;

use App\Models\Catalog\UdcCode;
use App\Support\DatabaseSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UdcClassificationService
{
    /**
     * @return Collection<int, UdcCode>
     */
    public function tree(): Collection
    {
        if (! DatabaseSchema::hasTable('udc_codes') || ! DatabaseSchema::hasTable('bibliographic_records')) {
            return collect();
        }

        $counts = $this->countsByExactCode();
        $codes = UdcCode::query()
            ->orderByRaw('LENGTH(code), code')
            ->get();

        foreach ($codes as $code) {
            $code->setAttribute('records_count', $this->prefixTotal($counts['records'], $code->code));
            $code->setAttribute('copies_count', $this->prefixTotal($counts['copies'], $code->code));
        }

        foreach ($codes as $code) {
            $code->setRelation(
                'children',
                $codes->where('parent_id', $code->getKey())->sortBy('code')->values(),
            );
        }

        return $codes
            ->whereNull('parent_id')
            ->sortBy('code')
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public function reportRows(): Collection
    {
        return $this->tree()
            ->filter(fn (UdcCode $code): bool => preg_match('/^[0-9]$/', $code->code) === 1)
            ->map(fn (UdcCode $code): object => (object) [
                'code' => $code->code,
                'description' => $code->localizedDescription(),
                'department' => $code->department,
                'records' => (int) $code->getAttribute('records_count'),
                'copies' => (int) $code->getAttribute('copies_count'),
            ])
            ->values();
    }

    public function descriptionFor(?string $rawCode): string
    {
        $code = trim((string) $rawCode);
        if ($code === '' || ! DatabaseSchema::hasTable('udc_codes')) {
            return '';
        }

        $match = UdcCode::query()
            ->whereRaw('? LIKE code || ?', [$code, '%'])
            ->orderByRaw('LENGTH(code) DESC')
            ->first();

        return $match?->localizedDescription() ?? '';
    }

    /**
     * @return array{records: array<string, int>, copies: array<string, int>}
     */
    private function countsByExactCode(): array
    {
        $records = DB::table('bibliographic_records')
            ->whereNull('deleted_at')
            ->whereNotNull('udc_code')
            ->whereRaw("TRIM(udc_code) <> ''")
            ->selectRaw('TRIM(udc_code) AS code, COUNT(*) AS aggregate')
            ->groupByRaw('TRIM(udc_code)')
            ->pluck('aggregate', 'code')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $copies = DB::table('bibliographic_records')
            ->join('book_copies', 'book_copies.bibliographic_record_id', '=', 'bibliographic_records.id')
            ->whereNull('bibliographic_records.deleted_at')
            ->whereNotNull('bibliographic_records.udc_code')
            ->whereRaw("TRIM(bibliographic_records.udc_code) <> ''")
            ->whereNotIn('book_copies.status', ['written_off', 'lost'])
            ->selectRaw('TRIM(bibliographic_records.udc_code) AS code, COUNT(book_copies.id) AS aggregate')
            ->groupByRaw('TRIM(bibliographic_records.udc_code)')
            ->pluck('aggregate', 'code')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return compact('records', 'copies');
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function prefixTotal(array $counts, string $prefix): int
    {
        return collect($counts)
            ->filter(fn (int $count, string $code): bool => str_starts_with($code, $prefix))
            ->sum();
    }
}
