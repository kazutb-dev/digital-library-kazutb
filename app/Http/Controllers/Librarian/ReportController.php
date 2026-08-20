<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\InventorySession;
use App\Models\Catalog\Loan;
use App\Models\Catalog\Reservation;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryVisitService;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\UdcClassificationService;
use App\Support\Csv;
use App\Support\DatabaseSchema;
use App\Support\OfficeOpenXmlExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operational reports for the librarian workspace (Historical 22.2),
 * Every definition has its own registry permission. The route boundary only
 * establishes an authenticated staff session; this controller enforces the
 * least-privilege report scope before reading any dataset.
 */
class ReportController extends Controller
{
    public function __construct(private readonly LibraryVisitService $visits) {}

    public function index(Request $request, LibraryReportService $reports, ReportRegistry $registry): View
    {
        $request->validate([
            'report' => ['nullable', Rule::in($registry->codes())],
            'sort' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in([25, 50, 100])],
        ]);
        $filters = ReportFilters::fromRequest($request);
        $type = (string) ($request->input('report') ?: collect($registry->all())
            ->first(fn ($definition): bool => $registry->allows($request->user(), $definition))?->code);
        abort_unless($type !== '', 403);
        abort_unless($registry->allows($request->user(), $type), 403);
        $report = $reports->build($type, $filters, $request->user());
        $report = $this->screenRows($request, $report);

        $attendance = $registry->allows($request->user(), 'visits')
            ? $this->attendanceData($filters)
            : ['canViewAttendance' => false];
        $attendance['canViewAttendance'] ??= true;

