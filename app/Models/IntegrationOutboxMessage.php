<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationOutboxMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload_safe' => 'encrypted:array', 'next_attempt_at' => 'immutable_datetime', 'locked_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];
    }
}
