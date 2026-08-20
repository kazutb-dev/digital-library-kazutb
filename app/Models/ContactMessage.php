<?php

namespace App\Models;

use App\Models\Catalog\ReaderProfile;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ContactMessage extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'request',
        'complaint',
        'suggestion',
        'question',
    ];

    public const TYPES = self::CATEGORIES;

    public const STATUSES = [
        'open',
        'in_review',
        'waiting_for_user',
        'response_prepared',
        'resolved',
        'rejected',
        'closed',
        'reopened',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
        'critical',
    ];

    protected $fillable = [
        'public_id',
        'ticket_number',
        'user_id',
        'reader_profile_id',
        'type',
        'category',
        'category_id',
        'subject',
        'body',
        'source',
        'preferred_locale',
        'preferred_contact_channel',
        'sender_id',
        'sender_email',
        'sender_name_snapshot',
        'sender_email_snapshot',
        'sender_phone_snapshot',
        'reader_ticket_snapshot',
        'branch_id',
        'related_entity_type',
        'related_entity_id',
        'complaint_against_user_id',
        'status',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'reviewed_by',
        'reviewed_at',
        'priority',
        'resolution_comment',
        'attachments',
        'resolved_at',
        'resolved_by',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'closed_at',
        'due_at',
        'first_response_due_at',
        'first_response_at',
        'last_response_at',
        'last_user_message_at',
        'last_staff_message_at',
        'sla_paused_at',
        'sla_paused_minutes',
        'requires_director_review',
        'sensitive',
        'satisfaction_score',
        'satisfaction_comment',
        'idempotency_key',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'resolved_at' => 'immutable_datetime',
            'assigned_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'first_response_due_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'last_response_at' => 'immutable_datetime',
            'last_user_message_at' => 'immutable_datetime',
            'last_staff_message_at' => 'immutable_datetime',
            'sla_paused_at' => 'immutable_datetime',
            'requires_director_review' => 'boolean',
            'sensitive' => 'boolean',
            'sla_paused_minutes' => 'integer',
            'satisfaction_score' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (Schema::hasColumn($message->getTable(), 'public_id')) {
                $message->public_id ??= (string) Str::uuid();
            }
            if (Schema::hasColumn($message->getTable(), 'user_id')) {
                $message->user_id ??= $message->sender_id;
            }
            if (Schema::hasColumn($message->getTable(), 'type')) {
                $message->type ??= in_array($message->category, self::TYPES, true) ? $message->category : 'request';
            }
        });

        static::created(function (self $message): void {
            if (Schema::hasColumn($message->getTable(), 'ticket_number') && blank($message->ticket_number)) {
                $message->forceFill(['ticket_number' => 'KUTB-'.now('UTC')->format('Ymd').'-'.str_pad((string) $message->getKey(), 6, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readerProfile(): BelongsTo
    {
        return $this->belongsTo(ReaderProfile::class);
    }

    public function messageCategory(): BelongsTo
    {
        return $this->belongsTo(MessageCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function complaintAgainst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'complaint_against_user_id');
    }

    public function threadEntries(): HasMany
    {
        return $this->hasMany(MessageThreadEntry::class)->orderBy('created_at');
    }

    public function publicThreadEntries(): HasMany
    {
        return $this->threadEntries()->where('visibility', 'public');
    }

    public function messageAttachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_watchers')->withPivot(['added_by', 'reason'])->withTimestamps();
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->whereHas('messageCategory', fn (Builder $builder) => $builder->where('slug', $category));
    }

    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeReceivedBetween(
        Builder $query,
        DateTimeInterface|string|null $from,
        DateTimeInterface|string|null $until
    ): Builder {
        return $query
            ->when(
                $from !== null,
                fn (Builder $builder): Builder => $builder->where(
                    'created_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $until !== null,
                fn (Builder $builder): Builder => $builder->where(
                    'created_at',
                    '<=',
                    $until
                )
            );
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['resolved', 'rejected', 'closed']);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(subject) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(body) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(sender_email) LIKE ?', [$needle]);
            if (Schema::hasColumn('contact_messages', 'ticket_number')) {
                $builder->orWhereRaw('LOWER(COALESCE(ticket_number, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(sender_name_snapshot, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(reader_ticket_snapshot, \'\')) LIKE ?', [$needle]);
            }
        });
    }

    public function isOverdue(): bool
    {
        return $this->due_at?->isPast() === true && ! in_array($this->status, ['resolved', 'rejected', 'closed'], true);
    }

    public function getRouteKeyName(): string
    {
        return Schema::hasColumn($this->getTable(), 'public_id') ? 'public_id' : $this->getKeyName();
    }
}
