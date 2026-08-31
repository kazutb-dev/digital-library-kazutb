<?php

namespace App\Models\Ksu;

use App\Models\Catalog\BookCopy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KsuConflict extends Model
{
    protected $fillable = [
        'ksu_book_id',
        'kind',
        'ksu_number_raw',
        'source_inv_id',
        'source_doc_id',
        'book_copy_id',
        'reason',
        'payload',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(KsuBook::class, 'ksu_book_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    /** Source-backed copy candidate used when the imported queue had no FK. */
    public function sourceCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'source_inv_id', 'legacy_inv_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeForRawNumber(Builder $query, ?string $rawNumber): Builder
    {
        return $rawNumber === null
            ? $query->whereNull($this->qualifyColumn('ksu_number_raw'))
            : $query->where($this->qualifyColumn('ksu_number_raw'), $rawNumber);
    }
}
