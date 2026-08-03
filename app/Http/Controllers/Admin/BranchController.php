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
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        return view('admin.branches.index', [
            'branches' => Branch::query()->withCount('funds')->orderBy('sort_order')->orderBy('name')->get(),
            'funds' => Fund::query()->with('branch')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($validated, $audit): void {
            $branch = Branch::query()->create($validated);

            $audit->logRequired(
                actionType: 'create',
                entityType: 'branch',
                entityId: $branch->getKey(),
                newValues: $this->snapshot($branch),
                scope: 'system',
            );
        });

        return back()->with('success', __('common.created_successfully'));
    }

    public function update(Request $request, Branch $branch, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request, $branch);
        $validated['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($branch, $validated, $audit): void {
            Branch::query()->whereKey($branch->getKey())->lockForUpdate()->firstOrFail();
            $branch->refresh();
            $old = $this->snapshot($branch);
            $branch->update($validated);
            $branch->refresh();

            $audit->logRequired(
                actionType: 'update',
                entityType: 'branch',
                entityId: $branch->getKey(),
                oldValues: $old,
                newValues: $this->snapshot($branch),
                scope: 'system',
            );
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    public function destroy(Request $request, Branch $branch, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($branch, $validated, $audit): void {
            Branch::query()->whereKey($branch->getKey())->lockForUpdate()->firstOrFail();
            $branch->refresh();

            if ($branch->funds()->exists()) {
                throw ValidationException::withMessages([
                    'branch' => __('settings.branches.errors.has_funds'),
                ]);
            }

            $old = $this->snapshot($branch);
            $branch->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'branch',
                entityId: $branch->getKey(),
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
    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch)],
            'type' => ['required', Rule::in(['library', 'circulation_desk', 'service_point', 'reading_room'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.weekdays' => ['nullable', 'string', 'max:120'],
            'opening_hours.weekend' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Branch $branch): array
    {
        return $branch->only([
            'name',
            'code',
            'type',
            'address',
            'phone',
            'email',
            'opening_hours',
            'description',
            'is_active',
            'sort_order',
        ]);
    }
}
