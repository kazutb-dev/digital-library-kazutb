<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'event',
        'announcement',
        'update',
        'schedule',
    ];

    public const STATUSES = [
        'draft',
        'scheduled',
        'published',
        'archived',
    ];

    public const LANGUAGES = ['ru', 'kk', 'en'];

    protected $table = 'news';

    protected $fillable = [
        'slug',
        'title',
        'category',
        'body',
        'excerpt',
        'cover_image',
        'status',
        'publish_at',
        'show_on_homepage',
        'language',
        'created_by',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'immutable_datetime',
            'show_on_homepage' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
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

    public function scopeLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now('UTC'));
            });
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query
            ->where('status', 'scheduled')
            ->whereNotNull('publish_at');
    }

    public function scopeDueForPublication(Builder $query): Builder
    {
        return $query
            ->scheduled()
            ->where('publish_at', '<=', now('UTC'));
    }

    public function scopeHomepage(Builder $query): Builder
    {
        return $query->where('show_on_homepage', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(body) LIKE ?', [$needle]);
        });
    }
}
