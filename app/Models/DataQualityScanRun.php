<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataQualityScanRun extends Model
{
    public const STATUSES = ['queued', 'running', 'completed', 'completed_with_errors', 'failed', 'cancelled'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(DataQualityIssue::class, 'scan_run_id');
    }
}
