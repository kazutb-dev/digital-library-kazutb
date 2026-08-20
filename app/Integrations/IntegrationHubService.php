<?php

namespace App\Integrations;

use App\Models\Integration;
use App\Models\IntegrationConflict;
use App\Models\IntegrationInboxMessage;
use App\Models\IntegrationOutboxMessage;
use App\Models\IntegrationSyncRun;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class IntegrationHubService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private IntegrationConnectorRegistry $connectors, private AuditLogger $audit) {}

    public function healthCheck(Integration $integration, ?User $actor): array
    {
        $result = $this->connectors->for($integration->code)->healthCheck($integration);
        $integration->forceFill([
            'health_status' => $result['healthy'] ? 'healthy' : 'unavailable',
            'status' => $result['healthy'] && $integration->enabled ? 'healthy' : ($integration->enabled ? 'unavailable' : $integration->status),
            'last_health_check_at' => now('UTC'), 'last_latency_ms' => $result['latency_ms'],
            'last_success_at' => $result['healthy'] ? now('UTC') : $integration->last_success_at,
            'last_failure_at' => $result['healthy'] ? $integration->last_failure_at : now('UTC'),
            'consecutive_failures' => $result['healthy'] ? 0 : $integration->consecutive_failures + 1,
            'updated_by' => $actor?->id,
        ])->save();
        $this->audit->logRequired(
            actionType: 'integration.health_checked',
            entityType: 'integration',
            entityId: (string) $integration->id,
            newValues: ['health_status' => $integration->health_status, 'error_code' => $result['error_code'], 'latency_ms' => $result['latency_ms']],
            scope: 'system',
            actor: $actor,
        );

        return $result;
    }

    public function setEnabled(Integration $integration, bool $enabled, User $actor): void
    {
        if ($enabled && ! in_array($integration->code, $this->connectors->connectedCodes(), true)) {
            throw ValidationException::withMessages(['enabled' => 'integration_provider_not_configured']);
        }
        $integration->forceFill(['enabled' => $enabled, 'status' => $enabled ? 'configured' : 'disabled', 'updated_by' => $actor->id])->save();
        $this->audit->logRequired(
            actionType: $enabled ? 'integration.enabled' : 'integration.disabled',
            entityType: 'integration',
            entityId: (string) $integration->id,
            newValues: ['enabled' => $enabled],
            scope: 'system',
            actor: $actor,
        );
    }

    public function receive(Integration $integration, string $externalId, string $event, array $payload, string $correlationId): IntegrationInboxMessage
    {
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        try {
            $message = IntegrationInboxMessage::query()->create(['integration_id' => $integration->id, 'external_message_id' => $externalId, 'event_type' => $event, 'payload_hash' => $hash, 'payload_safe' => $payload, 'received_at' => now('UTC'), 'status' => 'pending', 'correlation_id' => $correlationId]);
            $this->audit->logRequired(
                actionType: 'integration.message_received',
                entityType: 'integration_inbox_message',
                entityId: (string) $message->id,
                newValues: ['integration_id' => $integration->id, 'event_type' => $event, 'payload_hash' => $hash, 'correlation_id' => $correlationId],
                scope: 'system',
            );

            return $message;
        } catch (UniqueConstraintViolationException) {
            return IntegrationInboxMessage::query()
                ->where('integration_id', $integration->id)
                ->where('external_message_id', $externalId)
                ->firstOrFail();
        }
    }

    public function queue(Integration $integration, string $aggregateType, string $aggregateId, string $event, array $payload, string $key, string $destination): IntegrationOutboxMessage
    {
        return IntegrationOutboxMessage::query()->firstOrCreate(
            ['integration_id' => $integration->id, 'idempotency_key' => $key],
            ['aggregate_type' => $aggregateType, 'aggregate_id' => $aggregateId, 'event_type' => $event, 'payload_safe' => $payload, 'destination' => $destination, 'status' => 'pending', 'next_attempt_at' => now('UTC'), 'correlation_id' => (string) Str::uuid()],
        );
    }

    public function deliver(IntegrationOutboxMessage $message): void
    {
        $claimed = DB::transaction(function () use ($message): ?IntegrationOutboxMessage {
            $candidate = IntegrationOutboxMessage::query()->lockForUpdate()->findOrFail($message->id);
            if (! in_array($candidate->status, ['pending', 'failed'], true) || ($candidate->next_attempt_at?->isFuture() ?? false)) {
                return null;
            }
            $candidate->forceFill(['status' => 'processing', 'locked_at' => now('UTC'), 'attempts' => $candidate->attempts + 1])->save();

            return $candidate;
        });

        if ($claimed === null) {
            return;
        }

        try {
            $integration = Integration::query()->findOrFail($claimed->integration_id);
            $this->connectors->for($integration->code)->push($integration, $claimed->payload_safe ?? [], ['correlation_id' => $claimed->correlation_id]);
            $claimed->forceFill(['status' => 'sent', 'sent_at' => now('UTC'), 'locked_at' => null, 'error_code' => null])->save();
            $this->audit->logRequired(
                actionType: 'integration.message_sent',
                entityType: 'integration_outbox_message',
                entityId: (string) $claimed->id,
                newValues: ['integration_id' => $claimed->integration_id, 'event_type' => $claimed->event_type, 'correlation_id' => $claimed->correlation_id],
                scope: 'system',
            );
        } catch (\Throwable) {
            $dead = $claimed->attempts >= self::MAX_ATTEMPTS;
            $delay = min(3600, 30 * (2 ** max(0, $claimed->attempts - 1)));
            $claimed->forceFill(['status' => $dead ? 'dead_letter' : 'failed', 'next_attempt_at' => $dead ? null : now('UTC')->addSeconds($delay), 'locked_at' => null, 'error_code' => 'delivery_failed'])->save();
            $this->audit->logRequired(
                actionType: $dead ? 'integration.dead_lettered' : 'integration.message_failed',
                entityType: 'integration_outbox_message',
                entityId: (string) $claimed->id,
                newValues: ['integration_id' => $claimed->integration_id, 'event_type' => $claimed->event_type, 'error_code' => 'delivery_failed', 'attempts' => $claimed->attempts],
                scope: 'system',
            );
        }
    }

    public function retry(IntegrationOutboxMessage $message, ?User $actor = null): void
    {
        $message->forceFill(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now('UTC'), 'locked_at' => null, 'error_code' => null])->save();
        $this->audit->logRequired(
            actionType: 'integration.retried',
            entityType: 'integration_outbox_message',
            entityId: (string) $message->id,
            newValues: ['integration_id' => $message->integration_id],
            scope: 'system',
            actor: $actor,
        );
    }

    public function dryRun(Integration $integration, User $actor): IntegrationSyncRun
    {
        $run = IntegrationSyncRun::query()->create(['uuid' => (string) Str::uuid(), 'integration_id' => $integration->id, 'type' => 'dry_run', 'started_at' => now('UTC'), 'status' => 'running', 'started_by' => $actor->id]);
        $result = $this->connectors->for($integration->code)->pull($integration, ['dry_run' => true]);
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        $run->forceFill(['completed_at' => now('UTC'), 'status' => ($result['status'] ?? '') === 'awaiting_configuration' ? 'configuration_required' : 'completed', 'received' => count($items)])->save();
        $this->audit->logRequired(
            actionType: 'integration.sync_completed',
            entityType: 'integration_sync_run',
            entityId: (string) $run->id,
            newValues: ['type' => 'dry_run', 'status' => $run->status],
            scope: 'system',
            actor: $actor,
        );

        return $run;
    }

    public function startSync(Integration $integration, User $actor): IntegrationSyncRun
    {
        $run = IntegrationSyncRun::query()->create(['uuid' => (string) Str::uuid(), 'integration_id' => $integration->id, 'type' => 'full', 'started_at' => now('UTC'), 'status' => 'running', 'started_by' => $actor->id]);
        $this->audit->logRequired(
            actionType: 'integration.sync_started',
            entityType: 'integration_sync_run',
            entityId: (string) $run->id,
            newValues: ['integration_id' => $integration->id, 'type' => 'full'],
            scope: 'system',
            actor: $actor,
        );

        if (! $integration->enabled || ! in_array('user_sync', $integration->capabilities ?? [], true)) {
            $run->forceFill(['completed_at' => now('UTC'), 'status' => 'configuration_required', 'error_code' => 'provider_not_configured'])->save();
        } else {
            $result = $this->connectors->for($integration->code)->pull($integration, ['dry_run' => false]);
            $items = is_array($result['items'] ?? null) ? $result['items'] : [];
            $run->forceFill([
                'completed_at' => now('UTC'),
                'status' => ($result['status'] ?? '') === 'awaiting_configuration' ? 'configuration_required' : 'completed',
                'received' => count($items),
            ])->save();
        }
        $this->audit->logRequired(
            actionType: 'integration.sync_completed',
            entityType: 'integration_sync_run',
            entityId: (string) $run->id,
            newValues: ['type' => 'full', 'status' => $run->status, 'received' => $run->received],
            scope: 'system',
            actor: $actor,
        );

        return $run;
    }

    public function resolve(IntegrationConflict $conflict, string $resolution, string $reason, User $actor): void
    {
        $conflict->forceFill(['status' => 'resolved', 'resolution' => $resolution, 'resolution_reason' => $reason, 'resolved_by' => $actor->id, 'resolved_at' => now('UTC')])->save();
        $this->audit->logRequired(
            actionType: 'integration.conflict_resolved',
            entityType: 'integration_conflict',
            entityId: (string) $conflict->id,
            newValues: ['resolution' => $resolution],
            reason: $reason,
            scope: 'system',
            actor: $actor,
        );
    }

    public function reconcile(Integration $integration, User $actor): IntegrationSyncRun
    {
        $run = IntegrationSyncRun::query()->create(['uuid' => (string) Str::uuid(), 'integration_id' => $integration->id, 'type' => 'reconciliation', 'started_at' => now('UTC'), 'status' => 'running', 'started_by' => $actor->id]);
        $result = $this->connectors->for($integration->code)->reconcile($integration, ['dry_run' => true]);
        $mismatches = is_array($result['mismatches'] ?? null) ? $result['mismatches'] : [];
        $requiresConfiguration = ($result['status'] ?? '') === 'awaiting_configuration';
        $run->forceFill([
            'completed_at' => now('UTC'),
            'status' => $requiresConfiguration ? 'configuration_required' : 'completed',
            'conflicts' => count($mismatches),
        ])->save();
        $this->audit->logRequired(
            actionType: 'integration.reconciliation_completed',
            entityType: 'integration_sync_run',
            entityId: (string) $run->id,
            newValues: ['status' => $run->status, 'conflicts' => $run->conflicts],
            scope: 'system',
            actor: $actor,
        );

        return $run;
    }
}