        return view('librarian.reports.index', array_merge($report, $attendance, [
            // Kept for older integrations which consumed these two variables
            // from the operational report page.
            'from' => $filters->from,
            'to' => $filters->to,
        ]));
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    private function screenRows(Request $request, array $report): array
    {
        $columnKeys = collect($report['columns'] ?? [])->pluck('key')->map(fn (mixed $key): string => (string) $key);
        $sort = trim((string) $request->input('sort', ''));
        if ($sort !== '' && ! $columnKeys->containsStrict($sort)) {
            throw ValidationException::withMessages(['sort' => __('validation.in', ['attribute' => 'sort'])]);
        }
        $direction = (string) $request->input('direction', 'asc');
        $rows = collect($report['rows'] ?? []);
        if ($sort !== '') {
            $rows = $rows->sortBy(
                static function (array $row) use ($sort): int|float|string {
                    $value = data_get($row, $sort);

                    return is_numeric($value) ? (float) $value : mb_strtolower((string) $value);
                },
                SORT_NATURAL | SORT_FLAG_CASE,
                $direction === 'desc',
            )->values();
        }

        $perPage = (int) $request->input('per_page', 25);
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min((int) $request->input('page', 1), $lastPage);
        $offset = ($page - 1) * $perPage;
        $report['rows'] = $rows->slice($offset, $perPage)->values()->all();
        $report['sorting'] = ['key' => $sort, 'direction' => $direction];
        $report['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];

        return $report;
    }

    public function export(
        Request $request,
        string $type,
        AuditLogger $audit,
        UdcClassificationService $classification,
        LibraryReportService $reports,
        ReportRegistry $registry,
        OfficeOpenXmlExporter $office,
        ?string $format = null,
    ): Response {
        $format = mb_strtolower($format ?: 'csv');
        $legacyTypes = ['popular', 'dynamics', 'udc-fund', 'circulation'];
        abort_unless(in_array($type, [...$registry->codes(), ...$legacyTypes], true), 404);
        if (in_array($type, $legacyTypes, true)) {
            abort_unless($request->user()?->canAny(['reports.view_ops', 'reports.view_full']), 403);
        }
        if ($type === 'incidents' && ($format === 'csv' || $format === 'pdf' || $format === 'xlsx' || $format === 'docx')) {
            abort_unless($request->user()?->canAny(['incidents.view_reports', 'reports.view_full']), 403);
        }

        $filters = ReportFilters::fromRequest($request);

        if (in_array($type, LibraryReportService::TYPES, true)) {
            abort_unless($registry->allows($request->user(), $type), 403);
            abort_unless(in_array($format, ['csv', 'pdf', 'xlsx', 'docx'], true), 404);
            $report = $reports->build($type, $filters, $request->user());

            $audit->logRequired(
                actionType: 'export',
                entityType: 'report',
                entityId: 'librarian:'.$type,
                newValues: [
                    'format' => $format,
                    'filters' => $filters->toArray(),
                    'from_utc' => $filters->from->toIso8601String(),
                    'to_utc' => $filters->to->toIso8601String(),
                    'rows' => count($report['rows']),
                ],
                scope: 'operational',
            );

            return $this->canonicalExport($type, $format, $report, $reports, $office);
        }

        abort_unless($format === 'csv', 404);
        $from = $filters->from;
        $to = $filters->to;

        $audit->logRequired(
            actionType: 'export',
            entityType: 'report',
            entityId: 'librarian:'.$type,
            newValues: [
                'format' => 'csv',
                'filters' => $filters->toArray(),
                'from_utc' => $from->toIso8601String(),
                'to_utc' => $to->toIso8601String(),
            ],
            scope: 'operational',
        );

        return response()->streamDownload(function () use ($type, $from, $to, $classification, $request): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");

            match ($type) {
                'popular' => $this->exportPopular($output, $from, $to),
                'dynamics' => $this->exportDynamics($output, $from, $to),
                'udc-fund' => $this->exportUdcFund($output, $classification),
                'visits' => $this->exportVisits($output, $from, $to),
                'incidents' => $this->exportIncidents($output, $request, $from, $to),
                'reservations' => $this->exportReservations($output, $from, $to),
                'circulation' => $this->exportCirculation($output, $from, $to),
                'inventory' => $this->exportInventory($output, $from, $to),
            };

            fclose($output);
        }, 'librarian-report-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(
        Request $request,
        string $type,
        AuditLogger $audit,
        LibraryReportService $reports,
        ReportRegistry $registry,
    ): View {
        abort_unless(in_array($type, $registry->codes(), true), 404);
        abort_unless($registry->allows($request->user(), $type), 403);
        $filters = ReportFilters::fromRequest($request);
        $report = $reports->build($type, $filters, $request->user());
        $generatedAt = now((string) config('app.library_timezone', 'Asia/Almaty'));

        $audit->logRequired(
            actionType: 'print',
            entityType: 'report',
            entityId: 'librarian:'.$type,
            newValues: ['format' => 'print', 'filters' => $filters->toArray(), 'rows' => count($report['rows'])],
            scope: 'operational',
        );

        return view('librarian.reports.print', array_merge($report, [
            'report' => $report,
            'reportTitle' => $reports->title($type),
            'generatedAt' => $generatedAt,
            'printMode' => true,
        ]));
    }

    /** @param array<string, mixed> $report */
    private function canonicalExport(
        string $type,
        string $format,
        array $report,
        LibraryReportService $reports,
        OfficeOpenXmlExporter $office,
    ): Response {
        $timestamp = now('UTC')->format('Ymd-His');
        $filename = "library-report-{$type}-{$timestamp}";
        $columns = collect($report['columns']);
        $headers = $columns->pluck('label')->map(fn (mixed $label): string => (string) $label)->all();
        $rows = collect($report['rows'])->map(fn (array $row): array => $columns
            ->map(fn (array $column): mixed => data_get($row, $column['key']))->all());

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $output = fopen('php://output', 'wb');
                fwrite($output, "\xEF\xBB\xBF");
                Csv::writeRow($output, $headers);
                foreach ($rows as $row) {
                    Csv::writeRow($output, $row);
                }
                fclose($output);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($format === 'pdf') {
            return Pdf::loadView('librarian.reports.document', array_merge($report, [
                'report' => $report,
                'reportTitle' => $reports->title($type),
                'generatedAt' => now((string) config('app.library_timezone', 'Asia/Almaty')),
                'printMode' => false,
            ]))->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        $path = $format === 'xlsx'
            ? $office->xlsx($reports->title($type), $headers, $rows)
            : $office->docx($reports->title($type), $headers, $rows, $report['filters']);
        $mime = $format === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return response()->download($path, $filename.'.'.$format, ['Content-Type' => $mime])->deleteFileAfterSend(true);
    }

    /** @return array<string, mixed> */
    private function attendanceData(ReportFilters $filters): array
    {
        if (! DatabaseSchema::hasTable('library_visits')) {
            return [
                'visitSummary' => ['total' => 0, 'visits' => 0, 'unique_readers' => 0, 'busiest_day' => null, 'busiest_day_visits' => 0],
                'visitSeries' => collect(),
                'visitSeriesIsWeekly' => false,
                'visitBranches' => collect(),
            ];
        }

        $days = $this->visits->dailyTotals($filters->from, $filters->to);
        $weekly = $days->count() > 45;
        $summary = $this->visits->summary($filters->from, $filters->to);
        $summary['total'] = $summary['visits'];

        return [
            'visitSummary' => $summary,
            'visitSeries' => $weekly ? $this->visits->weeklyTotals($filters->from, $filters->to) : $days,
            'visitSeriesIsWeekly' => $weekly,
            'visitBranches' => $this->visits->branchTotals($filters->from, $filters->to),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $from = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    private function exportPopular($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, [__('librarian.reports.columns.title'), __('librarian.reports.columns.author'), __('librarian.reports.columns.issues')]);
        Loan::query()
            ->join('book_copies', 'book_copies.id', '=', 'loans.copy_id')
            ->join('bibliographic_records', 'bibliographic_records.id', '=', 'book_copies.bibliographic_record_id')
            ->whereBetween('loans.issued_at', [$from, $to])
            ->selectRaw('bibliographic_records.title, bibliographic_records.primary_author, count(*) as issues')
            ->groupBy('bibliographic_records.title', 'bibliographic_records.primary_author')
            ->orderByDesc('issues')
            ->limit(100)
            ->get()
            ->each(fn ($row) => Csv::writeRow($output, [$row->title, $row->primary_author, $row->issues]));
    }

    private function exportFundUsage($output): void
    {
        Csv::writeRow($output, [__('librarian.reports.columns.fund'), __('librarian.reports.columns.copies'), __('librarian.reports.columns.on_loan')]);
        BookCopy::query()
            ->leftJoin('funds', 'funds.id', '=', 'book_copies.fund_id')
            ->selectRaw("COALESCE(funds.name, '—') as fund, count(*) as copies, sum(CASE WHEN book_copies.status IN ('issued', 'overdue') THEN 1 ELSE 0 END) as on_loan")
            ->groupBy('funds.name')
            ->orderByDesc('copies')
            ->get()
            ->each(fn ($row) => Csv::writeRow($output, [$row->fund, $row->copies, $row->on_loan]));
    }

    private function exportDynamics($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, [__('librarian.reports.columns.day'), __('librarian.reports.columns.issued'), __('librarian.reports.columns.returned')]);
        $issued = Loan::query()->selectRaw('DATE(issued_at) as day, count(*) as total')->whereBetween('issued_at', [$from, $to])->groupBy('day')->pluck('total', 'day');
        $returned = Loan::query()->selectRaw('DATE(returned_at) as day, count(*) as total')->whereBetween('returned_at', [$from, $to])->groupBy('day')->pluck('total', 'day');
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();
            Csv::writeRow($output, [$day, $issued[$day] ?? 0, $returned[$day] ?? 0]);
            $cursor->addDay();
        }
    }

    private function exportAcquisitions($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, [__('librarian.reports.columns.source'), __('librarian.reports.columns.copies'), __('librarian.reports.columns.total_price')]);
        BookCopy::query()
            ->whereBetween('registration_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("COALESCE(acquisition_source, '—') as source, count(*) as copies, COALESCE(sum(price), 0) as total_price")
            ->groupBy('acquisition_source')
            ->orderByDesc('copies')
            ->get()
            ->each(fn ($row) => Csv::writeRow($output, [$row->source, $row->copies, $row->total_price]));
    }

    /**
     * 9.4 attendance export: the daily series first, then the branch split, so
     * one file answers both "when" and "where".
     */
    private function exportVisits($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, [
            __('librarian.reports.columns.day'),
            __('librarian.reports.columns.visits'),
        ]);
        $this->visits->dailyTotals($from, $to)->each(
            fn (int $total, string $day) => Csv::writeRow($output, [$day, $total]),
        );

        Csv::writeRow($output, []);
        Csv::writeRow($output, [
            __('librarian.reports.columns.branch'),
            __('librarian.reports.columns.visits'),
            __('librarian.reports.columns.unique_readers'),
        ]);
        $this->visits->branchTotals($from, $to)->each(
            fn ($row) => Csv::writeRow($output, [$row->branch, $row->visits, $row->readers]),
        );
    }

