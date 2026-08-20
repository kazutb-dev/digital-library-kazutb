<?php

namespace App\Services\Reports;

use App\Models\AcquisitionOrder;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\DigitalMaterialAccessLog;
use App\Models\Catalog\Fine;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\Loan;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\Reservation;
use App\Models\ContactMessage;
use App\Models\DataQualityIssue;
use App\Models\ExecutiveAlertAcknowledgement;
use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use App\Models\IntegrationInboxMessage;
use App\Models\IntegrationOutboxMessage;
use App\Models\LibraryTask;
use App\Models\News;
use App\Models\User;
use App\Services\Localization\LocalizedContentResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy-preserving executive aggregates for the library director.
 *
 * This service intentionally returns no reader names, IDs, titles from an
 * individual's history, IP addresses, or raw event payloads. Operational
 * detail continues to live behind the dedicated permission-scoped screens.
 */
final class DirectorAnalyticsService
{
    /** @var array<string, bool> */
    private array $tableAvailability = [];

    public function __construct(private readonly LocalizedContentResolver $localizedContent) {}

    /**
     * @return array{
     *     cards: array<string, int|float>,
     *     trends: array<string, list<array{label: string, value: int}>>,
     *     distributions: array<string, list<array{label: string, value: int}>>,
     *     top_resources: list<array{label: string, value: int}>,
     *     alerts: list<array{key: string, value: int, severity: string}>
     * }
     */
    public function build(array $filters = []): array
    {
        $now = now(config('app.library_timezone', 'Asia/Almaty'));
        $period = $this->period($filters, $now);
        $selectedStart = $period['from']->copy()->utc();
        $selectedEnd = $period['to']->copy()->utc();
        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd = $now->copy()->endOfDay()->utc();
        $weekStart = $now->copy()->startOfWeek()->utc();
        $weekEnd = $now->copy()->endOfWeek()->utc();
        $monthStart = $now->copy()->startOfMonth()->utc();
        $monthEnd = $now->copy()->endOfMonth()->utc();

        $cards = [
            'fund_total' => $this->count(BookCopy::class, fn (Builder $query) => $query->whereNotIn('status', ['written_off', 'lost'])),
            'acquisitions_month' => $this->count(BookCopy::class, fn (Builder $query) => $query->where(function (Builder $dates) use ($selectedStart, $selectedEnd): void {
                $dates->whereBetween('registration_date', [$selectedStart->toDateString(), $selectedEnd->toDateString()])
                    ->orWhere(function (Builder $fallback) use ($selectedStart, $selectedEnd): void {
                        $fallback->whereNull('registration_date')->whereBetween('acquisition_date', [$selectedStart->toDateString(), $selectedEnd->toDateString()]);
                    });
            })),
            'written_off' => $this->count(BookCopy::class, fn (Builder $query) => $query->where('status', 'written_off')->whereBetween('updated_at', [$selectedStart, $selectedEnd])),
            'issued_today' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('issued_at', [$todayStart, $todayEnd])),
            'issued_week' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('issued_at', [$weekStart, $weekEnd])),
            'issued_month' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('issued_at', [$selectedStart, $selectedEnd])),
            'returned_today' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('returned_at', [$todayStart, $todayEnd])),
            'returned_week' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('returned_at', [$weekStart, $weekEnd])),
            'returned_month' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('returned_at', [$selectedStart, $selectedEnd])),
            'overdue' => $this->count(Loan::class, fn (Builder $query) => $query->where('status', 'overdue')->whereNull('returned_at')),
            'outstanding_fines' => $this->sum(Fine::class, 'amount', fn (Builder $query) => $query->where('status', 'pending')),
            'active_users' => $this->count(User::class, fn (Builder $query) => $query->where('is_active', true)->where('auth_provider', 'ldap')),
            'active_readers_month' => $this->activeReaders($selectedStart, $selectedEnd),
            'new_users_month' => $this->count(User::class, fn (Builder $query) => $query->where('auth_provider', 'ldap')->whereBetween('created_at', [$selectedStart, $selectedEnd])),
            'visits_month' => $this->count(LibraryVisit::class, fn (Builder $query) => $query->whereBetween('scanned_at', [$selectedStart, $selectedEnd])),
            'problem_copies' => $this->count(BookCopy::class, fn (Builder $query) => $query->where(function (Builder $problems): void {
                $problems->whereIn('status', ['lost', 'under_repair'])->orWhere('condition', 'damaged');
            })),
            'repository_published' => $this->count(RepositoryItem::class, fn (Builder $query) => $query->where('status', 'published')),
            'repository_pending' => $this->count(RepositoryItem::class, fn (Builder $query) => $query->whereIn('status', ['metadata_review', 'rights_review', 'quality_review', 'pending_approval'])),
            'external_expiring' => $this->count(ExternalResource::class, fn (Builder $query) => $query->whereIn('resource_type', ['licensed', 'partner'])
                ->whereDate('contract_ends_at', '>=', $now->toDateString())
                ->whereDate('contract_ends_at', '<=', $now->copy()->addDays(90)->toDateString())),
            'external_expired' => $this->count(ExternalResource::class, fn (Builder $query) => $query->whereIn('resource_type', ['licensed', 'partner'])->whereDate('contract_ends_at', '<', $now->toDateString())),
            'open_messages' => $this->count(ContactMessage::class, fn (Builder $query) => $query->whereIn('status', ['open', 'in_review', 'reopened'])),
            'upcoming_events' => $this->count(News::class, fn (Builder $query) => $query->whereIn('type', ['event', 'schedule'])->where('status', 'published')->where('starts_at', '>=', $now->utc())),
            'data_quality_open' => $this->countQualityObjects('bibliographic_record'),
            'unused_records' => $this->count(BibliographicRecord::class, fn (Builder $query) => $query->whereHas('copies')->whereDoesntHave('copies', fn (Builder $copies) => $copies->where('issue_count', '>', 0))),
            'digital_views_month' => $this->count(DigitalMaterialAccessLog::class, fn (Builder $query) => $query->whereBetween('created_at', [$selectedStart, $selectedEnd])->where('allowed', true)->whereIn('action', ['view', 'preview', 'read'])),
            'external_opens_month' => $this->count(ExternalResourceEvent::class, fn (Builder $query) => $query->whereBetween('created_at', [$selectedStart, $selectedEnd])->whereIn('event_type', ['outbound_click', 'external_resource.open'])),
            'electronic_resources' => $this->count(ExternalResource::class, fn (Builder $query) => $query->where('publication_status', 'published')->where('is_active', true)),
            'message_sla_overdue' => $this->count(ContactMessage::class, fn (Builder $query) => $query
                ->whereIn('status', ['open', 'in_review', 'waiting_for_user', 'response_prepared', 'reopened'])
                ->whereNotNull('due_at')
                ->where('due_at', '<', $now->utc())),
        ];

        $cards += $this->extendedCards($selectedStart, $selectedEnd, $now);

        $trends = [
            'issues' => $this->dailyTrend(Loan::class, 'issued_at', 30),
            'visits' => $this->dailyTrend(LibraryVisit::class, 'scanned_at', 30),
            'active_readers' => $this->activeReaderTrend(30),
            'digital' => $this->dailyTrend(DigitalMaterialAccessLog::class, 'created_at', 30, fn (Builder $query) => $query->where('allowed', true)->whereIn('action', ['view', 'preview', 'read', 'download'])),
            'acquisitions' => $this->monthlyAcquisitionTrend(12),
            'repository' => $this->monthlyTrend(RepositoryItem::class, 'published_at', 12, fn (Builder $query) => $query->where('status', 'published')),
            'external' => $this->dailyTrend(ExternalResourceEvent::class, 'created_at', 30, fn (Builder $query) => $query->whereIn('event_type', ['outbound_click', 'external_resource.open'])),
        ];

        $distributions = [
            'fund_types' => $this->catalogDistribution('records.resource_type'),
            'udc' => $this->udcDistribution(),
            'message_sla' => $this->messageSlaDistribution($now->utc()),
            'reader_segments' => $this->readerSegmentDistribution(),
            'reservations' => $this->statusDistribution('reservations'),
            'fines' => $this->statusDistribution('fines'),
            'repository_access' => $this->statusDistribution('repository_items', 'access_policy'),
            'licences' => $this->licenceDistribution($now),
        ];

        $scopeHash = hash('sha256', implode('|', [$period['key'], $period['from']->toDateString(), $period['to']->toDateString()]));
        $acknowledged = $this->hasTable('executive_alert_acknowledgements')
            ? ExecutiveAlertAcknowledgement::query()->where('scope_hash', $scopeHash)->pluck('alert_key')->all()
            : [];
        $alerts = collect([
            ['key' => 'overdue', 'value' => (int) $cards['overdue'], 'severity' => 'high'],
            ['key' => 'external_expired', 'value' => (int) $cards['external_expired'], 'severity' => 'high'],
            ['key' => 'external_expiring', 'value' => (int) $cards['external_expiring'], 'severity' => 'warning'],
            ['key' => 'data_quality_open', 'value' => (int) $cards['data_quality_open'], 'severity' => 'warning'],
            ['key' => 'open_messages', 'value' => (int) $cards['open_messages'], 'severity' => 'warning'],
            ['key' => 'problem_copies', 'value' => (int) $cards['problem_copies'], 'severity' => 'warning'],
            ['key' => 'message_sla_overdue', 'value' => (int) $cards['message_sla_overdue'], 'severity' => 'high'],
        ])->filter(fn (array $alert): bool => $alert['value'] > 0)
            ->map(fn (array $alert): array => $alert + [
                'threshold' => 1,
                'recommendation' => $alert['key'],
                'acknowledged' => in_array($alert['key'], $acknowledged, true),
                'scope_hash' => $scopeHash,
            ])->values()->all();

        return [
            'cards' => $cards,
            'trends' => $trends,
            'distributions' => $distributions,
            'top_resources' => $this->topResources($selectedStart, $selectedEnd),
            'popular_resources' => $this->popularResources($selectedStart, $selectedEnd),
            'unused_resources' => $this->unusedResources($now),
            'staff_workload' => $this->staffWorkload($selectedStart, $selectedEnd),
            'bottlenecks' => $this->bottlenecks($now, $cards),
            'period' => [
                'key' => $period['key'],
                'from' => $period['from']->toDateString(),
                'to' => $period['to']->toDateString(),
                'compare' => $period['compare'],
            ],
            'comparison' => $period['compare'] ? $this->comparison($cards, $period) : [],
            'budget' => ['available' => false, 'reason' => 'integration_required'],
            'alerts' => $alerts,
        ];
    }

    /** @param class-string<Model> $model */
    private function count(string $model, callable $scope): int
    {
        $instance = new $model;
        if (! $this->hasTable($instance->getTable())) {
            return 0;
        }

        return (int) $scope($model::query())->count();
    }

    /**
     * Executive indicators count affected objects. Individual findings stay
     * available as a secondary diagnostic inside Data Quality.
     *
     * @param  list<string>  $severities
     */
    private function countQualityObjects(?string $entityType = null, array $severities = []): int
    {
        if (! $this->hasTable((new DataQualityIssue)->getTable())) {
            return 0;
        }

        $objects = DataQualityIssue::query()
            ->actionable()
            ->when($entityType !== null, fn (Builder $query) => $query->where('entity_type', $entityType))
            ->when($severities !== [], fn (Builder $query) => $query->whereIn('severity', $severities))
            ->select(['entity_type', 'entity_id'])
            ->distinct();

        return (int) DB::query()->fromSub($objects->toBase(), 'quality_objects')->count();
    }

    /**
     * Schema introspection is comparatively expensive on PostgreSQL. Keep it
     * local to this one dashboard build so migrations/tests can never inherit
     * a stale process-wide schema result.
     */
    private function hasTable(string $table): bool
    {
        return $this->tableAvailability[$table] ??= DB::connection()->getSchemaBuilder()->hasTable($table);
    }

    /** @param class-string<Model> $model */
    private function sum(string $model, string $column, callable $scope): float
    {
        $instance = new $model;
        if (! $this->hasTable($instance->getTable())) {
            return 0.0;
        }

        return round((float) $scope($model::query())->sum($column), 2);
    }

    /**
     * @param  class-string<Model>  $model
     * @return list<array{label: string, value: int}>
     */
    private function dailyTrend(string $model, string $column, int $days, ?callable $scope = null): array
    {
        $instance = new $model;
        if (! $this->hasTable($instance->getTable())) {
            return [];
        }

        $timezone = config('app.library_timezone', 'Asia/Almaty');
        $from = now($timezone)->subDays($days - 1)->startOfDay();
        $query = $model::query()->whereNotNull($column)->where($column, '>=', $from->copy()->utc());
        if ($scope !== null) {
            $scope($query);
        }
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $bucketSql = $pgsql ? "DATE(timezone(?, {$column}))" : "DATE({$column})";
        $bindings = $pgsql ? [$timezone] : [];
        $counts = $query
            ->selectRaw("{$bucketSql} AS bucket, COUNT(*) AS aggregate", $bindings)
            // Group by the output alias. Repeating a parameterized timezone
            // expression makes PostgreSQL see two distinct bind parameters,
            // so it cannot prove that SELECT and GROUP BY are identical.
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $counts): array {
            $date = $from->copy()->addDays($offset);

            return ['label' => $date->format('d.m'), 'value' => (int) ($counts[$date->toDateString()] ?? 0)];
        })->all();
    }

    /**
     * @param  class-string<Model>  $model
     * @return list<array{label: string, value: int}>
     */
    private function monthlyTrend(string $model, string $column, int $months, ?callable $scope = null): array
    {
        $instance = new $model;
        if (! $this->hasTable($instance->getTable())) {
            return [];
        }

        $timezone = config('app.library_timezone', 'Asia/Almaty');
        $from = now($timezone)->subMonths($months - 1)->startOfMonth();
        $query = $model::query()->whereNotNull($column)->where($column, '>=', $from->copy()->utc());
        if ($scope !== null) {
            $scope($query);
        }
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $bucketSql = $pgsql
            ? "TO_CHAR(timezone(?, {$column}), 'YYYY-MM')"
            : "strftime('%Y-%m', {$column})";
        $bindings = $pgsql ? [$timezone] : [];
        $counts = $query
            ->selectRaw("{$bucketSql} AS bucket, COUNT(*) AS aggregate", $bindings)
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        return collect(range(0, $months - 1))->map(function (int $offset) use ($from, $counts): array {
            $date = $from->copy()->addMonths($offset);

            return ['label' => $date->format('m.Y'), 'value' => (int) ($counts[$date->format('Y-m')] ?? 0)];
        })->all();
    }

    private function activeReaders(Carbon $from, Carbon $to): int
    {
        if (! $this->hasTable('loans') && ! $this->hasTable('library_visits')) {
            return 0;
        }

        $queries = [];
        if ($this->hasTable('loans')) {
            $queries[] = DB::table('loans')->select('user_id')->whereNotNull('user_id')->whereBetween('issued_at', [$from, $to]);
        }
        if ($this->hasTable('library_visits')) {
            $queries[] = DB::table('library_visits')->select('user_id')->whereNotNull('user_id')->whereBetween('scanned_at', [$from, $to]);
        }

        $activity = array_shift($queries);
        foreach ($queries as $query) {
            $activity->union($query);
        }

        return (int) DB::query()->fromSub($activity, 'active_readers')->distinct()->count('user_id');
    }

    /** @return list<array{label: string, value: int}> */
    private function activeReaderTrend(int $days): array
    {
        if (! $this->hasTable('loans') && ! $this->hasTable('library_visits')) {
            return [];
        }

        $timezone = config('app.library_timezone', 'Asia/Almaty');
        $from = now($timezone)->subDays($days - 1)->startOfDay();
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $queries = [];

        if ($this->hasTable('loans')) {
            $bucket = $pgsql ? 'DATE(timezone(?, issued_at))' : 'DATE(issued_at)';
            $queries[] = DB::table('loans')
                ->selectRaw("{$bucket} AS bucket, user_id", $pgsql ? [$timezone] : [])
                ->whereNotNull('user_id')
                ->where('issued_at', '>=', $from->copy()->utc());
        }
        if ($this->hasTable('library_visits')) {
            $bucket = $pgsql ? 'DATE(timezone(?, scanned_at))' : 'DATE(scanned_at)';
            $queries[] = DB::table('library_visits')
                ->selectRaw("{$bucket} AS bucket, user_id", $pgsql ? [$timezone] : [])
                ->whereNotNull('user_id')
                ->where('scanned_at', '>=', $from->copy()->utc());
        }

        $activity = array_shift($queries);
        foreach ($queries as $query) {
            $activity->unionAll($query);
        }

        $counts = DB::query()
            ->fromSub($activity, 'reader_activity')
            ->selectRaw('bucket, COUNT(DISTINCT user_id) AS aggregate')
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $counts): array {
            $date = $from->copy()->addDays($offset);

            return ['label' => $date->format('d.m'), 'value' => (int) ($counts[$date->toDateString()] ?? 0)];
        })->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function monthlyAcquisitionTrend(int $months): array
    {
        if (! $this->hasTable('book_copies')) {
            return [];
        }

        $from = now(config('app.library_timezone', 'Asia/Almaty'))->subMonths($months - 1)->startOfMonth();
        $pgsql = DB::connection()->getDriverName() === 'pgsql';
        $dateExpression = 'COALESCE(registration_date, acquisition_date)';
        $bucket = $pgsql
            ? "TO_CHAR({$dateExpression}, 'YYYY-MM')"
            : "strftime('%Y-%m', {$dateExpression})";
        $counts = BookCopy::query()
            ->where(function (Builder $dates): void {
                $dates->whereNotNull('registration_date')->orWhereNotNull('acquisition_date');
            })
            ->whereRaw("{$dateExpression} >= ?", [$from->toDateString()])
            ->selectRaw("{$bucket} AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw($bucket)
            ->pluck('aggregate', 'bucket');

        return collect(range(0, $months - 1))->map(function (int $offset) use ($from, $counts): array {
            $date = $from->copy()->addMonths($offset);

            return ['label' => $date->format('m.Y'), 'value' => (int) ($counts[$date->format('Y-m')] ?? 0)];
        })->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function catalogDistribution(string $column): array
    {
        if (! $this->hasTable('book_copies') || ! $this->hasTable('bibliographic_records')) {
            return [];
        }

        return BookCopy::query()
            ->join('bibliographic_records as records', 'records.id', '=', 'book_copies.bibliographic_record_id')
            ->whereNotIn('book_copies.status', ['written_off', 'lost'])
            ->selectRaw("COALESCE({$column}, 'other') AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw("COALESCE({$column}, 'other')")
            ->orderByDesc('aggregate')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function udcDistribution(): array
    {
        if (! $this->hasTable('book_copies') || ! $this->hasTable('bibliographic_records')) {
            return [];
        }

        $bucket = "COALESCE(NULLIF(SUBSTR(TRIM(records.udc_code), 1, 1), ''), '—')";

        return BookCopy::query()
            ->join('bibliographic_records as records', 'records.id', '=', 'book_copies.bibliographic_record_id')
            ->whereNotIn('book_copies.status', ['written_off', 'lost'])
            ->selectRaw("{$bucket} AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw($bucket)
            ->orderByDesc('aggregate')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function messageSlaDistribution(Carbon $now): array
    {
        if (! $this->hasTable('contact_messages')) {
            return [];
        }

        $openStatuses = ['open', 'in_review', 'waiting_for_user', 'response_prepared', 'reopened'];
        $base = ContactMessage::query()->whereIn('status', $openStatuses);

        return [
            ['label' => 'on_track', 'value' => (int) (clone $base)->where(fn (Builder $query) => $query->whereNull('due_at')->orWhere('due_at', '>', $now->copy()->addDay()))->count()],
            ['label' => 'due_soon', 'value' => (int) (clone $base)->whereBetween('due_at', [$now, $now->copy()->addDay()])->count()],
            ['label' => 'overdue', 'value' => (int) (clone $base)->whereNotNull('due_at')->where('due_at', '<', $now)->count()],
        ];
    }

    /** @return list<array{label: string, value: int}> */
    private function topResources(Carbon $from, Carbon $to): array
    {
        if (! $this->hasTable('loans') || ! $this->hasTable('book_copies') || ! $this->hasTable('bibliographic_records')) {
            return [];
        }

        $rows = Loan::query()
            ->join('book_copies as copies', 'copies.id', '=', 'loans.copy_id')
            ->join('bibliographic_records as records', 'records.id', '=', 'copies.bibliographic_record_id')
            ->whereBetween('loans.issued_at', [$from, $to])
            ->selectRaw('records.id as record_id, records.title, COUNT(*) AS aggregate')
            ->groupBy('records.id', 'records.title')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get();
        $records = BibliographicRecord::query()->with('translations')->whereIn('id', $rows->pluck('record_id'))->get()->keyBy('id');

        return $rows
            ->map(function ($row) use ($records): array {
                $record = $records->get($row->record_id) ?? new BibliographicRecord(['title' => (string) $row->title]);

                return ['label' => $this->localizedContent->bibliographic($record)['title'], 'value' => (int) $row->aggregate];
            })
            ->all();
    }

    /** @return array{key:string,from:Carbon,to:Carbon,previous_from:Carbon,previous_to:Carbon,compare:bool} */
    private function period(array $filters, Carbon $now): array
    {
        $key = in_array(($filters['period'] ?? 'month'), ['today', 'week', 'month', 'quarter', 'year', 'custom'], true)
            ? (string) ($filters['period'] ?? 'month')
            : 'month';
        [$from, $to] = match ($key) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'quarter' => [$now->copy()->firstOfQuarter()->startOfDay(), $now->copy()->lastOfQuarter()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom' => [
                Carbon::parse((string) ($filters['from'] ?? $now->toDateString()), $now->timezone)->startOfDay(),
                Carbon::parse((string) ($filters['to'] ?? $now->toDateString()), $now->timezone)->endOfDay(),
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
        if ($from->diffInDays($to) > 370) {
            $from = $to->copy()->subDays(370)->startOfDay();
        }
        $seconds = max(1, $from->diffInSeconds($to) + 1);

        return [
            'key' => $key,
            'from' => $from,
            'to' => $to,
            'previous_from' => $from->copy()->subSeconds($seconds),
            'previous_to' => $from->copy()->subSecond(),
            'compare' => filter_var($filters['compare'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @return array<string,int|float> */
    private function extendedCards(Carbon $from, Carbon $to, Carbon $now): array
    {
        $repositoryUsage = $this->hasTable('repository_usage_daily')
            ? (int) DB::table('repository_usage_daily')->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])->sum('event_count')
            : 0;
        $failedIntegrations = 0;
        if ($this->hasTable('integration_inbox_messages')) {
            $failedIntegrations += (int) IntegrationInboxMessage::query()->where('status', 'failed')->count();
        }
        if ($this->hasTable('integration_outbox_messages')) {
            $failedIntegrations += (int) IntegrationOutboxMessage::query()->where('status', 'failed')->count();
        }

        return [
            'records_total' => $this->count(BibliographicRecord::class, fn (Builder $query) => $query),
            'copies_available' => $this->count(BookCopy::class, fn (Builder $query) => $query->where('status', 'available')),
            'copies_issued' => $this->count(BookCopy::class, fn (Builder $query) => $query->whereIn('status', ['issued', 'overdue'])),
            'copies_repair' => $this->count(BookCopy::class, fn (Builder $query) => $query->where('status', 'under_repair')),
            'copies_lost' => $this->count(BookCopy::class, fn (Builder $query) => $query->where('status', 'lost')),
            'records_added_period' => $this->count(BibliographicRecord::class, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to])),
            'reservations_queued' => $this->count(Reservation::class, fn (Builder $query) => $query->whereIn('status', ['pending', 'queued', 'confirmed', 'in_transit'])),
            'reservations_ready' => $this->count(Reservation::class, fn (Builder $query) => $query->where('status', 'ready_for_pickup')),
            'reservations_expired_period' => $this->count(Reservation::class, fn (Builder $query) => $query->where('status', 'expired')->whereBetween('expired_at', [$from, $to])),
            'reservation_average_wait_hours' => $this->reservationAverageWaitHours($from, $to),
            'overdue_readers' => $this->overdueReaders(),
            'average_overdue_days' => $this->averageOverdueDays($now),
            'oldest_overdue_days' => $this->oldestOverdueDays($now),
            'fines_charged_period' => $this->sum(Fine::class, 'amount', fn (Builder $query) => $query->whereBetween('charged_at', [$from, $to])),
            'fines_paid_period' => $this->sum(Fine::class, 'amount', fn (Builder $query) => $query->where('status', 'paid')->whereBetween('resolved_at', [$from, $to])),
            'fines_waived_period' => $this->sum(Fine::class, 'amount', fn (Builder $query) => $query->where('status', 'waived')->whereBetween('resolved_at', [$from, $to])),
            'repository_added_period' => $this->count(RepositoryItem::class, fn (Builder $query) => $query->where('status', 'published')->whereBetween('published_at', [$from, $to])),
            'repository_usage_period' => $repositoryUsage,
            'repository_restricted' => $this->count(RepositoryItem::class, fn (Builder $query) => $query->whereIn('access_policy', ['restricted', 'embargoed', 'metadata_only'])),
            'external_active' => $this->count(ExternalResource::class, fn (Builder $query) => $query->where('publication_status', 'published')->where('is_active', true)),
            'external_unavailable' => $this->count(ExternalResource::class, fn (Builder $query) => $query->where('publication_status', 'published')->where(fn (Builder $state) => $state->where('is_active', false)->orWhere('health_status', 'unavailable'))),
            'digital_access_failures' => $this->count(DigitalMaterialAccessLog::class, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to])->where('allowed', false)),
            'complaints_open' => $this->count(ContactMessage::class, fn (Builder $query) => $query->where('type', 'complaint')->whereIn('status', ['open', 'in_review', 'reopened'])),
            'messages_high_priority' => $this->count(ContactMessage::class, fn (Builder $query) => $query->whereIn('priority', ['high', 'critical'])->whereIn('status', ['open', 'in_review', 'reopened'])),
            'data_quality_critical' => $this->countQualityObjects(null, ['critical']),
            'inactive_readers' => $this->inactiveReaders($now),
            'active_staff_accounts' => $this->activeStaffAccounts(),
            'privileged_role_assignments' => $this->privilegedRoleAssignments(),
            'tasks_open' => $this->count(LibraryTask::class, fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress', 'blocked'])),
            'tasks_overdue' => $this->count(LibraryTask::class, fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress', 'blocked'])->whereNotNull('due_at')->where('due_at', '<', $now->utc())),
            'integration_failures' => $failedIntegrations,
            'acquisition_value_period' => $this->hasTable('acquisition_orders')
                ? round((float) AcquisitionOrder::query()->whereBetween('created_at', [$from, $to])->sum('total_amount'), 2)
                : 0.0,
        ];
    }

    /** @return array<string,array{current:int|float,previous:int|float,delta:int|float,percent:float|null}> */
    private function comparison(array $cards, array $period): array
    {
        $from = $period['previous_from']->copy()->utc();
        $to = $period['previous_to']->copy()->utc();
        $previous = [
            'acquisitions_month' => $this->count(BookCopy::class, fn (Builder $query) => $query->where(function (Builder $dates) use ($from, $to): void {
                $dates->whereBetween('registration_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(fn (Builder $fallback) => $fallback->whereNull('registration_date')->whereBetween('acquisition_date', [$from->toDateString(), $to->toDateString()]));
            })),
            'issued_month' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('issued_at', [$from, $to])),
            'returned_month' => $this->count(Loan::class, fn (Builder $query) => $query->whereBetween('returned_at', [$from, $to])),
            'active_readers_month' => $this->activeReaders($from, $to),
            'new_users_month' => $this->count(User::class, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to])),
            'visits_month' => $this->count(LibraryVisit::class, fn (Builder $query) => $query->whereBetween('scanned_at', [$from, $to])),
            'digital_views_month' => $this->count(DigitalMaterialAccessLog::class, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to])->where('allowed', true)),
            'external_opens_month' => $this->count(ExternalResourceEvent::class, fn (Builder $query) => $query->whereBetween('created_at', [$from, $to])->whereIn('event_type', ['outbound_click', 'external_resource.open'])),
        ];

        return collect($previous)->map(function (int|float $old, string $key) use ($cards): array {
            $current = $cards[$key] ?? 0;
            $delta = $current - $old;

            return [
                'current' => $current,
                'previous' => $old,
                'delta' => $delta,
                'percent' => $old == 0 ? null : round(($delta / $old) * 100, 1),
            ];
        })->all();
    }

    /** @return list<array{label:string,value:int,period:string}> */
    private function unusedResources(Carbon $now): array
    {
        if (! $this->hasTable('bibliographic_records') || ! $this->hasTable('loans')) {
            return [];
        }

        $copyStats = BookCopy::query()
            ->selectRaw('bibliographic_record_id, SUM(issue_count) AS total_issue_count')
            ->groupBy('bibliographic_record_id');
        $loanStats = Loan::query()
            ->join('book_copies as usage_copies', 'usage_copies.id', '=', 'loans.copy_id')
            ->selectRaw('usage_copies.bibliographic_record_id, MAX(loans.issued_at) AS latest_issue_at')
            ->groupBy('usage_copies.bibliographic_record_id');

        return BibliographicRecord::query()
            ->joinSub($copyStats, 'copy_stats', 'copy_stats.bibliographic_record_id', '=', 'bibliographic_records.id')
            ->leftJoinSub($loanStats, 'loan_stats', 'loan_stats.bibliographic_record_id', '=', 'bibliographic_records.id')
            ->select('bibliographic_records.*', 'copy_stats.total_issue_count', 'loan_stats.latest_issue_at')
            ->with('translations')
            ->orderBy('latest_issue_at')
            ->limit(8)
            ->get()
            ->map(function (BibliographicRecord $record) use ($now): array {
                $last = $record->latest_issue_at ? Carbon::parse($record->latest_issue_at) : null;

                return [
                    'label' => $this->localizedContent->bibliographic($record)['title'],
                    'value' => (int) $record->total_issue_count,
                    'period' => $last === null ? 'never' : ($last->lt($now->copy()->subYears(3)) ? '3_plus' : ($last->lt($now->copy()->subYears(2)) ? '2_years' : '1_year')),
                ];
            })->all();
    }

    /** @return list<array{name:string,assigned:int,overdue:int,completed:int}> */
    private function staffWorkload(Carbon $from, Carbon $to): array
    {
        if (! $this->hasTable('library_tasks')) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->where('auth_provider', 'ldap')
            ->whereHas('roles', fn (Builder $roles) => $roles->whereNotIn('name', ['member', 'admin']))
            ->withCount([
                'assignedLibraryTasks as assigned_tasks_count' => fn (Builder $tasks) => $tasks->whereIn('status', ['open', 'in_progress', 'blocked']),
                'assignedLibraryTasks as overdue_tasks_count' => fn (Builder $tasks) => $tasks->whereIn('status', ['open', 'in_progress', 'blocked'])->where('due_at', '<', now('UTC')),
                'assignedLibraryTasks as completed_tasks_count' => fn (Builder $tasks) => $tasks->where('status', 'completed')->whereBetween('completed_at', [$from, $to]),
            ])->orderByDesc('overdue_tasks_count')->limit(12)->get()
            ->map(fn (User $user): array => [
                'name' => (string) $user->name,
                'assigned' => (int) $user->assigned_tasks_count,
                'overdue' => (int) $user->overdue_tasks_count,
                'completed' => (int) $user->completed_tasks_count,
            ])->all();
    }

    /** @return list<array{key:string,value:int,severity:string,action:string}> */
    private function bottlenecks(Carbon $now, array $cards): array
    {
        return collect([
            ['key' => 'reservation_queue', 'value' => (int) ($cards['reservations_queued'] ?? 0), 'severity' => 'warning', 'action' => 'reservations'],
            ['key' => 'task_overdue', 'value' => (int) ($cards['tasks_overdue'] ?? 0), 'severity' => 'high', 'action' => 'tasks'],
            ['key' => 'repository_pending', 'value' => (int) ($cards['repository_pending'] ?? 0), 'severity' => 'warning', 'action' => 'repository'],
            ['key' => 'message_sla', 'value' => (int) ($cards['message_sla_overdue'] ?? 0), 'severity' => 'critical', 'action' => 'messages'],
            ['key' => 'integration_failures', 'value' => (int) ($cards['integration_failures'] ?? 0), 'severity' => 'critical', 'action' => 'integrations'],
        ])->filter(fn (array $item): bool => $item['value'] > 0)->sortByDesc(fn (array $item): int => match ($item['severity']) {
            'critical' => 4, 'high' => 3, 'warning' => 2, default => 1,
        })->values()->all();
    }

    /** @return list<array{label:string,value:int}> */
    private function statusDistribution(string $table, string $column = 'status'): array
    {
        if (! $this->hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->selectRaw("COALESCE({$column}, 'unknown') AS bucket, COUNT(*) AS aggregate")
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }

    /** @return list<array{label:string,value:int}> */
    private function readerSegmentDistribution(): array
    {
        if (! $this->hasTable('reader_profiles')) {
            return [];
        }

        return DB::table('reader_profiles')
            ->selectRaw("COALESCE(category, 'other') AS bucket, COUNT(*) AS aggregate")
            ->groupBy('category')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }

    /** @return list<array{label:string,value:int}> */
    private function licenceDistribution(Carbon $now): array
    {
        if (! $this->hasTable('external_resources')) {
            return [];
        }

        $base = ExternalResource::query()->whereIn('resource_type', ['licensed', 'partner']);

        return [
            ['label' => 'active', 'value' => (int) (clone $base)->where('publication_status', 'published')->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('contract_ends_at')->orWhereDate('contract_ends_at', '>=', $now->toDateString()))->count()],
            ['label' => 'expiring', 'value' => (int) (clone $base)->whereDate('contract_ends_at', '>=', $now->toDateString())->whereDate('contract_ends_at', '<=', $now->copy()->addDays(90)->toDateString())->count()],
            ['label' => 'expired', 'value' => (int) (clone $base)->whereDate('contract_ends_at', '<', $now->toDateString())->count()],
            ['label' => 'unavailable', 'value' => (int) (clone $base)->where(fn (Builder $q) => $q->where('is_active', false)->orWhere('health_status', 'unavailable'))->count()],
        ];
    }

    private function overdueReaders(): int
    {
        return $this->hasTable('loans')
            ? (int) Loan::query()->where('status', 'overdue')->whereNull('returned_at')->distinct()->count('user_id')
            : 0;
    }

    private function averageOverdueDays(Carbon $now): float
    {
        if (! $this->hasTable('loans')) {
            return 0.0;
        }

        $expression = DB::connection()->getDriverName() === 'pgsql'
            ? 'AVG(EXTRACT(EPOCH FROM (? - due_at)) / 86400)'
            : 'AVG(julianday(?) - julianday(due_at))';

        return round((float) Loan::query()->where('status', 'overdue')->whereNull('returned_at')->whereNotNull('due_at')->selectRaw("{$expression} AS aggregate", [$now->utc()])->value('aggregate'), 1);
    }

    private function oldestOverdueDays(Carbon $now): int
    {
        if (! $this->hasTable('loans')) {
            return 0;
        }

        $dueAt = Loan::query()->where('status', 'overdue')->whereNull('returned_at')->whereNotNull('due_at')->min('due_at');

        return $dueAt ? max(0, Carbon::parse($dueAt)->diffInDays($now->utc())) : 0;
    }

    private function reservationAverageWaitHours(Carbon $from, Carbon $to): float
    {
        if (! $this->hasTable('reservations')) {
            return 0.0;
        }

        $query = Reservation::query()->whereBetween('created_at', [$from, $to])->whereNotNull('ready_at');
        $expression = DB::connection()->getDriverName() === 'pgsql'
            ? 'AVG(EXTRACT(EPOCH FROM (ready_at - created_at)) / 3600)'
            : 'AVG((julianday(ready_at) - julianday(created_at)) * 24)';

        return round((float) $query->selectRaw("{$expression} AS aggregate")->value('aggregate'), 1);
    }

    private function inactiveReaders(Carbon $now): int
    {
        if (! $this->hasTable('reader_profiles')) {
            return 0;
        }

        $months = max(1, (int) config('library.analytics.inactive_reader_months', 12));
        $cutoff = $now->copy()->subMonths($months)->utc();

        return (int) DB::table('reader_profiles as profiles')
            ->where('profiles.status', 'active')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('loans')->whereColumn('loans.user_id', 'profiles.user_id')->where('loans.issued_at', '>=', $cutoff))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('library_visits')->whereColumn('library_visits.user_id', 'profiles.user_id')->where('library_visits.scanned_at', '>=', $cutoff))
            ->count();
    }

    private function activeStaffAccounts(): int
    {
        if (! $this->hasTable('users') || ! $this->hasTable('model_has_roles')) {
            return 0;
        }

        return (int) DB::table('users')->where('is_active', true)->whereExists(function ($query): void {
            $query->selectRaw('1')->from('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereColumn('model_has_roles.model_id', 'users.id')->where('model_has_roles.model_type', User::class)->where('roles.name', '!=', 'member');
        })->count();
    }

    private function privilegedRoleAssignments(): int
    {
        if (! $this->hasTable('model_has_roles') || ! $this->hasTable('roles')) {
            return 0;
        }

        return (int) DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['director', 'admin', 'senior_librarian'])->count();
    }

    /** @return array<string,list<array{label:string,value:int}>> */
    private function popularResources(Carbon $from, Carbon $to): array
    {
        $issues = $this->topResources($from, $to);
        $reservations = [];
        if ($this->hasTable('reservations')) {
            $rows = Reservation::query()->join('bibliographic_records as records', 'records.id', '=', 'reservations.bibliographic_record_id')
                ->whereBetween('reservations.created_at', [$from, $to])->selectRaw('records.id AS record_id, COUNT(*) AS aggregate')
                ->groupBy('records.id')->orderByDesc('aggregate')->limit(5)->get();
            $records = BibliographicRecord::query()->with('translations')->whereIn('id', $rows->pluck('record_id'))->get()->keyBy('id');
            $reservations = $rows->map(fn ($row): array => [
                'label' => $this->localizedContent->bibliographic($records->get($row->record_id))['title'],
                'value' => (int) $row->aggregate,
            ])->all();
        }

        return ['issues' => $issues, 'reservations' => $reservations];
    }
}
