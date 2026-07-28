<?php

namespace App\Models\Library;

class Reader extends ReadOnlyPgsqlModel
{
    protected $table = 'app.readers';

    protected $casts = [
        'birthday' => 'date',
        'needs_review' => 'boolean',
        'review_reason_codes' => 'array',
    ];
}
