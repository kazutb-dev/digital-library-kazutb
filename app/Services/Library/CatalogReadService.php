<?php

namespace App\Services\Library;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\UdcCode;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Public catalogue read model (Master.md §6, §8, §9).
 *
 * Reads the canonical `bibliographic_records` / `book_copies` tables. The
 * response shape is preserved from the previous legacy-view implementation so
 * the Blade catalogue, the React SPA, and /api/v1/catalog-db keep working
 * unchanged. There is no demo fallback: an empty collection returns an empty
 * result set, because a catalogue that invents books is worse than an empty one.
 */
class CatalogReadService
{
    /**
     * Public institution filter values mapped onto real branch/fund codes.
     * Keeping the legacy filter vocabulary avoids breaking existing links.
     */
    private const INSTITUTION_MAP = [
        'college_library' => ['funds' => ['COLLEGE'], 'branches' => []],
        'economic_library' => ['funds' => ['UNIVERSITY-ECONOMIC'], 'branches' => ['ECONOMICS-DESK']],
        'technology_library' => ['funds' => ['UNIVERSITY-TECHNOLOGY'], 'branches' => ['TECHNOLOGY-DESK']],
        'ktslib' => ['funds' => ['MAIN', 'RESEARCH', 'EDUCATIONAL'], 'branches' => ['SCIENTIFIC-LIBRARY', 'READING-ROOM']],
    ];

    /**
     * Service-point codes the catalogue UI already knows how to label
     * ("Библиотека колледжа · каб. 3"). Emitting them keeps those curated
     * labels working now that holdings come from branches and funds.
     */
    private const SERVICE_POINT_CODES = [
        'ECONOMICS-DESK' => '1',
        'TECHNOLOGY-DESK' => '2',
        'SCIENTIFIC-LIBRARY' => 'kstlib',
        'READING-ROOM' => 'kstlib',
    ];

    private const CAMPUS_CODES = [
        'ECONOMICS-DESK' => 'university_economic',
        'TECHNOLOGY-DESK' => 'university_technological',
        'SCIENTIFIC-LIBRARY' => 'university_central',
        'READING-ROOM' => 'university_central',
    ];

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function search(
        string $query = '',
        ?string $title = null,
        ?string $author = null,
        ?string $publisher = null,
        ?string $isbn = null,
        ?string $subject = null,
        ?string $udc = null,
        ?string $language = null,
        int $page = 1,
        int $limit = 10,
        string $sort = 'popular',
        ?int $yearFrom = null,
        ?int $yearTo = null,
        bool $availableOnly = false,
        bool $physicalOnly = false,
        ?string $materialType = null,
        ?string $subjectId = null,
        ?string $institution = null,
        bool $includeTotal = true,
        bool $includeLocations = true,
        ?string $resourceType = null,
        ?string $fund = null,
        ?string $branch = null,
        ?string $category = null,
        ?string $availability = null,
        ?string $format = null,
        bool $completeOnly = false,
        bool $includeUdcCode = false,
    ): array {
        $page = max($page, 1);
        $limit = min(max($limit, 1), 100);

        // A catalogue that has not been provisioned yet answers honestly with
        // an empty result set rather than failing the whole public page.
        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            return $this->emptyResult($page, $limit);
        }

