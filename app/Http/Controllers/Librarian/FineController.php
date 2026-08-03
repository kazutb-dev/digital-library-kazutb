<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Fine;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fines and debts board (Master.md §14.4-14.5, ТЗ Этап 5.4). Waiving always
 * requires a reason and is audited.
 */
class FineController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Fine::STATUSES)],
            'reason' => ['nullable', Rule::in(Fine::REASONS)],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = Fine::query()->with(['reader.readerProfile', 'loan.copy.bibliographicRecord', 'copy.bibliographicRecord']);

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($reason = ($filters['reason'] ?? null)) {
            $query->where('reason', $reason);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->whereHas('reader', fn ($reader) => $reader->whereRaw('LOWER(name) LIKE ?', [$needle]));
        }

        return view('librarian.fines.index', [
            'fines' => $query->orderByDesc('charged_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters,
            'pendingTotal' => (float) Fine::query()->where('status', 'pending')->sum('amount'),
            'pendingCount' => Fine::query()->where('status', 'pending')->count(),
        ]);
    }

    public function resolve(Request $request, Fine $fine, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['paid', 'waived'])],
            'reason' => ['nullable', 'required_if:action,waived', 'string', 'min:5', 'max:1000'],
        ]);

        if ($fine->status !== 'pending') {
            return back()->withErrors(['action' => __('librarian.fines.already_resolved')]);
        }

        $fine->update([
            'status' => $validated['action'],
            'resolved_at' => now(),
            'resolved_by' => $request->user()->getKey(),
            'notes' => trim(($fine->notes ? $fine->notes."\n" : '').($validated['reason'] ?? '')) ?: $fine->notes,
        ]);

        $audit->logRequired(
            actionType: 'fines.'.$validated['action'],
            entityType: 'fine',
            entityId: $fine->getKey(),
            newValues: ['amount' => (float) $fine->amount, 'reader_id' => $fine->user_id],
            reason: $validated['reason'] ?? null,
            scope: 'library',
        );

        return back()->with('success', __('common.updated_successfully'));
    }
}
