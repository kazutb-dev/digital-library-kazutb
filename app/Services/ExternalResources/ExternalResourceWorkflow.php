<?php

namespace App\Services\ExternalResources;

use App\Models\ExternalResource;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalResourceWorkflow
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function transition(
        ExternalResource $resource,
        string $action,
        ?string $reason = null,
    ): ExternalResource {
        return DB::transaction(function () use ($resource, $action, $reason): ExternalResource {
            $locked = ExternalResource::query()
                ->whereKey($resource->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $old = $this->snapshot($locked);

            $this->assertTransitionAllowed($locked, $action);

            match ($action) {
                'submit_review' => $locked->forceFill([
                    'publication_status' => 'review',
                    'is_active' => false,
                    'published_at' => null,
                ]),
                'publish', 'resume' => $locked->forceFill([
                    'publication_status' => 'published',
                    'is_active' => true,
                    'published_at' => $locked->published_at ?? now('UTC'),
                ]),
                'suspend' => $locked->forceFill(['is_active' => false]),
                'archive' => $locked->forceFill([
                    'publication_status' => 'archived',
                    'is_active' => false,
                ]),
                'return_to_draft' => $locked->forceFill([
                    'publication_status' => 'draft',
                    'is_active' => false,
                    'published_at' => null,
                ]),
            };
            $locked->save();

            $this->audit->logRequired(
                actionType: 'external_resource.workflow.'.$action,
                entityType: 'external_resource',
                entityId: $locked->getKey(),
                oldValues: $old,
                newValues: $this->snapshot($locked),
                reason: $reason,
                scope: 'operational',
            );

            return $locked->refresh();
        });
    }

    /**
     * Public-facing edits invalidate an existing approval. The controller
     * calls this while holding the same row lock as the content update.
     */
    public function applyContentUpdateState(ExternalResource $resource): void
    {
        if ($resource->publication_status === 'published') {
            $resource->forceFill([
                'publication_status' => 'review',
                'is_active' => false,
                'published_at' => null,
            ]);

            return;
        }

        $resource->is_active = false;
        if (in_array($resource->publication_status, ['draft', 'review'], true)) {
            $resource->published_at = null;
        }
    }

    private function assertTransitionAllowed(ExternalResource $resource, string $action): void
    {
        $allowed = match ($action) {
            'submit_review' => in_array($resource->publication_status, ['draft', 'review'], true),
            'publish' => $resource->publication_status === 'review',
            'suspend' => $resource->publication_status === 'published' && $resource->is_active,
            'resume' => $resource->publication_status === 'published' && ! $resource->is_active,
            'archive' => $resource->publication_status === 'published',
            'return_to_draft' => in_array($resource->publication_status, ['review', 'archived'], true),
            default => false,
        };
        if (! $allowed) {
            throw ValidationException::withMessages([
                'action' => __('external_resources.validation.invalid_transition'),
            ]);
        }

        if (in_array($action, ['publish', 'resume'], true)) {
            $issues = $resource->publicationReadinessIssues();
            if ($issues !== []) {
                throw ValidationException::withMessages([
                    'publication_status' => __('external_resources.validation.not_ready', [
                        'fields' => collect($issues)
                            ->map(fn (string $field): string => __('external_resources.readiness.'.$field))
                            ->join(', '),
                    ]),
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(ExternalResource $resource): array
    {
        return [
            'publication_status' => $resource->publication_status,
            'is_active' => (bool) $resource->is_active,
            'published_at' => $resource->published_at?->toIso8601String(),
        ];
    }
}
