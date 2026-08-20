<?php

namespace App\Integrations\Support;

use InvalidArgumentException;

final class WebhookSignatureVerifier
{
    public function verify(string $body, string $signature, int $timestamp, string $secret, int $windowSeconds = 300): void
    {
        if ($secret === '' || abs(time() - $timestamp) > max(30, min(900, $windowSeconds))) {
            throw new InvalidArgumentException('webhook_rejected');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        if (! hash_equals($expected, $provided)) {
            throw new InvalidArgumentException('webhook_rejected');
        }
    }
}
