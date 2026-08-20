<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ExecutiveAlertAcknowledgement;
use App\Services\AuditLogger;
use App\Services\Library\LibrarianWorkspaceService;
use App\Services\Reports\DirectorAnalyticsService;
use App\Support\Csv;
use App\Support\OfficeOpenXmlExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ExecutiveDashboardController extends Controller
{
    private const ALERT_KEYS = [
        'overdue', 'external_expired', 'external_expiring', 'data_quality_open',
        'open_messages', 'problem_copies', 'message_sla_overdue',
    ];

    public function acknowledge(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'alert_key' => ['required', Rule::in(self::ALERT_KEYS)],
            'scope_hash' => ['required', 'string', 'size:64'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        abort_unless($user?->hasRole('director') && $user->can('reports.view_full'), 403);

        $acknowledgement = ExecutiveAlertAcknowledgement::query()->updateOrCreate(
            ['alert_key' => $data['alert_key'], 'scope_hash' => $data['scope_hash'], 'acknowledged_by' => $user->getKey()],
            ['acknowledged_at' => now('UTC'), 'comment' => $data['comment'] ?? null],
        );
        $audit->logRequired('executive.alert.acknowledge', 'executive_alert', $acknowledgement->getKey(), newValues: $data, scope: 'operational');

        return back()->with('success', __('librarian.overview.director.alert_acknowledged'));
    }

    public function assign(Request $request, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasRole('director') && $user->can('tasks.assign'), 403);
        $data = $request->validate([
            'alert_key' => ['required', Rule::in(self::ALERT_KEYS)],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::in(['normal', 'high', 'critical'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);
        $workspace->createTask($user, [
            'title' => __('librarian.overview.director.alert_task_titles.'.$data['alert_key']),
            'type' => 'general',
            'related_entity_type' => 'executive_alert',
            'related_entity_id' => $data['alert_key'],
            'assigned_to' => $data['assigned_to'],
            'priority' => $data['priority'],
            'due_at' => $data['due_at'],
            'status' => 'open',
            'comment' => $data['comment'] ?? null,
        ]);

        return back()->with('success', __('librarian.overview.director.task_created'));
    }

    public function export(
        Request $request,
        string $format,
        DirectorAnalyticsService $analytics,
        OfficeOpenXmlExporter $office,
    ): BinaryFileResponse {
        abort_unless($request->user()?->hasRole('director') && $request->user()->can('reports.view_full'), 403);
        abort_unless(in_array($format, ['csv', 'pdf', 'xlsx', 'docx'], true), 404);
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'quarter', 'year', 'custom'])],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'compare' => ['nullable', 'boolean'],
        ]);
        $dashboard = $analytics->build($filters);
        $title = __('librarian.overview.director.export_title');
        $headers = [__('librarian.overview.director.export_metric'), __('librarian.overview.director.export_value')];
        $rows = collect($dashboard['cards'])->map(fn ($value, $key): array => [
            trans()->has('librarian.overview.director.cards.'.$key) ? __('librarian.overview.director.cards.'.$key) : (string) $key,
            $value,
        ])->values()->all();
        $path = match ($format) {
            'csv' => $this->csv($headers, $rows),
            'xlsx' => $office->xlsx($title, $headers, $rows),
            'docx' => $office->docx($title, $headers, $rows, $filters),
            'pdf' => $this->pdf($title, $headers, $rows, $dashboard),
        };
        $mime = match ($format) {
            'csv' => 'text/csv; charset=UTF-8',
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };

        return response()->download($path, 'executive-summary-'.now()->format('Ymd-His').'.'.$format, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend();
    }

    private function csv(array $headers, array $rows): string
    {
        $path = $this->temporaryPath('csv');
        $stream = fopen($path, 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        Csv::writeRow($stream, $headers);
        foreach ($rows as $row) {
            Csv::writeRow($stream, $row);
        }
        fclose($stream);

        return $path;
    }

    private function pdf(string $title, array $headers, array $rows, array $dashboard): string
    {
        $path = $this->temporaryPath('pdf');
        file_put_contents($path, Pdf::loadView('librarian.executive-summary', compact('title', 'headers', 'rows', 'dashboard'))->setPaper('a4')->output());

        return $path;
    }

    private function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'executive-');
        abort_if($base === false, 500, 'Unable to create export file.');
        $path = $base.'.'.$extension;
        rename($base, $path);

        return $path;
    }
}
