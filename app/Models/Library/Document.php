<?php

namespace App\Models\Library;

class Document extends ReadOnlyPgsqlModel
{
    protected $table = 'app.documents';

    protected $casts = [
        'publication_year' => 'integer',
        'isbn_is_valid' => 'boolean',
        'needs_review' => 'boolean',
        'review_reason_codes' => 'array',
    ];
}
