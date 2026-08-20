<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsSlugRedirect extends Model
{
    protected $fillable = ['news_id', 'locale', 'old_slug'];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
