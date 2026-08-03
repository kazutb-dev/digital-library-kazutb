<?php

namespace App\Http\Controllers\Librarian;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\MachineCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Circulation desk (Master.md §14, scenario §31.2): dashboard, issue,
 * return, renewal. The desk works in exactly the order the regulation
 * prescribes — reader first, restrictions second, copy third.
 */
class CirculationController extends Controller
{
    public function __construct(private readonly CirculationService $circulation) {}

    public function dashboard(): View
    {
        $openLoans = Loan::query()->open();

        return view('librarian.circulation.dashboard', [
            'activeCount' => (clone $openLoans)->count(),
            'overdueCount' => Loan::query()->where('status', 'overdue')->whereNull('returned_at')->count(),
            'issuedToday' => Loan::query()->whereDate('issued_at', today())->count(),
            'returnedToday' => Loan::query()->whereDate('returned_at', today())->count(),
            'issuedWeek' => Loan::query()->where('issued_at', '>=', now()->subDays(7))->count(),
            'overdueLoans' => Loan::query()
                ->where('status', 'overdue')
                ->whereNull('returned_at')
                ->with(['reader', 'copy.bibliographicRecord'])
                ->orderBy('due_at')
                ->limit(15)
                ->get(),
            'recentLoans' => Loan::query()
                ->with(['reader', 'copy.bibliographicRecord'])
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function history(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', Rule::in(Loan::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $needle = trim((string) ($validated['q'] ?? ''));
        $loans = Loan::query()->with(['reader.readerProfile', 'copy.bibliographicRecord', 'copy.branch'])
            ->when($needle !== '', fn (Builder $query) => $query->where(function (Builder $scope) use ($needle): void {
                $scope->whereHas('reader', fn (Builder $reader) => $reader->where('name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"))
                    ->orWhereHas('copy', fn (Builder $copy) => $copy->where('inventory_number', 'like', "%{$needle}%")->orWhere('barcode', 'like', "%{$needle}%"));
            }))
            ->when($validated['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn (Builder $query, $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, $to) => $query->whereDate('issued_at', '<=', $to))
            ->when($validated['branch_id'] ?? null, fn (Builder $query, $branch) => $query->whereHas('copy', fn (Builder $copy) => $copy->where('branch_id', $branch)))
            ->latest('issued_at')->paginate(30)->withQueryString();

        return view('librarian.circulation.history', ['loans' => $loans]);
    }

    public function issueForm(Request $request): View
    {
        $reader = null;
        $summary = null;

        if ($readerId = $request->query('reader')) {
            $reader = User::query()->find($readerId);
            if ($reader !== null) {
                $summary = $this->circulation->readerSummary($reader);
            }
        }

        return view('librarian.circulation.issue', [
            'reader' => $reader,
            'summary' => $summary,
        ]);
    }

    /**
     * Step 1 of §14.1 — find the reader by name / ticket / email / barcode.
     */
    public function readerLookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $needle = '%'.mb_strtolower($term).'%';
        // The raw needle is matched alongside the folded one because SQLite
        // lowercases ASCII only — a Cyrillic name would otherwise never match.
        $rawNeedle = '%'.$term.'%';
        $readers = User::query()
            ->where(function (Builder $builder) use ($needle, $rawNeedle): void {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhere('name', 'like', $rawNeedle)
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(ad_login, \'\')) LIKE ?', [$needle])
                    // §9.4 — a scanned card barcode must resolve here too, not
                    // only free-text name/ticket search.
                    ->orWhereHas('readerProfile', fn (Builder $profile) => $profile
                        ->whereRaw('LOWER(ticket_number) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$needle]));
            })
            ->where('is_active', true)
            ->with('readerProfile')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'ticket' => $user->readerProfile?->ticket_number,
                'barcode' => $user->readerProfile?->barcode,
                'category' => $user->readerProfile?->category,
            ]);

        return response()->json(['data' => $readers]);
    }

    /**
     * Copy lookup by barcode / inventory number for the issue and return desks.
     */
    public function copyLookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return response()->json(['data' => null]);
        }

        $copy = BookCopy::query()
            ->whereRaw('LOWER(barcode) = ?', [mb_strtolower($term)])
            ->orWhereRaw('LOWER(inventory_number) = ?', [mb_strtolower($term)])
            ->with(['bibliographicRecord', 'branch', 'activeLoan.reader', 'activeReservation.reader'])
            ->first();

        if ($copy === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => $copy->getKey(),
            'inventory_number' => $copy->inventory_number,
            'barcode' => $copy->barcode,
            'status' => $copy->status,
            'status_label' => __('librarian.copies.statuses.'.$copy->status),
            'access_restriction' => $copy->access_restriction,
            'title' => $copy->bibliographicRecord?->title,
            'author' => $copy->bibliographicRecord?->primary_author,
            'branch' => $copy->branch?->name,
            'active_loan' => $copy->activeLoan ? [
                'reader' => $copy->activeLoan->reader?->name,
                'due_at' => $copy->activeLoan->due_at?->format('d.m.Y'),
                'overdue_days' => $copy->activeLoan->overdueDays(),
            ] : null,
            'reserved_for' => $copy->activeReservation?->reader?->name,
        ]]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reader_id' => ['required', 'integer', 'exists:users,id'],
            'copy_code' => ['required', 'string', 'max:64'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override,1', 'string', 'min:5', 'max:1000'],
            'manual_due_at' => ['nullable', 'date', 'after:today'],
            'due_date_reason' => ['nullable', 'required_with:manual_due_at', 'string', 'min:5', 'max:1000'],
        ]);

        $reader = User::query()->findOrFail($validated['reader_id']);
        $copy = $this->findCopyByCode($validated['copy_code']);

        if ($copy === null) {
            return back()->withInput()->withErrors(['copy_code' => __('librarian.errors.copy_not_found')]);
        }

        try {
            $loan = $this->circulation->issue(
                $reader,
                $copy,
                $request->user(),
                $request->boolean('override'),
                $validated['override_reason'] ?? null,
                $validated['manual_due_at'] ?? null,
                $validated['due_date_reason'] ?? null,
            );
        } catch (CirculationException $exception) {
            return back()->withInput()->withErrors(['copy_code' => $exception->getMessage()]);
        }

        return redirect()
            ->route('librarian.circulation.issue', ['reader' => $reader->getKey()])
            ->with('success', __('librarian.circulation.issued_success', [
                'title' => (string) $copy->bibliographicRecord?->title,
                'due' => (string) $loan->due_at?->format('d.m.Y'),
            ]));
    }

    public function returnForm(Request $request): View
    {
        return view('librarian.circulation.return');
    }

    public function returnCopy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'copy_code' => ['required', 'string', 'max:64'],
            'condition_on_return' => ['required', Rule::in(Loan::RETURN_CONDITIONS)],
            'incident' => ['required', Rule::in(['none', 'damaged', 'lost'])],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'open_case' => ['nullable', 'boolean'],
            'open_replacement_case' => ['nullable', 'boolean'],
            'damage_severity' => ['nullable', 'required_if:incident,damaged', Rule::in(['minor', 'moderate', 'severe', 'irreparable'])],
            'damage_description' => ['nullable', 'required_if:incident,damaged', 'string', 'max:4000'],
            'preliminary_action' => ['nullable', 'required_if:incident,damaged', Rule::in(['return_to_fund', 'repair', 'fine', 'replacement', 'write_off'])],
        ]);

