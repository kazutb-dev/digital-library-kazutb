<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'library',
        'circulation_desk',
        'reading_room',
        'service_point',
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'address',
        'phone',
        'email',
        'opening_hours',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
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
        return $query->where('type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