        // The public catalogue represents the whole physical fund. Incomplete
        // metadata remains flagged for librarians through is_draft and the
        // Data Cleanup queue, but it must not hide a real book from readers.
        $builder = BibliographicRecord::query()
            ->when($completeOnly, fn (Builder $query) => $query
                ->where('is_draft', false)
                ->whereNotNull('title')
                ->whereRaw("TRIM(title) <> ''"))
            ->withCount([
                'copies',
                'copies as physical_copies_count' => fn (Builder $copies) => $copies->whereNotIn('status', ['written_off', 'lost']),
                'copies as available_copies_count' => fn (Builder $copies) => $copies->where('status', 'available'),
                'copies as issued_copies_count' => fn (Builder $copies) => $copies->whereIn('status', ['issued', 'overdue']),
                'copies as processing_copies_count' => fn (Builder $copies) => $copies->where('status', 'in_processing'),
                'copies as repair_copies_count' => fn (Builder $copies) => $copies->where('status', 'under_repair'),
                'copies as reading_room_copies_count' => fn (Builder $copies) => $copies->where('access_restriction', 'reading_room'),
                'copies as limited_copies_count' => fn (Builder $copies) => $copies->where('access_restriction', 'limited'),
                'electronicMaterials as active_electronic_materials_count' => fn (Builder $materials) => $materials->where('is_active', true),
            ]);
        $builder
            ->withSum('copies as total_issue_count', 'issue_count')
            ->withMax('copies as latest_registration_date', 'registration_date');

        $this->applyTextFilters($builder, $query, $title, $author, $publisher, $isbn, $subject);
        $this->applyFacets($builder, $udc, $language, $yearFrom, $yearTo, $materialType, $subjectId);
        $this->applyCopyFilters($builder, $availableOnly, $physicalOnly, $institution);
        $this->applyCanonicalFilters($builder, $resourceType, $fund, $branch, $category, $availability, $format);

        $total = $includeTotal ? (clone $builder)->count() : 0;

        $this->applySort($builder, $sort);

        $records = $builder
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        $locations = $includeLocations
            ? $this->loadLocations($records->pluck('id')->all(), $institution)
            : [];

        $data = $records
            ->map(fn (BibliographicRecord $record): array => $this->present(
                $record,
                $locations[$record->getKey()] ?? [],
                $this->popularRecordIds(),
                $includeUdcCode,
            ))
            ->all();

        $totalPages = max(1, $limit > 0 ? (int) ceil($total / $limit) : 1);

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * Return the most requested records for one lending desk. Popularity is
     * accumulated from the imported copy issue counters, scoped to the desk's
     * real fund/branch mapping rather than a presentation fixture.
     *
     * @return list<array<string, mixed>>
     */
    public function popularByInstitution(string $institution, int $limit = 4): array
    {
        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            return [];
        }

        $mapping = self::INSTITUTION_MAP[$institution] ?? null;
        if ($mapping === null) {
            return [];
        }

