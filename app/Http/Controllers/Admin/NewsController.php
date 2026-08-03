<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Csv;
use App\Support\StoredUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
            'category' => ['nullable', Rule::in($this->categories())],
            'language' => ['nullable', Rule::in(['ru', 'kk', 'en'])],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'publish_at', 'title'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $mayEditAny = $request->user()?->can('news.edit_any') === true;
        $news = $this->filteredQuery($filters)
            ->when(! $mayEditAny, fn (Builder $query): Builder => $query->where('created_by', $request->user()?->getKey()))
            ->with(['creator', 'publisher'])
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();

        return view('admin.news.index', [
            'newsItems' => $news,
            'filters' => $filters,
            'statusCounts' => News::query()
                ->when(! $mayEditAny, fn (Builder $query): Builder => $query->where('created_by', $request->user()?->getKey()))
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'categories' => $this->categories(),
        ]);
    }

    public function create(): View
    {
        return view('admin.news.form', [
            'newsItem' => new News([
                'language' => app()->getLocale(),
                'category' => 'announcement',
                'status' => 'draft',
                'show_on_homepage' => false,
            ]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        if (in_array($validated['status'], ['scheduled', 'published'], true)) {
            abort_unless($request->user()?->can('news.publish'), 403);
        }
        $validated['slug'] = $this->uniqueSlug($validated['title']);
        $validated['created_by'] = $request->user()?->getKey();
        $this->applyPublicationState($validated, $request);

        $newCover = null;
        if ($request->hasFile('cover_image')) {
            $newCover = StoredUpload::put($request->file('cover_image'), 'news-covers', 'public');
            $validated['cover_image'] = $newCover;
        }

        unset($validated['reason']);
        try {
            $news = DB::transaction(function () use ($validated, $audit): News {
                $news = News::query()->create($validated);

                $audit->logRequired(
                    actionType: $news->status === 'published' ? 'publish' : 'create',
                    entityType: 'news',
                    entityId: $news->getKey(),
                    newValues: $this->snapshot($news),
                    scope: 'operational',
                );

                return $news;
            });
        } catch (Throwable $exception) {
            StoredUpload::deleteOrReport($newCover, 'public');

            throw $exception;
        }

        return redirect()
            ->route('admin.news.edit', $news)
            ->with('success', __('common.created_successfully'));
    }

    public function edit(Request $request, News $news): View
    {
        $this->assertMayEdit($request->user(), $news);

        return view('admin.news.form', [
            'newsItem' => $news,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, News $news, AuditLogger $audit): RedirectResponse
    {
        $this->assertMayEdit($request->user(), $news);
        $validated = $this->validated($request, $news);
        $this->authorizePublicationControls($request, $news, $validated);
        $newCover = null;
        if ($request->hasFile('cover_image')) {
            $newCover = StoredUpload::put($request->file('cover_image'), 'news-covers', 'public');
            $validated['cover_image'] = $newCover;
        }

        unset($validated['reason']);
        try {
            $oldCover = DB::transaction(function () use ($news, $validated, $request, $audit): ?string {
                News::query()->whereKey($news->getKey())->lockForUpdate()->firstOrFail();
                $news->refresh();
                $this->assertMayEdit($request->user(), $news);

                $old = $this->snapshot($news);
                $oldCover = $news->cover_image;
                $values = $validated;
                $this->authorizePublicationControls($request, $news, $values);
                $this->applyPublicationState($values, $request, $news);
                $news->update($values);
                $news->refresh();
                $action = $old['status'] !== $news->status && $news->status === 'published'
                    ? 'publish'
                    : ($old['status'] === 'published' && $news->status !== 'published' ? 'unpublish' : 'update');

                $audit->logRequired(
                    actionType: $action,
                    entityType: 'news',
                    entityId: $news->getKey(),
                    oldValues: $old,
                    newValues: $this->snapshot($news),
                    scope: 'operational',
                );

                return $oldCover;
            });
        } catch (Throwable $exception) {
            StoredUpload::deleteOrReport($newCover, 'public');

            throw $exception;
        }

        if ($oldCover && $news->cover_image !== $oldCover) {
            StoredUpload::deleteOrReport($oldCover, 'public');
        }

        return back()->with('success', __('common.updated_successfully'));
    }

    public function destroy(Request $request, News $news, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        DB::transaction(function () use ($news, $validated, $audit): void {
            News::query()->whereKey($news->getKey())->lockForUpdate()->firstOrFail();
            $news->refresh();
            $snapshot = $this->snapshot($news);
            $news->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'news',
                entityId: $news->getKey(),
                oldValues: $snapshot,
                reason: $validated['reason'],
                scope: 'operational',
            );
        });

        return redirect()->route('admin.news.index')->with('success', __('common.deleted_successfully'));
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
            'category' => ['nullable', Rule::in($this->categories())],
            'language' => ['nullable', Rule::in(['ru', 'kk', 'en'])],
        ]);
        $rows = $this->filteredQuery($filters)->with('creator')->orderByDesc('created_at')->cursor();

        $audit->logRequired(
            actionType: 'export',
            entityType: 'report',
            entityId: 'news',
            newValues: ['format' => 'csv', 'filters' => $filters],
            scope: 'system',
        );

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            Csv::writeRow($output, [
                __('reports.columns.id'),
                __('reports.columns.title'),
                __('reports.columns.language'),
                __('reports.columns.category'),
                __('reports.columns.status'),
                __('reports.columns.homepage'),
                __('reports.columns.publish_at_utc'),
                __('reports.columns.author'),
            ]);

            foreach ($rows as $news) {
                Csv::writeRow($output, [
                    $news->getKey(),
                    $news->title,
                    __('common.languages.'.$news->language),
                    trans()->has('news.categories.'.$news->category)
                        ? __('news.categories.'.$news->category)
                        : $news->category,
                    __('news.statuses.'.$news->status),
                    $news->show_on_homepage ? __('common.boolean.yes') : __('common.boolean.no'),
                    $news->publish_at?->utc()->toIso8601String(),
                    $news->creator?->name,
                ]);
            }

            fclose($output);
        }, 'news-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?News $news = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in($this->categories())],
            'language' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'body' => ['required', 'string', 'max:100000'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
            'publish_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'show_on_homepage' => ['required', 'boolean'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $validated['show_on_homepage'] = $request->boolean('show_on_homepage');

        if (($validated['status'] ?? null) === 'scheduled' && Carbon::parse($validated['publish_at'])->isPast()) {
            throw ValidationException::withMessages([
                'publish_at' => __('news.validation.schedule_future'),
            ]);
        }

        if (
            ($validated['status'] ?? null) === 'published'
            && ! empty($validated['publish_at'])
            && Carbon::parse($validated['publish_at'])->isFuture()
        ) {
            throw ValidationException::withMessages([
                'publish_at' => __('news.validation.published_not_future'),
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyPublicationState(array &$values, Request $request, ?News $news = null): void
    {
        if ($values['status'] === 'published') {
            $values['publish_at'] ??= now('UTC');
            if ($news?->status !== 'published' || $news->published_by === null) {
                $values['published_by'] = $request->user()?->getKey();
            }
        } elseif ($values['status'] === 'scheduled') {
            // The scheduling operator is the accountable approver for the
            // automatic publication performed later by the scheduler.
            if ($news?->status !== 'scheduled' || $news->published_by === null) {
                $values['published_by'] = $request->user()?->getKey();
            }
        } elseif ($news?->status === 'scheduled') {
            $values['published_by'] = null;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = News::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$needle]);
            });
        }

        foreach (['status', 'category', 'language'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'news';
        $slug = $base;
        $counter = 2;

        while (News::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function assertMayEdit(?User $user, News $news): void
    {
        abort_unless(
            $user !== null
            && (
                $user->can('news.edit_any')
                || ($user->can('news.edit_own') && (int) $news->created_by === (int) $user->getKey())
            ),
            403,
        );
    }

    /**
     * Editors without publication authority may edit content, but cannot
     * reschedule, publish, withdraw, or change homepage exposure.
     *
     * @param  array<string, mixed>  $values
     */
    private function authorizePublicationControls(Request $request, News $news, array &$values): void
    {
        if ($request->user()?->can('news.publish')) {
            return;
        }

        $controlledStatuses = ['scheduled', 'published'];
        $controlsAreRelevant = in_array($news->status, $controlledStatuses, true)
            || in_array((string) ($values['status'] ?? ''), $controlledStatuses, true);

        if (! $controlsAreRelevant) {
            return;
        }

        $currentPublishAt = $news->publish_at?->utc()->format('Y-m-d H:i');
        $requestedPublishAt = empty($values['publish_at'])
            ? null
            : Carbon::parse($values['publish_at'])->utc()->format('Y-m-d H:i');
        $controlsChanged = (string) $values['status'] !== (string) $news->status
            || (bool) $values['show_on_homepage'] !== (bool) $news->show_on_homepage
            || $requestedPublishAt !== $currentPublishAt;

        abort_if($controlsChanged, 403);

        // Preserve exact stored values (including seconds and approver) when
        // a non-publisher submits an otherwise unchanged edit form.
        $values['status'] = $news->status;
        $values['publish_at'] = $news->publish_at;
        $values['show_on_homepage'] = (bool) $news->show_on_homepage;
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        $configured = Setting::valueFor(
            'news_categories',
            ['event', 'announcement', 'update', 'schedule'],
        );

        return collect(is_array($configured) ? $configured : [])
            ->map(fn (mixed $value): string => mb_strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '' && mb_strlen($value) <= 32)
            ->unique()
            ->values()
            ->all() ?: ['event', 'announcement', 'update', 'schedule'];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(News $news): array
    {
        return [
            'title' => $news->title,
            'slug' => $news->slug,
            'language' => $news->language,
            'category' => $news->category,
            'body' => $news->body,
            'excerpt' => $news->excerpt,
            'status' => $news->status,
            'publish_at' => $news->publish_at?->utc()->toIso8601String(),
            'show_on_homepage' => (bool) $news->show_on_homepage,
            'cover_image' => $news->cover_image,
            'created_by' => $news->created_by,
            'published_by' => $news->published_by,
        ];
    }
}
