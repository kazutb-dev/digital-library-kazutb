<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    public const SCHEMES = ['topical', 'geographic', 'genre', 'local'];

    protected $fillable = ['term', 'normalized_term', 'scheme'];

    public function bibliographicRecords(): BelongsToMany
    {
        return $this->belongsToMany(BibliographicRecord::class, 'bibliographic_record_subject')
            ->withPivot(['position', 'marc_tag'])
            ->withTimestamps();
    }

    public static function normalizeTerm(string $term): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($term)), 'UTF-8');
    }
}
