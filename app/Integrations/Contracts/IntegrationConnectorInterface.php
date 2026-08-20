<?php

namespace App\Integrations\Contracts;

use App\Models\Integration;

interface IntegrationConnectorInterface
{
    public function code(): string;

    /** @return list<string> */
    public function capabilities(): array;

    /** @return array{healthy:bool,latency_ms:int,error_code:?string} */
    public function healthCheck(Integration $integration): array;

    /** @return array<string,mixed> */
    public function pull(Integration $integration, array $context = []): array;

    /** @return array<string,mixed> */
    public function push(Integration $integration, array $payload, array $context = []): array;

    /** @return array<string,mixed> */
    public function reconcile(Integration $integration, array $context = []): array;
}
