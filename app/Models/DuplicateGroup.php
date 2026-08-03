<?php

namespace App\Models;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DuplicateGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'decimal:2', 'reviewed_at' => 'immutable_datetime'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(DuplicateGroupMember::class);
    }

    public function canonicalRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'canonical_record_id');
    }
}
