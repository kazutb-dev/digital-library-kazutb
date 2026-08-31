<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contributor extends Model
{
    public const KINDS = ['person', 'organisation', 'meeting'];

    public const ROLES = ['author', 'editor', 'translator', 'compiler', 'other'];

    protected $fillable = ['name', 'normalized_name', 'kind'];

    public function bibliographicRecords(): BelongsToMany
    {
        return $this->belongsToMany(BibliographicRecord::class, 'bibliographic_record_contributor')
            ->withPivot(['role', 'position', 'marc_tag'])
            ->withTimestamps();
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)), 'UTF-8');
    }
}
