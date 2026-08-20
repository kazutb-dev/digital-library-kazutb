<?php

namespace App\Integrations\Connectors;

use App\Directory\ActiveDirectoryService;
use App\Integrations\Contracts\InboundConnector;
use App\Models\Integration;

final readonly class ActiveDirectoryConnector implements InboundConnector
{
    public function __construct(private ActiveDirectoryService $directory) {}

    public function code(): string
    {
        return 'active_directory';
    }

    public function capabilities(): array
    {
        return ['authentication', 'identity', 'health'];
    }

    public function healthCheck(Integration $integration): array
    {
        $health = $this->directory->health();

        return ['healthy' => $health->connected, 'latency_ms' => (int) round($health->latencyMs), 'error_code' => $health->errorCategory];
    }

    public function pull(Integration $integration, array $context = []): array
    {
        return ['status' => 'manual_identity_lookup_only', 'items' => []];
    }

    public function push(Integration $integration, array $payload, array $context = []): array
    {
        throw new \RuntimeException('capability_not_supported');
    }

    public function reconcile(Integration $integration, array $context = []): array
    {
        return ['status' => 'not_applicable', 'mismatches' => []];
    }
}
