<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'occurred_at',
        'action_type',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'reason',
        'scope',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ActivityLog $log): void {
            $log->occurred_at ??= now('UTC');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForActor(Builder $query, int $actorId): Builder
    {
        return $query->where('actor_id', $actorId);
    }

    public function scopeAction(Builder $query, string $actionType): Builder
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeEntityType(Builder $query, string $entityType): Builder
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeForEntity(
        Builder $query,
        string $entityType,
        int|string|null $entityId = null
    ): Builder {
        return $query
            ->where('entity_type', $entityType)
            ->when(
                $entityId !== null,
                fn (Builder $builder): Builder => $builder->where(
                    'entity_id',
                    (string) $entityId
                )
            );
    }

    public function scopeOccurredBetween(
        Builder $query,
        DateTimeInterface|string|null $from,
        DateTimeInterface|string|null $until
    ): Builder {
        return $query
            ->when(
                $from !== null,
                fn (Builder $builder): Builder => $builder->where(
                    'occurred_at',
                    '>=',
                    $from
                )
            )
            ->when(
                $until !== null,
                fn (Builder $builder): Builder => $builder->where(
                    'occurred_at',
                    '<=',
                    $until
                )
            );
    }

    public function scopeWithinScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * Admins see every entry. Librarians see their own actions plus the
     * shared operational stream; other authenticated users see only theirs.
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->hasRole('admin')) {
            return $query;
        }

        if ($viewer->hasRole('librarian')) {
            return $query->where(function (Builder $builder) use ($viewer): void {
                $builder
                    ->where('actor_id', $viewer->getKey())
                    ->orWhere('scope', 'operational');
            });
        }

        return $query->where('actor_id', $viewer->getKey());
    }
}
