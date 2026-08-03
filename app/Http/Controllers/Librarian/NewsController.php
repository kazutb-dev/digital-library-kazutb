<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Librarian news desk — the news.edit_own scope: a librarian sees all
 * published news but can create and edit only their own items (the admin
 * console keeps the edit_any scope).
 */
class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'published', 'archived'])],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = News::query()->where('created_by', $request->user()->getKey());

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }

        return view('librarian.news.index', [
            'news' => $query->orderByDesc('created_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters,
            'categories' => (array) Setting::valueFor('news_categories', ['event', 'announcement', 'update', 'schedule']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('librarian.news.form', [
            'item' => new News(['status' => 'draft', 'language' => app()->getLocale()]),
            'categories' => (array) Setting::valueFor('news_categories', ['event', 'announcement', 'update', 'schedule']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);

        $news = News::query()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'created_by' => $request->user()->getKey(),
            'published_by' => $validated['status'] === 'published' ? $request->user()->getKey() : null,
            'publish_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        $audit->logRequired(
            actionType: $news->status === 'published' ? 'publish' : 'create',
            entityType: 'news',
            entityId: $news->getKey(),
            newValues: $news->only(['title', 'category', 'status', 'language']),
            scope: 'operational',
        );

        return redirect()->route('librarian.news.edit', $news)->with('success', __('common.created_successfully'));
    }

    public function edit(Request $request, News $news): View
    {
        abort_unless((int) $news->created_by === (int) $request->user()->getKey(), 403);

        return view('librarian.news.form', [
            'item' => $news,
            'categories' => (array) Setting::valueFor('news_categories', ['event', 'announcement', 'update', 'schedule']),
        ]);
    }

    public function update(Request $request, News $news, AuditLogger $audit): RedirectResponse
    {
        abort_unless((int) $news->created_by === (int) $request->user()->getKey(), 403);

        $validated = $this->validated($request);
        $old = $news->only(['title', 'category', 'status', 'language', 'body']);

        $news->fill($validated);
        if ($news->isDirty('status') && $news->status === 'published') {
            $news->published_by = $request->user()->getKey();
            $news->publish_at = $news->publish_at ?? now();
        }
        $news->save();

        $audit->logRequired(
            actionType: 'update',
            entityType: 'news',
            entityId: $news->getKey(),
            oldValues: $old,
            newValues: $news->only(['title', 'category', 'status', 'language', 'body']),
            scope: 'operational',
        );

        return redirect()->route('librarian.news.edit', $news)->with('success', __('common.updated_successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $categories = (array) Setting::valueFor('news_categories', ['event', 'announcement', 'update', 'schedule']);

        return $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'category' => ['required', Rule::in($categories)],
            'language' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'max:65000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'show_on_homepage' => ['nullable', 'boolean'],
        ]) + ['show_on_homepage' => $request->boolean('show_on_homepage')];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 60, '')) ?: 'news';
        $slug = $base;
        $counter = 2;
        while (News::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
