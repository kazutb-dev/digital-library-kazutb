<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Single application-wide audit boundary.
 *
 * Domain services should call this class instead of writing directly to the
 * audit table. Snapshot fields deliberately preserve the actor's name and role
 * even if the account is renamed, deactivated, or later removed.
 */
class AuditLogger
{
    /**
     * Actions whose forensic value depends on an operator-supplied reason.
     *
     * @var list<string>
     */
    private const SENSITIVE_ACTIONS = [
        'delete',
        'remove',
        'merge',
        'duplicate.merge',
        'catalog.delete',
        'catalog.delete_record',
        'catalog.merge',
        'catalog.merge_duplicates',
        'copy.delete',
        'digital.delete',
        'repository.remove',
        'user.delete',
        'data_cleanup.bulk',
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     * @param  User|array<string, mixed>|null  $actor
     */
    public function log(
        string $actionType,
        string $entityType,
        string|int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        string $scope = 'system',
        array $metadata = [],
        User|array|null $actor = null,
        ?Request $request = null,
    ): ?ActivityLog {
        $reason = is_string($reason) ? trim($reason) : null;

        if ($this->requiresReason($actionType) && ($reason === null || $reason === '')) {
            throw new InvalidArgumentException('A reason is required for sensitive audit actions.');
        }

        if (! $this->tableExists()) {
            return null;
        }

        $request ??= request();
        $context = $this->actorContext($actor);

        return ActivityLog::query()->create([
            'actor_id' => $context['id'],
            'actor_name' => $this->limit($context['name'], 255),
            'actor_role' => $this->limit($context['role'], 255),
            'occurred_at' => now('UTC'),
            'action_type' => $this->limit(mb_strtolower(trim($actionType)), 64),
            'entity_type' => $this->limit(mb_strtolower(trim($entityType)), 191),
            'entity_id' => $this->limit((string) $entityId, 191),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request?->ip(),
            'reason' => $reason ?: null,
            'scope' => in_array($scope, ['system', 'security', 'operational', 'personal'], true)
                ? $scope
                : 'system',
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    /**
     * Record an audit event as part of a business transaction.
     *
     * Mutating control-plane operations use this strict variant so a missing
     * audit table can never silently leave an unaudited state change behind.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     * @param  User|array<string, mixed>|null  $actor
     */
    public function logRequired(
        string $actionType,
        string $entityType,
        string|int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        string $scope = 'system',
        array $metadata = [],
        User|array|null $actor = null,
        ?Request $request = null,
    ): ActivityLog {
        return $this->log(
            actionType: $actionType,
            entityType: $entityType,
            entityId: $entityId,
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
            scope: $scope,
            metadata: $metadata,
            actor: $actor,
            request: $request,
        ) ?? throw new RuntimeException('The required activity log table is unavailable.');
    }

    public function requiresReason(string $actionType): bool
    {
        $normalized = mb_strtolower(trim($actionType));

        return in_array($normalized, self::SENSITIVE_ACTIONS, true)
            || str_ends_with($normalized, '.delete')
            || str_ends_with($normalized, '.remove')
            || str_ends_with($normalized, '.merge');
    }

    /**
     * Future-facing access scope from PROJECT_CONTEXT §26.3.
     *
     * Admins receive the full log. Librarians receive their own actions plus
     * operational events. Members receive only events tied to their account.
     *
     * @param  User|array<string, mixed>|null  $viewer
     */
    public function visibleQuery(User|array|null $viewer): Builder
    {
        $query = ActivityLog::query();
        $context = $this->actorContext($viewer);

        if ($viewer instanceof User && $viewer->hasRole('admin')) {
            return $query;
        }

        if ($context['role'] === 'librarian'
            || ! in_array($context['role'], ['admin', 'member', 'guest'], true)) {
            return $query->where(function (Builder $builder) use ($context): void {
                $builder->where('scope', 'operational');

                if ($context['id'] !== null) {
                    $builder->orWhere('actor_id', $context['id']);
                }
            });
        }

        if ($context['id'] === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('actor_id', $context['id']);
    }

    /**
     * @param  User|array<string, mixed>|null  $actor
     * @return array{id: int|null, name: string, role: string}
     */
    private function actorContext(User|array|null $actor): array
    {
        $actor ??= Auth::user();

        if ($actor instanceof User) {
            $role = (string) ($actor->getRoleNames()->first() ?: $actor->role ?: 'member');
            $normalizedRole = mb_strtolower($role);

            return [
                'id' => (int) $actor->getKey(),
                'name' => (string) $actor->name,
                'role' => in_array($normalizedRole, ['admin', 'librarian', 'member'], true)
                    ? $normalizedRole
                    : $role,
            ];
        }

        if (! is_array($actor)) {
            $currentRequest = request();
            $sessionActor = $currentRequest?->hasSession()
                ? $currentRequest->session()->get('library.user')
                : null;
            $actor = is_array($sessionActor) ? $sessionActor : [];
        }

        // Session `id` is intentionally the CRM UUID for legacy catalog
        // operations. Audit actor_id is a FK to local users, so prefer the
        // dedicated local identity introduced by AuthSessionManager.
        $rawId = $actor['local_id'] ?? $actor['id'] ?? null;
        $localId = is_numeric($rawId) ? (int) $rawId : null;
        if ($localId !== null) {
            try {
                $localId = User::query()->whereKey($localId)->exists() ? $localId : null;
            } catch (\Throwable) {
                $localId = null;
            }
        }

        $role = trim((string) ($actor['canonical_role'] ?? $actor['role'] ?? 'guest')) ?: 'guest';
        $normalizedRole = mb_strtolower($role);

        return [
            'id' => $localId,
            'name' => trim((string) ($actor['name'] ?? $actor['email'] ?? 'System')) ?: 'System',
            'role' => in_array($normalizedRole, ['admin', 'librarian', 'member', 'guest'], true)
                ? $normalizedRole
                : $role,
        ];
    }

    /**
     * Prevent credentials, tokens, and integration secrets from leaking into
     * before/after snapshots.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sensitiveFragments = ['password', 'token', 'secret', 'authorization', 'api_key'];

        foreach (Arr::dot($values) as $key => $value) {
            $normalized = mb_strtolower((string) $key);

            if (collect($sensitiveFragments)->contains(
                static fn (string $fragment): bool => str_contains($normalized, $fragment)
            )) {
                Arr::set($values, $key, '[REDACTED]');
            }
        }

        return $values;
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('activity_logs');
        } catch (\Throwable) {
            return false;
        }
    }

    private function limit(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
