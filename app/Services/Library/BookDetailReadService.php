<?php

namespace App\Services\Library;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\UdcCode;
use App\Models\User;
use App\Services\Localization\LocalizedContentResolver;
use App\Support\DatabaseSchema;
use App\Support\PublicCatalogLanguage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Book detail read model (Master.md 9.2-9.3).
 *
 * Resolves a record by numeric id or ISBN from the canonical catalogue tables
 * and returns the shape the book page and /api/v1/book-db expect. Returns null
 * for an unknown identifier so the caller can answer 404 honestly.
 */
class BookDetailReadService
{
    public function __construct(private readonly LocalizedContentResolver $localizedContent) {}

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
        $isbnExpression = BibliographicRecord::query()->getConnection()->getDriverName() === 'pgsql'
            ? "LOWER(REGEXP_REPLACE(COALESCE(isbn, ''), '[^0-9Xx]', '', 'g'))"
            : "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(isbn, ''), '-', ''), ' ', ''), '.', ''), '/', ''))";

        $eagerLoads = ['translations'];
        if (DatabaseSchema::hasTable('contributors') && DatabaseSchema::hasTable('bibliographic_record_contributor')) {
            $eagerLoads[] = 'contributors';
        }
        if (DatabaseSchema::hasTable('subjects') && DatabaseSchema::hasTable('bibliographic_record_subject')) {
            $eagerLoads[] = 'subjects';
        }

        $record = BibliographicRecord::query()
            ->where(function (Builder $builder) use ($identifier, $isbnExpression, $normalizedIsbn): void {
                $builder
                    ->where('isbn', $identifier)
                    ->orWhereRaw("{$isbnExpression} = ?", [mb_strtolower($normalizedIsbn)]);
                if (ctype_digit($identifier)) {
                    $builder->orWhere('id', (int) $identifier);
                }
            })
            ->with($eagerLoads)
            ->withCount([
                'copies' => fn (Builder $copies) => $copies->whereNotIn('status', ['written_off', 'lost']),
                'copies as available_copies_count' => fn (Builder $copies) => $copies->availableForCirculation(),
            ])
            ->first();

        if ($record === null) {
            return null;
        }

        $localized = $this->localizedContent->bibliographic($record);

        $copies = $record->copies()
            ->whereNotIn('status', ['written_off', 'lost'])
            ->with(['branch', 'fund'])
            ->get();

        $available = $copies->filter(fn (BookCopy $copy): bool => $copy->status === 'available' && $copy->isCirculatable())->count();
        $issued = $copies->whereIn('status', ['issued', 'overdue'])->count();
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

        $languageCode = PublicCatalogLanguage::normalize((string) $record->language);
        $contributors = $record->relationLoaded('contributors')
            ? $record->contributors->map(static fn ($contributor): array => [
                'name' => (string) $contributor->name,
                'role' => (string) ($contributor->pivot?->role ?? 'author'),
                'kind' => (string) ($contributor->kind ?? 'person'),
            ])->values()
            : collect();
        $subjects = $record->relationLoaded('subjects')
            ? $record->subjects->map(static fn ($subject): array => [
                'term' => (string) $subject->term,
                'scheme' => (string) ($subject->scheme ?? 'topical'),
            ])->values()
            : collect();
        $classification = $subjects
            ->map(static fn (array $subject): array => [
                'id' => $subject['term'],
                'label' => $subject['term'],
                'sourceKind' => 'subject',
                'scheme' => $subject['scheme'],
            ]);
        if (filled($record->category) && ! $classification->contains(fn (array $item): bool => $item['label'] === $record->category)) {
            $classification->push([
                'id' => (string) $record->category,
                'label' => (string) $record->category,
                'sourceKind' => 'category',
                'scheme' => 'local',
            ]);
        }
        $authorNames = $record->allAuthors();
        $primaryAuthorRaw = trim((string) ($record->primary_author ?? '')) ?: (string) ($authorNames[0] ?? '');

