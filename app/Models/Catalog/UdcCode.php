<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UdcCode extends Model
{
    protected $fillable = [
        'code', 'description', 'description_kk', 'description_en', 'parent_id',
        'is_verified', 'department',
    ];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(code) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(description) LIKE ?', [$needle]);
        });
    }

    public function localizedDescription(): string
    {
        return match (app()->getLocale()) {
            'kk' => $this->description_kk ?: $this->description,
            'en' => $this->description_en ?: $this->description,
            default => $this->description,
        };
    }
}
