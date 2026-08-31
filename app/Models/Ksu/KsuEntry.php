<?php

namespace App\Models\Ksu;

use App\Models\Branch;
use App\Models\Fund;
use App\Models\Operations\AcquisitionBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KsuEntry extends Model
{
    /**
     * Parse the raw legacy presentation only when it is already a canonical
     * positive numeric tuple. Recovery review must never repair punctuation,
     * padding, whitespace, or a malformed year on the operator's behalf.
     *
     * @return array{number:int, year:int}|null
     */
    public static function parseStrictLegacyNumber(?string $raw): ?array
    {
        if ($raw === null || preg_match('/^([1-9][0-9]*)\/([0-9]{4})$/D', $raw, $matches) !== 1) {
            return null;
        }

        $numberText = $matches[1];
        if (strlen($numberText) > 10
            || (strlen($numberText) === 10 && strcmp($numberText, '4294967295') > 0)) {
            return null;
        }

        $number = (int) $numberText;
        $year = (int) $matches[2];
        if ($number < 1 || $year < 1900 || $year > 9999) {
            return null;
        }

        return ['number' => $number, 'year' => $year];
    }

    protected $fillable = [
        'ksu_book_id',
        'entry_number',
        'number',
        'year',
        'entry_date',
        'operation_type',
        'act_number',
        'operation_reason',
        'acquisition_source',
        'supplier_name',
        'title_count',
        'copy_count',
        'total_cost',
        'total_cost_raw',
        'fund_id',
        'branch_id',
        'status',
        'legacy_ksu_id',
        'legacy_source_table',
        'legacy_breakdown',
        'source_row_hash',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'year' => 'integer',
            'entry_date' => 'immutable_date',
            'title_count' => 'integer',
            'copy_count' => 'integer',
            'total_cost' => 'decimal:2',
            'legacy_breakdown' => 'array',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(KsuBook::class, 'ksu_book_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KsuEntryItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acquisitionBatch(): HasOne
    {
        return $this->hasOne(AcquisitionBatch::class);
    }
}