        return [
            'id' => (string) $record->getKey(),
            'title' => [
                'display' => $localized['title'],
                'raw' => (string) $record->title,
                'subtitle' => (string) ($record->subtitle ?? ''),
                'original' => $localized['original_title'],
                'isFallback' => $localized['is_fallback'],
            ],
            'primaryAuthor' => $primaryAuthorRaw ?: __('common.catalog.author_unknown'),
            // Unsubstituted source values. The display fields above fall back to
            // "author/publisher not specified" labels, which are fine on screen
            // but must never end up inside a generated bibliographic reference.
            'primaryAuthorRaw' => $primaryAuthorRaw,
            'publisherRaw' => (string) ($record->publisher ?? ''),
            'authors' => array_map(
                static fn (string $name): array => ['name' => $name],
                $authorNames,
            ),
            'publisher' => [
                'name' => (string) ($record->publisher ?: __('common.catalog.publisher_unknown')),
            ],
            'publicationPlace' => (string) ($record->publication_place ?? ''),
            'statementOfResponsibility' => (string) ($record->statement_of_responsibility ?? ''),
            'editionStatement' => (string) ($record->edition_statement ?? ''),
            'publicationYear' => $record->publication_year,
            'language' => [
                'code' => $languageCode,
                'raw' => $languageCode,
                'label' => PublicCatalogLanguage::label($languageCode),
            ],
            'isbn' => [
                'raw' => (string) ($record->isbn ?? ''),
                'isValid' => $this->isValidIsbn((string) ($record->isbn ?? '')),
            ],
            'issn' => [
                'raw' => (string) ($record->issn ?? ''),
            ],
            'bbkCode' => (string) ($record->bbk_code ?? ''),
            'localClassification' => (string) ($record->local_classification ?? ''),
            'physicalExtent' => (string) ($record->physical_extent ?? ''),
            'physicalDetails' => (string) ($record->physical_details ?? ''),
            'dimensions' => (string) ($record->dimensions ?? ''),
            'accompanyingMaterial' => (string) ($record->accompanying_material ?? ''),
            'seriesTitle' => (string) ($record->series_title ?? ''),
            'seriesNumber' => (string) ($record->series_number ?? ''),
            'volume' => (string) ($record->volume ?? ''),
            'issue' => (string) ($record->issue ?? ''),
            'partNumber' => (string) ($record->part_number ?? ''),
            'partTitle' => (string) ($record->part_title ?? ''),
            'materialDesignation' => (string) ($record->material_designation ?? ''),
            'controlNumber' => (string) ($record->control_number ?? ''),
            'countryCode' => (string) ($record->country_code ?? ''),
            'ksuLiteratureType' => (string) ($record->ksu_literature_type ?? ''),
            'faculty' => (string) ($record->faculty ?? ''),
            'department' => (string) ($record->department ?? ''),
            'disciplines' => (string) ($record->disciplines ?? ''),
            'specialty' => (string) ($record->specialty ?? ''),
            'recordCreatedOn' => optional($record->record_created_on)->format('Y-m-d') ?? '',
            'contributors' => $contributors->all(),
            'subjects' => $subjects->all(),
            'resourceType' => (string) $record->resource_type,
            'annotation' => $localized['annotation'],
            'originalAnnotation' => $localized['original_annotation'],
            'keywords' => $localized['keywords'],
            'metadataLocale' => $localized['locale'],
            'notes' => $canViewInternalNotes ? (string) ($record->notes ?? '') : '',
            'coverPath' => $record->cover_path,
            'authorMark' => (string) ($record->author_mark ?? ''),
            'copies' => [
                'available' => $available,
                'issued' => $issued,
                'total' => $total,
            ],
            'availability' => [
                'isAvailable' => $available > 0,
                'availableCopies' => $available,
                'issuedCopies' => $issued,
                'totalCopies' => $total,
                'locations' => $this->groupLocations($copies, $viewer !== null),
            ],
            'viewer' => [
                'authenticated' => $viewer !== null,
                'canReserve' => $viewer !== null && ! $record->is_draft && $available > 0,
                'reservationEligible' => ! $record->is_draft && $available > 0,
                'activeLoan' => $readerLoan === null ? null : [
                    'status' => (string) $readerLoan->status,
                    'issuedAt' => $readerLoan->issued_at?->toIso8601String(),
                    'dueAt' => $readerLoan->due_at?->toIso8601String(),
                ],
            ],
            ...($canViewInternalNotes ? [
                'quality' => [
                    'needsReview' => (bool) $record->is_draft,
                    'reviewReasonCodes' => $record->missingRequiredFields(),
                ],
            ] : []),
            'classification' => $classification->values()->all(),
            'udc' => [
                // UDC is public catalogue metadata. It is safe for guests;
                // copy identifiers and exact storage data are gated elsewhere.
                'raw' => $udcCode,
                'description' => $udcReference?->localizedDescription() ?? '',
                'display' => trim($udcCode.($udcReference ? ' — '.$udcReference->localizedDescription() : '')),
                'source' => $udcCode !== '' ? 'udc' : '',
            ],
            'electronicMaterials' => $record->electronicMaterials()
                ->published()
                ->get()
                ->map(static fn ($material): array => [
                    'id' => (string) $material->getKey(),
                    'title' => (string) $material->title,
                    'fileType' => (string) $material->file_type,
                    'accessLevel' => (string) $material->access_level,
                    'licenseTerms' => (string) ($material->license_terms ?? ''),
                    'allowDownload' => (bool) $material->allow_download,
                ])
                ->all(),
            'relatedMaterials' => $record->relatedRecords()
                ->with('translations')
                ->limit(6)
                ->get(['bibliographic_records.id', 'title', 'isbn', 'primary_author', 'publication_year'])
                ->map(fn (BibliographicRecord $related): array => [
                    'id' => (string) $related->getKey(),
                    'title' => $this->localizedContent->bibliographic($related)['title'],
                    'isbn' => (string) ($related->isbn ?? ''),
                    'author' => (string) ($related->primary_author ?? ''),
                    'publicationYear' => $related->publication_year,
                ])
                ->all(),
            'similarMaterials' => $this->similarMaterials($record),
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
            ->with('translations')
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
                ->with('translations')
                ->get();
            $exact = $exact->concat($fallback);
        }

