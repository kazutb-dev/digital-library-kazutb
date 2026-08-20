<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\CatalogReadService;
use App\Services\Library\PublicPortalStatistics;
use Illuminate\Http\JsonResponse;

class LandingController extends Controller
{
    public function index(
        CatalogReadService $catalogReadService,
        PublicPortalStatistics $publicPortalStatistics,
    ): JsonResponse {
        $catalogPayload = $catalogReadService->search(
            limit: 6,
            sort: 'popular',
            includeTotal: true,
            includeLocations: false,
        );
        $books = is_array($catalogPayload['data'] ?? null) ? $catalogPayload['data'] : [];
        $truth = $publicPortalStatistics->snapshot();

        $stats = collect([
            ['source' => 'catalog_titles', 'label' => __('common.public_landing.stats.catalog_titles')],
            ['source' => 'physical_copies', 'label' => __('common.public_landing.stats.physical_copies')],
            ['source' => 'published_resources', 'label' => __('common.public_landing.stats.published_resources')],
        ])->map(function (array $definition) use ($truth): ?array {
            $value = $truth[$definition['source']] ?? null;

            return is_int($value) && $value > 0
                ? [
                    'value' => number_format($value, 0, '.', ' '),
                    'label' => $definition['label'],
                    'source' => $definition['source'],
                ]
                : null;
        })->filter()->values()->all();

        $stats[] = [
            'value' => '24/7',
            'label' => __('common.public_landing.stats.public_catalog_availability'),
            'source' => 'public_catalog_availability',
        ];

        $showcase = collect($books)->take(3)->map(static function (array $book): array {
            $title = trim((string) data_get($book, 'title.display')) ?: __('common.catalog.title_unknown');

            return [
                'title' => $title,
                'author' => trim((string) ($book['primaryAuthor'] ?? '')),
                'publication_year' => $book['publicationYear'] ?? null,
                'language' => (string) data_get($book, 'language.label', ''),
                'available_copies' => (int) data_get($book, 'copies.available', 0),
                'total_copies' => (int) data_get($book, 'copies.total', 0),
                'isbn' => (string) data_get($book, 'isbn.raw', ''),
                'url' => '/book/'.rawurlencode((string) (data_get($book, 'isbn.raw') ?: ($book['id'] ?? ''))),
            ];
        })->values()->all();

        return response()->json([
            'hero' => [
                'title' => __('common.public_landing.title'),
                'description' => __('common.public_landing.description'),
                'search' => [
                    'action' => '/catalog',
                    'fields' => ['title', 'author', 'isbn', 'udc'],
                ],
                'stats' => $stats,
            ],
            'catalog' => [
                'total' => (int) data_get($catalogPayload, 'meta.total', 0),
                'showcase' => $showcase,
            ],
            'content' => [
                'repository_published' => $truth['published_repository'] ?? null,
                'news_published' => $truth['published_news'] ?? null,
                'events_published' => $truth['published_events'] ?? null,
            ],
            'links' => [
                'catalog' => '/catalog',
                'resources' => '/resources',
                'repository' => '/repository',
                'news' => '/news',
                'events' => '/events',
                'contacts' => '/contacts',
            ],
        ]);
    }
}
