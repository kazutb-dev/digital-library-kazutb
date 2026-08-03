<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityIssueComment extends Model
{
    protected $fillable = ['issue_id', 'author_id', 'body'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(DataQualityIssue::class, 'issue_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
