<?php

namespace App\Services\Reports;

use App\Models\User;
use InvalidArgumentException;

final class ReportRegistry
{
    public const OFFICIAL_CODES = ['acquisitions', 'fund-usage', 'users', 'electronic-resources'];

    public const OPERATIONAL_CODES = [
        'loans', 'returns', 'renewals', 'overdue', 'fines', 'reservations', 'queue',
        'incidents', 'lost-damaged', 'inventory', 'visits', 'data-quality',
        'news-events', 'messages', 'repository', 'external-resources', 'staff',
        'audit-summary', 'fund-movement', 'new-acquisitions', 'write-offs',
        'electronic-materials',
    ];

    /** @var array<string, ReportDefinition> */
    private array $definitions;

    public function __construct()
    {
        $officialFilters = [
            'preset', 'from', 'to', 'branch_id', 'fund_id', 'resource_type',
            'user_segment', 'language', 'udc', 'status', 'subject', 'access_type',
            'operation', 'acquisition_source',
        ];

        $official = collect(self::OFFICIAL_CODES)->mapWithKeys(fn (string $code): array => [$code => new ReportDefinition(
            code: $code,
            titleKey: "analytics.reports.{$code}.title",
            descriptionKey: "analytics.reports.{$code}.description",
            dataset: match ($code) {
                'acquisitions' => 'catalog.book_copies',
                'fund-usage' => 'catalog.book_copies+circulation.loans+attendance.visits',
                'users' => 'identity.users+attendance.visits+circulation.loans+digital.access_events',
                'electronic-resources' => 'digital.access_logs+external_resource_events+repository_usage_daily',
            },
            filters: $officialFilters,
            columns: $this->columns($code),
            defaultSort: ['key' => $code === 'acquisitions' ? 'received_date' : 'total', 'direction' => 'desc'],
            totals: $this->totals($code),
            charts: $this->charts($code),
            exports: ['csv', 'pdf', 'xlsx', 'docx'],
            permission: $code === 'acquisitions'
                ? 'reports.view_acquisitions|reports.view_ops|reports.view_full'
                : 'reports.view_ops|reports.view_full',
            official: true,
            snapshotSupport: true,
            scheduleSupport: false,
            sensitivityClass: $code === 'users' ? 'restricted_aggregate' : 'internal',
        )])->all();

        $operational = collect(self::OPERATIONAL_CODES)->mapWithKeys(fn (string $code): array => [$code => new ReportDefinition(
            code: $code,
            titleKey: "analytics.reports.{$code}.title",
            descriptionKey: "analytics.reports.{$code}.description",
            dataset: match ($code) {
                'loans', 'returns', 'renewals', 'overdue' => 'circulation.loans',
                'fines' => 'circulation.fines',
                'reservations', 'queue' => 'circulation.reservations',
                'incidents', 'lost-damaged' => 'circulation.incident_cases',
                'inventory' => 'catalog.inventory_sessions',
                'visits' => 'attendance.library_visits',
                'data-quality' => 'governance.data_quality_issues',
                'news-events' => 'content.news',
                'messages' => 'service.contact_messages',
                'repository' => 'repository.items+repository_usage_daily',
                'external-resources' => 'resources.external_resources+external_resource_events',
                'staff', 'audit-summary' => 'governance.activity_logs',
                'fund-movement', 'write-offs' => 'catalog.copy_history',
                'new-acquisitions' => 'catalog.book_copies',
                'electronic-materials' => 'digital.access_logs',
            },
            filters: $this->operationalFilters($code),
            columns: ['dimension', 'total', ...($code === 'fines' ? ['amount'] : [])],
            defaultSort: ['key' => 'total', 'direction' => 'desc'],
            totals: $code === 'fines' ? ['total', 'amount'] : ['total'],
            charts: ['distribution'],
            exports: ['csv', 'pdf', 'xlsx', 'docx'],
            permission: match ($code) {
                'fines' => 'fines.view|reports.view_full',
                'incidents', 'lost-damaged' => 'incidents.view_reports|reports.view_full',
                'data-quality' => 'data_quality.view_reports|data_quality.view|reports.view_full',
                'news-events' => 'news.view_analytics|reports.view_full',
                'messages' => 'messages.view_analytics|reports.view_full',
                'repository' => 'repository.view_analytics|reports.view_full',
                'external-resources' => 'external_resources.view_analytics|reports.view_full',
                'staff' => 'staff_performance.view|reports.view_full',
                'audit-summary' => 'system.logs|reports.view_full',
                default => 'reports.view_ops|reports.view_full',
            },
            official: false,
            snapshotSupport: false,
            scheduleSupport: false,
            sensitivityClass: in_array($code, ['fines', 'incidents', 'lost-damaged', 'staff', 'audit-summary'], true)
                ? 'restricted_aggregate'
                : 'internal',
        )])->all();

        $this->definitions = [...$official, ...$operational];
    }

