<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\AnnualContentPlanItem;
use App\Models\Catalog\RepositoryItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Setting;
use App\Services\News\NewsEditorService;
use App\Services\News\NewsWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(News::STATUSES)], 'type' => ['nullable', Rule::in(News::TYPES)], 'search' => ['nullable', 'string', 'max:200'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $mayEditAny = $request->user()->can('news.edit_any') || $request->user()->can('news.review');
        $query = News::query()->with(['creator', 'newsCategory', 'reviewer'])->when(! $mayEditAny, fn (Builder $q) => $q->where('created_by', $request->user()->getKey()));
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['type'] ?? null) {
            $query->where('type', $filters['type']);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }
        if ($filters['from'] ?? null) {
            $query->whereDate('starts_at', '>=', $filters['from']);
        }
        if ($filters['to'] ?? null) {
            $query->whereDate('starts_at', '<=', $filters['to']);
        }
        $weekStart = now(config('app.timezone'))->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        return view('librarian.news.index', [
            'news' => $query->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString(), 'filters' => $filters,
            'categories' => NewsCategory::query()->where('active', true)->orderBy('sort_order')->get(),
            'weekPublic' => News::query()->published()->whereBetween('starts_at', [$weekStart, $weekEnd])->orderBy('starts_at')->get(),
            'weekTasks' => News::query()->whereIn('status', ['draft', 'pending_review', 'changes_requested', 'scheduled'])->where(fn (Builder $q) => $q->whereBetween('starts_at', [$weekStart, $weekEnd])->orWhereBetween('scheduled_publish_at', [$weekStart, $weekEnd])->orWhere('status', 'changes_requested'))->orderBy('starts_at')->limit(12)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new News(['status' => 'draft', 'type' => 'event', 'timezone' => 'Asia/Almaty']), $request);
    }

    public function store(Request $request, NewsEditorService $editor): RedirectResponse
    {
        $news = $editor->save($request, $request->user());

        return redirect()->route('librarian.news.edit', $news)->with('success', __('news.messages.created'));
    }

    public function edit(Request $request, News $news): View
    {
        $this->assertMayEdit($request, $news);

        return $this->form($news->load(['reviews.actor', 'revisions', 'annualPlanItem']), $request);
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
        $data = $request->validate(['status' => ['required', Rule::in(News::STATUSES)], 'comment' => ['nullable', 'string', 'max:3000'], 'reason' => ['nullable', 'string', 'max:3000'], 'issues' => ['nullable', 'array', 'max:30'], 'issues.*' => ['string', 'max:500'], 'scheduled_publish_at' => ['nullable', 'date'], 'reviewer_id' => ['nullable', 'exists:users,id']]);
        $workflow->transition($news, $data['status'], $request->user(), $data);

        return back()->with('success', __('news.messages.transitioned'));
    }

    public function emergencyPublish(Request $request, News $news, NewsWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:3000']]);
        $workflow->emergencyPublish($news, $request->user(), $data['reason']);

        return back()->with('success', __('news.messages.published'));
    }

    public function calendar(Request $request): View
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'status' => ['nullable', Rule::in(News::STATUSES)], 'type' => ['nullable', Rule::in(News::TYPES)]]);
        $month = Carbon::createFromFormat('Y-m', ($filters['month'] ?? now()->format('Y-m')), 'Asia/Almaty')->startOfMonth();
        $items = News::query()->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))->where(fn ($q) => $q->whereBetween('starts_at', [$month, $month->copy()->endOfMonth()])->orWhereBetween('scheduled_publish_at', [$month, $month->copy()->endOfMonth()]))->orderByRaw('COALESCE(starts_at, scheduled_publish_at)')->get();
        $planItems = AnnualContentPlanItem::query()->with('plan')->whereBetween('planned_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get();

        return view('librarian.news.calendar', compact('items', 'planItems', 'month', 'filters'));
    }

    public function preview(Request $request, News $news): View
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($request->user()->can('news.edit_any') || $request->user()->can('news.review') || (int) $news->created_by === (int) $request->user()->getKey(), 403);

        return view('librarian.news.preview', ['publication' => $news]);
    }

    private function form(News $item, Request $request): View
    {
        return view('librarian.news.form', ['item' => $item, 'categories' => NewsCategory::query()->where('active', true)->orderBy('sort_order')->get(), 'planItems' => AnnualContentPlanItem::query()->whereIn('status', ['planned', 'preparing'])->orderBy('planned_date')->get(), 'repositoryWorks' => Schema::hasTable('repository_approvals') ? RepositoryItem::query()->publicMetadata()->orderByDesc('published_at')->limit(250)->get(['id', 'title', 'year']) : collect()]);
    }

    private function assertMayEdit(Request $request, News $news): void
    {
        abort_unless($request->user()->can('news.edit_any') || ($request->user()->can('news.edit_own') && (int) $news->created_by === (int) $request->user()->getKey()), 403);
    }
}
