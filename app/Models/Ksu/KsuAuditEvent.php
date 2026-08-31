<?php

namespace App\Models\Ksu;

use App\Models\Catalog\BookCopy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KsuAuditEvent extends Model
{
    protected $fillable = [
        'event_type',
        'ksu_book_id',
        'ksu_entry_id',
        'book_copy_id',
        'actor_id',
        'actor_name',
        'old_values',
        'new_values',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(KsuBook::class, 'ksu_book_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KsuEntry::class, 'ksu_entry_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
