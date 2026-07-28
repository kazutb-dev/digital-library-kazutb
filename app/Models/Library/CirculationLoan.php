<?php

namespace App\Models\Library;

use Illuminate\Database\Eloquent\Model;

class CirculationLoan extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'app.circulation_loans';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'renew_count' => 'integer',
    ];
}
