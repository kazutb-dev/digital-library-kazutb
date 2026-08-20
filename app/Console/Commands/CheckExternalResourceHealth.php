<?php

namespace App\Console\Commands;

use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use App\Services\ExternalResources\ExternalResourceNotificationOutboxService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CheckExternalResourceHealth extends Command
{
    protected $signature = 'library:external-resources:health-check
        {--resource= : Resource slug or numeric ID}
        {--dry-run : Perform checks without updating records or sending notifications}';

    protected $description = 'Check published external-resource landing URLs without logging in or following redirects';

    public function handle(ExternalResourceNotificationOutboxService $outbox): int
    {
        if (! $this->option('dry-run')) {
            $outbox->drain();
        }

        $query = ExternalResource::query()
            ->where('publication_status', 'published')
            ->where('is_active', true)
            ->whereNotNull('url');

        if ($selector = trim((string) $this->option('resource'))) {
            $query->where(static function ($builder) use ($selector): void {
                $builder->where('slug', $selector);
                if (ctype_digit($selector)) {
                    $builder->orWhereKey((int) $selector);
                }
            });
        }

        $checked = 0;
        $unavailable = 0;
        $query->ordered()->each(function (ExternalResource $resource) use ($outbox, &$checked, &$unavailable): void {
            if (! $resource->readyForDirectory()) {
                $this->warn($resource->slug.': skipped (not publication-ready)');

                return;
            }

            [$status, $statusCode, $reason] = $this->check($resource);
            $checked++;
            $unavailable += $status === 'unavailable' ? 1 : 0;
            $this->line(sprintf('%s: %s%s', $resource->slug, $status, $statusCode ? " ({$statusCode})" : ''));

            if ($this->option('dry-run')) {
                return;
            }

            $this->persistResult($resource, $status, $statusCode, $reason, $outbox);
        });

        if (! $this->option('dry-run')) {
            $outbox->drain();
        }

        $this->info("Checked {$checked}; unavailable {$unavailable}.");

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: int|null, 2: string|null} */
    private function check(ExternalResource $resource): array
    {
        $url = trim((string) ($resource->health_check_url ?: $resource->url));
        if (! ExternalResource::isSafeDestination($url, (string) $resource->resource_type)) {
            return ['degraded', null, 'unsafe_health_destination'];
        }
        if (! ExternalResource::isSafeHealthDestination($url, (string) $resource->resource_type)) {
            return ['degraded', null, 'credential_free_health_url_required'];
        }

        if ($resource->resource_type === 'internal') {
            try {
                $route = app('router')->getRoutes()->match(Request::create($url, 'GET'));
                if ($route->isFallback) {
                    return ['unavailable', null, 'internal_route_missing'];
                }

                return ['healthy', 200, null];
            } catch (Throwable) {
                return ['unavailable', null, 'internal_route_missing'];
            }
        }

        $addresses = $this->resolvePublicAddresses($url);
        if ($addresses === []) {
            return ['unavailable', null, 'host_not_public'];
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        // A configured HTTP(S) proxy would resolve the destination itself and
        // could bypass CURLOPT_RESOLVE. Health checks therefore always use a
        // direct connection in addition to the pinned address below.
        $options = ['allow_redirects' => false, 'cookies' => false, 'proxy' => null];
        if (defined('CURLOPT_NOPROXY')) {
            $options['curl'] = [CURLOPT_NOPROXY => '*'];
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            if (! defined('CURLOPT_RESOLVE')) {
                return ['degraded', null, 'dns_pinning_unavailable'];
            }
            $address = $addresses[0];
            $curlAddress = str_contains($address, ':') ? "[{$address}]" : $address;
            $options['curl'][CURLOPT_RESOLVE] = ["{$host}:443:{$curlAddress}"];
        }

        try {
            // The checked URL has no query credentials. DNS is resolved once,
            // validated and pinned while cURL keeps the original Host/SNI.
            $response = Http::timeout((int) config('digital_library.external_resource_health_timeout', 8))
                ->connectTimeout((int) config('digital_library.external_resource_health_connect_timeout', 4))
                ->withoutRedirecting()
                ->withOptions($options)
                ->withHeaders(['User-Agent' => 'KazUTB-Library-Link-Check/1.0'])
                ->head($url);

            return $this->classify($response);
        } catch (Throwable $exception) {
            // Never log the destination or the exception message: a malformed
            // historical URL may contain credentials despite validation.
            Log::warning('External-resource health request failed.', [
                'external_resource_id' => $resource->getKey(),
                'exception_class' => $exception::class,
            ]);

            return ['unavailable', null, 'request_failed'];
        }
    }

    /** @return array{0: string, 1: int, 2: string|null} */
    private function classify(Response $response): array
    {
        $code = $response->status();
        if ($code >= 200 && $code < 400) {
            return ['healthy', $code, null];
        }
        if (in_array($code, [401, 403, 405, 429], true)) {
            return ['degraded', $code, 'reachable_restricted_response'];
        }

        return ['unavailable', $code, 'unexpected_status'];
    }

    /** @return list<string> */
    private function resolvePublicAddresses(string $url): array
    {
        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false ? [$host] : [];
        }

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($address)) {
                continue;
            }
            if (filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false) {
                return [];
            }
            $addresses[] = $address;
        }

        return array_values(array_unique($addresses));
    }

    private function persistResult(
        ExternalResource $resource,
        string $status,
        ?int $statusCode,
        ?string $reason,
        ExternalResourceNotificationOutboxService $outbox,
    ): void {
        DB::transaction(function () use ($resource, $status, $statusCode, $reason, $outbox): void {
            $locked = ExternalResource::query()
                ->whereKey($resource->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $previousStatus = (string) ($locked->health_status ?: 'unchecked');
            $newIncident = $status === 'unavailable'
                && ($previousStatus !== 'unavailable' || $locked->health_incident_id === null);
            $incidentId = $status === 'unavailable'
                ? ($newIncident ? (string) Str::uuid() : $locked->health_incident_id)
                : null;

            $locked->forceFill([
                'health_status' => $status,
                'health_incident_id' => $incidentId,
                'health_incident_started_at' => $status === 'unavailable'
                    ? ($newIncident ? now('UTC') : $locked->health_incident_started_at)
                    : null,
                'last_checked_at' => now('UTC'),
            ])->save();

            ExternalResourceEvent::query()->create([
                'external_resource_id' => $locked->getKey(),
                'event_type' => 'health_check',
                'role_name' => 'system',
                'metadata' => array_filter([
                    'result' => $status,
                    'status_code' => $statusCode,
                    'reason' => $reason,
                    'incident_id' => $incidentId,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                'retention_until' => now('UTC')->addDays(max(1, min(
                    3650,
                    (int) config('digital_library.external_resource_analytics_retention_days', 395),
                ))),
            ]);

            if (! $newIncident || $incidentId === null) {
                return;
            }

            $dedupeKey = hash('sha256', implode('|', [
                'health_outage',
                (string) $locked->getKey(),
                $incidentId,
            ]));
            $payload = [
                'title' => $locked->title,
                'incident_id' => $incidentId,
                'detected_at' => $locked->health_incident_started_at?->toIso8601String(),
            ];
            ExternalResourceEvent::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'external_resource_id' => $locked->getKey(),
                    'event_type' => 'health_outage',
                    'role_name' => 'system',
                    'metadata' => $payload,
                ],
            );
            $outbox->enqueue($locked, 'health_outage', $dedupeKey, $payload);
        });
    }
}
