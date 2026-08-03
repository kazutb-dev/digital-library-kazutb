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

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function relatedRecords(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'bibliographic_record_relations', 'record_id', 'related_record_id');
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

        return $query->where(function (Builder $builder) use ($needle, $term): void {
            $builder
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(subtitle, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(primary_author, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(publisher, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(isbn, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(udc_code, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(author_mark, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(resource_type, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(annotation, \'\')) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(additional_authors AS TEXT)) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(CAST(keywords AS TEXT)) LIKE ?', [$needle])
                ->orWhereJsonContains('keywords', $term);
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
        return array_values(array_filter([
            $this->primary_author,
            ...(array) ($this->additional_authors ?? []),
        ]));
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
        return $this->copies()->where('status', 'available')->count();
    }
}
