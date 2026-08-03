<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Fund;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FundController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($validated, $audit): void {
            $this->lockBranch($validated['branch_id'] ?? null);
            $fund = Fund::query()->create($validated);

            $audit->logRequired(
                actionType: 'create',
                entityType: 'fund',
                entityId: $fund->getKey(),
                newValues: $this->snapshot($fund),
                scope: 'system',
            );
        });

        return back()->with('success', __('common.created_successfully'));
    }

    public function update(Request $request, Fund $fund, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request, $fund);
        $validated['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($fund, $validated, $audit): void {
            $this->lockBranch($validated['branch_id'] ?? null);
            Fund::query()->whereKey($fund->getKey())->lockForUpdate()->firstOrFail();
            $fund->refresh();
            $old = $this->snapshot($fund);
            $fund->update($validated);
            $fund->refresh();

            $audit->logRequired(
                actionType: 'update',
                entityType: 'fund',
                entityId: $fund->getKey(),
                oldValues: $old,
                newValues: $this->snapshot($fund),
                scope: 'system',
            );
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    public function destroy(Request $request, Fund $fund, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        DB::transaction(function () use ($fund, $validated, $audit): void {
            Fund::query()->whereKey($fund->getKey())->lockForUpdate()->firstOrFail();
            $fund->refresh();
            $old = $this->snapshot($fund);
            $fund->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'fund',
                entityId: $fund->getKey(),
                oldValues: $old,
                reason: $validated['reason'],
                scope: 'system',
            );
        });

        return back()->with('success', __('common.deleted_successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Fund $fund = null): array
    {
        return $request->validate([
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('funds', 'code')->ignore($fund)],
            'fund_type' => ['required', Rule::in(['main', 'educational', 'research', 'periodicals', 'electronic'])],
            'institutional_scope' => ['required', Rule::in(['college', 'university_economic', 'university_technology', 'general'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Fund $fund): array
    {
        return $fund->only([
            'name',
            'code',
            'fund_type',
            'institutional_scope',
            'branch_id',
            'description',
            'location',
            'is_active',
            'sort_order',
        ]);
    }

    private function lockBranch(mixed $branchId): void
    {
        if ($branchId === null || $branchId === '') {
            return;
        }

        $branchExists = Branch::query()
            ->whereKey($branchId)
            ->lockForUpdate()
            ->exists();

        if (! $branchExists) {
            throw ValidationException::withMessages([
                'branch_id' => __('validation.exists', ['attribute' => __('admin.funds.fields.branch')]),
            ]);
        }
    }
}
