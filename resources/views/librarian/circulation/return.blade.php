@php
    $copyJsLabels = [
        'not_found' => __('librarian.circulation.copy_not_found_js'),
        'found' => __('librarian.circulation.copy_found'),
        'inventory_number' => __('librarian.copies.fields.inventory_number'),
        'barcode' => __('librarian.copies.fields.barcode'),
        'branch' => __('librarian.copies.fields.branch'),
        'access_restriction' => __('librarian.copies.fields.access_restriction'),
        'current_loan' => __('librarian.copies.current_loan'),
        'no_current_loan' => __('librarian.copies.no_current_loan'),
        'reserved_for' => __('librarian.copies.reserved_for'),
        'reader' => __('librarian.reservations.reader'),
        'due_date' => __('librarian.circulation.due_date'),
        'overdue_days' => __('librarian.circulation.overdue_days', ['count' => ':count']),
    ];

    $accessLabels = [];
    foreach (\App\Models\Catalog\BookCopy::ACCESS_RESTRICTIONS as $restriction) {
        $accessLabels[$restriction] = __('librarian.copies.access_restrictions.'.$restriction);
    }

    $incidents = [
        'none' => ['label' => __('librarian.circulation.incident_none'), 'icon' => 'check_circle'],
        'damaged' => ['label' => __('librarian.circulation.incident_damaged'), 'icon' => 'report'],
        'lost' => ['label' => __('librarian.circulation.incident_lost'), 'icon' => 'help_center'],
    ];
    $selectedIncident = (string) old('incident', 'none');
@endphp

@extends('layouts.librarian')