        $copy = $this->findCopyByCode($validated['copy_code']);
        if ($copy === null) {
            return back()->withInput()->withErrors(['copy_code' => __('librarian.errors.copy_not_found')]);
        }

        try {
            $loan = $this->circulation->returnCopy(
                $copy,
                $request->user(),
                $validated['condition_on_return'] ?? null,
                $validated['incident'],
                isset($validated['fine_amount']) ? (float) $validated['fine_amount'] : null,
                $validated['notes'] ?? null,
                [
                    // Lost/damaged returns create a real obligation by default;
                    // the checkbox only controls replacement specifically.
                    'open_case' => $validated['incident'] !== 'none',
                    'open_replacement_case' => $request->boolean('open_replacement_case'),
                    'damage_severity' => $validated['damage_severity'] ?? null,
                    'damage_description' => $validated['damage_description'] ?? null,
                    'preliminary_action' => $validated['preliminary_action'] ?? null,
                    'condition_after' => $validated['condition_on_return'] ?? null,
                ],
            );
        } catch (CirculationException $exception) {
            return back()->withInput()->withErrors(['copy_code' => $exception->getMessage()]);
        }

        $finesTotal = (float) $loan->fines()->where('status', 'pending')->sum('amount');

        return redirect()
            ->route('librarian.circulation.return')
            ->with('success', __('librarian.circulation.returned_success', [
                'title' => (string) $copy->bibliographicRecord?->title,
            ]).($finesTotal > 0 ? ' '.__('librarian.circulation.fine_charged', ['amount' => number_format($finesTotal, 0, ',', ' ')]) : ''));
    }

    public function renew(Request $request, Loan $loan): RedirectResponse
    {
        try {
            $this->circulation->renew($loan, $request->user(), byStaff: true);
        } catch (CirculationException $exception) {
            return back()->withErrors(['renew' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.circulation.renewed_success', [
            'due' => (string) $loan->refresh()->due_at?->format('d.m.Y'),
        ]));
    }

    /**
     * Reader profile management from the desk: block/unblock, category.
     */
    public function updateReader(Request $request, User $reader): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(ReaderProfile::CATEGORIES)],
            'status' => ['required', Rule::in(ReaderProfile::STATUSES)],
            'block_reason' => ['nullable', 'required_unless:status,active', 'string', 'min:5', 'max:1000'],
        ]);

        $profile = ReaderProfile::forUser($reader);
        $old = $profile->only(['category', 'status', 'block_reason']);
        $profile->update([
            'category' => $validated['category'],
            'status' => $validated['status'],
            'block_reason' => $validated['status'] === 'active' ? null : $validated['block_reason'],
        ]);

        app(AuditLogger::class)->logRequired(
            actionType: 'reader.update',
            entityType: 'reader_profile',
            entityId: $profile->getKey(),
            oldValues: $old,
            newValues: $profile->only(['category', 'status', 'block_reason']),
            reason: $validated['block_reason'] ?? null,
            scope: 'library',
        );

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * §9.4 — printable reader card carrying the scannable code, mirroring the
     * copy label at /librarian/copies/{copy}/label.
     */
    public function readerCard(User $reader, MachineCodeService $codes): View
    {
        // Guarantees the card has a code even for a profile predating §9.4.
        $profile = ReaderProfile::forUser($reader);

        return view('librarian.circulation.reader-card', [
            'reader' => $reader,
            'profile' => $profile,
            'code128Svg' => $codes->code128($profile->barcode ?: $profile->ticket_number, 1.5, 48),
            'qrSvg' => $codes->qr($profile->barcode ?: $profile->ticket_number),
        ]);
    }

    private function findCopyByCode(string $code): ?BookCopy
    {
        $normalized = mb_strtolower(trim($code));

        return BookCopy::query()
            ->whereRaw('LOWER(barcode) = ?', [$normalized])
            ->orWhereRaw('LOWER(inventory_number) = ?', [$normalized])
            ->first();
    }
}
