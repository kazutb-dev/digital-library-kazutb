<?php

namespace App\Services\Reports;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\DataQualityIssue;
use App\Models\DuplicateGroup;
use App\Models\ContactMessage;
use App\Models\LibraryTask;
use App\Models\Catalog\Reservation;
use App\Models\Catalog\CirculationIncidentCase;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Role-specific, aggregate-only work queues for acquisitions and cataloguing. */
final class OperationalDashboardService
{
    /**
     * @return null|array{
     *     role: 'acquisitions'|'cataloguer'|'bibliographer'|'senior_librarian',
     *     cards: array<string, int>,
     *     distribution: list<array{label: string, value: int}>,
     *     trend: list<array{label: string, value: int}>
     * }
     */
    public function build(string $role): ?array
    {
        return match ($role) {
            'acquisitions' => $this->acquisitions(),
            'cataloguer' => $this->cataloguer(),
            'bibliographer' => $this->bibliographer(),
            'senior_librarian' => $this->seniorLibrarian(),
            default => null,
        };
    }

    /** @return array{role: 'acquisitions', cards: array<string, int>, distribution: list<array{label: string, value: int}>, trend: list<array{label: string, value: int}>} */
    private function acquisitions(): array
    {
        $now = now(config('app.library_timezone', 'Asia/Almaty'));
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $received = static fn (Builder $query): Builder => $query->where(function (Builder $dates): void {
            $dates->whereNotNull('registration_date')->orWhereNotNull('acquisition_date');
        });

        return [
            'role' => 'acquisitions',
            'cards' => [
                'received_today' => $this->countCopies(fn (Builder $query) => $received($query)->whereRaw('COALESCE(registration_date, acquisition_date) = ?', [$today])),
                'received_month' => $this->countCopies(fn (Builder $query) => $received($query)->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$monthStart, $monthEnd])),
                'sources_month' => $this->countCopySources($monthStart, $monthEnd),
                'processing_copies' => $this->countCopies(fn (Builder $query) => $query->where('status', 'in_processing')),
                'incomplete_records' => $this->countRecords(fn (Builder $query) => $query->where('is_draft', true)),
            ],
            'distribution' => $this->acquisitionSources($monthStart, $monthEnd),
            'trend' => $this->acquisitionTrend(12),
        ];
    }

    /** @return array{role: 'cataloguer', cards: array<string, int>, distribution: list<array{label: string, value: int}>, trend: list<array{label: string, value: int}>} */
    private function cataloguer(): array
    {
        $cards = [
            'incomplete_records' => $this->countRecords(fn (Builder $query) => $query->where('is_draft', true)),
            'without_udc' => $this->countRecords(fn (Builder $query) => $query->where(fn (Builder $udc) => $udc->whereNull('udc_code')->orWhere('udc_code', ''))),
            'manual_review' => $this->countRecords(fn (Builder $query) => $query->where('needs_manual_review', true)),
            'duplicate_groups' => DatabaseSchema::hasTable('duplicate_groups')
                ? (int) DuplicateGroup::query()->whereIn('status', ['open', 'assigned', 'in_review'])->count()
                : 0,
            'data_quality_open' => DatabaseSchema::hasTable('data_quality_issues')
                ? $this->countQualityObjects('bibliographic_record')
                : 0,
        ];

        return [
            'role' => 'cataloguer',
            'cards' => $cards,
            'distribution' => $this->recordTypes(),
            'trend' => [],
        ];
    }

    /** @return array{role:string,cards:array<string,int>,distribution:list<array{label:string,value:int}>,trend:list<array{label:string,value:int}>} */
    private function bibliographer(): array
    {
        $messages = DatabaseSchema::hasTable('contact_messages') ? ContactMessage::query() : null;

        return [
            'role' => 'bibliographer',
            'cards' => [
                'assigned_messages' => $messages ? (clone $messages)->whereNotIn('status', ['resolved', 'closed', 'rejected'])->count() : 0,
                'bibliographic_requests' => $messages ? (clone $messages)->whereIn('type', ['question', 'request'])->whereNotIn('status', ['resolved', 'closed', 'rejected'])->count() : 0,
                'repository_published' => DatabaseSchema::hasTable('repository_items') ? (int) \App\Models\Catalog\RepositoryItem::query()->where('status', 'published')->count() : 0,
                'external_resources' => DatabaseSchema::hasTable('external_resources') ? (int) \App\Models\ExternalResource::query()->where('publication_status', 'published')->count() : 0,
                'open_tasks' => DatabaseSchema::hasTable('library_tasks') ? (int) LibraryTask::query()->whereIn('status', ['open', 'in_progress', 'blocked'])->count() : 0,
            ],
            'distribution' => [],
            'trend' => [],
        ];
    }

    /** @return array{role:string,cards:array<string,int>,distribution:list<array{label:string,value:int}>,trend:list<array{label:string,value:int}>} */
    private function seniorLibrarian(): array
    {
        return [
            'role' => 'senior_librarian',
            'cards' => [
                'reservation_queue' => DatabaseSchema::hasTable('reservations') ? (int) Reservation::query()->whereIn('status', ['pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup'])->count() : 0,
                'open_incidents' => DatabaseSchema::hasTable('circulation_incident_cases') ? (int) CirculationIncidentCase::query()->whereNotIn('status', ['resolved', 'closed', 'rejected'])->count() : 0,
                'sla_messages' => DatabaseSchema::hasTable('contact_messages') ? (int) ContactMessage::query()->whereNotIn('status', ['resolved', 'closed', 'rejected'])->where('due_at', '<', now('UTC'))->count() : 0,
                'quality_issues' => DatabaseSchema::hasTable('data_quality_issues') ? $this->countQualityObjects(null, ['critical', 'high']) : 0,
                'overdue_tasks' => DatabaseSchema::hasTable('library_tasks') ? (int) LibraryTask::query()->whereIn('status', ['open', 'in_progress', 'blocked'])->where('due_at', '<', now('UTC'))->count() : 0,
            ],
            'distribution' => [],
            'trend' => [],
        ];
    }

    private function countCopies(callable $scope): int
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return 0;
        }

        return (int) $scope(BookCopy::query())->count();
    }

    private function countRecords(callable $scope): int
    {
        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            return 0;
        }

        return (int) $scope(BibliographicRecord::query())->count();
    }

    /** Count affected library objects, not individual findings. */
    /** @param list<string> $severities */
    private function countQualityObjects(?string $entityType = null, array $severities = []): int
    {
        $objects = DataQualityIssue::query()
            ->actionable()
            ->when($entityType !== null, fn (Builder $query) => $query->where('entity_type', $entityType))
            ->when($severities !== [], fn (Builder $query) => $query->whereIn('severity', $severities))
            ->select(['entity_type', 'entity_id'])
            ->distinct();

        return (int) DB::query()->fromSub($objects->toBase(), 'quality_objects')->count();
    }

    private function countCopySources(string $from, string $to): int
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return 0;
        }

        return (int) BookCopy::query()
            ->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$from, $to])
            ->whereNotNull('acquisition_source')
            ->distinct()
            ->count('acquisition_source');
    }

    /** @return list<array{label: string, value: int}> */
    private function acquisitionSources(string $from, string $to): array
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return [];
        }

        return BookCopy::query()
            ->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$from, $to])
            ->selectRaw("COALESCE(acquisition_source, 'other') AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw("COALESCE(acquisition_source, 'other')")
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function acquisitionTrend(int $months): array
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return [];
        }

        $from = now(config('app.library_timezone', 'Asia/Almaty'))->subMonths($months - 1)->startOfMonth();
        $date = 'COALESCE(registration_date, acquisition_date)';
        $pgsql = BookCopy::query()->getConnection()->getDriverName() === 'pgsql';
        $bucket = $pgsql ? "TO_CHAR({$date}, 'YYYY-MM')" : "strftime('%Y-%m', {$date})";
        /** @var Collection<string, int|string> $counts */
        $counts = BookCopy::query()
            ->whereRaw("{$date} >= ?", [$from->toDateString()])
            ->selectRaw("{$bucket} AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw($bucket)
            ->pluck('aggregate', 'bucket');

        return collect(range(0, $months - 1))->map(function (int $offset) use ($from, $counts): array {
            $month = $from->copy()->addMonths($offset);

            return ['label' => $month->format('m.Y'), 'value' => (int) ($counts[$month->format('Y-m')] ?? 0)];
        })->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function recordTypes(): array
    {
        if (! DatabaseSchema::hasTable('bibliographic_records')) {
            return [];
        }

        return BibliographicRecord::query()
            ->where(fn (Builder $query) => $query->where('is_draft', true)->orWhere('needs_manual_review', true))
            ->selectRaw("COALESCE(resource_type, 'other') AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw("COALESCE(resource_type, 'other')")
            ->orderByDesc('aggregate')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }
}