    private function exportUdcFund($output, UdcClassificationService $classification): void
    {
        Csv::writeRow($output, ['Класс УДК', 'Описание', 'Направление', 'Библиографические записи', 'Экземпляры']);
        $classification->reportRows()->each(
            fn ($row) => Csv::writeRow($output, [
                $row->code,
                $row->description,
                $row->department,
                $row->records,
                $row->copies,
            ]),
        );
    }

    /** @return array<string, mixed> */
    private function incidentMetrics(Request $request, Carbon $from, Carbon $to): array
    {
        $query = $this->incidentQuery($request, $from, $to);
        $rows = (clone $query)->with(['originalCopy.bibliographicRecord', 'fine'])->get();

        return [
            'total' => $rows->count(),
            'open' => $rows->whereIn('status', CirculationIncidentCase::OPEN_STATUSES)->count(),
            'lost' => $rows->where('incident_type', 'lost')->count(),
            'damaged' => $rows->where('incident_type', 'damaged')->count(),
            'accepted' => $rows->whereNotNull('replacement_copy_id')->count(),
            'rejected' => $rows->where('status', 'rejected')->count() + $rows->sum(fn ($case) => $case->candidates()->where('status', 'rejected')->count()),
            'monetary' => $rows->where('resolution_type', 'monetary_compensation')->count(),
            'fines' => (float) $rows->sum(fn ($case) => (float) ($case->fine?->amount ?? 0)),
            'written_off' => $rows->where('resolution_type', 'write_off')->count(),
            'under_repair' => $rows->filter(fn ($case) => $case->originalCopy?->status === 'under_repair')->count(),
            'overdue' => $rows->filter(fn ($case) => in_array($case->status, CirculationIncidentCase::OPEN_STATUSES, true) && $case->resolution_due_at?->isPast())->count(),
            'average_hours' => round((float) $rows->whereNotNull('resolved_at')->avg(fn ($case) => $case->opened_at->diffInHours($case->resolved_at)), 1),
            'statuses' => $rows->groupBy('status')->map->count(),
            'branches' => $rows->groupBy(fn ($case) => $case->originalCopy?->branch?->name ?? '—')->map->count()->sortDesc(),
            'frequent' => $rows->groupBy(fn ($case) => $case->originalCopy?->bibliographicRecord?->title ?? '—')->map->count()->sortDesc()->take(10),
        ];
    }

