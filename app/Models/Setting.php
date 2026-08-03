<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'entity');
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeNamed(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable((new static)->getTable())) {
                return $default;
            }

            return static::query()->where('key', $key)->first()?->value ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function resultsPerPage(int $default = 20): int
    {
        return min(100, max(10, (int) static::valueFor('results_per_page', $default)));
    }

    /**
     * Cards per page in the public catalogue. Kept separate from
     * results_per_page: that one sizes dense administrative tables, while the
     * catalogue is a card grid whose page size is a layout decision.
     */
    public static function catalogPageSize(int $default = 12): int
    {
        return min(60, max(6, (int) static::valueFor('catalog_page_size', $default)));
    }
}
