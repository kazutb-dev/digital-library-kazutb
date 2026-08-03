<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataCorrectionBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'selection_filter' => 'array',
            'operation_config' => 'array',
            'dry_run' => 'boolean',
            'high_risk' => 'boolean',
            'approved_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
            'rolled_back_at' => 'immutable_datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DataCorrectionBatchItem::class, 'batch_id');
    }
}