    private function incidentQuery(Request $request, Carbon $from, Carbon $to)
    {
        return CirculationIncidentCase::query()
            ->whereBetween('opened_at', [$from, $to])
            ->when($request->filled('incident_type'), fn ($q) => $q->where('incident_type', $request->string('incident_type')))
            ->when($request->filled('incident_status'), fn ($q) => $q->where('status', $request->string('incident_status')))
            ->when($request->filled('incident_resolution'), fn ($q) => $q->where('resolution_type', $request->string('incident_resolution')))
            ->when($request->filled('incident_assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('incident_assigned_to')))
            ->when($request->filled('incident_branch_id'), fn ($q) => $q->whereHas('originalCopy', fn ($c) => $c->where('branch_id', $request->integer('incident_branch_id'))))
            ->when($request->filled('reader_category'), fn ($q) => $q->whereHas('reader.readerProfile', fn ($p) => $p->where('category', $request->string('reader_category'))))
            ->when($request->filled('incident_udc'), fn ($q) => $q->whereHas('originalCopy.bibliographicRecord', fn ($r) => $r->where('udc_code', 'like', $request->string('incident_udc').'%')))
            ->when($request->filled('fund_type'), fn ($q) => $q->whereHas('originalCopy.fund', fn ($f) => $f->where('fund_type', $request->string('fund_type'))));
    }

    private function exportIncidents($output, Request $request, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, ['Case', 'Opened', 'Type', 'Status', 'Reader', 'Category', 'Title', 'Inventory', 'Branch', 'Resolution', 'Fine', 'Resolved']);
        $this->incidentQuery($request, $from, $to)
            ->with(['reader.readerProfile', 'originalCopy.bibliographicRecord', 'originalCopy.branch', 'fine'])
            ->orderBy('opened_at')->each(fn ($case) => Csv::writeRow($output, [
                $case->case_number, $case->opened_at?->toIso8601String(), $case->incident_type, $case->status,
                $case->reader?->name, $case->reader?->readerProfile?->category,
                $case->originalCopy?->bibliographicRecord?->title, $case->originalCopy?->inventory_number,
                $case->originalCopy?->branch?->name, $case->resolution_type,
                $case->fine?->amount, $case->resolved_at?->toIso8601String(),
            ]));
    }

    private function exportReservations($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, ['Reservation', 'Created', 'Status', 'Reader', 'Ticket', 'Title', 'Copy', 'Pickup branch', 'Queued', 'Ready', 'Expires']);
        Reservation::query()->whereBetween('created_at', [$from, $to])->with(['reader.readerProfile', 'bibliographicRecord', 'assignedCopy', 'pickupBranch'])
            ->orderBy('created_at')->each(fn ($r) => Csv::writeRow($output, [$r->reservation_number, $r->created_at?->toIso8601String(), $r->status, $r->reader?->name, $r->reader?->readerProfile?->ticket_number, $r->bibliographicRecord?->title, $r->assignedCopy?->inventory_number, $r->pickupBranch?->name, $r->queued_at?->toIso8601String(), $r->ready_at?->toIso8601String(), $r->expires_at?->toIso8601String()]));
    }

