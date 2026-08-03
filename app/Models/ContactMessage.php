<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactMessage extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'request',
        'complaint',
        'suggestion',
        'question',
        'other',
    ];

    public const STATUSES = [
        'open',
        'in_review',
        'resolved',
        'archived',
    ];

    public const PRIORITIES = [
        'low',
        'normal',
        'high',
        'urgent',
    ];

    protected $fillable = [
        'category',
        'subject',
        'body',
        'sender_id',
        'sender_email',
        'status',
        'assigned_to',
        'priority',
        'resolution_comment',
        'attachments',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
        return $query->where('category', $category);
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
        return $query->whereIn('status', ['open', 'in_review']);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(subject) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(body) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(sender_email) LIKE ?', [$needle]);
        });
    }
}
