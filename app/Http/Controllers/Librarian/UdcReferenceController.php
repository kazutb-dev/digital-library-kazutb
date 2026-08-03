<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\UdcCode;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UdcReferenceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        return view('librarian.udc-reference.index', [
            'codes' => UdcCode::query()
                ->with('parent')
                ->when($search !== '', fn ($query) => $query->search($search))
                ->orderBy('is_verified')
                ->orderByRaw('LENGTH(code), code')
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
            'unverifiedCount' => UdcCode::query()->where('is_verified', false)->count(),
        ]);
    }

    public function update(Request $request, UdcCode $udcCode, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'description_kk' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:160'],
            'is_verified' => ['nullable', 'boolean'],
        ]);

        $old = $udcCode->only(['description', 'description_kk', 'description_en', 'department', 'is_verified']);
        $udcCode->update([
            ...$validated,
            'is_verified' => $request->boolean('is_verified'),
        ]);

        $audit->logRequired(
            actionType: 'udc.update',
            entityType: 'udc_code',
            entityId: $udcCode->getKey(),
            oldValues: $old,
            newValues: $udcCode->only(['description', 'description_kk', 'description_en', 'department', 'is_verified']),
            scope: 'library',
        );

        return back()->with('success', __('common.updated_successfully'));
    }
}
