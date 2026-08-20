<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConflict extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['local_value_safe' => 'encrypted:array', 'external_value_safe' => 'encrypted:array', 'resolved_at' => 'immutable_datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
