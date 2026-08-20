<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsReview extends Model
{
    protected $fillable = ['news_id', 'actor_id', 'action', 'comment', 'issues'];

    protected function casts(): array
    {
        return ['issues' => 'array'];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