        return $exact
            ->map(fn (BibliographicRecord $similar): array => [
                'id' => (string) $similar->getKey(),
                'title' => $this->localizedContent->bibliographic($similar)['title'],
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
                        'code' => (string) ($first->branch?->code ?? ''),
                        'name' => (string) ($first->branch?->name ?? ''),
                    ],
                    'servicePoint' => [
                        'code' => (string) ($first->branch?->code ?? ''),
                        'name' => (string) ($first->branch?->name ?? ''),
                    ],
                    'storageSigla' => $showExactLocation ? (string) ($first->storage_sigla ?? '') : '',
                    'address' => $showExactLocation ? (string) ($first->branch?->address ?? '') : '',
                    'shelf' => $showExactLocation ? (string) ($first->shelf_location ?? '') : '',
                    'exactLocationPending' => $showExactLocation
                        && $group->filter(fn (BookCopy $copy): bool => $copy->status === 'available' && $copy->isCirculatable())->isNotEmpty()
                        && $group->filter(fn (BookCopy $copy): bool => $copy->status === 'available' && $copy->isCirculatable())->every(
                            static fn (BookCopy $copy): bool => blank($copy->shelf_location),
                        ),
                    'copies' => [
                        'total' => $group->count(),
                        'available' => $group->filter(fn (BookCopy $copy): bool => $copy->status === 'available' && $copy->isCirculatable())->count(),
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
