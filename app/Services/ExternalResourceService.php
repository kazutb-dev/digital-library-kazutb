<?php

namespace App\Services;

use App\Models\ExternalResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ExternalResourceService
{
    /**
     * Get all external resources, optionally filtered.
     *
     * @param  array{category?: string, access_type?: string, status?: string}  $filters
     */
    public function list(array $filters = []): Collection
    {
        $roles = $this->viewerRoles();
        $resources = $this->source()
            ->filter(static fn (array $resource): bool => in_array(
                (string) ($resource['status'] ?? 'inactive'),
                ['active', 'expiring_soon'],
                true,
            ))
            ->filter(static function (array $resource) use ($roles): bool {
                $allowed = (array) ($resource['available_roles'] ?? []);

                return array_intersect($allowed, $roles) !== [];
            });

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
        $resources = $this->list();

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

    /**
     * Database-backed after the admin migration. The config catalogue is
     * available only to installations where the table has not been migrated;
     * an operational database failure must never resurrect stale licences.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function source(): Collection
    {
        $configuredResources = collect(config('external_resources.resources', []))
            ->keyBy('slug');

        try {
            if (Schema::hasTable('external_resources')) {
                return ExternalResource::query()
                    ->ordered()
                    ->get()
                    ->map(static function (ExternalResource $resource) use ($configuredResources): array {
                        $configured = $configuredResources->get($resource->slug, []);
                        $status = ! $resource->is_active
                            ? 'inactive'
                            : ($resource->license_expires_at?->isPast()
                                ? 'expired'
                                : ($resource->expiresSoon(60) ? 'expiring_soon' : 'active'));

                        return [
                            'id' => $resource->getKey(),
                            'slug' => $resource->slug,
                            'title' => $resource->title,
                            'provider' => $resource->provider,
                            'description' => $resource->description,
                            'resource_type' => $resource->resource_type,
                            'access_type' => $resource->access_type
                                ?: ($resource->resource_type === 'open' ? 'open' : 'remote_auth'),
                            'status' => $status,
                            'expiry_date' => $resource->license_expires_at?->toDateString(),
                            'url' => $resource->url,
                            'category' => $resource->category ?: 'research_database',
                            'notes' => $resource->access_instructions,
                            'available_roles' => $resource->available_roles,
                            'logo_path' => $resource->logo_path,
                            'logo' => $resource->logo_path ? null : ($configured['logo'] ?? null),
                        ];
                    })
                    ->values();
            }
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'External resources are temporarily unavailable.');
        }

        return $configuredResources->values()
            ->map(static function (array $resource): array {
                $type = ($resource['resource_type'] ?? null)
                    ?: (($resource['access_type'] ?? null) === 'open' ? 'open' : 'licensed');
                $resource['available_roles'] ??= $type === 'open'
                    ? ['guest', 'member', 'librarian', 'admin']
                    : ['member', 'librarian', 'admin'];
                $resource['status'] ??= 'active';

                return $resource;
            });
    }

    /**
     * @return list<string>
     */
    private function viewerRoles(): array
    {
        $user = Auth::user();
        if ($user !== null && method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames()->map(fn (mixed $role): string => (string) $role)->all();

            return $roles !== [] ? $roles : ['member'];
        }

        $currentRequest = request();
        $session = $currentRequest?->hasSession()
            ? $currentRequest->session()->get('library.user')
            : null;
        if (is_array($session)) {
            $role = (string) ($session['canonical_role'] ?? $session['role'] ?? 'member');

            return [$role === 'reader' ? 'member' : $role];
        }

        return ['guest'];
    }
}
