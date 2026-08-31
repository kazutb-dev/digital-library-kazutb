<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuEntryItem;
use App\Models\Ksu\KsuSequence;
use App\Services\AuditLogger;
use App\Services\Operations\KsuLegacyReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KsuRegisterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:1900', 'max:9999'],
            'book' => ['nullable', 'integer', 'exists:ksu_books,id'],
            'status' => ['nullable', Rule::in(['legacy', 'draft', 'posted'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));

        $entries = KsuEntry::query()
            ->with(['book:id,code,name', 'branch:id,name', 'fund:id,name', 'creator:id,name'])
            ->when($validated['year'] ?? null, fn ($builder, $year) => $builder->where('year', $year))
            ->when($validated['book'] ?? null, fn ($builder, $book) => $builder->where('ksu_book_id', $book))
            ->when($validated['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($scope) use ($query): void {
                    $scope->where('entry_number', 'like', '%'.$query.'%')
                        ->orWhere('supplier_name', 'like', '%'.$query.'%')
                        ->orWhere('act_number', 'like', '%'.$query.'%');
                });
            })
            ->orderByDesc('year')
            ->orderByDesc('number')
            ->paginate(25)
            ->withQueryString();

        return view('librarian.ksu.index', [
            'entries' => $entries,
            'books' => KsuBook::query()->withCount(['entries', 'conflicts'])->orderBy('sort_order')->get(),
            'sequences' => KsuSequence::query()->with('book:id,code')->orderByDesc('year')->orderByDesc('last_number')->limit(25)->get(),
            'openConflicts' => KsuConflict::query()->where('status', 'open')->count(),
            'canManage' => $request->user()->can('ksu.manage'),
        ]);
    }

    public function show(Request $request, KsuEntry $entry): View
    {
        $this->authorizeView($request);

        return view('librarian.ksu.show', [
            'entry' => $entry->load(['book', 'branch:id,name', 'fund:id,name', 'creator:id,name', 'acquisitionBatch:id,batch_number,ksu_entry_id']),
            'items' => KsuEntryItem::query()
                ->where('ksu_entry_id', $entry->getKey())
                ->with(['copy:id,inventory_number,barcode,status,inventory_status,circulation_status,ksu_number,acquisition_source,registration_date', 'bibliographicRecord:id,title,primary_author'])
                ->orderBy('id')
                ->paginate(50),
        ]);
    }

    public function conflicts(Request $request, KsuLegacyReviewService $legacyReview): View
    {
        $this->authorizeView($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'resolved', 'ignored'])],
            'kind' => ['nullable', 'string', 'max:64'],
            'book' => ['nullable', 'integer', 'exists:ksu_books,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', Rule::in(['grouped', 'individual'])],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));
        $grouped = ($validated['view'] ?? 'grouped') === 'grouped';
        $filters = [
            'status' => $validated['status'] ?? 'open',
            'kind' => array_key_exists('kind', $validated)
                ? $validated['kind']
                : ($grouped ? 'unresolved_link' : null),
            'book' => $validated['book'] ?? null,
            'q' => $query,
        ];

        $conflicts = null;
        $groups = null;
        if ($grouped) {
            $groups = $legacyReview->groupedQueue($filters);
        } else {
            $conflicts = KsuConflict::query()
                ->with(['book:id,code', 'copy:id,inventory_number,barcode', 'sourceCopy:id,inventory_number,barcode,legacy_inv_id', 'resolver:id,name'])
                ->when($filters['status'], fn ($builder, $status) => $builder->where('status', $status))
                ->when($filters['kind'], fn ($builder, $kind) => $builder->where('kind', $kind))
                ->when($filters['book'], fn ($builder, $book) => $builder->where('ksu_book_id', $book))
                ->when($query !== '', function ($builder) use ($query): void {
                    $builder->where(function ($scope) use ($query): void {
                        $scope->where('ksu_number_raw', 'like', '%'.$query.'%')
                            ->orWhere('reason', 'like', '%'.$query.'%');
                        if (ctype_digit($query)) {
                            $scope->orWhere('source_inv_id', (int) $query)
                                ->orWhere('source_doc_id', (int) $query);
                        }
                    });
                })
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        }

        $partOne = KsuBook::query()->where('code', 'KSU-1')->first();

        return view('librarian.ksu.conflicts', [
            'conflicts' => $conflicts,
            'groups' => $groups,
            'grouped' => $grouped,
            'books' => KsuBook::query()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'kinds' => KsuConflict::query()->distinct()->orderBy('kind')->pluck('kind'),
            'existingEntries' => $partOne === null
                ? collect()
                : KsuEntry::query()
                    ->where('ksu_book_id', $partOne->getKey())
                    ->whereIn('status', ['legacy', 'posted'])
                    ->orderByDesc('year')
                    ->orderByDesc('number')
                    ->get(['id', 'entry_number', 'year', 'number']),
            'canManage' => $request->user()->can('ksu.resolve'),
        ]);
    }

    public function resolveGroup(
        Request $request,
        KsuLegacyReviewService $legacyReview,
    ): RedirectResponse {
        abort_unless($request->user()->can('ksu.resolve'), 403);
        $data = $request->validate([
            'ksu_number_raw' => ['present', 'nullable', 'string', 'max:64'],
            'action' => ['required', Rule::in([
                KsuLegacyReviewService::ACTION_LINK_EXISTING,
                KsuLegacyReviewService::ACTION_CREATE_HISTORICAL,
                KsuLegacyReviewService::ACTION_IGNORE,
                KsuLegacyReviewService::ACTION_LEAVE_UNRESOLVED,
            ])],
            'ksu_entry_id' => [
                'nullable',
                'integer',
                'exists:ksu_entries,id',
                Rule::requiredIf(fn (): bool => $request->input('action') === KsuLegacyReviewService::ACTION_LINK_EXISTING),
            ],
            'resolution_note' => [
                'nullable',
                'string',
                'max:4000',
                Rule::requiredIf(fn (): bool => $request->input('action') !== KsuLegacyReviewService::ACTION_LEAVE_UNRESOLVED),
            ],
        ]);

        $result = $legacyReview->resolveGroup(
            rawNumber: $data['ksu_number_raw'],
            action: $data['action'],
            actor: $request->user(),
            entryId: isset($data['ksu_entry_id']) ? (int) $data['ksu_entry_id'] : null,
            reason: $data['resolution_note'] ?? null,
        );

        $message = match ($result['action']) {
            KsuLegacyReviewService::ACTION_CREATE_HISTORICAL => 'ksu_historical_group_created',
            KsuLegacyReviewService::ACTION_IGNORE => 'ksu_group_ignored',
            KsuLegacyReviewService::ACTION_LEAVE_UNRESOLVED => 'ksu_group_left_unresolved',
            default => 'ksu_group_linked',
        };

        return back()->with('success', __('operations.messages.'.$message, [
            'conflicts' => $result['conflicts'],
            'copies' => $result['copies'],
        ]));
    }

    public function resolve(
        Request $request,
        KsuConflict $conflict,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($request->user()->can('ksu.resolve'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['resolved', 'ignored'])],
            'book_copy_id' => ['nullable', 'integer', 'exists:book_copies,id'],
            'resolution_note' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        DB::transaction(function () use ($request, $conflict, $data, $audit): void {
            $conflict = KsuConflict::query()->whereKey($conflict->getKey())->lockForUpdate()->firstOrFail();
            if ($conflict->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => __('operations.messages.conflict_already_closed'),
                ]);
            }
            $copyId = $data['book_copy_id'] ?? $conflict->book_copy_id;
            if ($data['status'] === 'resolved' && $conflict->kind === 'unresolved_link' && $copyId === null) {
                throw ValidationException::withMessages([
                    'book_copy_id' => __('operations.messages.conflict_copy_required'),
                ]);
            }
            $old = $conflict->only(['status', 'book_copy_id', 'resolution_note']);
            $conflict->update([
                'status' => $data['status'],
                'book_copy_id' => $copyId,
                'resolution_note' => $data['resolution_note'],
                'resolved_by' => $request->user()->getKey(),
                'resolved_at' => now('UTC'),
            ]);
            KsuAuditEvent::query()->create([
                'event_type' => 'conflict.'.$data['status'],
                'ksu_book_id' => $conflict->ksu_book_id,
                'book_copy_id' => $conflict->book_copy_id,
                'actor_id' => $request->user()->getKey(),
                'actor_name' => $request->user()->name,
                'old_values' => $old,
                'new_values' => $conflict->fresh()->only(['status', 'book_copy_id', 'resolution_note']),
                'reason' => $data['resolution_note'],
                'occurred_at' => now('UTC'),
            ]);
            $audit->logRequired(
                'ksu.conflict.'.$data['status'],
                'ksu_conflict',
                (string) $conflict->getKey(),
                oldValues: $old,
                newValues: $conflict->fresh()->only(['status', 'book_copy_id', 'resolution_note']),
                reason: $data['resolution_note'],
                scope: 'operational',
                actor: $request->user(),
            );
        });

        return back()->with('success', __('operations.messages.conflict_resolved'));
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->can('ksu.view'), 403);
    }
}
