<?php

namespace App\Integrations\Connectors;

use App\Integrations\Contracts\IntegrationConnectorInterface;
use App\Models\Integration;

final class UnconfiguredConnector implements IntegrationConnectorInterface
{
    public function __construct(private readonly string $connectorCode = '*') {}

    public function code(): string
    {
        return $this->connectorCode;
    }

    public function capabilities(): array
    {
        return [];
    }

    public function healthCheck(Integration $integration): array
    {
        return ['healthy' => false, 'latency_ms' => 0, 'error_code' => 'provider_not_configured'];
    }

    public function pull(Integration $integration, array $context = []): array
    {
        return ['status' => 'awaiting_configuration', 'items' => []];
    }

    public function push(Integration $integration, array $payload, array $context = []): array
    {
        throw new \RuntimeException('provider_not_configured');
    }

    public function reconcile(Integration $integration, array $context = []): array
    {
        return ['status' => 'awaiting_configuration', 'mismatches' => []];
    }
}
