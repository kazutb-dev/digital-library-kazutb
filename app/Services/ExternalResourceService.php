<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ExternalResourceService
{
    /**
     * Get all external resources, optionally filtered.
     *
     * @param  array{category?: string, access_type?: string, status?: string}  $filters
     */
    public function list(array $filters = []): Collection
    {
        $resources = collect(config('external_resources.resources', []));

        if (! empty($filters['category'])) {
            $resources = $resources->where('category', $filters['category']);
        }

        if (! empty($filters['access_type'])) {
            $resources = $resources->where('access_type', $filters['access_type']);
        }

        if (! empty($filters['status'])) {
            $resources = $resources->where('status', $filters['status']);
        }

        return $resources->values();
    }

    /**
     * Get a single resource by slug.
     */
    public function findBySlug(string $slug): ?array
    {
        $resources = collect(config('external_resources.resources', []));

        $resource = $resources->firstWhere('slug', $slug);

        return $resource ?: null;
    }

    /**
     * Get available categories with labels.
     */
    public function categories(): array
    {
        return config('external_resources.categories', []);
    }

    /**
     * Get available access types with labels.
     */
    public function accessTypes(): array
    {
        return config('external_resources.access_types', []);
    }

    /**
     * Get only active resources (status != inactive).
     */
    public function listActive(array $filters = []): Collection
    {
        $filters['status'] = 'active';

        return $this->list($filters);
    }
}
