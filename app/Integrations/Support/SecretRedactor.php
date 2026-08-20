<?php

namespace App\Integrations\Support;

final class SecretRedactor
{
    private const SENSITIVE = [
        'authorization',
        'cookie',
        'api_key',
        'password',
        'passwd',
        'token',
        'secret',
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    public function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->sensitive($key)) {
            return '[REDACTED]';
        }
        if (! is_array($value)) {
            return is_string($value) ? preg_replace('/Bearer\s+[^\s]+/i', 'Bearer [REDACTED]', $value) : $value;
        }
        $redacted = [];
        foreach ($value as $itemKey => $item) {
            $redacted[$itemKey] = $this->redact($item, is_string($itemKey) ? $itemKey : null);
        }

        return $redacted;
    }

    private function sensitive(string $key): bool
    {
        $key = mb_strtolower(str_replace(['.', '-'], '_', $key));

        return collect(self::SENSITIVE)->contains(fn (string $needle): bool => str_contains($key, str_replace('-', '_', $needle)));
    }
}
