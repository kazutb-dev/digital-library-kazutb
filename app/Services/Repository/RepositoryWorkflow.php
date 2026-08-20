<?php

namespace App\Services\Repository;

use App\Models\Catalog\RepositoryApproval;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryItemVersion;
use App\Models\Catalog\RepositoryReview;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RepositoryWorkflow
{
    public const TRANSITIONS = [
        'draft' => ['metadata_review'],
        'metadata_review' => ['author_verification', 'changes_requested', 'rejected'],
        'author_verification' => ['rights_review', 'changes_requested', 'rejected'],
        'rights_review' => ['quality_review', 'changes_requested', 'rejected'],
        'quality_review' => ['pending_approval', 'changes_requested', 'rejected'],
        'changes_requested' => ['metadata_review', 'archived'],
        'pending_approval' => ['approved', 'changes_requested', 'rejected'],
        'approved' => ['scheduled', 'published', 'embargoed'],
        'scheduled' => ['published', 'embargoed', 'archived'],
        'embargoed' => ['published', 'withdrawn'],
        'published' => ['withdrawn', 'archived'],
        'rejected' => ['metadata_review', 'archived'],
        'withdrawn' => ['archived'],
        'archived' => [],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function transition(RepositoryItem $item, string $to, User $actor, ?string $comment = null, ?string $scheduledFor = null): RepositoryItem
    {
        $this->authorize($item, $to, $actor);
        $this->assertTransition($item, $to, $actor, $comment, $scheduledFor);

        DB::transaction(function () use ($item, $to, $actor, $comment, $scheduledFor): void {
            $locked = RepositoryItem::query()->whereKey($item)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            // Repeat every state-dependent invariant under the row lock. This
            // prevents two moderation requests from approving/publishing the
            // same stale state concurrently.
            $this->authorize($locked, $to, $actor);
            $this->assertTransition($locked, $to, $actor, $comment, $scheduledFor);

            $changes = ['status' => $to];
            if ($to === 'pending_approval') {
                $changes += ['reviewed_by' => $actor->getKey()];
            }
            if ($to === 'approved') {
                $version = $this->activeVersionForApproval($locked);
                $approval = RepositoryApproval::query()->create([
                    'repository_item_id' => $locked->getKey(),
                    'repository_item_version_id' => $version->getKey(),
                    'approver_id' => $actor->getKey(),
                    'approver_role_snapshot' => 'director',
                    'checksum_sha256' => $version->checksum_sha256,
                    'metadata_fingerprint' => $locked->approvalFingerprint($version),
                    'approved_at' => now('UTC'),
                ]);
                $changes += [
                    'approved_by' => $actor->getKey(),
                    'active_approval_id' => $approval->getKey(),
                ];
            }
            if ($to === 'published') {
                $changes += ['published_at' => now()];
            }
            if ($to === 'scheduled') {
                $changes += ['scheduled_for' => $scheduledFor ?? $locked->scheduled_for];
            }
            if ($to === 'withdrawn') {
                $changes += ['withdrawn_at' => now(), 'withdrawn_by' => $actor->getKey(), 'withdrawal_reason' => $comment];
            }
            $locked->update($changes);
            RepositoryReview::create(['repository_item_id' => $locked->getKey(), 'review_type' => $from, 'decision' => $to, 'comment' => $comment, 'reviewer_id' => $actor->getKey()]);
            $this->audit->logRequired("repository.{$to}", 'repository_item', $locked->getKey(), oldValues: ['status' => $from], newValues: ['status' => $to], reason: $comment, scope: 'library', actor: $actor);
        });

        return $item->refresh();
    }

    private function authorize(RepositoryItem $item, string $to, User $actor): void
    {
        $allowed = match ($to) {
            'approved' => $actor->can('approve', $item),
            'scheduled', 'published', 'embargoed' => $actor->can('publish', $item),
            'metadata_review', 'author_verification', 'quality_review', 'pending_approval', 'rejected' => $actor->can('reviewMetadata', $item),
            'rights_review' => $actor->can('reviewRights', $item),
            'changes_requested' => $actor->can('requestChanges', $item),
            'withdrawn', 'archived' => $actor->can('withdraw', $item),
            default => $actor->can('edit', $item),
        };

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    private function assertTransition(RepositoryItem $item, string $to, User $actor, ?string $comment, ?string $scheduledFor): void
    {
        if (! in_array($to, self::TRANSITIONS[$item->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('repository.validation.invalid_transition')]);
        }

        if (in_array($to, ['approved', 'scheduled', 'published', 'embargoed'], true) && ! $item->rightsPermitPublication()) {
            throw ValidationException::withMessages(['copyright_status' => __('repository.validation.rights_required')]);
        }

        if ($to === 'approved' && $item->uploaded_by === $actor->getKey()) {
            throw new AuthorizationException;
        }

        if (in_array($to, ['scheduled', 'published', 'embargoed'], true) && ! $item->hasStoredPublishablePdf()) {
            throw ValidationException::withMessages(['file' => __('repository.validation.pdf_required')]);
        }

        if (in_array($to, ['scheduled', 'published', 'embargoed'], true)
            && ! $item->hasDirectorApproval()) {
            throw ValidationException::withMessages(['status' => __('repository.validation.approval_required')]);
        }

        if ($to === 'embargoed' && (! $item->embargoIsActive()
            || ! in_array(
                RepositoryItem::normaliseAccessPolicy($item->post_embargo_access_policy),
                RepositoryItem::POST_EMBARGO_ACCESS_POLICIES,
                true,
            ))) {
            throw ValidationException::withMessages(['embargo_until' => __('repository.validation.invalid_transition')]);
        }

        if (in_array($to, ['changes_requested', 'rejected', 'withdrawn'], true) && blank($comment)) {
            throw ValidationException::withMessages(['comment' => __('repository.validation.reason_required')]);
        }

        if ($to === 'scheduled' && blank($scheduledFor) && $item->scheduled_for === null) {
            throw ValidationException::withMessages(['scheduled_for' => __('repository.validation.schedule_required')]);
        }
    }

    private function activeVersionForApproval(RepositoryItem $item): RepositoryItemVersion
    {
        if (! $item->hasStoredPublishablePdf() || blank($item->checksum_sha256)) {
            throw ValidationException::withMessages(['file' => __('repository.validation.pdf_required')]);
        }

        $version = RepositoryItemVersion::query()
            ->where('repository_item_id', $item->getKey())
            ->where('is_active', true)
            ->where('version_number', $item->version_number)
            ->lockForUpdate()
            ->first();

        if ($version === null
            || blank($version->checksum_sha256)
            || ! hash_equals((string) $item->checksum_sha256, (string) $version->checksum_sha256)
            || (string) $item->file_path !== (string) $version->file_path) {
            throw ValidationException::withMessages(['file' => __('repository.validation.pdf_required')]);
        }

        return $version;
    }
}
