@php
    $profile = $summary['profile'] ?? $reader?->readerProfile;
    $readerCategory = $profile?->category;
    $readerStatus = $profile?->status;
    $readerStatusTone = match ((string) $readerStatus) {
        'active' => 'active',
        'blocked', 'suspended' => 'expired',
        default => 'unknown',
    };

    $canIssue = (bool) (auth()->user()?->can('circulation.issue'));

    $copyJsLabels = [
        'not_found' => __('librarian.circulation.copy_not_found_js'),
        'found' => __('librarian.circulation.copy_found'),
        'inventory_number' => __('librarian.copies.fields.inventory_number'),
        'barcode' => __('librarian.copies.fields.barcode'),
        'branch' => __('librarian.copies.fields.branch'),
        'access_restriction' => __('librarian.copies.fields.access_restriction'),
        'current_loan' => __('librarian.copies.current_loan'),
        'reserved_for' => __('librarian.copies.reserved_for'),
        'due_date' => __('librarian.circulation.due_date'),
        'overdue_days' => __('librarian.circulation.overdue_days', ['count' => ':count']),
        'no_results' => __('common.pagination.no_results'),
        'ticket' => __('librarian.circulation.ticket'),
    ];

    $accessLabels = [];
    foreach (\App\Models\Catalog\BookCopy::ACCESS_RESTRICTIONS as $restriction) {
        $accessLabels[$restriction] = __('librarian.copies.access_restrictions.'.$restriction);
    }

    $categoryLabels = [];
    foreach (\App\Models\Catalog\ReaderProfile::CATEGORIES as $category) {
        $categoryLabels[$category] = __('librarian.circulation.reader_categories.'.$category);
    }
@endphp

@extends('layouts.librarian')

