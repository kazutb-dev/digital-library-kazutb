<?php

namespace App\Services\Reports;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\Reservation;
use App\Models\ContactMessage;
use App\Models\DataQualityIssue;
use App\Models\DuplicateGroup;
use App\Models\ExternalResource;
use App\Models\LibraryTask;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Role-specific, aggregate-only work queues for acquisitions and cataloguing. */
final class OperationalDashboardService
{
    /**
     * @return null|array{
     *     role: 'acquisitions'|'cataloguer'|'bibliographer'|'senior_librarian',
     *     cards: array<string, int|float>,
     *     distribution: list<array{label: string, value: int}>,
     *     distributions?: array<string, list<array{label: string, value: int|float}>>,
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

    /** @return array{role: 'acquisitions', cards: array<string, int|float>, distribution: list<array{label: string, value: int}>, distributions: array<string, list<array{label: string, value: int|float}>>, trend: list<array{label: string, value: int}>} */
    private function acquisitions(): array
    {
        $now = now(config('app.library_timezone', 'Asia/Almaty'));
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();
        $yearEnd = $now->copy()->endOfYear()->toDateString();

        $received = static fn (Builder $query): Builder => $query->where(function (Builder $dates): void {
            $dates->whereNotNull('registration_date')->orWhereNotNull('acquisition_date');
        });

        return [
            'role' => 'acquisitions',
            'cards' => [
                'received_today' => $this->countCopies(fn (Builder $query) => $received($query)->whereRaw('COALESCE(registration_date, acquisition_date) = ?', [$today])),
                'received_month' => $this->countCopies(fn (Builder $query) => $received($query)->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$monthStart, $monthEnd])),
                'arrivals_current_month' => $this->countCopies(fn (Builder $query) => $received($query)->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$monthStart, $monthEnd])),
                'sources_month' => $this->countCopySources($monthStart, $monthEnd),
                'acquisition_value_month' => $this->acquisitionValue($monthStart, $monthEnd),
                'writeoffs_year' => $this->writeoffCount($yearStart, $yearEnd),
                'ksu_entries_year' => $this->ksuEntries((int) $now->year),
                'ksu_conflicts' => $this->ksuConflicts(),
                'processing_copies' => $this->countCopies(fn (Builder $query) => $query->where('status', 'in_processing')),
                'incomplete_records' => $this->countRecords(fn (Builder $query) => $query->where('is_draft', true)),
            ],
            'distribution' => $this->acquisitionSources($monthStart, $monthEnd),
            'distributions' => [
                'sources' => $this->acquisitionSources($monthStart, $monthEnd),
                'value_by_source' => $this->acquisitionValueBySource($monthStart, $monthEnd),
                'languages' => $this->acquisitionDimension('language', $monthStart, $monthEnd),
                'udc' => $this->acquisitionDimension('udc_code', $monthStart, $monthEnd),
                'sigla' => $this->acquisitionSigla($monthStart, $monthEnd),
            ],
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
                'repository_published' => DatabaseSchema::hasTable('repository_items') ? (int) RepositoryItem::query()->where('status', 'published')->count() : 0,
                'external_resources' => DatabaseSchema::hasTable('external_resources') ? (int) ExternalResource::query()->where('publication_status', 'published')->count() : 0,
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

    private function acquisitionValue(string $from, string $to): float
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return 0.0;
        }

        return round((float) BookCopy::query()
            ->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$from, $to])
            ->sum('price'), 2);
    }

    private function writeoffCount(string $from, string $to): int
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return 0;
        }

        $query = BookCopy::query()->where('status', 'written_off');
        if (Schema::hasColumn('book_copies', 'writeoff_date')) {
            $query->where(function (Builder $period) use ($from, $to): void {
                $period->whereBetween('writeoff_date', [$from, $to])
                    ->orWhere(fn (Builder $fallback) => $fallback->whereNull('writeoff_date')->whereBetween('updated_at', [$from.' 00:00:00', $to.' 23:59:59']));
            });
        } else {
            $query->whereBetween('updated_at', [$from.' 00:00:00', $to.' 23:59:59']);
        }

        return (int) $query->count();
    }

    private function ksuEntries(int $year): int
    {
        return DatabaseSchema::hasTable('ksu_entries')
            ? (int) DB::table('ksu_entries')->where('year', $year)->where('status', '<>', 'draft')->count()
            : 0;
    }

    private function ksuConflicts(): int
    {
        return DatabaseSchema::hasTable('ksu_conflicts')
            ? (int) DB::table('ksu_conflicts')->where('status', 'open')->count()
            : 0;
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

    /** @return list<array{label: string, value: float}> */
    private function acquisitionValueBySource(string $from, string $to): array
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return [];
        }

        return BookCopy::query()
            ->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$from, $to])
            ->selectRaw("COALESCE(acquisition_source, 'other') AS bucket, COALESCE(SUM(price), 0) AS aggregate")
            ->groupByRaw("COALESCE(acquisition_source, 'other')")
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => round((float) $row->aggregate, 2)])
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function acquisitionDimension(string $column, string $from, string $to): array
    {
        if (! DatabaseSchema::hasTable('book_copies') || ! DatabaseSchema::hasTable('bibliographic_records')) {
            return [];
        }

        $qualified = 'records.'.$column;

        return BookCopy::query()
            ->join('bibliographic_records as records', 'records.id', '=', 'book_copies.bibliographic_record_id')
            ->whereRaw('COALESCE(book_copies.registration_date, book_copies.acquisition_date) BETWEEN ? AND ?', [$from, $to])
            ->selectRaw("COALESCE({$qualified}, '—') AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw("COALESCE({$qualified}, '—')")
            ->orderByDesc('aggregate')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->bucket, 'value' => (int) $row->aggregate])
            ->all();
    }

    /** @return list<array{label: string, value: int}> */
    private function acquisitionSigla(string $from, string $to): array
    {
        if (! DatabaseSchema::hasTable('book_copies')) {
            return [];
        }

        $bucket = Schema::hasColumn('book_copies', 'sigla_code')
            ? "COALESCE(NULLIF(sigla_code, ''), NULLIF(storage_sigla, ''), '—')"
            : "COALESCE(NULLIF(storage_sigla, ''), '—')";

        return BookCopy::query()
            ->whereRaw('COALESCE(registration_date, acquisition_date) BETWEEN ? AND ?', [$from, $to])
            ->selectRaw("{$bucket} AS bucket, COUNT(*) AS aggregate")
            ->groupByRaw($bucket)
            ->orderByDesc('aggregate')
            ->limit(12)
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
