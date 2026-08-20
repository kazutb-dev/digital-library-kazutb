<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\AcquisitionOrder;
use App\Models\AcquisitionOrderItem;
use App\Models\Branch;
use App\Models\Fund;
use App\Models\LibraryTask;
use App\Models\PeriodicalSubscription;
use App\Models\User;
use App\Services\Library\LibrarianWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function tasks(Request $request, LibrarianWorkspaceService $workspace): View
    {
        return $this->view('tasks', ['tasks' => $workspace->tasks($request->user()), 'staff' => $this->staff()]);
    }

    public function storeTask(Request $request, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        abort_unless($request->user()->canAny(['tasks.manage_own', 'tasks.assign']), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['general', 'catalogue', 'circulation', 'incident', 'message', 'electronic', 'event', 'licence'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'critical'])],
            'due_at' => ['nullable', 'date'], 'comment' => ['nullable', 'string', 'max:4000'],
        ]);
        $workspace->createTask($request->user(), $data);

        return back()->with('success', __('workspace.messages.task_created'));
    }

    public function updateTask(Request $request, LibraryTask $task, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'blocked', 'completed', 'cancelled'])], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'comment' => ['nullable', 'string', 'max:4000']]);
        $workspace->updateTask($request->user(), $task, $data);

        return back()->with('success', __('workspace.messages.task_updated'));
    }

    public function orders(LibrarianWorkspaceService $workspace): View
    {
        return $this->view('orders', ['orders' => $workspace->orders()]);
    }

    public function storeOrder(Request $request, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        abort_unless($request->user()->canAny(['acquisitions.create_order', 'acquisitions.manage']), 403);
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:64', 'unique:acquisition_orders,order_number'], 'supplier' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['requested', 'approved', 'ordered'])],
            'ordered_at' => ['nullable', 'date'], 'expected_at' => ['nullable', 'date'], 'currency' => ['required', Rule::in(['KZT', 'USD', 'EUR'])], 'notes' => ['nullable', 'string', 'max:4000'],
            'item.bibliographic_record_id' => ['nullable', 'integer', 'exists:bibliographic_records,id'], 'item.title_snapshot' => ['required', 'string', 'max:255'],
            'item.quantity_ordered' => ['required', 'integer', 'min:1', 'max:100000'], 'item.quantity_received' => ['nullable', 'integer', Rule::in([0])],
            'item.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ]);
        $data['item']['quantity_received'] ??= 0;
        $workspace->createOrder($request->user(), $data);

        return back()->with('success', __('workspace.messages.order_created'));
    }

    public function receiveOrderItem(
        Request $request,
        AcquisitionOrder $order,
        AcquisitionOrderItem $item,
        LibrarianWorkspaceService $workspace,
    ): RedirectResponse {
        abort_unless($request->user()->canAny(['acquisitions.receive', 'acquisitions.manage']), 403);
        $data = $request->validate([
            'received_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'bibliographic_record_id' => ['nullable', 'integer', 'exists:bibliographic_records,id'],
        ]);
        $workspace->receiveOrderItem($request->user(), $order, $item, $data);

        return back()->with('success', __('workspace.messages.order_received'));
    }

    public function deliveries(Request $request, LibrarianWorkspaceService $workspace): View
    {
        return $this->view('edd', ['deliveries' => $workspace->deliveries($request->user()), 'staff' => $this->staff()]);
    }

    public function storeDelivery(Request $request, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        abort_unless($request->user()->can('edd.manage'), 403);
        $data = $request->validate([
            'request_number' => ['required', 'string', 'max:64', 'unique:document_delivery_requests,request_number'], 'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requested_document' => ['required', 'string', 'max:4000'], 'source' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['requested', 'searching', 'ordered', 'received', 'completed', 'rejected', 'cancelled'])],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'], 'due_at' => ['nullable', 'date'], 'rights_restrictions' => ['nullable', 'string', 'max:4000'],
        ]);
        $workspace->createDelivery($request->user(), $data);

        return back()->with('success', __('workspace.messages.edd_created'));
    }

    public function periodicals(LibrarianWorkspaceService $workspace): View
    {
        return $this->view('periodicals', ['subscriptions' => $workspace->periodicals(), 'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(), 'funds' => Fund::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function storePeriodical(Request $request, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        abort_unless($request->user()->can('periodicals.manage'), 403);
        $workspace->createPeriodical($request->user(), $request->validate([
            'bibliographic_record_id' => ['nullable', 'integer', 'exists:bibliographic_records,id'], 'title_snapshot' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(now()->year + 2)], 'expected_issues' => ['required', 'integer', 'min:0', 'max:1000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'fund_id' => ['nullable', 'integer', 'exists:funds,id'], 'shelf' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
        ]));

        return back()->with('success', __('workspace.messages.periodical_created'));
    }

    public function receiveIssue(Request $request, PeriodicalSubscription $subscription, LibrarianWorkspaceService $workspace): RedirectResponse
    {
        abort_unless($request->user()->can('periodicals.manage'), 403);
        $data = $request->validate(['issue_number' => ['required', 'string', 'max:64'], 'expected_at' => ['nullable', 'date'], 'received_at' => ['nullable', 'date'], 'status' => ['required', Rule::in(['expected', 'received', 'missing', 'claimed'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $workspace->receiveIssue($request->user(), $subscription, $data);

        return back()->with('success', __('workspace.messages.issue_saved'));
    }

    public function movements(LibrarianWorkspaceService $workspace): View
    {
        return $this->view('movements', ['movements' => $workspace->movements()]);
    }

    public function calendar(Request $request, LibrarianWorkspaceService $workspace): View
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['month'])
            ? Carbon::createFromFormat('!Y-m', $validated['month'], config('app.timezone'))
            : now();
        $from = $month->copy()->startOfMonth()->utc();
        $to = $month->copy()->endOfMonth()->utc();

        return $this->view('calendar', ['events' => $workspace->calendar($request->user(), $from, $to), 'month' => $month->format('Y-m')]);
    }

    public function search(Request $request, LibrarianWorkspaceService $workspace): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'min:2', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));

        return $this->view('search', ['query' => $term, 'results' => $term === '' ? [] : $workspace->search($request->user(), $term)]);
    }

    /** @param array<string,mixed> $data */
    private function view(string $section, array $data = []): View
    {
        return view('librarian.workspace.index', [...$data, 'section' => $section]);
    }

    private function staff()
    {
        return User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->whereNotIn('name', ['member']))->orderBy('name')->get(['id', 'name', 'email']);
    }
}