        $deskCopies = function (Builder $copies) use ($mapping): Builder {
            return $copies
                ->whereNotIn('status', ['written_off', 'lost'])
                ->where(function (Builder $scoped) use ($mapping): void {
                    if ($mapping['funds'] !== []) {
                        $scoped->orWhereHas('fund', fn (Builder $fund) => $fund->whereIn('code', $mapping['funds']));
                    }
                    if ($mapping['branches'] !== []) {
                        $scoped->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('code', $mapping['branches']));
                    }
                });
        };

        return BibliographicRecord::query()
            ->whereNotNull('title')
            ->whereRaw("TRIM(title) <> ''")
            ->where('title', 'not like', '[Без заглавия;%')
            ->whereHas('copies', $deskCopies)
            ->withCount([
                'copies as desk_copies_count' => $deskCopies,
                'copies as desk_available_copies_count' => fn (Builder $copies) => $deskCopies($copies)->where('status', 'available'),
            ])
            ->withSum(['copies as desk_issue_count' => $deskCopies], 'issue_count')
            ->orderByDesc('desk_issue_count')
            ->orderByDesc('desk_available_copies_count')
            ->orderBy('title')
            ->limit(max(1, min(12, $limit)))
            ->get()
            ->map(fn (BibliographicRecord $record): array => [
                'id' => (string) $record->getKey(),
                'identifier' => (string) ($record->isbn ?: $record->getKey()),
                'title' => (string) $record->title,
                'author' => (string) ($record->primary_author ?: __('common.catalog.author_unknown')),
                'copies' => (int) ($record->desk_available_copies_count ?? 0),
                'totalCopies' => (int) ($record->desk_copies_count ?? 0),
                'issueCount' => (int) ($record->desk_issue_count ?? 0),
                'coverPath' => $record->cover_path,
            ])
            ->all();
    }

    public function institutionCopiesCount(string $institution): int
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return 0;
        }

        $mapping = self::INSTITUTION_MAP[$institution] ?? null;
        if ($mapping === null) {
            return 0;
        }

        return (int) BookCopy::query()
            ->whereNotIn('status', ['written_off', 'lost'])
            ->where(function (Builder $scoped) use ($mapping): void {
                if ($mapping['funds'] !== []) {
                    $scoped->orWhereHas('fund', fn (Builder $fund) => $fund->whereIn('code', $mapping['funds']));
                }
                if ($mapping['branches'] !== []) {
                    $scoped->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('code', $mapping['branches']));
                }
            })
            ->count();
    }

    /**
     * Canonical filter axes that map straight onto the schema (Master.md §8.2):
     * document type, fund, branch, subject area, availability, and format.
     * Multi-value axes accept a comma-separated list, so a reader can tick
     * several boxes on one axis.
     */
    private function applyCanonicalFilters(
        Builder $builder,
        ?string $resourceType,
        ?string $fund,
        ?string $branch,
        ?string $category,
        ?string $availability,
        ?string $format,
    ): void {
        if (($types = $this->splitList($resourceType)) !== []) {
            $builder->whereIn('resource_type', $types);
        }

        if (($categories = $this->splitList($category)) !== []) {
            $builder->whereIn('category', $categories);
        }

        if (($funds = $this->splitList($fund)) !== []) {
            $builder->whereHas('copies', fn (Builder $copies) => $copies
                ->whereNotIn('status', ['written_off'])
                ->whereHas('fund', fn (Builder $related) => $related->whereIn('code', $funds)));
        }

        if (($branches = $this->splitList($branch)) !== []) {
            $builder->whereHas('copies', fn (Builder $copies) => $copies
                ->whereNotIn('status', ['written_off'])
                ->whereHas('branch', fn (Builder $related) => $related->whereIn('code', $branches)));
        }

        // §8.3 — availability is an aggregate over the record's copies, not a
        // column: a record is "available" when at least one copy is on shelf.
        $availabilityKey = mb_strtolower(trim((string) $availability));
        match ($availabilityKey) {
            'available' => $builder->whereHas('copies', fn (Builder $copies) => $copies->where('status', 'available')),
            'issued' => $builder
                ->whereHas('copies', fn (Builder $copies) => $copies->whereIn('status', ['issued', 'overdue']))
                ->whereDoesntHave('copies', fn (Builder $copies) => $copies->where('status', 'available')),
            'electronic_only' => $builder
                ->whereHas('electronicMaterials', fn (Builder $materials) => $materials->where('is_active', true))
                ->whereDoesntHave('copies', fn (Builder $copies) => $copies->whereNotIn('status', ['written_off', 'lost'])),
            'processing' => $builder->whereHas('copies', fn (Builder $copies) => $copies->where('status', 'in_processing')),
            'repair' => $builder->whereHas('copies', fn (Builder $copies) => $copies->where('status', 'under_repair')),
            default => null,
        };

        // §8.4 — print / electronic / hybrid, derived from what the record
        // actually holds rather than a stored flag.
        $formatKey = mb_strtolower(trim((string) $format));
        $hasPhysical = fn (Builder $copies) => $copies->whereNotIn('status', ['written_off', 'lost']);
        $hasDigital = fn (Builder $materials) => $materials->where('is_active', true);
        match ($formatKey) {
            'print' => $builder
                ->whereHas('copies', $hasPhysical)
                ->whereDoesntHave('electronicMaterials', $hasDigital),
            'electronic' => $builder
                ->whereHas('electronicMaterials', $hasDigital)
                ->whereDoesntHave('copies', $hasPhysical),
            'hybrid' => $builder
                ->whereHas('copies', $hasPhysical)
                ->whereHas('electronicMaterials', $hasDigital),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function splitList(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Real filter axes with live counts, so the sidebar can only ever offer
     * values that exist in the collection (Master.md §8.2). Every entry is
     * derived from the database — nothing here is a curated constant list.
     *
     * @return array<string, mixed>
     */
    public function facets(): array
    {
        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            $currentYear = (int) date('Y');

            return [
                'resource_types' => [], 'languages' => [], 'categories' => [],
                'funds' => [], 'branches' => [], 'udc' => [],
                'availability' => [], 'formats' => [],
                'years' => ['min' => $currentYear - 25, 'max' => $currentYear],
                'total' => 0,
            ];
        }

        $catalogRecords = fn (): Builder => BibliographicRecord::query();

        $countBy = fn (string $column): array => $catalogRecords()
            ->selectRaw("{$column} as value, count(*) as total")
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['value' => (string) $row->value, 'count' => (int) $row->total])
            ->all();

        return [
            'resource_types' => $countBy('resource_type'),
            'languages' => $this->languageFacet(),
            'categories' => $countBy('category'),
            'funds' => $this->holdingFacet('fund'),
            'branches' => $this->holdingFacet('branch'),
            'udc' => $this->udcFacet(),
            'availability' => $this->availabilityFacet(),
            'formats' => $this->formatFacet(),
            'years' => $this->yearBounds(),
            'total' => $catalogRecords()->count(),
        ];
    }

    /**
     * Every interface language is always listed, with a zero count when the
     * collection has nothing in it yet — the sidebar must not reshuffle as
     * records are catalogued.
     *
     * @return list<array{value: string, count: int}>
     */
    private function languageFacet(): array
    {
        $counts = BibliographicRecord::query()
            ->selectRaw('language, count(*) as total')
            ->groupBy('language')
            ->pluck('total', 'language');

        return collect(['ru', 'kk', 'en', 'other'])
            ->map(fn (string $code): array => ['value' => $code, 'count' => (int) ($counts[$code] ?? 0)])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, count: int}>
     */
    private function holdingFacet(string $relation): array
    {
        $table = $relation === 'fund' ? 'funds' : 'branches';

        return DB::table('book_copies')
            ->join($table, "{$table}.id", '=', "book_copies.{$relation}_id")
            ->join('bibliographic_records', 'bibliographic_records.id', '=', 'book_copies.bibliographic_record_id')
            ->whereNotIn('book_copies.status', ['written_off'])
            ->selectRaw("{$table}.code as value, {$table}.name as label, count(distinct bibliographic_records.id) as total")
            ->groupBy("{$table}.code", "{$table}.name")
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'value' => (string) $row->value,
                'label' => (string) $row->label,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Top-level UDC classes present in the collection, labelled from the
     * classifier so the sidebar shows "004 — Информационные технологии".
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    private function udcFacet(): array
    {
        $used = BibliographicRecord::query()
            ->whereNotNull('udc_code')
            ->pluck('udc_code');

        if ($used->isEmpty()) {
            return [];
        }

        $known = DatabaseSchema::hasTable('udc_codes')
            ? UdcCode::query()->get()->keyBy('code')
            : collect();

        // Roll a code up to its parent class only when the classifier actually
        // describes that parent — otherwise "159.9" would collapse to a bare
        // "159" with no label to show for it.
        $counts = $used->countBy(function (string $code) use ($known): string {
            $root = $this->udcRoot($code);

            return $known->has($root) || ! $known->has(trim($code)) ? $root : trim($code);
        });

        $labels = $known;

        return $counts
            ->map(fn (int $total, string $code): array => [
                'value' => $code,
                'label' => $labels->get($code)?->localizedDescription() ?? $code,
                'count' => $total,
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * The classifier's top-level class for a UDC code: "004.8" and "004" both
     * roll up to "004", "93/94" stays whole.
     */
    private function udcRoot(string $code): string
    {
        $code = trim($code);

        return str_contains($code, '.') ? explode('.', $code)[0] : $code;
    }

    /**
     * @return list<array{value: string, count: int}>
     */
    private function availabilityFacet(): array
    {
        $states = ['available', 'issued', 'electronic_only', 'processing', 'repair'];

        return collect($states)
            ->map(function (string $state): array {
                $builder = BibliographicRecord::query();
                $this->applyCanonicalFilters($builder, null, null, null, null, $state, null);

                return ['value' => $state, 'count' => $builder->count()];
            })
            ->all();
    }

    /**
     * @return list<array{value: string, count: int}>
     */
    private function formatFacet(): array
    {
        return collect(['print', 'electronic', 'hybrid'])
            ->map(function (string $format): array {
                $builder = BibliographicRecord::query();
                $this->applyCanonicalFilters($builder, null, null, null, null, null, $format);

                return ['value' => $format, 'count' => $builder->count()];
            })
            ->all();
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    private function emptyResult(int $page, int $limit): array
    {
        return [
            'data' => [],
            'meta' => [
                'page' => $page,
                'per_page' => $limit,
                'total' => 0,
                'total_pages' => 1,
                'totalPages' => 1,
            ],
        ];
    }

    /**
     * Publication-year range across the catalogue, for the year slider.
     *
     * @return array{min:int,max:int}
     */
    public function yearBounds(): array
    {
        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            $currentYear = (int) date('Y');

            return ['min' => $currentYear - 25, 'max' => $currentYear];
        }

        $bounds = BibliographicRecord::query()
            ->whereNotNull('publication_year')
            ->selectRaw('MIN(publication_year) as min_year, MAX(publication_year) as max_year')
            ->first();

        $min = (int) ($bounds->min_year ?? 0);
        $max = (int) ($bounds->max_year ?? 0);

        if ($min === 0 || $max === 0) {
            $currentYear = (int) date('Y');

            return ['min' => $currentYear - 25, 'max' => $currentYear];
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Presentation shape shared with the book-detail service and the SPA.
     *
     * @param  list<array<string, mixed>>  $locations
     * @return array<string, mixed>
     */
    public function present(
        BibliographicRecord $record,
        array $locations = [],
        array $popularRecordIds = [],
        bool $includeUdcCode = false,
    ): array {
        $available = (int) ($record->available_copies_count ?? $record->copies()->where('status', 'available')->count());
        $totalCopies = (int) ($record->physical_copies_count ?? $record->copies()->whereNotIn('status', ['written_off', 'lost'])->count());
        $digitalCount = (int) ($record->active_electronic_materials_count ?? 0);
        $availabilityStatus = match (true) {
            $available > 0 => 'available',
            (int) ($record->processing_copies_count ?? 0) > 0 => 'in_processing',
            (int) ($record->repair_copies_count ?? 0) > 0 => 'under_repair',
            (int) ($record->issued_copies_count ?? 0) > 0 => 'issued',
            default => 'no_holdings',
        };
        $format = match (true) {
            $totalCopies > 0 && $digitalCount > 0 => 'hybrid',
            $digitalCount > 0 || in_array($record->resource_type, ['ebook', 'digital_document'], true) => 'electronic',
            default => 'print',
        };
        $accessRestriction = match (true) {
            (int) ($record->limited_copies_count ?? 0) > 0 => 'limited',
            (int) ($record->reading_room_copies_count ?? 0) > 0 => 'reading_room',
            default => 'free',
        };
        $latestRegistrationDate = $record->latest_registration_date;
        $registeredAt = $latestRegistrationDate === null
            ? null
            : Carbon::parse($latestRegistrationDate);
        $isNewArrival = $registeredAt !== null
            && $registeredAt->betweenIncluded(now()->subDays(30)->startOfDay(), now()->endOfDay());
        $udcCode = trim((string) ($record->udc_code ?? ''));
        $udcDescription = $this->udcDescription($udcCode);

        return [
            'id' => (string) $record->getKey(),
            'title' => [
                'display' => (string) $record->title,
                'raw' => (string) $record->title,
                'subtitle' => (string) ($record->subtitle ?? ''),
            ],
            'primaryAuthor' => $record->primary_author,
            'authors' => $record->allAuthors(),
            'publisher' => [
                'name' => (string) ($record->publisher ?? ''),
            ],
            'publicationYear' => $record->publication_year,
            'language' => [
                'code' => (string) $record->language,
                'raw' => (string) $record->language,
            ],
            'isbn' => [
                'raw' => (string) ($record->isbn ?? ''),
            ],
            'resourceType' => (string) $record->resource_type,
            'annotation' => (string) ($record->annotation ?? ''),
            'keywords' => (array) ($record->keywords ?? []),
            'coverPath' => $record->cover_path,
            'copies' => [
                'available' => $available,
                'total' => $totalCopies,
            ],
            'availability' => [
                'locations' => $locations,
            ],
            'indicators' => [
                'availability' => $availabilityStatus,
                'format' => $format,
                'copySupply' => $available === 1 ? 'last_copy' : ($available > 1 ? 'in_stock' : 'absent'),
                'popular' => in_array((int) $record->getKey(), $popularRecordIds, true),
                'newArrival' => $isNewArrival,
                'accessRestriction' => $accessRestriction,
                'issueCount' => (int) ($record->total_issue_count ?? 0),
                'latestRegistrationDate' => $latestRegistrationDate,
            ],
            'classification' => $record->category !== null && $record->category !== ''
                ? [['id' => $record->category, 'label' => $record->category, 'sourceKind' => 'subject']]
                : [],
            'udc' => [
                'raw' => $includeUdcCode ? $udcCode : '',
                'description' => $udcDescription,
                'display' => $includeUdcCode
                    ? trim($udcCode.($udcDescription !== '' ? ' — '.$udcDescription : ''))
                    : $udcDescription,
                'source' => $udcCode !== '' ? 'udc' : '',
            ],
            'authorMark' => (string) ($record->author_mark ?? ''),
            'source' => 'catalog.bibliographic_records',
        ];
    }

    private function udcDescription(string $code): string
    {
        if ($code === '' || ! DatabaseSchema::hasTable('udc_codes')) {
            return '';
        }

        $reference = UdcCode::query()
            ->whereRaw('? LIKE code || ?', [$code, '%'])
            ->orderByRaw('LENGTH(code) DESC')
            ->first();

        return $reference?->localizedDescription() ?? '';
    }

    /**
     * Exact top decile by accumulated copy issue_count. IDs are cached briefly
     * because the same set is reused for all twelve cards in one response.
     *
     * @return list<int>
     */
    private function popularRecordIds(): array
    {
        return Cache::remember('catalog.popular_record_ids', now()->addMinutes(15), function (): array {
            $recordCount = BibliographicRecord::query()->count();
            if ($recordCount === 0) {
                return [];
            }

            return DB::table('book_copies')
                ->selectRaw('bibliographic_record_id, SUM(issue_count) AS issue_total')
                ->groupBy('bibliographic_record_id')
                ->havingRaw('SUM(issue_count) > 0')
                ->orderByDesc('issue_total')
                ->orderBy('bibliographic_record_id')
                ->limit(max(1, (int) ceil($recordCount * 0.10)))
                ->pluck('bibliographic_record_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
        });
    }

    private function applyTextFilters(
        Builder $builder,
        string $query,
        ?string $title,
        ?string $author,
        ?string $publisher,
        ?string $isbn,
        ?string $subject,
    ): void {
        if (($term = trim($query)) !== '') {
            $builder->search($term);
        }
        if (($value = trim((string) $title)) !== '') {
            $builder->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($value).'%']);
        }
        if (($value = trim((string) $author)) !== '') {
            $needle = '%'.mb_strtolower($value).'%';
            $builder->where(function (Builder $inner) use ($needle): void {
                $inner
                    ->whereRaw('LOWER(COALESCE(primary_author, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(CAST(additional_authors AS TEXT)) LIKE ?', [$needle]);
            });
        }
        if (($value = trim((string) $publisher)) !== '') {
            $builder->whereRaw('LOWER(COALESCE(publisher, \'\')) LIKE ?', ['%'.mb_strtolower($value).'%']);
        }
        if (($value = trim((string) $isbn)) !== '') {
            $normalized = preg_replace('/[^0-9xX]/', '', $value) ?: $value;
            $builder->where(function (Builder $inner) use ($value, $normalized): void {
                $inner
                    ->where('isbn', 'like', '%'.$value.'%')
                    ->orWhere('isbn', 'like', '%'.$normalized.'%');
            });
        }
        if (($value = trim((string) $subject)) !== '') {
            $needle = '%'.mb_strtolower($value).'%';
            $builder->where(function (Builder $inner) use ($needle, $value): void {
                $inner
                    ->whereRaw('LOWER(COALESCE(annotation, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(udc_code, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(CAST(keywords AS TEXT)) LIKE ?', [$needle])
                    ->orWhereJsonContains('keywords', $value);
            });
        }
    }

    private function applyFacets(
        Builder $builder,
        ?string $udc,
        ?string $language,
        ?int $yearFrom,
        ?int $yearTo,
        ?string $materialType,
        ?string $subjectId,
    ): void {
        if (($value = trim((string) $udc)) !== '') {
            // Prefix match so "33" also returns 330, 336, 338.
            $builder->where('udc_code', 'like', $value.'%');
        }
        if (($value = trim((string) $language)) !== '') {
            $builder->whereIn('language', $this->languageAliases($value));
        }
        if ($yearFrom !== null) {
            $builder->where('publication_year', '>=', $yearFrom);
        }
        if ($yearTo !== null) {
            $builder->where('publication_year', '<=', $yearTo);
        }
        // `materialType` carries the public format vocabulary (Master.md §8.4),
        // not the bibliographic resource type: печатный / электронный / гибрид.
        $format = mb_strtolower(trim((string) $materialType));
        if ($format === 'physical') {
            $builder->whereHas('copies', fn (Builder $copies) => $copies->whereNotIn('status', ['written_off', 'lost']));
        } elseif ($format === 'digital') {
            $builder->where(function (Builder $inner): void {
                $inner
                    ->whereHas('electronicMaterials', fn (Builder $materials) => $materials->where('is_active', true))
                    ->orWhereIn('resource_type', ['ebook', 'digital_document']);
            });
        } elseif ($format === 'archive') {
            $builder->whereIn('resource_type', ['dissertation', 'abstract', 'publication', 'periodical']);
        }

        if (($value = trim((string) $subjectId)) !== '') {
            // Accepts either a category slug or a UDC branch, since the
            // discover page deep-links by UDC while facets use categories.
            $builder->where(function (Builder $inner) use ($value): void {
                $inner->where('category', $value)->orWhere('udc_code', 'like', $value.'%');
            });
        }
    }

    private function applyCopyFilters(
        Builder $builder,
        bool $availableOnly,
        bool $physicalOnly,
        ?string $institution,
    ): void {
        $institutionKey = trim((string) $institution);
        $mapping = self::INSTITUTION_MAP[$institutionKey] ?? null;

        if ($availableOnly || $physicalOnly || $mapping !== null) {
            $builder->whereHas('copies', function (Builder $copies) use ($availableOnly, $mapping): void {
                if ($availableOnly) {
                    $copies->where('status', 'available');
                } else {
                    // "Physical holdings" means a copy that still belongs to
                    // the collection — written-off and lost items do not count.
                    $copies->whereNotIn('status', ['written_off', 'lost']);
                }

                if ($mapping !== null) {
                    $copies->where(function (Builder $scoped) use ($mapping): void {
                        if ($mapping['funds'] !== []) {
                            $scoped->orWhereHas('fund', fn (Builder $fund) => $fund->whereIn('code', $mapping['funds']));
                        }
                        if ($mapping['branches'] !== []) {
                            $scoped->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('code', $mapping['branches']));
                        }
                    });
                }
            });
        }
    }

    private function applySort(Builder $builder, string $sort): void
    {
        match (mb_strtolower($sort)) {
            'recently_added' => $builder
                ->orderByDesc(
                    BookCopy::query()
                        ->selectRaw('MAX(registration_date)')
                        ->whereColumn('book_copies.bibliographic_record_id', 'bibliographic_records.id'),
                )
                ->orderByDesc('created_at')
                ->orderBy('title'),
            'newest' => $builder->orderByDesc('publication_year')->orderBy('title'),
            'title' => $builder->orderBy('title'),
            'author' => $builder->orderByRaw('COALESCE(primary_author, \'\') ASC')->orderBy('title'),
            default => $builder->orderByDesc('available_copies_count')->orderBy('title'),
        };
    }

    /**
     * Holdings grouped per record, in the legacy institution/campus/service
     * point shape the catalogue UI renders.
     *
     * @param  list<int>  $recordIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadLocations(array $recordIds, ?string $institution = null): array
    {
        if ($recordIds === []) {
            return [];
        }

        $mapping = self::INSTITUTION_MAP[trim((string) $institution)] ?? null;

        $rows = BookCopy::query()
            ->whereIn('bibliographic_record_id', $recordIds)
            ->whereNotIn('status', ['written_off', 'lost'])
            ->when($mapping !== null, function (Builder $builder) use ($mapping): void {
                $builder->where(function (Builder $scoped) use ($mapping): void {
                    if ($mapping['funds'] !== []) {
                        $scoped->orWhereHas('fund', fn (Builder $fund) => $fund->whereIn('code', $mapping['funds']));
                    }
                    if ($mapping['branches'] !== []) {
                        $scoped->orWhereHas('branch', fn (Builder $branch) => $branch->whereIn('code', $mapping['branches']));
                    }
                });
            })
            ->with(['branch', 'fund'])
            ->get()
            ->groupBy('bibliographic_record_id');

        $result = [];

        foreach ($rows as $recordId => $copies) {
            /** @var Collection $copies */
            $grouped = $copies->groupBy(fn ($copy): string => ($copy->branch?->code ?? 'unassigned').'|'.($copy->fund?->code ?? ''));

            $locations = $grouped
                ->map(function (Collection $group): array {
                    $first = $group->first();
                    $branchCode = (string) ($first->branch?->code ?? '');
                    $fundCode = (string) ($first->fund?->code ?? '');
                    // The college collection is identified by its fund, since
                    // it has no dedicated service point of its own.
                    $servicePointCode = $fundCode === 'COLLEGE'
                        ? '3'
                        : (self::SERVICE_POINT_CODES[$branchCode] ?? $branchCode);

                    return [
                        'institutionUnit' => [
                            'code' => $fundCode === 'COLLEGE' ? 'college' : $fundCode,
                            'name' => (string) ($first->fund?->name ?? ''),
                        ],
                        'campus' => [
                            'code' => $fundCode === 'COLLEGE' ? 'college_main' : (self::CAMPUS_CODES[$branchCode] ?? $branchCode),
                            'name' => (string) ($first->branch?->name ?? ''),
                        ],
                        'servicePoint' => [
                            'code' => $servicePointCode,
                            'name' => (string) ($first->branch?->name ?? ''),
                        ],
                        // Public catalogue responses expose the fund/branch,
                        // never the exact shelf. Exact placement is added by
                        // BookDetailReadService only for signed-in readers.
                        'shelf' => '',
                        'copies' => [
                            'total' => $group->count(),
                            'available' => $group->where('status', 'available')->count(),
                        ],
                    ];
                })
                ->sortByDesc(fn (array $location): int => $location['copies']['available'])
                ->values()
                ->all();

            $result[(int) $recordId] = $locations;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function languageAliases(string $language): array
    {
        $normalized = mb_strtolower(trim($language));

        return match ($normalized) {
            'kk', 'kaz', 'kz', 'kazakh', 'қазақша' => ['kk'],
            'ru', 'rus', 'russian', 'русский' => ['ru'],
            'en', 'eng', 'english', 'английский' => ['en'],
            default => [$normalized],
        };
    }
}
