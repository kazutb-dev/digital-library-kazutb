<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalResource extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'licensed',
        'open',
        'partner',
        'internal',
    ];

    protected $fillable = [
        'slug',
        'title',
        'resource_type',
        'description',
        'logo_path',
        'available_roles',
        'license_expires_at',
        'is_active',
        'access_instructions',
        'url',
        'provider',
        'access_type',
        'category',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'available_roles' => 'array',
            'license_expires_at' => 'immutable_date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('resource_type', $type);
    }

    public function scopeAvailableToRole(
        Builder $query,
        string $role
    ): Builder {
        return $query->whereJsonContains('available_roles', $role);
    }

    public function scopeExpiringSoon(
        Builder $query,
        int $withinDays = 30
    ): Builder {
        return $query
            ->where('is_active', true)
            ->whereNotNull('license_expires_at')
            ->whereBetween('license_expires_at', [
                today('UTC')->toDateString(),
                today('UTC')->addDays($withinDays)->toDateString(),
            ]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function expiresSoon(int $withinDays = 30): bool
    {
        if (! $this->is_active || $this->license_expires_at === null) {
            return false;
        }

        return $this->license_expires_at->betweenIncluded(
            today('UTC'),
            today('UTC')->addDays($withinDays)
        );
    }
}
