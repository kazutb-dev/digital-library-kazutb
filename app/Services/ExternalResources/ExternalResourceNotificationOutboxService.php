<?php

namespace App\Services\ExternalResources;

use App\Models\ExternalResource;
use App\Models\ExternalResourceNotificationOutbox;
use App\Models\User;
use App\Services\Catalog\LibraryNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExternalResourceNotificationOutboxService
{
    public function __construct(
        private readonly LibraryNotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $payload */
    public function enqueue(
        ExternalResource $resource,
        string $notificationType,
        string $dedupeKey,
        array $payload,
    ): ExternalResourceNotificationOutbox {
        return ExternalResourceNotificationOutbox::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'external_resource_id' => $resource->getKey(),
                'notification_type' => $notificationType,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now('UTC'),
            ],
        );
    }

    public function drain(int $limit = 100): int
    {
        $staleBefore = now('UTC')->subMinutes(15);
        $ids = ExternalResourceNotificationOutbox::query()
            ->whereNull('processed_at')
            ->where('available_at', '<=', now('UTC'))
            ->where(function ($query) use ($staleBefore): void {
                $query->where('status', '!=', 'processing')
                    ->orWhereNull('locked_at')
                    ->orWhere('locked_at', '<=', $staleBefore);
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 1000)))
            ->pluck('id');

        $processed = 0;
        foreach ($ids as $id) {
            $outbox = $this->claim((int) $id);
            if ($outbox === null) {
                continue;
            }

            try {
                $this->deliver($outbox);
                $outbox->forceFill([
                    'status' => 'delivered',
                    'processed_at' => now('UTC'),
                    'locked_at' => null,
                    'last_error' => null,
                ])->save();
                $processed++;
            } catch (Throwable $exception) {
                $this->releaseForRetry($outbox, $exception::class);
                Log::warning('External-resource notification outbox delivery failed.', [
                    'outbox_id' => $outbox->getKey(),
                    'notification_type' => $outbox->notification_type,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        return $processed;
    }

    private function claim(int $id): ?ExternalResourceNotificationOutbox
    {
        return DB::transaction(function () use ($id): ?ExternalResourceNotificationOutbox {
            $outbox = ExternalResourceNotificationOutbox::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();
            if ($outbox === null || $outbox->processed_at !== null) {
                return null;
            }
            if ($outbox->status === 'processing'
                && $outbox->locked_at?->isAfter(now('UTC')->subMinutes(15))) {
                return null;
            }

            $outbox->forceFill([
                'status' => 'processing',
                'attempts' => ((int) $outbox->attempts) + 1,
                'locked_at' => now('UTC'),
            ])->save();

            return $outbox->refresh();
        });
    }

    private function deliver(ExternalResourceNotificationOutbox $outbox): void
    {
        $resource = ExternalResource::query()->find($outbox->external_resource_id);
        if ($resource === null) {
            return;
        }

        $payload = (array) $outbox->payload;
        $recipients = $this->recipients($outbox->notification_type, $resource);
        if ($recipients->isEmpty()) {
            throw new RuntimeException('No active recipients are configured.');
        }

        foreach ($recipients as $recipient) {
            if ($outbox->notification_type === 'licence_expiry') {
                $this->notifications->sendLocalized(
                    $recipient,
                    'external_resource_licence',
                    'digital.external.licence_notice_title',
                    'digital.external.licence_notice_body',
                    [
                        'title' => (string) ($payload['title'] ?? $resource->title),
                        'days' => (int) ($payload['days_remaining'] ?? 0),
                    ],
                    [
                        'external_resource_id' => $resource->getKey(),
                        'expiry_date' => $payload['expiry_date'] ?? null,
                        'threshold_days' => $payload['threshold_days'] ?? null,
                        'outbox_key' => $outbox->dedupe_key,
                    ],
                );

                continue;
            }

            if ($outbox->notification_type === 'health_outage') {
                $this->notifications->sendLocalized(
                    $recipient,
                    'external_resource_health',
                    'digital.external.health_outage_title',
                    'digital.external.health_outage_body',
                    ['title' => (string) ($payload['title'] ?? $resource->title)],
                    [
                        'external_resource_id' => $resource->getKey(),
                        'incident_id' => $payload['incident_id'] ?? null,
                        'outbox_key' => $outbox->dedupe_key,
                    ],
                );
            }
        }
    }

    /** @return Collection<int, User> */
    private function recipients(string $notificationType, ExternalResource $resource): Collection
    {
        if ($notificationType === 'health_outage') {
            return User::role('admin')->where('is_active', true)->get()->unique('id')->values();
        }

        if ($notificationType === 'licence_expiry') {
            $recipients = User::role('director')->where('is_active', true)->get();
            $responsible = $resource->responsibleUser()->where('is_active', true)->first();
            if ($responsible !== null) {
                $recipients->push($responsible);
            }

            return $recipients->unique('id')->values();
        }

        return new Collection;
    }

    private function releaseForRetry(ExternalResourceNotificationOutbox $outbox, string $reason): void
    {
        $delayMinutes = min(1440, 5 * (2 ** min((int) $outbox->attempts, 8)));
        $outbox->forceFill([
            'status' => 'failed',
            'available_at' => now('UTC')->addMinutes($delayMinutes),
            'locked_at' => null,
            'last_error' => mb_substr($reason, 0, 255),
        ])->save();
    }
}
