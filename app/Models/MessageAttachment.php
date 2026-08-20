<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = ['contact_message_id', 'thread_entry_id', 'uploaded_by', 'public_id', 'disk', 'path', 'original_name', 'extension', 'mime', 'size', 'sha256', 'visibility', 'scan_status', 'reviewed_at', 'reviewed_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'reviewed_at' => 'immutable_datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }

    public function threadEntry(): BelongsTo
    {
        return $this->belongsTo(MessageThreadEntry::class, 'thread_entry_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
