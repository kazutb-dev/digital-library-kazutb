<?php

namespace App\Models;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\RepositoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class News extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'event',
        'announcement',
        'update',
        'schedule',
    ];

    public const CATEGORIES = self::TYPES;

    public const STATUSES = ['draft', 'pending_review', 'changes_requested', 'approved', 'scheduled', 'published', 'cancelled', 'archived'];

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
        'public_id', 'type', 'category_id', 'editor_id', 'reviewer_id', 'approved_by',
        'approved_at', 'published_at', 'scheduled_publish_at', 'archived_at',
        'cancelled_at', 'cancellation_reason', 'homepage_priority', 'is_featured',
        'is_pinned', 'visibility', 'branch_id', 'audience', 'starts_at', 'ends_at',
        'timezone', 'venue', 'online_url', 'registration_url', 'registration_required',
        'capacity', 'contact_name', 'contact_email', 'contact_phone', 'cover_image_alt',
        'gallery_enabled', 'annual_plan_item_id', 'homepage_until', 'expires_at',
        'repository_item_id',
        'importance', 'source', 'organizer', 'title_kk', 'title_ru', 'title_en',
        'excerpt_kk', 'excerpt_ru', 'excerpt_en', 'content_kk', 'content_ru',
        'content_en', 'slug_kk', 'slug_ru', 'slug_en', 'image_alt_kk', 'image_alt_ru',
        'image_alt_en', 'venue_kk', 'venue_ru', 'venue_en', 'seo_title_kk',
        'seo_title_ru', 'seo_title_en', 'seo_description_kk', 'seo_description_ru',
        'seo_description_en', 'view_count', 'homepage_click_count', 'registration_click_count',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'immutable_datetime',
            'show_on_homepage' => 'boolean',
            'approved_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'scheduled_publish_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'homepage_until' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'is_featured' => 'boolean',
            'is_pinned' => 'boolean',
            'registration_required' => 'boolean',
            'gallery_enabled' => 'boolean',
            'homepage_priority' => 'integer',
            'capacity' => 'integer',
            'view_count' => 'integer',
            'homepage_click_count' => 'integer',
            'registration_click_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (News $news): void {
            if (Schema::hasColumn('news', 'public_id')) {
                $news->public_id ??= (string) Str::uuid();
            }
            if (Schema::hasColumn('news', 'type')) {
                $news->type ??= in_array($news->category, self::TYPES, true) ? $news->category : 'update';
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function newsCategory(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function annualPlanItem(): BelongsTo
    {
        return $this->belongsTo(AnnualContentPlanItem::class, 'annual_plan_item_id');
    }

    public function repositoryItem(): BelongsTo
    {
        return $this->belongsTo(RepositoryItem::class);
    }

    public function linkedPlanItem(): HasOne
    {
        return $this->hasOne(AnnualContentPlanItem::class, 'publication_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NewsRevision::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(NewsReview::class);
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(NewsSlugRedirect::class);
    }

    public function bibliographicRecords(): BelongsToMany
    {
        return $this->belongsToMany(BibliographicRecord::class, 'news_bibliographic_record');
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
        if (! Schema::hasColumn('news', 'published_at')) {
            return $query->where('status', 'published')->where(fn (Builder $builder) => $builder->whereNull('publish_at')->orWhere('publish_at', '<=', now('UTC')));
        }

        return $query
            ->where('status', 'published')
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now('UTC'));
            });
    }

    public function scopeScheduled(Builder $query): Builder
    {
        if (! Schema::hasColumn('news', 'scheduled_publish_at')) {
            return $query->where('status', 'scheduled')->whereNotNull('publish_at');
        }

        return $query
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_publish_at');
    }

    public function scopeDueForPublication(Builder $query): Builder
    {
        if (! Schema::hasColumn('news', 'scheduled_publish_at')) {
            return $query->scheduled()->where('publish_at', '<=', now('UTC'));
        }

        return $query
            ->scheduled()
            ->where('scheduled_publish_at', '<=', now('UTC'));
    }

    public function scopeHomepage(Builder $query): Builder
    {
        return $query->where('show_on_homepage', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $needle = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder->whereRaw('LOWER(title) LIKE ?', [$needle])->orWhereRaw('LOWER(body) LIKE ?', [$needle]);
            if (Schema::hasColumn('news', 'title_kk')) {
                $builder->orWhereRaw('LOWER(COALESCE(title_kk, \'\')) LIKE ?', [$needle])->orWhereRaw('LOWER(COALESCE(title_ru, \'\')) LIKE ?', [$needle])->orWhereRaw('LOWER(COALESCE(title_en, \'\')) LIKE ?', [$needle]);
            }
        });
    }

    public function localized(string $field, ?string $locale = null): string
    {
        $locale = in_array($locale, self::LANGUAGES, true) ? $locale : app()->getLocale();
        $value = trim((string) $this->getAttribute($field.'_'.$locale));
        if ($value !== '') {
            return $value;
        }
        $fallback = trim((string) $this->getAttribute($field.'_kk'));
        if ($fallback !== '') {
            return $fallback;
        }

        return trim((string) $this->getAttribute(match ($field) {
            'content' => 'body', 'title', 'excerpt', 'venue' => $field, default => $field,
        }));
    }

    public function localizedSlug(?string $locale = null): string
    {
        return $this->localized('slug', $locale) ?: (string) $this->slug;
    }
}
