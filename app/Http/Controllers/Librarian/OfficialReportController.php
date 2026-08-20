<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Services\AuditLogger;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\OfficialReportExportService;
use App\Services\Reports\OfficialReportSnapshotService;
use App\Services\Reports\ReportFileIntegrity;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialReportController extends Controller
{
    public function index(Request $request, ReportRegistry $registry, LibraryReportService $reports): View
    {
        Gate::authorize('viewAny', OfficialReportSnapshot::class);
        $validated = $request->validate([
            'type' => ['nullable', Rule::in($registry->officialCodes())],
            'status' => ['nullable', Rule::in(OfficialReportSnapshot::STATUSES)],
        ]);
        $snapshots = OfficialReportSnapshot::query()
            ->select([
                'id', 'public_id', 'report_number', 'lineage_id', 'revision', 'report_type',
                'period_from', 'period_to', 'status', 'created_by', 'submitted_by',
                'approved_by', 'created_at',
            ])
            ->with(['creator', 'submitter', 'approver'])
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('report_type', $type))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('librarian.reports.official.index', [
            'snapshots' => $snapshots,
            'definitions' => $registry->officialDefinitions(),
            'filterOptions' => $reports->filterOptions(),
            'filters' => $validated,
        ]);
    }

    public function store(
        Request $request,
        ReportRegistry $registry,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('create', OfficialReportSnapshot::class);
        $validated = $request->validate([
            'report' => ['required', Rule::in($registry->officialCodes())],
            'revision_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $filters = ReportFilters::fromRequest($request);
        $snapshot = $snapshots->create(
            $validated['report'],
            $filters,
            $request->user(),
            $validated['revision_note'] ?? null,
        );

        return redirect()->route('librarian.reports.official.show', $snapshot)
            ->with('success', __('official_reports.messages.created'));
    }

    public function show(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): View {
        Gate::authorize('view', $snapshot);
        $snapshot->load([
            'creator', 'submitter', 'approver', 'rejector',
            'revisions' => fn ($query) => $query->select([
                'id', 'public_id', 'lineage_id', 'revision', 'status', 'created_by', 'created_at',
            ])->with('creator:id,name'),
            'exports' => fn ($query) => $query->select([
                'id', 'public_id', 'snapshot_id', 'requested_by', 'format', 'status',
                'progress', 'attempts', 'retention_until', 'file_deleted_at', 'created_at',
            ])->with('requester:id,name')->limit(100),
        ]);

        try {
            $snapshots->assertIntegrity($snapshot);
            $integrityOk = true;
        } catch (RuntimeException) {
            $integrityOk = false;
        }

        return view('librarian.reports.official.show', [
            'snapshot' => $snapshot,
            'payload' => $snapshot->source_data,
            'revisions' => $snapshot->revisions->filter(
                fn (OfficialReportSnapshot $revision): bool => Gate::forUser($request->user())->allows('view', $revision),
            )->values(),
            'exports' => $snapshot->exports->filter(
                fn (ReportExportJob $export): bool => Gate::forUser($request->user())->allows('viewExport', $export),
            )->values(),
            'integrityOk' => $integrityOk,
        ]);
    }

    public function submit(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('submit', $snapshot);
        $snapshots->submit($snapshot, $request->user());

        return back()->with('success', __('official_reports.messages.submitted'));
    }

    public function approve(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('approve', $snapshot);
        $validated = $request->validate(['decision_note' => ['nullable', 'string', 'max:2000']]);
        $snapshots->approve($snapshot, $request->user(), $validated['decision_note'] ?? null);

        return back()->with('success', __('official_reports.messages.approved'));
    }

    public function reject(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('reject', $snapshot);
        $validated = $request->validate(['decision_note' => ['required', 'string', 'min:3', 'max:2000']]);
        $snapshots->reject($snapshot, $request->user(), $validated['decision_note']);

        return back()->with('success', __('official_reports.messages.rejected'));
    }

    public function revise(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('revise', $snapshot);
        $validated = $request->validate(['revision_note' => ['required', 'string', 'min:3', 'max:2000']]);
        $revision = $snapshots->revise($snapshot, $request->user(), $validated['revision_note']);

        return redirect()->route('librarian.reports.official.show', $revision)
            ->with('success', __('official_reports.messages.revised'));
    }

    public function destroy(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('delete', $snapshot);
        $snapshots->deleteDraft($snapshot, $request->user());

        return redirect()->route('librarian.reports.official.index')
            ->with('success', __('official_reports.messages.deleted'));
    }

    public function archive(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
    ): RedirectResponse {
        Gate::authorize('archive', $snapshot);
        $snapshots->archive($snapshot, $request->user());

        return back()->with('success', __('official_reports.messages.archived'));
    }

    public function source(
        Request $request,
        OfficialReportSnapshot $snapshot,
        OfficialReportSnapshotService $snapshots,
        AuditLogger $audit,
    ): StreamedResponse {
        Gate::authorize('downloadSource', $snapshot);
        $snapshots->assertIntegrity($snapshot);
        $audit->logRequired(
            actionType: 'official_report.source_downloaded',
            entityType: 'official_report_snapshot',
            entityId: $snapshot->public_id,
            newValues: ['source_hash' => $snapshot->source_hash],
            scope: 'operational',
            actor: $request->user(),
        );

        return Storage::disk($snapshot->archive_disk)->download(
            $snapshot->archive_path,
            strtolower($snapshot->report_number).'-source.json',
            $this->privateHeaders('application/json; charset=UTF-8'),
        );
    }

    public function export(
        Request $request,
        OfficialReportSnapshot $snapshot,
        ReportRegistry $registry,
        OfficialReportExportService $exports,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('export', $snapshot);
        $definition = $registry->official($snapshot->report_type);
        $validated = $request->validate([
            'format' => ['required', Rule::in($definition->exports)],
            'idempotency_key' => ['nullable', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9_.:\/-]+$/'],
        ]);
        $clientKey = $request->header('Idempotency-Key') ?: ($validated['idempotency_key'] ?? null);
        if ($request->header('Idempotency-Key') !== null && ! preg_match('/^[A-Za-z0-9_.:\/-]{8,128}$/', $request->header('Idempotency-Key'))) {
            abort(422, 'Invalid Idempotency-Key header.');
        }
        $export = $exports->request($snapshot, $validated['format'], $request->user(), $clientKey);

        if ($request->expectsJson()) {
            return response()->json($this->exportPayload($export), $export->status === 'ready' ? 200 : 202);
        }

        return back()->with('success', __('official_reports.messages.export_queued'));
    }

    public function exportStatus(ReportExportJob $export): JsonResponse
    {
        Gate::authorize('viewExport', $export);

        return response()->json($this->exportPayload($export->fresh()));
    }

    public function retryExport(
        Request $request,
        ReportExportJob $export,
        OfficialReportExportService $exports,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('viewExport', $export);
        Gate::authorize('export', $export->snapshot);
        $export = $exports->retry($export, $request->user());

        return $request->expectsJson()
            ? response()->json($this->exportPayload($export), 202)
            : back()->with('success', __('official_reports.messages.export_queued'));
    }

    public function downloadExport(
        Request $request,
        ReportExportJob $export,
        AuditLogger $audit,
        ReportFileIntegrity $files,
    ): StreamedResponse {
        Gate::authorize('downloadExport', $export);
        if (($export->retention_until !== null && $export->retention_until->isPast())
            || $export->file_deleted_at !== null) {
            abort(410, 'This official report export has expired.');
        }
        if ($export->file_disk === null || $export->file_path === null || ! Storage::disk($export->file_disk)->exists($export->file_path)) {
            abort(404);
        }
        $files->assert(Storage::disk($export->file_disk), $export->file_path, (string) $export->file_hash, (int) $export->file_size);
        $audit->logRequired(
            actionType: 'official_report.export_downloaded',
            entityType: 'official_report_export',
            entityId: $export->public_id,
            newValues: ['snapshot_id' => $export->snapshot->public_id, 'format' => $export->format, 'file_hash' => $export->file_hash],
            scope: 'operational',
            actor: $request->user(),
        );

        return Storage::disk($export->file_disk)->download(
            $export->file_path,
            $export->file_name,
            $this->privateHeaders((string) $export->mime_type),
        );
    }

    /** @return array<string, mixed> */
    private function exportPayload(ReportExportJob $export): array
    {
        return [
            'id' => $export->public_id,
            'snapshot_id' => $export->snapshot?->public_id ?? $export->snapshot()->value('public_id'),
            'format' => $export->format,
            'status' => $export->status,
            'progress' => $export->progress,
            'attempts' => $export->attempts,
            'error' => $export->status === 'failed'
                ? [
                    'code' => $export->public_error_code ?: 'REPORT_GENERATION_FAILED',
                    'message' => __('official_reports.messages.export_failed'),
                ]
                : null,
            'status_url' => route('librarian.reports.official.exports.status', $export),
            'download_url' => $export->status === 'ready'
                ? route('librarian.reports.official.exports.download', $export)
                : null,
        ];
    }

    /** @return array<string, string> */
    private function privateHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
