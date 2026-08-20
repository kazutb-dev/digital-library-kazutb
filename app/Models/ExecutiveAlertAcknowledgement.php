<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveAlertAcknowledgement extends Model
{
    protected $fillable = ['alert_key', 'scope_hash', 'acknowledged_by', 'acknowledged_at', 'comment'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'immutable_datetime'];
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
