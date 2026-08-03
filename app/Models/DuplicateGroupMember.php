<?php

namespace App\Models;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateGroupMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['match_details' => 'array', 'is_recommended_canonical' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DuplicateGroup::class, 'duplicate_group_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'bibliographic_record_id');
    }
}
