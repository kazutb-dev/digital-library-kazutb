<?php

namespace App\Services\ExternalResources;

use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use Illuminate\Http\Request;

class ExternalResourceAnalytics
{
    public function recordCardView(Request $request, ExternalResource $resource): void
    {
        $actorKey = $request->user() !== null
            ? 'user:'.$request->user()->getKey()
            : 'session:'.$this->anonymousSessionKey($request);
        $dedupeKey = hash('sha256', implode('|', [
            'external-resource-card-view',
            (string) $resource->getKey(),
            $actorKey,
            today('UTC')->toDateString(),
        ]));

        ExternalResourceEvent::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'external_resource_id' => $resource->getKey(),
                // Management reporting needs a role segment, not a durable
                // record of which named reader opened which research source.
                'user_id' => null,
                'event_type' => 'card_view',
                'role_name' => $this->roleName($request),
                'metadata' => [
                    'locale' => app()->getLocale(),
                    'source' => 'public_directory',
                    'resource_type' => $resource->resource_type,
                ],
                'retention_until' => $this->retentionUntil(),
            ],
        );
    }

    /** @param array<string, mixed> $metadata */
    public function recordAccess(
        Request $request,
        ExternalResource $resource,
        string $eventType,
        array $metadata,
    ): void {
        ExternalResourceEvent::query()->create([
            'external_resource_id' => $resource->getKey(),
            'user_id' => null,
            'event_type' => $eventType,
            'role_name' => $this->roleName($request),
            'metadata' => $metadata,
            'retention_until' => $this->retentionUntil(),
        ]);
    }

    public function pruneExpired(int $limit = 5000): int
    {
        $deleted = 0;
        ExternalResourceEvent::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now('UTC'))
            ->orderBy('id')
            ->limit(max(1, min($limit, 50_000)))
            ->pluck('id')
            ->chunk(500)
            ->each(function ($ids) use (&$deleted): void {
                $deleted += ExternalResourceEvent::query()->whereKey($ids->all())->delete();
            });

        return $deleted;
    }

    private function retentionUntil(): \DateTimeInterface
    {
        $days = max(1, min(
            3650,
            (int) config('digital_library.external_resource_analytics_retention_days', 395),
        ));

        return now('UTC')->addDays($days);
    }

    private function roleName(Request $request): string
    {
        $sessionUser = $request->hasSession()
            ? $request->session()->get('library.user')
            : null;

        return (string) ($request->user()?->getRoleNames()->first()
            ?? (is_array($sessionUser)
                ? ($sessionUser['canonical_role'] ?? $sessionUser['role'] ?? 'guest')
                : 'guest'));
    }

    private function anonymousSessionKey(Request $request): string
    {
        // The value is used only inside a second, date-scoped one-way dedupe
        // hash. Raw network data is never persisted in the analytics table.
        return hash_hmac('sha256', implode('|', [
            (string) $request->ip(),
            (string) ($request->header('User-Agent') ?: 'guest'),
        ]), (string) config('app.key'));
    }
}
