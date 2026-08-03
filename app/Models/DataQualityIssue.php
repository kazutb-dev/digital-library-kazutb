<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataQualityIssue extends Model
{
    public const STATUSES = ['open', 'assigned', 'in_review', 'waiting_for_source', 'resolved', 'ignored', 'false_positive', 'reopened'];

    public const SEVERITIES = ['info', 'low', 'medium', 'high', 'critical'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'first_detected_at' => 'immutable_datetime',
            'last_detected_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'ignored_until' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
        ];
    }

    public function scanRun(): BelongsTo
    {
        return $this->belongsTo(DataQualityScanRun::class, 'scan_run_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DataQualityIssueComment::class, 'issue_id');
    }

    public function scopeActionable(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'assigned', 'in_review', 'waiting_for_source', 'reopened']);
    }
}
