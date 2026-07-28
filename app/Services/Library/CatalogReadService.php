<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;

class CatalogReadService
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private const DEMO_CATALOG = [
        [
            'id' => 'demo-catalog-001',
            'title' => [
                'display' => 'Artificial Intelligence in Higher Education',
                'raw' => 'Artificial Intelligence in Higher Education',
                'subtitle' => 'Tools, ethics, and learning design for modern universities',
            ],
            'primaryAuthor' => 'A. Kurmanbayev',
            'publisher' => ['name' => 'KazUTB Press'],
            'publicationYear' => 2026,
            'language' => ['code' => 'en', 'raw' => 'English'],
            'isbn' => ['raw' => '9780134610993'],
            'copies' => ['available' => 4, 'total' => 6],
            'availability' => ['locations' => []],
            'classification' => [['id' => 'demo-udc-001', 'label' => '004.8 Artificial intelligence', 'sourceKind' => 'subject']],
            'udc' => ['raw' => '004.8', 'source' => 'subject'],
            'coverUrl' => '/images/news/ai-workshop.jpg',
            'source' => 'demo.catalog',
        ],
        [
            'id' => 'demo-catalog-002',
            'title' => [
                'display' => 'Data Science Methods for Research',
                'raw' => 'Data Science Methods for Research',
                'subtitle' => 'Practical workflows for analysis, visualization, and reporting',
            ],
            'primaryAuthor' => 'S. Tleubayeva',
            'publisher' => ['name' => 'Data Lab Editions'],
            'publicationYear' => 2024,
            'language' => ['code' => 'en', 'raw' => 'English'],
            'isbn' => ['raw' => '9781449373320'],
            'copies' => ['available' => 2, 'total' => 5],
            'availability' => ['locations' => []],
            'classification' => [['id' => 'demo-udc-002', 'label' => '519.2 Statistics', 'sourceKind' => 'subject']],
            'udc' => ['raw' => '519.2', 'source' => 'subject'],
            'coverUrl' => '/images/news/default-library.jpg',
            'source' => 'demo.catalog',
        ],
        [
            'id' => 'demo-catalog-003',
            'title' => [
                'display' => 'Современное академическое письмо',
                'raw' => 'Современное академическое письмо',
                'subtitle' => 'Структура текста, аргументация и стиль научной работы',
            ],
            'primaryAuthor' => 'Ж. Панкей',
            'publisher' => ['name' => 'University Methodics'],
            'publicationYear' => 2023,
            'language' => ['code' => 'ru', 'raw' => 'Русский'],
            'isbn' => ['raw' => '9781506386706'],
            'copies' => ['available' => 6, 'total' => 6],
            'availability' => ['locations' => []],
            'classification' => [['id' => 'demo-udc-003', 'label' => '808.02 Writing', 'sourceKind' => 'subject']],
            'udc' => ['raw' => '808.02', 'source' => 'subject'],
            'coverUrl' => '/images/news/classics-event.jpg',
            'source' => 'demo.catalog',
        ],
        [
            'id' => 'demo-catalog-004',
            'title' => [
                'display' => 'Экономические трансформации Казахстана',
                'raw' => 'Экономические трансформации Казахстана',
                'subtitle' => 'Анализ реформ, отраслей и цифровой адаптации',
            ],
            'primaryAuthor' => 'S. Tleubayeva',
            'publisher' => ['name' => 'KazUTB Press'],
            'publicationYear' => 2021,
            'language' => ['code' => 'ru', 'raw' => 'Русский'],
            'isbn' => ['raw' => '9781111111111'],
            'copies' => ['available' => 1, 'total' => 3],
            'availability' => [
                'locations' => [
                    [
                        'institutionUnit' => ['code' => 'college', 'name' => 'Колледж'],
                        'campus' => ['code' => 'college_main', 'name' => 'College Main'],
                        'servicePoint' => ['code' => '3', 'name' => 'Библиотека колледжа'],
                        'copies' => ['total' => 3, 'available' => 1],
                    ],
                ],
            ],
            'classification' => [['id' => 'demo-udc-004', 'label' => '330 Economics', 'sourceKind' => 'subject']],
            'udc' => ['raw' => '330', 'source' => 'subject'],
            'coverUrl' => '/images/news/campus-library.jpg',
            'source' => 'demo.catalog',
        ],
        [
            'id' => 'demo-catalog-005',
            'title' => [
                'display' => 'Тұрақты технологиялар және энергия',
                'raw' => 'Тұрақты технологиялар және энергия',
                'subtitle' => 'Инженерлік шешімдер для чистой инфраструктуры',
            ],
            'primaryAuthor' => 'A. Zhaksylyk',
            'publisher' => ['name' => 'Tech Campus'],
            'publicationYear' => 2025,
            'language' => ['code' => 'kk', 'raw' => 'Қазақша'],
            'isbn' => ['raw' => '9782222222222'],
            'copies' => ['available' => 3, 'total' => 4],
            'availability' => ['locations' => []],
            'classification' => [['id' => 'demo-udc-005', 'label' => '620 Engineering', 'sourceKind' => 'subject']],
            'udc' => ['raw' => '620', 'source' => 'subject'],
            'coverUrl' => '/images/news/author-visit.jpg',
            'source' => 'demo.catalog',
        ],
        [
            'id' => 'demo-catalog-006',
            'title' => [
                'display' => 'История Центральной Азии: архивы и карты',
                'raw' => 'История Центральной Азии: архивы и карты',
                'subtitle' => 'Источники, хронология и исследовательские заметки',
            ],
            'primaryAuthor' => 'Various contributors',
            'publisher' => ['name' => 'Heritage Archive'],
            'publicationYear' => 2019,
            'language' => ['code' => 'en', 'raw' => 'English'],
            'isbn' => ['raw' => '9783333333333'],
            'copies' => ['available' => 0, 'total' => 1],
            'availability' => ['locations' => []],
            'classification' => [['id' => 'demo-udc-006', 'label' => '94(5) Central Asia history', 'sourceKind' => 'subject']],
            'udc' => ['raw' => '94(5)', 'source' => 'subject'],
            'coverUrl' => '/images/news/default-library.jpg',
            'source' => 'demo.catalog',
        ],
    ];

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function search(
        string $query = '',
        ?string $title = null,
        ?string $author = null,
        ?string $publisher = null,
        ?string $isbn = null,
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
    ): array {
        $page = max($page, 1);
        $limit = min(max($limit, 1), 100);

        if (DB::getDriverName() === 'sqlite') {
            if ($includeTotal && $page === 1 && trim($query) === '') {
                return $this->demoCatalogResponse($page, $limit, $sort);
            }

            $totalPages = 1;

            return [
                'data' => [],
                'meta' => [
                    'page' => $page,
                    'per_page' => $limit,
                    'total' => 0,
                    'total_pages' => $totalPages,
                    'totalPages' => $totalPages,
                ],
            ];
        }

        try {
            $builder = DB::table('app.document_detail_v as d')
                ->select([
                    'd.document_id',
                    'd.legacy_doc_id',
                    'd.title_display',
                    'd.title_raw',
                    'd.subtitle_raw',
                    'd.isbn_normalized',
                    'd.isbn_raw',
                    'd.publication_year',
                    'd.language_code',
                    'd.language_raw',
                    'd.publisher_name',
                    'd.authors_json',
                    'd.copy_summary_json',
                    'd.subjects_json',
                    'd.raw_marc',
                ]);

            if ($query !== '') {
                $q = '%' . mb_strtolower($query) . '%';
                $builder->where(function ($inner) use ($q): void {
                    $inner
                        ->whereRaw("LOWER(COALESCE(d.title_display, d.title_raw, '')) LIKE ?", [$q])
                        ->orWhereRaw("LOWER(COALESCE(d.isbn_normalized, d.isbn_raw, '')) LIKE ?", [$q])
                        ->orWhereRaw("LOWER(COALESCE(d.publisher_name, '')) LIKE ?", [$q])
                        ->orWhereRaw("LOWER(COALESCE(d.authors_json::text, '')) LIKE ?", [$q]);
                });
            }

            if ($title !== null && trim($title) !== '') {
                $value = '%' . mb_strtolower(trim($title)) . '%';
                $builder->whereRaw("LOWER(COALESCE(d.title_display, d.title_raw, '')) LIKE ?", [$value]);
            }

            if ($author !== null && trim($author) !== '') {
                $value = '%' . mb_strtolower(trim($author)) . '%';
                $builder->whereRaw("LOWER(COALESCE(d.authors_json::text, '')) LIKE ?", [$value]);
            }

            if ($publisher !== null && trim($publisher) !== '') {
                $value = '%' . mb_strtolower(trim($publisher)) . '%';
                $builder->whereRaw("LOWER(COALESCE(d.publisher_name, '')) LIKE ?", [$value]);
            }

            if ($isbn !== null && trim($isbn) !== '') {
                $value = '%' . mb_strtolower(trim($isbn)) . '%';
                $builder->whereRaw("LOWER(COALESCE(d.isbn_normalized, d.isbn_raw, '')) LIKE ?", [$value]);
            }

            if ($udc !== null && trim($udc) !== '') {
                $value = '%' . mb_strtolower(trim($udc)) . '%';
                $builder->whereRaw("LOWER(COALESCE(d.raw_marc, '')::text) LIKE ?", [$value]);
            }

            if (!empty($language)) {
                $aliases = $this->resolveLanguageAliases($language);

                $builder->where(function ($query) use ($aliases): void {
                    $query->whereIn(DB::raw("LOWER(COALESCE(d.language_code, ''))"), $aliases);

                    foreach ($aliases as $alias) {
                        $query->orWhereRaw("LOWER(COALESCE(d.language_raw, '')) ~ ?", ['(^|[^[:alpha:]])' . preg_quote($alias, '/') . '([^[:alpha:]]|$)']);
                    }
                });
            }

            if ($yearFrom !== null) {
                $builder->where('d.publication_year', '>=', $yearFrom);
            }

            if ($yearTo !== null) {
                $builder->where('d.publication_year', '<=', $yearTo);
            }

            if ($availableOnly) {
                $builder->whereRaw("COALESCE((d.copy_summary_json->>'availableCopies')::int, 0) > 0");
            }

            if ($physicalOnly) {
                $builder->whereRaw("COALESCE((d.copy_summary_json->>'totalCopies')::int, 0) > 0");
            }

            if ($materialType !== null && $materialType !== '' && $materialType !== 'all') {
                $archiveMarkerSql = "(LOWER(COALESCE(d.subjects_json::text, '')) LIKE '%диссер%' OR LOWER(COALESCE(d.subjects_json::text, '')) LIKE '%thesis%' OR LOWER(COALESCE(d.subjects_json::text, '')) LIKE '%archive%')";

                if ($materialType === 'archive') {
                    $builder->whereRaw(
                        "({$archiveMarkerSql} OR (COALESCE((d.copy_summary_json->>'totalCopies')::int, 0) > 0 AND COALESCE((d.copy_summary_json->>'availableCopies')::int, 0) = 0))"
                    );
                } elseif ($materialType === 'physical') {
                    $builder
                        ->whereRaw("COALESCE((d.copy_summary_json->>'totalCopies')::int, 0) > 0")
                        ->whereRaw("COALESCE((d.copy_summary_json->>'availableCopies')::int, 0) > 0")
                        ->whereRaw("NOT {$archiveMarkerSql}");
                } elseif ($materialType === 'digital') {
                    $builder
                        ->whereRaw("COALESCE((d.copy_summary_json->>'totalCopies')::int, 0) = 0")
                        ->whereRaw("NOT {$archiveMarkerSql}");
                }
            }

            if ($subjectId !== null && $subjectId !== '') {
                $builder->whereExists(function ($sub) use ($subjectId): void {
                    $sub->select(DB::raw(1))
                        ->from('app.document_subjects as ds')
                        ->whereColumn('ds.document_id', 'd.document_id')
                        ->where('ds.subject_id', $subjectId);
                });
            }

            if ($institution !== null && $institution !== '') {
                $builder->whereExists(function ($sub) use ($institution): void {
                    $sub->select(DB::raw(1))
                        ->from('app.document_availability_by_location_v as loc')
                        ->whereColumn('loc.document_id', 'd.document_id');

                    if ($institution === 'college_library') {
                        $sub->where(function ($q): void {
                            $q->whereRaw("LOWER(COALESCE(loc.campus_code, '')) = ?", ['college_main'])
                                ->orWhereRaw("LOWER(COALESCE(loc.institution_unit_code, '')) = ?", ['college'])
                                ->orWhereRaw("LOWER(COALESCE(loc.service_point_code, '')) = ?", ['3']);
                        });
                    } elseif ($institution === 'economic_library') {
                        $sub->where(function ($q): void {
                            $q->whereRaw("LOWER(COALESCE(loc.campus_code, '')) = ?", ['university_economic'])
                                ->orWhereRaw("LOWER(COALESCE(loc.service_point_code, '')) = ?", ['1']);
                        });
                    } elseif ($institution === 'technology_library') {
                        $sub->where(function ($q): void {
                            $q->whereRaw("LOWER(COALESCE(loc.campus_code, '')) = ?", ['university_technological'])
                                ->orWhereRaw("LOWER(COALESCE(loc.service_point_code, '')) = ?", ['2']);
                        });
                    } elseif ($institution === 'ktslib') {
                        $sub->where(function ($q): void {
                            $q->whereRaw("LOWER(COALESCE(loc.service_point_code, '')) = ?", ['kstlib'])
                                ->orWhereRaw("LOWER(COALESCE(loc.campus_code, '')) = ?", ['university_central']);
                        });
                    }
                });
            }

            $total = $includeTotal ? (clone $builder)->count() : 0;

            $sortLower = mb_strtolower($sort);
            if ($sortLower === 'newest') {
                $builder->orderByDesc('d.publication_year')->orderBy('d.title_display');
            } elseif ($sortLower === 'title') {
                $builder->orderBy('d.title_display');
            } elseif ($sortLower === 'author') {
                $builder->orderByRaw('COALESCE((d.authors_json->0->>\'name\'), \'\') ASC')->orderBy('d.title_display');
            } else {
                $builder->orderByRaw('COALESCE((d.copy_summary_json->>\'availableCopies\')::int, 0) DESC')
                    ->orderBy('d.title_display');
            }

            $rows = $builder
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get();

            if ($includeTotal && $rows->isEmpty() && $this->shouldUseDemoFallback(
                query: $query,
                title: $title,
                author: $author,
                publisher: $publisher,
                isbn: $isbn,
                udc: $udc,
                language: $language,
                yearFrom: $yearFrom,
                yearTo: $yearTo,
                availableOnly: $availableOnly,
                physicalOnly: $physicalOnly,
                materialType: $materialType,
                subjectId: $subjectId,
                institution: $institution,
                page: $page,
            )) {
                return $this->demoCatalogResponse($page, $limit, $sort);
            }
        } catch (\Throwable $e) {
            if ($this->shouldUseDemoFallback(
                query: $query,
                title: $title,
                author: $author,
                publisher: $publisher,
                isbn: $isbn,
                udc: $udc,
                language: $language,
                yearFrom: $yearFrom,
                yearTo: $yearTo,
                availableOnly: $availableOnly,
                physicalOnly: $physicalOnly,
                materialType: $materialType,
                subjectId: $subjectId,
                institution: $institution,
                page: $page,
            )) {
                return $this->demoCatalogResponse($page, $limit, $sort);
            }

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

        $documentIds = $rows
            ->map(static fn (object $row): string => (string) ($row->document_id ?? ''))
            ->filter(static fn (string $id): bool => $id !== '')
            ->values()
            ->all();

        $locationsByDocument = $includeLocations ? $this->loadLocationsByDocument($documentIds, $institution) : [];

        $data = $rows->map(function (object $row) use ($locationsByDocument): array {
            $authors = $this->decodeJsonValue($row->authors_json);
            $copySummary = $this->decodeJsonValue($row->copy_summary_json);
            $subjects = $this->decodeJsonValue($row->subjects_json);
            $primaryAuthor = is_array($authors) && isset($authors[0]['name']) ? (string) $authors[0]['name'] : null;
            $documentId = (string) ($row->document_id ?? '');
            $languageCode = (string) ($row->language_code ?: '');
            $languageRaw = (string) ($row->language_raw ?: $languageCode ?: '');

            $available = is_array($copySummary) ? (int) ($copySummary['availableCopies'] ?? 0) : 0;
            $totalCopies = is_array($copySummary) ? (int) ($copySummary['totalCopies'] ?? 0) : 0;

            $classification = [];
            if (is_array($subjects) && count($subjects) > 0) {
                $classification = array_map(static fn (array $s): array => [
                    'id' => (string) ($s['id'] ?? ''),
                    'label' => (string) ($s['label'] ?? ''),
                    'sourceKind' => (string) ($s['sourceKind'] ?? ''),
                ], $subjects);
            }

            return [
                'id' => (string) ($row->document_id ?? $row->legacy_doc_id ?? ''),
                'title' => [
                    'display' => (string) ($row->title_display ?: $row->title_raw ?: 'Без названия'),
                    'raw' => (string) ($row->title_raw ?: $row->title_display ?: 'Без названия'),
                    'subtitle' => (string) ($row->subtitle_raw ?: ''),
                ],
                'primaryAuthor' => $primaryAuthor,
                'publisher' => [
                    'name' => (string) ($row->publisher_name ?: ''),
                ],
                'publicationYear' => $row->publication_year,
                'language' => [
                    'code' => $this->normalizeLanguageCode($languageCode, $languageRaw),
                    'raw' => $languageRaw,
                ],
                'isbn' => [
                    'raw' => (string) ($row->isbn_normalized ?: $row->isbn_raw ?: ''),
                ],
                'copies' => [
                    'available' => $available,
                    'total' => $totalCopies,
                ],
                'availability' => [
                    'locations' => $locationsByDocument[$documentId] ?? [],
                ],
                'classification' => $classification,
                'udc' => $this->extractUdcData($row->raw_marc ?? null, $classification),
                'source' => 'app.document_detail_v',
            ];
        })->all();

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
     * @return array{min:int,max:int}
     */
    public function yearBounds(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return ['min' => 1950, 'max' => (int) date('Y')];
        }

        $row = DB::table('app.document_detail_v')
            ->whereNotNull('publication_year')
            ->whereBetween('publication_year', [1900, 2100])
            ->selectRaw('MIN(publication_year) as min_year, MAX(publication_year) as max_year')
            ->first();

        $min = (int) ($row->min_year ?? 1950);
        $max = (int) ($row->max_year ?? (int) date('Y'));

        if ($min <= 0 || $max <= 0 || $min > $max) {
            return ['min' => 1950, 'max' => (int) date('Y')];
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function demoCatalogResponse(int $page, int $limit, string $sort): array
    {
        $items = $this->sortedDemoCatalog($sort);
        $total = count($items);
        $totalPages = max(1, $limit > 0 ? (int) ceil($total / $limit) : 1);
        $safePage = max($page, 1);

        return [
            'data' => array_slice($items, ($safePage - 1) * $limit, $limit),
            'meta' => [
                'page' => $safePage,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sortedDemoCatalog(string $sort): array
    {
        $items = self::DEMO_CATALOG;
        $sortLower = mb_strtolower($sort);

        usort($items, static function (array $left, array $right) use ($sortLower): int {
            $leftTitle = (string) ($left['title']['display'] ?? '');
            $rightTitle = (string) ($right['title']['display'] ?? '');
            $leftAuthor = (string) ($left['primaryAuthor'] ?? '');
            $rightAuthor = (string) ($right['primaryAuthor'] ?? '');
            $leftYear = (int) ($left['publicationYear'] ?? 0);
            $rightYear = (int) ($right['publicationYear'] ?? 0);
            $leftAvailable = (int) ($left['copies']['available'] ?? 0);
            $rightAvailable = (int) ($right['copies']['available'] ?? 0);

            return match ($sortLower) {
                'newest' => $rightYear <=> $leftYear ?: strcasecmp($leftTitle, $rightTitle),
                'title' => strcasecmp($leftTitle, $rightTitle),
                'author' => strcasecmp($leftAuthor, $rightAuthor) ?: strcasecmp($leftTitle, $rightTitle),
                default => $rightAvailable <=> $leftAvailable ?: strcasecmp($leftTitle, $rightTitle),
            };
        });

        return $items;
    }

    private function shouldUseDemoFallback(
        string $query,
        ?string $title,
        ?string $author,
        ?string $publisher,
        ?string $isbn,
        ?string $udc,
        ?string $language,
        ?int $yearFrom,
        ?int $yearTo,
        bool $availableOnly,
        bool $physicalOnly,
        ?string $materialType,
        ?string $subjectId,
        ?string $institution,
        int $page,
    ): bool {
        return $page === 1
            && trim($query) === ''
            && trim((string) $title) === ''
            && trim((string) $author) === ''
            && trim((string) $publisher) === ''
            && trim((string) $isbn) === ''
            && trim((string) $udc) === ''
            && trim((string) $language) === ''
            && $yearFrom === null
            && $yearTo === null
            && ! $availableOnly
            && ! $physicalOnly
            && (trim((string) $materialType) === '' || trim((string) $materialType) === 'all')
            && trim((string) $subjectId) === ''
            && trim((string) $institution) === '';
    }

    /**
     * @param array<int, array<string, string>> $classification
     * @return array{raw: string, source: string}
     */
    private function extractUdcData(mixed $rawMarc, array $classification = []): array
    {
        if (is_string($rawMarc) && $rawMarc !== '') {
            foreach (['080', '084'] as $tag) {
                $value = $this->extractMarcFieldValue($rawMarc, $tag);
                if ($value !== '') {
                    return ['raw' => $value, 'source' => $tag];
                }
            }
        }

        foreach ($classification as $item) {
            $kind = (string) ($item['sourceKind'] ?? '');
            $label = trim((string) ($item['label'] ?? ''));
            if ($label !== '' && in_array($kind, ['subject', 'specialization'], true)) {
                return ['raw' => $label, 'source' => $kind];
            }
        }

        return ['raw' => '', 'source' => ''];
    }

    private function extractMarcFieldValue(string $rawMarc, string $tag): string
    {
        $pattern = sprintf('/(?:^|\x1E)%s\s{0,2}([^\x1E]+)/u', preg_quote($tag, '/'));
        if (! preg_match($pattern, $rawMarc, $matches)) {
            return '';
        }

        $fieldData = (string) ($matches[1] ?? '');
        $subfields = preg_split('/\x1F/u', $fieldData) ?: [];
        $values = [];

        foreach ($subfields as $subfield) {
            $subfield = trim($subfield);
            if ($subfield === '') {
                continue;
            }

            $code = mb_substr($subfield, 0, 1);
            $value = trim(mb_substr($subfield, 1));
            if ($value === '') {
                continue;
            }

            if (in_array($code, ['a', 'x'], true)) {
                $values[] = preg_replace('/\s+/u', ' ', $value) ?: $value;
            }
        }

        if ($values !== []) {
            return trim(implode(' · ', array_unique($values)));
        }

        $normalized = preg_replace('/[\x1F\\]+/u', ' ', $fieldData);

        return trim(preg_replace('/\s+/u', ' ', $normalized ?: '') ?: '');
    }

    /**
     * @return array<int, string>
     */
    private function resolveLanguageAliases(string $language): array
    {
        $normalized = mb_strtolower(trim($language));

        return match ($normalized) {
            'ru', 'rus', 'russian' => ['ru', 'rus', 'russian'],
            'kk', 'kaz', 'kz', 'kazakh', 'қазақ' => ['kk', 'kaz', 'kz', 'kazakh', 'қазақ'],
            'en', 'eng', 'english' => ['en', 'eng', 'english'],
            default => [$normalized],
        };
    }

    private function normalizeLanguageCode(string $languageCode, string $languageRaw = ''): string
    {
        $candidates = [mb_strtolower(trim($languageCode)), mb_strtolower(trim($languageRaw))];

        foreach ($candidates as $candidate) {
            if (in_array($candidate, ['ru', 'rus', 'russian'], true)) {
                return 'ru';
            }

            if (in_array($candidate, ['kk', 'kaz', 'kz', 'kazakh', 'қазақ'], true)) {
                return 'kk';
            }

            if (in_array($candidate, ['en', 'eng', 'english'], true)) {
                return 'en';
            }
        }

        return $candidates[0] ?? '';
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decodeJsonValue(mixed $value): array|null
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int,string> $documentIds
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function loadLocationsByDocument(array $documentIds, ?string $institution = null): array
    {
        if ($documentIds === []) {
            return [];
        }

        $rowsQuery = DB::table('app.document_availability_by_location_v')
            ->select([
                'document_id',
                'institution_unit_name',
                'institution_unit_code',
                'campus_name',
                'campus_code',
                'service_point_name',
                'service_point_code',
                'total_copy_count',
                'available_copy_count',
            ])
            ->whereIn('document_id', $documentIds);

        if ($institution !== null && $institution !== '') {
            if ($institution === 'college_library') {
                $rowsQuery->where(function ($q): void {
                    $q->whereRaw("LOWER(COALESCE(campus_code, '')) = ?", ['college_main'])
                        ->orWhereRaw("LOWER(COALESCE(institution_unit_code, '')) = ?", ['college'])
                        ->orWhereRaw("LOWER(COALESCE(service_point_code, '')) = ?", ['3']);
                });
            } elseif ($institution === 'economic_library') {
                $rowsQuery->where(function ($q): void {
                    $q->whereRaw("LOWER(COALESCE(campus_code, '')) = ?", ['university_economic'])
                        ->orWhereRaw("LOWER(COALESCE(service_point_code, '')) = ?", ['1']);
                });
            } elseif ($institution === 'technology_library') {
                $rowsQuery->where(function ($q): void {
                    $q->whereRaw("LOWER(COALESCE(campus_code, '')) = ?", ['university_technological'])
                        ->orWhereRaw("LOWER(COALESCE(service_point_code, '')) = ?", ['2']);
                });
            } elseif ($institution === 'ktslib') {
                $rowsQuery->where(function ($q): void {
                    $q->whereRaw("LOWER(COALESCE(service_point_code, '')) = ?", ['kstlib'])
                        ->orWhereRaw("LOWER(COALESCE(campus_code, '')) = ?", ['university_central']);
                });
            }
        }

        $rows = $rowsQuery
            ->orderByDesc('available_copy_count')
            ->orderByDesc('total_copy_count')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $documentId = (string) ($row->document_id ?? '');
            if ($documentId === '') {
                continue;
            }

            $result[$documentId][] = [
                'institutionUnit' => [
                    'code' => (string) ($row->institution_unit_code ?? ''),
                    'name' => (string) ($row->institution_unit_name ?? ''),
                ],
                'campus' => [
                    'code' => (string) ($row->campus_code ?? ''),
                    'name' => (string) ($row->campus_name ?? ''),
                ],
                'servicePoint' => [
                    'code' => (string) ($row->service_point_code ?? ''),
                    'name' => (string) ($row->service_point_name ?? ''),
                ],
                'copies' => [
                    'total' => (int) ($row->total_copy_count ?? 0),
                    'available' => (int) ($row->available_copy_count ?? 0),
                ],
            ];
        }

        return $result;
    }
}
