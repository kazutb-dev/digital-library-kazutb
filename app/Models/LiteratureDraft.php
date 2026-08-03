<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiteratureDraft extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'notes',
        'slug', 'description', 'cover_path', 'collection_type', 'visibility', 'status',
        'owner_type', 'target_audience', 'language', 'subject', 'udc', 'created_by', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /**
     * @return HasMany<LiteratureDraftItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LiteratureDraftItem::class, 'draft_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'literature_collection_follows', 'collection_id')->withTimestamps();
    }
}