@section('title', __('librarian.circulation.return_title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.circulation.eyebrow')"
        :title="__('librarian.circulation.return_title')"
        :subtitle="__('librarian.circulation.return_subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
        @can('circulation.issue')
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation.issue') }}">
                <span class="material-symbols-outlined text-[19px]">outbox</span>
                {{ __('librarian.circulation.issue_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <form method="POST" action="{{ route('librarian.circulation.return.store') }}" class="grid gap-6 xl:grid-cols-12">
        @csrf

        <section class="admin-card space-y-5 xl:col-span-7">
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
        </section>

        <section class="admin-card space-y-5 xl:col-span-5">
            <label class="block">
                <span class="admin-label">{{ __('librarian.circulation.condition_on_return') }}</span>
                <select class="admin-input" name="condition_on_return">
                    @foreach (\App\Models\Catalog\Loan::RETURN_CONDITIONS as $condition)
                        <option value="{{ $condition }}" @selected(old('condition_on_return') === $condition)>
                            {{ __('librarian.circulation.return_conditions.'.$condition) }}
                        </option>
                    @endforeach
                </select>
                @error('condition_on_return')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </label>

            <fieldset>
                <legend class="admin-label">{{ __('librarian.circulation.incident') }}</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($incidents as $value => $incident)
                        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-primary hover:bg-surface-container-low">
                            <input
                                class="incident-radio h-4 w-4 border-slate-300 text-secondary focus:ring-secondary"
                                type="radio"
                                name="incident"
                                value="{{ $value }}"
                                @checked($selectedIncident === $value)
                                required
                            >
                            <span class="material-symbols-outlined text-[19px] text-slate-400">{{ $incident['icon'] }}</span>
                            <span class="truncate">{{ $incident['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('incident')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </fieldset>

            <div id="fine-block" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 {{ $selectedIncident === 'none' ? 'hidden' : '' }}">
                <label class="block">
                    <span class="admin-label">{{ __('librarian.circulation.incident_fine') }}</span>
                    <input
                        class="admin-input"
                        id="fine-amount"
                        type="number"
                        name="fine_amount"
                        value="{{ old('fine_amount') }}"
                        min="0"
                        max="10000000"
                        step="1"
                        inputmode="decimal"
                    >
                    @error('fine_amount')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>
                <p class="mt-2 text-xs text-amber-900">{{ __('librarian.circulation.incident_hint') }}</p>
            </div>

            <details id="incident-case-block" class="rounded-xl border border-slate-200 bg-surface-container-low p-4 {{ $selectedIncident === 'none' ? 'hidden' : '' }}" @if($selectedIncident !== 'none') open @endif>
                <summary class="flex cursor-pointer items-center justify-between font-semibold text-primary">
                    <span>{{ __('incidents.return.case_details') }}</span>
                    <span class="material-symbols-outlined">expand_more</span>
                </summary>
                <div class="mt-4 space-y-4">
                    <div id="damage-fields" class="space-y-4 {{ $selectedIncident !== 'damaged' ? 'hidden' : '' }}">
                        <label class="block">
                            <span class="admin-label">{{ __('incidents.fields.damage_severity') }}</span>
                            <select class="admin-input" name="damage_severity">
                                <option value="">—</option>
                                @foreach (\App\Models\Catalog\CirculationIncidentCase::DAMAGE_SEVERITIES as $severity)
                                    <option value="{{ $severity }}" @selected(old('damage_severity') === $severity)>{{ __('incidents.damage_severities.'.$severity) }}</option>
                                @endforeach
                            </select>
                            @error('damage_severity')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </label>
                        <label class="block">
                            <span class="admin-label">{{ __('incidents.fields.damage_description') }}</span>
                            <textarea class="admin-input" name="damage_description" rows="3" maxlength="4000">{{ old('damage_description') }}</textarea>
                            @error('damage_description')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </label>
                        <label class="block">
                            <span class="admin-label">{{ __('incidents.fields.preliminary_action') }}</span>
                            <select class="admin-input" name="preliminary_action">
                                <option value="">—</option>
                                @foreach (\App\Models\Catalog\CirculationIncidentCase::PRELIMINARY_ACTIONS as $action)
                                    <option value="{{ $action }}" @selected(old('preliminary_action') === $action)>{{ __('incidents.preliminary_actions.'.$action) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="flex items-start gap-3 text-sm">
                        <input class="mt-1 rounded border-slate-300 text-secondary" type="checkbox" name="open_replacement_case" value="1" @checked(old('open_replacement_case', $selectedIncident === 'lost'))>
                        <span><strong>{{ __('incidents.return.require_replacement') }}</strong><br><span class="text-xs text-slate-500">{{ __('incidents.return.require_replacement_hint') }}</span></span>
                    </label>
                </div>
            </details>

            <label class="block">
                <span class="admin-label">{{ __('librarian.circulation.return_notes') }}</span>
                <textarea class="admin-input" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                @error('notes')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </label>

            <button class="admin-btn admin-btn-primary w-full" type="submit">
                <span class="material-symbols-outlined text-[19px]">task_alt</span>
                {{ __('librarian.circulation.confirm_return') }}
            </button>
        </section>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var LABELS = @json($copyJsLabels);
    var ACCESS_LABELS = @json($accessLabels);
    var COPY_LOOKUP_URL = @json(route('librarian.circulation.copy-lookup'));

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

    function emptyState(message) {
        var box = el('div', 'flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600');
        box.appendChild(icon('search_off', 'text-[20px] text-slate-400'));
        box.appendChild(el('span', '', message));
        return box;
    }

    /* The borrower drives the overdue fine, so the active loan is the headline
       of the return preview rather than a footnote. */
    function buildLoanPanel(loan) {
        var overdue = Number(loan.overdue_days || 0);
        var classes = overdue > 0
            ? 'border-red-200 bg-red-50 text-red-900'
            : 'border-slate-200 bg-surface-container-low text-primary';
        var panel = el('div', 'mt-4 rounded-xl border px-4 py-3 ' + classes);

        var head = el('div', 'flex items-start justify-between gap-3');
        var left = el('div', 'min-w-0');
        left.appendChild(el('p', 'admin-label mb-1', LABELS.current_loan));
        left.appendChild(el('p', 'truncate font-headline text-xl', loan.reader || '—'));
        left.appendChild(el('p', 'mt-0.5 text-xs', LABELS.due_date + ': ' + (loan.due_at || '—')));
        head.appendChild(left);

        if (overdue > 0) {
            head.appendChild(el(
                'span',
                'shrink-0 inline-flex items-center rounded-full border border-red-300 bg-white px-3 py-1.5 text-sm font-bold text-red-800',
                LABELS.overdue_days.replace(':count', String(overdue))
            ));
        }

        panel.appendChild(head);
        return panel;
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
            card.appendChild(buildLoanPanel(copy.active_loan));
        } else {
            card.appendChild(emptyState(LABELS.no_current_loan));
            card.lastChild.classList.add('mt-4');
        }

        if (copy.reserved_for) {
            var reserved = el('div', 'mt-3 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900');
            reserved.setAttribute('role', 'alert');
            reserved.appendChild(icon('warning', 'text-[20px]'));
            var body = el('div', 'min-w-0');
            body.appendChild(el('strong', 'block', LABELS.reserved_for));
            body.appendChild(el('span', 'mt-0.5 block text-xs', copy.reserved_for));
            reserved.appendChild(body);
            card.appendChild(reserved);
        }

        return card;
    }

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

    /* The compensation field only applies to damage or loss. */
    var fineBlock = document.getElementById('fine-block');
    var fineAmount = document.getElementById('fine-amount');
    var incidentCaseBlock = document.getElementById('incident-case-block');
    var damageFields = document.getElementById('damage-fields');
    var incidentRadios = document.querySelectorAll('.incident-radio');

    if (fineBlock && incidentRadios.length > 0) {
        var syncFine = function () {
            var selected = document.querySelector('.incident-radio:checked');
            var withIncident = selected !== null && selected.value !== 'none';
            fineBlock.classList.toggle('hidden', !withIncident);
            if (incidentCaseBlock) { incidentCaseBlock.classList.toggle('hidden', !withIncident); }
            if (damageFields) { damageFields.classList.toggle('hidden', selected === null || selected.value !== 'damaged'); }
            if (fineAmount && !withIncident) { fineAmount.value = ''; }
        };

        incidentRadios.forEach(function (radio) {
            radio.addEventListener('change', syncFine);
        });

        syncFine();
    }
})();
</script>
@endpush