    /** @return list<string> */
    private function operationalFilters(string $code): array
    {
        $filters = ['preset', 'from', 'to'];
        if (in_array($code, ['loans', 'returns', 'renewals', 'overdue', 'fines', 'reservations', 'queue', 'incidents', 'inventory', 'news-events', 'messages'], true)) {
            $filters[] = 'status';
        }
        if ($code === 'external-resources') {
            $filters[] = 'operation';
        }
        if (in_array($code, ['repository', 'lost-damaged'], true)) {
            $filters[] = 'resource_type';
        }
        if (in_array($code, ['inventory', 'visits'], true)) {
            $filters[] = 'branch_id';
        }
        if (in_array($code, ['inventory'], true)) {
            $filters[] = 'fund_id';
        }
        if ($code === 'external-resources') {
            $filters[] = 'user_segment';
        }
        if (in_array($code, ['fund-movement', 'new-acquisitions'], true)) {
            $filters[] = 'branch_id';
            $filters[] = 'fund_id';
        }
        if ($code === 'new-acquisitions') {
            $filters[] = 'acquisition_source';
        }
        if (in_array($code, ['fund-movement', 'write-offs', 'electronic-materials'], true)) {
            $filters[] = 'operation';
        }

        return array_values(array_unique($filters));
    }

    /** @return list<string> */
    private function columns(string $code): array
    {
        return match ($code) {
            'acquisitions' => ['received_date', 'acquisition_source', 'resource_type', 'branch', 'fund', 'supplier', 'ksu_number', 'records', 'copies', 'total_value'],
            'fund-usage' => ['branch', 'fund', 'resource_type', 'on_loan', 'issued', 'returned', 'renewals', 'reservations', 'usage_rate', 'turnover_rate'],
            'users' => ['user_segment', 'users', 'active_users', 'new_users', 'visits', 'issued', 'reservations', 'electronic_actions'],
            'electronic-resources' => ['resource', 'source', 'access_type', 'views', 'downloads', 'logins', 'denied', 'failures', 'total'],
            default => ['dimension', 'total'],
        };
    }

    /** @return list<string> */
    private function totals(string $code): array
    {
        return match ($code) {
            'acquisitions' => ['records', 'copies', 'value', 'sources'],
            'fund-usage' => ['copies', 'issued', 'returned', 'renewals', 'reservations', 'visits'],
            'users' => ['users', 'active_users', 'new_users', 'visits'],
            'electronic-resources' => ['views', 'downloads', 'logins', 'denied', 'failures'],
            default => ['total'],
        };
    }

    /** @return list<string> */
    private function charts(string $code): array
    {
        return match ($code) {
            'acquisitions' => ['sources', 'funds', 'languages', 'resource_types'],
            'fund-usage' => ['funds', 'udc', 'languages', 'resource_types', 'popular'],
            'users' => ['segments', 'activity'],
            'electronic-resources' => ['sources', 'actions', 'popular'],
            default => ['distribution'],
        };
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->definitions);
    }

    /** @return list<ReportDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /** @return list<string> */
    public function officialCodes(): array
    {
        return self::OFFICIAL_CODES;
    }

    /** @return list<ReportDefinition> */
    public function officialDefinitions(): array
    {
        return array_values(array_filter(
            $this->definitions,
            static fn (ReportDefinition $definition): bool => $definition->official,
        ));
    }

    public function get(string $code): ReportDefinition
    {
        return $this->definitions[$code]
            ?? throw new InvalidArgumentException("Unknown report definition: {$code}");
    }

    public function find(string $code): ?ReportDefinition
    {
        return $this->definitions[$code] ?? null;
    }

    public function official(string $code): ReportDefinition
    {
        $definition = $this->get($code);
        if (! $definition->official) {
            throw new InvalidArgumentException("Report {$code} is not an official form.");
        }

        return $definition;
    }

    public function allows(User $user, string|ReportDefinition $report): bool
    {
        $definition = is_string($report) ? $this->get($report) : $report;

        return collect(explode('|', $definition->permission))
            ->map(static fn (string $permission): string => trim($permission))
            ->filter()
            ->contains(fn (string $permission): bool => $user->can($permission));
    }
}
