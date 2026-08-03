<?php

namespace Database\Seeders;

use App\Models\ExternalResource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
            $accessType = $resource['access_type'] ?? null;
            $instructions = $resource['access_instructions']
                ?? $resource['notes']
                ?? ($accessTypes[$accessType]['description'] ?? null);

            ExternalResource::withTrashed()->firstOrCreate(
                ['slug' => $resource['slug']],
                [
                    'title' => $resource['title'],
                    'resource_type' => $type,
                    'description' => $resource['description'],
                    'logo_path' => $resource['logo_path']
                        ?? $resource['logo']
                        ?? null,
                    'available_roles' => $resource['available_roles']
                        ?? $this->defaultRolesFor($type),
                    'license_expires_at' => $resource['expiry_date'] ?? null,
                    'is_active' => ($resource['status'] ?? 'active') !== 'inactive',
                    'access_instructions' => $instructions,
                    'url' => $resource['url'],
                    'provider' => $resource['provider'] ?? null,
                    'access_type' => $accessType,
                    'category' => $resource['category'] ?? null,
                    'sort_order' => ($position + 1) * 10,
                ]
            );
        }
    }

    private function resourceType(array $resource): string
    {
        $explicitType = $resource['resource_type'] ?? $resource['type'] ?? null;

        if (in_array($explicitType, ExternalResource::TYPES, true)) {
            return $explicitType;
        }

        return ($resource['access_type'] ?? null) === 'open'
            ? 'open'
            : 'licensed';
    }

    /**
     * @return list<string>
     */
    private function defaultRolesFor(string $type): array
    {
        return match ($type) {
            'open' => ['guest', 'member', 'librarian', 'admin'],
            'internal' => ['librarian', 'admin'],
            default => ['member', 'librarian', 'admin'],
        };
    }

    private function assertRequiredFields(array $resource, int|string $position): void
    {
        foreach (['slug', 'title', 'description', 'url'] as $field) {
            if (! isset($resource[$field]) || trim((string) $resource[$field]) === '') {
                throw new InvalidArgumentException(sprintf(
                    'External resource at position %s is missing required field [%s].',
                    (string) $position,
                    $field
                ));
            }
        }
    }
}
