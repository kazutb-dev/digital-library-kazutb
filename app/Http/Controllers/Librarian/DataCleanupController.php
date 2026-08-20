<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\DataQualityQueues;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Data quality workbench (Master.md 11, ДИР 6).
 *
 * The page is organised around the three kinds of work a record can need —
 * completion, judgement, retyping — rather than a flat wall of counters, so a
 * librarian arriving at 9 000 open items has an obvious place to start.
 */
class DataCleanupController extends Controller
{
    public function __construct(private readonly DataQualityQueues $queues) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'issue' => ['nullable', Rule::in($this->queues->keys())],
            'tier' => ['nullable', Rule::in(['high', 'medium', 'low'])],
        ]);
        $issue = $filters['issue'] ?? 'drafts';
        $tier = $filters['tier'] ?? null;

        $counts = $this->queues->counts();
        $definitions = $this->queues->definitions();
        $mode = $definitions[$issue]['mode'];

        $records = null;
        $duplicates = null;
        $copies = null;
        $tierCounts = null;

        if ($issue === 'duplicates') {
            $duplicates = $this->queues->duplicateGroups()->map(function (object $group): array {
                $records = BibliographicRecord::query()
                    ->whereRaw('LOWER(title) = ?', [$group->normalized_title])
                    ->orderBy('id')
                    ->get();

                return ['title' => $records->first()?->title, 'records' => $records];
            });
        } elseif ($issue === 'unplaced_copies') {
            $copies = BookCopy::query()
                ->with(['bibliographicRecord', 'branch'])
                ->where(fn (Builder $b) => $b
                    ->whereNull('branch_id')
                    ->orWhereNull('shelf_location')
                    ->orWhereRaw("TRIM(COALESCE(shelf_location, '')) = ''"))
                ->orderByDesc('updated_at')
                ->paginate(Setting::resultsPerPage())
                ->withQueryString();
        } elseif ($issue === 'language_mismatch') {
            // The tier split is computed in PHP (see kazakhTitleConfidence), so
            // the landing view counts the whole queue once and each tier view
            // then filters the page it renders.
            $tierCounts = $this->languageTierCounts();

            if ($tier !== null) {
                $records = $this->paginateByTier($tier);
            }
        } else {
            $records = $this->queues->query($issue)
                ->withCount('copies')
                ->orderByDesc('updated_at')
                ->paginate(Setting::resultsPerPage())
                ->withQueryString();
        }

        return view('librarian.data-cleanup', [
            'issue' => $issue,
            'tier' => $tier,
            'mode' => $mode,
            'definitions' => $definitions,
            'counts' => $counts,
            'tierCounts' => $tierCounts,
            'records' => $records,
            'duplicates' => $duplicates,
            'copies' => $copies,
            'totalRecords' => BibliographicRecord::query()->count(),
            'openTotal' => $this->queues->openRecordTotal(),
            'groupTotals' => [
                DataQualityQueues::GROUP_COMPLETION => $this->queues->openRecordTotal(DataQualityQueues::GROUP_COMPLETION),
                DataQualityQueues::GROUP_JUDGEMENT => $this->queues->openRecordTotal(DataQualityQueues::GROUP_JUDGEMENT),
                DataQualityQueues::GROUP_RETYPING => $this->queues->openRecordTotal(DataQualityQueues::GROUP_RETYPING),
            ],
            'resolvedToday' => $this->resolvedToday($request),
        ]);
    }

    /**
     * One-record-at-a-time retyping console for glyph-substituted text.
     * Deliberately not the ordinary record form: the whole job is inspecting
     * two strings character by character.
     */
    public function retype(Request $request, DataQualityQueues $queues): View
    {
        $queue = $queues->query('glyph_substitution')->orderBy('id');
        $ids = (clone $queue)->pluck('id')->all();

        $current = $request->integer('record') ?: ($ids[0] ?? null);
        $record = $current !== null ? BibliographicRecord::query()->find($current) : null;

        $position = $record !== null ? array_search($record->getKey(), $ids, true) : false;

        return view('librarian.data-cleanup-retype', [
            'record' => $record,
            'total' => count($ids),
            'position' => $position === false ? null : $position + 1,
            'previousId' => $position !== false && $position > 0 ? $ids[$position - 1] : null,
            'nextId' => $position !== false && isset($ids[$position + 1]) ? $ids[$position + 1] : null,
            'titleSegments' => $record !== null ? $queues->annotate((string) $record->title) : [],
            'authorSegments' => $record !== null ? $queues->annotate((string) $record->primary_author) : [],
            'substitutions' => DataQualityQueues::GLYPH_SUBSTITUTIONS,
        ]);
    }

    /**
     * Save a retyped title/author. Nothing is auto-substituted — the operator
     * types the corrected string and this stores exactly that.
     */
    public function storeRetype(
        Request $request,
        BibliographicRecord $record,
        AuditLogger $audit,
        DataQualityQueues $queues,
    ): RedirectResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'primary_author' => ['nullable', 'string', 'max:255'],
            'next' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($record, $validated, $audit): void {
            $old = $record->only(['title', 'primary_author', 'needs_manual_review', 'review_category']);
            $record->title = $validated['title'];
            $record->primary_author = $validated['primary_author'] ?: null;
            $record->save();

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                oldValues: $old,
                newValues: $record->only(['title', 'primary_author', 'needs_manual_review', 'review_category']),
                scope: 'library',
            );
        });

        // Still damaged after the edit? Keep it in the queue rather than
        // pretending it was handled.
        $stillDamaged = $queues->query('glyph_substitution')->whereKey($record->getKey())->exists();

        $next = $validated['next'] ?? null;

        return redirect()
            ->route('librarian.data-cleanup.retype', $next ? ['record' => $next] : [])
            ->with('success', $stillDamaged
                ? __('librarian.data_cleanup.retype.saved_still_flagged')
                : __('librarian.data_cleanup.retype.saved'));
    }

    /**
     * ДИР 6.3 — a mismatch resolved as "this really is a Russian edition with
     * a Kazakh subtitle". Clears the flag without touching `language`, and
     * records why so the next detection pass does not re-raise it.
     */
    public function resolveParallel(
        BibliographicRecord $record,
        AuditLogger $audit,
    ): RedirectResponse {
        DB::transaction(function () use ($record, $audit): void {
            $old = $record->only(['needs_manual_review', 'review_category', 'review_note']);
            $record->needs_manual_review = false;
            $record->review_category = 'parallel_title';
            $record->review_note = __('librarian.data_cleanup.tiers.parallel_note');
            $record->save();

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                oldValues: $old,
                newValues: $record->only(['needs_manual_review', 'review_category', 'review_note']),
                scope: 'library',
            );
        });

        return back()->with('success', __('librarian.data_cleanup.tiers.parallel_saved'));
    }

    /**
     * Records this librarian actually finished today, used for the progress
     * line. Counted from the audit trail rather than a column, so it reflects
     * real work and resets naturally each day.
     */
    private function resolvedToday(Request $request): int
    {
        $actorId = $request->user()?->getKey();
        if ($actorId === null) {
            return 0;
        }

        return ActivityLog::query()
            ->where('entity_type', 'bibliographic_record')
            ->where('action_type', 'metadata.update')
            ->where('actor_id', $actorId)
            ->whereDate('occurred_at', now()->toDateString())
            ->distinct()
            ->count('entity_id');
    }

    /**
     * @return array{high: int, medium: int, low: int}
     */
    private function languageTierCounts(): array
    {
        $tiers = ['high' => 0, 'medium' => 0, 'low' => 0];

        $this->queues->query('language_mismatch')
            ->select(['id', 'title'])
            ->chunkById(1000, function ($chunk) use (&$tiers): void {
                foreach ($chunk as $record) {
                    $tiers[$record->kazakhTitleConfidence()['tier']]++;
                }
            });

        return $tiers;
    }

    /**
     * Tier membership needs per-record word analysis, so the ids are resolved
     * first and the page is then a plain keyed query — which keeps ordinary
     * pagination working.
     */
    private function paginateByTier(string $tier)
    {
        $ids = [];
        $this->queues->query('language_mismatch')
            ->select(['id', 'title'])
            ->orderBy('id')
            ->chunkById(1000, function ($chunk) use (&$ids, $tier): void {
                foreach ($chunk as $record) {
                    if ($record->kazakhTitleConfidence()['tier'] === $tier) {
                        $ids[] = $record->getKey();
                    }
                }
            });

        return BibliographicRecord::query()
            ->whereIn('id', $ids)
            ->withCount('copies')
            ->orderBy('id')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();
    }
}
