<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiteratureDraftItem extends Model
{
    protected $fillable = [
        'draft_id',
        'identifier',
        'title',
        'type',
        'author',
        'publisher',
        'year',
        'language',
        'isbn',
        'available',
        'total',
        'url',
        'provider',
        'access_type',
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'integer',
            'total' => 'integer',
            'added_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LiteratureDraft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(LiteratureDraft::class, 'draft_id');
    }

    /**
     * Convert to the shortlist item array format used by the API.
     *
     * @return array<string, mixed>
     */
    public function toShortlistArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'title' => $this->title,
            'type' => $this->type,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'year' => $this->year,
            'language' => $this->language,
            'isbn' => $this->isbn,
            'available' => $this->available,
            'total' => $this->total,
            'url' => $this->url,
            'provider' => $this->provider,
            'access_type' => $this->access_type,
            'addedAt' => $this->added_at?->toIso8601String(),
        ];
    }
}
