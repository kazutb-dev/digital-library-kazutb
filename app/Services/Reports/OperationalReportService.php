<?php

namespace App\Services\Reports;

use App\Support\DatabaseSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Privacy-preserving aggregate datasets for the non-official report registry. */
final class OperationalReportService
{
    /** @return array<string, mixed> */
    public function build(string $type, ReportFilters $filters): array
    {
        $definition = $this->definition($type);
        if (! DatabaseSchema::hasTable($definition['table'])) {
            return $this->empty();
        }

        $query = DB::table($definition['table'].' as records')
            ->whereBetween('records.'.$definition['date'], [$filters->from, $filters->to]);
        ($definition['scope'])($query, $filters);
        $this->applyCommonFilters($query, $definition, $filters);

        $dimension = $definition['dimension'];
        $maximum = max(100, (int) config('library.reports.max_live_rows', 10000));
        $rows = $query
            ->selectRaw("COALESCE(records.{$dimension}, 'unknown') as dimension")
            ->selectRaw('COUNT(*) as aggregate')
            ->when($definition['amount'] !== null, fn (Builder $builder): Builder => $builder
                ->selectRaw("COALESCE(SUM(records.{$definition['amount']}), 0) as amount"))
            ->groupBy('records.'.$dimension)
            ->orderByDesc('aggregate')
            ->limit($maximum + 1)
            ->get();

        if ($rows->count() > $maximum) {
            throw new ReportLimitExceeded("The report contains more than {$maximum} aggregate rows.");
        }

        $mapped = $rows->map(function (object $row) use ($definition): array {
            $label = $this->display((string) $row->dimension, $definition['dimension']);

            return [
                'dimension' => $label,
                'label' => $label,
                'value' => (int) $row->aggregate,
                'total' => (int) $row->aggregate,
                'amount' => $definition['amount'] === null ? null : round((float) ($row->amount ?? 0), 2),
            ];
        })->values();
        $amount = round((float) $mapped->sum('amount'), 2);
        $metrics = [
            $this->metric('total', (int) $mapped->sum('total')),
            $this->metric('groups', $mapped->count()),
        ];
        if ($definition['amount'] !== null) {
            $metrics[] = $this->metric('amount', $amount);
        }

        $columns = [
            $this->column('dimension'),
            $this->column('total'),
        ];
        if ($definition['amount'] !== null) {
            $columns[] = $this->column('amount');
        }

        $items = $mapped->map(fn (array $row): array => [
            'label' => $row['label'],
            'value' => $row['value'],
        ])->all();

        return [
            'metrics' => $metrics,
            'columns' => $columns,
            'rows' => $mapped->all(),
            'breakdowns' => [[
                'key' => 'distribution',
                'label' => $this->label('analytics.columns.dimension', 'Distribution'),
                'items' => $items,
                'rows' => $items,
            ]],
        ];
    }

    /**
     * @return array{table: string, date: string, dimension: string, amount: string|null, status: string|null, operation: string|null, resource_type: string|null, scope: callable(Builder, ReportFilters): void}
     */
    private function definition(string $type): array
    {
        return match ($type) {
            'loans' => $this->spec('loans', 'issued_at', 'status'),
            'returns' => $this->spec('loans', 'returned_at', 'status', scope: static fn (Builder $query) => $query->whereNotNull('records.returned_at')),
            'renewals' => $this->spec('loans', 'updated_at', 'status', scope: static fn (Builder $query) => $query->where('records.renewal_count', '>', 0)),
            'overdue' => $this->spec('loans', 'due_at', 'status', scope: static fn (Builder $query) => $query
                ->whereNull('records.returned_at')->where(fn (Builder $overdue) => $overdue
                ->where('records.status', 'overdue')->orWhere('records.due_at', '<', now('UTC')))),
            'fines' => $this->spec('fines', 'charged_at', 'status', 'amount'),
            'reservations' => $this->spec('reservations', 'created_at', 'status'),
            'queue' => $this->spec('reservations', 'created_at', 'status', scope: static fn (Builder $query) => $query
                ->whereIn('records.status', ['pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup'])),
            'incidents' => $this->spec('circulation_incident_cases', 'opened_at', 'status'),
            'lost-damaged' => $this->spec('circulation_incident_cases', 'opened_at', 'incident_type', scope: static fn (Builder $query) => $query
                ->whereIn('records.incident_type', ['lost', 'damaged'])),
            'inventory' => $this->spec('inventory_sessions', 'inventory_date', 'status', 'expected_count'),
            'visits' => $this->spec('library_visits', 'scanned_at', 'source'),
            'data-quality' => $this->spec('data_quality_issues', 'first_detected_at', 'severity'),
            'news-events' => $this->spec('news', 'created_at', 'status'),
            'messages' => $this->spec('contact_messages', 'created_at', 'status'),
            'repository' => $this->spec('repository_items', 'created_at', 'work_type'),
            'external-resources' => $this->spec('external_resource_events', 'created_at', 'event_type'),
            'staff' => $this->spec('activity_logs', 'occurred_at', 'actor_role'),
            'audit-summary' => $this->spec('activity_logs', 'occurred_at', 'action_type'),
            'fund-movement' => $this->spec('copy_history', 'occurred_at', 'event_type'),
            'new-acquisitions' => $this->spec('book_copies', 'registration_date', 'acquisition_source'),
            'write-offs' => $this->spec('copy_history', 'occurred_at', 'event_type', scope: static fn (Builder $query) => $query
                ->whereIn('records.event_type', ['write_off', 'written_off'])),
            'electronic-materials' => $this->spec('digital_material_access_logs', 'created_at', 'action'),
            default => throw new \InvalidArgumentException("Unknown operational report: {$type}"),
        };
    }

