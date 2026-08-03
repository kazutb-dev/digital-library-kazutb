@extends('layouts.librarian')

@section('title', __('librarian.visits.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.visits.eyebrow')"
        :title="__('librarian.visits.title')"
        :subtitle="__('librarian.visits.subtitle')"
    >
        @can('reports.view_ops')
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.index') }}#visits">
                <span class="material-symbols-outlined text-[19px]">bar_chart</span>
                {{ __('librarian.visits.open_report') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['label' => __('librarian.visits.metrics.today'), 'value' => $todayVisits, 'icon' => 'today'],
            ['label' => __('librarian.visits.metrics.today_readers'), 'value' => $todayReaders, 'icon' => 'group'],
            ['label' => __('librarian.visits.metrics.week'), 'value' => $weekVisits, 'icon' => 'date_range'],
        ] as $metric)
            <div class="admin-card flex items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-secondary">
                    <span class="material-symbols-outlined">{{ $metric['icon'] }}</span>
                </span>
                <span>
                    <strong class="block font-headline text-3xl text-primary">{{ number_format((int) $metric['value'], 0, ',', ' ') }}</strong>
                    <small class="text-xs uppercase tracking-wider text-slate-500">{{ $metric['label'] }}</small>
                </span>
            </div>
        @endforeach
    </div>

    {{-- §9.4 — the scan point. A physical turnstile or kiosk can post to the
         same endpoint later; nothing here assumes a keyboard. --}}
    <form method="POST" action="{{ route('librarian.visits.store') }}" class="admin-card mb-6" id="visit-form">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="sm:col-span-2">
                <span class="admin-label">{{ __('librarian.visits.code') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">badge</span>
                    <input
                        class="admin-input pl-10 font-mono"
                        id="visit-code"
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        maxlength="64"
                        autocomplete="off"
                        autofocus
                        required
                        placeholder="{{ __('librarian.visits.code_placeholder') }}"
                    >
                </span>
                <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.visits.code_help') }}</span>
                @error('code')
                    <span class="mt-1 block text-xs font-semibold text-red-700">{{ $message }}</span>
                @enderror
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.visits.branch') }}</span>
                <select class="admin-input" name="branch_id">
                    <option value="">{{ __('librarian.visits.branch_unspecified') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end">
                <button class="admin-btn admin-btn-primary w-full" type="submit">
                    <span class="material-symbols-outlined text-[19px]">how_to_reg</span>
                    {{ __('librarian.visits.record') }}
                </button>
            </div>
        </div>

        <p class="mt-3 hidden rounded-xl border px-4 py-3 text-sm" id="visit-preview" role="status" aria-live="polite"></p>

        <p class="mt-3 text-xs text-slate-500">
            {{ __('librarian.visits.dedupe_hint', ['minutes' => $dedupeMinutes]) }}
        </p>
    </form>

    <section class="admin-card overflow-hidden p-0">
        <h2 class="border-b border-slate-100 px-6 py-4 font-headline text-2xl text-primary">{{ __('librarian.visits.recent') }}</h2>
        <div class="overflow-x-auto">
            <table class="admin-table min-w-200">
                <thead>
                    <tr>
                        <th>{{ __('librarian.visits.columns.reader') }}</th>
                        <th>{{ __('librarian.visits.columns.scanned_at') }}</th>
                        <th>{{ __('librarian.visits.columns.branch') }}</th>
                        <th>{{ __('librarian.visits.columns.source') }}</th>
                        <th>{{ __('librarian.visits.columns.scanned_by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $visit)
                        <tr>
                            <td>
                                <strong class="block text-sm text-primary">{{ $visit->reader?->name ?? '—' }}</strong>
                                <span class="block font-mono text-xs text-slate-500">
                                    {{ $visit->reader?->readerProfile?->barcode ?: $visit->reader?->readerProfile?->ticket_number ?: '—' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ $visit->scanned_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td class="text-slate-600">{{ $visit->branch?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-slate-600">{{ __('librarian.visits.sources.'.$visit->source) }}</td>
                            <td class="text-slate-600">{{ $visit->scannedBy?->name ?? __('librarian.visits.unattended') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">sensor_door</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.visits.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$recent" />
    </section>

    <script>
        // Confirms whose card was scanned before the visit is written, so a
        // mistyped code is caught at the door rather than in the report.
        (function () {
            const input = document.getElementById('visit-code');
            const preview = document.getElementById('visit-preview');
            const lookupUrl = @json(route('librarian.visits.lookup'));
            const labels = {
                found: @json(__('librarian.visits.preview_found')),
                notFound: @json(__('librarian.visits.reader_not_found')),
            };
            if (!input || !preview) { return; }

            let timer = null;

            function show(html, tone) {
                preview.className = 'mt-3 rounded-xl border px-4 py-3 text-sm ' + tone;
                preview.innerHTML = html;
            }

            function hide() {
                preview.className = 'mt-3 hidden rounded-xl border px-4 py-3 text-sm';
                preview.textContent = '';
            }

            input.addEventListener('input', function () {
                const code = input.value.trim();
                window.clearTimeout(timer);
                if (code.length < 3) { hide(); return; }

                timer = window.setTimeout(function () {
                    fetch(lookupUrl + '?code=' + encodeURIComponent(code), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then(function (response) { return response.ok ? response.json() : { data: null }; })
                        .then(function (payload) {
                            const reader = payload.data;
                            if (!reader) {
                                show(labels.notFound, 'border-amber-200 bg-amber-50 text-amber-900');
                                return;
                            }
                            const bits = [reader.ticket, reader.status_label].filter(Boolean).join(' · ');
                            const name = document.createElement('strong');
                            name.textContent = reader.name;
                            const meta = document.createElement('span');
                            meta.className = 'mt-0.5 block text-xs';
                            meta.textContent = bits;
                            preview.className = 'mt-3 rounded-xl border px-4 py-3 text-sm border-emerald-200 bg-emerald-50 text-emerald-900';
                            preview.textContent = labels.found + ' ';
                            preview.appendChild(name);
                            preview.appendChild(meta);
                        })
                        .catch(function () { hide(); });
                }, 250);
            });
        })();
    </script>
@endsection
