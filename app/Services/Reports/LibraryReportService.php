<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Support\DatabaseSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * One read boundary for the four official operational library reports.
 *
 * Every source is a canonical application table. Optional sources are guarded
 * individually: an installation can introduce circulation, visits, digital
 * logs, or external-resource events in stages without taking Reports down.
 */
final class LibraryReportService
{
    public const OFFICIAL_TYPES = ReportRegistry::OFFICIAL_CODES;

    public const OPERATIONAL_TYPES = ReportRegistry::OPERATIONAL_CODES;

    public const TYPES = [...self::OFFICIAL_TYPES, ...self::OPERATIONAL_TYPES];

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly OperationalReportService $operational,
        private readonly CollectionAccountingReportService $collectionAccounting,
    ) {}

    /** @return array<string, mixed> */
    public function build(string $type, ReportFilters $filters, ?User $viewer = null): array
    {
        abort_unless($this->registry->find($type) !== null, 404);

        // Missing optional tables are handled inside each builder. Once a
        // source exists, SQL/programming errors must fail loudly: silently
        // turning them into an official-looking zero report is unsafe.
        $report = $this->dataset($type, $filters);

        return array_merge([
            'reportTypes' => $this->reportTypes($viewer),
            'activeReport' => $type,
            'filters' => $filters->toArray(),
            'filterOptions' => $this->filterOptions(),
            'supportedFilters' => $this->registry->get($type)->filters,
        ], $report, [
            // `cards` is the domain name used by API consumers; metrics is the
            // same canonical list rendered by the librarian workspace.
            'cards' => $report['metrics'],
        ]);
    }

    /** @return array<string, mixed> */
    public function dataset(string $type, ReportFilters $filters): array
    {
        abort_unless($this->registry->find($type) !== null, 404);
        $report = $this->collectionAccounting->supports($type)
            ? $this->collectionAccounting->build($type, $filters)
            : match ($type) {
                'acquisitions' => $this->acquisitions($filters),
                'fund-usage' => $this->fundUsage($filters),
                'users' => $this->users($filters),
                'electronic-resources' => $this->electronicResources($filters),
                default => $this->operational->build($type, $filters),
            };

        $maximum = max(100, (int) config('library.reports.max_live_rows', 10000));
        if (count($report['rows'] ?? []) > $maximum) {
            throw new ReportLimitExceeded("The report contains more than {$maximum} aggregate rows.");
        }

        return $report;
    }

    /** @return list<array{key: string, label: string, description: string, frequency: string, official: string, is_official: bool}> */
    public function reportTypes(?User $viewer = null): array
    {
        return collect($this->registry->all())
            ->filter(fn (ReportDefinition $definition): bool => $viewer === null || $this->registry->allows($viewer, $definition))
            ->map(fn (ReportDefinition $definition): array => [
                'key' => $definition->code,
                'label' => $this->label("analytics.reports.{$definition->code}.short", Str::headline($definition->code)),
                'description' => $this->label($definition->descriptionKey, ''),
                'frequency' => $this->label("analytics.reports.{$definition->code}.frequency", ''),
                'official' => $this->label(
                    "analytics.reports.{$definition->code}.official",
                    $this->label($definition->official ? 'analytics.groups.official_badge' : 'analytics.groups.operational_badge', ''),
                ),
                'is_official' => $definition->official,
            ])->values()->all();
    }

    public function title(string $type): string
    {
        $definition = $this->registry->find($type);
        abort_unless($definition !== null, 404);

        return $this->label($definition->titleKey, Str::headline($type));
    }

    /** @return array<string, list<array{value: string|int, label: string}>> */
    public function filterOptions(): array
    {
        $branches = $this->optionsFromTable('branches', 'id', 'name', ['is_active' => true]);
        $funds = $this->optionsFromTable('funds', 'id', 'name', ['is_active' => true]);

        $resourceTypes = $this->distinctOptions([
            ['bibliographic_records', 'resource_type'],
            ['electronic_materials', 'material_type'],
            ['external_resources', 'resource_type'],
            ['repository_items', 'work_type'],
        ]);
        $languages = $this->distinctOptions([
            ['bibliographic_records', 'language'],
            ['electronic_materials', 'language'],
            ['repository_items', 'language'],
        ]);
        $statuses = $this->distinctOptions([
            ['book_copies', 'status'],
            ['reader_profiles', 'status'],
            ['loans', 'status'],
            ['fines', 'status'],
            ['reservations', 'status'],
            ['circulation_incident_cases', 'status'],
            ['inventory_sessions', 'status'],
            ['data_quality_issues', 'status'],
            ['news', 'status'],
            ['contact_messages', 'status'],
            ['acquisition_batches', 'status'],
            ['ksu_entries', 'status'],
            ['electronic_materials', 'workflow_status'],
            ['external_resources', 'publication_status'],
            ['repository_items', 'status'],
        ]);
        $statuses = collect($statuses)->map(fn (array $option): array => [
            'value' => $option['value'],
            'label' => $this->optionLabel('analytics.statuses', (string) $option['value']),
        ])->all();
        $subjects = $this->distinctOptions([
            ['bibliographic_records', 'category'],
            ['external_resources', 'category'],
            ['repository_items', 'department'],
        ]);
        $accessTypes = $this->distinctOptions([
            ['book_copies', 'access_restriction'],
            ['electronic_materials', 'access_level'],
            ['external_resources', 'resource_type'],
            ['external_resources', 'access_type'],
            ['repository_items', 'access_policy'],
        ], ReportFilters::ACCESS_TYPES);

        return [
            'preset' => $this->translatedOptions(ReportFilters::PRESETS, 'analytics.presets'),
            'branch_id' => $branches,
            'fund_id' => $funds,
            'resource_type' => $resourceTypes,
            'user_segment' => $this->translatedOptions(ReportFilters::USER_SEGMENTS, 'analytics.segments'),
            'language' => $languages,
            'udc' => $this->distinctOptions([['bibliographic_records', 'udc_code'], ['repository_items', 'udc_code']]),
            'status' => $statuses,
            'subject' => $subjects,
            'access_type' => $accessTypes,
            'operation' => $this->translatedOptions(ReportFilters::OPERATIONS, 'analytics.operations'),
            'acquisition_source' => $this->translatedOptions(ReportFilters::ACQUISITION_SOURCES, 'analytics.sources'),
        ];
    }

    /** @return array<string, mixed> */
    private function acquisitions(ReportFilters $filters): array
    {
        if (! $this->hasTable('book_copies')) {
            return $this->emptyReport($this->acquisitionColumns(), ['copies', 'records', 'value', 'sources', 'total']);
        }

        $hasRecords = $this->hasTable('bibliographic_records');
        $hasBranches = $this->hasTable('branches');
        $hasFunds = $this->hasTable('funds');
        $hasSupplier = $this->hasColumn('book_copies', 'supplier_name');
        $hasKsuNumber = $this->hasColumn('book_copies', 'ksu_number');
        $dateFrom = $this->localDate($filters->from);
        $dateTo = $this->localDate($filters->to);
        $sourceSql = "CASE WHEN copies.acquisition_source = 'gift' THEN 'donation' ELSE COALESCE(copies.acquisition_source, 'other') END";
        $dateSql = 'COALESCE(copies.registration_date, copies.acquisition_date)';

        $query = DB::table('book_copies as copies');
        if ($hasRecords) {
            $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
        }
        if ($hasBranches) {
            $query->leftJoin('branches as branches', 'branches.id', '=', 'copies.branch_id');
        }
        if ($hasFunds) {
            $query->leftJoin('funds as funds', 'funds.id', '=', 'copies.fund_id');
        }

        $query->where(function (Builder $period) use ($dateFrom, $dateTo): void {
            $period->whereBetween('copies.registration_date', [$dateFrom, $dateTo])
                ->orWhere(function (Builder $fallback) use ($dateFrom, $dateTo): void {
                    $fallback->whereNull('copies.registration_date')
                        ->whereBetween('copies.acquisition_date', [$dateFrom, $dateTo]);
                });
        });
        $this->applyCopyFilters($query, $filters, $hasRecords);
        $recordCount = (int) (clone $query)
            ->whereNotNull('copies.bibliographic_record_id')
            ->distinct()
            ->count('copies.bibliographic_record_id');

        $rows = $query
            ->selectRaw("{$dateSql} as received_date")
            ->selectRaw("{$sourceSql} as acquisition_source")
            ->selectRaw($hasRecords ? "COALESCE(records.resource_type, 'book') as resource_type" : "'book' as resource_type")
            ->selectRaw($hasBranches ? "COALESCE(branches.name, '—') as branch" : "'—' as branch")
            ->selectRaw($hasFunds ? "COALESCE(funds.name, '—') as fund" : "'—' as fund")
            ->selectRaw($hasSupplier ? "COALESCE(copies.supplier_name, '—') as supplier" : "'—' as supplier")
            ->selectRaw($hasKsuNumber ? "COALESCE(copies.ksu_number, '—') as ksu_number" : "'—' as ksu_number")
            ->selectRaw('COUNT(DISTINCT copies.bibliographic_record_id) as records')
            ->selectRaw('COUNT(*) as copies')
            ->selectRaw('COALESCE(SUM(copies.price), 0) as total_value')
            ->groupByRaw($dateSql)
            ->groupByRaw($sourceSql)
            ->when($hasRecords, fn (Builder $q) => $q->groupBy('records.resource_type'))
            ->when($hasBranches, fn (Builder $q) => $q->groupBy('branches.name'))
            ->when($hasFunds, fn (Builder $q) => $q->groupBy('funds.name'))
            ->when($hasSupplier, fn (Builder $q) => $q->groupBy('copies.supplier_name'))
            ->when($hasKsuNumber, fn (Builder $q) => $q->groupBy('copies.ksu_number'))
            ->orderByDesc('received_date')
            ->orderBy('acquisition_source')
            ->get()
            ->map(fn (object $row): array => [
                'received_date' => (string) $row->received_date,
                'acquisition_source' => $this->optionLabel('analytics.sources', (string) $row->acquisition_source),
                'resource_type' => $this->displayValue((string) $row->resource_type),
                'branch' => (string) $row->branch,
                'fund' => (string) $row->fund,
                'supplier' => (string) $row->supplier,
                'ksu_number' => (string) $row->ksu_number,
                'records' => (int) $row->records,
                'copies' => (int) $row->copies,
                'total_value' => round((float) $row->total_value, 2),
            ]);

        $copies = (int) $rows->sum('copies');
        $value = round((float) $rows->sum('total_value'), 2);
        $sourceItems = $this->breakdownItems($rows, 'acquisition_source', 'copies');
        $resourceItems = $this->breakdownItems($rows, 'resource_type', 'copies');

        return [
            'metrics' => [
                $this->metric('copies', $copies),
                $this->metric('records', $recordCount),
                $this->metric('value', $value),
                $this->metric('sources', $sourceItems->count()),
                $this->metric('total', $rows->count()),
            ],
            'columns' => $this->acquisitionColumns(),
            'rows' => $rows->values()->all(),
            'breakdowns' => [
                $this->breakdown('sources', $this->label('analytics.filters.acquisition_source', 'Acquisition source'), $sourceItems),
                $this->breakdown('resource_types', $this->label('analytics.filters.resource_type', 'Resource type'), $resourceItems),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function fundUsage(ReportFilters $filters): array
    {
        if (! $this->hasTable('book_copies')) {
            return $this->emptyReport($this->fundUsageColumns(), ['copies', 'issued', 'returned', 'renewals', 'reservations', 'visits']);
        }

        $hasRecords = $this->hasTable('bibliographic_records');
        $hasBranches = $this->hasTable('branches');
        $hasFunds = $this->hasTable('funds');
        $base = DB::table('book_copies as copies');
        if ($hasRecords) {
            $base->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
        }
        if ($hasBranches) {
            $base->leftJoin('branches as branches', 'branches.id', '=', 'copies.branch_id');
        }
        if ($hasFunds) {
            $base->leftJoin('funds as funds', 'funds.id', '=', 'copies.fund_id');
        }
        $this->applyCopyFilters($base, $filters, $hasRecords);

        $holdings = (clone $base)
            ->selectRaw('copies.fund_id')
            ->selectRaw('copies.branch_id')
            ->selectRaw($hasFunds ? "COALESCE(funds.name, '—') as fund" : "'—' as fund")
            ->selectRaw($hasBranches ? "COALESCE(branches.name, '—') as branch" : "'—' as branch")
            ->selectRaw('COUNT(*) as copies')
            ->selectRaw("SUM(CASE WHEN copies.status IN ('issued', 'overdue') THEN 1 ELSE 0 END) as on_loan")
            ->groupBy('copies.fund_id')
            ->groupBy('copies.branch_id')
            ->when($hasFunds, fn (Builder $q) => $q->groupBy('funds.name'))
            ->when($hasBranches, fn (Builder $q) => $q->groupBy('branches.name'))
            ->get();

        $issued = $filters->permitsOperation('issue')
            ? $this->loanTotalsByFund($filters, 'issued_at')
            : collect();
        $returned = $filters->permitsOperation('return')
            ? $this->loanTotalsByFund($filters, 'returned_at')
            : collect();
        $renewals = $filters->permitsOperation('renewal')
            ? $this->loanRenewalsByFund($filters)
            : collect();
        $reservations = $filters->permitsOperation('reservation')
            ? $this->reservationTotalsByFund($filters)
            : collect();

        $holdingByFund = $holdings->keyBy(fn (object $row): string => $this->fundBranchKey($row->fund_id, $row->branch_id));
        $keys = $holdingByFund->keys()->merge($issued->keys())->merge($returned->keys())
            ->merge($renewals->keys())->merge($reservations->keys())->unique();
        $rows = $keys->map(function (string $key) use ($holdingByFund, $issued, $returned, $renewals, $reservations): array {
            $holding = $holdingByFund->get($key);
            $copies = (int) ($holding->copies ?? 0);
            $onLoan = (int) ($holding->on_loan ?? 0);
            $issuedCount = (int) $issued->get($key, 0);

            return [
                'fund' => (string) ($holding->fund ?? '—'),
                'branch' => (string) ($holding->branch ?? '—'),
                'copies' => $copies,
                'on_loan' => $onLoan,
                'issued' => $issuedCount,
                'returned' => (int) $returned->get($key, 0),
                'renewals' => (int) $renewals->get($key, 0),
                'reservations' => (int) $reservations->get($key, 0),
                'usage_rate' => $copies > 0 ? round($onLoan / $copies * 100, 1) : 0.0,
                'turnover_rate' => $copies > 0 ? round($issuedCount / $copies, 2) : 0.0,
            ];
        })->sortByDesc('issued')->values();

        $visits = $filters->permitsOperation('visit') ? $this->visitTotal($filters) : 0;
        $statusItems = $this->copyStatusBreakdown($base);
        $operationItems = collect([
            ['label' => $this->optionLabel('analytics.operations', 'issue'), 'value' => (int) $rows->sum('issued')],
            ['label' => $this->optionLabel('analytics.operations', 'return'), 'value' => (int) $rows->sum('returned')],
            ['label' => $this->optionLabel('analytics.operations', 'renewal'), 'value' => (int) $rows->sum('renewals')],
            ['label' => $this->optionLabel('analytics.operations', 'reservation'), 'value' => (int) $rows->sum('reservations')],
            ['label' => $this->optionLabel('analytics.operations', 'visit'), 'value' => $visits],
        ]);

        return [
            'metrics' => [
                $this->metric('copies', (int) $rows->sum('copies')),
                $this->metric('issued', (int) $rows->sum('issued')),
                $this->metric('returned', (int) $rows->sum('returned')),
                $this->metric('renewals', (int) $rows->sum('renewals')),
                $this->metric('reservations', (int) $rows->sum('reservations')),
                $this->metric('visits', $visits),
            ],
            'columns' => $this->fundUsageColumns(),
            'rows' => $rows->all(),
            'breakdowns' => [
                $this->breakdown('operations', $this->label('analytics.filters.operation', 'Operation'), $operationItems),
                $this->breakdown('statuses', $this->label('analytics.filters.status', 'Status'), $statusItems),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function users(ReportFilters $filters): array
    {
        if (! $this->hasTable('users')) {
            return $this->emptyReport($this->userColumns(), ['total', 'active_users', 'unique_users', 'visits']);
        }

        $hasProfiles = $this->hasTable('reader_profiles');
        $segmentSql = $hasProfiles
            ? "COALESCE(profiles.category, CASE WHEN users.role = 'reader' THEN 'student' ELSE 'staff' END)"
            : "CASE WHEN users.role = 'reader' THEN 'student' ELSE 'staff' END";
        $query = DB::table('users as users');
        if ($hasProfiles) {
            $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'users.id');
        }
        if ($filters->userSegment !== null) {
            $query->whereRaw("{$segmentSql} = ?", [$filters->userSegment]);
        }
        if ($filters->branchId !== null) {
            if ($hasProfiles && $this->hasColumn('reader_profiles', 'preferred_branch_id')) {
                $query->where('profiles.preferred_branch_id', $filters->branchId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        $this->applyUserStatusFilter($query, $filters, $hasProfiles);

        $registrations = $query
            ->selectRaw("{$segmentSql} as segment")
            ->selectRaw('COUNT(*) as users')
            ->selectRaw('SUM(CASE WHEN users.is_active THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN users.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_users', [$filters->from, $filters->to])
            ->groupByRaw($segmentSql)
            ->get()
            ->keyBy('segment');

        $activities = $this->userActivity($filters);
        $segments = $registrations->keys()->merge($activities->keys())->unique();
        $rows = $segments->map(function (string $segment) use ($registrations, $activities): array {
            $registration = $registrations->get($segment);
            $activity = $activities->get($segment, ['visits' => 0, 'issued' => 0, 'returned' => 0, 'electronic' => 0, 'unique_users' => 0]);

            return [
                'user_segment' => $this->optionLabel('analytics.segments', $segment),
                'users' => (int) ($registration->users ?? 0),
                'active_users' => (int) ($registration->active ?? 0),
                'new_users' => (int) ($registration->new_users ?? 0),
                'unique_users' => (int) $activity['unique_users'],
                'visits' => (int) $activity['visits'],
                'issued' => (int) $activity['issued'],
                'returned' => (int) $activity['returned'],
                'electronic_actions' => (int) $activity['electronic'],
                'total_actions' => (int) $activity['visits'] + (int) $activity['issued'] + (int) $activity['returned'] + (int) $activity['electronic'],
            ];
        })->sortByDesc('total_actions')->values();

        $activityItems = collect([
            ['label' => $this->optionLabel('analytics.operations', 'visit'), 'value' => (int) $rows->sum('visits')],
            ['label' => $this->optionLabel('analytics.operations', 'issue'), 'value' => (int) $rows->sum('issued')],
            ['label' => $this->optionLabel('analytics.operations', 'return'), 'value' => (int) $rows->sum('returned')],
            ['label' => $this->label('analytics.reports.electronic-resources.short', 'Electronic'), 'value' => (int) $rows->sum('electronic_actions')],
        ]);
        $segmentItems = $rows->map(fn (array $row): array => ['label' => $row['user_segment'], 'value' => $row['total_actions']]);

        return [
            'metrics' => [
                $this->metric('total', (int) $rows->sum('users')),
                $this->metric('active_users', (int) $rows->sum('active_users')),
                $this->metric('unique_users', (int) $rows->sum('unique_users')),
                $this->metric('visits', (int) $rows->sum('visits')),
                $this->metric('issued', (int) $rows->sum('issued')),
                $this->metric('returned', (int) $rows->sum('returned')),
            ],
            'columns' => $this->userColumns(),
            'rows' => $rows->all(),
            'breakdowns' => [
                $this->breakdown('activities', $this->label('analytics.filters.operation', 'Operation'), $activityItems),
                $this->breakdown('segments', $this->label('analytics.filters.user_segment', 'User segment'), $segmentItems),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function electronicResources(ReportFilters $filters): array
    {
        $events = $this->digitalEvents($filters)
            ->concat($this->externalEvents($filters))
            ->concat($this->repositoryEvents($filters));

        $rows = $events->groupBy(fn (array $event): string => $event['source'].'|'.$event['resource_id'])
            ->map(function (Collection $resourceEvents): array {
                $first = $resourceEvents->first();
                $actions = $resourceEvents->groupBy('action')
                    ->map(fn (Collection $items): int => (int) $items->sum('event_count'));
                $denied = (int) $resourceEvents->filter(fn (array $event): bool => ! $event['allowed'] || in_array($event['action'], ['access_denied', 'expired_click'], true))->sum('event_count');
                $failures = (int) $resourceEvents->filter(fn (array $event): bool => in_array($event['action'], ['error', 'expired_click', 'unsafe_destination'], true))->sum('event_count');

                return [
                    'resource' => $first['resource'],
                    'source' => $first['source_label'],
                    'resource_type' => $this->displayValue($first['resource_type']),
                    'access_type' => $this->optionLabel('analytics.access_types', $first['access_type']),
                    'views' => (int) $actions->get('view', 0) + (int) $actions->get('stream', 0),
                    'downloads' => (int) $actions->get('download', 0),
                    'logins' => (int) $actions->get('login', 0) + (int) $actions->get('outbound_click', 0),
                    'denied' => $denied,
                    'failures' => $failures,
                    'total' => (int) $resourceEvents->sum('event_count'),
                    'unique_users' => $this->eventUniqueUsers($resourceEvents),
                ];
            })->sortByDesc('total')->values();

        $denied = (int) $events->filter(fn (array $event): bool => ! $event['allowed'] || in_array($event['action'], ['access_denied', 'expired_click'], true))->sum('event_count');
        $failures = (int) $events->whereIn('action', ['error', 'expired_click', 'unsafe_destination'])->sum('event_count');
        $licensed = (int) $events->where('access_type', 'licensed')->sum('event_count');
        $operationItems = $events->groupBy('action')->map(fn (Collection $items, string $action): array => [
            'label' => $this->optionLabel('analytics.operations', $action),
            'value' => (int) $items->sum('event_count'),
        ])->values()->sortByDesc('value')->values();
        $accessItems = $events->groupBy('access_type')->map(fn (Collection $items, string $access): array => [
            'label' => $this->optionLabel('analytics.access_types', $access),
            'value' => (int) $items->sum('event_count'),
        ])->values()->sortByDesc('value')->values();

        return [
            'metrics' => [
                $this->metric('total', (int) $events->sum('event_count')),
                $this->metric('views', (int) $rows->sum('views')),
                $this->metric('downloads', (int) $rows->sum('downloads')),
                $this->metric('logins', (int) $rows->sum('logins')),
                $this->metric('unique_users', $this->eventUniqueUsers($events)),
                $this->metric('denied', $denied),
                $this->metric('failures', $failures),
                $this->metric('licensed', $licensed),
            ],
            'columns' => $this->electronicColumns(),
            'rows' => $rows->all(),
            'breakdowns' => [
                $this->breakdown('operations', $this->label('analytics.filters.operation', 'Operation'), $operationItems),
                $this->breakdown('access_types', $this->label('analytics.filters.access_type', 'Access type'), $accessItems),
            ],
        ];
    }

    /** @return Collection<string, int> */
    private function loanTotalsByFund(ReportFilters $filters, string $dateColumn): Collection
    {
        if (! $this->hasTable('loans') || ! $this->hasTable('book_copies')) {
            return collect();
        }

        $hasRecords = $this->hasTable('bibliographic_records');
        $hasProfiles = $this->hasTable('reader_profiles');
        $query = DB::table('loans as loans')
            ->join('book_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->whereBetween("loans.{$dateColumn}", [$filters->from, $filters->to]);
        if ($hasRecords) {
            $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
        }
        if ($hasProfiles) {
            $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'loans.user_id');
        }
        $this->applyCopyFilters($query, $filters, $hasRecords);
        if ($filters->userSegment !== null) {
            $hasProfiles ? $query->where('profiles.category', $filters->userSegment) : $query->whereRaw('1 = 0');
        }

        return $query->selectRaw('copies.fund_id, copies.branch_id, COUNT(*) as aggregate')
            ->groupBy('copies.fund_id', 'copies.branch_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$this->fundBranchKey($row->fund_id, $row->branch_id) => (int) $row->aggregate]);
    }

    /** @return Collection<string, int> */
    private function loanRenewalsByFund(ReportFilters $filters): Collection
    {
        if (! $this->hasTable('loans') || ! $this->hasTable('book_copies')) {
            return collect();
        }

        $hasRecords = $this->hasTable('bibliographic_records');
        $hasProfiles = $this->hasTable('reader_profiles');
        $query = DB::table('loans as loans')
            ->join('book_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->whereBetween('loans.issued_at', [$filters->from, $filters->to]);
        $hasRecords && $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
        $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'loans.user_id');
        $this->applyCopyFilters($query, $filters, $hasRecords);
        $filters->userSegment !== null && ($hasProfiles
            ? $query->where('profiles.category', $filters->userSegment)
            : $query->whereRaw('1 = 0'));

        return $query->selectRaw('copies.fund_id, copies.branch_id, COALESCE(SUM(loans.renewal_count), 0) as aggregate')
            ->groupBy('copies.fund_id', 'copies.branch_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$this->fundBranchKey($row->fund_id, $row->branch_id) => (int) $row->aggregate]);
    }

    /** Assigned reservations can be attributed to an exact fund and branch. @return Collection<string, int> */
    private function reservationTotalsByFund(ReportFilters $filters): Collection
    {
        if (! $this->hasTable('reservations') || ! $this->hasTable('book_copies')) {
            return collect();
        }

        $hasRecords = $this->hasTable('bibliographic_records');
        $hasProfiles = $this->hasTable('reader_profiles');
        $query = DB::table('reservations as reservations')
            ->join('book_copies as copies', 'copies.id', '=', 'reservations.assigned_copy_id')
            ->whereBetween('reservations.created_at', [$filters->from, $filters->to]);
        $hasRecords && $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
        $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'reservations.user_id');
        $this->applyCopyFilters($query, $filters, $hasRecords);
        $filters->userSegment !== null && ($hasProfiles
            ? $query->where('profiles.category', $filters->userSegment)
            : $query->whereRaw('1 = 0'));

        return $query->selectRaw('copies.fund_id, copies.branch_id, COUNT(*) as aggregate')
            ->groupBy('copies.fund_id', 'copies.branch_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$this->fundBranchKey($row->fund_id, $row->branch_id) => (int) $row->aggregate]);
    }

    private function visitTotal(ReportFilters $filters): int
    {
        if (! $this->hasTable('library_visits') || $this->resourceSpecificFilters($filters)) {
            return 0;
        }

        $query = DB::table('library_visits as visits')->whereBetween('visits.scanned_at', [$filters->from, $filters->to]);
        if ($filters->branchId !== null) {
            $query->where('visits.branch_id', $filters->branchId);
        }
        if ($filters->userSegment !== null) {
            if (! $this->hasTable('reader_profiles')) {
                return 0;
            }
            $query->join('reader_profiles as profiles', 'profiles.user_id', '=', 'visits.user_id')
                ->where('profiles.category', $filters->userSegment);
        }

        return $query->count();
    }

    /** @return Collection<string, array{visits: int, issued: int, returned: int, electronic: int, unique_users: int, user_ids: list<int>}> */
    private function userActivity(ReportFilters $filters): Collection
    {
        $activities = collect();
        $append = function (string $segment, string $kind, int $count, array $userIds = []) use ($activities): void {
            $row = $activities->get($segment, [
                'visits' => 0, 'issued' => 0, 'returned' => 0, 'electronic' => 0,
                'unique_users' => 0, 'user_ids' => [],
            ]);
            $row[$kind] += $count;
            $row['user_ids'] = collect($row['user_ids'])->merge($userIds)->unique()->values()->all();
            $row['unique_users'] = count($row['user_ids']);
            $activities->put($segment, $row);
        };

        if ($filters->permitsOperation('visit') && $this->hasTable('library_visits') && ! $this->resourceSpecificFilters($filters)) {
            $query = DB::table('library_visits as events');
            $hasProfiles = $this->hasTable('reader_profiles');
            $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'events.user_id');
            $query->whereBetween('events.scanned_at', [$filters->from, $filters->to]);
            $filters->branchId !== null && $query->where('events.branch_id', $filters->branchId);
            $filters->userSegment !== null && ($hasProfiles ? $query->where('profiles.category', $filters->userSegment) : $query->whereRaw('1 = 0'));
            $segmentSql = $hasProfiles ? "COALESCE(profiles.category, 'student')" : "'student'";
            $query->selectRaw("{$segmentSql} as segment, COUNT(*) as aggregate")
                ->selectRaw($this->distinctUserIdsSql('events.user_id').' as user_ids')
                ->groupByRaw($segmentSql)->get()
                ->each(fn (object $row) => $append((string) $row->segment, 'visits', (int) $row->aggregate, $this->parseUserIds($row->user_ids)));
        }

        if ($filters->permitsOperation('issue') && $this->hasTable('loans') && $this->hasTable('book_copies')) {
            $hasProfiles = $this->hasTable('reader_profiles');
            $hasRecords = $this->hasTable('bibliographic_records');
            $query = DB::table('loans as events')->join('book_copies as copies', 'copies.id', '=', 'events.copy_id');
            $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'events.user_id');
            $hasRecords && $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
            $query->whereBetween('events.issued_at', [$filters->from, $filters->to]);
            $this->applyCopyFilters($query, $filters, $hasRecords);
            $filters->userSegment !== null && ($hasProfiles ? $query->where('profiles.category', $filters->userSegment) : $query->whereRaw('1 = 0'));
            $segmentSql = $hasProfiles ? "COALESCE(profiles.category, 'student')" : "'student'";
            $query->selectRaw("{$segmentSql} as segment, COUNT(*) as aggregate")
                ->selectRaw($this->distinctUserIdsSql('events.user_id').' as user_ids')
                ->groupByRaw($segmentSql)->get()
                ->each(fn (object $row) => $append((string) $row->segment, 'issued', (int) $row->aggregate, $this->parseUserIds($row->user_ids)));
        }

        if ($filters->permitsOperation('return') && $this->hasTable('loans') && $this->hasTable('book_copies')) {
            $hasProfiles = $this->hasTable('reader_profiles');
            $hasRecords = $this->hasTable('bibliographic_records');
            $query = DB::table('loans as events')->join('book_copies as copies', 'copies.id', '=', 'events.copy_id');
            $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'events.user_id');
            $hasRecords && $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id');
            $query->whereBetween('events.returned_at', [$filters->from, $filters->to]);
            $this->applyCopyFilters($query, $filters, $hasRecords);
            $filters->userSegment !== null && ($hasProfiles ? $query->where('profiles.category', $filters->userSegment) : $query->whereRaw('1 = 0'));
            $segmentSql = $hasProfiles ? "COALESCE(profiles.category, 'student')" : "'student'";
            $query->selectRaw("{$segmentSql} as segment, COUNT(*) as aggregate")
                ->selectRaw($this->distinctUserIdsSql('events.user_id').' as user_ids')
                ->groupByRaw($segmentSql)->get()
                ->each(fn (object $row) => $append((string) $row->segment, 'returned', (int) $row->aggregate, $this->parseUserIds($row->user_ids)));
        }

        $electronic = $this->digitalEvents($filters)
            ->concat($this->externalEvents($filters))
            ->concat($this->repositoryEvents($filters));
        $electronic->groupBy('segment')->each(
            fn (Collection $items, string $segment) => $append(
                $segment ?: 'guest',
                'electronic',
                (int) $items->sum('event_count'),
                $items->flatMap(static fn (array $event): array => $event['user_ids'] ?? [])->unique()->values()->all(),
            ),
        );

        return $activities;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function digitalEvents(ReportFilters $filters): Collection
    {
        if (! $this->hasTable('digital_material_access_logs') || ! $this->hasTable('electronic_materials')) {
            return collect();
        }

        return (function () use ($filters): Collection {
            $hasRecords = $this->hasTable('bibliographic_records');
            $hasProfiles = $this->hasTable('reader_profiles');
            $query = DB::table('digital_material_access_logs as events')
                ->join('electronic_materials as materials', 'materials.id', '=', 'events.electronic_material_id')
                ->whereBetween('events.created_at', [$filters->from, $filters->to]);
            $hasRecords && $query->leftJoin('bibliographic_records as records', 'records.id', '=', 'materials.bibliographic_record_id');
            $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'events.user_id');

            if ($filters->branchId !== null) {
                if ($hasProfiles && $this->hasColumn('reader_profiles', 'preferred_branch_id')) {
                    $query->where('profiles.preferred_branch_id', $filters->branchId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
            if ($filters->fundId !== null) {
                $this->whereMaterialHasCopy($query, 'materials.bibliographic_record_id', 'fund_id', $filters->fundId);
            }
            if ($filters->resourceType !== null) {
                $query->where(function (Builder $where) use ($filters, $hasRecords): void {
                    $where->where('materials.material_type', $filters->resourceType);
                    $hasRecords && $where->orWhere('records.resource_type', $filters->resourceType);
                });
            }
            $filters->userSegment !== null && ($hasProfiles ? $query->where('profiles.category', $filters->userSegment) : $query->whereRaw('1 = 0'));
            $filters->language !== null && $query->where(function (Builder $where) use ($filters, $hasRecords): void {
                $where->where('materials.language', $filters->language);
                $hasRecords && $where->orWhere('records.language', $filters->language);
            });
            $filters->udc !== null && ($hasRecords ? $query->where('records.udc_code', 'like', $filters->udc.'%') : $query->whereRaw('1 = 0'));
            $filters->status !== null && $query->where('materials.workflow_status', $filters->status);
            $filters->subject !== null && ($hasRecords
                ? $query->where(fn (Builder $where) => $where->where('records.category', $filters->subject)->orWhere('records.title', 'like', '%'.$filters->subject.'%'))
                : $query->whereRaw('1 = 0'));
            $filters->accessType !== null && $query->where('materials.access_level', $filters->accessType);
            $filters->operation !== null && $this->applyEventOperation($query, 'events.action', 'events.allowed', $filters->operation);

            $segmentSql = $hasProfiles
                ? "COALESCE(profiles.category, CASE WHEN events.user_id IS NULL THEN 'guest' ELSE 'student' END)"
                : "CASE WHEN events.user_id IS NULL THEN 'guest' ELSE 'student' END";
            $rows = $query
                ->selectRaw('materials.id as resource_id, materials.title as resource')
                ->selectRaw('materials.material_type as resource_type, materials.access_level as access_type')
                ->selectRaw('events.action, events.allowed')
                ->selectRaw("{$segmentSql} as segment")
                ->selectRaw('COUNT(*) as event_count')
                ->selectRaw($this->distinctUserIdsSql('events.user_id').' as user_ids')
                ->groupBy('materials.id', 'materials.title', 'materials.material_type', 'materials.access_level', 'events.action', 'events.allowed')
                ->groupByRaw($segmentSql)
                ->get();

            return $rows->map(fn (object $event): array => [
                'source' => 'internal',
                'source_label' => $this->label('analytics.access_types.internal', 'Internal'),
                'resource_id' => (string) $event->resource_id,
                'resource' => (string) $event->resource,
                'resource_type' => (string) ($event->resource_type ?: 'electronic'),
                'access_type' => (string) ($event->access_type ?: 'internal'),
                'action' => (string) $event->action,
                'allowed' => (bool) $event->allowed,
                'user_id' => null,
                'user_ids' => $this->parseUserIds($event->user_ids),
                'segment' => (string) $event->segment,
                'at' => null,
                'event_count' => (int) $event->event_count,
            ]);
        })();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function externalEvents(ReportFilters $filters): Collection
    {
        if (! $this->hasTable('external_resource_events') || ! $this->hasTable('external_resources')) {
            return collect();
        }

        return (function () use ($filters): Collection {
            $hasProfiles = $this->hasTable('reader_profiles');
            $query = DB::table('external_resource_events as events')
                ->join('external_resources as resources', 'resources.id', '=', 'events.external_resource_id')
                ->whereBetween('events.created_at', [$filters->from, $filters->to]);
            $hasProfiles && $query->leftJoin('reader_profiles as profiles', 'profiles.user_id', '=', 'events.user_id');
            if ($filters->branchId !== null) {
                if ($hasProfiles && $this->hasColumn('reader_profiles', 'preferred_branch_id')) {
                    $query->where('profiles.preferred_branch_id', $filters->branchId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
            $filters->fundId !== null && $query->whereRaw('1 = 0');
            $filters->resourceType !== null && $query->where(function (Builder $where) use ($filters): void {
                $where->where('resources.resource_type', $filters->resourceType)->orWhere('resources.category', $filters->resourceType);
            });
            $filters->userSegment !== null && $query->where(function (Builder $where) use ($filters, $hasProfiles): void {
                $where->where('events.role_name', $filters->userSegment);
                $hasProfiles && $where->orWhere('profiles.category', $filters->userSegment);
            });
            $filters->language !== null && $query->whereRaw('1 = 0');
            $filters->udc !== null && $query->whereRaw('1 = 0');
            $filters->status !== null && $query->where('resources.publication_status', $filters->status);
            $filters->subject !== null && $query->where(fn (Builder $where) => $where->where('resources.category', $filters->subject)->orWhere('resources.title', 'like', '%'.$filters->subject.'%'));
            $filters->accessType !== null && $query->where(fn (Builder $where) => $where->where('resources.resource_type', $filters->accessType)->orWhere('resources.access_type', $filters->accessType));
            $filters->operation !== null && $this->applyEventOperation($query, 'events.event_type', null, $filters->operation);

            $segmentSql = $hasProfiles
                ? "COALESCE(profiles.category, events.role_name, CASE WHEN events.user_id IS NULL THEN 'guest' ELSE 'student' END)"
                : "COALESCE(events.role_name, CASE WHEN events.user_id IS NULL THEN 'guest' ELSE 'student' END)";
            $rows = $query
                ->selectRaw('resources.id as resource_id, resources.title as resource')
                ->selectRaw('resources.resource_type, resources.access_type, events.event_type')
                ->selectRaw("{$segmentSql} as segment")
                ->selectRaw('COUNT(*) as event_count')
                ->selectRaw($this->distinctUserIdsSql('events.user_id').' as user_ids')
                ->groupBy('resources.id', 'resources.title', 'resources.resource_type', 'resources.access_type', 'events.event_type')
                ->groupByRaw($segmentSql)
                ->get();

            return $rows->map(function (object $event): array {
                $action = (string) $event->event_type;
                $access = (string) ($event->resource_type ?: $event->access_type ?: 'external');

                return [
                    'source' => 'external',
                    'source_label' => $this->label('analytics.reports.electronic-resources.short', 'External'),
                    'resource_id' => (string) $event->resource_id,
                    'resource' => (string) $event->resource,
                    'resource_type' => (string) ($event->resource_type ?: 'external'),
                    'access_type' => $access,
                    'action' => $action,
                    'allowed' => ! in_array($action, ['access_denied', 'expired_click', 'unsafe_destination', 'error'], true),
                    'user_id' => null,
                    'user_ids' => $this->parseUserIds($event->user_ids),
                    'segment' => (string) $event->segment,
                    'at' => null,
                    'event_count' => (int) $event->event_count,
                ];
            });
        })();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function repositoryEvents(ReportFilters $filters): Collection
    {
        if (! $this->hasTable('repository_usage_daily') || ! $this->hasTable('repository_items')) {
            return collect();
        }

        $query = DB::table('repository_usage_daily as events')
            ->join('repository_items as resources', 'resources.id', '=', 'events.repository_item_id')
            ->whereBetween('events.occurred_on', [$this->localDate($filters->from), $this->localDate($filters->to)]);

        if ($filters->branchId !== null) {
            // Branch is deliberately not collected because it would make the
            // low-volume daily dimensions re-identifying for repository use.
            $query->whereRaw('1 = 0');
        }
        $filters->fundId !== null && $query->whereRaw('1 = 0');
        $filters->resourceType !== null && $query->where('resources.work_type', $filters->resourceType);
        $filters->userSegment !== null && $query->where('events.role_name', $filters->userSegment);
        $filters->language !== null && $query->where('resources.language', $filters->language);
        $filters->udc !== null && $query->where('resources.udc_code', 'like', $filters->udc.'%');
        $filters->status !== null && $query->where('resources.status', $filters->status);
        $filters->subject !== null && $query->where(function (Builder $where) use ($filters): void {
            $where->where('resources.department', $filters->subject)
                ->orWhere('resources.title', 'like', '%'.$filters->subject.'%');
        });
        if ($filters->accessType !== null) {
            $this->hasColumn('repository_items', 'access_policy')
                ? $query->where('resources.access_policy', $filters->accessType)
                : $query->whereRaw('1 = 0');
        }
        if ($filters->operation !== null) {
            $eventType = match ($filters->operation) {
                'view' => 'metadata_view',
                'stream' => 'pdf_view',
                'download' => 'download',
                default => null,
            };
            $eventType === null ? $query->whereRaw('1 = 0') : $query->where('events.event_type', $eventType);
        }

        $fields = [
            'events.id', 'events.event_type', 'events.role_name', 'events.occurred_on', 'events.event_count',
            'resources.id as resource_id', 'resources.title as resource', 'resources.work_type as resource_type',
        ];
        if ($this->hasColumn('repository_items', 'access_policy')) {
            $fields[] = 'resources.access_policy as access_type';
        }

        return $query->get($fields)->map(function (object $event): array {
            return [
                'source' => 'repository',
                'source_label' => $this->label('analytics.sources.repository', 'Repository'),
                'resource_id' => (string) $event->resource_id,
                'resource' => (string) $event->resource,
                'resource_type' => (string) ($event->resource_type ?: 'repository'),
                'access_type' => (string) ($event->access_type ?? 'open_access'),
                'action' => match ((string) $event->event_type) {
                    'metadata_view' => 'view',
                    'pdf_view' => 'stream',
                    default => 'download',
                },
                'allowed' => true,
                'user_id' => null,
                'user_ids' => [],
                'segment' => (string) ($event->role_name ?: 'guest'),
                'at' => $event->occurred_on,
                'event_count' => (int) $event->event_count,
            ];
        });
    }

    private function applyCopyFilters(Builder $query, ReportFilters $filters, bool $hasRecords): void
    {
        $filters->branchId !== null && $query->where('copies.branch_id', $filters->branchId);
        $filters->fundId !== null && $query->where('copies.fund_id', $filters->fundId);
        $filters->status !== null && $query->where('copies.status', $filters->status);
        $filters->accessType !== null && $query->where('copies.access_restriction', $filters->accessType);
        if ($filters->acquisitionSource !== null) {
            $filters->acquisitionSource === 'donation'
                ? $query->whereIn('copies.acquisition_source', ['donation', 'gift'])
                : $query->where('copies.acquisition_source', $filters->acquisitionSource);
        }

        foreach ([
            'resourceType' => ['resource_type', '='],
            'language' => ['language', '='],
            'udc' => ['udc_code', 'like'],
        ] as $property => [$column, $operator]) {
            if ($filters->{$property} === null) {
                continue;
            }
            if (! $hasRecords) {
                $query->whereRaw('1 = 0');

                continue;
            }
            $value = $property === 'udc' ? $filters->{$property}.'%' : $filters->{$property};
            $query->where("records.{$column}", $operator, $value);
        }
        if ($filters->subject !== null) {
            $hasRecords
                ? $query->where(fn (Builder $where) => $where->where('records.category', $filters->subject)->orWhere('records.title', 'like', '%'.$filters->subject.'%'))
                : $query->whereRaw('1 = 0');
        }
    }

    private function distinctUserIdsSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "STRING_AGG(DISTINCT CAST({$column} AS TEXT), ',')"
            : "GROUP_CONCAT(DISTINCT {$column})";
    }

    /** @return list<int> */
    private function parseUserIds(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->filter(static fn (string $id): bool => ctype_digit(trim($id)))
            ->map(static fn (string $id): int => (int) trim($id))
            ->unique()
            ->values()
            ->all();
    }

    private function eventUniqueUsers(Collection $events): int
    {
        return $events
            ->flatMap(static fn (array $event): array => $event['user_ids'] ?? [])
            ->unique()
            ->count();
    }

    private function applyUserStatusFilter(Builder $query, ReportFilters $filters, bool $hasProfiles): void
    {
        if ($filters->status === null) {
            return;
        }
        if (in_array($filters->status, ['active', 'inactive'], true)) {
            $query->where('users.is_active', $filters->status === 'active');

            return;
        }
        $hasProfiles ? $query->where('profiles.status', $filters->status) : $query->whereRaw('1 = 0');
    }

    private function applyEventOperation(Builder $query, string $actionColumn, ?string $allowedColumn, string $operation): void
    {
        if ($operation === 'access_denied' && $allowedColumn !== null) {
            $query->where(fn (Builder $where) => $where->where($actionColumn, 'access_denied')->orWhere($allowedColumn, false));

            return;
        }
        $query->where($actionColumn, $operation);
    }

    private function whereMaterialHasCopy(Builder $query, string $recordColumn, string $column, int $value): void
    {
        if (! $this->hasTable('book_copies')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereExists(function (Builder $copies) use ($recordColumn, $column, $value): void {
            $copies->selectRaw('1')->from('book_copies as material_copies')
                ->whereColumn('material_copies.bibliographic_record_id', $recordColumn)
                ->where("material_copies.{$column}", $value);
        });
    }

    private function resourceSpecificFilters(ReportFilters $filters): bool
    {
        return $filters->fundId !== null
            || $filters->resourceType !== null
            || $filters->language !== null
            || $filters->udc !== null
            || $filters->subject !== null
            || $filters->accessType !== null
            || $filters->acquisitionSource !== null;
    }

    /** @return Collection<int, array{label: string, value: int}> */
    private function copyStatusBreakdown(Builder $base): Collection
    {
        return (clone $base)->selectRaw('copies.status, COUNT(*) as aggregate')->groupBy('copies.status')->get()
            ->map(fn (object $row): array => ['label' => $this->displayValue((string) $row->status), 'value' => (int) $row->aggregate])
            ->sortByDesc('value')->values();
    }

    /** @return Collection<int, array{label: string, value: int|float}> */
    private function breakdownItems(Collection $rows, string $labelKey, string $valueKey): Collection
    {
        return $rows->groupBy($labelKey)->map(fn (Collection $items, string $label): array => [
            'label' => $label,
            'value' => $items->sum($valueKey),
        ])->values()->sortByDesc('value')->values();
    }

    /** @return array{key: string, label: string, items: array, rows: array} */
    private function breakdown(string $key, string $label, Collection $items): array
    {
        return ['key' => $key, 'label' => $label, 'items' => $items->values()->all(), 'rows' => $items->values()->all()];
    }

    /** @return array{key: string, label: string, value: int|float} */
    private function metric(string $key, int|float $value): array
    {
        return ['key' => $key, 'label' => $this->label("analytics.metrics.{$key}", Str::headline($key)), 'value' => $value];
    }

    /** @return list<array{key: string, label: string}> */
    private function acquisitionColumns(): array
    {
        return $this->columns(['received_date', 'acquisition_source', 'resource_type', 'branch', 'fund', 'supplier', 'ksu_number', 'records', 'copies', 'total_value']);
    }

    /** @return list<array{key: string, label: string}> */
    private function fundUsageColumns(): array
    {
        return $this->columns(['fund', 'branch', 'copies', 'on_loan', 'issued', 'returned', 'renewals', 'reservations', 'usage_rate', 'turnover_rate']);
    }

    /** @return list<array{key: string, label: string}> */
    private function userColumns(): array
    {
        return $this->columns(['user_segment', 'users', 'active_users', 'new_users', 'visits', 'issued', 'returned', 'electronic_actions', 'total_actions']);
    }

    /** @return list<array{key: string, label: string}> */
    private function electronicColumns(): array
    {
        return $this->columns(['resource', 'source', 'resource_type', 'access_type', 'views', 'downloads', 'logins', 'denied', 'failures', 'total', 'unique_users']);
    }

    /** @param list<string> $keys @return list<array{key: string, label: string}> */
    private function columns(array $keys): array
    {
        return collect($keys)->map(fn (string $key): array => [
            'key' => $key,
            'label' => $this->label("analytics.columns.{$key}", Str::headline($key)),
        ])->all();
    }

    /** @param list<array{key: string, label: string}> $columns @param list<string> $metricKeys @return array<string, mixed> */
    private function emptyReport(array $columns = [], array $metricKeys = []): array
    {
        return [
            'metrics' => collect($metricKeys)->map(fn (string $key): array => $this->metric($key, 0))->all(),
            'columns' => $columns,
            'rows' => [],
            'breakdowns' => [],
        ];
    }

    /** @param list<array{0: string, 1: string}> $sources @param list<string> $defaults @return list<array{value: string, label: string}> */
    private function distinctOptions(array $sources, array $defaults = []): array
    {
        $values = collect($defaults);
        foreach ($sources as [$table, $column]) {
            if (! $this->hasTable($table) || ! $this->hasColumn($table, $column)) {
                continue;
            }
            $values = $values->merge($this->safe(
                fn (): Collection => DB::table($table)->whereNotNull($column)->where($column, '<>', '')->distinct()->pluck($column),
                collect(),
            ));
        }

        return $values->map(fn (mixed $value): string => trim((string) $value))->filter()->unique()->sort()->values()
            ->map(fn (string $value): array => ['value' => $value, 'label' => $this->displayValue($value)])->all();
    }

    /** @param array<string, scalar> $conditions @return list<array{value: int|string, label: string}> */
    private function optionsFromTable(string $table, string $valueColumn, string $labelColumn, array $conditions = []): array
    {
        if (! $this->hasTable($table)) {
            return [];
        }

        return $this->safe(function () use ($table, $valueColumn, $labelColumn, $conditions): array {
            $query = DB::table($table);
            foreach ($conditions as $column => $value) {
                if ($this->hasColumn($table, $column)) {
                    $query->where($column, $value);
                }
            }

            return $query->orderBy($labelColumn)->get([$valueColumn, $labelColumn])
                ->map(fn (object $row): array => ['value' => $row->{$valueColumn}, 'label' => (string) $row->{$labelColumn}])->all();
        }, []);
    }

    /** @param list<string> $values @return list<array{value: string, label: string}> */
    private function translatedOptions(array $values, string $prefix): array
    {
        return collect($values)->map(fn (string $value): array => [
            'value' => $value,
            'label' => $this->optionLabel($prefix, $value),
        ])->all();
    }

    private function optionLabel(string $prefix, string $value): string
    {
        $canonical = $value === 'gift' ? 'donation' : $value;

        return $this->label("{$prefix}.{$canonical}", $this->displayValue($canonical));
    }

    private function displayValue(string $value): string
    {
        return Str::of($value)->replace(['_', '-'], ' ')->ucfirst()->toString();
    }

    private function label(string $key, string $fallback): string
    {
        return trans()->has($key) ? (string) __($key) : $fallback;
    }

    private function localDate(Carbon $date): string
    {
        return $date->copy()->timezone((string) config('app.library_timezone', 'Asia/Almaty'))->toDateString();
    }

    private function fundBranchKey(mixed $fundId, mixed $branchId): string
    {
        return ($fundId === null ? 'none' : (string) $fundId).'|'.($branchId === null ? 'none' : (string) $branchId);
    }

    private function hasTable(string $table): bool
    {
        return DatabaseSchema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->safe(fn (): bool => Schema::hasColumn($table, $column), false);
    }

    /** @template T @param callable(): T $callback @param T $fallback @return T */
    private function safe(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
