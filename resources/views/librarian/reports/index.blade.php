@extends('layouts.librarian')

@section('title', __('librarian.reports.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $periodParams = array_filter(request()->only(['date_from', 'date_to']));

        $exportUrl = static fn (string $type): string => route(
            'librarian.reports.export',
            array_merge($periodParams, ['type' => $type]),
        );

        $maxIssued = (int) $loanDynamics->max('issued');
        $dynamicsDays = $loanDynamics->count();
        $showBarValues = $dynamicsDays > 0 && $dynamicsDays <= 20;

        $totalCards = [
            ['key' => 'issued', 'icon' => 'outbound', 'value' => number_format((int) ($totals['issued'] ?? 0), 0, ',', ' ')],
            ['key' => 'returned', 'icon' => 'assignment_return', 'value' => number_format((int) ($totals['returned'] ?? 0), 0, ',', ' ')],
            ['key' => 'reservations', 'icon' => 'bookmark_manager', 'value' => number_format((int) ($totals['reservations'] ?? 0), 0, ',', ' ')],
            ['key' => 'fines_charged', 'icon' => 'payments', 'value' => number_format((float) ($totals['fines_charged'] ?? 0), 0, ',', ' ').' ₸'],
        ];
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.reports.eyebrow')"
        :title="__('librarian.reports.title')"
        :subtitle="__('librarian.reports.subtitle')"
    />

    <form method="GET" action="{{ route('librarian.reports.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="report-date-from" class="admin-label">{{ __('common.filters.date_from') }}</label>
                <input
                    id="report-date-from"
                    class="admin-input"
                    type="date"
                    name="date_from"
                    value="{{ old('date_from', $from->format('Y-m-d')) }}"
                >
                @error('date_from')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="report-date-to" class="admin-label">{{ __('common.filters.date_to') }}</label>
                <input
                    id="report-date-to"
                    class="admin-input"
                    type="date"
                    name="date_to"
                    value="{{ old('date_to', $to->format('Y-m-d')) }}"
                >
                @error('date_to')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="admin-btn admin-btn-primary flex-1">
                    <span class="material-symbols-outlined text-[19px]">query_stats</span>
                    {{ __('librarian.reports.apply') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('librarian.reports.index') }}"
                    title="{{ __('common.actions.clear_filters') }}"
                    aria-label="{{ __('common.actions.clear_filters') }}"
                >
                    <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
                </a>
            </div>

            <div class="flex items-end">
                <p class="text-xs leading-5 text-slate-500">
                    <span class="block font-bold uppercase tracking-[.055em] text-on-surface-variant">{{ __('librarian.reports.period') }}</span>
                    <time datetime="{{ $from->toDateString() }}">{{ $from->format('d.m.Y') }}</time>
                    —
                    <time datetime="{{ $to->toDateString() }}">{{ $to->format('d.m.Y') }}</time>
                </p>
            </div>
        </div>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($totalCards as $card)
            <article class="admin-card flex items-start justify-between gap-4 p-5">
                <div class="min-w-0">
                    <p class="admin-label mb-2">{{ __('librarian.reports.totals.'.$card['key']) }}</p>
                    <p class="font-headline text-3xl leading-none text-primary">{{ $card['value'] }}</p>
                </div>
                <span class="material-symbols-outlined shrink-0 text-[26px] text-slate-300">{{ $card['icon'] }}</span>
            </article>
        @endforeach
    </div>
    @can('reports.export')
    <div class="mb-6 flex flex-wrap gap-2"><a class="admin-btn admin-btn-secondary" href="{{ $exportUrl('reservations') }}">CSV · {{ __('librarian.nav.reservations') }}</a><a class="admin-btn admin-btn-secondary" href="{{ $exportUrl('circulation') }}">CSV · {{ __('librarian.nav.circulation') }}</a><a class="admin-btn admin-btn-secondary" href="{{ $exportUrl('inventory') }}">CSV · {{ __('librarian.nav.inventory') }}</a></div>
    @endcan

    {{-- Catalogue quality at a glance for leadership: how much is open and how
         fast it is moving. Read-only — fixing happens in Data Quality. --}}
    <article class="admin-card mb-6 flex flex-wrap items-center justify-between gap-6">
        <div class="min-w-0">
            <p class="admin-label mb-2">{{ __('librarian.reports.catalog_quality.title') }}</p>
            <p class="text-sm leading-6 text-on-surface-variant">
                {{ __('librarian.reports.catalog_quality.summary', [
                    'open' => number_format((int) ($catalogQuality['open'] ?? 0), 0, ',', ' '),
                    'resolved' => number_format((int) ($catalogQuality['resolved_week'] ?? 0), 0, ',', ' '),
                ]) }}
            </p>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-right">
                <div class="font-headline text-3xl leading-none text-primary">{{ number_format((int) ($catalogQuality['open'] ?? 0), 0, ',', ' ') }}</div>
                <div class="mt-1 text-xs uppercase tracking-[.14em] text-outline">{{ __('librarian.reports.catalog_quality.open') }}</div>
            </div>
            <div class="text-right">
                <div class="font-headline text-3xl leading-none text-secondary">−{{ number_format((int) ($catalogQuality['resolved_week'] ?? 0), 0, ',', ' ') }}</div>
                <div class="mt-1 text-xs uppercase tracking-[.14em] text-outline">{{ __('librarian.reports.catalog_quality.week') }}</div>
            </div>
            @can('data_cleanup.access')
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-cleanup') }}">
                    <span class="material-symbols-outlined text-[19px]">rule</span>
                    {{ __('librarian.nav.data_cleanup') }}
                </a>
            @endcan
        </div>
    </article>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="admin-card overflow-hidden p-0 xl:col-span-2" data-report="udc-fund">
            <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-headline text-2xl leading-tight text-primary">Фонд по УДК-разделам</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Реальное количество библиографических записей и физических экземпляров по десяти основным классам.</p>
                </div>
                @can('reports.export')
                    <a class="admin-btn admin-btn-secondary shrink-0 whitespace-nowrap" href="{{ $exportUrl('udc-fund') }}">
                        <span class="material-symbols-outlined text-[19px]">download</span>
                        {{ __('librarian.reports.export_csv') }}
                    </a>
                @endcan
            </header>
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[680px]">
                    <thead>
                        <tr>
                            <th>Класс</th>
                            <th>Описание</th>
                            <th>Направление</th>
                            <th class="text-right">Записи</th>
                            <th class="text-right">Экземпляры</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($udcFund as $row)
                            <tr>
                                <td class="font-mono font-bold text-primary">{{ $row->code }}</td>
                                <td>{{ $row->description }}</td>
                                <td>{{ $row->department ?: '—' }}</td>
                                <td class="text-right font-semibold">{{ number_format($row->records, 0, ',', ' ') }}</td>
                                <td class="text-right font-semibold">{{ number_format($row->copies, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card overflow-hidden p-0">
            <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-headline text-2xl leading-tight text-primary">{{ __('librarian.reports.popular') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('librarian.reports.popular_hint') }}</p>
                </div>
                @can('reports.export')
                    <a class="admin-btn admin-btn-secondary shrink-0 whitespace-nowrap" href="{{ $exportUrl('popular') }}">
                        <span class="material-symbols-outlined text-[19px]">download</span>
                        {{ __('librarian.reports.export_csv') }}
                    </a>
                @endcan
            </header>

            <div class="overflow-x-auto">
                <table class="admin-table min-w-[520px]">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>{{ __('librarian.reports.columns.title') }}</th>
                            <th>{{ __('librarian.reports.columns.author') }}</th>
                            <th class="text-right">{{ __('librarian.reports.columns.issues') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($popular as $record)
                            <tr>
                                <td class="font-headline text-lg text-slate-400">{{ $loop->iteration }}</td>
                                <td>
                                    @can('catalog.edit_record')
                                        <a class="font-semibold text-primary hover:text-secondary" href="{{ route('librarian.catalog.edit', $record->id) }}">
                                            {{ $record->title }}
                                        </a>
                                    @else
                                        <span class="font-semibold text-primary">{{ $record->title }}</span>
                                    @endcan
                                </td>
                                <td class="text-slate-600">{{ $record->primary_author ?: '—' }}</td>
                                <td class="text-right font-bold text-primary">{{ number_format((int) $record->issues, 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-14 text-center">
                                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">local_library</span>
                                    <span class="text-sm text-slate-500">{{ __('librarian.reports.empty') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card overflow-hidden p-0">
            <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="font-headline text-2xl leading-tight text-primary">{{ __('librarian.reports.fund_usage') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('librarian.reports.fund_usage_hint') }}</p>
                </div>
                @can('reports.export')
                    <a class="admin-btn admin-btn-secondary shrink-0 whitespace-nowrap" href="{{ $exportUrl('fund-usage') }}">
                        <span class="material-symbols-outlined text-[19px]">download</span>
                        {{ __('librarian.reports.export_csv') }}
                    </a>
                @endcan
            </header>

            <div class="overflow-x-auto">
                <table class="admin-table min-w-[520px]">
                    <thead>
                        <tr>
                            <th>{{ __('librarian.reports.columns.fund') }}</th>
                            <th class="text-right">{{ __('librarian.reports.columns.copies') }}</th>
                            <th>{{ __('librarian.reports.columns.on_loan') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fundUsage as $fundRow)
                            @php
                                $fundCopies = (int) $fundRow->copies;
                                $fundOnLoan = (int) $fundRow->on_loan;
                                $fundShare = $fundCopies > 0 ? (int) min(100, round($fundOnLoan / $fundCopies * 100)) : 0;
                            @endphp
                            <tr>
                                <td class="font-semibold text-primary">{{ $fundRow->fund ?: '—' }}</td>
                                <td class="text-right text-slate-600">{{ number_format($fundCopies, 0, ',', ' ') }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="w-14 shrink-0 font-bold text-primary">{{ number_format($fundOnLoan, 0, ',', ' ') }}</span>
                                        <span class="h-2 w-full min-w-24 max-w-40 overflow-hidden rounded-full bg-slate-100" role="presentation">
                                            <span class="block h-2 rounded-full bg-secondary" style="width: {{ $fundShare }}%"></span>
                                        </span>
                                        <span class="w-10 shrink-0 text-right text-xs font-semibold text-slate-500">{{ $fundShare }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-14 text-center">
                                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">inventory_2</span>
                                    <span class="text-sm text-slate-500">{{ __('librarian.reports.empty') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="admin-card mt-6 overflow-hidden p-0">
        <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-headline text-2xl leading-tight text-primary">{{ __('librarian.reports.dynamics') }}</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('librarian.reports.dynamics_hint') }}</p>
            </div>
            @can('reports.export')
                <a class="admin-btn admin-btn-secondary shrink-0 whitespace-nowrap" href="{{ $exportUrl('dynamics') }}">
                    <span class="material-symbols-outlined text-[19px]">download</span>
                    {{ __('librarian.reports.export_csv') }}
                </a>
            @endcan
        </header>

        @if ($dynamicsDays > 0)
            <div class="flex gap-4 p-6">
                <div class="flex w-10 shrink-0 flex-col justify-between text-right text-[10px] font-bold text-slate-400" style="height: 16rem">
                    <span>{{ number_format($maxIssued, 0, ',', ' ') }}</span>
                    <span>{{ number_format((int) round($maxIssued / 2), 0, ',', ' ') }}</span>
                    <span>0</span>
                </div>

                <div class="min-w-0 flex-1 overflow-x-auto pb-1">
                    <div class="flex items-end gap-1 border-b border-l border-slate-200 pl-1" style="min-width: {{ max(320, $dynamicsDays * 26) }}px">
                        @foreach ($loanDynamics as $point)
                            @php
                                $pointDay = \Illuminate\Support\Carbon::parse($point->day);
                                $pointIssued = (int) $point->issued;
                                $barHeight = $maxIssued > 0 ? max(2, (int) round($pointIssued / $maxIssued * 100)) : 2;
                                $barTitle = $pointDay->format('d.m.Y').' — '.__('librarian.reports.columns.issued').': '.$pointIssued;
                            @endphp
                            <div class="flex flex-1 flex-col items-center" style="min-width: 18px">
                                @if ($showBarValues)
                                    <span class="mb-1 text-[10px] font-bold leading-none text-slate-500">{{ $pointIssued }}</span>
                                @endif
                                <span class="flex w-full items-end" style="height: 16rem">
                                    <span
                                        class="block w-full rounded-t bg-secondary/80 transition-colors hover:bg-secondary"
                                        style="height: {{ $barHeight }}%"
                                        title="{{ $barTitle }}"
                                        aria-label="{{ $barTitle }}"
                                        role="img"
                                    ></span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-1 pl-1 pt-2" style="min-width: {{ max(320, $dynamicsDays * 26) }}px">
                        @foreach ($loanDynamics as $point)
                            <div class="flex flex-1 justify-center" style="min-width: 18px">
                                <time
                                    class="text-[10px] leading-none text-slate-500"
                                    style="writing-mode: vertical-rl"
                                    datetime="{{ $point->day }}"
                                >{{ \Illuminate\Support\Carbon::parse($point->day)->format('d.m') }}</time>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="py-14 text-center">
                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">bar_chart</span>
                <span class="text-sm text-slate-500">{{ __('librarian.reports.empty') }}</span>
            </div>
        @endif
    </section>

    <section class="admin-card mt-6 overflow-hidden p-0">
        <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-headline text-2xl leading-tight text-primary">{{ __('librarian.reports.acquisitions') }}</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('librarian.reports.acquisitions_hint') }}</p>
            </div>
            @can('reports.export')
                <a class="admin-btn admin-btn-secondary shrink-0 whitespace-nowrap" href="{{ $exportUrl('acquisitions') }}">
                    <span class="material-symbols-outlined text-[19px]">download</span>
                    {{ __('librarian.reports.export_csv') }}
                </a>
            @endcan
        </header>

        <div class="overflow-x-auto">
            <table class="admin-table min-w-[560px]">
                <thead>
                    <tr>
                        <th>{{ __('librarian.reports.columns.source') }}</th>
                        <th class="text-right">{{ __('librarian.reports.columns.copies') }}</th>
                        <th class="text-right">{{ __('librarian.reports.columns.total_price') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($acquisitions as $acquisition)
                        <tr>
                            <td class="font-semibold text-primary">{{ $acquisition->source ?: '—' }}</td>
                            <td class="text-right text-slate-600">{{ number_format((int) $acquisition->copies, 0, ',', ' ') }}</td>
                            <td class="whitespace-nowrap text-right font-bold text-primary">{{ number_format((float) $acquisition->total_price, 0, ',', ' ') }} ₸</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-14 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">local_shipping</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.reports.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{-- §9.4 attendance. ДИР §2.2 promises library leadership visit figures
         alongside issues and returns, so this sits in the shared reports
         module rather than a director-only screen. --}}
    <section class="admin-card mt-6 overflow-hidden p-0" id="visits" data-report="visits">
        <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-headline text-2xl leading-tight text-primary">{{ __('librarian.reports.visits') }}</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ __('librarian.reports.visits_hint') }}</p>
            </div>
            @can('reports.export')
                <a class="admin-btn admin-btn-secondary shrink-0 whitespace-nowrap" href="{{ $exportUrl('visits') }}">
                    <span class="material-symbols-outlined text-[19px]">download</span>
                    {{ __('librarian.reports.export_csv') }}
                </a>
            @endcan
        </header>

        <div class="grid gap-4 border-b border-slate-100 p-6 sm:grid-cols-3">
            @foreach ([
                ['label' => __('librarian.reports.visit_metrics.total'), 'value' => number_format((int) $visitSummary['visits'], 0, ',', ' ')],
                ['label' => __('librarian.reports.visit_metrics.unique_readers'), 'value' => number_format((int) $visitSummary['unique_readers'], 0, ',', ' ')],
                [
                    'label' => __('librarian.reports.visit_metrics.busiest_day'),
                    'value' => $visitSummary['busiest_day']
                        ? \Illuminate\Support\Carbon::parse($visitSummary['busiest_day'])->format('d.m.Y').' · '.$visitSummary['busiest_day_visits']
                        : '—',
                ],
            ] as $metric)
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <strong class="block font-headline text-2xl text-primary">{{ $metric['value'] }}</strong>
                    <small class="text-xs uppercase tracking-wider text-slate-500">{{ $metric['label'] }}</small>
                </div>
            @endforeach
        </div>

        @php $visitMax = (int) $visitSeries->max(); @endphp

        @if ($visitSeries->isNotEmpty() && $visitMax > 0)
            <div class="flex gap-4 p-6">
                <div class="flex w-10 shrink-0 flex-col justify-between text-right text-[10px] font-bold text-slate-400" style="height: 12rem">
                    <span>{{ number_format($visitMax, 0, ',', ' ') }}</span>
                    <span>{{ number_format((int) round($visitMax / 2), 0, ',', ' ') }}</span>
                    <span>0</span>
                </div>

                <div class="min-w-0 flex-1 overflow-x-auto pb-1">
                    <div class="flex items-end gap-1 border-b border-l border-slate-200 pl-1" style="min-width: {{ max(320, $visitSeries->count() * 26) }}px">
                        @foreach ($visitSeries as $day => $total)
                            @php
                                $barHeight = max(2, (int) round($total / $visitMax * 100));
                                $barLabel = \Illuminate\Support\Carbon::parse($day)->format('d.m.Y').' — '.__('librarian.reports.columns.visits').': '.$total;
                            @endphp
                            <div class="flex flex-1 flex-col items-center" style="min-width: 18px">
                                <span class="flex w-full items-end" style="height: 12rem">
                                    <span
                                        class="block w-full rounded-t bg-primary/70 transition-colors hover:bg-primary"
                                        style="height: {{ $barHeight }}%"
                                        title="{{ $barLabel }}"
                                        aria-label="{{ $barLabel }}"
                                        role="img"
                                    ></span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-1 pl-1 pt-2" style="min-width: {{ max(320, $visitSeries->count() * 26) }}px">
                        @foreach ($visitSeries as $day => $total)
                            <div class="flex flex-1 justify-center" style="min-width: 18px">
                                <time
                                    class="text-[10px] leading-none text-slate-500"
                                    style="writing-mode: vertical-rl"
                                    datetime="{{ $day }}"
                                >{{ \Illuminate\Support\Carbon::parse($day)->format('d.m') }}</time>
                            </div>
                        @endforeach
                    </div>

                    @if ($visitSeriesIsWeekly)
                        <p class="pl-1 pt-2 text-xs text-slate-500">{{ __('librarian.reports.visits_weekly_note') }}</p>
                    @endif
                </div>
            </div>
        @else
            <div class="py-14 text-center">
                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">sensor_door</span>
                <span class="text-sm text-slate-500">{{ __('librarian.reports.visits_empty') }}</span>
            </div>
        @endif

        <div class="overflow-x-auto border-t border-slate-100">
            <table class="admin-table min-w-140">
                <thead>
                    <tr>
                        <th>{{ __('librarian.reports.columns.branch') }}</th>
                        <th class="text-right">{{ __('librarian.reports.columns.visits') }}</th>
                        <th class="text-right">{{ __('librarian.reports.columns.unique_readers') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($visitBranches as $row)
                        <tr>
                            <td class="font-semibold text-primary">{{ $row->branch }}</td>
                            <td class="text-right text-slate-600">{{ number_format((int) $row->visits, 0, ',', ' ') }}</td>
                            <td class="text-right text-slate-600">{{ number_format((int) $row->readers, 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-10 text-center text-sm text-slate-500">{{ __('librarian.reports.visits_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @can('incidents.view_reports')
    <section class="admin-card mt-6" id="incidents" data-report="incidents">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="font-headline text-2xl text-primary">{{ __('incidents.report.title') }}</h2><p class="mt-1 text-sm text-slate-500">{{ __('incidents.report.subtitle') }}</p></div>
            @can('reports.export')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.export',['type'=>'incidents'] + request()->query()) }}">{{ __('librarian.reports.export_csv') }}</a>@endcan
        </div>
        <form method="GET" class="mt-5 grid gap-3 rounded-xl border bg-surface-container-low p-4 sm:grid-cols-2 lg:grid-cols-4">
            <input type="hidden" name="date_from" value="{{ $from->toDateString() }}"><input type="hidden" name="date_to" value="{{ $to->toDateString() }}">
            <label><span class="admin-label">{{ __('incidents.fields.type') }}</span><select class="admin-input" name="incident_type"><option value="">—</option>@foreach(['lost','damaged'] as $v)<option value="{{ $v }}" @selected(request('incident_type')===$v)>{{ __('incidents.types.'.$v) }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('incidents.fields.status') }}</span><select class="admin-input" name="incident_status"><option value="">—</option>@foreach(\App\Models\Catalog\CirculationIncidentCase::STATUSES as $v)<option value="{{ $v }}" @selected(request('incident_status')===$v)>{{ __('incidents.statuses.'.$v) }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('incidents.fields.branch') }}</span><select class="admin-input" name="incident_branch_id"><option value="">—</option>@foreach($incidentFilterBranches as $branch)<option value="{{ $branch->id }}" @selected((string)request('incident_branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('incidents.fields.assigned_to') }}</span><select class="admin-input" name="incident_assigned_to"><option value="">—</option>@foreach($incidentFilterStaff as $person)<option value="{{ $person->id }}" @selected((string)request('incident_assigned_to')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('incidents.report.reader_category') }}</span><select class="admin-input" name="reader_category"><option value="">—</option>@foreach($incidentReaderCategories as $v)<option value="{{ $v }}" @selected(request('reader_category')===$v)>{{ $v }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('incidents.candidate_fields.udc_code') }}</span><input class="admin-input" name="incident_udc" value="{{ request('incident_udc') }}"></label>
            <label><span class="admin-label">{{ __('incidents.report.fund_type') }}</span><select class="admin-input" name="fund_type"><option value="">—</option>@foreach($incidentFundTypes as $v)<option value="{{ $v }}" @selected(request('fund_type')===$v)>{{ $v }}</option>@endforeach</select></label>
            <label><span class="admin-label">{{ __('incidents.fields.resolution_type') }}</span><select class="admin-input" name="incident_resolution"><option value="">—</option>@foreach(\App\Models\Catalog\CirculationIncidentCase::RESOLUTIONS as $v)<option value="{{ $v }}" @selected(request('incident_resolution')===$v)>{{ __('incidents.resolutions.'.$v) }}</option>@endforeach</select></label>
            <div class="sm:col-span-2 lg:col-span-4"><button class="admin-btn admin-btn-primary">{{ __('common.actions.apply_filters') }}</button></div>
        </form>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach(['open','lost','damaged','accepted','rejected','monetary','fines','written_off','under_repair','overdue','average_hours'] as $metric)
            <div class="rounded-xl bg-surface-container-low p-4"><div class="text-xs uppercase text-slate-500">{{ __('incidents.report.metrics.'.$metric) }}</div><div class="mt-2 text-2xl font-bold">{{ $metric==='fines' ? number_format((float)$incidentMetrics[$metric],2,',',' ').' ₸' : $incidentMetrics[$metric] }}</div></div>
            @endforeach
        </div>
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            @foreach(['statuses','branches','frequent'] as $group)<div><h3 class="font-bold">{{ __('incidents.report.'.$group) }}</h3><div class="mt-3 space-y-2">@foreach($incidentMetrics[$group] as $label=>$count)<div class="flex justify-between gap-3 border-b pb-2 text-sm"><span>{{ $group==='statuses' ? __('incidents.statuses.'.$label) : $label }}</span><strong>{{ $count }}</strong></div>@endforeach</div></div>@endforeach
        </div>
    </section>
    @endcan
@endsection
