<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataImportStagingRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'mapping_result' => 'array',
            'validation_errors' => 'array',
            'duplicate_candidates' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataImportBatch::class, 'batch_id');
    }
}
