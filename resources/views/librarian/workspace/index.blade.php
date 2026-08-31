@extends('layouts.librarian')

@section('title', __('workspace.sections.'.$section.'.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header :eyebrow="__('workspace.eyebrow')" :title="__('workspace.sections.'.$section.'.title')" :subtitle="__('workspace.sections.'.$section.'.description')" />

    <nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('workspace.navigation') }}">
        @foreach(['search', 'tasks', 'calendar', 'movements', 'orders', 'edd', 'periodicals'] as $workspaceSection)
            @can(match($workspaceSection) {'tasks' => 'tasks.view', 'calendar' => 'calendar.view', 'movements' => 'copies.movements.view', 'orders' => 'acquisitions.view', 'edd' => 'edd.view', 'periodicals' => 'periodicals.view', default => 'catalog.search'})
                <a @class(['admin-btn', 'admin-btn-primary' => $section === $workspaceSection, 'admin-btn-secondary' => $section !== $workspaceSection]) href="{{ route('librarian.workspace.'.$workspaceSection) }}">{{ __('workspace.sections.'.$workspaceSection.'.short') }}</a>
            @endcan
        @endforeach
    </nav>

    @if($section === 'tasks')
        @canany(['tasks.manage_own', 'tasks.assign'])
            <form method="POST" action="{{ route('librarian.workspace.tasks.store') }}" class="admin-card mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf
                <label class="xl:col-span-2"><span class="admin-label">{{ __('workspace.fields.title') }}</span><input class="admin-input" name="title" required maxlength="255" value="{{ old('title') }}"></label>
                <label><span class="admin-label">{{ __('workspace.fields.type') }}</span><select class="admin-input" name="type">@foreach(['general','catalogue','circulation','incident','message','electronic','event','licence'] as $value)<option value="{{ $value }}">{{ __('workspace.task_types.'.$value) }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('workspace.fields.priority') }}</span><select class="admin-input" name="priority">@foreach(['low','normal','high','critical'] as $value)<option value="{{ $value }}">{{ __('workspace.priorities.'.$value) }}</option>@endforeach</select></label>
                @can('tasks.assign')<label><span class="admin-label">{{ __('workspace.fields.assigned_to') }}</span><select class="admin-input" name="assigned_to">@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>@endcan
                <label><span class="admin-label">{{ __('workspace.fields.due_at') }}</span><input class="admin-input" type="datetime-local" name="due_at"></label>
                <label class="md:col-span-2"><span class="admin-label">{{ __('workspace.fields.comment') }}</span><textarea class="admin-input" name="comment" maxlength="4000"></textarea></label>
                <div class="self-end"><button class="admin-btn admin-btn-primary" type="submit">{{ __('workspace.actions.create_task') }}</button></div>
            </form>
        @endcanany
        <section class="admin-card overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('workspace.fields.title') }}</th><th>{{ __('workspace.fields.assigned_to') }}</th><th>{{ __('workspace.fields.priority') }}</th><th>{{ __('workspace.fields.due_at') }}</th><th>{{ __('workspace.fields.status') }}</th><th>{{ __('common.fields.actions') }}</th></tr></thead><tbody>
        @forelse($tasks as $task)<tr><td><strong>{{ $task->title }}</strong><div class="text-xs text-slate-500">{{ __('workspace.task_types.'.$task->type) }}</div></td><td>{{ $task->assignee?->name }}</td><td>{{ __('workspace.priorities.'.$task->priority) }}</td><td>{{ $task->due_at?->timezone(config('app.library_timezone'))->format('d.m.Y H:i') ?? '—' }}</td><td>{{ __('workspace.statuses.'.$task->status) }}</td><td><form class="flex gap-2" method="POST" action="{{ route('librarian.workspace.tasks.update', $task) }}">@csrf @method('PATCH')<select class="admin-input min-w-36" name="status">@foreach(['open','in_progress','blocked','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($task->status === $status)>{{ __('workspace.statuses.'.$status) }}</option>@endforeach</select><button class="admin-btn admin-btn-secondary">{{ __('common.actions.save') }}</button></form></td></tr>
        @empty<tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('workspace.empty.tasks') }}</td></tr>@endforelse
        </tbody></table>{{ $tasks->links() }}</section>

    @elseif($section === 'orders')
        @canany(['acquisitions.create_order','acquisitions.manage'])
        <form method="POST" action="{{ route('librarian.workspace.orders.store') }}" class="admin-card mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf
            <label><span class="admin-label">{{ __('workspace.fields.order_number') }}</span><input class="admin-input" name="order_number" required maxlength="64"></label><label><span class="admin-label">{{ __('workspace.fields.supplier') }}</span><input class="admin-input" name="supplier" maxlength="255"></label>
            <label><span class="admin-label">{{ __('workspace.fields.status') }}</span><select class="admin-input" name="status">@foreach(['requested','approved','ordered'] as $status)<option value="{{ $status }}">{{ __('workspace.statuses.'.$status) }}</option>@endforeach</select></label><label><span class="admin-label">{{ __('workspace.fields.expected_at') }}</span><input class="admin-input" type="date" name="expected_at"></label>
            <label class="md:col-span-2"><span class="admin-label">{{ __('workspace.fields.document') }}</span><input class="admin-input" name="item[title_snapshot]" required maxlength="255"></label><label><span class="admin-label">{{ __('workspace.fields.quantity') }}</span><input class="admin-input" type="number" name="item[quantity_ordered]" value="1" min="1" required></label><label><span class="admin-label">{{ __('workspace.fields.unit_price') }}</span><input class="admin-input" type="number" step="0.01" min="0" name="item[unit_price]" value="0" required></label>
            <input type="hidden" name="currency" value="KZT"><div><button class="admin-btn admin-btn-primary">{{ __('workspace.actions.create_order') }}</button></div>
        </form>@endcanany
        <section class="admin-card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>{{ __('workspace.fields.order_number') }}</th><th>{{ __('workspace.fields.supplier') }}</th><th>{{ __('workspace.fields.document') }}</th><th>{{ __('workspace.fields.status') }}</th><th>{{ __('workspace.fields.received_quantity') }}</th><th>{{ __('workspace.fields.total') }}</th><th>{{ __('common.fields.actions') }}</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    @foreach($order->items as $item)
                        @php($remaining = max(0, (int) $item->quantity_ordered - (int) $item->quantity_received))
                        <tr>
                            <td>{{ $order->order_number }}<div class="text-xs text-slate-500">{{ $order->expected_at?->format('d.m.Y') ?? '—' }}</div></td>
                            <td>{{ $order->supplier ?: '—' }}</td>
                            <td><strong>{{ $item->title_snapshot }}</strong><div class="text-xs text-slate-500">@if($item->record)@can('catalog.edit_record')<a class="text-secondary hover:underline" href="{{ route('librarian.catalog.edit', $item->record) }}">#{{ $item->record->id }} · {{ $item->record->title }}</a>@else#{{ $item->record->id }} · {{ $item->record->title }}@endcan @else{{ __('workspace.messages.record_not_linked') }}@endif</div></td>
                            <td>{{ __('workspace.statuses.'.$order->status) }}</td>
                            <td>{{ $item->quantity_received }} / {{ $item->quantity_ordered }}</td>
                            <td>{{ number_format((float)$order->total_amount,2,',',' ') }} {{ $order->currency }}</td>
                            <td>
                                @canany(['acquisitions.receive','acquisitions.manage'])
                                    @if($order->status !== 'cancelled' && ($remaining > 0 || !$item->bibliographic_record_id))
                                        <form method="POST" action="{{ route('librarian.workspace.orders.items.receive', [$order, $item]) }}" class="grid min-w-64 gap-2">@csrf @method('PATCH')
                                            <label><span class="admin-label">{{ __('workspace.fields.received_now') }}</span><input class="admin-input" type="number" name="received_quantity" min="0" max="{{ $remaining }}" value="{{ $remaining > 0 ? $remaining : 0 }}" required></label>
                                            <label><span class="admin-label">{{ __('workspace.fields.catalog_record_id') }}</span><input class="admin-input" type="number" name="bibliographic_record_id" min="1" value="{{ $item->bibliographic_record_id }}" placeholder="ID"></label>
                                            <button class="admin-btn admin-btn-secondary" type="submit">{{ __('workspace.actions.receive_order') }}</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500">{{ __('workspace.messages.receipt_complete') }}</span>
                                    @endif
                                @endcanany
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">{{ __('workspace.empty.orders') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $orders->links() }}
        </section>

    @elseif($section === 'edd')
        @can('edd.manage')<form method="POST" action="{{ route('librarian.workspace.edd.store') }}" class="admin-card mb-6 grid gap-4 md:grid-cols-2">@csrf<label><span class="admin-label">{{ __('workspace.fields.request_number') }}</span><input class="admin-input" name="request_number" required maxlength="64"></label><label><span class="admin-label">{{ __('workspace.fields.due_at') }}</span><input class="admin-input" type="datetime-local" name="due_at"></label><label class="md:col-span-2"><span class="admin-label">{{ __('workspace.fields.document') }}</span><textarea class="admin-input" name="requested_document" required maxlength="4000"></textarea></label><label><span class="admin-label">{{ __('workspace.fields.source') }}</span><input class="admin-input" name="source" maxlength="255"></label><label><span class="admin-label">{{ __('workspace.fields.rights') }}</span><input class="admin-input" name="rights_restrictions" maxlength="4000"></label><input type="hidden" name="status" value="requested"><button class="admin-btn admin-btn-primary w-fit">{{ __('workspace.actions.create_edd') }}</button></form>@endcan
        <section class="admin-card overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('workspace.fields.request_number') }}</th><th>{{ __('workspace.fields.document') }}</th><th>{{ __('workspace.fields.source') }}</th><th>{{ __('workspace.fields.responsible') }}</th><th>{{ __('workspace.fields.due_at') }}</th><th>{{ __('workspace.fields.status') }}</th></tr></thead><tbody>@forelse($deliveries as $delivery)<tr><td>{{ $delivery->request_number }}</td><td>{{ $delivery->requested_document }}</td><td>{{ $delivery->source ?: '—' }}</td><td>{{ $delivery->responsible?->name ?? '—' }}</td><td>{{ $delivery->due_at?->timezone(config('app.library_timezone'))->format('d.m.Y H:i') ?? '—' }}</td><td>{{ __('workspace.statuses.'.$delivery->status) }}</td></tr>@empty<tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('workspace.empty.edd') }}</td></tr>@endforelse</tbody></table>{{ $deliveries->links() }}</section>

    @elseif($section === 'periodicals')
        @can('periodicals.manage')<form method="POST" action="{{ route('librarian.workspace.periodicals.store') }}" class="admin-card mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">@csrf<label class="md:col-span-2"><span class="admin-label">{{ __('workspace.fields.title') }}</span><input class="admin-input" name="title_snapshot" required maxlength="255"></label><label><span class="admin-label">{{ __('workspace.fields.year') }}</span><input class="admin-input" type="number" name="year" value="{{ now()->year }}" min="1900" required></label><label><span class="admin-label">{{ __('workspace.fields.expected_issues') }}</span><input class="admin-input" type="number" name="expected_issues" value="0" min="0" required></label><label><span class="admin-label">{{ __('workspace.fields.branch') }}</span><select class="admin-input" name="branch_id"><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label><label><span class="admin-label">{{ __('workspace.fields.fund') }}</span><select class="admin-input" name="fund_id"><option value="">—</option>@foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach</select></label><input type="hidden" name="status" value="active"><button class="admin-btn admin-btn-primary w-fit">{{ __('workspace.actions.create_periodical') }}</button></form>@endcan
        <div class="space-y-4">@forelse($subscriptions as $subscription)<article class="admin-card"><div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-headline text-xl text-primary">{{ $subscription->title_snapshot }} · {{ $subscription->year }}</h2><p class="text-sm text-slate-600">{{ __('workspace.fields.expected_issues') }}: {{ $subscription->expected_issues }} · {{ __('workspace.fields.received_issues') }}: {{ $subscription->issues->where('status','received')->count() }}</p></div><span>{{ __('workspace.statuses.'.$subscription->status) }}</span></div>@can('periodicals.manage')<form method="POST" action="{{ route('librarian.workspace.periodicals.issues.store', $subscription) }}" class="mt-4 flex flex-wrap gap-3">@csrf<input class="admin-input max-w-40" name="issue_number" required placeholder="№"><input class="admin-input max-w-48" type="date" name="received_at"><input type="hidden" name="status" value="received"><button class="admin-btn admin-btn-secondary">{{ __('workspace.actions.receive_issue') }}</button></form>@endcan</article>@empty<div class="admin-card py-8 text-center text-slate-500">{{ __('workspace.empty.periodicals') }}</div>@endforelse{{ $subscriptions->links() }}</div>

    @elseif($section === 'movements')
        @can('copies.movements.create')
            <form method="POST" action="{{ route('librarian.workspace.movements.store') }}" class="admin-card mb-6">
                @csrf
                <div class="mb-5">
                    <h2 class="font-headline text-2xl text-primary">{{ __('fund_movements.create.title') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('fund_movements.create.description') }}</p>
                </div>
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    <label class="lg:col-span-2 xl:row-span-2">
                        <span class="admin-label">{{ __('fund_movements.fields.copy_codes') }}</span>
                        <textarea class="admin-input min-h-36 font-mono" name="copy_codes" required maxlength="20000" placeholder="{{ __('fund_movements.placeholders.copy_codes') }}">{{ old('copy_codes') }}</textarea>
                        <span class="mt-1 block text-xs text-slate-500">{{ __('fund_movements.hints.copy_codes') }}</span>
                    </label>
                    <label><span class="admin-label">{{ __('fund_movements.fields.branch') }}</span><select class="admin-input" name="branch_id"><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('branch_id') === (string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                    <label><span class="admin-label">{{ __('fund_movements.fields.fund') }}</span><select class="admin-input" name="fund_id"><option value="">—</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((string)old('fund_id') === (string)$fund->id)>{{ $fund->name }}</option>@endforeach</select></label>
                    <label><span class="admin-label">{{ __('fund_movements.fields.sigla') }}</span><input class="admin-input" name="storage_sigla" maxlength="64" value="{{ old('storage_sigla') }}"></label>
                    <label><span class="admin-label">{{ __('fund_movements.fields.service_point') }}</span><input class="admin-input" name="service_point_code" maxlength="64" value="{{ old('service_point_code') }}"></label>
                    <label><span class="admin-label">{{ __('fund_movements.fields.shelf_index') }}</span><input class="admin-input" name="shelf_index" maxlength="128" value="{{ old('shelf_index') }}"></label>
                    <label><span class="admin-label">{{ __('fund_movements.fields.shelf') }}</span><input class="admin-input" name="shelf_location" maxlength="255" value="{{ old('shelf_location') }}"></label>
                    <label class="lg:col-span-2 xl:col-span-4"><span class="admin-label">{{ __('fund_movements.fields.reason') }}</span><textarea class="admin-input" name="reason" required minlength="5" maxlength="2000">{{ old('reason') }}</textarea></label>
                </div>
                <div class="mt-5 flex justify-end"><button class="admin-btn admin-btn-primary" type="submit">{{ __('fund_movements.actions.move') }}</button></div>
            </form>
        @endcan

        <form class="admin-card mb-6 grid gap-3 md:grid-cols-4" method="GET">
            <label class="md:col-span-2"><span class="admin-label">{{ __('fund_movements.fields.search') }}</span><input class="admin-input" name="q" value="{{ $movementFilters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('fund_movements.placeholders.search') }}"></label>
            <label><span class="admin-label">{{ __('fund_movements.fields.date_from') }}</span><input class="admin-input" type="date" name="date_from" value="{{ $movementFilters['date_from'] ?? '' }}"></label>
            <label><span class="admin-label">{{ __('fund_movements.fields.date_to') }}</span><input class="admin-input" type="date" name="date_to" value="{{ $movementFilters['date_to'] ?? '' }}"></label>
            <div class="md:col-span-4 flex gap-2"><button class="admin-btn admin-btn-primary">{{ __('common.actions.search') }}</button><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.workspace.movements') }}">{{ __('common.actions.reset') }}</a></div>
        </form>

        <section class="admin-card overflow-x-auto">
            <table class="admin-table min-w-full">
                <thead><tr><th>{{ __('workspace.fields.date') }}</th><th>{{ __('workspace.fields.operation') }}</th><th>{{ __('workspace.fields.copy') }}</th><th>{{ __('workspace.fields.title') }}</th><th>{{ __('fund_movements.fields.route') }}</th><th>{{ __('workspace.fields.responsible') }}</th></tr></thead>
                <tbody>
                @forelse($movements as $movement)
                    @php($oldPlacement = data_get($movement->details, 'old', []))
                    @php($newPlacement = data_get($movement->details, 'new', []))
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->occurred_at?->timezone(config('app.library_timezone'))->format('d.m.Y H:i') }}</td>
                        <td>{{ trans()->has('librarian.copies.events.'.$movement->event_type) ? __('librarian.copies.events.'.$movement->event_type) : __('fund_movements.events.'.$movement->event_type) }}</td>
                        <td><a class="font-mono font-semibold text-secondary hover:underline" href="{{ $movement->copy ? route('librarian.copies.show', $movement->copy) : '#' }}">{{ $movement->copy?->inventory_number ?? '—' }}</a></td>
                        <td>{{ $movement->copy?->bibliographicRecord?->title ?? '—' }}</td>
                        <td class="min-w-64 text-xs text-slate-600">
                            <div>{{ __('fund_movements.fields.from') }}: {{ collect([$oldPlacement['storage_sigla'] ?? null, $oldPlacement['service_point_code'] ?? null, $oldPlacement['shelf_index'] ?? null, $oldPlacement['shelf_location'] ?? null])->filter()->implode(' / ') ?: '—' }}</div>
                            <div class="mt-1 font-semibold text-primary">{{ __('fund_movements.fields.to') }}: {{ collect([$newPlacement['storage_sigla'] ?? null, $newPlacement['service_point_code'] ?? null, $newPlacement['shelf_index'] ?? null, $newPlacement['shelf_location'] ?? null])->filter()->implode(' / ') ?: '—' }}</div>
                        </td>
                        <td>{{ $movement->actor?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('workspace.empty.movements') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $movements->links() }}
        </section>

    @elseif($section === 'calendar')
        <form class="admin-card mb-6 flex flex-wrap items-end gap-3"><label><span class="admin-label">{{ __('workspace.fields.month') }}</span><input class="admin-input" type="month" name="month" value="{{ $month }}"></label><button class="admin-btn admin-btn-primary">{{ __('common.actions.open') }}</button></form><ol class="space-y-3">@forelse($events as $event)<li class="admin-card flex gap-4"><time class="font-bold text-secondary">{{ optional($event['at'])->format('d.m H:i') ?: '—' }}</time><div><strong>{{ $event['title'] }}</strong><p class="text-xs text-slate-500">{{ __('workspace.calendar_types.'.$event['type']) }} · {{ trans()->has('workspace.statuses.'.$event['status']) ? __('workspace.statuses.'.$event['status']) : __('analytics.statuses.unknown') }}</p></div></li>@empty<li class="admin-card py-8 text-center text-slate-500">{{ __('workspace.empty.calendar') }}</li>@endforelse</ol>

    @else
        <form class="admin-card mb-6 flex gap-3" role="search"><label class="flex-1"><span class="sr-only">{{ __('workspace.actions.search') }}</span><input class="admin-input" type="search" name="q" value="{{ $query }}" minlength="2" maxlength="100" placeholder="{{ __('workspace.search_placeholder') }}"></label><button class="admin-btn admin-btn-primary">{{ __('workspace.actions.search') }}</button></form>
        @if($query !== '')<div class="grid gap-5 xl:grid-cols-2">@foreach(['records','copies','readers','operations'] as $group)<section class="admin-card"><h2 class="font-headline text-xl text-primary">{{ __('workspace.search_groups.'.$group) }}</h2><ul class="mt-4 space-y-2">@forelse($results[$group] ?? [] as $item)
            <li class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm">
                @if($group === 'records')<a class="block" href="{{ route('librarian.catalog.edit', $item) }}"><strong class="text-primary">{{ $item->title }}</strong><span class="mt-1 block text-slate-600">{{ $item->primary_author ?: '—' }} · {{ $item->isbn ?: '—' }}</span><span class="mt-2 flex items-center gap-1 text-xs font-semibold text-secondary">{{ $item->available_copies_count }} / {{ $item->copies_count }} {{ __('librarian.nav.copies') }} <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span></span></a>
                @elseif($group === 'copies')<a class="block" href="{{ route('librarian.copies.show', $item) }}"><strong class="font-mono text-primary">{{ $item->inventory_number }}</strong><span class="ml-2">{{ $item->bibliographicRecord?->title }}</span><span class="mt-2 block text-xs text-slate-500">{{ $item->branch?->name ?? '—' }} · {{ $item->fund?->name ?? '—' }} · {{ $item->shelf_location ?: '—' }}</span><span class="mt-1 flex items-center gap-1 text-xs font-semibold text-secondary">{{ __('librarian.copies.statuses.'.$item->status) }} <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span></span></a>
                @elseif($group === 'readers')<strong>{{ $item->name }}</strong><span class="ml-2 text-slate-500">{{ $item->email }}</span>
                @else<strong>{{ __('workspace.operation_types.'.$item['type']) }} #{{ $item['id'] }}</strong> · {{ trans()->has('analytics.statuses.'.$item['status']) ? __('analytics.statuses.'.$item['status']) : $item['status'] }}@endif
            </li>
        @empty<li class="text-sm text-slate-500">{{ __('workspace.empty.search') }}</li>@endforelse</ul></section>@endforeach</div>@endif
    @endif
@endsection
