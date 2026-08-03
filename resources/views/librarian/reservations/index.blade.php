@extends('layouts.librarian')

@section('title', __('librarian.reservations.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $activeStatuses = \App\Models\Catalog\Reservation::ACTIVE_STATUSES;
        $currentStatus = (string) ($filters['status'] ?? '');
        $baseQuery = request()->except(['page', 'status']);
        $activeTotal = collect($activeStatuses)->sum(fn (string $status): int => (int) ($statusCounts[$status] ?? 0));

        // Column heading reuses the existing queue_position sentence with the
        // placeholder stripped — no invented translation key.
        $queueColumnLabel = \Illuminate\Support\Str::of(__('librarian.reservations.queue_position', ['position' => '']))
            ->trim()
            ->rtrim(':')
            ->trim()
            ->toString();

        $statusTone = [
            'pending' => 'pending',
            'queued' => 'pending',
            'in_transit' => 'confirmed',
            'confirmed' => 'confirmed',
            'ready_for_pickup' => 'open',
            'fulfilled' => 'inactive',
            'cancelled' => 'inactive',
            'expired' => 'expired',
        ];
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.reservations.eyebrow')"
        :title="__('librarian.reservations.title')"
        :subtitle="__('librarian.reservations.subtitle')"
    >
        @can('circulation.issue')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.circulation.issue') }}">
                <span class="material-symbols-outlined text-[19px]">outbox</span>
                {{ __('librarian.nav.new_transaction') }}
            </a>
        @endcan
    </x-admin.page-header>

    {{-- §8.3: the librarian must not have to guess the hold period — the live
         setting value is stated on the screen itself. --}}
    <p class="mb-6 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <span class="material-symbols-outlined mt-px text-[18px] text-slate-400">schedule</span>
        <span>{{ __('librarian.reservations.lifespan_hint', ['days' => $pickupHoldDays]) }}</span>
    </p>

    <nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('librarian.reservations.filters.status') }}">
        <a
            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition {{ $currentStatus === '' ? 'border-secondary bg-secondary text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary' }}"
            href="{{ route('librarian.reservations.index', $baseQuery) }}"
            @if ($currentStatus === '') aria-current="page" @endif
        >
            <span class="material-symbols-outlined text-[18px]">bolt</span>
            {{ __('librarian.reservations.filters.active_only') }}
            <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $currentStatus === '' ? 'bg-white/20' : 'bg-slate-100 text-slate-700' }}">{{ $activeTotal }}</span>
        </a>

        @foreach (\App\Models\Catalog\Reservation::STATUSES as $status)
            <a
                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition {{ $currentStatus === $status ? 'border-secondary bg-secondary text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary' }}"
                href="{{ route('librarian.reservations.index', array_merge($baseQuery, ['status' => $status])) }}"
                @if ($currentStatus === $status) aria-current="page" @endif
            >
                {{ __('librarian.reservations.statuses.'.$status) }}
                <span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $currentStatus === $status ? 'bg-white/20' : 'bg-slate-100 text-slate-700' }}">{{ (int) ($statusCounts[$status] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('librarian.reservations.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="sm:col-span-2">
                <span class="admin-label">{{ __('librarian.reservations.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('librarian.reservations.filters.search') }}"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.reservations.filters.status') }}</span>
                <select class="admin-input" name="status">
                    <option value="">{{ __('librarian.reservations.filters.active_only') }}</option>
                    @foreach (\App\Models\Catalog\Reservation::STATUSES as $status)
                        <option value="{{ $status }}" @selected($currentStatus === $status)>
                            {{ __('librarian.reservations.statuses.'.$status) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button class="admin-btn admin-btn-primary flex-1" type="submit">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('librarian.reservations.index') }}"
                    title="{{ __('common.actions.clear_filters') }}"
                    aria-label="{{ __('common.actions.clear_filters') }}"
                >
                    <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
                </a>
            </div>
        </div>
    </form>

    <section class="admin-card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1180px]">
                <thead>
                    <tr>
                        <th>{{ __('librarian.reservations.reader') }}</th>
                        <th>{{ __('librarian.reservations.record') }}</th>
                        <th>{{ __('common.fields.status') }}</th>
                        <th>{{ $queueColumnLabel }}</th>
                        <th>{{ __('librarian.reservations.assigned_copy') }}</th>
                        <th>{{ __('librarian.reservations.expires_at') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservations as $reservation)
                        @php
                            $reader = $reservation->reader;
                            $ticket = $reader?->readerProfile?->ticket_number;
                            $record = $reservation->bibliographicRecord;
                            $copy = $reservation->assignedCopy;
                            $isCancellable = $reservation->isCancellable();
                            $canMarkReady = in_array($reservation->status, ['pending', 'confirmed'], true) && $copy !== null;
                            $canCancel = $isCancellable && (auth()->user()?->can('reservation.cancel_any') ?? false);
                            $hasActions = $reservation->status === 'pending' || $canMarkReady || $canCancel;

                            $profile = $reader?->readerProfile;
                            $waitingCount = (int) ($queueDepths[$reservation->bibliographic_record_id] ?? 0);
                            $copyCount = (int) ($copyCounts[$reservation->bibliographic_record_id] ?? 0);
                            $recordLoanDays = (int) ($loanPeriodDays[$reservation->bibliographic_record_id] ?? 0);
                            $forecastDays = $forecasts[$reservation->id] ?? null;
                            $log = $notificationLogs[$reservation->id] ?? collect();
                            $pickable = $assignableCopies[$reservation->id] ?? collect();
                            $someoneWaiting = (bool) ($queueWaiting[$reservation->id] ?? false);
                            $logistics = $reservation->logisticsState();

                            // §8.3: a hold may only be stretched while nobody is next in line.
                            $canExtend = $reservation->status === 'ready_for_pickup';
                            $canPassToNext = in_array($reservation->status, ['confirmed', 'ready_for_pickup'], true)
                                && $copy !== null
                                && (auth()->user()?->can('reservation.cancel_any') ?? false);
                        @endphp
                        <tr>
                            <td>
                                <div class="flex min-w-52 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-headline text-lg font-semibold text-primary">
                                        {{ mb_strtoupper(mb_substr((string) ($reader?->name ?: '—'), 0, 1)) }}
                                    </span>
                                    <span class="min-w-0">
                                        <strong class="block truncate text-sm text-primary">{{ $reader?->name ?? '—' }}</strong>
                                        <span class="block truncate font-mono text-xs text-slate-500" title="{{ __('librarian.circulation.ticket') }}">
                                            {{ $ticket ?: '—' }}
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="min-w-56 max-w-md">
                                    @if ($record)
                                        @can('catalog.edit_record')
                                            <a class="block text-sm font-semibold text-primary hover:text-secondary" href="{{ route('librarian.catalog.edit', $record) }}">
                                                {{ $record->title }}
                                            </a>
                                        @else
                                            <span class="block text-sm font-semibold text-primary">{{ $record->title }}</span>
                                        @endcan
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">
                                            {{ $record->primary_author ?: '—' }}@if ($record->publication_year) · {{ $record->publication_year }}@endif
                                        </span>
                                    @else
                                        <span class="text-sm text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <x-admin.status-badge
                                    :status="$statusTone[$reservation->status] ?? $reservation->status"
                                    :label="__('librarian.reservations.statuses.'.$reservation->status)"
                                />
                            </td>
                            <td class="text-slate-600">
                                <div class="min-w-44">
                                    @if ($reservation->queue_position !== null)
                                        <span class="block text-sm">{{ __('librarian.reservations.queue_position', ['position' => $reservation->queue_position]) }}</span>
                                    @else
                                        <span class="block text-slate-400">—</span>
                                    @endif
                                    @if ($waitingCount > 0)
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ __('librarian.reservations.queue_depth', ['count' => $waitingCount]) }}</span>
                                    @endif
                                    {{-- §8: rough estimate only, never presented as a promised date. --}}
                                    @if ($forecastDays !== null)
                                        <span
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"
                                            title="{{ __('librarian.reservations.forecast_approximate', ['period' => $recordLoanDays, 'copies' => $copyCount]) }}"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">timelapse</span>
                                            {{ __('librarian.reservations.forecast_value', ['days' => $forecastDays]) }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($copy)
                                    <div class="min-w-40">
                                        @canany(['copies.create', 'copies.edit'])
                                            <a class="block font-mono text-sm font-semibold text-primary hover:text-secondary" href="{{ route('librarian.copies.show', $copy) }}">
                                                {{ $copy->inventory_number }}
                                            </a>
                                        @else
                                            <span class="block font-mono text-sm font-semibold text-primary">{{ $copy->inventory_number }}</span>
                                        @endcanany
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $copy->branch?->name ?? '—' }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">{{ __('librarian.reservations.no_copy_assigned') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-slate-600">
                                {{ $reservation->expires_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td>
                                <div class="flex min-w-56 flex-col items-end gap-2">
                                    @if ($reservation->status === 'pending')
                                        <form method="POST" action="{{ route('librarian.reservations.confirm', $reservation) }}" class="w-full">
                                            @csrf
                                            {{-- §8 action "assign a copy": with more than one candidate the
                                                 librarian picks; a single option needs no choice. --}}
                                            @if ($pickable->count() > 1)
                                                <label class="mb-1.5 block text-left">
                                                    <span class="admin-label">{{ __('librarian.reservations.assign_copy') }}</span>
                                                    <select class="admin-input py-1.5 text-xs" name="assigned_copy_id">
                                                        <option value="">{{ __('librarian.reservations.assign_copy_auto') }}</option>
                                                        @foreach ($pickable as $candidate)
                                                            <option value="{{ $candidate->id }}" @selected($candidate->id === $reservation->assigned_copy_id)>
                                                                {{ $candidate->inventory_number }}@if ($candidate->branch?->name) · {{ $candidate->branch->name }}@endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            @endif
                                            <button class="admin-btn admin-btn-primary w-full px-3 py-1.5 text-xs" type="submit">
                                                <span class="material-symbols-outlined text-[17px]">task_alt</span>
                                                {{ __('librarian.reservations.confirm') }}
                                            </button>
                                        </form>
                                    @endif

                                    @if ($canMarkReady)
                                        <form method="POST" action="{{ route('librarian.reservations.ready', $reservation) }}" class="w-full">
                                            @csrf
                                            <button class="admin-btn admin-btn-secondary w-full px-3 py-1.5 text-xs" type="submit">
                                                <span class="material-symbols-outlined text-[17px]">inventory</span>
                                                {{ __('librarian.reservations.mark_ready') }}
                                            </button>
                                        </form>
                                    @endif

                                    @if ($canExtend)
                                        @if ($someoneWaiting)
                                            <span class="w-full rounded-xl bg-slate-100 px-3 py-1.5 text-center text-xs text-slate-500">
                                                {{ __('librarian.reservations.extend_blocked') }}
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('librarian.reservations.extend', $reservation) }}" class="w-full">
                                                @csrf
                                                <button class="admin-btn admin-btn-secondary w-full px-3 py-1.5 text-xs" type="submit">
                                                    <span class="material-symbols-outlined text-[17px]">more_time</span>
                                                    {{ __('librarian.reservations.extend') }}
                                                </button>
                                            </form>
                                        @endif
                                    @endif

                                    @if ($canPassToNext)
                                        @if (! $someoneWaiting)
                                            <span class="w-full rounded-xl bg-slate-100 px-3 py-1.5 text-center text-xs text-slate-500">
                                                {{ __('librarian.reservations.pass_to_next_blocked') }}
                                            </span>
                                        @else
                                            <details class="w-full">
                                                <summary class="admin-btn admin-btn-secondary w-full cursor-pointer px-3 py-1.5 text-xs">
                                                    <span class="material-symbols-outlined text-[17px]">move_down</span>
                                                    {{ __('librarian.reservations.pass_to_next') }}
                                                </summary>
                                                <form method="POST" action="{{ route('librarian.reservations.pass-to-next', $reservation) }}" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left">
                                                    @csrf
                                                    <p class="mb-2 text-xs text-slate-500">{{ __('librarian.reservations.pass_to_next_hint') }}</p>
                                                    <label class="block">
                                                        <span class="admin-label">{{ __('librarian.reservations.pass_to_next_reason') }}</span>
                                                        <textarea class="admin-input" name="reason" rows="3" minlength="5" maxlength="1000" required></textarea>
                                                    </label>
                                                    <button class="admin-btn admin-btn-secondary mt-2 w-full px-3 py-1.5 text-xs" type="submit">
                                                        {{ __('common.actions.confirm') }}
                                                    </button>
                                                </form>
                                            </details>
                                        @endif
                                    @endif

                                    @if ($canCancel)
                                        <details class="w-full">
                                            <summary class="admin-btn admin-btn-danger w-full cursor-pointer px-3 py-1.5 text-xs">
                                                <span class="material-symbols-outlined text-[17px]">cancel</span>
                                                {{ __('librarian.reservations.cancel') }}
                                            </summary>
                                            <form method="POST" action="{{ route('librarian.reservations.cancel', $reservation) }}" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left">
                                                @csrf
                                                <label class="block">
                                                    <span class="admin-label">{{ __('librarian.reservations.cancel_reason') }}</span>
                                                    <textarea
                                                        class="admin-input"
                                                        name="reason"
                                                        rows="3"
                                                        minlength="5"
                                                        maxlength="1000"
                                                        required
                                                    >{{ old('reason') }}</textarea>
                                                </label>
                                                @error('reason')
                                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                                @enderror
                                                <button class="admin-btn admin-btn-danger mt-2 w-full px-3 py-1.5 text-xs" type="submit">
                                                    {{ __('common.actions.confirm') }}
                                                </button>
                                            </form>
                                        </details>
                                    @endif

                                    @unless ($hasActions)
                                        <span class="text-xs text-slate-400">—</span>
                                    @endunless
                                </div>
                            </td>
                        </tr>

                        {{-- §8 "what the librarian must see": contacts, reader status, ISBN,
                             dates, logistics, and the notification log for this one hold. --}}
                        <tr class="border-t-0">
                            <td colspan="7" class="pt-0!">
                                <details class="rounded-xl border border-slate-200 bg-slate-50/70">
                                    <summary class="flex cursor-pointer items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-600 hover:text-secondary">
                                        <span class="material-symbols-outlined text-[17px]">expand_more</span>
                                        {{ __('librarian.reservations.details') }}
                                    </summary>

                                    <div class="grid gap-5 border-t border-slate-200 p-4 lg:grid-cols-3">
                                        <div class="space-y-3">
                                            <div>
                                                <p class="admin-label">{{ __('librarian.reservations.contacts') }}</p>
                                                <p class="text-sm text-slate-700">{{ $reader?->email ?: '—' }}</p>
                                                {{-- Honest gap: no phone column exists on users or reader_profiles. --}}
                                                <p class="text-xs italic text-slate-400">{{ __('librarian.reservations.phone_not_tracked') }}</p>
                                            </div>
                                            <div>
                                                <p class="admin-label">{{ __('librarian.reservations.reader_status') }}</p>
                                                @if ($profile)
                                                    <x-admin.status-badge
                                                        :status="$profile->status === 'active' ? 'active' : 'expired'"
                                                        :label="__('librarian.circulation.reader_statuses.'.$profile->status)"
                                                    />
                                                    @if ($profile->block_reason)
                                                        <p class="mt-1 text-xs text-slate-500">{{ $profile->block_reason }}</p>
                                                    @endif
                                                @else
                                                    <p class="text-sm text-slate-400">—</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <div>
                                                <p class="admin-label">{{ __('librarian.reservations.isbn') }}</p>
                                                <p class="font-mono text-sm text-slate-700">{{ $record?->isbn ?: '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="admin-label">{{ __('librarian.reservations.created_at') }}</p>
                                                <p class="text-sm text-slate-700">{{ $reservation->created_at?->format('d.m.Y H:i') ?? '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="admin-label">{{ __('librarian.reservations.forecast') }}</p>
                                                @if ($forecastDays !== null)
                                                    <p class="text-sm text-slate-700">{{ __('librarian.reservations.forecast_value', ['days' => $forecastDays]) }}</p>
                                                    <p class="text-xs text-slate-500">{{ __('librarian.reservations.forecast_approximate', ['period' => $recordLoanDays, 'copies' => $copyCount]) }}</p>
                                                @else
                                                    <p class="text-sm text-slate-400">{{ __('librarian.reservations.forecast_unavailable') }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="space-y-3">
                                            <div>
                                                <p class="admin-label">{{ __('librarian.reservations.logistics') }}</p>
                                                <p class="text-sm text-slate-700">
                                                    {{ __('librarian.reservations.logistics_states.'.($logistics ?? 'unknown')) }}
                                                    @if ($logistics === 'in_transit' && $reservation->pendingTransferBranch)
                                                        — {{ $reservation->pendingTransferBranch->name }}
                                                    @endif
                                                </p>
                                            </div>

                                            @can('reservation.manage_transfer')
                                                @php($transfer = $reservation->transfer)
                                                @if(!$transfer && $reservation->status==='confirmed' && $reservation->pickup_branch_id && $copy?->branch_id !== $reservation->pickup_branch_id)
                                                    <form method="POST" action="{{ route('librarian.reservations.transfer.request',$reservation) }}">@csrf<button class="admin-btn admin-btn-secondary px-3 py-1.5 text-xs" type="submit">{{ __('librarian.reservations.transfer_request') }}</button></form>
                                                @elseif($transfer)
                                                    <p class="text-xs text-slate-500">{{ $transfer->transfer_number }} · {{ $transfer->sourceBranch?->name }} → {{ $transfer->destinationBranch?->name }}</p>
                                                    @if($transfer->status==='requested')<form method="POST" action="{{ route('librarian.transfers.approve',$transfer) }}">@csrf<button class="admin-btn admin-btn-secondary px-3 py-1.5 text-xs" type="submit">{{ __('librarian.reservations.transfer_approve') }}</button></form>@endif
                                                    @if($transfer->status==='approved')<form method="POST" action="{{ route('librarian.transfers.send',$transfer) }}">@csrf<button class="admin-btn admin-btn-secondary px-3 py-1.5 text-xs" type="submit">{{ __('librarian.reservations.transfer_send') }}</button></form>@endif
                                                    @if($transfer->status==='in_transit')<form method="POST" action="{{ route('librarian.transfers.receive',$transfer) }}" class="space-y-2">@csrf<input class="admin-input py-1.5 text-xs" name="scanned_code" autocomplete="off" placeholder="{{ __('librarian.reservations.transfer_scan') }}" required><button class="admin-btn admin-btn-primary px-3 py-1.5 text-xs" type="submit">{{ __('librarian.reservations.transfer_receive') }}</button></form>@endif
                                                @endif
                                            @endcan
                                        </div>

                                        <div class="lg:col-span-3">
                                            <p class="admin-label">{{ __('librarian.reservations.notifications_log') }}</p>
                                            @if ($log->isEmpty())
                                                <p class="text-sm text-slate-400">{{ __('librarian.reservations.notifications_log_empty') }}</p>
                                            @else
                                                <ul class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white">
                                                    @foreach ($log as $entry)
                                                        <li class="flex flex-wrap items-baseline justify-between gap-2 px-3 py-2">
                                                            <span class="text-sm text-slate-700">{{ $entry->title }}</span>
                                                            <span class="flex items-center gap-3 text-xs text-slate-500">
                                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold">{{ __('librarian.reservations.channel_in_app') }}</span>
                                                                <span>{{ $entry->created_at?->format('d.m.Y H:i') ?? '—' }}</span>
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            {{-- Honest gap: email dispatch is not persisted, so it cannot be shown. --}}
                                            <p class="mt-1 text-xs italic text-slate-400">{{ __('librarian.reservations.notifications_log_disclaimer') }}</p>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">bookmark_manager</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.reservations.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$reservations" />
    </section>
@endsection
