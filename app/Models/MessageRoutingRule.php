<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageRoutingRule extends Model
{
    protected $fillable = ['name', 'message_type', 'category_id', 'branch_id', 'priority', 'target_role', 'director_visibility', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['director_visibility' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MessageCategory::class, 'category_id');
    }
}
