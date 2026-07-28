<?php

namespace App\Models\Library;

class QualityIssue extends ReadOnlyPgsqlModel
{
    protected $table = 'review.quality_issues';

    protected $casts = [
        'details' => 'array',
    ];
}
