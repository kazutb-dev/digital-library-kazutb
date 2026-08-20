<?php

namespace App\Integrations\Transport;

use App\Integrations\Support\SafeEndpoint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final readonly class HttpTransport
{
    public function __construct(private SafeEndpoint $endpoints) {}

    /**
     * Send a TLS-verified request while pinning the already validated public
     * DNS result. Redirects and ambient HTTP proxies are disabled.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function send(string $method, string $url, array $headers = [], array $payload = [], int $timeout = 10): Response
    {
        $safeUrl = $this->endpoints->validate($url);
        $host = (string) parse_url($safeUrl, PHP_URL_HOST);
        $addresses = $this->publicAddresses($host);
        $pinned = $addresses[0];
        $resolveAddress = str_contains($pinned, ':') ? '['.$pinned.']' : $pinned;

        return Http::withHeaders($headers)
            ->timeout(max(1, min(30, $timeout)))
            ->connectTimeout(5)
            ->withOptions([
                'allow_redirects' => false,
                'cookies' => false,
                'proxy' => null,
                'verify' => true,
                'curl' => [
                    CURLOPT_NOPROXY => '*',
                    CURLOPT_RESOLVE => [sprintf('%s:443:%s', $host, $resolveAddress)],
                ],
            ])->send(mb_strtoupper($method), $safeUrl, ['json' => $payload]);
    }

    /** @return list<string> */
    private function publicAddresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = collect($records ?: [])->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)->filter()->values()->all();
        if ($addresses === []) {
            throw new InvalidArgumentException('endpoint_dns_unavailable');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('unsafe_endpoint');
            }
        }

        return $addresses;
    }
}
