<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CsvImportService;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CSV import for users and external resources: upload — validated preview —
 * confirmed commit. The commit re-parses nothing — it applies the cached,
 * already-validated plan inside a single transaction and refuses to run when
 * any row still carries an error, so imports are all-or-nothing.
 */
class ImportController extends Controller
{
    private const CACHE_TTL_MINUTES = 30;

    public function form(Request $request, string $type): View
    {
        $this->authorizeType($request, $type);

        return view('admin.imports.form', ['type' => $type]);
    }

    public function preview(Request $request, string $type, CsvImportService $import): View|RedirectResponse
    {
        $this->authorizeType($request, $type);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $import->parse($type, $request->file('file'), $request->user());

        if ($result['error'] !== null) {
            return back()->withErrors(['file' => $result['error']]);
        }

        $token = Str::random(40);
        Cache::put('admin-import:'.$token, [
            'type' => $type,
            'rows' => $result['rows'],
            'file_name' => $request->file('file')->getClientOriginalName(),
            'importer_id' => $request->user()->getKey(),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        $rows = collect($result['rows']);

        return view('admin.imports.preview', [
            'type' => $type,
            'token' => $token,
            'rows' => $result['rows'],
            'fileName' => $request->file('file')->getClientOriginalName(),
            'stats' => [
                'create' => $rows->where('action', 'create')->count(),
                'update' => $rows->where('action', 'update')->count(),
                'error' => $rows->where('action', 'error')->count(),
            ],
        ]);
    }

    public function commit(Request $request, string $type, CsvImportService $import, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeType($request, $type);

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:40'],
        ]);

        $cacheKey = 'admin-import:'.$validated['token'];
        $plan = Cache::get($cacheKey);

        if (! is_array($plan)
            || $plan['type'] !== $type
            || $plan['importer_id'] !== $request->user()->getKey()) {
            return redirect()
                ->route('admin.imports.form', $type)
                ->withErrors(['file' => __('admin.imports.errors.preview_expired')]);
        }

        $rows = collect($plan['rows']);
        if ($rows->where('action', 'error')->isNotEmpty()) {
            return back()->withErrors(['file' => __('admin.imports.errors.rows_invalid')]);
        }

        try {
            $this->commitTransaction($rows, $type, $plan, $import, $audit);
        } catch (QueryException) {
            // The database changed between preview and commit (e.g. a user
            // with an imported email was created meanwhile). The transaction
            // already rolled everything back — nothing was partially applied.
            return redirect()
                ->route('admin.imports.form', $type)
                ->withErrors(['file' => __('admin.imports.errors.conflict')]);
        }

        Cache::forget($cacheKey);

        $summary = __('admin.imports.done_summary', [
            'created' => $rows->where('action', 'create')->count(),
            'updated' => $rows->where('action', 'update')->count(),
        ]);

        return redirect()
            ->route($type === 'users' ? 'admin.users.index' : 'admin.external-resources.index')
            ->with('success', $summary);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $plan
     */
    private function commitTransaction($rows, string $type, array $plan, CsvImportService $import, AuditLogger $audit): void
    {
        DB::transaction(function () use ($rows, $type, $plan, $import, $audit): void {
            foreach ($rows as $row) {
                match ($type) {
                    'users' => $import->applyUserRow($row),
                    default => $import->applyExternalResourceRow($row),
                };
            }

            $audit->logRequired(
                actionType: 'import',
                entityType: $type === 'users' ? 'user' : 'external_resource',
                entityId: 'csv:'.$plan['file_name'],
                newValues: [
                    'file' => $plan['file_name'],
                    'created' => $rows->where('action', 'create')->count(),
                    'updated' => $rows->where('action', 'update')->count(),
                    'total_rows' => $rows->count(),
                ],
                scope: 'security',
            );
        });
    }

    /**
     * Each import type demands the same permission as its manual CRUD pages.
     */
    private function authorizeType(Request $request, string $type): void
    {
        abort_unless(in_array($type, CsvImportService::TYPES, true), 404);
        abort_unless(
            (bool) $request->user()?->can($type === 'users' ? 'users.manage' : 'external_resources.manage'),
            403,
        );
    }
}
