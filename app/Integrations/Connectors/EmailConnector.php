<?php

namespace App\Integrations\Connectors;

use App\Integrations\Contracts\OutboundConnector;
use App\Models\Integration;
use Illuminate\Support\Facades\Mail;

final class EmailConnector implements OutboundConnector
{
    public function code(): string
    {
        return 'email';
    }

    public function capabilities(): array
    {
        return ['delivery', 'health', 'retry'];
    }

    public function healthCheck(Integration $integration): array
    {
        $configured = config('mail.default') !== null;

        return ['healthy' => $configured, 'latency_ms' => 0, 'error_code' => $configured ? null : 'provider_not_configured'];
    }

    public function pull(Integration $integration, array $context = []): array
    {
        return ['status' => 'not_supported', 'items' => []];
    }

    public function push(Integration $integration, array $payload, array $context = []): array
    {
        if (! isset($payload['to'], $payload['subject'], $payload['body'])) {
            throw new \RuntimeException('payload_invalid');
        }
        Mail::raw((string) $payload['body'], fn ($message) => $message->to((string) $payload['to'])->subject((string) $payload['subject']));

        return ['status' => 'sent'];
    }

    public function reconcile(Integration $integration, array $context = []): array
    {
        return ['status' => 'not_applicable', 'mismatches' => []];
    }
}
