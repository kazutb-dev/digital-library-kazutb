<?php

namespace App\Services;

use App\Models\ExternalResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class ExternalResourceService
{
    /**
     * Get all external resources, optionally filtered.
     *
     * @param  array{category?: string, resource_type?: string, access_type?: string, status?: string, audience?: string, content_type?: string, access_scope?: string}  $filters
     */
    public function list(array $filters = []): Collection
    {
        $audiences = $this->viewerAudiences();
        $resources = $this->source()
            ->reject(static fn (array $resource): bool => ($resource['status'] ?? 'inactive') === 'inactive')
            ->filter(static function (array $resource) use ($audiences): bool {
                $allowed = (array) ($resource['available_roles'] ?? []);

                return $allowed === [] || array_intersect($allowed, $audiences) !== [];
            });

        return $this->applyFilters($resources, $filters);
    }

    /**
     * Public directory cards intentionally describe every published resource,
     * even when a guest must sign in or visit campus to open it.  Drafts and
     * manually disabled resources remain private; an expired subscription is
     * retained so its status is transparent instead of silently disappearing.
     *
     * @param  array{category?: string, resource_type?: string, access_type?: string, status?: string, audience?: string, content_type?: string, access_scope?: string}  $filters
     */
    public function directory(array $filters = []): Collection
    {
        $resources = $this->source()
            ->reject(static fn (array $resource): bool => ($resource['status'] ?? 'inactive') === 'inactive');

        return $this->applyFilters($resources, $filters);
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

    /** A published record is discoverable even when opening it requires login or campus. */
    public function findPublicBySlug(string $slug): ?array
    {
        $resource = $this->directory()->firstWhere('slug', $slug);

        return $resource ?: null;
    }

    /**
     * Get available categories with labels.
     */
    public function categories(): array
    {
        $categories = (array) config('external_resources.categories', []);
        foreach ($categories as $key => &$category) {
            $category['label'] = __('external_resources.categories.'.$key);
        }
        unset($category);

        return $categories;
    }

    /**
     * Get available access types with labels.
     */
    public function accessTypes(): array
    {
        return $this->localisedOptions((array) config('external_resources.access_types', []), 'access_types');
    }

    public function resourceTypes(): array
    {
        return collect(ExternalResource::TYPES)
            ->mapWithKeys(static fn (string $type): array => [
                $type => ['label' => __('external_resources.types.'.$type)],
            ])
            ->all();
    }

    public function audiences(): array
    {
        return collect(ExternalResource::AUDIENCES)
            ->mapWithKeys(static fn (string $audience): array => [
                $audience => __('external_resources.audiences.'.$audience),
            ])->all();
    }

    public function contentTypes(): array
    {
        return collect(ExternalResource::CONTENT_TYPES)
            ->mapWithKeys(static fn (string $type): array => [
                $type => __('external_resources.content_types.'.$type),
            ])->all();
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
     * Public resources are database-backed. Configuration may decorate a
     * persisted record, but must never become a second source of licences.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function source(): Collection
    {
        $configuredResources = collect(config('external_resources.resources', []))
            ->keyBy('slug');
        $request = request();
        $user = $request->user();
        $requestIp = $request->ip();
        $onCampus = $requestIp !== null
            && IpUtils::checkIp($requestIp, (array) config('digital_access.campus_ranges', []));

        try {
            if (Schema::hasTable('external_resources')) {
                return ExternalResource::query()
                    ->ordered()
                    ->get()
                    ->map(static function (ExternalResource $resource) use ($configuredResources, $onCampus, $user): array {
                        $configured = $configuredResources->get($resource->slug, []);
                        $status = $resource->accessStatus(60);
                        $expiry = $resource->effectiveExpiryDate();
                        $logo = self::logoUrl($resource->logo_path, $configured['logo'] ?? null);
                        $canOpen = $resource->canOpen($user, $onCampus);
                        $accessDenial = match (true) {
                            $canOpen => null,
                            in_array($status, ['expired', 'inactive'], true)
                                || $resource->health_status === 'unavailable' => 'unavailable',
                            $resource->campus_only && ! $onCampus => 'campus',
                            $user === null => 'sign_in',
                            default => 'restricted',
                        };

                        return [
                            'id' => $resource->getKey(),
                            'slug' => $resource->slug,
                            'title' => self::resourceTranslation($resource, 'name', 'title'),
                            'provider' => $resource->provider,
                            'description' => self::resourceTranslation($resource, 'description', 'description'),
                            'resource_type' => $resource->resource_type,
                            'access_type' => $resource->access_type
                                ?: ($resource->resource_type === 'open_access' ? 'open' : 'remote_auth'),
                            'status' => $status,
                            'expiry_date' => $expiry?->toDateString(),
                            'contract_starts_at' => $resource->contract_starts_at?->toDateString(),
                            'contract_ends_at' => $resource->contract_ends_at?->toDateString(),
                            'url' => route('external-resources.open', $resource),
                            'detail_url' => route('resources.show', $resource->slug),
                            'category' => $resource->category ?: 'research_database',
                            'notes' => self::resourceTranslation($resource, 'instructions', 'instructions'),
                            'access_instructions' => self::resourceTranslation($resource, 'instructions', 'instructions'),
                            'available_roles' => $resource->normalisedAudiences(),
                            'content_types' => array_values((array) $resource->content_types),
                            'access_method' => $resource->access_method,
                            'guest_access' => (bool) $resource->guest_access,
                            'campus_only' => (bool) $resource->campus_only,
                            'login_required' => (bool) $resource->login_required,
                            'can_open' => $canOpen,
                            'access_denial' => $accessDenial,
                            'health_status' => in_array($resource->health_status, ExternalResource::HEALTH_STATUSES, true)
                                ? $resource->health_status
                                : 'unchecked',
                            'logo_path' => $resource->logo_path,
                            'logo' => $logo,
                        ];
                    })
                    ->values();
            }
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'External resources are temporarily unavailable.');
        }

        return collect();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resources
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $resources, array $filters): Collection
    {
        foreach (['category', 'access_type', 'resource_type', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $resources = $resources->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['audience'])) {
            $audience = (string) $filters['audience'];
            $resources = $resources->filter(static fn (array $resource): bool => in_array(
                $audience,
                (array) ($resource['available_roles'] ?? []),
                true,
            ));
        }

        if (! empty($filters['content_type'])) {
            $contentType = (string) $filters['content_type'];
            $resources = $resources->filter(static fn (array $resource): bool => in_array(
                $contentType,
                (array) ($resource['content_types'] ?? []),
                true,
            ));
        }

        if (! empty($filters['access_scope'])) {
            $scope = (string) $filters['access_scope'];
            $resources = $resources->filter(static fn (array $resource): bool => match ($scope) {
                'guest' => (bool) ($resource['guest_access'] ?? false),
                'authenticated' => (bool) ($resource['login_required'] ?? false),
                'campus' => (bool) ($resource['campus_only'] ?? false)
                    || ($resource['access_type'] ?? null) === 'campus',
                'remote' => ! (bool) ($resource['campus_only'] ?? false),
                default => true,
            });
        }

        return $resources->values();
    }

    /**
     * @return list<string>
     */
    private function viewerAudiences(): array
    {
        $user = Auth::user();
        if ($user !== null) {
            return ExternalResource::audiencesForUser($user);
        }

        $currentRequest = request();
        $session = $currentRequest?->hasSession()
            ? $currentRequest->session()->get('library.user')
            : null;
        if (is_array($session)) {
            $role = (string) ($session['canonical_role'] ?? $session['role'] ?? 'member');

            return in_array($role, ['admin', 'librarian', 'director', 'senior_librarian', 'staff'], true)
                ? ['library_staff']
                : ['student', 'teacher'];
        }

        return ['guest'];
    }

    /** @param array<string, array<string, mixed>> $options */
    private function localisedOptions(array $options, string $group): array
    {
        foreach ($options as $key => &$option) {
            $translated = __('external_resources.'.$group.'.'.$key.'.label');
            if ($translated !== 'external_resources.'.$group.'.'.$key.'.label') {
                $option['label'] = $translated;
            }
            $description = __('external_resources.'.$group.'.'.$key.'.description');
            if ($description !== 'external_resources.'.$group.'.'.$key.'.description') {
                $option['description'] = $description;
            }
        }
        unset($option);

        return $options;
    }

    private static function logoUrl(?string $storedPath, ?string $fallback): ?string
    {
        $path = trim((string) ($storedPath ?: $fallback));
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    private static function catalogueTranslation(string $slug, string $field, string $fallback): string
    {
        $key = 'external_resources.catalog.'.$slug.'.'.$field;
        $locale = app()->getLocale();

        return Lang::has($key, $locale, false)
            ? (string) trans($key, [], $locale)
            : $fallback;
    }

    private static function resourceTranslation(ExternalResource $resource, string $modelField, string $catalogueField): string
    {
        $translations = (array) $resource->{$modelField.'_translations'};
        $locale = app()->getLocale();
        $curated = trim((string) ($translations[$locale] ?? ''));

        return $curated !== ''
            ? $curated
            : self::catalogueTranslation($resource->slug, $catalogueField, $resource->translated($modelField));
    }

    /** @param array<string, mixed> $resource */
    private static function configuredStatus(array $resource): string
    {
        $type = (string) ($resource['resource_type'] ?? 'licensed');
        if (($resource['status'] ?? 'active') === 'inactive'
            || (($resource['publication_status'] ?? 'published') !== 'published')
            || ! ExternalResource::isSafeDestination((string) ($resource['url'] ?? ''), $type)) {
            return 'inactive';
        }

        $expiry = $resource['contract_ends_at'] ?? $resource['expiry_date'] ?? null;
        if (! is_string($expiry) || trim($expiry) === '') {
            return in_array($type, ['licensed', 'partner'], true) ? 'inactive' : 'active';
        }

        try {
            $date = CarbonImmutable::parse($expiry, 'UTC')->startOfDay();
        } catch (\Throwable) {
            return 'inactive';
        }

        if ($date->isBefore(today('UTC'))) {
            return 'expired';
        }

        return $date->betweenIncluded(today('UTC'), today('UTC')->addDays(60))
            ? 'expiring_soon'
            : 'active';
    }
}
