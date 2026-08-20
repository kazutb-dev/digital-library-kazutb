<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
