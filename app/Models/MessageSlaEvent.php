<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageSlaEvent extends Model
{
    protected $fillable = ['contact_message_id', 'event_type', 'threshold_key', 'triggered_at', 'metadata'];

    protected function casts(): array
    {
        return ['triggered_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }
}
