<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'encoding_confidence' => 'decimal:2',
            'reconciliation' => 'array',
            'approved_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DataImportStagingRow::class, 'batch_id');
    }
}
