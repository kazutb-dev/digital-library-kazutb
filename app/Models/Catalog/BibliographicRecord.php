<?php

namespace App\Models\Catalog;

use App\Models\User;
use App\Services\Catalog\DataQualityQueues;
use Database\Factories\Catalog\BibliographicRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BibliographicRecord extends Model
{
    /** @use HasFactory<BibliographicRecordFactory> */
    use HasFactory, SoftDeletes;

    public const RESOURCE_TYPES = [
        'book', 'textbook', 'study_guide', 'journal', 'periodical', 'dissertation',
        'abstract', 'article', 'publication', 'methodical', 'ebook', 'digital_document',
    ];

    public const LANGUAGES = ['kk', 'ru', 'en', 'other'];

    /**
     * Fields every complete record must carry (Master.md §7). A record saved
     * without them becomes a draft and enters the Data Cleanup queue.
     */
    public const REQUIRED_FOR_COMPLETE = ['title', 'primary_author', 'publisher', 'publication_year', 'udc_code', 'annotation'];

    /**
     * Letters present in Kazakh Cyrillic but absent from Russian. Lower case
     * only is not enough — legacy titles are sometimes fully upper case.
     *
     * @var list<string>
     */
    public const KAZAKH_ONLY_LETTERS = [
        'Ә', 'ә', 'Ғ', 'ғ', 'Қ', 'қ', 'Ң', 'ң', 'Ө', 'ө', 'Ұ', 'ұ', 'Ү', 'ү', 'Һ', 'һ', 'І', 'і',
    ];

    protected $fillable = [
        'title', 'subtitle', 'primary_author', 'additional_authors', 'publisher',
        'publication_year', 'language', 'udc_code', 'author_mark', 'category',
        'annotation', 'keywords', 'isbn', 'resource_type', 'cover_path', 'notes',
        'publication_place', 'statement_of_responsibility', 'edition_statement',
        'issn', 'bbk_code', 'local_classification', 'physical_extent',
        'physical_details', 'dimensions', 'accompanying_material', 'series_title',
        'series_number', 'volume', 'issue', 'part_number', 'part_title',
        'control_number', 'country_code', 'cataloging_language', 'source_agency',
        'material_designation', 'ksu_literature_type', 'faculty', 'department',
        'disciplines', 'specialty', 'record_created_on',
        'legacy_language_code', 'legacy_modified_at',
        'legacy_local_path', 'legacy_import_batch_id', 'legacy_imported_at',
        'is_draft', 'needs_manual_review', 'review_note', 'review_category', 'responsible_librarian_id',
        'merged_into_id', 'merge_status', 'legacy_external_id',
    ];

    protected static function newFactory(): BibliographicRecordFactory
    {
        return BibliographicRecordFactory::new();
    }

    protected function casts(): array
    {
        return [
            'additional_authors' => 'array',
            'keywords' => 'array',
            'publication_year' => 'integer',
            'record_created_on' => 'date',
            'legacy_import_batch_id' => 'integer',
            'legacy_modified_at' => 'datetime',
            'legacy_imported_at' => 'datetime',
            'is_draft' => 'boolean',
            'needs_manual_review' => 'boolean',
        ];
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function electronicMaterials(): HasMany
    {
        return $this->hasMany(ElectronicMaterial::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BibliographicRecordTranslation::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function relatedRecords(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'bibliographic_record_relations', 'record_id', 'related_record_id');
    }

    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(Contributor::class, 'bibliographic_record_contributor')
            ->withPivot(['role', 'position', 'marc_tag'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'bibliographic_record_subject')
            ->withPivot(['position', 'marc_tag'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function legacyMarcRecords(): HasMany
    {
        return $this->hasMany(LegacyMarcRecord::class)->latest('id');
    }

    /** Alias used by read models that present the legacy payload as raw MARC. */
    public function rawMarcRecords(): HasMany
    {
        return $this->hasMany(LegacyMarcRecord::class)->latest('id');
    }

    public function responsibleLibrarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_librarian_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        $needle = '%'.mb_strtolower($term).'%';
        $normalizedIsbn = mb_strtolower((string) preg_replace('/[^0-9xX]/', '', $term));
        $normalizedIssn = mb_strtolower((string) preg_replace('/[^0-9xX]/', '', $term));
        $schema = $query->getConnection()->getSchemaBuilder();
        $hasRecoveryColumns = $schema->hasColumn('bibliographic_records', 'issn');
        $hasAcademicColumns = $schema->hasColumn('bibliographic_records', 'faculty');
        $hasContributors = $schema->hasTable('contributors') && $schema->hasTable('bibliographic_record_contributor');
        $hasSubjects = $schema->hasTable('subjects') && $schema->hasTable('bibliographic_record_subject');
        $isbnExpression = $query->getConnection()->getDriverName() === 'pgsql'
            ? "LOWER(REGEXP_REPLACE(COALESCE(isbn, ''), '[^0-9Xx]', '', 'g'))"
            : "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(isbn, ''), '-', ''), ' ', ''), '.', ''), '/', ''))";
        $issnExpression = $query->getConnection()->getDriverName() === 'pgsql'
            ? "LOWER(REGEXP_REPLACE(COALESCE(issn, ''), '[^0-9Xx]', '', 'g'))"
            : "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(issn, ''), '-', ''), ' ', ''), '.', ''), '/', ''))";

        return $query->where(function (Builder $builder) use ($hasAcademicColumns, $hasContributors, $hasRecoveryColumns, $hasSubjects, $isbnExpression, $issnExpression, $needle, $normalizedIsbn, $normalizedIssn, $term): void {
            $builder
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(subtitle, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(primary_author, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(publisher, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(isbn, \'\')) LIKE ?', [$needle])
                ->when(
                    strlen($normalizedIsbn) >= 8,
                    fn (Builder $isbnQuery) => $isbnQuery->orWhereRaw("{$isbnExpression} LIKE ?", ['%'.$normalizedIsbn.'%']),
                )
                ->orWhereRaw('LOWER(COALESCE(udc_code, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(author_mark, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(resource_type, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(annotation, \'\')) LIKE ?', [$needle])
                ->orWhereRaw("LOWER(COALESCE(CAST(publication_year AS TEXT), '')) LIKE ?", [$needle])
                ->orWhereRaw('LOWER(CAST(additional_authors AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(keywords AS TEXT)) LIKE ?', [$needle])
                ->orWhereJsonContains('keywords', $term);

            // SQLite's LOWER() is ASCII-only. Keep a native LIKE branch so
            // Cyrillic/Kazakh searches remain useful in the isolated suite;
            // PostgreSQL still benefits from the normalized branch above.
            foreach (['title', 'subtitle', 'primary_author', 'publisher', 'isbn', 'udc_code', 'author_mark', 'category', 'annotation'] as $column) {
                $builder->orWhere($column, 'like', '%'.$term.'%');
            }

            if ($hasRecoveryColumns) {
                foreach ([
                    'publication_place', 'statement_of_responsibility', 'edition_statement',
                    'issn', 'bbk_code', 'local_classification', 'physical_extent',
                    'physical_details', 'dimensions', 'accompanying_material', 'series_title',
                    'series_number', 'volume', 'issue', 'part_number', 'part_title',
                    'control_number', 'country_code', 'cataloging_language',
                    'material_designation',
                ] as $column) {
                    $builder
                        ->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$needle])
                        ->orWhere($column, 'like', '%'.$term.'%');
                }
                if (strlen($normalizedIssn) >= 7) {
                    $builder->orWhereRaw("{$issnExpression} LIKE ?", ['%'.$normalizedIssn.'%']);
                }
            }

            if ($hasAcademicColumns) {
                foreach (['ksu_literature_type', 'faculty', 'department', 'disciplines', 'specialty'] as $column) {
                    $builder
                        ->orWhereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", [$needle])
                        ->orWhere($column, 'like', '%'.$term.'%');
                }
            }

            if ($hasContributors) {
                $builder->orWhereHas('contributors', fn (Builder $contributors) => $contributors
                    ->where(function (Builder $names) use ($needle, $term): void {
                        $names
                            ->whereRaw('LOWER(contributors.name) LIKE ?', [$needle])
                            ->orWhere('contributors.name', 'like', '%'.$term.'%');
                    }));
            }

            if ($hasSubjects) {
                $builder->orWhereHas('subjects', fn (Builder $subjects) => $subjects
                    ->where(function (Builder $terms) use ($needle, $term): void {
                        $terms
                            ->whereRaw('LOWER(subjects.term) LIKE ?', [$needle])
                            ->orWhere('subjects.term', 'like', '%'.$term.'%');
                    }));
            }

            $builder->orWhereHas('translations', function (Builder $translations) use ($needle, $term): void {
                $translations
                    ->whereIn('translation_status', BibliographicRecordTranslation::PUBLIC_STATUSES)
                    ->where(function (Builder $content) use ($needle, $term): void {
                        $content
                            ->whereRaw('LOWER(title) LIKE ?', [$needle])
                            ->orWhere('title', 'like', '%'.$term.'%')
                            ->orWhereRaw('LOWER(COALESCE(annotation, \'\')) LIKE ?', [$needle])
                            ->orWhere('annotation', 'like', '%'.$term.'%')
                            ->orWhereRaw('LOWER(CAST(keywords AS TEXT)) LIKE ?', [$needle])
                            ->orWhereJsonContains('keywords', $term);
                    });
            });
        });
    }

    /**
     * Titles containing at least one letter that exists in Kazakh Cyrillic but
     * not in Russian — the signal that a record tagged `ru` is really Kazakh.
     *
     * Built from LIKE rather than a regex on purpose: PostgreSQL's `~` is not
     * available on SQLite, which the test suite runs on. LIKE with a leading
     * wildcard scans either way, so nothing is lost.
     */
    public function scopeTitleHasKazakhLetters(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            foreach (self::KAZAKH_ONLY_LETTERS as $letter) {
                $builder->orWhere('title', 'like', '%'.$letter.'%');
            }
        });
    }

    /**
     * Records whose title or author carries a legacy cp1251 substitution glyph
     * (є ѓ ќ ± µ ў) in place of a real Kazakh letter. LIKE rather than a regex
     * so the SQLite test suite can run it.
     */
    public function scopeHasCorruptedGlyphs(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            foreach (DataQualityQueues::CORRUPTION_GLYPHS as $glyph) {
                $builder->orWhere('title', 'like', '%'.$glyph.'%')
                    ->orWhere('primary_author', 'like', '%'.$glyph.'%');
            }
        });
    }

    /**
     * How confident we are that a title tagged non-Kazakh is really Kazakh,
     * expressed as the share of whitespace-separated words carrying a
     * Kazakh-only letter.
     *
     * A whole-title match is a safe bulk fix; a single Kazakh word inside an
     * otherwise Russian title is usually a proper noun ("Нұрлы Жол") or a
     * homoglyph Roman numeral ("ХХІ"), and must be eyeballed. Computed in PHP
     * for the current page only — the equivalent SQL needs regexp_split, which
     * is PostgreSQL-specific.
     *
     * @return array{tier: 'high'|'medium'|'low', kazakh: int, total: int}
     */
    public function kazakhTitleConfidence(): array
    {
        $words = preg_split('/\s+/u', trim((string) $this->title)) ?: [];
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
        $total = count($words);

        $class = '['.implode('', self::KAZAKH_ONLY_LETTERS).']';
        $kazakh = count(array_filter(
            $words,
            static fn (string $word): bool => (bool) preg_match('/'.$class.'/u', $word),
        ));

        $ratio = $total > 0 ? $kazakh / $total : 0.0;

        return [
            'tier' => $ratio >= 0.60 ? 'high' : ($ratio >= 0.25 ? 'medium' : 'low'),
            'kazakh' => $kazakh,
            'total' => $total,
        ];
    }

    /**
     * All author names, primary first.
     *
     * @return list<string>
     */
    public function allAuthors(): array
    {
        $authors = array_filter([
            $this->primary_author,
            ...(array) ($this->additional_authors ?? []),
        ]);

        if ($this->relationLoaded('contributors')) {
            $authors = [
                ...$authors,
                ...$this->contributors
                    ->filter(fn (Contributor $contributor): bool => ($contributor->pivot?->role ?? 'author') === 'author')
                    ->pluck('name')
                    ->all(),
            ];
        }

        return collect($authors)
            ->map(static fn ($author): string => trim((string) $author))
            ->filter()
            ->unique(static fn (string $author): string => mb_strtolower($author, 'UTF-8'))
            ->values()
            ->all();
    }

    /**
     * Which of the required fields (§7) are missing — the record's Data
     * Cleanup signature. An empty result means the record is complete.
     *
     * @return list<string>
     */
    public function missingRequiredFields(): array
    {
        $missing = [];
        foreach (self::REQUIRED_FOR_COMPLETE as $field) {
            $value = $this->getAttribute($field);
            if ($value === null || trim((string) $value) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    public function availableCopiesCount(): int
    {
        return $this->copies()->availableForCirculation()->count();
    }
}
