<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCorrectionBatchItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before_snapshot' => 'array', 'after_snapshot' => 'array'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DataCorrectionBatch::class, 'batch_id');
    }
}
