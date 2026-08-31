<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Jobs\RunDataQualityScan;
use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\DataCorrectionBatch;
use App\Models\DataImportBatch;
use App\Models\DataImportMappingProfile;
use App\Models\DataImportStagingRow;
use App\Models\DataQualityIssue;
use App\Models\DataQualityIssueComment;
use App\Models\DataQualityScanRun;
use App\Models\DuplicateGroup;
use App\Models\RecordMergeOperation;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataQuality\BulkCorrectionService;
use App\Services\DataQuality\DataQualityIssuePresenter;
use App\Services\DataQuality\DataQualityRuleRegistry;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\DataQuality\EncodingInspector;
use App\Services\DataQuality\ImportStagingService;
use App\Services\DataQuality\IssueWorkflowService;
use App\Services\DataQuality\RecordMergeService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataQualityController extends Controller
{
    public function index(Request $request, DataQualityRuleRegistry $rules): View
    {
        $filters = $request->validate([
            'entity_type' => ['nullable', 'string', 'max:64'],
            'rule_code' => ['nullable', 'string', 'max:96'],
            'category' => ['nullable', 'string', 'max:64'],
            'severity' => ['nullable', Rule::in(DataQualityIssue::SEVERITIES)],
            'status' => ['nullable', Rule::in(DataQualityIssue::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'scan_run_id' => ['nullable', 'integer', 'exists:data_quality_scan_runs,id'],
            'overdue' => ['nullable', 'boolean'],
            'mine' => ['nullable', 'boolean'],
            'work_type' => ['nullable', Rule::in(['error', 'warning', 'recommendation'])],
            'priority' => ['nullable', Rule::in(['p1', 'p2', 'p3', 'p4'])],
            'q' => ['nullable', 'string', 'max:200'],
        ]);
        $catalogue = $rules->catalogue();
        $recommendationCodes = collect($catalogue)->filter(fn (array $definition) => ($definition['type'] ?? 'warning') === 'recommendation')->keys()->values();
        $errorCodes = collect($catalogue)->filter(fn (array $definition) => ($definition['type'] ?? 'warning') === 'error')->keys()->values();
        $warningCodes = collect($catalogue)->keys()->diff($recommendationCodes)->diff($errorCodes)->values();

        $query = DataQualityIssue::query();
        if (($filters['status'] ?? null) === null) {
            $query->actionable();
        }
        foreach (['entity_type', 'rule_code', 'category', 'severity', 'status', 'assigned_to', 'scan_run_id'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($request->boolean('overdue')) {
            $query->actionable()->where('due_at', '<', now());
        }
        if ($request->boolean('mine')) {
            $query->where('assigned_to', $request->user()->getKey());
        }
        if (($filters['work_type'] ?? null) === 'recommendation') {
            $query->whereIn('rule_code', $recommendationCodes);
        } elseif (($filters['work_type'] ?? null) === 'error') {
            $query->whereIn('rule_code', $errorCodes);
        } elseif (($filters['work_type'] ?? null) === 'warning') {
            $query->whereIn('rule_code', $warningCodes);
        } elseif (($filters['status'] ?? null) === null) {
            // The daily inbox contains work that can block accounting or search.
            // Enrichment recommendations remain available as an explicit tab.
            $query->whereNotIn('rule_code', $recommendationCodes);
        }
        $priorityRules = $this->priorityRules();
        if ($priority = ($filters['priority'] ?? null)) {
            $query->whereIn('rule_code', $priorityRules[$priority]);
        }
        if ($search = trim((string) ($filters['q'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle])
                    ->orWhereExists(function ($record) use ($needle): void {
                        $record->selectRaw('1')->from('bibliographic_records')
                            ->whereRaw('CAST(bibliographic_records.id AS TEXT) = data_quality_issues.entity_id')
                            ->where('data_quality_issues.entity_type', 'bibliographic_record')
                            ->where(function ($value) use ($needle): void {
                                $value->whereRaw('LOWER(title) LIKE ?', [$needle])
                                    ->orWhereRaw('LOWER(COALESCE(primary_author, \'\')) LIKE ?', [$needle])
                                    ->orWhereRaw('LOWER(COALESCE(isbn, \'\')) LIKE ?', [$needle]);
                            });
                    })
                    ->orWhereExists(function ($copy) use ($needle): void {
                        $copy->selectRaw('1')->from('book_copies')
                            ->whereRaw('CAST(book_copies.id AS TEXT) = data_quality_issues.entity_id')
                            ->where('data_quality_issues.entity_type', 'book_copy')
                            ->where(function ($value) use ($needle): void {
                                $value->whereRaw('LOWER(inventory_number) LIKE ?', [$needle])
                                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$needle]);
                            });
                    });
            });
        }

        $actionable = DataQualityIssue::query()->actionable();
        $totalRecords = BibliographicRecord::query()->where('merge_status', 'active')->count();
        $latestFullScan = DataQualityScanRun::query()->where('scope', 'all')->where('status', 'completed')->latest('finished_at')->first();
        $recordsChecked = $latestFullScan
            ? $totalRecords - BibliographicRecord::query()->where('merge_status', 'active')->where('updated_at', '>', $latestFullScan->finished_at)->count()
            : 0;
        $copiesTotal = BookCopy::query()->count();
        $copiesChecked = $latestFullScan
            ? $copiesTotal - BookCopy::query()->where('updated_at', '>', $latestFullScan->finished_at)->count()
            : 0;
        $recordsWithIssues = DataQualityIssue::query()->actionable()
            ->whereNotIn('rule_code', $recommendationCodes)
            ->where('entity_type', 'bibliographic_record')->distinct()->count('entity_id');
        $resolved = DataQualityIssue::query()->where('status', 'resolved')->count();
        $reopened = DataQualityIssue::query()->where('status', 'reopened')->count();

        $objects = (clone $query)
            ->select(['entity_type', 'entity_id'])
            ->selectRaw('MIN(id) AS first_issue_id')
            ->selectRaw('COUNT(*) AS finding_count')
            ->selectRaw("MIN(CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END) AS severity_rank")
            ->selectRaw('MAX(last_detected_at) AS last_detected_at')
            ->groupBy('entity_type', 'entity_id')
            ->orderBy('severity_rank')->orderByDesc('last_detected_at')
            ->paginate(Setting::resultsPerPage())->withQueryString();
        $this->hydrateObjectRows($objects);

        $attentionBase = DataQualityIssue::query()->actionable()->whereNotIn('rule_code', $recommendationCodes);
        $recordObjects = (clone $attentionBase)->where('entity_type', 'bibliographic_record')->distinct()->count('entity_id');
        $copyObjects = (clone $attentionBase)->where('entity_type', 'book_copy')->distinct()->count('entity_id');

        return view('librarian.data-quality.index', [
            'objects' => $objects,
            'stats' => [
                'records_attention' => $recordObjects,
                'copies_attention' => $copyObjects,
                'critical_objects' => $this->countDistinctObjects((clone $attentionBase)->where('severity', 'critical')),
                'high_objects' => $this->countDistinctObjects((clone $attentionBase)->where('severity', 'high')),
                'clean_percent' => $latestFullScan && $recordsChecked > 0
                    ? round((max(0, $recordsChecked - $recordsWithIssues) / $recordsChecked) * 100, 1)
                    : null,
                'raw_findings' => (clone $actionable)->count(),
            ],
            'ruleCatalogue' => $catalogue,
            'coverage' => [
                'records_checked' => $recordsChecked,
                'records_total' => $totalRecords,
                'copies_checked' => $copiesChecked,
                'copies_total' => $copiesTotal,
                'last_scan' => $latestFullScan?->finished_at,
                'active_rules' => count($catalogue),
            ],
            'scanRuns' => DataQualityScanRun::query()->latest()->limit(10)->get(),
            'assignees' => User::permission('data_quality.correct')->orderBy('name')->get(),
            'distributions' => [
                'rules' => DataQualityIssue::query()->actionable()->whereNotIn('rule_code', $recommendationCodes)
                    ->selectRaw("rule_code, count(*) total, count(distinct (entity_type || ':' || entity_id)) objects")
                    ->groupBy('rule_code')->orderByDesc('objects')->limit(10)->get(),
            ],
            'priorityCounts' => collect($priorityRules)->map(fn (array $codes) => $this->countDistinctObjects(
                DataQualityIssue::query()->actionable()->whereIn('rule_code', $codes)
            )),
        ]);
    }

    public function show(DataQualityIssue $issue, EncodingInspector $encoding, DataQualityIssuePresenter $presenter): View
    {
        $issue->load(['assignee', 'comments.author', 'scanRun']);
        $entity = $this->entityFor($issue);
        $relatedIssues = DataQualityIssue::query()->actionable()
            ->where('entity_type', $issue->entity_type)->where('entity_id', $issue->entity_id)
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->get();

        return view('librarian.data-quality.show', [
            'issue' => $issue,
            'entity' => $entity,
            'characters' => is_string($issue->current_value) ? $encoding->characters($issue->current_value) : [],
            'history' => ActivityLog::query()
                ->where(function ($query) use ($issue): void {
                    $query->where('entity_type', 'data_quality_issue')->where('entity_id', (string) $issue->getKey())
                        ->orWhere(fn ($entityQuery) => $entityQuery->where('entity_type', $issue->entity_type)->where('entity_id', $issue->entity_id));
                })
                ->latest('occurred_at')->limit(100)->get(),
            'assignees' => User::permission('data_quality.correct')->orderBy('name')->get(),
            'relatedIssues' => $relatedIssues,
            'presentations' => $relatedIssues->mapWithKeys(fn (DataQualityIssue $finding): array => [
                $finding->getKey() => $presenter->present($finding, $entity),
            ]),
            'nextIssue' => DataQualityIssue::query()->actionable()
                ->whereNotIn('rule_code', collect(app(DataQualityRuleRegistry::class)->catalogue())->filter(fn (array $definition) => ($definition['type'] ?? 'warning') === 'recommendation')->keys())
                ->where(function ($query) use ($issue): void {
                    $query->where('entity_type', '>', $issue->entity_type)
                        ->orWhere(fn ($same) => $same->where('entity_type', $issue->entity_type)->where('entity_id', '>', $issue->entity_id));
                })->orderBy('entity_type')->orderBy('entity_id')->first(),
            'canCorrectInline' => $entity && in_array($issue->field_name, match ($issue->entity_type) {
                'bibliographic_record' => ['title', 'subtitle', 'primary_author', 'publisher', 'publication_year', 'language', 'udc_code', 'author_mark', 'category', 'annotation', 'keywords', 'isbn', 'resource_type', 'notes'],
                'book_copy' => ['inventory_number', 'barcode', 'branch_id', 'fund_id', 'shelf_location', 'price', 'acquisition_date', 'condition', 'defect_description', 'status'],
                default => [],
            }, true),
        ]);
    }

    public function queueScan(Request $request, DataQualityScanner $scanner): RedirectResponse
    {
        $validated = $request->validate(['scope' => ['required', Rule::in(['all', ...array_keys(DataQualityScanner::SCOPES)])]]);
        $run = $scanner->start($validated['scope'], $request->user());
        RunDataQualityScan::dispatch($run->getKey())->afterCommit();

        return back()->with('success', __('data_quality.messages.scan_queued', ['number' => $run->run_number]));
    }

    public function assign(Request $request, DataQualityIssue $issue, IssueWorkflowService $workflow): RedirectResponse
    {
        $validated = $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);
        $workflow->assign($issue, User::query()->findOrFail($validated['assigned_to']), $request->user());

        return back()->with('success', __('data_quality.messages.assigned'));
    }

    public function correct(Request $request, DataQualityIssue $issue, IssueWorkflowService $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'changes' => ['required', 'array', 'min:1'],
            'changes.*' => ['nullable'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $workflow->correct($issue, $validated['changes'], $validated['reason'], $request->user());

        return back()->with('success', __('data_quality.messages.corrected'));
    }

    public function falsePositive(Request $request, DataQualityIssue $issue, IssueWorkflowService $workflow): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $workflow->falsePositive($issue, $validated['reason'], $request->user());

        return back()->with('success', __('data_quality.messages.false_positive'));
    }

    public function ignore(Request $request, DataQualityIssue $issue, IssueWorkflowService $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'ignored_until' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $workflow->ignoreUntil($issue, new \DateTimeImmutable($validated['ignored_until']), $validated['reason'], $request->user());

        return back()->with('success', __('data_quality.messages.ignored'));
    }

    public function reopen(Request $request, DataQualityIssue $issue, IssueWorkflowService $workflow): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $workflow->reopen($issue, $validated['reason'], $request->user());

        return back()->with('success', __('data_quality.messages.reopened'));
    }

    public function comment(Request $request, DataQualityIssue $issue): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        DataQualityIssueComment::query()->create(['issue_id' => $issue->getKey(), 'author_id' => $request->user()->getKey(), 'body' => $validated['body']]);

        return back()->with('success', __('data_quality.messages.comment_added'));
    }

    public function duplicates(): View
    {
        return view('librarian.data-quality.duplicates', [
            'groups' => DuplicateGroup::query()->with(['members.record', 'canonicalRecord'])->latest()->paginate(Setting::resultsPerPage()),
        ]);
    }

    public function proposeMerge(Request $request, DuplicateGroup $group, RecordMergeService $merges): RedirectResponse
    {
        $validated = $request->validate([
            'target_record_id' => ['required', 'integer', 'exists:bibliographic_records,id'],
            'source_record_id' => ['required', 'different:target_record_id', 'integer', 'exists:bibliographic_records,id'],
            'field_selection' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $merges->propose(
            $group,
            BibliographicRecord::query()->findOrFail($validated['target_record_id']),
            BibliographicRecord::query()->findOrFail($validated['source_record_id']),
            $validated['field_selection'],
            $validated['reason'],
            $request->user(),
        );

        return back()->with('success', __('data_quality.messages.merge_proposed'));
    }

    public function approveMerge(Request $request, RecordMergeOperation $operation, RecordMergeService $merges): RedirectResponse
    {
        $merges->approve($operation, $request->user());

        return back()->with('success', __('data_quality.messages.merge_approved'));
    }

    public function executeMerge(Request $request, RecordMergeOperation $operation, RecordMergeService $merges): RedirectResponse
    {
        $merges->execute($operation, $request->user());

        return redirect()->route('librarian.data-quality.duplicates')->with('success', __('data_quality.messages.merge_executed'));
    }

    public function bulkPreview(Request $request, BulkCorrectionService $bulk): RedirectResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', Rule::in(['bibliographic_record', 'book_copy', 'reader_profile'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
            'operation' => ['required', 'string'],
            'config' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $batch = $bulk->preview($validated['entity_type'], $validated['ids'], $validated['operation'], $validated['config'], $validated['reason'], $request->user());

        return redirect()->route('librarian.data-quality.batch', $batch);
    }

    public function batch(DataCorrectionBatch $batch): View
    {
        return view('librarian.data-quality.batch', ['batch' => $batch->load('items')]);
    }

    public function bulkApprove(Request $request, DataCorrectionBatch $batch, BulkCorrectionService $bulk): RedirectResponse
    {
        $bulk->approve($batch, $request->user());

        return back()->with('success', __('data_quality.messages.batch_approved'));
    }

    public function bulkExecute(Request $request, DataCorrectionBatch $batch, BulkCorrectionService $bulk): RedirectResponse
    {
        $bulk->execute($batch, $request->user());

        return back()->with('success', __('data_quality.messages.batch_executed'));
    }

    public function bulkRollback(Request $request, DataCorrectionBatch $batch, BulkCorrectionService $bulk): RedirectResponse
    {
        $bulk->rollback($batch, $request->user());

        return back()->with('success', __('data_quality.messages.batch_rolled_back'));
    }

    public function imports(): View
    {
        return view('librarian.data-quality.imports', [
            'batches' => DataImportBatch::query()->latest()->paginate(Setting::resultsPerPage()),
            'profiles' => DataImportMappingProfile::query()->where('is_active', true)->orderBy('name')->get(),
            'formats' => ImportStagingService::SUPPORTED_FORMATS,
        ]);
    }

    public function uploadImport(Request $request, ImportStagingService $imports): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'source_format' => ['required', Rule::in(ImportStagingService::SUPPORTED_FORMATS)],
            'mapping_profile_id' => ['nullable', 'integer', 'exists:data_import_mapping_profiles,id'],
            'encoding' => ['nullable', 'string', 'max:32'],
        ]);
        $profile = isset($validated['mapping_profile_id']) ? DataImportMappingProfile::query()->findOrFail($validated['mapping_profile_id']) : null;
        $batch = $imports->upload($request->file('file'), $validated['source_format'], $profile, $request->user(), $validated['encoding'] ?? null);

        return redirect()->route('librarian.data-quality.imports.show', $batch);
    }

    public function showImport(DataImportBatch $batch): View
    {
        return view('librarian.data-quality.import-show', ['batch' => $batch->load('rows')]);
    }

    public function decideImportRow(Request $request, DataImportStagingRow $row, ImportStagingService $imports): RedirectResponse
    {
        $validated = $request->validate(['action' => ['required', Rule::in(['create', 'update', 'skip', 'review'])]]);
        $imports->decideRow($row, $validated['action']);

        return back();
    }

    public function approveImport(Request $request, DataImportBatch $batch, ImportStagingService $imports): RedirectResponse
    {
        $imports->approve($batch, $request->user());

        return back()->with('success', __('data_quality.messages.import_approved'));
    }

    public function executeImport(Request $request, DataImportBatch $batch, ImportStagingService $imports): RedirectResponse
    {
        $imports->import($batch, $request->user());

        return back()->with('success', __('data_quality.messages.import_completed'));
    }

    public function export(Request $request, string $type = 'issues'): StreamedResponse
    {
        abort_unless(in_array($type, ['issues', 'statistics', 'scan-runs', 'correction-batches', 'duplicate-groups', 'import-reconciliation'], true), 404);

        return response()->streamDownload(function () use ($request, $type): void {
            $stream = fopen('php://output', 'w');
            if ($type === 'issues') {
                $query = DataQualityIssue::query();
                if ($request->filled('status')) {
                    $query->where('status', $request->string('status'));
                }
                Csv::writeRow($stream, ['issue_number', 'entity_type', 'entity_id', 'rule_code', 'severity', 'status', 'field', 'current_value', 'assigned_to', 'due_at']);
                $query->orderBy('id')->chunkById(1000, function ($issues) use ($stream): void {
                    foreach ($issues as $issue) {
                        Csv::writeRow($stream, [$issue->issue_number, $issue->entity_type, $issue->entity_id, $issue->rule_code, $issue->severity, $issue->status, $issue->field_name, $issue->current_value, $issue->assigned_to, $issue->due_at?->toIso8601String()]);
                    }
                });
            } elseif ($type === 'statistics') {
                Csv::writeRow($stream, ['dimension', 'value', 'count']);
                foreach (DataQualityIssue::query()->selectRaw('status as value, count(*) total')->groupBy('status')->get() as $row) {
                    Csv::writeRow($stream, ['status', $row->value, $row->total]);
                }
                foreach (DataQualityIssue::query()->selectRaw('rule_code as value, count(*) total')->groupBy('rule_code')->get() as $row) {
                    Csv::writeRow($stream, ['rule', $row->value, $row->total]);
                }
            } elseif ($type === 'scan-runs') {
                Csv::writeRow($stream, ['run_number', 'scope', 'status', 'started_at', 'finished_at', 'records_scanned', 'issues_found', 'issues_created', 'issues_reopened', 'duration_ms']);
                DataQualityScanRun::query()->orderBy('id')->each(fn ($run) => Csv::writeRow($stream, [$run->run_number, $run->scope, $run->status, $run->started_at, $run->finished_at, $run->records_scanned, $run->issues_found, $run->issues_created, $run->issues_reopened, $run->duration_ms]));
            } elseif ($type === 'correction-batches') {
                Csv::writeRow($stream, ['batch_number', 'entity_type', 'operation', 'status', 'selected', 'succeeded', 'failed', 'executed_at']);
                DataCorrectionBatch::query()->orderBy('id')->each(fn ($batch) => Csv::writeRow($stream, [$batch->batch_number, $batch->entity_type, $batch->operation_type, $batch->status, $batch->records_selected, $batch->records_succeeded, $batch->records_failed, $batch->executed_at]));
            } elseif ($type === 'duplicate-groups') {
                Csv::writeRow($stream, ['group_number', 'status', 'match_level', 'score', 'canonical_record_id', 'members']);
                DuplicateGroup::query()->withCount('members')->orderBy('id')->each(fn ($group) => Csv::writeRow($stream, [$group->group_number, $group->status, $group->match_level, $group->score, $group->canonical_record_id, $group->members_count]));
            } else {
                Csv::writeRow($stream, ['batch_number', 'filename', 'status', 'rows_total', 'rows_imported', 'rows_skipped', 'reconciliation']);
                DataImportBatch::query()->orderBy('id')->each(fn ($batch) => Csv::writeRow($stream, [$batch->batch_number, $batch->source_filename, $batch->status, $batch->rows_total, $batch->rows_imported, $batch->rows_skipped, json_encode($batch->reconciliation, JSON_UNESCAPED_UNICODE)]));
            }
            fclose($stream);
        }, 'data-quality-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function entityFor(DataQualityIssue $issue): mixed
    {
        $class = match ($issue->entity_type) {
            'bibliographic_record' => BibliographicRecord::class,
            'book_copy' => BookCopy::class,
            'reader_profile' => ReaderProfile::class,
            'loan' => Loan::class,
            'fine' => Fine::class,
            'reservation' => Reservation::class,
            default => null,
        };

        if ($class === null) {
            return null;
        }

        return $class === BookCopy::class
            ? BookCopy::query()->with(['branch', 'fund.branch', 'bibliographicRecord'])->find($issue->entity_id)
            : $class::query()->find($issue->entity_id);
    }

    private function countDistinctObjects(Builder $query): int
    {
        return (int) DB::query()
            ->fromSub($query->select(['entity_type', 'entity_id'])->distinct(), 'quality_objects')
            ->count();
    }

    /** @return array<string,list<string>> */
    private function priorityRules(): array
    {
        return [
            'p1' => ['copy.inventory.missing', 'copy.status.invalid', 'copy.condition.invalid', 'copy.record.missing', 'copy.location.inactive', 'copy.location.fund_branch_conflict', 'copy.loan_state.conflict', 'copy.reservation_state.conflict', 'bib.physical.no_copies'],
            'p2' => ['bib.title.missing', 'bib.title.suspicious', 'bib.title.truncated', 'bib.author.missing', 'bib.author.suspicious', 'bib.year.invalid', 'bib.isbn.invalid', 'bib.language.invalid', 'bib.language.possible_mismatch', 'bib.duplicate.exact', 'bib.duplicate.probable', 'bib.duplicate.possible', 'encoding.replacement_character', 'encoding.null_byte', 'encoding.control_character', 'encoding.mojibake', 'encoding.html_entity', 'encoding.question_replacement', 'encoding.mixed_alphabet', 'encoding.legacy_kazakh_glyph'],
            'p3' => ['bib.udc.missing', 'bib.udc.invalid_format'],
            'p4' => ['bib.title.spacing', 'bib.author.spacing', 'bib.year.missing', 'bib.isbn.not_normalized', 'bib.author_mark.missing', 'bib.language.legacy_code', 'copy.barcode.missing', 'copy.location.missing', 'encoding.non_breaking_space'],
        ];
    }

    private function hydrateObjectRows($objects): void
    {
        $rows = $objects->getCollection();
        if ($rows->isEmpty()) {
            return;
        }
        $recordIds = $rows->where('entity_type', 'bibliographic_record')->pluck('entity_id')->map(fn ($id) => (int) $id);
        $copyIds = $rows->where('entity_type', 'book_copy')->pluck('entity_id')->map(fn ($id) => (int) $id);
        $records = BibliographicRecord::query()->withCount('copies')->whereIn('id', $recordIds)->get()->keyBy(fn ($record) => (string) $record->getKey());
        $copies = BookCopy::query()->with(['bibliographicRecord', 'branch', 'fund'])->whereIn('id', $copyIds)->get()->keyBy(fn ($copy) => (string) $copy->getKey());
        $entryIssues = DataQualityIssue::query()->whereIn('id', $rows->pluck('first_issue_id'))->get()->keyBy('id');
        $pairs = $rows->map(fn ($row) => [$row->entity_type, (string) $row->entity_id]);
        $categories = DataQualityIssue::query()->actionable()->where(function ($query) use ($pairs): void {
            foreach ($pairs as [$type, $id]) {
                $query->orWhere(fn ($pair) => $pair->where('entity_type', $type)->where('entity_id', $id));
            }
        })->get(['entity_type', 'entity_id', 'category'])->groupBy(fn ($finding) => $finding->entity_type.':'.$finding->entity_id);

        $objects->setCollection($rows->map(function ($row) use ($records, $copies, $categories, $entryIssues) {
            $row->setAttribute('entity', $row->entity_type === 'bibliographic_record' ? $records->get((string) $row->entity_id) : $copies->get((string) $row->entity_id));
            $row->setAttribute('entry_issue', $entryIssues->get((int) $row->first_issue_id));
            $row->setAttribute('categories', $categories->get($row->entity_type.':'.$row->entity_id, collect())->pluck('category')->unique()->values());
            $row->setAttribute('max_severity', [1 => 'critical', 2 => 'high', 3 => 'medium', 4 => 'low', 5 => 'info'][(int) $row->severity_rank] ?? 'info');

            return $row;
        }));
    }
}
