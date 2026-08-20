<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\Loan;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\Reservation;
use App\Models\ContactMessage;
use App\Models\DataImportBatch;
use App\Models\DataQualityIssue;
use App\Services\Reports\DirectorAnalyticsService;
use App\Services\Reports\OperationalDashboardService;
use App\Support\DatabaseSchema;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Librarian Overview — every figure is a live aggregate over the domain
 * tables. This page replaced a static mockup whose numbers (42/18/07/05)
 * were hardcoded in the Blade.
 */
class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DirectorAnalyticsService $directorAnalytics,
        OperationalDashboardService $operationalDashboard,
    ): View {
        $user = $request->user();
        abort_if($user === null, 401);
        $executiveFilters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'week', 'month', 'quarter', 'year', 'custom'])],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:from'],
            'compare' => ['nullable', 'boolean'],
        ]);

        $canCirculationWorkflow = $user->canAny(['circulation.issue', 'circulation.return']);
        $canCirculation = $canCirculationWorkflow || $user->can('reports.view_full');
        $canReservations = $user->can('reservation.confirm');
        $canCatalogCleanup = $user->canAny(['data_cleanup.access', 'catalog.edit_record']);
        $canRepository = $user->canAny(['repository.upload', 'repository.approve', 'repository.publish']);
        $canFines = $user->can('fines.view');
        $canMessages = $user->canAny(['messages.view_all', 'messages.view_assigned']);
        $canQuality = $user->can('data_quality.view');
        $canOperationalReports = $user->canAny([
            'reports.view_acquisitions', 'reports.view_ops', 'reports.view_full',
            'fines.view', 'incidents.view_reports', 'data_quality.view_reports',
            'data_quality.view', 'news.view_analytics', 'messages.view_analytics',
            'repository.view_analytics', 'external_resources.view_analytics',
            'staff_performance.view', 'system.logs',
        ]);
        $canManageCopies = $user->can('copies.edit');
        $libraryNow = now((string) config('app.library_timezone', 'Asia/Almaty'));
        $todayStart = $libraryNow->copy()->startOfDay()->utc();
        $todayEnd = $libraryNow->copy()->endOfDay()->utc();

        $overdueCount = $canCirculation ? Loan::query()->where('status', 'overdue')->whereNull('returned_at')->count() : 0;
        $activeLoans = $canCirculation ? Loan::query()->open()->count() : 0;
        $pendingPulls = $canReservations ? Reservation::query()->whereIn('status', ['pending', 'confirmed'])->count() : 0;
        $readyPickups = $canReservations ? Reservation::query()->where('status', 'ready_for_pickup')->count() : 0;
        $draftRecords = $canCatalogCleanup ? BibliographicRecord::query()->where('is_draft', true)->count() : 0;
        $repositoryPending = $canRepository && DatabaseSchema::hasTable('repository_items')
            ? RepositoryItem::query()
                ->whereIn('status', ['metadata_review', 'author_verification', 'rights_review', 'quality_review', 'pending_approval'])
                ->count()
            : 0;
        $pendingFines = $canFines ? Fine::query()->where('status', 'pending')->count() : 0;
        $unresolvedMessages = $canMessages && DatabaseSchema::hasTable('contact_messages')
            ? ContactMessage::query()->whereIn('status', ['open', 'in_review'])->count()
            : 0;
        $issuedToday = $canCirculation ? Loan::query()->whereBetween('issued_at', [$todayStart, $todayEnd])->count() : 0;
        $returnedToday = $canCirculation ? Loan::query()->whereBetween('returned_at', [$todayStart, $todayEnd])->count() : 0;
        $visitsToday = $canOperationalReports && DatabaseSchema::hasTable('library_visits')
            ? LibraryVisit::query()->whereBetween('scanned_at', [$todayStart, $todayEnd])->count()
            : 0;
        $problemCopies = $canManageCopies
            ? BookCopy::query()->where(fn ($query) => $query->whereIn('status', ['lost', 'under_repair'])->orWhere('condition', 'damaged'))->count()
            : 0;
        $qualityMetrics = $canQuality && DatabaseSchema::hasTable('data_quality_issues') ? [
            'records_attention' => DataQualityIssue::query()->actionable()->where('entity_type', 'bibliographic_record')->distinct()->count('entity_id'),
            'copies_attention' => DataQualityIssue::query()->actionable()->where('entity_type', 'book_copy')->distinct()->count('entity_id'),
            'high_priority' => $this->countDistinctQualityObjects(DataQualityIssue::query()->actionable()->whereIn('severity', ['critical', 'high'])),
            'overdue' => $this->countDistinctQualityObjects(DataQualityIssue::query()->actionable()->where('due_at', '<', now())),
        ] : ['records_attention' => 0, 'copies_attention' => 0, 'high_priority' => 0, 'overdue' => 0];

        $executiveAnalytics = $user->hasRole('director') && $user->can('reports.view_full')
            ? $directorAnalytics->build($executiveFilters)
            : null;
        $canonicalRole = $user->effectiveRole();

        return view('librarian.overview', [
            'metrics' => [
                'active_loans' => $activeLoans,
                'overdue' => $overdueCount,
                'pending_pulls' => $pendingPulls,
                'ready_pickups' => $readyPickups,
                'draft_records' => $draftRecords,
                'repository_pending' => $repositoryPending,
                'pending_fines' => $pendingFines,
                'unresolved_messages' => $unresolvedMessages,
                'issued_today' => $issuedToday,
                'returned_today' => $returnedToday,
                'visits_today' => $visitsToday,
                'problem_copies' => $problemCopies,
            ],
            'overdueLoans' => $canCirculationWorkflow
                ? Loan::query()
                    ->where('status', 'overdue')
                    ->whereNull('returned_at')
                    ->with(['reader', 'copy.bibliographicRecord'])
                    ->orderBy('due_at')
                    ->limit(6)
                    ->get()
                : collect(),
            'readyReservations' => $canReservations
                ? Reservation::query()
                    ->where('status', 'ready_for_pickup')
                    ->with(['reader', 'bibliographicRecord'])
                    ->orderBy('expires_at')
                    ->limit(6)
                    ->get()
                : collect(),
            'draftRecords' => $canCatalogCleanup
                ? BibliographicRecord::query()
                    ->where('is_draft', true)
                    ->orderByDesc('updated_at')
                    ->limit(5)
                    ->get()
                : collect(),
            'qualityMetrics' => $qualityMetrics,
            'executiveAnalytics' => $executiveAnalytics,
            'executiveStaff' => $executiveAnalytics !== null
                ? \App\Models\User::query()
                    ->where('is_active', true)
                    ->where('auth_provider', 'ldap')
                    ->whereHas('roles', fn ($roles) => $roles->whereNotIn('name', ['member', 'admin']))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'operationalAnalytics' => $operationalDashboard->build($canonicalRole),
        ]);
    }

    private function countDistinctQualityObjects(Builder $query): int
    {
        return (int) DB::query()
            ->fromSub($query->select(['entity_type', 'entity_id'])->distinct(), 'quality_objects')
            ->count();
    }
}
