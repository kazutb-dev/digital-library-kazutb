<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodicalIssue extends Model
{
    protected $fillable = ['periodical_subscription_id', 'issue_number', 'expected_at', 'received_at', 'status', 'notes'];

    protected function casts(): array
    {
        return ['expected_at' => 'date', 'received_at' => 'date'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PeriodicalSubscription::class);
    }
}
