<?php

namespace App\Services\Library;

use App\Models\AcquisitionOrder;
use App\Models\AcquisitionOrderItem;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\CopyHistory;
use App\Models\Catalog\Loan;
use App\Models\Catalog\Reservation;
use App\Models\DocumentDeliveryRequest;
use App\Models\ExternalResource;
use App\Models\LibraryTask;
use App\Models\News;
use App\Models\PeriodicalIssue;
use App\Models\PeriodicalSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LibrarianWorkspaceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string,int> */
    public function queueCounts(User $user): array
    {
        $now = now('UTC');

        return [
            'my_tasks' => $user->can('tasks.view') ? LibraryTask::query()->where('assigned_to', $user->getKey())->whereIn('status', ['open', 'in_progress', 'blocked'])->count() : 0,
            'overdue_tasks' => $user->can('tasks.view') ? LibraryTask::query()->where('assigned_to', $user->getKey())->whereIn('status', ['open', 'in_progress', 'blocked'])->where('due_at', '<', $now)->count() : 0,
            'orders_open' => $user->can('acquisitions.view') ? AcquisitionOrder::query()->whereNotIn('status', ['received', 'cancelled'])->count() : 0,
            'edd_open' => $user->can('edd.view') ? DocumentDeliveryRequest::query()->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count() : 0,
            'periodicals_missing' => $user->can('periodicals.view') ? PeriodicalIssue::query()->where('status', 'missing')->count() : 0,
        ];
    }

    public function tasks(User $user): LengthAwarePaginator
    {
        return LibraryTask::query()->with(['assignee:id,name,email', 'creator:id,name,email'])
            ->when(! $user->can('tasks.assign'), fn (Builder $query) => $query->where('assigned_to', $user->getKey()))
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('due_at ASC NULLS LAST')->paginate(25)->withQueryString();
    }

    /** @param array<string,mixed> $data */
    public function createTask(User $actor, array $data): LibraryTask
    {
        $assignedTo = $actor->can('tasks.assign') ? (int) ($data['assigned_to'] ?? $actor->getKey()) : (int) $actor->getKey();
        $task = LibraryTask::query()->create([...$data, 'assigned_to' => $assignedTo, 'created_by' => $actor->getKey()]);
        $this->audit->logRequired('create', 'library_task', (string) $task->getKey(), newValues: ['title' => $task->title, 'assigned_to' => $assignedTo, 'priority' => $task->priority], scope: 'operational');

        return $task;
    }

    /** @param array<string,mixed> $data */
    public function updateTask(User $actor, LibraryTask $task, array $data): LibraryTask
    {
        abort_unless($actor->can('tasks.assign') || ((int) $task->assigned_to === (int) $actor->getKey() && $actor->can('tasks.manage_own')), 403);
        if (! $actor->can('tasks.assign')) {
            unset($data['assigned_to']);
        }
        $data['completed_at'] = ($data['status'] ?? $task->status) === 'completed' ? now('UTC') : null;
        $task->update($data);
        $this->audit->logRequired('update', 'library_task', (string) $task->getKey(), newValues: ['status' => $task->status, 'assigned_to' => $task->assigned_to], scope: 'operational');

        return $task->refresh();
    }

    public function orders(): LengthAwarePaginator
    {
        return AcquisitionOrder::query()
            ->with(['creator:id,name,email', 'items.record:id,title,primary_author,publication_year'])
            ->withCount('items')
            ->withSum('items as quantity_ordered_total', 'quantity_ordered')
            ->withSum('items as quantity_received_total', 'quantity_received')
            ->orderByDesc('created_at')->paginate(25)->withQueryString();
    }

    /** @param array<string,mixed> $data */
    public function createOrder(User $actor, array $data): AcquisitionOrder
    {
        return DB::transaction(function () use ($actor, $data): AcquisitionOrder {
            $item = $data['item'];
            unset($data['item']);
            $order = AcquisitionOrder::query()->create([...$data, 'created_by' => $actor->getKey()]);
            $order->items()->create($item);
            $order->update(['total_amount' => round(((int) $item['quantity_ordered']) * ((float) $item['unit_price']), 2)]);
            $this->audit->logRequired('create', 'acquisition_order', (string) $order->getKey(), newValues: [
                'order_number' => $order->order_number,
                'supplier' => $order->supplier,
                'status' => $order->status,
                'currency' => $order->currency,
                'total_amount' => $order->total_amount,
                'item' => $order->items()->firstOrFail()->only([
                    'bibliographic_record_id', 'title_snapshot', 'quantity_ordered',
                    'quantity_received', 'unit_price',
                ]),
            ], scope: 'operational', actor: $actor);

            return $order->refresh();
        });
    }

    /**
     * Register one physical receipt against the existing order line. The
     * quantity is an increment, not an absolute rewrite, so partial receipts
     * remain traceable and can never silently decrease an earlier intake.
     *
     * @param  array{received_quantity:int,bibliographic_record_id?:int|null}  $data
     */
    public function receiveOrderItem(
        User $actor,
        AcquisitionOrder $order,
        AcquisitionOrderItem $item,
        array $data,
    ): AcquisitionOrderItem {
        return DB::transaction(function () use ($actor, $order, $item, $data): AcquisitionOrderItem {
            $order = AcquisitionOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $item = AcquisitionOrderItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $item->acquisition_order_id !== (int) $order->getKey()) {
                abort(404);
            }
            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages(['received_quantity' => __('workspace.messages.cancelled_order_cannot_receive')]);
            }

            $increment = (int) $data['received_quantity'];
            $newQuantity = (int) $item->quantity_received + $increment;
            if ($newQuantity > (int) $item->quantity_ordered) {
                throw ValidationException::withMessages(['received_quantity' => __('workspace.messages.receipt_exceeds_order')]);
            }

            $recordId = array_key_exists('bibliographic_record_id', $data)
                ? $data['bibliographic_record_id']
                : $item->bibliographic_record_id;
            if ($increment > 0 && ($recordId === null || $recordId === '')) {
                throw ValidationException::withMessages([
                    'bibliographic_record_id' => __('workspace.messages.receipt_record_required'),
                ]);
            }
            if ((int) $item->quantity_received > 0
                && $item->bibliographic_record_id !== null
                && (string) $recordId !== (string) $item->bibliographic_record_id) {
                throw ValidationException::withMessages([
                    'bibliographic_record_id' => __('workspace.messages.receipt_record_locked'),
                ]);
            }
            if ($increment === 0 && (string) $recordId === (string) $item->bibliographic_record_id) {
                throw ValidationException::withMessages(['received_quantity' => __('workspace.messages.receipt_no_change')]);
            }

            $oldItem = $item->only(['bibliographic_record_id', 'quantity_received']);
            $oldOrder = $order->only(['status', 'received_at']);
            $item->update([
                'bibliographic_record_id' => $recordId,
                'quantity_received' => $newQuantity,
            ]);

            $items = $order->items()->get(['quantity_ordered', 'quantity_received']);
            $allReceived = $items->isNotEmpty()
                && $items->every(fn (AcquisitionOrderItem $line): bool => (int) $line->quantity_received >= (int) $line->quantity_ordered);
            $anyReceived = $items->contains(fn (AcquisitionOrderItem $line): bool => (int) $line->quantity_received > 0);
            $newStatus = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $order->status);
            $order->update([
                'status' => $newStatus,
                'received_at' => $allReceived ? now('UTC')->toDateString() : null,
            ]);

            $this->audit->logRequired(
                'acquisition.received',
                'acquisition_order_item',
                (string) $item->getKey(),
                oldValues: $oldItem + ['order' => $oldOrder],
                newValues: $item->fresh()->only(['bibliographic_record_id', 'quantity_received']) + [
                    'received_quantity' => $increment,
                    'order' => $order->fresh()->only(['status', 'received_at']),
                ],
                scope: 'operational',
                metadata: ['acquisition_order_id' => $order->getKey()],
                actor: $actor,
            );

            return $item->refresh();
        });
    }

    public function deliveries(User $user): LengthAwarePaginator
    {
        return DocumentDeliveryRequest::query()->with(['user:id,name,email', 'responsible:id,name,email'])
            ->when(! $user->can('tasks.assign'), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->whereNull('responsible_id')->orWhere('responsible_id', $user->getKey())))
            ->orderByRaw('due_at ASC NULLS LAST')->paginate(25)->withQueryString();
    }

    /** @param array<string,mixed> $data */
    public function createDelivery(User $actor, array $data): DocumentDeliveryRequest
    {
        $request = DocumentDeliveryRequest::query()->create([...$data, 'responsible_id' => $data['responsible_id'] ?? $actor->getKey()]);
        $this->audit->logRequired('create', 'document_delivery_request', (string) $request->getKey(), newValues: ['request_number' => $request->request_number, 'status' => $request->status], scope: 'operational');

        return $request;
    }

    public function periodicals(): LengthAwarePaginator
    {
        return PeriodicalSubscription::query()->with(['record:id,title', 'branch:id,name', 'fund:id,name', 'issues' => fn ($query) => $query->orderBy('expected_at')])
            ->orderByDesc('year')->orderBy('title_snapshot')->paginate(20)->withQueryString();
    }

    /** @param array<string,mixed> $data */
    public function createPeriodical(User $actor, array $data): PeriodicalSubscription
    {
        $subscription = PeriodicalSubscription::query()->create($data);
        $this->audit->logRequired('create', 'periodical_subscription', (string) $subscription->getKey(), newValues: ['title' => $subscription->title_snapshot, 'year' => $subscription->year], scope: 'operational');

        return $subscription;
    }

    /** @param array<string,mixed> $data */
    public function receiveIssue(User $actor, PeriodicalSubscription $subscription, array $data): PeriodicalIssue
    {
        $issue = $subscription->issues()->updateOrCreate(['issue_number' => $data['issue_number']], $data);
        $this->audit->logRequired('receive', 'periodical_issue', (string) $issue->getKey(), newValues: ['subscription_id' => $subscription->getKey(), 'issue_number' => $issue->issue_number], scope: 'operational');

        return $issue;
    }

    public function movements(): LengthAwarePaginator
    {
        return CopyHistory::query()->with(['copy.bibliographicRecord:id,title', 'actor:id,name,email'])
            ->orderByDesc('occurred_at')->paginate(50)->withQueryString();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function calendar(User $user, Carbon $from, Carbon $to): Collection
    {
        $events = collect();
        if ($user->can('news.view_internal')) {
            $events = $events->concat(News::query()->whereBetween(DB::raw('COALESCE(starts_at, scheduled_publish_at, publish_at)'), [$from, $to])->get()->map(fn (News $news): array => ['at' => $news->starts_at ?? $news->scheduled_publish_at ?? $news->publish_at, 'type' => 'news', 'title' => $news->localized('title'), 'status' => $news->status]));
        }
        if ($user->can('tasks.view')) {
            $events = $events->concat(LibraryTask::query()->where('assigned_to', $user->getKey())->whereBetween('due_at', [$from, $to])->get()->map(fn (LibraryTask $task): array => ['at' => $task->due_at, 'type' => 'task', 'title' => $task->title, 'status' => $task->status]));
        }
        if ($user->can('external_resources.view_analytics')) {
            $events = $events->concat(ExternalResource::query()->whereBetween('contract_ends_at', [$from->toDateString(), $to->toDateString()])->get()->map(fn (ExternalResource $resource): array => ['at' => $resource->contract_ends_at, 'type' => 'licence', 'title' => (string) (data_get($resource->name_translations, app()->getLocale()) ?: $resource->title), 'status' => $resource->publication_status]));
        }

        return $events->sortBy('at')->values();
    }

    /** @return array<string,Collection<int,mixed>> */
    public function search(User $user, string $term): array
    {
        $like = '%'.mb_strtolower(addcslashes($term, '%_\\')).'%';
        $matches = static function (Builder $query, string $column, string $value): Builder {
            $wrapped = $query->getQuery()->getGrammar()->wrap($column);

            return $query->whereRaw("LOWER({$wrapped}) LIKE ? ESCAPE '\\'", [$value]);
        };
        $result = ['records' => collect(), 'copies' => collect(), 'readers' => collect(), 'operations' => collect()];
        if ($user->can('catalog.search')) {
            $result['records'] = BibliographicRecord::query()->withCount(['copies', 'copies as available_copies_count' => fn (Builder $query) => $query->where('status', 'available')])->where(function (Builder $query) use ($like, $matches): void {
                $matches($query, 'title', $like)
                    ->orWhereRaw('LOWER('.$query->getQuery()->getGrammar()->wrap('isbn').") LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw('LOWER('.$query->getQuery()->getGrammar()->wrap('primary_author').") LIKE ? ESCAPE '\\'", [$like]);
            })->limit(10)->get(['id', 'title', 'primary_author', 'isbn']);
            $result['copies'] = BookCopy::query()->with(['bibliographicRecord:id,title', 'branch:id,name', 'fund:id,name'])->where(function (Builder $query) use ($like, $matches): void {
                $matches($query, 'inventory_number', $like)
                    ->orWhereRaw('LOWER('.$query->getQuery()->getGrammar()->wrap('barcode').") LIKE ? ESCAPE '\\'", [$like]);
            })->limit(10)->get();
        }
        if ($user->canAny(['circulation.issue', 'circulation.return', 'messages.view_all'])) {
            $result['readers'] = User::query()->with('readerProfile')->where(function (Builder $query) use ($like, $matches): void {
                $matches($query, 'name', $like)
                    ->orWhereRaw('LOWER('.$query->getQuery()->getGrammar()->wrap('email').") LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereHas('readerProfile', function (Builder $profile) use ($like, $matches): void {
                        $matches($profile, 'ticket_number', $like)
                            ->orWhereRaw('LOWER('.$profile->getQuery()->getGrammar()->wrap('barcode').") LIKE ? ESCAPE '\\'", [$like]);
                    });
            })->limit(10)->get(['id', 'name', 'email']);
        }
        if ($user->canAny(['circulation.issue', 'circulation.return', 'incidents.view', 'messages.view_all'])) {
            $result['operations'] = collect()
                ->concat(Loan::query()->where('id', ctype_digit($term) ? (int) $term : -1)->limit(3)->get()->map(fn ($row) => ['type' => 'loan', 'id' => $row->id, 'status' => $row->status]))
                ->concat(Reservation::query()->where('id', ctype_digit($term) ? (int) $term : -1)->limit(3)->get()->map(fn ($row) => ['type' => 'reservation', 'id' => $row->id, 'status' => $row->status]))
                ->concat(CirculationIncidentCase::query()->whereRaw('LOWER('.CirculationIncidentCase::query()->getQuery()->getGrammar()->wrap('case_number').") LIKE ? ESCAPE '\\'", [$like])->limit(3)->get()->map(fn ($row) => ['type' => 'incident', 'id' => $row->id, 'status' => $row->status]));
        }

        return $result;
    }
}
