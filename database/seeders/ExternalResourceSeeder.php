<?php

namespace Database\Seeders;

use App\Models\ExternalResource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExternalResourceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $resources = config('external_resources.resources', []);
        $accessTypes = config('external_resources.access_types', []);

        foreach ($resources as $position => $resource) {
            $this->assertRequiredFields($resource, $position);

            $type = $this->resourceType($resource);
            $publicationStatus = (string) ($resource['publication_status']
                ?? (($resource['status'] ?? 'active') === 'inactive' ? 'draft' : 'published'));
            $isPublished = $publicationStatus === 'published';
            $accessType = $resource['access_type'] ?? null;
            $instructions = $resource['access_instructions']
                ?? $resource['notes']
                ?? ($accessTypes[$accessType]['description'] ?? null);

            $attributes = [
                ...(Schema::hasColumn('external_resources', 'public_id') ? ['public_id' => (string) Str::uuid()] : []),
                'slug' => $resource['slug'],
                'title' => $resource['title'],
                'resource_type' => $type,
                'description' => $resource['description'],
                // `logo` is a public/static fallback. `logo_path` is reserved
                // for administrator uploads on the public storage disk.
                'logo_path' => $resource['logo_path'] ?? null,
                'available_roles' => $resource['available_roles']
                    ?? $this->defaultRolesFor($type),
                'license_expires_at' => $resource['expiry_date'] ?? null,
                'is_active' => $isPublished && ($resource['status'] ?? 'active') !== 'inactive',
                'access_instructions' => $instructions,
                'url' => $resource['url'] ?? null,
                ...(Schema::hasColumn('external_resources', 'health_check_url') ? [
                    'health_check_url' => $resource['health_check_url'] ?? null,
                ] : []),
                'provider' => $resource['provider'] ?? null,
                'access_type' => $accessType,
                'category' => $resource['category'] ?? null,
                'sort_order' => ($position + 1) * 10,
                ...(Schema::hasColumn('external_resources', 'publication_status') ? [
                    'name_translations' => $resource['name_translations'] ?? null,
                    'short_description_translations' => $resource['short_description_translations'] ?? null,
                    'description_translations' => $resource['description_translations'] ?? null,
                    'instructions_translations' => $resource['instructions_translations'] ?? null,
                    'content_types' => array_values((array) ($resource['content_types'] ?? [])),
                    'access_method' => $resource['access_method'] ?? 'public_url',
                    'guest_access' => in_array('guest', $resource['available_roles'] ?? $this->defaultRolesFor($type), true),
                    'campus_only' => (bool) ($resource['campus_only'] ?? false),
                    'login_required' => (bool) ($resource['login_required'] ?? false),
                    'contract_starts_at' => $resource['contract_starts_at'] ?? null,
                    'contract_ends_at' => $resource['contract_ends_at'] ?? $resource['expiry_date'] ?? null,
                    'renewal_at' => $resource['renewal_at'] ?? null,
                    'publication_status' => $publicationStatus,
                    'renewal_status' => $type === 'open_access' || $type === 'internal' ? 'not_required' : 'pending',
                    'published_at' => $isPublished ? now() : null,
                ] : []),
            ];

            $existing = ExternalResource::withTrashed()->where('slug', $resource['slug'])->first();
            if ($existing?->trashed()) {
                // A deliberate admin deletion must never be undone by deploy.
                continue;
            }

            if ($existing === null) {
                ExternalResource::query()->create($attributes);

                continue;
            }

            // Enrich installations seeded before the expanded specification,
            // without overwriting values already curated by an administrator.
            $missing = [];
            foreach ([
                'name_translations', 'short_description_translations', 'description_translations',
                'instructions_translations', 'content_types', 'contract_starts_at',
                'contract_ends_at', 'renewal_at',
            ] as $field) {
                if (array_key_exists($field, $attributes)
                    && ($existing->{$field} === null || $existing->{$field} === '' || $existing->{$field} === [])) {
                    $missing[$field] = $attributes[$field];
                }
            }
            if ($missing !== []) {
                $existing->update($missing);
            }
        }
    }

    private function resourceType(array $resource): string
    {
        $explicitType = $resource['resource_type'] ?? $resource['type'] ?? null;

        if (in_array($explicitType, ExternalResource::TYPES, true)) {
            return $explicitType;
        }

        return ($resource['access_type'] ?? null) === 'open'
            ? (Schema::hasColumn('external_resources', 'publication_status') ? 'open_access' : 'open')
            : 'licensed';
    }

    /**
     * @return list<string>
     */
    private function defaultRolesFor(string $type): array
    {
        return match ($type) {
            'open', 'open_access', 'internal' => ExternalResource::AUDIENCES,
            default => ['student', 'teacher', 'library_staff'],
        };
    }

    private function assertRequiredFields(array $resource, int|string $position): void
    {
        foreach (['slug', 'title', 'description'] as $field) {
            if (! isset($resource[$field]) || trim((string) $resource[$field]) === '') {
                throw new InvalidArgumentException(sprintf(
                    'External resource at position %s is missing required field [%s].',
                    (string) $position,
                    $field
                ));
            }
        }

        $publicationStatus = (string) ($resource['publication_status']
            ?? (($resource['status'] ?? 'active') === 'inactive' ? 'draft' : 'published'));
        $url = trim((string) ($resource['url'] ?? ''));
        if ($publicationStatus === 'published' && $url === '') {
            throw new InvalidArgumentException(sprintf(
                'Published external resource at position %s is missing required field [url].',
                (string) $position,
            ));
        }

        $type = (string) ($resource['resource_type'] ?? 'licensed');
        if ($publicationStatus === 'published'
            && in_array($type, ['licensed', 'partner'], true)
            && empty($resource['contract_ends_at'])
            && empty($resource['expiry_date'])) {
            throw new InvalidArgumentException(sprintf(
                'Published %s resource at position %s requires a verified agreement end date.',
                $type,
                (string) $position,
            ));
        }

        if ($url !== '' && ! ExternalResource::isSafeDestination($url, $type)) {
            throw new InvalidArgumentException(sprintf(
                'External resource at position %s has an unsafe destination URL.',
                (string) $position,
            ));
        }

        $healthCheckUrl = trim((string) ($resource['health_check_url'] ?? ''));
        if ($healthCheckUrl !== '' && ! ExternalResource::isSafeHealthDestination($healthCheckUrl, $type)) {
            throw new InvalidArgumentException(sprintf(
                'External resource at position %s has an unsafe health-check URL.',
                (string) $position,
            ));
        }
    }
}