    /** @return array{table: string, date: string, dimension: string, amount: string|null, status: string|null, operation: string|null, resource_type: string|null, scope: callable(Builder, ReportFilters): void} */
    private function spec(
        string $table,
        string $date,
        string $dimension,
        ?string $amount = null,
        ?callable $scope = null,
    ): array {
        return [
            'table' => $table,
            'date' => $date,
            'dimension' => $dimension,
            'amount' => $amount,
            'status' => Schema::hasTable($table) && Schema::hasColumn($table, 'status') ? 'status' : null,
            'operation' => match ($table) {
                'external_resource_events', 'copy_history' => 'event_type',
                'digital_material_access_logs' => 'action',
                'book_copies' => 'acquisition_source',
                'activity_logs' => 'action_type',
                default => null,
            },
            'resource_type' => match ($table) {
                'repository_items' => 'work_type',
                'circulation_incident_cases' => 'incident_type',
                default => null,
            },
            'scope' => $scope ?? static function (Builder $query, ReportFilters $filters): void {},
        ];
    }

    /** @param array<string, mixed> $definition */
    private function applyCommonFilters(Builder $query, array $definition, ReportFilters $filters): void
    {
        if ($filters->status !== null && $definition['status'] !== null) {
            $query->where('records.'.$definition['status'], $filters->status);
        }
        if ($filters->operation !== null && $definition['operation'] !== null) {
            $query->where('records.'.$definition['operation'], $filters->operation);
        }
        if ($filters->resourceType !== null && $definition['resource_type'] !== null) {
            $query->where('records.'.$definition['resource_type'], $filters->resourceType);
        }
        foreach (['branchId' => 'branch_id', 'fundId' => 'fund_id'] as $property => $column) {
            if ($filters->{$property} !== null && Schema::hasColumn($definition['table'], $column)) {
                $query->where('records.'.$column, $filters->{$property});
            }
        }
        if ($filters->userSegment !== null) {
            $column = match ($definition['table']) {
                'activity_logs' => 'actor_role',
                'external_resource_events' => 'role_name',
                default => null,
            };
            $column !== null && $query->where('records.'.$column, $filters->userSegment);
        }
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'metrics' => [$this->metric('total', 0), $this->metric('groups', 0)],
            'columns' => [$this->column('dimension'), $this->column('total')],
            'rows' => [],
            'breakdowns' => [],
        ];
    }

    /** @return array{key: string, label: string, value: int|float} */
    private function metric(string $key, int|float $value): array
    {
        return ['key' => $key, 'label' => $this->label("analytics.metrics.{$key}", Str::headline($key)), 'value' => $value];
    }

    /** @return array{key: string, label: string} */
    private function column(string $key): array
    {
        return ['key' => $key, 'label' => $this->label("analytics.columns.{$key}", Str::headline($key))];
    }

    private function display(string $value, string $dimension): string
    {
        foreach ($this->translationKeys($dimension, $value) as $key) {
            if (trans()->has($key)) {
                return (string) __($key);
            }
        }

        return (string) __('analytics.statuses.unknown');
    }

    /** @return list<string> */
    private function translationKeys(string $dimension, string $value): array
    {
        $normalized = str_replace(['-', '.'], '_', $value ?: 'unknown');

        return array_values(array_filter([
            match ($dimension) {
                'event_type', 'action_type', 'action' => "analytics.operations.{$normalized}",
                'acquisition_source' => "analytics.sources.{$normalized}",
                'actor_role', 'role_name' => "roles.names.{$normalized}",
                'source' => "analytics.visit_sources.{$normalized}",
                'severity' => "analytics.severities.{$normalized}",
                'incident_type' => "analytics.incident_types.{$normalized}",
                'work_type' => "librarian.repository.work_types.{$normalized}",
                default => null,
            },
            "analytics.segments.{$normalized}",
            "analytics.statuses.{$normalized}",
            "common.statuses.{$normalized}",
            "librarian.statuses.{$normalized}",
            "repository.types.{$normalized}",
        ]));
    }

    private function label(string $key, string $fallback): string
    {
        return trans()->has($key) ? (string) __($key) : $fallback;
    }
}
