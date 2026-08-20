<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\AnnualContentPlan;
use App\Models\AnnualContentPlanItem;
use App\Models\News;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnualContentPlanController extends Controller
{
    public function index(): View
    {
        return view('librarian.news.plans', ['plans' => AnnualContentPlan::query()->withCount('items')->orderByDesc('year')->get()]);
    }

    public function show(AnnualContentPlan $plan): View
    {
        return view('librarian.news.plan', ['plan' => $plan->load(['items.responsible', 'creator', 'approver'])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100', 'unique:annual_content_plans,year'], 'title' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $plan = DB::transaction(function () use ($data, $request, $audit) {
            $plan = AnnualContentPlan::query()->create($data + ['status' => 'draft', 'created_by' => $request->user()->getKey()]);
            $audit->logRequired(actionType: 'annual_plan.created', entityType: 'annual_content_plan', entityId: $plan->getKey(), newValues: $data, scope: 'operational');

            return $plan;
        });

        return redirect()->route('librarian.news.plans.show', $plan)->with('success', __('news.plan.created'));
    }

    public function transition(Request $request, AnnualContentPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(AnnualContentPlan::STATUSES)]]);
        $to = $data['status'];
        $allowed = ['draft' => ['pending_approval'], 'pending_approval' => ['approved'], 'approved' => ['active'], 'active' => ['completed'], 'completed' => ['archived'], 'archived' => []];
        abort_unless(in_array($to, $allowed[$plan->status] ?? [], true), 422);
        if ($to === 'approved') {
            abort_unless($request->user()->can('news.approve'), 403);
        }
        DB::transaction(function () use ($plan, $to, $request, $audit) {
            $old = $plan->status;
            $values = ['status' => $to];
            if ($to === 'approved') {
                $values += ['approved_by' => $request->user()->getKey(), 'approved_at' => now('UTC')];
            }$plan->update($values);
            $audit->logRequired(actionType: 'annual_plan.'.$to, entityType: 'annual_content_plan', entityId: $plan->getKey(), oldValues: ['status' => $old], newValues: ['status' => $to], scope: 'operational');
        });

        return back()->with('success', __('news.plan.updated'));
    }

    public function storeItem(Request $request, AnnualContentPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['item_number' => ['required', 'integer', 'min:1', Rule::unique('annual_content_plan_items')->where('plan_id', $plan->getKey())], 'type' => ['required', Rule::in(News::TYPES)], 'title_kk' => ['required', 'string', 'max:255'], 'title_ru' => ['nullable', 'string', 'max:255'], 'title_en' => ['nullable', 'string', 'max:255'], 'planned_date' => ['required', 'date'], 'responsible_id' => ['nullable', 'exists:users,id'], 'audience' => ['nullable', 'string', 'max:255'], 'expected_result' => ['nullable', 'string', 'max:3000']]);
        $item = AnnualContentPlanItem::query()->create($data + ['plan_id' => $plan->getKey(), 'status' => 'planned']);
        $audit->logRequired(actionType: 'annual_plan.item_created', entityType: 'annual_content_plan_item', entityId: $item->getKey(), newValues: $data, scope: 'operational');

        return back()->with('success', __('news.plan.item_created'));
    }

    public function completeItem(Request $request, AnnualContentPlanItem $item, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['actual_date' => ['required', 'date'], 'completion_report' => ['required', 'string', 'min:20', 'max:10000']]);
        $item->update($data + ['status' => 'completed']);
        $audit->logRequired(actionType: 'annual_plan.item_completed', entityType: 'annual_content_plan_item', entityId: $item->getKey(), newValues: $data + ['status' => 'completed'], scope: 'operational');

        return back()->with('success', __('news.plan.item_completed'));
    }
}
