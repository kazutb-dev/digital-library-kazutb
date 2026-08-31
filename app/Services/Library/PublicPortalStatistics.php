<?php

namespace App\Services\Library;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\RepositoryItem;
use App\Models\ExternalResource;
use App\Models\News;
use App\Support\DatabaseSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PublicPortalStatistics
{
    /**
     * System-derived public figures. A null means that the source is not
     * available; callers must omit that figure instead of inventing one.
     *
     * @return array{catalog_titles:?int,physical_copies:?int,electronic_materials:?int,published_resources:?int,published_repository:?int,published_news:?int,published_events:?int}
     */
    public function snapshot(): array
    {
        $computed = null;
        $load = function () use (&$computed): array {
            $catalogTitles = DatabaseSchema::hasTable('bibliographic_records')
                ? BibliographicRecord::query()->count()
                : null;

            $physicalCopies = DatabaseSchema::hasTable('book_copies')
                ? BookCopy::query()->whereNotIn('status', ['lost', 'written_off'])->count()
                : null;

            $electronicMaterials = DatabaseSchema::hasTable('electronic_materials')
                ? ElectronicMaterial::query()->published()->count()
                : null;

            $publishedResources = DatabaseSchema::hasTable('external_resources')
                ? ExternalResource::query()
                    ->active()
                    ->get()
                    ->filter(static fn (ExternalResource $resource): bool => $resource->accessStatus() !== 'inactive')
                    ->count()
                : null;

            // Repository publication is a multi-table invariant. During a
            // rolling deployment the base table can exist before its approval
            // and version tables; omit the metric until the whole read model
            // is available instead of turning the public home page into a 500
            // and exposing a technical SQL error to staff or visitors.
            $repositoryReady = DatabaseSchema::hasTable('repository_items')
                && DatabaseSchema::hasTable('repository_approvals')
                && DatabaseSchema::hasTable('repository_item_versions');
            $publishedRepository = $repositoryReady
                ? RepositoryItem::query()->published()->publicMetadata()->count()
                : null;

            $publishedNews = null;
            $publishedEvents = null;
            if (DatabaseSchema::hasTable('news')) {
                $publicNews = News::query()
                    ->published()
                    ->when(Schema::hasColumn('news', 'visibility'), fn ($query) => $query->where('visibility', 'public'));
                $publishedNews = Schema::hasColumn('news', 'type')
                    ? (clone $publicNews)->whereNotIn('type', ['event', 'schedule'])->count()
                    : $publicNews->count();
                $publishedEvents = Schema::hasColumn('news', 'type')
                    ? (clone $publicNews)
                        ->whereIn('type', ['event', 'schedule'])
                        ->when(Schema::hasColumn('news', 'ends_at'), function ($query): void {
                            $query->where(function ($dates): void {
                                $dates->where('ends_at', '>=', now('UTC'));
                                if (Schema::hasColumn('news', 'starts_at')) {
                                    $dates->orWhere(function ($withoutEnd): void {
                                        $withoutEnd->whereNull('ends_at')->where('starts_at', '>=', now('UTC'));
                                    });
                                }
                            });
                        })
                        ->count()
                    : 0;
            }

            return $computed = [
                'catalog_titles' => $catalogTitles,
                'physical_copies' => $physicalCopies,
                'electronic_materials' => $electronicMaterials,
                'published_resources' => $publishedResources,
                'published_repository' => $publishedRepository,
                'published_news' => $publishedNews,
                'published_events' => $publishedEvents,
            ];
        };

        try {
            return Cache::remember('public.portal.statistics.v3', now()->addMinutes(5), $load);
        } catch (\Throwable $exception) {
            // Statistics remain truthful and available even if the configured
            // cache store is temporarily unwritable. Reuse the value already
            // calculated by Cache::remember instead of repeating the queries.
            report($exception);

            return $computed ?? $load();
        }
    }
}
