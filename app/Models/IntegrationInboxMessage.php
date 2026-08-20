<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationInboxMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload_safe' => 'encrypted:array', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
