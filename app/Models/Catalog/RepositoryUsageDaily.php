<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Daily non-identifying repository usage counters used by official reports. */
class RepositoryUsageDaily extends Model
{
    public $timestamps = false;

    protected $table = 'repository_usage_daily';

    protected $fillable = [
        'repository_item_id', 'occurred_on', 'event_type', 'role_name',
        'locale', 'event_count',
    ];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'event_count' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RepositoryItem::class, 'repository_item_id');
    }
}
