<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aggregate-friendly repository usage, without IP, user-agent or query data. */
class RepositoryAccessEvent extends Model
{
    public const TYPES = ['metadata_view', 'pdf_view', 'download'];

    protected $fillable = [
        'repository_item_id', 'event_type', 'user_id', 'role_name', 'locale', 'occurred_on',
    ];

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RepositoryItem::class, 'repository_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
