<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

class CirculationAuditEvent extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'app.circulation_audit_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'event_at' => 'datetime',
        'previous_state' => 'array',
        'new_state' => 'array',
        'metadata' => 'array',
    ];
}
