<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\Reservation;
use App\Models\ContactMessage;
use App\Models\DataImportBatch;
use App\Models\DataQualityIssue;
use App\Support\DatabaseSchema;
use Illuminate\View\View;

/**
 * Librarian Overview — every figure is a live aggregate over the domain
 * tables. This page replaced a static mockup whose numbers (42/18/07/05)
 * were hardcoded in the Blade.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $overdueCount = Loan::query()->where('status', 'overdue')->whereNull('returned_at')->count();
        $activeLoans = Loan::query()->open()->count();
        $pendingPulls = Reservation::query()->whereIn('status', ['pending', 'confirmed'])->count();
        $readyPickups = Reservation::query()->where('status', 'ready_for_pickup')->count();
        $draftRecords = BibliographicRecord::query()->where('is_draft', true)->count();
        $repositoryPending = RepositoryItem::query()->where('status', 'under_review')->count();
        $pendingFines = Fine::query()->where('status', 'pending')->count();
        $unresolvedMessages = DatabaseSchema::hasTable('contact_messages')
            ? ContactMessage::query()->whereIn('status', ['open', 'in_review'])->count()
            : 0;
        $issuedToday = Loan::query()->whereDate('issued_at', today())->count();
        $returnedToday = Loan::query()->whereDate('returned_at', today())->count();
        $qualityMetrics = DatabaseSchema::hasTable('data_quality_issues') ? [
            'open' => DataQualityIssue::query()->actionable()->count(),
            'critical' => DataQualityIssue::query()->actionable()->whereIn('severity', ['critical', 'high'])->count(),
            'overdue' => DataQualityIssue::query()->actionable()->where('due_at', '<', now())->count(),
            'migration_batches' => DataImportBatch::query()->whereIn('status', ['uploaded', 'parsing', 'staged', 'review_required', 'ready', 'importing', 'partially_imported'])->count(),
        ] : ['open' => 0, 'critical' => 0, 'overdue' => 0, 'migration_batches' => 0];

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
            ],
            'overdueLoans' => Loan::query()
                ->where('status', 'overdue')
                ->whereNull('returned_at')
                ->with(['reader', 'copy.bibliographicRecord'])
                ->orderBy('due_at')
                ->limit(6)
                ->get(),
            'readyReservations' => Reservation::query()
                ->where('status', 'ready_for_pickup')
                ->with(['reader', 'bibliographicRecord'])
                ->orderBy('expires_at')
                ->limit(6)
                ->get(),
            'draftRecords' => BibliographicRecord::query()
                ->where('is_draft', true)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(),
            'qualityMetrics' => $qualityMetrics,
        ]);
    }
}
