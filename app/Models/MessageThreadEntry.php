<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThreadEntry extends Model
{
    public const TYPES = ['user_message', 'staff_reply', 'internal_note', 'status_change', 'assignment', 'system_event', 'clarification_request', 'official_resolution'];

    public const VISIBILITIES = ['public', 'internal', 'director_only', 'system'];

    protected $fillable = ['contact_message_id', 'author_id', 'author_type', 'entry_type', 'body', 'visibility', 'is_official_response', 'version', 'supersedes_id', 'edited_at', 'edit_reason', 'metadata'];

    protected function casts(): array
    {
        return ['is_official_response' => 'boolean', 'version' => 'integer', 'edited_at' => 'immutable_datetime', 'metadata' => 'array'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'thread_entry_id');
    }

    public function scopeVisibleToReader(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }
}
