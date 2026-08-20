<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnnualContentPlanItem;
use App\Models\Catalog\RepositoryItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\News\NewsEditorService;
use App\Services\News\NewsWorkflowService;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'status' => ['nullable', Rule::in(News::STATUSES)], 'type' => ['nullable', Rule::in(News::TYPES)], 'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'scheduled_publish_at', 'title_kk'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $query = News::query()->with(['creator', 'approver']);
        if (Schema::hasTable('news_categories')) {
            $query->with('newsCategory');
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }foreach (['status', 'type'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }

        return view('admin.news.index', ['newsItems' => $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->paginate(Setting::resultsPerPage())->withQueryString(), 'filters' => $filters, 'statusCounts' => News::query()->selectRaw('status,count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status'), 'categories' => Schema::hasTable('news_categories') ? NewsCategory::query()->where('active', true)->orderBy('sort_order')->get() : collect()]);
    }

    public function create(): View
    {
        return $this->form(new News(['status' => 'draft', 'type' => 'announcement', 'timezone' => 'Asia/Almaty']));
    }

    public function store(Request $request, NewsEditorService $editor): RedirectResponse
    {
        $news = $editor->save($request, $request->user());

        return redirect()->route('admin.news.edit', $news)->with('success', __('news.messages.created'));
    }

    public function edit(Request $request, News $news): View
    {
        $this->assertMayEdit($request, $news);

        return $this->form($news->load(['reviews.actor', 'revisions', 'annualPlanItem']));
    }

    public function update(Request $request, News $news, NewsEditorService $editor): RedirectResponse
    {
        $editor->save($request, $request->user(), $news);

        return back()->with('success', __('news.messages.updated'));
    }

    public function autosave(Request $request, News $news, NewsEditorService $editor): JsonResponse
    {
        abort_unless(in_array($news->status, ['draft', 'changes_requested'], true), 409);
        $saved = $editor->save($request, $request->user(), $news);

        return response()->json(['saved_at' => $saved->updated_at?->toIso8601String()]);
    }

    public function transition(Request $request, News $news, NewsWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(News::STATUSES)], 'comment' => ['nullable', 'string', 'max:3000'], 'reason' => ['nullable', 'string', 'max:3000'], 'scheduled_publish_at' => ['nullable', 'date']]);
        $workflow->transition($news, $data['status'], $request->user(), $data);

        return back()->with('success', __('news.messages.transitioned'));
    }

    public function emergencyPublish(Request $request, News $news, NewsWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:3000']]);
        $workflow->emergencyPublish($news, $request->user(), $data['reason']);

        return back()->with('success', __('news.messages.published'));
    }

    public function destroy(Request $request, News $news, AuditLogger $audit): RedirectResponse
    {
        abort_unless($news->status === 'draft' && $request->user()->can('news.delete_draft'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($news, $data, $audit) {
            $snapshot = $news->only(['title_kk', 'status']);
            $news->delete();
            $audit->logRequired(actionType: 'news.delete_draft', entityType: 'news', entityId: $news->getKey(), oldValues: $snapshot, reason: $data['reason'], scope: 'operational');
        });

        return redirect()->route('admin.news.index')->with('success', __('common.deleted_successfully'));
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $rows = News::query()->with(['creator', 'newsCategory'])->orderByDesc('created_at')->cursor();
        $audit->logRequired(actionType: 'export', entityType: 'report', entityId: 'news', newValues: ['format' => 'csv'], scope: 'system');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            Csv::writeRow($out, ['ID', 'Title KK', 'Type', 'Category', 'Status', 'Published at', 'Author']);
            foreach ($rows as $item) {
                Csv::writeRow($out, [$item->public_id, $item->title_kk, $item->type, $item->newsCategory?->name(), $item->status, $item->published_at?->toIso8601String(), $item->creator?->name]);
            }fclose($out);
        }, 'news-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function categories(): View
    {
        return view('admin.news.categories', ['categories' => NewsCategory::query()->withCount('publications')->orderBy('sort_order')->get()]);
    }

    public function storeCategory(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->categoryData($request);
        $category = NewsCategory::query()->create($data);
        $audit->logRequired(actionType: 'news.category_created', entityType: 'news_category', entityId: $category->getKey(), newValues: $data, scope: 'operational');

        return back()->with('success', __('news.categories_admin.created'));
    }

    public function updateCategory(Request $request, NewsCategory $category, AuditLogger $audit): RedirectResponse
    {
        $data = $this->categoryData($request, $category);
        $old = $category->toArray();
        $category->update($data);
        $audit->logRequired(actionType: 'news.category_updated', entityType: 'news_category', entityId: $category->getKey(), oldValues: $old, newValues: $data, scope: 'operational');

        return back()->with('success', __('news.categories_admin.updated'));
    }

    public function analytics(): View
    {
        $status = News::query()->selectRaw('status,count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $types = News::query()->selectRaw('type,count(*) aggregate')->groupBy('type')->pluck('aggregate', 'type');
        $popular = News::query()->published()->orderByDesc('view_count')->limit(10)->get();
        $averageExpression = DB::getDriverName() === 'pgsql' ? 'AVG(EXTRACT(EPOCH FROM (approved_at-created_at))/3600)' : 'AVG((julianday(approved_at)-julianday(created_at))*24)';
        $averageReviewHours = News::query()->whereNotNull('approved_at')->selectRaw($averageExpression.' value')->value('value');

        return view('admin.news.analytics', compact('status', 'types', 'popular', 'averageReviewHours'));
    }

    private function form(News $newsItem): View
    {
        return view('admin.news.form', ['newsItem' => $newsItem, 'categories' => Schema::hasTable('news_categories') ? NewsCategory::query()->where('active', true)->orderBy('sort_order')->get() : collect(), 'planItems' => Schema::hasTable('annual_content_plan_items') ? AnnualContentPlanItem::query()->whereIn('status', ['planned', 'preparing'])->orderBy('planned_date')->get() : collect(), 'repositoryWorks' => Schema::hasTable('repository_approvals') ? RepositoryItem::query()->publicMetadata()->orderByDesc('published_at')->limit(250)->get(['id', 'title', 'year']) : collect()]);
    }

    private function assertMayEdit(Request $request, News $news): void
    {
        abort_unless($request->user()->can('news.edit_any') || ($request->user()->can('news.edit_own') && (int) $news->created_by === (int) $request->user()->getKey()), 403);
    }

    private function categoryData(Request $request, ?NewsCategory $category = null): array
    {
        return $request->validate(['slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('news_categories', 'slug')->ignore($category?->getKey())], 'name_kk' => ['required', 'string', 'max:255'], 'name_ru' => ['nullable', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'], 'icon' => ['nullable', 'string', 'max:64'], 'color_token' => ['required', Rule::in(['teal', 'blue', 'amber', 'red', 'slate'])], 'allowed_types' => ['nullable', 'array'], 'allowed_types.*' => [Rule::in(News::TYPES)], 'default_visibility' => ['required', Rule::in(['public', 'members', 'staff'])], 'active' => ['nullable', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:1000']]) + ['active' => $request->boolean('active')];
    }
}
