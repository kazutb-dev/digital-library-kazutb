<?php

namespace App\Models\Catalog;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BibliographicRecordTranslation extends Model
{
    public const LOCALES = ['kk', 'ru', 'en'];

    public const STATUSES = ['draft', 'reviewed', 'approved', 'needs_review'];

    public const PUBLIC_STATUSES = ['reviewed', 'approved'];

    public const SOURCES = ['original', 'manual_translation', 'imported', 'legacy'];

    protected $fillable = [
        'bibliographic_record_id', 'locale', 'title', 'annotation', 'keywords',
        'translation_status', 'source', 'translated_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(BibliographicRecord::class, 'bibliographic_record_id');
    }

    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePubliclyUsable(Builder $query): Builder
    {
        return $query->whereIn('translation_status', self::PUBLIC_STATUSES);
    }
}
