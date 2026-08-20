<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Digital\DigitalMaterialWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DigitalMaterialController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(ElectronicMaterial::WORKFLOW_STATUSES)], 'type' => ['nullable', Rule::in(ElectronicMaterial::MATERIAL_TYPES)], 'q' => ['nullable', 'string', 'max:200']]);
        $query = ElectronicMaterial::query()->with('bibliographicRecord');
        if ($filters['status'] ?? null) {
            $query->where('workflow_status', $filters['status']);
        }
        if ($filters['type'] ?? null) {
            $query->where('material_type', $filters['type']);
        }
        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where('title', 'like', '%'.$term.'%');
        }

        return view('librarian.digital-materials.index', ['materials' => $query->latest()->paginate(Setting::resultsPerPage())->withQueryString(), 'filters' => $filters]);
    }

    public function edit(ElectronicMaterial $material): View
    {
        return view('librarian.digital-materials.edit', ['material' => $material->load(['bibliographicRecord', 'versions'])]);
    }

    public function update(Request $request, ElectronicMaterial $material, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->canAny(['digital.review_metadata', 'digital.review_rights', 'digital.manage_policies']), 403);
        $validated = $request->validate([
            'material_type' => ['required', Rule::in(ElectronicMaterial::MATERIAL_TYPES)], 'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'], 'language' => ['required', Rule::in(['kk', 'ru', 'en'])],
            'source' => ['nullable', 'string', 'max:5000'], 'rights_holder' => ['nullable', 'string', 'max:255'],
            'copyright_status' => ['required', Rule::in(ElectronicMaterial::COPYRIGHT_STATUSES)], 'licence_type' => ['nullable', 'string', 'max:64'],
            'licence_text' => ['nullable', 'string', 'max:5000'], 'permission_date' => ['nullable', 'date'],
            'access_level' => ['required', Rule::in(ElectronicMaterial::ACCESS_LEVELS)], 'preview_policy' => ['required', Rule::in(['none', 'metadata', 'sample', 'full'])],
            'download_policy' => ['required', Rule::in(['disabled', 'allowed'])], 'print_policy' => ['required', Rule::in(['disabled', 'allowed'])],
            'copy_policy' => ['required', Rule::in(['disabled', 'allowed'])], 'restricted_roles' => ['nullable', 'array'], 'restricted_roles.*' => ['string', 'max:96'],
            'campus_only' => ['nullable', 'boolean'], 'embargo_until' => ['nullable', 'date'],
        ]);
        $validated['campus_only'] = $request->boolean('campus_only');
        $validated['allow_download'] = $validated['download_policy'] === 'allowed';
        $old = $material->only(array_keys($validated));
        $material->update($validated);
        $audit->logRequired('digital.metadata_updated', 'electronic_material', $material->getKey(), oldValues: $old, newValues: $material->only(array_keys($validated)), scope: 'library');

        return back()->with('success', __('common.updated_successfully'));
    }

    public function transition(Request $request, ElectronicMaterial $material, DigitalMaterialWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(ElectronicMaterial::WORKFLOW_STATUSES)], 'reason' => ['nullable', 'string', 'max:2000']]);
        $permission = match ($validated['status']) {
            'metadata_review' => 'digital.review_metadata', 'rights_review' => 'digital.review_rights',
            'processing', 'ready_for_review', 'processing_failed' => 'digital.process',
            'approved' => 'digital.approve', 'published' => 'digital.publish',
            'restricted' => 'digital.restrict', 'withdrawn', 'archived' => 'digital.withdraw',
            default => 'digital.review_metadata',
        };
        abort_unless($request->user()->can($permission), 403);
        $workflow->transition($material, $validated['status'], $request->user(), $validated['reason'] ?? null);

        return back()->with('success', __('common.updated_successfully'));
    }
}
