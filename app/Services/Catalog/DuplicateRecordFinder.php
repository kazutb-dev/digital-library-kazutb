<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BibliographicRecord;
use App\Services\Library\IsbnService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Likely-duplicate lookup for cataloguing (Master.md 11.3, ДИР 6.2).
 *
 * Extracted from CatalogController so the same rule backs both entry points:
 * the "проверить на дубли" control the librarian presses before submitting,
 * and the server-side guard that still runs on store().
 */
class DuplicateRecordFinder
{
    public function __construct(private readonly IsbnService $isbn) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function find(array $attributes, ?int $ignoreId = null): ?BibliographicRecord
    {
        $rawTitle = trim((string) ($attributes['title'] ?? ''));
        if ($rawTitle === '') {
            return null;
        }

        $byTitle = BibliographicRecord::query()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            // Exact match first, then case-folded: SQLite's LOWER() only folds
            // ASCII, so a Cyrillic title would slip past the folded comparison.
            ->where(fn (Builder $builder) => $builder
                ->where('title', $rawTitle)
                ->orWhereRaw('LOWER(title) = ?', [mb_strtolower($rawTitle)]))
            ->when($attributes['publication_year'] ?? null, fn (Builder $builder, $year) => $builder->where('publication_year', $year))
            ->when(
                trim((string) ($attributes['primary_author'] ?? '')) !== '',
                function (Builder $builder) use ($attributes): void {
                    $rawAuthor = trim((string) $attributes['primary_author']);
                    $builder->where(fn (Builder $inner) => $inner
                        ->where('primary_author', $rawAuthor)
                        ->orWhereRaw('LOWER(COALESCE(primary_author, \'\')) = ?', [mb_strtolower($rawAuthor)]));
                },
            )
            ->first();

        if ($byTitle !== null) {
            return $byTitle;
        }

        $isbn = $this->isbn->normalize((string) ($attributes['isbn'] ?? ''));
        if ($isbn === '') {
            return null;
        }

        $query = BibliographicRecord::query();
        $isbnExpression = $query->getConnection()->getDriverName() === 'pgsql'
            ? "REGEXP_REPLACE(UPPER(COALESCE(isbn, '')), '[^0-9X]', '', 'g')"
            : "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(isbn, ''), '-', ''), ' ', ''), '.', ''), '/', ''), '(', ''), ')', ''))";

        return $query
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->whereRaw("{$isbnExpression} = ?", [$isbn])
            ->first();
    }

    /**
     * Presentation payload shared by the flash message and the JSON endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function describe(?BibliographicRecord $record): ?array
    {
        if ($record === null) {
            return null;
        }

        return [
            'id' => $record->getKey(),
            'title' => $record->title,
            'author' => $record->primary_author,
            'year' => $record->publication_year,
            'isbn' => $record->isbn,
        ];
    }
}
