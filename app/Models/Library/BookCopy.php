<?php

namespace App\Models\Library;

class BookCopy extends ReadOnlyPgsqlModel
{
    protected $table = 'app.book_copies';

    protected $casts = [
        'state_code' => 'integer',
        'needs_review' => 'boolean',
        'review_reason_codes' => 'array',
        'location_mapping_confidence' => 'float',
        'retired_at' => 'datetime',
    ];
}
