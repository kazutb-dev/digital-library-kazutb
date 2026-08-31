<?php

namespace App\Models\Ksu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KsuSequence extends Model
{
    protected $fillable = [
        'ksu_book_id',
        'year',
        'last_number',
        'min_observed',
        'max_observed',
        'missing_numbers',
        'duplicate_numbers',
        'allocation_enabled',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
            'min_observed' => 'integer',
            'max_observed' => 'integer',
            'missing_numbers' => 'array',
            'duplicate_numbers' => 'array',
            'allocation_enabled' => 'boolean',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(KsuBook::class, 'ksu_book_id');
    }
}
