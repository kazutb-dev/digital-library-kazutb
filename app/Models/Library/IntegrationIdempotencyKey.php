<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

class IntegrationIdempotencyKey extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'app.integration_idempotency_keys';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'response_body' => 'array',
    ];
}
