<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fund extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'main',
        'educational',
        'research',
        'periodicals',
        'electronic',
    ];

    public const INSTITUTIONAL_SCOPES = [
        'general',
        'college',
        'university_economic',
        'university_technology',
    ];

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'fund_type',
        'institutional_scope',
        'academic_direction',
        'description',
        'location',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
        return $query->where('fund_type', $type);
    }

    public function scopeInstitutionalScope(
        Builder $query,
        string $scope
    ): Builder {
        return $query->where('institutional_scope', $scope);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
