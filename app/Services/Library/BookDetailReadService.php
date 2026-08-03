<?php

namespace App\Services\Library;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\UdcCode;
use App\Models\User;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Book detail read model (Master.md §9.2-§9.3).
 *
 * Resolves a record by numeric id or ISBN from the canonical catalogue tables
 * and returns the shape the book page and /api/v1/book-db expect. Returns null
 * for an unknown identifier so the caller can answer 404 honestly.
 */
class BookDetailReadService
{
    public function findByIdentifier(string $identifier, ?User $viewer = null): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            return null;
        }

        $normalizedIsbn = preg_replace('/[^0-9xX]/', '', $identifier) ?: $identifier;

        $record = BibliographicRecord::query()
            ->where(function (Builder $builder) use ($identifier, $normalizedIsbn): void {
                $builder->where('isbn', $identifier)->orWhere('isbn', $normalizedIsbn);
                if (ctype_digit($identifier)) {
                    $builder->orWhere('id', (int) $identifier);
                }
            })
            ->withCount([
                'copies',
                'copies as available_copies_count' => fn (Builder $copies) => $copies->where('status', 'available'),
            ])
            ->first();

        if ($record === null) {
            return null;
        }

        $copies = $record->copies()
            ->whereNotIn('status', ['written_off'])
            ->with(['branch', 'fund'])
            ->get();

        $available = $copies->where('status', 'available')->count();
        $total = $copies->count();
        $readerLoan = $viewer === null
            ? null
            : Loan::query()
                ->where('user_id', $viewer->getKey())
                ->whereIn('status', ['active', 'overdue'])
                ->whereHas('copy', fn (Builder $copy) => $copy->where('bibliographic_record_id', $record->getKey()))
                ->latest('issued_at')
                ->first();
        $canViewInternalNotes = $viewer?->canAny(['catalog.edit_record', 'catalog.manage_record']) ?? false;
        $udcCode = trim((string) ($record->udc_code ?? ''));
        $udcReference = $udcCode === ''
            ? null
            : UdcCode::query()
                ->whereRaw('? LIKE code || ?', [$udcCode, '%'])
                ->orderByRaw('LENGTH(code) DESC')
                ->first();

        return [
            'id' => (string) $record->getKey(),
            'title' => [
                'display' => (string) $record->title,
                'raw' => (string) $record->title,
                'subtitle' => (string) ($record->subtitle ?? ''),
            ],
            'primaryAuthor' => $record->primary_author ?: __('common.catalog.author_unknown'),
            // Unsubstituted source values. The display fields above fall back to
            // "author/publisher not specified" labels, which are fine on screen
            // but must never end up inside a generated bibliographic reference.
            'primaryAuthorRaw' => (string) ($record->primary_author ?? ''),
            'publisherRaw' => (string) ($record->publisher ?? ''),
            'authors' => array_map(
                static fn (string $name): array => ['name' => $name],
                $record->allAuthors(),
            ),
            'publisher' => [
                'name' => (string) ($record->publisher ?: __('common.catalog.publisher_unknown')),
            ],
            'publicationYear' => $record->publication_year,
            'language' => [
                'code' => (string) $record->language,
                'raw' => (string) $record->language,
            ],
            'isbn' => [
                'raw' => (string) ($record->isbn ?? ''),
                'isValid' => $this->isValidIsbn((string) ($record->isbn ?? '')),
            ],
            'resourceType' => (string) $record->resource_type,
            'annotation' => (string) ($record->annotation ?? ''),
            'keywords' => (array) ($record->keywords ?? []),
            'notes' => $canViewInternalNotes ? (string) ($record->notes ?? '') : '',
            'coverPath' => $record->cover_path,
            'authorMark' => (string) ($record->author_mark ?? ''),
            'copies' => [
                'available' => $available,
                'total' => $total,
            ],
            'availability' => [
                'isAvailable' => $available > 0,
                'availableCopies' => $available,
                'totalCopies' => $total,
                'locations' => $this->groupLocations($copies, $viewer !== null),
            ],
            'viewer' => [
                'authenticated' => $viewer !== null,
                'canReserve' => $viewer !== null && $available > 0,
                'activeLoan' => $readerLoan === null ? null : [
                    'status' => (string) $readerLoan->status,
                    'issuedAt' => $readerLoan->issued_at?->toIso8601String(),
                    'dueAt' => $readerLoan->due_at?->toIso8601String(),
                ],
            ],
            'quality' => [
                'needsReview' => (bool) $record->is_draft,
                'reviewReasonCodes' => $record->missingRequiredFields(),
            ],
            'classification' => $record->category !== null && $record->category !== ''
                ? [['id' => $record->category, 'label' => $record->category, 'sourceKind' => 'subject']]
                : [],
            'udc' => [
                'raw' => $viewer !== null ? $udcCode : '',
                'description' => $udcReference?->localizedDescription() ?? '',
                'display' => $viewer !== null
                    ? trim($udcCode.($udcReference ? ' — '.$udcReference->localizedDescription() : ''))
                    : ($udcReference?->localizedDescription() ?? ''),
                'source' => $udcCode !== '' ? 'udc' : '',
            ],
            'electronicMaterials' => $record->electronicMaterials()
                ->where('is_active', true)
                ->get()
                ->map(static fn ($material): array => [
                    'id' => (string) $material->getKey(),
                    'title' => (string) $material->title,
                    'fileType' => (string) $material->file_type,
                    'accessLevel' => (string) $material->access_level,
                    'licenseTerms' => (string) ($material->license_terms ?? ''),
                    'allowDownload' => (bool) $material->allow_download,
                    'externalUrl' => $material->external_url,
                ])
                ->all(),
            'relatedMaterials' => $record->relatedRecords()
                ->limit(6)
                ->get(['bibliographic_records.id', 'title', 'isbn', 'primary_author', 'publication_year'])
                ->map(static fn (BibliographicRecord $related): array => [
                    'id' => (string) $related->getKey(),
                    'title' => (string) $related->title,
                    'isbn' => (string) ($related->isbn ?? ''),
                    'author' => (string) ($related->primary_author ?? ''),
                    'publicationYear' => $related->publication_year,
                ])
                ->all(),
            'similarMaterials' => $this->similarMaterials($record),
            'source' => 'catalog.bibliographic_records',
        ];
    }

    /**
     * Automatic recommendations by exact UDC, then by its top-level class.
     *
     * @return list<array<string, mixed>>
     */
    private function similarMaterials(BibliographicRecord $record): array
    {
        $udc = trim((string) ($record->udc_code ?? ''));
        if ($udc === '') {
            return [];
        }

        $exact = BibliographicRecord::query()
            ->whereKeyNot($record->getKey())
            ->where('udc_code', $udc)
            ->where('is_draft', false)
            ->whereNotNull('title')
            ->whereRaw("TRIM(title) <> ''")
            ->where('title', 'not like', '[Без заглавия;%')
            ->orderByDesc('publication_year')
            ->limit(6)
            ->get();

        if ($exact->count() < 6) {
            $fallback = BibliographicRecord::query()
                ->whereKeyNot($record->getKey())
                ->whereNotIn('id', $exact->pluck('id'))
                ->where('udc_code', 'like', mb_substr($udc, 0, 1).'%')
                ->where('is_draft', false)
                ->whereNotNull('title')
                ->whereRaw("TRIM(title) <> ''")
                ->where('title', 'not like', '[Без заглавия;%')
                ->orderByDesc('publication_year')
                ->limit(6 - $exact->count())
                ->get();
            $exact = $exact->concat($fallback);
        }

        return $exact
            ->map(static fn (BibliographicRecord $similar): array => [
                'id' => (string) $similar->getKey(),
                'title' => (string) $similar->title,
                'isbn' => (string) ($similar->isbn ?? ''),
                'author' => (string) ($similar->primary_author ?? ''),
                'publicationYear' => $similar->publication_year,
            ])
            ->values()
            ->all();
    }

    /**
     * Holdings grouped by branch + fund, richest location first.
     *
     * @param  Collection<int, BookCopy>  $copies
     * @return list<array<string, mixed>>
     */
    private function groupLocations(Collection $copies, bool $showExactLocation): array
    {
        return $copies
            ->groupBy(function (BookCopy $copy) use ($showExactLocation): string {
                $key = ($copy->branch?->code ?? 'unassigned').'|'.($copy->fund?->code ?? '');

                return $showExactLocation
                    ? $key.'|'.($copy->storage_sigla ?? '').'|'.($copy->shelf_location ?? '')
                    : $key;
            })
            ->map(function (Collection $group) use ($showExactLocation): array {
                $first = $group->first();

                return [
                    'institutionUnit' => [
                        'code' => (string) ($first->fund?->code ?? ''),
                        'name' => (string) ($first->fund?->name ?? ''),
                    ],
                    'campus' => [
                        'code' => $showExactLocation ? (string) ($first->branch?->code ?? '') : '',
                        'name' => $showExactLocation ? (string) ($first->branch?->name ?? '') : '',
                    ],
                    'servicePoint' => [
                        'code' => $showExactLocation ? (string) ($first->branch?->code ?? '') : '',
                        'name' => $showExactLocation ? (string) ($first->branch?->name ?? '') : '',
                    ],
                    'storageSigla' => $showExactLocation ? (string) ($first->storage_sigla ?? '') : '',
                    'address' => $showExactLocation ? (string) ($first->branch?->address ?? '') : '',
                    'shelf' => $showExactLocation ? (string) ($first->shelf_location ?? '') : '',
                    'exactLocationPending' => $showExactLocation
                        && $group->where('status', 'available')->isNotEmpty()
                        && $group->where('status', 'available')->every(
                            static fn (BookCopy $copy): bool => blank($copy->shelf_location),
                        ),
                    'copies' => [
                        'total' => $group->count(),
                        'available' => $group->where('status', 'available')->count(),
                        'unavailable' => $group->whereNotIn('status', ['available'])->count(),
                        'review' => 0,
                        'problem' => $group->whereIn('status', ['lost', 'under_repair'])->count(),
                        'orphan' => 0,
                    ],
                ];
            })
            ->sortByDesc(fn (array $location): int => $location['copies']['available'])
            ->values()
            ->all();
    }

    /**
     * ISBN-10/13 checksum validation — the same signal the old MARC view
     * exposed as isbn_is_valid, now computed instead of stored.
     */
    private function isValidIsbn(string $isbn): bool
    {
        $digits = strtoupper(preg_replace('/[^0-9xX]/', '', $isbn) ?? '');

        if (strlen($digits) === 13) {
            $sum = 0;
            for ($i = 0; $i < 13; $i++) {
                if (! ctype_digit($digits[$i])) {
                    return false;
                }
                $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
            }

            return $sum % 10 === 0;
        }

        if (strlen($digits) === 10) {
            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $char = $digits[$i];
                if ($char === 'X') {
                    if ($i !== 9) {
                        return false;
                    }
                    $value = 10;
                } elseif (ctype_digit($char)) {
                    $value = (int) $char;
                } else {
                    return false;
                }
                $sum += $value * (10 - $i);
            }

            return $sum % 11 === 0;
        }

        return false;
    }
}
