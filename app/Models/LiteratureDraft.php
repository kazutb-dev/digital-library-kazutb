<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiteratureDraft extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'notes',
    ];

    /**
     * @return HasMany<LiteratureDraftItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LiteratureDraftItem::class, 'draft_id');
    }
}
