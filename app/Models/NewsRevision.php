<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsRevision extends Model
{
    protected $fillable = ['news_id', 'created_by', 'version', 'snapshot', 'reason'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
