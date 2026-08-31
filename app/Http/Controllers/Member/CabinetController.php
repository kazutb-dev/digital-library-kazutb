<?php

namespace App\Http\Controllers\Member;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\LiteratureCollection;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\LibraryNotificationService;
use App\Services\Catalog\MachineCodeService;
use App\Services\Catalog\ReservationInsightService;
use App\Services\Catalog\ReservationQueueService;
use App\Services\Member\MemberCabinetService;
use App\Services\ShortlistStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Reader personal cabinet (Master.md 15).
 *
 * Every screen under /dashboard/* is backed by the canonical circulation
 * schema — reader profile, loans, reservations, fines and notifications.
 * Ownership is enforced here, in the controller: a reader may only ever read
 * or mutate rows whose user_id matches the authenticated account, regardless
 * of what the UI happens to render.
 */
class CabinetController extends Controller
{
    public function __construct(
        private readonly CirculationService $circulation,
        private readonly ReservationQueueService $reservations,
        private readonly LibraryNotificationService $notifications,
        private readonly ShortlistStorageService $shortlist,
        private readonly ReservationInsightService $reservationInsights,
        private readonly MemberCabinetService $cabinet,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * 15.1-15.2 — the cabinet landing page: ticket, limits, what is on hand,
     * what is reserved, what is owed and what is new.
     */
    public function dashboard(Request $request): View
    {
        $reader = $this->reader($request);
        $summary = $this->circulation->readerSummary($reader);
        $capabilities = [
            'loans' => $reader->can('loans.view_own'),
            'reservations' => $reader->can('reservation.view_own'),
            'fines' => $reader->can('fines.view_own'),
            'incidents' => $reader->can('incidents.view_own'),
            'notifications' => $reader->can('notifications.view_own'),
            'collections' => $reader->can('collections.manage_own') || $reader->can('collections.view_public'),
            'shortlist' => $reader->can('shortlist.manage_own'),
            'card' => $reader->can('reader_card.view_own'),
            'messages' => $reader->can('messages.view_own'),
            'catalog' => $reader->can('catalog.search'),
        ];

        $activeReservations = $capabilities['reservations']
            ? $this->reservationQuery($reader)
                ->active()
                ->orderByRaw("CASE status WHEN 'ready_for_pickup' THEN 0 WHEN 'in_transit' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'queued' THEN 3 ELSE 4 END")
                ->orderBy('queue_sequence')
                ->orderBy('created_at')
                ->get()
            : collect();
        $activeReservations->each(function (Reservation $reservation): void {
            if ($reservation->status === 'queued') {
                $reservation->setAttribute('queue_position', $this->reservationInsights->queuePosition($reservation));
            }
        });

        /** @var Collection<int, Loan> $openLoans */
        $openLoans = $capabilities['loans'] ? $summary['open_loans'] : collect();
        $restrictions = $this->cabinet->restrictions($reader)->filter(function (array $restriction) use ($capabilities): bool {
            return match ($restriction['key'] ?? null) {
                'overdue' => $capabilities['loans'],
                'fine' => $capabilities['fines'],
                'incident' => $capabilities['incidents'],
                default => true,
            };
        })->values();

        return $this->view($request, 'member.dashboard', [
            'memberCapabilities' => $capabilities,
            'profile' => $summary['profile'],
            'openLoans' => $openLoans,
            'priorityLoan' => $openLoans->first(),
            'overdueCount' => $capabilities['loans'] ? $summary['overdue_count'] : 0,
            'maxLoans' => $capabilities['loans'] ? $summary['max_loans'] : 0,
            'loansRemaining' => $capabilities['loans'] ? $summary['loans_remaining'] : 0,
            'blocked' => $summary['blocked'],
            'overdueBlocked' => $capabilities['loans'] && $summary['overdue_blocked'],
            'pendingFinesTotal' => $capabilities['fines'] ? $summary['pending_fines_total'] : 0,
            'pendingFinesCount' => $capabilities['fines'] ? $summary['pending_fines']->count() : 0,
            'openIncidentsCount' => $capabilities['incidents'] ? $summary['open_incident_cases'] : 0,
            'dueSoonCount' => $openLoans->filter(fn (Loan $loan): bool => $loan->daysRemaining() >= 0 && $loan->daysRemaining() <= 3)->count(),
            'activeReservations' => $activeReservations,
            'readyReservationsCount' => $activeReservations->where('status', 'ready_for_pickup')->count(),
            'restrictions' => $restrictions,
            'unreadNotifications' => $capabilities['notifications'] ? $this->notifications->unreadCountFor($reader) : 0,
            'recentNotifications' => $capabilities['notifications']
                ? ReaderNotification::query()->where('user_id', $reader->getKey())->latest()->limit(4)->get()
                : collect(),
            'shortlistItems' => $capabilities['shortlist'] ? $this->shortlistItems($request)->take(3) : collect(),
            'shortlistTotal' => $capabilities['collections']
                ? LiteratureCollection::query()->where('user_id', (string) $reader->getKey())->withCount('items')->get()->sum('items_count')
                : 0,
            'recommendations' => $capabilities['catalog'] ? $this->recommendationsFor($reader) : collect(),
        ]);
    }

    /**
     * 15.2 — everything currently on hand, with the renewal control (5.3).
     */
    public function loans(Request $request): View
    {
        $reader = $this->reader($request);

        $loans = Loan::query()
            ->open()
            ->where('user_id', $reader->getKey())
            ->with(['copy.bibliographicRecord', 'copy.branch', 'copy.fund'])
            ->orderBy('due_at')
            ->get();
        $loans->each(fn (Loan $loan) => $loan->setAttribute('renewal_eligibility', $this->cabinet->canRenew($loan, $reader)));

        return $this->view($request, 'member.loans', [
            'profile' => ReaderProfile::forUser($reader),
            'loans' => $loans,
            'maxRenewals' => CirculationService::MAX_RENEWALS,
            'renewalAllowed' => (bool) Setting::valueFor('renewal_allowed', true),
        ]);
    }

    public function ticket(Request $request, MachineCodeService $codes): View
    {
        $reader = $this->reader($request);
        $profile = ReaderProfile::forUser($reader);
        $value = $profile->barcode ?: $profile->ticket_number;
        $this->audit->log('reader_card.viewed', 'reader_profile', $profile->getKey(), scope: 'personal', actor: $reader);

        return $this->view($request, 'member.ticket', [
            'profile' => $profile,
            'code128Svg' => $codes->code128($value, 2, 72),
            'qrSvg' => $codes->qr($value),
        ]);
    }

    public function cardPrinted(Request $request): RedirectResponse
    {
        $profile = ReaderProfile::forUser($this->reader($request));
        $this->audit->logRequired('reader_card.printed', 'reader_profile', $profile->getKey(), scope: 'personal', actor: $request->user());

        return back();
    }

    /**
     * 5.3 — reader-initiated renewal. Ownership is checked here as well as in
     * the service, so a forged loan id can never reach the domain layer.
     */
    public function renewLoan(Request $request, Loan $loan): RedirectResponse
    {
        $reader = $this->reader($request);
        abort_unless((int) $loan->user_id === (int) $reader->getKey(), 403);

        $validated = $request->validate(['expected_due_at' => ['required', 'date']]);

        try {
            $loan = $this->circulation->renew($loan, $reader, byStaff: false, expectedDueAt: $validated['expected_due_at']);
        } catch (CirculationException $exception) {
            return back()->withErrors(['loan' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.circulation.renewed_success', [
            'due' => $loan->due_at?->format('d.m.Y') ?? '—',
        ]));
    }

    /**
     * 15.3 — active queue plus the closed history of past requests.
     */
    public function reservations(Request $request): View
    {
        $reader = $this->reader($request);

        $all = $this->reservationQuery($reader)
            ->orderByRaw("CASE status WHEN 'ready_for_pickup' THEN 0 WHEN 'in_transit' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'queued' THEN 3 ELSE 4 END")
            ->orderBy('queue_sequence')
            ->orderByDesc('created_at')
            ->get();

        $active = $all->filter(
            fn (Reservation $reservation): bool => in_array($reservation->status, Reservation::ACTIVE_STATUSES, true)
        )->values();
        $active->each(function (Reservation $reservation): void {
            if ($reservation->status === 'queued') {
                $reservation->setAttribute('queue_position', $this->reservationInsights->queuePosition($reservation));
            }
        });

        // 8 — the reader sees the same rough availability estimate as the
        // librarian, explicitly labelled as approximate.
        $copyCounts = $this->reservationInsights->circulatingCopyCounts($active->pluck('bibliographic_record_id'));

        return $this->view($request, 'member.reservations', [
            'activeReservations' => $active,
            'pastReservations' => $all->reject(
                fn (Reservation $reservation): bool => in_array($reservation->status, Reservation::ACTIVE_STATUSES, true)
            )->values(),
            'pickupHoldDays' => (int) Setting::valueFor('reservation_hold_days', 1),
            'queueDepths' => $this->reservationInsights->queueDepths($active->pluck('bibliographic_record_id')),
            'forecasts' => $active->mapWithKeys(fn (Reservation $reservation): array => [
                $reservation->getKey() => $this->reservationInsights->estimatedDaysUntilAvailable(
                    $reservation,
                    (int) ($copyCounts[$reservation->bibliographic_record_id] ?? 0),
                ),
            ]),
        ]);
    }

    /**
     * 13.1 / 31.1 — a reader reserves an edition from the catalogue.
     * The `reservation.create` permission is enforced by route middleware and
     * re-checked here; the queue service owns every remaining domain rule.
     */
    public function storeReservation(Request $request): RedirectResponse
    {
        $reader = $this->reader($request);
        abort_unless($reader->can('reservation.create'), 403);

        $validated = $request->validate([
            'bibliographic_record_id' => ['required', 'integer', 'exists:bibliographic_records,id'],
            'pickup_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $record = BibliographicRecord::query()->findOrFail($validated['bibliographic_record_id']);

        // Drafts are not published catalogue records — they must not be
        // reservable from the public book page (9.1).
        abort_if((bool) $record->is_draft, 404);

        try {
            $pickupBranchId = $validated['pickup_branch_id'] ?? ReaderProfile::forUser($reader)->preferred_branch_id;
            $this->reservations->create($reader, $record, pickupBranchId: $pickupBranchId);
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.member.reservations.created_success', [
            'title' => (string) $record->title,
        ]));
    }

    /**
     * 15.3 — the reader withdraws their own request.
     */
    public function cancelReservation(Request $request, Reservation $reservation): RedirectResponse
    {
        $reader = $this->reader($request);
        abort_unless((int) $reservation->user_id === (int) $reader->getKey(), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->reservations->cancel(
                $reservation,
                $reader,
                $validated['reason'] ?? null,
                byStaff: false,
            );
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.cancelled_success'));
    }

    /**
     * 15.4 — closed loans, whether they came back late, and the fines they
     * generated.
     */
    public function history(Request $request): View
    {
        $reader = $this->reader($request);

        $validated = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:returned,lost'], 'overdue' => ['nullable', 'boolean'],
            'fine' => ['nullable', 'boolean'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $loans = Loan::query()
            ->where('user_id', $reader->getKey())
            ->whereIn('status', ['returned', 'lost'])
            ->when($validated['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('issued_at', '<=', $to))
            ->when($validated['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when(! empty($validated['overdue']), fn (Builder $query) => $query->whereColumn('returned_at', '>', 'due_at'))
            ->when(! empty($validated['fine']), fn (Builder $query) => $query->whereHas('fines'))
            ->when($validated['branch_id'] ?? null, fn (Builder $query, $branch) => $query->whereHas('copy', fn (Builder $copy) => $copy->where('branch_id', $branch)))
            ->with(['copy.bibliographicRecord', 'copy.branch', 'fines'])
            ->orderByDesc('returned_at')
            ->orderByDesc('issued_at')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();

        return $this->view($request, 'member.history', [
            'loans' => $loans,
            'pastReservations' => Reservation::query()
                ->where('user_id', $reader->getKey())
                ->whereNotIn('status', Reservation::ACTIVE_STATUSES)
                ->when($validated['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('created_at', '>=', $from))
                ->when($validated['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('created_at', '<=', $to))
                ->when($validated['branch_id'] ?? null, fn (Builder $query, $branch) => $query->where('pickup_branch_id', $branch))
                ->with(['bibliographicRecord', 'pickupBranch'])
                ->latest()
                ->limit(50)
                ->get(),
            'totalReturned' => Loan::query()->where('user_id', $reader->getKey())->where('status', 'returned')->count(),
            'totalLost' => Loan::query()->where('user_id', $reader->getKey())->where('status', 'lost')->count(),
        ]);
    }

    /**
     * 15.5 — read-only debt list. Settlement happens at the desk.
     */
    public function fines(Request $request): View
    {
        $reader = $this->reader($request);

        $fines = Fine::query()
            ->where('user_id', $reader->getKey())
            ->with(['copy.bibliographicRecord', 'loan'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('charged_at')
            ->get();

        return $this->view($request, 'member.fines', [
            'fines' => $fines,
            'pendingTotal' => (float) $fines->where('status', 'pending')->sum('amount'),
            'pendingCount' => $fines->where('status', 'pending')->count(),
            'paidTotal' => (float) $fines->where('status', 'paid')->sum('amount'),
            'waivedTotal' => (float) $fines->where('status', 'waived')->sum('amount'),
        ]);
    }

    /**
     * Saved-for-later list, backed by ShortlistStorageService
     * (literature_drafts + literature_draft_items).
     */
    public function shortlist(Request $request): View
    {
        return $this->view($request, 'member.list', [
            'shortlistItems' => $this->shortlistItems($request),
            'draft' => $this->shortlist->getDraftMeta($request),
        ]);
    }

    public function removeShortlistItem(Request $request, string $identifier): RedirectResponse
    {
        // The storage layer scopes every read and write to the authenticated
        // user's own draft, so there is nothing cross-reader to reach here.
        $removed = $this->shortlist->removeItem($request, $identifier);

        if (! $removed) {
            return back()->withErrors(['shortlist' => __('librarian.member.shortlist.remove_failed')]);
        }

        return back()->with('success', __('librarian.member.shortlist.removed_success'));
    }

    /**
     * The signed-in reader. The route group already guarantees an
     * authenticated account carrying role=reader.
     */
    private function reader(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user;
    }

    /** @return Builder<Reservation> */
    private function reservationQuery(User $reader): Builder
    {
        return Reservation::query()
            ->where('user_id', $reader->getKey())
            ->with(['bibliographicRecord', 'assignedCopy.branch']);
    }

    /**
     * Shortlist rows joined to the catalogue: entries that resolve to a real
     * bibliographic record gain a reserve action and a link to the book page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function shortlistItems(Request $request): Collection
    {
        $items = collect(array_values($this->shortlist->getItems($request)));

        if ($items->isEmpty()) {
            return $items;
        }

        // The catalogue writes shortlist identifiers as `isbn || id` (see the
        // book page), and an ISBN is itself all digits — so every identifier is
        // matched against BOTH columns rather than being split by shape.
        $identifiers = $items->pluck('identifier')->filter()->map(fn ($value): string => (string) $value)->unique()->values()->all();

        if ($identifiers === []) {
            return $items->map(function (array $item): array {
                $item['record'] = null;
                $item['available_copies'] = null;

                return $item;
            });
        }

        $numeric = array_values(array_filter($identifiers, 'ctype_digit'));

        $records = BibliographicRecord::query()
            ->where(function (Builder $builder) use ($identifiers, $numeric): void {
                $builder->whereIn('isbn', $identifiers);
                if ($numeric !== []) {
                    $builder->orWhereIn('id', $numeric);
                }
            })
            ->get();

        $byIsbn = $records->filter(fn (BibliographicRecord $record): bool => (string) $record->isbn !== '')
            ->keyBy(fn (BibliographicRecord $record): string => (string) $record->isbn);
        $byId = $records->keyBy(fn (BibliographicRecord $record): string => (string) $record->getKey());

        return $items->map(function (array $item) use ($byIsbn, $byId): array {
            $identifier = (string) ($item['identifier'] ?? '');
            $record = $byIsbn->get($identifier) ?? $byId->get($identifier);
            $item['record'] = $record;
            $item['available_copies'] = $record?->availableCopiesCount();

            return $item;
        });
    }

    /**
     * A deliberately transparent first recommendation rule: prefer available
     * catalogue records from the reader's most recent UDC/category, otherwise
     * show broadly available records. Already borrowed or reserved editions
     * are excluded and no other reader's activity is exposed.
     *
     * @return Collection<int, BibliographicRecord>
     */
    private function recommendationsFor(User $reader): Collection
    {
        $seenRecordIds = Loan::query()
            ->where('user_id', $reader->getKey())
            ->whereHas('copy')
            ->with('copy:id,bibliographic_record_id')
            ->latest('issued_at')
            ->limit(100)
            ->get()
            ->pluck('copy.bibliographic_record_id')
            ->filter();
        $seenRecordIds = $seenRecordIds->merge(
            Reservation::query()->where('user_id', $reader->getKey())->pluck('bibliographic_record_id')
        )->unique()->values();

        $recentRecord = Loan::query()
            ->where('user_id', $reader->getKey())
            ->with('copy.bibliographicRecord:id,udc_code,category')
            ->latest('issued_at')
            ->first()?->copy?->bibliographicRecord;
        $udcPrefix = mb_substr(trim((string) $recentRecord?->udc_code), 0, 2);
        $category = trim((string) $recentRecord?->category);

        $query = BibliographicRecord::query()
            ->where('is_draft', false)
            ->whereHas('copies', fn (Builder $copies) => $copies->availableForCirculation())
            ->when($seenRecordIds->isNotEmpty(), fn (Builder $records) => $records->whereNotIn('id', $seenRecordIds))
            ->withCount(['copies as available_copies_count' => fn (Builder $copies) => $copies->availableForCirculation()]);

        if ($udcPrefix !== '' || $category !== '') {
            $query->where(function (Builder $records) use ($udcPrefix, $category): void {
                if ($udcPrefix !== '') {
                    $records->where('udc_code', 'like', $udcPrefix.'%');
                }
                if ($category !== '') {
                    $method = $udcPrefix !== '' ? 'orWhere' : 'where';
                    $records->{$method}('category', $category);
                }
            });
        }

        $records = $query->orderByDesc('available_copies_count')->latest()->limit(6)->get();
        $reason = ($udcPrefix !== '' || $category !== '') ? 'similar_subject' : 'available_now';
        $records->each(fn (BibliographicRecord $record) => $record->setAttribute('recommendation_reason', $reason));

        return $records;
    }

    /**
     * Member views also need the legacy session identity the shared layout
     * reads (display name, profile type).
     *
     * @param  array<string, mixed>  $data
     */
    private function view(Request $request, string $view, array $data): View
    {
        return view($view, array_merge([
            'memberReader' => $request->session()->get('library.user'),
        ], $data));
    }
}
