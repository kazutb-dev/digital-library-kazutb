<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Scholarly repository read service (PROJECT_CONTEXT §20).
 *
 * Serves published scientific-work metadata for the public /repository
 * surfaces. Backed by config/repository_works.php for now; a future backend
 * phase replaces the config source with the scientific_works table while
 * keeping this API stable.
 */
class ScholarlyRepositoryService
{
    public const TYPES = [
        'bachelor_thesis',
        'master_dissertation',
        'phd_dissertation',
        'article',
        'report',
        'journal',
    ];

    /**
     * Published works, newest first, optionally filtered by work type.
     */
    public function list(?string $type = null): Collection
    {
        $works = collect(config('repository_works.works', []))
            ->where('status', 'published');

        if ($type !== null && in_array($type, self::TYPES, true)) {
            $works = $works->where('type', $type);
        }

        return $works
            ->sortByDesc('published_at')
            ->values();
    }

    public function findBySlug(string $slug): ?array
    {
        $work = collect(config('repository_works.works', []))
            ->where('status', 'published')
            ->firstWhere('slug', $slug);

        return is_array($work) ? $work : null;
    }

    /**
     * Work-type counts for the published set (used by the filter chips).
     *
     * @return array<string, int>
     */
    public function typeCounts(): array
    {
        return collect(config('repository_works.works', []))
            ->where('status', 'published')
            ->countBy('type')
            ->all();
    }
}
