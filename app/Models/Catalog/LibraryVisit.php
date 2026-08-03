<?php

namespace App\Models\Catalog;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded entry to the library (ДИР §9.4). Independent of loans: a reader
 * who comes in to study and borrows nothing still counts as attendance.
 */
class LibraryVisit extends Model
{
    /** Where the record came from. `turnstile` is reserved for future hardware. */
    public const SOURCES = ['desk', 'kiosk', 'turnstile'];

    protected $fillable = [
        'user_id', 'branch_id', 'scanned_at', 'scanned_by', 'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('scanned_at', [$from, $to]);
    }
}
