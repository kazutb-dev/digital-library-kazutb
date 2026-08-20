<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDeliveryRequest extends Model
{
    protected $fillable = ['request_number', 'user_id', 'requested_document', 'source', 'status', 'responsible_id', 'due_at', 'result', 'rights_restrictions'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
}
