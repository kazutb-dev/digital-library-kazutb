<?php

namespace App\Services\News;

use App\Models\News;
use App\Models\NewsReview;
use App\Models\NewsRevision;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class NewsWorkflowService
{
    private const TRANSITIONS = [
        'draft' => ['pending_review'],
        'pending_review' => ['approved', 'changes_requested'],
        'changes_requested' => ['draft'],
        'approved' => ['scheduled', 'published'],
        'scheduled' => ['published', 'cancelled'],
        'published' => ['archived', 'cancelled'],
        'archived' => ['published'],
        'cancelled' => [],
    ];

    public function __construct(private AuditLogger $audit, private LibraryNotificationService $notifications) {}

    /** @param array<string,mixed> $context */
    public function transition(News $publication, string $to, User $actor, array $context = []): News
    {
        return DB::transaction(function () use ($publication, $to, $actor, $context): News {
            $locked = News::query()->whereKey($publication)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('news.validation.invalid_transition', compact('from', 'to'))]);
            }
            $this->authorize($locked, $to, $actor, $context);
            if (in_array($to, ['pending_review', 'approved', 'scheduled', 'published'], true)) {
                $this->assertPublicationReady($locked);
            }
            if ($to === 'changes_requested' && trim((string) ($context['comment'] ?? '')) === '') {
                throw ValidationException::withMessages(['comment' => __('news.validation.review_comment_required')]);
            }
            if (in_array($to, ['cancelled'], true) && trim((string) ($context['reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['reason' => __('news.validation.reason_required')]);
            }
            if ($from === 'archived' && $to === 'published' && trim((string) ($context['reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['reason' => __('news.validation.reason_required')]);
            }
            $values = ['status' => $to];
            match ($to) {
                'pending_review' => $values['reviewer_id'] = $context['reviewer_id'] ?? null,
                'approved' => $values += ['approved_by' => $actor->getKey(), 'approved_at' => now('UTC'), 'reviewer_id' => $actor->getKey()],
                'scheduled' => $values += ['scheduled_publish_at' => $context['scheduled_publish_at'] ?? null, 'publish_at' => $context['scheduled_publish_at'] ?? null],
                'published' => $values += ['published_by' => $actor->getKey(), 'published_at' => now('UTC'), 'publish_at' => now('UTC')],
                'archived' => $values['archived_at'] = now('UTC'),
                'cancelled' => $values += ['cancelled_at' => now('UTC'), 'cancellation_reason' => trim((string) $context['reason'])],
                default => null,
            };
            if ($to === 'scheduled' && empty($values['scheduled_publish_at'])) {
                throw ValidationException::withMessages(['scheduled_publish_at' => __('news.validation.schedule_required')]);
            }
            if ($to === 'scheduled' && now('UTC')->gte($values['scheduled_publish_at'])) {
                throw ValidationException::withMessages(['scheduled_publish_at' => __('news.validation.schedule_future')]);
            }
            $locked->update($values);
            NewsReview::query()->create(['news_id' => $locked->getKey(), 'actor_id' => $actor->getKey(), 'action' => $to, 'comment' => $context['comment'] ?? $context['reason'] ?? null, 'issues' => $context['issues'] ?? null]);
            $this->audit->logRequired(actionType: 'news.'.match ($to) {
                'pending_review' => 'submitted_for_review','changes_requested' => 'changes_requested',default => $to
            }, entityType: 'news', entityId: $locked->getKey(), oldValues: ['status' => $from], newValues: ['status' => $to], reason: $context['reason'] ?? null, scope: 'operational');
            if ($to === 'published' && $locked->annualPlanItem) {
                $locked->annualPlanItem->update(['status' => 'announced', 'publication_id' => $locked->getKey()]);
            }
            $this->notifyAuthor($locked, $to);
            $this->forgetPublicCaches();

            return $locked->refresh();
        });
    }

    /** Emergency publication is deliberately separate from normal transitions. */
    public function emergencyPublish(News $publication, User $actor, string $reason): News
    {
        if (! $actor->can('news.publish_emergency')) {
            throw new AuthorizationException;
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => __('news.validation.emergency_reason')]);
        }
        $this->assertPublicationReady($publication);

        return DB::transaction(function () use ($publication, $actor, $reason): News {
            $locked = News::query()->whereKey($publication)->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $locked->update(['status' => 'published', 'published_by' => $actor->getKey(), 'published_at' => now('UTC'), 'publish_at' => now('UTC')]);
            $this->audit->logRequired(actionType: 'news.published_emergency', entityType: 'news', entityId: $locked->getKey(), oldValues: ['status' => $from], newValues: ['status' => 'published'], reason: $reason, scope: 'operational');
            $this->notifyDirectors($locked, $reason);
            $this->forgetPublicCaches();

            return $locked->refresh();
        });
    }

    public function publishDue(News $publication): bool
    {
        return DB::transaction(function () use ($publication): bool {
            $locked = News::query()->dueForPublication()->whereKey($publication)->lockForUpdate()->first();
            if (! $locked) {
                return false;
            }
            if (Schema::hasColumn('news', 'approved_by') && ! $locked->approved_by) {
                return false;
            }
            $old = $locked->status;
            $values = ['status' => 'published', 'publish_at' => now('UTC')];
            if (Schema::hasColumn('news', 'published_at')) {
                $values['published_at'] = now('UTC');
            }
            $locked->update($values);
            $this->audit->logRequired(actionType: 'news.published', entityType: 'news', entityId: $locked->getKey(), oldValues: ['status' => $old], newValues: ['status' => 'published'], scope: 'operational', actor: ['name' => 'Scheduler', 'role' => 'system']);
            $this->notifyAuthor($locked, 'published');
            $this->forgetPublicCaches();

            return true;
        });
    }

    public function archiveExpired(News $publication): bool
    {
        return DB::transaction(function () use ($publication): bool {
            $locked = News::query()
                ->whereKey($publication)
                ->where('status', 'published')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now('UTC'))
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return false;
            }

            $locked->update([
                'status' => 'archived',
                'archived_at' => now('UTC'),
                'show_on_homepage' => false,
                'is_featured' => false,
                'is_pinned' => false,
            ]);
            $this->audit->logRequired(actionType: 'news.archived', entityType: 'news', entityId: $locked->getKey(), oldValues: ['status' => 'published'], newValues: ['status' => 'archived', 'show_on_homepage' => false], scope: 'operational', actor: ['name' => 'Scheduler', 'role' => 'system']);
            $this->notifyAuthor($locked, 'archived');
            $this->forgetPublicCaches();

            return true;
        });
    }

    public function removeExpiredHomepagePlacement(News $publication): bool
    {
        return DB::transaction(function () use ($publication): bool {
            $locked = News::query()
                ->whereKey($publication)
                ->where('status', 'published')
                ->where('show_on_homepage', true)
                ->whereNotNull('homepage_until')
                ->where('homepage_until', '<=', now('UTC'))
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return false;
            }

            $before = $locked->only(['show_on_homepage', 'is_featured', 'is_pinned']);
            $locked->update(['show_on_homepage' => false, 'is_featured' => false, 'is_pinned' => false]);
            $this->audit->logRequired(actionType: 'news.homepage_updated', entityType: 'news', entityId: $locked->getKey(), oldValues: $before, newValues: ['show_on_homepage' => false, 'is_featured' => false, 'is_pinned' => false], scope: 'operational', actor: ['name' => 'Scheduler', 'role' => 'system']);
            $this->forgetPublicCaches();

            return true;
        });
    }

    /** @param array<string,mixed> $snapshot */
    public function recordRevision(News $news, User $actor, array $snapshot, ?string $reason = null): void
    {
        $version = (int) NewsRevision::query()->where('news_id', $news->getKey())->max('version') + 1;
        NewsRevision::query()->create(['news_id' => $news->getKey(), 'created_by' => $actor->getKey(), 'version' => $version, 'snapshot' => $snapshot, 'reason' => $reason]);
    }

    private function authorize(News $news, string $to, User $actor, array $context): void
    {
        $permission = match ($to) {
            'pending_review' => 'news.submit_for_review','changes_requested' => 'news.request_changes','approved' => 'news.approve','scheduled' => 'news.schedule','published' => 'news.publish','archived' => 'news.archive','cancelled' => 'news.cancel','draft' => 'news.edit_own',default => 'news.review'
        };
        if (! $actor->can($permission)) {
            throw new AuthorizationException;
        }
        if ($to === 'approved' && (int) $news->created_by === (int) $actor->getKey()) {
            throw ValidationException::withMessages(['status' => __('news.validation.self_approval')]);
        }
        if ($to === 'published' && ! $news->approved_by) {
            throw ValidationException::withMessages(['status' => __('news.validation.approval_required')]);
        }
        if ($to === 'published' && $news->status === 'archived' && ! $actor->can('news.publish')) {
            throw new AuthorizationException;
        }
    }

    public function assertPublicationReady(News $news): void
    {
        $errors = [];
        foreach (['title_kk' => 'title_kk', 'excerpt_kk' => 'excerpt_kk', 'content_kk' => 'content_kk', 'cover_image' => 'cover_image', 'image_alt_kk' => 'image_alt_kk', 'audience' => 'audience'] as $field => $label) {
            if (trim((string) $news->{$field}) === '') {
                $errors[$field] = __('news.validation.required_for_review');
            }
        }
        if (Schema::hasColumn('news', 'category_id')) {
            if (! $news->category_id || ! $news->newsCategory?->active) {
                $errors['category_id'] = __('news.validation.required_for_review');
            } elseif (($news->newsCategory->allowed_types ?? []) !== [] && ! in_array($news->type, $news->newsCategory->allowed_types ?? [], true)) {
                $errors['category_id'] = __('news.validation.category_type');
            }
        } elseif (trim((string) $news->category) === '') {
            $errors['category'] = __('news.validation.required_for_review');
        }
        if (in_array($news->type, ['event', 'schedule'], true)) {
            if (! $news->starts_at) {
                $errors['starts_at'] = __('news.validation.required_for_event');
            }
            if (! $news->ends_at || ($news->starts_at && $news->ends_at->lte($news->starts_at))) {
                $errors['ends_at'] = __('news.validation.event_end');
            }
            if (trim((string) $news->venue) === '' && trim((string) $news->online_url) === '') {
                $errors['venue'] = __('news.validation.venue_or_online');
            }
            foreach (['organizer', 'contact_name'] as $field) {
                if (trim((string) $news->{$field}) === '') {
                    $errors[$field] = __('news.validation.required_for_event');
                }
            }
        }
        if ($news->type === 'announcement') {
            if (! $news->starts_at) {
                $errors['starts_at'] = __('news.validation.required_for_announcement');
            }
            if (trim((string) $news->importance) === '') {
                $errors['importance'] = __('news.validation.required_for_announcement');
            }
        }
        if ($news->type === 'update' && trim((string) $news->source) === '') {
            $errors['source'] = __('news.validation.required_for_update');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function notifyAuthor(News $news, string $event): void
    {
        $author = $news->creator;
        if (! $author) {
            return;
        }
        $this->notifications->sendLocalized($author, 'news_'.$event, 'news.notifications.'.$event.'.title', 'news.notifications.'.$event.'.body', ['title' => $news->localized('title', 'kk')], ['news_id' => $news->getKey(), 'url' => route('librarian.news.edit', $news)]);
    }

    private function notifyDirectors(News $news, string $reason): void
    {
        User::role('director')->each(fn (User $user) => $this->notifications->sendLocalized($user, 'news_emergency', 'news.notifications.emergency.title', 'news.notifications.emergency.body', ['title' => $news->localized('title', 'kk'), 'reason' => $reason], ['news_id' => $news->getKey()]));
    }

    private function forgetPublicCaches(): void
    {
        foreach (['news.homepage', 'news.categories', 'news.upcoming'] as $key) {
            Cache::forget($key);
        }
    }
}
