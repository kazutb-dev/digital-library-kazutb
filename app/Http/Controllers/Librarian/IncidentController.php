<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\ReplacementCandidate;
use App\Models\Fund;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog\IncidentCaseService;
use App\Support\StoredUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentCaseService $incidents) {}

    public function index(Request $request): View
    {
        $query = CirculationIncidentCase::query()
            ->with(['reader.readerProfile', 'originalCopy.bibliographicRecord', 'originalCopy.branch', 'assignedTo', 'fine', 'candidates'])
            ->when($request->filled('type'), fn (Builder $q) => $q->where('incident_type', $request->string('type')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('reader'), fn (Builder $q) => $q->whereHas('reader', fn (Builder $u) => $u->where('name', 'like', '%'.$request->string('reader').'%')))
            ->when($request->filled('branch_id'), fn (Builder $q) => $q->whereHas('originalCopy', fn (Builder $c) => $c->where('branch_id', $request->integer('branch_id'))))
            ->when($request->filled('assigned_to'), fn (Builder $q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->boolean('has_fine'), fn (Builder $q) => $q->whereNotNull('fine_id'))
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->open()->where('resolution_due_at', '<', now()))
            ->when($request->boolean('requires_director'), fn (Builder $q) => $q->where('requires_director', true))
            ->when($request->boolean('awaiting_registration'), fn (Builder $q) => $q->where('status', 'awaiting_registration'));

        return view('librarian.incidents.index', [
            'cases' => $query->orderByRaw('CASE WHEN resolution_due_at < CURRENT_TIMESTAMP THEN 0 ELSE 1 END')->orderBy('resolution_due_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'branches' => Branch::query()->active()->ordered()->get(),
            'staff' => User::query()->role(['librarian', 'senior_librarian', 'director'])->orderBy('name')->get(),
        ]);
    }

    public function show(CirculationIncidentCase $incident): View
    {
        $incident->load([
            'loan', 'reader.readerProfile', 'originalCopy.bibliographicRecord', 'originalCopy.branch',
            'originalCopy.fund', 'replacementCopy.bibliographicRecord', 'fine', 'assignedTo',
            'candidates.bibliographicRecord', 'candidates.proposedBy', 'candidates.reviewedBy', 'attachments',
        ]);

        return view('librarian.incidents.show', [
            'incident' => $incident,
            'branches' => Branch::query()->active()->ordered()->get(),
            'funds' => Fund::query()->active()->ordered()->get(),
            'staff' => User::query()->role(['librarian', 'senior_librarian', 'director'])->orderBy('name')->get(),
            'auditEvents' => ActivityLog::query()
                ->where('entity_type', 'circulation_incident_case')
                ->where('entity_id', (string) $incident->getKey())
                ->orderByDesc('occurred_at')->get(),
        ]);
    }

    public function catalogSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'], 'isbn' => ['nullable', 'string', 'max:32'],
            'title' => ['nullable', 'string', 'max:255'], 'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'], 'year' => ['nullable', 'integer', 'min:1000', 'max:'.(now()->year + 1)],
            'language' => ['nullable', Rule::in(BibliographicRecord::LANGUAGES)],
            'udc' => ['nullable', 'string', 'max:64'], 'resource_type' => ['nullable', Rule::in(BibliographicRecord::RESOURCE_TYPES)],
        ]);
        $query = BibliographicRecord::query()->whereNull('deleted_at');
        foreach (['isbn', 'title', 'primary_author' => 'author', 'publisher', 'publication_year' => 'year', 'language', 'udc_code' => 'udc', 'resource_type'] as $column => $field) {
            if (is_int($column)) {
                $column = $field;
            }
            if (! empty($validated[$field])) {
                in_array($column, ['publication_year', 'language', 'resource_type'], true)
                    ? $query->where($column, $validated[$field])
                    : $query->where($column, 'like', '%'.$validated[$field].'%');
            }
        }
        if (! empty($validated['q'])) {
            $query->search($validated['q']);
        }

        return response()->json(['data' => $query->orderBy('title')->limit(20)->get([
            'id', 'isbn', 'title', 'primary_author', 'publisher', 'publication_year', 'language', 'udc_code', 'resource_type', 'is_draft',
        ])]);
    }

    public function propose(Request $request, CirculationIncidentCase $incident): RedirectResponse
    {
        $validated = $request->validate([
            'bibliographic_record_id' => ['nullable', 'integer', 'exists:bibliographic_records,id'],
            'isbn' => ['nullable', 'string', 'max:32'], 'author' => ['nullable', 'string', 'max:255'],
            'title' => ['required_without:bibliographic_record_id', 'nullable', 'string', 'max:255'],
            'work_title' => ['nullable', 'string', 'max:255'], 'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:'.(now()->year + 1)],
            'language' => ['nullable', Rule::in(BibliographicRecord::LANGUAGES)],
            'resource_type' => ['nullable', Rule::in(BibliographicRecord::RESOURCE_TYPES)],
            'udc_code' => ['nullable', 'string', 'max:64'], 'content_description' => ['nullable', 'string', 'max:4000'],
            'copy_condition' => ['required', Rule::in(BookCopy::CONDITIONS)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'], 'source' => ['nullable', 'string', 'max:64'],
        ]);
        $this->incidents->propose($incident, $request->user(), $validated);

        return back()->with('success', __('incidents.messages.candidate_added'));
    }

    public function review(Request $request, ReplacementCandidate $candidate): RedirectResponse
    {
        $rules = ['reviewer_comment' => ['nullable', 'string', 'max:4000']];
        foreach ([...ReplacementCandidate::REQUIRED_CRITERIA, 'value_comparable', 'complete_set'] as $criterion) {
            $rules[$criterion] = ['required', 'boolean'];
        }
        $validated = $request->validate($rules);
        $criteria = collect($validated)->except('reviewer_comment')->map(fn ($v) => (bool) $v)->all();
        $this->incidents->review($candidate, $request->user(), $criteria, $validated['reviewer_comment'] ?? null);

        return back()->with('success', __('incidents.messages.review_saved'));
    }

    public function decide(Request $request, ReplacementCandidate $candidate): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'clarify'])],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
            'exception' => ['nullable', 'boolean'], 'exception_criteria' => ['nullable', 'array'],
            'exception_criteria.*' => [Rule::in(ReplacementCandidate::REQUIRED_CRITERIA)],
            'resolution_type' => ['required_if:decision,approve', Rule::in(CirculationIncidentCase::RESOLUTIONS)],
            'fine_remains' => ['nullable', 'boolean'],
        ]);
        $this->incidents->decide(
            $candidate, $request->user(), $validated['decision'], $validated['reason'],
            $request->boolean('exception'), $validated['exception_criteria'] ?? [],
            $validated['resolution_type'] ?? 'replacement', $request->boolean('fine_remains'),
        );

        return back()->with('success', __('incidents.messages.decision_saved'));
    }

    public function register(Request $request, CirculationIncidentCase $incident): RedirectResponse
    {
        $validated = $request->validate([
            'bibliographic_record_id' => ['nullable', 'integer', 'exists:bibliographic_records,id'],
            'inventory_number' => ['required', 'string', 'max:64', 'unique:book_copies,inventory_number'],
            'barcode' => ['required', 'string', 'max:64', 'unique:book_copies,barcode'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'], 'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'shelf_location' => ['nullable', 'string', 'max:255'], 'storage_sigla' => ['required', 'string', 'max:64'],
            'condition' => ['required', Rule::in(['new', 'good', 'worn'])],
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'price' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $copy = $this->incidents->registerReplacement($incident, $request->user(), $validated);

        return back()->with('success', __('incidents.messages.copy_registered', ['inventory' => $copy->inventory_number]));
    }

    public function createDraft(Request $request, ReplacementCandidate $candidate, AuditLogger $audit): RedirectResponse
    {
        abort_unless($candidate->bibliographic_record_id === null, 409);
        $duplicate = BibliographicRecord::query()
            ->when($candidate->isbn, fn (Builder $q) => $q->where('isbn', $candidate->isbn))
            ->when(! $candidate->isbn, fn (Builder $q) => $q->where('title', $candidate->title)->where('primary_author', $candidate->author))
            ->first();
        if ($duplicate !== null && ! $request->boolean('confirm_duplicate')) {
            return back()->withErrors(['draft' => __('incidents.errors.possible_duplicate', ['id' => $duplicate->getKey()])]);
        }

        $record = DB::transaction(function () use ($candidate, $request, $audit): BibliographicRecord {
            $candidate = ReplacementCandidate::query()->whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();
            $record = BibliographicRecord::query()->create([
                'isbn' => $candidate->isbn, 'title' => $candidate->title, 'primary_author' => $candidate->author,
                'publisher' => $candidate->publisher, 'publication_year' => $candidate->publication_year,
                'language' => $candidate->language, 'resource_type' => $candidate->resource_type ?: 'book',
                'udc_code' => $candidate->udc_code, 'annotation' => $candidate->content_description,
                'is_draft' => true, 'needs_manual_review' => true,
                'review_note' => __('incidents.draft_review_note', ['case' => $candidate->incidentCase->case_number]),
            ]);
            $candidate->update(['bibliographic_record_id' => $record->getKey()]);
            $audit->logRequired('incident.replacement_record_drafted', 'circulation_incident_case', $candidate->incident_case_id, null, [
                'candidate_id' => $candidate->getKey(), 'bibliographic_record_id' => $record->getKey(),
            ], null, 'operational', ['case_id' => $candidate->incident_case_id], $request->user());

            return $record;
        });

        return back()->with('success', __('incidents.messages.draft_created', ['id' => $record->getKey()]));
    }

    public function reopen(Request $request, CirculationIncidentCase $incident): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->incidents->reopen($incident, $request->user(), $validated['reason']);

        return back()->with('success', __('incidents.messages.reopened'));
    }

    public function resolve(Request $request, CirculationIncidentCase $incident): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_type' => ['required', Rule::in(['fine', 'repair', 'write_off', 'monetary_compensation', 'no_charge'])],
            'reason' => ['required', 'string', 'min:5', 'max:4000'],
            'waive_fine' => ['nullable', 'boolean'],
        ]);
        $this->incidents->resolveWithoutReplacement(
            $incident, $request->user(), $validated['resolution_type'],
            $validated['reason'], $request->boolean('waive_fine'),
        );

        return back()->with('success', __('incidents.messages.resolved'));
    }

    public function cancel(Request $request, CirculationIncidentCase $incident): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:4000']]);
        $this->incidents->cancel($incident, $request->user(), $validated['reason']);

        return back()->with('success', __('incidents.messages.cancelled'));
    }

    public function uploadAttachment(Request $request, CirculationIncidentCase $incident, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'attachment' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $file = $validated['attachment'];
        $path = null;

        try {
            $path = StoredUpload::put($file, 'incident-attachments/'.$incident->getKey(), 'local');
            DB::transaction(function () use ($incident, $request, $audit, $file, $path): void {
                $attachment = $incident->attachments()->create([
                    'kind' => 'damage_photo',
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                    'uploaded_by' => $request->user()->getKey(),
                ]);
                $audit->logRequired('incident.attachment_added', 'circulation_incident_case', $incident->getKey(), null, [
                    'attachment_id' => $attachment->getKey(),
                    'kind' => $attachment->kind,
                    'original_name' => $attachment->original_name,
                ], null, 'operational', ['case_id' => $incident->getKey()], $request->user());
            });
        } catch (Throwable $exception) {
            StoredUpload::deleteOrReport($path, 'local');
            throw $exception;
        }

        return back()->with('success', __('incidents.messages.attachment_added'));
    }

    public function assign(Request $request, CirculationIncidentCase $incident, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);
        DB::transaction(function () use ($incident, $request, $validated, $audit): void {
            $incident = CirculationIncidentCase::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();
            $old = ['assigned_to' => $incident->assigned_to];
            $incident->update(['assigned_to' => $validated['assigned_to']]);
            $audit->logRequired('incident.assigned', 'circulation_incident_case', $incident->getKey(), $old, [
                'assigned_to' => $incident->assigned_to,
            ], null, 'operational', ['case_id' => $incident->getKey()], $request->user());
        });

        return back()->with('success', __('incidents.messages.assigned'));
    }
}
