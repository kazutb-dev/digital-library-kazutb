<?php

namespace App\Integrations\Support;

use InvalidArgumentException;

final class SafeEndpoint
{
    public function validate(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! in_array(mb_strtolower((string) ($parts['scheme'] ?? '')), ['https'], true) || isset($parts['user'], $parts['pass'])) {
            throw new InvalidArgumentException('unsafe_endpoint');
        }
        $host = mb_strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false || preg_match('/^[0-9.]+$/', $host)) {
            throw new InvalidArgumentException('unsafe_endpoint');
        }
        $port = (int) ($parts['port'] ?? 443);
        if ($port !== 443) {
            throw new InvalidArgumentException('unsafe_endpoint');
        }

        return 'https://'.$host.(string) ($parts['path'] ?? '');
    }
}
