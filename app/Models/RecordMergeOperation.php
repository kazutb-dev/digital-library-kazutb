<?php

namespace App\Models;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordMergeOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'field_selection' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'approved_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
            'rolled_back_at' => 'immutable_datetime',
        ];
    }

    public function targetRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'target_record_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'source_record_id');
    }

    public function duplicateGroup(): BelongsTo
    {
        return $this->belongsTo(DuplicateGroup::class);
    }
}
