<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\ExternalResource;
use App\Models\Fund;
use App\Models\News;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cross-entity admin search behind the header field. Each entity group is
 * gated by the same permission that guards its section, so a limited staff
 * account only ever sees groups it could open anyway.
 */
class GlobalSearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request, AuditLogger $audit): View|JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'format' => ['nullable', 'in:json'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $groups = mb_strlen($query) >= 2
            ? $this->collectGroups($request, $query)
            : [];

        if (($validated['format'] ?? null) === 'json') {
            return response()->json([
                'query' => $query,
                'groups' => $groups,
            ]);
        }

        return view('admin.search.index', [
            'query' => $query,
            'groups' => $groups,
            'totalResults' => array_sum(array_map(
                static fn (array $group): int => count($group['items']),
                $groups,
            )),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, items: list<array{title: string, subtitle: string, url: string}>}>
     */
    private function collectGroups(Request $request, string $query): array
    {
        $user = $request->user();
        $needle = '%'.mb_strtolower($query).'%';
        $groups = [];

        if ($user?->can('users.manage')) {
            $items = User::query()
                ->where(function (Builder $builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(ad_login, \'\')) LIKE ?', [$needle]);
                })
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (User $found): array => [
                    'title' => $found->name,
                    'subtitle' => $found->email,
                    'url' => route('admin.users.show', $found),
                ])
                ->all();
            $this->pushGroup($groups, 'users', $items);
        }

        if ($user?->canAny(['news.edit_any', 'news.edit_own'])) {
            $items = News::query()
                ->search($query)
                ->orderByDesc('created_at')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (News $found): array => [
                    'title' => (string) $found->title,
                    'subtitle' => (string) ($found->status ?? ''),
                    'url' => route('admin.news.edit', $found),
                ])
                ->all();
            $this->pushGroup($groups, 'news', $items);
        }

        if ($user?->can('messages.view_all')) {
            $items = ContactMessage::query()
                ->search($query)
                ->orderByDesc('created_at')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (ContactMessage $found): array => [
                    'title' => (string) $found->subject,
                    'subtitle' => (string) $found->sender_email,
                    'url' => route('admin.messages.show', $found),
                ])
                ->all();
            $this->pushGroup($groups, 'messages', $items);
        }

        if ($user?->can('external_resources.manage')) {
            $items = ExternalResource::query()
                ->where(function (Builder $builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(title) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(provider, \'\')) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$needle]);
                })
                ->orderBy('title')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (ExternalResource $found): array => [
                    'title' => (string) $found->title,
                    'subtitle' => (string) ($found->provider ?? ''),
                    'url' => route('admin.external-resources.edit', $found),
                ])
                ->all();
            $this->pushGroup($groups, 'external_resources', $items);
        }

        if ($user?->can('branches.manage')) {
            $items = Branch::query()
                ->where(function (Builder $builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
                })
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (Branch $found): array => [
                    'title' => (string) $found->name,
                    'subtitle' => (string) $found->code,
                    'url' => route('admin.branches.index'),
                ])
                ->all();

            $items = array_merge($items, Fund::query()
                ->where(function (Builder $builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$needle]);
                })
                ->orderBy('name')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (Fund $found): array => [
                    'title' => (string) $found->name,
                    'subtitle' => (string) $found->code,
                    'url' => route('admin.branches.index'),
                ])
                ->all());
            $this->pushGroup($groups, 'branches_funds', $items);
        }

        if ($user?->can('system.logs')) {
            $items = ActivityLog::query()
                ->where(function (Builder $builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(actor_name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(entity_id) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(action_type) LIKE ?', [$needle]);
                })
                ->orderByDesc('occurred_at')
                ->limit(self::PER_GROUP)
                ->get()
                ->map(static fn (ActivityLog $found): array => [
                    'title' => $found->actor_name.' · '.$found->action_type,
                    'subtitle' => (string) $found->occurred_at?->utc()->format('Y-m-d H:i:s'),
                    'url' => route('admin.logs.show', $found),
                ])
                ->all();
            $this->pushGroup($groups, 'audit', $items);
        }

        return $groups;
    }

    /**
     * @param  list<array{key: string, label: string, items: list<array<string, string>>}>  $groups
     * @param  list<array<string, string>>  $items
     */
    private function pushGroup(array &$groups, string $key, array $items): void
    {
        if ($items === []) {
            return;
        }

        $groups[] = [
            'key' => $key,
            'label' => __('admin.search.groups.'.$key),
            'items' => $items,
        ];
    }
}
