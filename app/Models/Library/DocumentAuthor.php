<?php

namespace App\Models\Library;

class DocumentAuthor extends ReadOnlyPgsqlModel
{
    protected $table = 'app.document_authors';

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];
}