@section('title', __('librarian.circulation.issue_title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.circulation.eyebrow')"
        :title="__('librarian.circulation.issue_title')"
        :subtitle="__('librarian.circulation.issue_subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
        @can('circulation.return')
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation.return') }}">
                <span class="material-symbols-outlined text-[19px]">inbox</span>
                {{ __('librarian.circulation.return_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    {{-- STEP 1 — identify the reader --}}
    <section class="admin-card mb-6">
        <header class="mb-4 flex items-center gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-container font-headline text-sm font-bold text-on-primary">1</span>
            <h2 class="font-headline text-xl text-primary">{{ __('librarian.circulation.step_reader') }}</h2>
        </header>

        @if ($reader === null)
            <div class="max-w-2xl">
                <label class="admin-label" for="reader-search">{{ __('librarian.circulation.reader_search') }}</label>
                <div class="relative" id="reader-search-wrap">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-[0.65rem] text-[19px] text-slate-400">person_search</span>
                    <input
                        class="admin-input pl-10"
                        id="reader-search"
                        type="search"
                        autocomplete="off"
                        autofocus
                        placeholder="{{ __('librarian.circulation.reader_search_placeholder') }}"
                    >
                    <ul
                        class="absolute left-0 right-0 top-full z-30 mt-1 hidden max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        id="reader-results"
                        aria-live="polite"
                    ></ul>
                </div>
                <p class="mt-2 text-xs text-slate-500">{{ __('librarian.circulation.reader_search_hint') }}</p>
                @include('librarian.partials.camera-scanner', ['targetId' => 'reader-search'])
            </div>
        @else
            <div class="flex flex-col gap-5 rounded-xl border border-slate-200 bg-surface-container-low px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-container font-headline text-xl font-semibold text-on-primary">
                        {{ mb_strtoupper(mb_substr((string) $reader->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="admin-label mb-1">{{ __('librarian.circulation.reader_card') }}</p>
                        <p class="truncate font-headline text-xl text-primary">{{ $reader->name }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $reader->email ?? '—' }}</p>
                    </div>
                </div>

                <dl class="grid flex-1 gap-4 sm:grid-cols-3 xl:max-w-md">
                    <div class="min-w-0">
                        <dt class="admin-label mb-1">{{ __('librarian.circulation.ticket') }}</dt>
                        <dd class="truncate text-sm font-semibold text-primary">{{ $profile?->ticket_number ?? '—' }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="admin-label mb-1">{{ __('librarian.circulation.category') }}</dt>
                        <dd class="truncate text-sm text-primary">
                            {{ $readerCategory ? __('librarian.circulation.reader_categories.'.$readerCategory) : '—' }}
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="admin-label mb-1">{{ __('librarian.circulation.reader_status') }}</dt>
                        <dd>
                            @if ($readerStatus)
                                <x-admin.status-badge
                                    :status="$readerStatusTone"
                                    :label="__('librarian.circulation.reader_statuses.'.$readerStatus)"
                                />
                            @else
                                <span class="text-sm text-slate-500">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <a class="admin-btn admin-btn-secondary shrink-0" href="{{ route('librarian.circulation.issue') }}">
                    <span class="material-symbols-outlined text-[19px]">swap_horiz</span>
                    {{ __('librarian.circulation.change_reader') }}
                </a>
            </div>
        @endif
    </section>

    @if ($reader === null)
        <section class="admin-card py-16 text-center">
            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">how_to_reg</span>
            <p class="text-sm text-slate-500">{{ __('librarian.circulation.reader_not_selected') }}</p>
        </section>
    @else
        <div class="grid gap-6 xl:grid-cols-12">
            {{-- STEP 2 — restrictions --}}
            <section class="admin-card {{ $canIssue ? 'xl:col-span-5' : 'xl:col-span-12' }}">
                <header class="mb-4 flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-container font-headline text-sm font-bold text-on-primary">2</span>
                    <h2 class="font-headline text-xl text-primary">{{ __('librarian.circulation.step_restrictions') }}</h2>
                </header>

                @if ($summary === null)
                    <p class="text-sm text-slate-500">{{ __('librarian.circulation.reader_not_selected') }}</p>
                @else
                    @php
                        $openLoans = $summary['open_loans'];
                        $usedLoans = $openLoans->count();
                        $pendingFines = $summary['pending_fines'];
                        $pendingTotal = (float) $summary['pending_fines_total'];
                    @endphp

                    <p class="admin-label">{{ __('librarian.circulation.limits') }}</p>
                    <dl class="mb-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 px-4 py-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('librarian.circulation.open_loans') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-primary">
                                {{ __('librarian.circulation.loans_used', ['used' => $usedLoans, 'max' => $summary['max_loans']]) }}
                            </dd>
                        </div>
                        <div class="rounded-xl border px-4 py-3 {{ $summary['loans_remaining'] > 0 ? 'border-slate-200' : 'border-red-200 bg-red-50' }}">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('common.status.available') }}</dt>
                            <dd class="mt-1 text-sm font-semibold {{ $summary['loans_remaining'] > 0 ? 'text-primary' : 'text-red-700' }}">
                                {{ __('librarian.circulation.loans_remaining', ['count' => $summary['loans_remaining']]) }}
                            </dd>
                        </div>
                        <div class="rounded-xl border px-4 py-3 {{ $pendingTotal > 0 ? 'border-amber-200 bg-amber-50' : 'border-slate-200' }}">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __('librarian.circulation.pending_fines') }}</dt>
                            <dd class="mt-1 text-sm font-semibold {{ $pendingTotal > 0 ? 'text-amber-800' : 'text-primary' }}">
                                {{ number_format($pendingTotal, 0, ',', ' ') }} ₸
                            </dd>
                            @if ($pendingFines->count() > 0)
                                <dd class="mt-1 text-xs text-slate-500">
                                    {{ __('librarian.fines.pending_count') }}: {{ number_format($pendingFines->count(), 0, ',', ' ') }}
                                    @can('fines.view')
                                        ·
                                        <a
                                            class="font-semibold text-secondary hover:underline"
                                            href="{{ route('librarian.fines.index', ['search' => $reader->name, 'status' => 'pending']) }}"
                                        >{{ __('common.actions.open') }}</a>
                                    @endcan
                                </dd>
                            @endif
                        </div>
                    </dl>

                    @if ($summary['blocked'])
                        <div role="alert" class="mb-3 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <span class="material-symbols-outlined text-[20px]">block</span>
                            <span class="min-w-0">
                                <strong class="block">{{ __('librarian.circulation.blocked_reader') }}</strong>
                                @if ($profile?->block_reason)
                                    <span class="mt-1 block text-xs">{{ __('librarian.circulation.block_reason') }}: {{ $profile->block_reason }}</span>
                                @endif
                            </span>
                        </div>
                    @endif

                    @if ($summary['overdue_blocked'])
                        <div role="alert" class="mb-3 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <span class="material-symbols-outlined text-[20px]">running_with_errors</span>
                            <span class="min-w-0">
                                <strong class="block">{{ __('librarian.circulation.blocked_overdue') }}</strong>
                                <span class="mt-1 block text-xs">
                                    {{ __('librarian.circulation.metrics.overdue') }}: {{ number_format((int) $summary['overdue_count'], 0, ',', ' ') }}
                                </span>
                            </span>
                        </div>
                    @endif

                    @if (! $summary['blocked'] && ! $summary['overdue_blocked'])
                        <div role="status" class="mb-3 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            <span class="material-symbols-outlined text-[20px]">verified</span>
                            <span>{{ __('librarian.circulation.no_restrictions') }}</span>
                        </div>
                    @endif

                    <p class="admin-label mt-5">{{ __('librarian.circulation.open_loans') }}</p>
                    @forelse ($openLoans as $loan)
                        @php
                            $loanOverdue = $loan->overdueDays();
                            $loanRecord = $loan->copy?->bibliographicRecord;
                        @endphp
                        <div class="mb-2 flex items-start justify-between gap-3 rounded-xl border px-4 py-3 {{ $loanOverdue > 0 ? 'border-red-200 bg-red-50' : 'border-slate-200' }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-primary" title="{{ $loanRecord?->title }}">{{ $loanRecord?->title ?? '—' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ __('librarian.circulation.due_date') }}: {{ $loan->due_at?->format('d.m.Y') ?? '—' }}
                                    @if ($loan->copy?->inventory_number)
                                        · {{ $loan->copy->inventory_number }}
                                    @endif
                                </p>
                                <p class="mt-1 text-xs {{ $loanOverdue > 0 ? 'font-semibold text-red-700' : 'text-slate-600' }}">
                                    @if ($loanOverdue > 0)
                                        {{ __('librarian.circulation.overdue_days', ['count' => $loanOverdue]) }}
                                    @else
                                        {{ __('librarian.circulation.days_left', ['count' => $loan->daysRemaining()]) }}
                                    @endif
                                </p>
                            </div>
                            @can('circulation.renew')
                                <form method="POST" action="{{ route('librarian.circulation.renew', $loan) }}" class="shrink-0">
                                    @csrf
                                    <button class="admin-btn admin-btn-secondary px-3 py-1.5 text-xs" type="submit">
                                        <span class="material-symbols-outlined text-[17px]">more_time</span>
                                        {{ __('librarian.circulation.renew') }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                            {{ __('librarian.circulation.no_open_loans') }}
                        </p>
                    @endforelse

                    <details class="mt-5 rounded-xl border border-slate-200" @if ($errors->hasAny(['category', 'status', 'block_reason'])) open @endif>
                        <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-primary">
                            <span class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[19px] text-slate-400">manage_accounts</span>
                                {{ __('librarian.circulation.reader_profile_title') }}
                            </span>
                            <span class="material-symbols-outlined text-[19px] text-slate-400">expand_more</span>
                        </summary>
                        <form method="POST" action="{{ route('librarian.circulation.reader.update', $reader) }}" class="space-y-4 border-t border-slate-100 px-4 py-4">
                            @csrf
                            @method('PATCH')

                            <p class="text-xs text-slate-500">{{ __('librarian.circulation.reader_profile_hint') }}</p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block">
                                    <span class="admin-label">{{ __('librarian.circulation.category') }}</span>
                                    <select class="admin-input" name="category" required>
                                        @foreach ($categoryLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(old('category', $readerCategory) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('category')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                                </label>

                                <label class="block">
                                    <span class="admin-label">{{ __('librarian.circulation.reader_status') }}</span>
                                    <select class="admin-input" name="status" id="reader-profile-status" required>
                                        @foreach (\App\Models\Catalog\ReaderProfile::STATUSES as $value)
                                            <option value="{{ $value }}" @selected(old('status', $readerStatus) === $value)>
                                                {{ __('librarian.circulation.reader_statuses.'.$value) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <label class="block">
                                <span class="admin-label">{{ __('librarian.circulation.block_reason') }}</span>
                                <textarea class="admin-input" name="block_reason" id="reader-profile-block-reason" rows="3" maxlength="1000">{{ old('block_reason', $profile?->block_reason) }}</textarea>
                                @error('block_reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            </label>

                            <button class="admin-btn admin-btn-primary" type="submit">
                                <span class="material-symbols-outlined text-[19px]">save</span>
                                {{ __('common.actions.save_changes') }}
                            </button>
                        </form>
                    </details>
                @endif
            </section>

            {{-- STEP 3 — scan the copy --}}
            @can('circulation.issue')
                <section class="admin-card xl:col-span-7">
                    <header class="mb-4 flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-container font-headline text-sm font-bold text-on-primary">3</span>
                        <h2 class="font-headline text-xl text-primary">{{ __('librarian.circulation.step_copy') }}</h2>
                    </header>

                    <form method="POST" action="{{ route('librarian.circulation.issue.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="reader_id" value="{{ $reader->getKey() }}">

                        <label class="block">
                            <span class="admin-label">{{ __('librarian.circulation.copy_code') }}</span>
                            <span class="relative block">
                                <span class="material-symbols-outlined pointer-events-none absolute left-3 top-[0.65rem] text-[19px] text-slate-400">barcode_scanner</span>
                                <input
                                    class="admin-input pl-10"
                                    id="copy-code"
                                    type="text"
                                    name="copy_code"
                                    value="{{ old('copy_code') }}"
                                    autocomplete="off"
                                    autofocus
                                    maxlength="64"
                                    required
                                    placeholder="{{ __('librarian.circulation.copy_code_placeholder') }}"
                                >
                            </span>
                            @error('copy_code')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            @include('librarian.partials.camera-scanner', ['targetId' => 'copy-code'])
                            <span class="mt-2 block text-xs text-slate-500">{{ __('librarian.circulation.copy_lookup_hint') }}</span>
                        </label>

                        <div id="copy-preview" aria-live="polite"></div>

                        @can('circulation.override_limits')
                            <details class="rounded-xl border border-amber-200 bg-amber-50" @if (old('override') || $errors->has('override_reason')) open @endif>
                                <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-amber-900">
                                    <span class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[19px]">gpp_maybe</span>
                                        {{ __('librarian.circulation.override_title') }}
                                    </span>
                                    <span class="material-symbols-outlined text-[19px]">expand_more</span>
                                </summary>
                                <div class="space-y-3 border-t border-amber-200 px-4 py-4">
                                    <p class="text-xs text-amber-900">{{ __('librarian.circulation.override_hint') }}</p>

                                    <label class="flex items-start gap-2 text-sm font-semibold text-amber-900">
                                        <input
                                            class="mt-0.5 h-4 w-4 rounded border-amber-300 text-secondary focus:ring-secondary"
                                            type="checkbox"
                                            id="override-toggle"
                                            name="override"
                                            value="1"
                                            @checked(old('override'))
                                        >
                                        <span>{{ __('librarian.circulation.override_checkbox') }}</span>
                                    </label>
                                    @error('override')<p class="text-xs text-red-700">{{ $message }}</p>@enderror

                                    <label class="block">
                                        <span class="admin-label">{{ __('librarian.circulation.override_reason') }}</span>
                                        <textarea class="admin-input" id="override-reason" name="override_reason" rows="3" maxlength="1000">{{ old('override_reason') }}</textarea>
                                        @error('override_reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                                    </label>
                                </div>
                            </details>
                        @endcan

                        @can('circulation.override_due_date')
                            <details class="rounded-xl border border-slate-200 p-4" @if(old('manual_due_at')) open @endif>
                                <summary class="cursor-pointer text-sm font-semibold text-primary">{{ __('librarian.circulation.manual_due_date') }}</summary>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2"><label><span class="admin-label">{{ __('librarian.circulation.due_date') }}</span><input class="admin-input" type="date" name="manual_due_at" min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addDays((int)\App\Models\Setting::valueFor('manual_due_date_max_days',30))->toDateString() }}" value="{{ old('manual_due_at') }}"></label><label><span class="admin-label">{{ __('librarian.circulation.override_reason') }}</span><textarea class="admin-input" name="due_date_reason" rows="2">{{ old('due_date_reason') }}</textarea></label></div>
                                <p class="mt-2 text-xs text-amber-700">{{ __('librarian.circulation.manual_due_warning') }}</p>
                            </details>
                        @endcan

                        <button class="admin-btn admin-btn-primary w-full sm:w-auto" type="submit">
                            <span class="material-symbols-outlined text-[19px]">task_alt</span>
                            {{ __('librarian.circulation.confirm_issue') }}
                        </button>
                    </form>
                </section>
            @endcan
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var LABELS = @json($copyJsLabels);
    var ACCESS_LABELS = @json($accessLabels);
    var CATEGORY_LABELS = @json($categoryLabels);
    var READER_LOOKUP_URL = @json(route('librarian.circulation.reader-lookup'));
    var COPY_LOOKUP_URL = @json(route('librarian.circulation.copy-lookup'));
    var ISSUE_URL = @json(route('librarian.circulation.issue'));

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null && text !== '') { node.textContent = String(text); }
        return node;
    }

    function icon(name, className) {
        var node = el('span', 'material-symbols-outlined ' + (className || ''));
        node.textContent = name;
        return node;
    }

    function statusTone(status) {
        if (status === 'available') { return 'border-emerald-200 bg-emerald-50 text-emerald-800'; }
        if (status === 'issued') { return 'border-cyan-200 bg-cyan-50 text-cyan-800'; }
        if (status === 'reserved' || status === 'in_processing' || status === 'on_display' || status === 'reserved_stock') {
            return 'border-amber-200 bg-amber-50 text-amber-800';
        }
        if (status === 'overdue' || status === 'lost' || status === 'written_off' || status === 'under_repair') {
            return 'border-red-200 bg-red-50 text-red-800';
        }
        return 'border-slate-200 bg-slate-100 text-slate-600';
    }

    function definition(term, value) {
        var wrap = el('div', 'min-w-0');
        wrap.appendChild(el('dt', 'text-[11px] font-bold uppercase tracking-wider text-slate-400', term));
        wrap.appendChild(el('dd', 'truncate text-sm text-primary', value));
        return wrap;
    }

    function notice(tone, heading, lines) {
        var classes = tone === 'danger'
            ? 'border-red-200 bg-red-50 text-red-900'
            : 'border-amber-200 bg-amber-50 text-amber-900';
        var box = el('div', 'mt-3 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm ' + classes);
        box.setAttribute('role', 'alert');
        box.appendChild(icon(tone === 'danger' ? 'error' : 'warning', 'text-[20px]'));
        var body = el('div', 'min-w-0');
        body.appendChild(el('strong', 'block', heading));
        (lines || []).forEach(function (line) {
            if (line) { body.appendChild(el('span', 'mt-0.5 block text-xs', line)); }
        });
        box.appendChild(body);
        return box;
    }

    function emptyState(message) {
        var box = el('div', 'flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600');
        box.appendChild(icon('search_off', 'text-[20px] text-slate-400'));
        box.appendChild(el('span', '', message));
        return box;
    }

    function buildCopyCard(copy) {
        var card = el('div', 'rounded-xl border border-slate-200 bg-white p-4');

        var head = el('div', 'flex items-start justify-between gap-3');
        var titleWrap = el('div', 'min-w-0');
        titleWrap.appendChild(el('p', 'admin-label mb-1', LABELS.found));
        titleWrap.appendChild(el('p', 'truncate font-headline text-lg text-primary', copy.title || '—'));
        titleWrap.appendChild(el('p', 'truncate text-xs text-slate-500', copy.author || '—'));
        head.appendChild(titleWrap);
        head.appendChild(el(
            'span',
            'shrink-0 inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ' + statusTone(copy.status),
            copy.status_label || copy.status || '—'
        ));
        card.appendChild(head);

        var grid = el('dl', 'mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4');
        grid.appendChild(definition(LABELS.inventory_number, copy.inventory_number || '—'));
        grid.appendChild(definition(LABELS.barcode, copy.barcode || '—'));
        grid.appendChild(definition(LABELS.branch, copy.branch || '—'));
        grid.appendChild(definition(
            LABELS.access_restriction,
            ACCESS_LABELS[copy.access_restriction] || copy.access_restriction || '—'
        ));
        card.appendChild(grid);

        if (copy.active_loan) {
            var loanLines = [];
            if (copy.active_loan.reader) { loanLines.push(copy.active_loan.reader); }
            if (copy.active_loan.due_at) { loanLines.push(LABELS.due_date + ': ' + copy.active_loan.due_at); }
            if (copy.active_loan.overdue_days > 0) {
                loanLines.push(LABELS.overdue_days.replace(':count', String(copy.active_loan.overdue_days)));
            }
            card.appendChild(notice('danger', LABELS.current_loan, loanLines));
        }

        if (copy.reserved_for) {
            card.appendChild(notice('warning', LABELS.reserved_for, [copy.reserved_for]));
        }

        return card;
    }

    /* Step 1 — reader lookup */
    var readerInput = document.getElementById('reader-search');
    var readerResults = document.getElementById('reader-results');

    if (readerInput && readerResults) {
        var readerTimer = null;
        var readerController = null;

        var closeResults = function () {
            readerResults.classList.add('hidden');
            readerResults.replaceChildren();
        };

        var renderReaders = function (rows) {
            readerResults.replaceChildren();

            if (rows.length === 0) {
                readerResults.appendChild(el('li', 'px-4 py-3 text-sm text-slate-500', LABELS.no_results));
                readerResults.classList.remove('hidden');
                return;
            }

            rows.forEach(function (row) {
                var item = el('li', '');
                var button = el('button', 'flex w-full flex-col items-start gap-0.5 px-4 py-2.5 text-left hover:bg-surface-container-low');
                button.type = 'button';
                button.appendChild(el('span', 'w-full truncate text-sm font-semibold text-primary', row.name || '—'));

                var meta = [];
                if (row.ticket) { meta.push(LABELS.ticket + ': ' + row.ticket); }
                if (row.category && CATEGORY_LABELS[row.category]) { meta.push(CATEGORY_LABELS[row.category]); }
                if (row.email) { meta.push(row.email); }
                button.appendChild(el('span', 'w-full truncate text-xs text-slate-500', meta.join(' · ')));

                button.addEventListener('click', function () {
                    window.location.href = ISSUE_URL + '?reader=' + encodeURIComponent(row.id);
                });

                item.appendChild(button);
                readerResults.appendChild(item);
            });

            readerResults.classList.remove('hidden');
        };

        var searchReaders = function () {
            var term = readerInput.value.trim();
            if (term.length < 2) {
                closeResults();
                return;
            }

            if (readerController) { readerController.abort(); }
            readerController = new AbortController();

            fetch(READER_LOOKUP_URL + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: readerController.signal
            })
                .then(function (response) { return response.ok ? response.json() : { data: [] }; })
                .then(function (payload) { renderReaders(Array.isArray(payload.data) ? payload.data : []); })
                .catch(function () { /* aborted or offline: keep the previous state */ });
        };

        readerInput.addEventListener('input', function () {
            window.clearTimeout(readerTimer);
            readerTimer = window.setTimeout(searchReaders, 250);
        });

        readerInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeResults(); }
        });

        document.addEventListener('click', function (event) {
            var wrap = document.getElementById('reader-search-wrap');
            if (wrap && !wrap.contains(event.target)) { closeResults(); }
        });
    }

    /* Step 3 — copy lookup */
    var copyInput = document.getElementById('copy-code');
    var copyPreview = document.getElementById('copy-preview');

    if (copyInput && copyPreview) {
        var copyTimer = null;
        var copyController = null;

        var lookupCopy = function () {
            var term = copyInput.value.trim();
            if (term === '') {
                copyPreview.replaceChildren();
                return;
            }

            if (copyController) { copyController.abort(); }
            copyController = new AbortController();

            fetch(COPY_LOOKUP_URL + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: copyController.signal
            })
                .then(function (response) { return response.ok ? response.json() : { data: null }; })
                .then(function (payload) {
                    copyPreview.replaceChildren(
                        payload && payload.data ? buildCopyCard(payload.data) : emptyState(LABELS.not_found)
                    );
                })
                .catch(function () { /* aborted or offline: keep the previous state */ });
        };

        copyInput.addEventListener('input', function () {
            window.clearTimeout(copyTimer);
            copyTimer = window.setTimeout(lookupCopy, 250);
        });

        if (copyInput.value.trim() !== '') { lookupCopy(); }
    }

    /* Reader profile — a reason is mandatory unless the reader stays active */
    var statusSelect = document.getElementById('reader-profile-status');
    var blockReason = document.getElementById('reader-profile-block-reason');

    if (statusSelect && blockReason) {
        var syncBlockReason = function () {
            blockReason.required = statusSelect.value !== 'active';
        };
        statusSelect.addEventListener('change', syncBlockReason);
        syncBlockReason();
    }

    /* Override — a reason is mandatory once the checkbox is ticked */
    var overrideToggle = document.getElementById('override-toggle');
    var overrideReason = document.getElementById('override-reason');

    if (overrideToggle && overrideReason) {
        var syncOverride = function () {
            overrideReason.required = overrideToggle.checked;
        };
        overrideToggle.addEventListener('change', syncOverride);
        syncOverride();
    }
})();
</script>
@endpush