    private function exportCirculation($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, ['Loan', 'Issued', 'Due', 'Returned', 'Status', 'Renewals', 'Reader', 'Title', 'Inventory', 'Fine']);
        Loan::query()->whereBetween('issued_at', [$from, $to])->with(['reader', 'copy.bibliographicRecord', 'fines'])->orderBy('issued_at')
            ->each(fn ($l) => Csv::writeRow($output, [$l->id, $l->issued_at?->toIso8601String(), $l->due_at?->toIso8601String(), $l->returned_at?->toIso8601String(), $l->status, $l->renewal_count, $l->reader?->name, $l->copy?->bibliographicRecord?->title, $l->copy?->inventory_number, $l->fines->sum('amount')]));
    }

    private function exportInventory($output, Carbon $from, Carbon $to): void
    {
        Csv::writeRow($output, ['Session', 'Date', 'Status', 'Branch', 'Expected', 'Found', 'Missing', 'Misplaced', 'Unknown', 'Duplicates']);
        InventorySession::query()->whereBetween('inventory_date', [$from->toDateString(), $to->toDateString()])->with('branch')->orderBy('inventory_date')
            ->each(fn ($s) => Csv::writeRow($output, [$s->session_number, $s->inventory_date?->toDateString(), $s->status, $s->branch?->name, $s->expected_count, $s->found_count, $s->missing_count, $s->misplaced_count, $s->unknown_count, $s->duplicate_count]));
    }
}
