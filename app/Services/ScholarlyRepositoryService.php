<?php

namespace App\Services;

use App\Models\Catalog\RepositoryItem;
use Illuminate\Support\Collection;

/**
 * Scholarly repository read service (PROJECT_CONTEXT 20).
 *
 * Compatibility read service for code that has not yet moved to the public
 * repository controller. The canonical source is repository_items; the old
 * config-backed catalogue must never drift into a second public data source.
 */
class ScholarlyRepositoryService
{
    public const TYPES = RepositoryItem::WORK_TYPES;

    /**
     * Published works, newest first, optionally filtered by work type.
     */
    public function list(?string $type = null): Collection
    {
        $works = RepositoryItem::query()->publicMetadata();

        if ($type !== null && in_array($type, RepositoryItem::acceptedWorkTypes(), true)) {
            $works->whereIn('work_type', RepositoryItem::equivalentWorkTypes($type));
        }

        return $works->orderByDesc('published_at')->orderByDesc('id')->get();
    }

    public function findBySlug(string $slug): ?array
    {
        if (! ctype_digit($slug)) {
            return null;
        }

        $work = RepositoryItem::query()->publicMetadata()->find((int) $slug);

        return $work?->toArray();
    }

    /**
     * Work-type counts for the published set (used by the filter chips).
     *
     * @return array<string, int>
     */
    public function typeCounts(): array
    {
        return RepositoryItem::query()
            ->publicMetadata()
            ->selectRaw('work_type, count(*) as total')
            ->groupBy('work_type')
            ->pluck('total', 'work_type')
            ->reduce(function (array $counts, int|string $total, string $type): array {
                $canonical = RepositoryItem::normaliseWorkType($type) ?? $type;
                $counts[$canonical] = ($counts[$canonical] ?? 0) + (int) $total;

                return $counts;
            }, []);
    }
}
