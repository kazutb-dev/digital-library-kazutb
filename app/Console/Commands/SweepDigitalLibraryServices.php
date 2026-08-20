<?php

namespace App\Console\Commands;

use App\Models\Catalog\RepositoryItem;
use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use App\Services\AuditLogger;
use App\Services\ExternalResources\ExternalResourceNotificationOutboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SweepDigitalLibraryServices extends Command
{
    protected $signature = 'library:digital-services-sweep';

    protected $description = 'Publish due repository records and monitor external-resource licences';

    public function handle(
        AuditLogger $audit,
        ExternalResourceNotificationOutboxService $outbox,
    ): int {
        $this->sweepRepository($audit);
        $this->sweepLicences($outbox);
        $outbox->drain();

        return self::SUCCESS;
    }

    private function sweepRepository(AuditLogger $audit): void
    {
        RepositoryItem::query()->where('status', 'scheduled')->where('scheduled_for', '<=', now())
            ->each(function (RepositoryItem $item) use ($audit): void {
                DB::transaction(function () use ($item, $audit): void {
                    $locked = RepositoryItem::query()->whereKey($item)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'scheduled' || $locked->scheduled_for?->isFuture()) {
                        return;
                    }
                    if (! $locked->readyForPublicRelease()) {
                        return;
                    }
                    $status = $locked->embargoIsActive() ? 'embargoed' : 'published';
                    $locked->update(['status' => $status, 'published_at' => $status === 'published' ? now() : null]);
                    $audit->logRequired("repository.{$status}", 'repository_item', $locked->getKey(), oldValues: ['status' => 'scheduled'], newValues: ['status' => $status], scope: 'operational', actor: ['name' => 'Scheduler', 'role' => 'system']);
                });
            });

        RepositoryItem::query()->where('status', 'embargoed')->where('embargo_until', '<=', now())
            ->each(function (RepositoryItem $item) use ($audit): void {
                DB::transaction(function () use ($item, $audit): void {
                    $locked = RepositoryItem::query()->whereKey($item)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'embargoed' || $locked->embargoIsActive()) {
                        return;
                    }
                    if (! $locked->readyForPublicRelease()) {
                        return;
                    }
                    $locked->update(['status' => 'published', 'published_at' => now()]);
                    $audit->logRequired('repository.embargo_released', 'repository_item', $locked->getKey(), oldValues: ['status' => 'embargoed'], newValues: ['status' => 'published'], scope: 'operational', actor: ['name' => 'Scheduler', 'role' => 'system']);
                });
            });
    }

    private function sweepLicences(ExternalResourceNotificationOutboxService $outbox): void
    {
        $thresholds = collect((array) config('digital_library.external_resource_expiry_notice_days', []))
            ->filter(static fn (mixed $value): bool => is_int($value) || ctype_digit((string) $value))
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value >= 0 && $value <= 3650)
            ->unique()
            ->sort()
            ->values();
        ExternalResource::query()->whereIn('resource_type', ['licensed', 'partner'])
            ->where('publication_status', 'published')->where('is_active', true)
            ->whereRaw('COALESCE(contract_ends_at, license_expires_at) IS NOT NULL')
            ->each(function (ExternalResource $resource) use ($thresholds, $outbox): void {
                DB::transaction(function () use ($resource, $thresholds, $outbox): void {
                    $locked = ExternalResource::query()
                        ->whereKey($resource->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                    if ($locked->publication_status !== 'published' || ! $locked->is_active) {
                        return;
                    }

                    $expiry = $locked->effectiveExpiryDate();
                    if ($expiry === null) {
                        return;
                    }
                    $days = today('UTC')->diffInDays($expiry, false);
                    $threshold = $thresholds->first(
                        fn (int $candidate): bool => $days <= $candidate && $days >= 0,
                    );
                    if ($days < 0) {
                        $locked->update(['renewal_status' => 'expired']);
                        $threshold = -1;
                    }
                    if ($threshold === null) {
                        return;
                    }

                    $eventType = 'licence_notice_'.($threshold < 0 ? 'expired' : $threshold);
                    $expiryDate = $expiry->toDateString();
                    $dedupeKey = hash('sha256', implode('|', [
                        'licence_notice',
                        (string) $locked->getKey(),
                        $eventType,
                        $expiryDate,
                    ]));
                    $payload = [
                        'title' => $locked->title,
                        'days_remaining' => $days,
                        'expiry_date' => $expiryDate,
                        'threshold_days' => $threshold,
                    ];

                    ExternalResourceEvent::query()->firstOrCreate(
                        ['dedupe_key' => $dedupeKey],
                        [
                            'external_resource_id' => $locked->getKey(),
                            'event_type' => $eventType,
                            'role_name' => 'system',
                            'metadata' => $payload,
                        ],
                    );
                    $outbox->enqueue($locked, 'licence_expiry', $dedupeKey, $payload);
                });
            });
    }
}
