@extends('layouts.librarian')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $filterOptions = is_array($filterOptions ?? null) ? $filterOptions : [];
    $supportedFilters = collect($supportedFilters ?? array_keys($filterOptions));
    $types = collect($reportTypes ?? [])->map(function ($report, $key) {
        if (is_string($report)) {
            return ['key' => $report];
        }
        if (is_object($report)) {
            $report = (array) $report;
        }
        $report = is_array($report) ? $report : [];
        $report['key'] ??= is_string($key) ? $key : ($report['type'] ?? '');

        return $report;
    })->filter(fn (array $report): bool => filled($report['key'] ?? null))->values();
    $activeReportKey = is_array($activeReport ?? null)
        ? (string) ($activeReport['key'] ?? $activeReport['type'] ?? '')
        : (string) ($activeReport ?? request('report', ''));
    if (! $types->contains(fn (array $report): bool => $report['key'] === $activeReportKey)) {
        $activeReportKey = (string) ($types->first()['key'] ?? '');
    }
    $reportQuery = array_filter($filters, static fn ($value) => $value !== null && $value !== '');
    $reportQuery['report'] = $activeReportKey;
    $reportGroups = [
        'official' => $types->filter(fn (array $report): bool => (bool) ($report['is_official'] ?? false))->values(),
        'operational' => $types->reject(fn (array $report): bool => (bool) ($report['is_official'] ?? false))->values(),
    ];

    $metrics = collect($metrics ?? [])->map(function ($metric, $key) {
        if (! is_array($metric)) {
            return ['key' => is_string($key) ? $key : 'total', 'value' => $metric];
        }
        $metric['key'] ??= is_string($key) ? $key : 'total';

        return $metric;
    })->values();

    $columns = collect($columns ?? [])->map(function ($column, $key) {
        if (is_string($column) && is_int($key)) {
            return ['key' => $column, 'label' => $column];
        }
        if (is_string($column)) {
            return ['key' => (string) $key, 'label' => $column];
        }
        $column = is_array($column) ? $column : (array) $column;
        $column['key'] ??= is_string($key) ? $key : ($column['field'] ?? '');
        $column['label'] ??= $column['title'] ?? $column['key'];

        return $column;
    })->filter(fn (array $column): bool => $column['key'] !== '')->values();

    $rows = collect($rows ?? [])->map(fn ($row) => is_array($row) ? $row : (array) $row);
    $breakdowns = collect($breakdowns ?? []);
    $pagination = array_merge(['page' => 1, 'per_page' => 25, 'total' => $rows->count(), 'last_page' => 1, 'from' => $rows->isEmpty() ? 0 : 1, 'to' => $rows->count()], is_array($pagination ?? null) ? $pagination : []);
    $sorting = array_merge(['key' => '', 'direction' => 'asc'], is_array($sorting ?? null) ? $sorting : []);

    $labelFor = static function (string $key, ?string $fallback = null): string {
        foreach (['analytics.'.$key, 'reports.'.$key, 'librarian.reports.'.$key] as $translationKey) {
            if (trans()->has($translationKey)) {
                return __($translationKey);
            }
        }

        return $fallback ?: str($key)->replace(['_', '-'], ' ')->title()->toString();
    };

    $formatValue = static function ($value, string $column = ''): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value)->format('d.m.Y H:i');
        }
        if (is_bool($value)) {
            return $value ? __('common.boolean.yes') : __('common.boolean.no');
        }
        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => $item !== null && $item !== '')->implode(', ') ?: '—';
        }
        if (is_numeric($value)) {
            $decimals = str_contains($column, 'rate') || str_contains($column, 'percent') ? 1 : (((float) $value !== (float) (int) $value) ? 2 : 0);
            return number_format((float) $value, $decimals, ',', ' ');
        }

        return (string) $value;
    };

    $optionEntries = static function ($options): array {
        return collect($options ?? [])->map(function ($option, $key) {
            if (is_array($option)) {
                return [
                    'value' => (string) ($option['value'] ?? $option['id'] ?? $option['key'] ?? $key),
                    'label' => (string) ($option['label'] ?? $option['name'] ?? $option['title'] ?? $option['value'] ?? $key),
                ];
            }
            if (is_object($option)) {
                return [
                    'value' => (string) ($option->value ?? $option->id ?? $option->key ?? $key),
                    'label' => (string) ($option->label ?? $option->name ?? $option->title ?? $option->value ?? $key),
                ];
            }

            return is_string($key)
                ? ['value' => (string) $key, 'label' => (string) $option]
                : ['value' => (string) $option, 'label' => (string) $option];
        })->values()->all();
    };

    $metricIcons = [
        'total' => 'monitoring', 'copies' => 'inventory_2', 'value' => 'payments', 'sources' => 'category',
        'issued' => 'outbound', 'returned' => 'assignment_return', 'visits' => 'sensor_door',
        'unique_users' => 'groups', 'active_users' => 'person_check', 'views' => 'visibility',
        'downloads' => 'download', 'logins' => 'login', 'denied' => 'lock', 'failures' => 'error',
        'licensed' => 'verified',
    ];
    $reportIcons = [
        'acquisitions' => 'inventory', 'fund-usage' => 'local_library', 'users' => 'groups', 'electronic-resources' => 'devices',
        'loans' => 'outbound', 'returns' => 'assignment_return', 'renewals' => 'autorenew', 'overdue' => 'event_busy', 'fines' => 'payments',
        'reservations' => 'bookmark', 'queue' => 'format_list_numbered', 'incidents' => 'report_problem', 'lost-damaged' => 'broken_image',
        'inventory' => 'fact_check', 'visits' => 'sensor_door', 'data-quality' => 'verified', 'news-events' => 'campaign',
        'messages' => 'support_agent', 'repository' => 'school', 'external-resources' => 'language', 'staff' => 'badge',
        'audit-summary' => 'policy', 'fund-movement' => 'sync_alt', 'new-acquisitions' => 'library_add',
        'write-offs' => 'inventory_2', 'electronic-materials' => 'picture_as_pdf',
    ];

    $activeMeta = $types->firstWhere('key', $activeReportKey) ?? ['key' => $activeReportKey];
    $activeTitle = $activeMeta['label'] ?? $activeMeta['title'] ?? __('analytics.reports.'.$activeReportKey.'.title');
    $activeDescription = $activeMeta['description'] ?? __('analytics.reports.'.$activeReportKey.'.description');
    $periodFrom = $filters['date_from'] ?? $filters['from'] ?? now()->startOfMonth()->toDateString();
    $periodTo = $filters['date_to'] ?? $filters['to'] ?? now()->toDateString();
@endphp

@section('title', __('analytics.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('analytics.eyebrow')"
        :title="__('analytics.title')"
        :subtitle="__('analytics.subtitle')"
    />

    <nav class="report-tabs mb-6 space-y-6" aria-label="{{ __('analytics.navigation') }}" data-report-registry>
        @foreach ($reportGroups as $groupKey => $groupReports)
            @continue($groupReports->isEmpty())
            <section aria-labelledby="report-group-{{ $groupKey }}">
                <div class="mb-3 flex items-center gap-3">
                    <h2 id="report-group-{{ $groupKey }}" class="font-headline text-xl text-primary">{{ __('analytics.groups.'.$groupKey) }}</h2>
                    <span class="rounded-full bg-surface-container-low px-2.5 py-1 text-xs font-bold text-slate-600">{{ $groupReports->count() }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($groupReports as $report)
                        @php
                            $reportKey = $report['key'];
                            $isActiveReport = $reportKey === $activeReportKey;
                            $reportHref = route('librarian.reports.index', array_merge($reportQuery, ['report' => $reportKey, 'page' => null, 'sort' => null, 'direction' => null]));
                        @endphp
                        <a href="{{ $reportHref }}" data-report-code="{{ $reportKey }}" @class([
                            'group rounded-2xl border p-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2',
                            'border-primary-container bg-primary-container text-white shadow-lg shadow-primary-container/10' => $isActiveReport,
                            'border-slate-200 bg-white text-primary hover:border-secondary hover:shadow-md' => ! $isActiveReport,
                        ]) @if($isActiveReport) aria-current="page" @endif>
                            <div class="mb-5 flex items-start justify-between gap-3">
                                <span aria-hidden="true" @class(['material-symbols-outlined text-[25px]', 'text-secondary-fixed' => $isActiveReport, 'text-secondary' => ! $isActiveReport])>{{ $reportIcons[$reportKey] ?? 'monitoring' }}</span>
                                <span @class(['rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider', 'bg-white/10 text-white' => $isActiveReport, 'bg-emerald-50 text-emerald-700' => ! $isActiveReport])>
                                    {{ $report['official'] ?? __('analytics.groups.'.(($report['is_official'] ?? false) ? 'official_badge' : 'operational_badge')) }}
                                </span>
                            </div>
                            <h3 class="font-headline text-lg leading-tight">{{ $report['label'] ?? $report['title'] ?? __('analytics.reports.'.$reportKey.'.short') }}</h3>
                            @if(filled($report['frequency'] ?? null))
                                <p @class(['mt-2 text-xs leading-5', 'text-white/70' => $isActiveReport, 'text-slate-500' => ! $isActiveReport])>{{ $report['frequency'] }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('librarian.reports.index') }}" class="report-filters admin-card mb-6" data-report-filters>
        <input type="hidden" name="report" value="{{ $activeReportKey }}">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="admin-label mb-1">{{ __('analytics.filters.title') }}</p>
                <p class="text-xs text-slate-500">{{ __('analytics.period', ['from' => $periodFrom, 'to' => $periodTo]) }}</p>
            </div>
            <div class="flex gap-2">
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.index', ['report' => $activeReportKey]) }}">
                    <span class="material-symbols-outlined text-[18px]">filter_alt_off</span>{{ __('analytics.filters.reset') }}
                </a>
                <button class="admin-btn admin-btn-primary" type="submit">
                    <span class="material-symbols-outlined text-[18px]">query_stats</span>{{ __('analytics.filters.apply') }}
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <label>
                <span class="admin-label">{{ __('analytics.filters.preset') }}</span>
                <select class="admin-input" name="preset" data-period-preset>
                    @foreach ($optionEntries($filterOptions['preset'] ?? []) as $option)
                        <option value="{{ $option['value'] }}" @selected(($filters['preset'] ?? 'month') === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="admin-label">{{ __('analytics.filters.date_from') }}</span>
                <input class="admin-input" type="date" name="date_from" value="{{ $periodFrom }}">
            </label>
            <label>
                <span class="admin-label">{{ __('analytics.filters.date_to') }}</span>
                <input class="admin-input" type="date" name="date_to" value="{{ $periodTo }}">
            </label>

            @foreach (['branch_id', 'fund_id', 'resource_type'] as $filterKey)
                @continue(! $supportedFilters->contains($filterKey))
                <label>
                    <span class="admin-label">{{ __('analytics.filters.'.$filterKey) }}</span>
                    <select class="admin-input" name="{{ $filterKey }}">
                        <option value="">{{ __('analytics.filters.all') }}</option>
                        @foreach ($optionEntries($filterOptions[$filterKey] ?? []) as $option)
                            <option value="{{ $option['value'] }}" @selected((string) ($filters[$filterKey] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>

        @if($supportedFilters->intersect(['user_segment','language','udc','status','subject','access_type','operation','acquisition_source'])->isNotEmpty())
        <details class="mt-4 rounded-xl border border-slate-100 bg-surface-container-low p-4" @if(collect(['user_segment','language','udc','status','subject','access_type','operation','acquisition_source'])->contains(fn ($key) => filled($filters[$key] ?? null))) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-bold text-primary">
                <span class="material-symbols-outlined text-[19px] text-secondary">tune</span>{{ __('analytics.filters.more') }}
            </summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['user_segment', 'language', 'status', 'subject', 'access_type', 'operation', 'acquisition_source'] as $filterKey)
                    @continue(! $supportedFilters->contains($filterKey))
                    <label>
                        <span class="admin-label">{{ __('analytics.filters.'.$filterKey) }}</span>
                        <select class="admin-input" name="{{ $filterKey }}">
                            <option value="">{{ __('analytics.filters.all') }}</option>
                            @foreach ($optionEntries($filterOptions[$filterKey] ?? []) as $option)
                                <option value="{{ $option['value'] }}" @selected((string) ($filters[$filterKey] ?? '') === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                @if($supportedFilters->contains('udc'))<label>
                    <span class="admin-label">{{ __('analytics.filters.udc') }}</span>
                    <input class="admin-input" type="search" name="udc" value="{{ $filters['udc'] ?? '' }}" maxlength="64" placeholder="00… 3… 62…">
                </label>@endif
            </div>
        </details>
        @endif
    </form>

    <section class="mb-6 rounded-2xl bg-primary-container px-6 py-7 text-white sm:px-8" aria-labelledby="active-report-title">
        <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <p class="mb-2 text-xs font-bold uppercase tracking-[.16em] text-secondary-fixed">{{ __('analytics.eyebrow') }}</p>
                <h1 id="active-report-title" class="font-headline text-3xl leading-tight sm:text-4xl">{{ $activeTitle }}</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-white/70">{{ $activeDescription }}</p>
            </div>
            @can('reports.export')
                <div class="report-actions flex flex-wrap gap-2" aria-label="{{ __('analytics.formats.title') }}">
                    @foreach (['csv' => 'table_view', 'xlsx' => 'grid_on', 'pdf' => 'picture_as_pdf', 'docx' => 'description'] as $format => $icon)
                        <a class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3.5 py-2 text-xs font-bold text-white hover:bg-white/20"
                           href="{{ route('librarian.reports.export', array_merge($reportQuery, ['type' => $activeReportKey, 'format' => $format])) }}">
                            <span class="material-symbols-outlined text-[17px]">{{ $icon }}</span>{{ __('analytics.formats.'.$format) }}
                        </a>
                    @endforeach
                    @if(\Illuminate\Support\Facades\Route::has('librarian.reports.print'))
                        <a class="inline-flex items-center gap-2 rounded-lg bg-secondary px-3.5 py-2 text-xs font-bold text-white hover:bg-secondary/80"
                           target="_blank" rel="noopener" href="{{ route('librarian.reports.print', array_merge($reportQuery, ['type' => $activeReportKey])) }}">
                            <span class="material-symbols-outlined text-[17px]">print</span>{{ __('analytics.formats.print') }}
                        </a>
                    @endif
                </div>
            @endcan
        </div>
    </section>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @forelse ($metrics as $metric)
            @php
                $metricKey = (string) ($metric['key'] ?? 'total');
                $metricLabel = $metric['label'] ?? $labelFor('metrics.'.$metricKey, $metricKey);
            @endphp
            <article class="admin-card flex min-h-32 items-start justify-between gap-4 p-5">
                <div class="min-w-0">
                    <p class="admin-label mb-3">{{ $metricLabel }}</p>
                    <p class="font-headline text-3xl leading-none text-primary">{{ $formatValue($metric['value'] ?? $metric['total'] ?? 0, $metricKey) }}</p>
                    @if(filled($metric['hint'] ?? null))<p class="mt-2 text-xs leading-5 text-slate-500">{{ $metric['hint'] }}</p>@endif
                </div>
                <span class="material-symbols-outlined shrink-0 text-[27px] text-secondary">{{ $metric['icon'] ?? $metricIcons[$metricKey] ?? 'monitoring' }}</span>
            </article>
        @empty
            @foreach (['total', 'issued', 'visits', 'downloads'] as $metricKey)
                <article class="admin-card p-5"><p class="admin-label mb-3">{{ $labelFor('metrics.'.$metricKey) }}</p><p class="font-headline text-3xl text-primary">0</p></article>
            @endforeach
        @endforelse
    </div>

    @if($breakdowns->isNotEmpty())
        <section class="mb-6 grid gap-4 xl:grid-cols-2" aria-labelledby="report-breakdowns-title">
            <h2 id="report-breakdowns-title" class="sr-only">{{ __('analytics.breakdowns') }}</h2>
            @foreach($breakdowns as $breakdownKey => $breakdown)
                @php
                    $breakdown = is_array($breakdown) ? $breakdown : (array) $breakdown;
                    $items = collect($breakdown['items'] ?? $breakdown['rows'] ?? (is_int($breakdownKey) ? [] : $breakdown));
                    $breakdownTitle = $breakdown['label'] ?? $breakdown['title'] ?? $labelFor('breakdowns.'.(is_string($breakdownKey) ? $breakdownKey : 'total'));
                    $maxBreakdown = max(1, (float) $items->map(fn ($item) => (float) (is_array($item) ? ($item['value'] ?? $item['total'] ?? $item['count'] ?? 0) : $item))->max());
                @endphp
                <article class="admin-card">
                    <h3 class="font-headline text-2xl text-primary">{{ $breakdownTitle }}</h3>
                    <div class="mt-5 space-y-4">
                        @forelse($items->take(12) as $itemKey => $item)
                            @php
                                $item = is_array($item) ? $item : (is_object($item) ? (array) $item : ['label' => is_string($itemKey) ? $itemKey : '', 'value' => $item]);
                                $value = (float) ($item['value'] ?? $item['total'] ?? $item['count'] ?? 0);
                                $itemLabel = $item['label'] ?? $item['name'] ?? $item['title'] ?? (is_string($itemKey) ? $itemKey : '—');
                                $width = max(2, min(100, (int) round($value / $maxBreakdown * 100)));
                            @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-4 text-xs"><span class="truncate font-semibold text-slate-700">{{ $itemLabel }}</span><span class="font-bold text-primary">{{ $formatValue($value) }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-secondary" style="width: {{ $width }}%"></span></div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">{{ __('analytics.empty') }}</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="admin-card overflow-hidden p-0" aria-labelledby="report-table-title">
        <header class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="report-table-title" class="font-headline text-2xl text-primary">{{ __('analytics.table_title') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('analytics.period', ['from' => $periodFrom, 'to' => $periodTo]) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600">
                <span class="rounded-full bg-surface-container-low px-3 py-1.5 font-bold" data-report-total>{{ number_format((int) $pagination['total'], 0, ',', ' ') }}</span>
                <span>{{ __('analytics.pagination.range', ['from' => $pagination['from'], 'to' => $pagination['to']]) }}</span>
                <span class="sr-only">{{ __('analytics.pagination.per_page') }}</span>
                @foreach ([25, 50, 100] as $pageSize)
                    <a href="{{ route('librarian.reports.index', array_merge(request()->query(), ['per_page' => $pageSize, 'page' => 1])) }}"
                       @class(['rounded-lg px-2 py-1 font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary', 'bg-primary text-white' => (int) $pagination['per_page'] === $pageSize, 'bg-surface-container-low hover:bg-slate-100' => (int) $pagination['per_page'] !== $pageSize])
                       @if((int) $pagination['per_page'] === $pageSize) aria-current="true" @endif>{{ $pageSize }}</a>
                @endforeach
            </div>
        </header>
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[900px]">
                <thead>
                    <tr>
                        @foreach($columns as $column)
                            @php
                                $isSorted = $sorting['key'] === $column['key'];
                                $nextDirection = $isSorted && $sorting['direction'] === 'asc' ? 'desc' : 'asc';
                                $sortHref = route('librarian.reports.index', array_merge(request()->query(), ['sort' => $column['key'], 'direction' => $nextDirection, 'page' => 1]));
                            @endphp
                            <th @if($isSorted) aria-sort="{{ $sorting['direction'] === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                <a class="inline-flex items-center gap-1 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary" href="{{ $sortHref }}">
                                    <span>{{ $column['label'] }}</span>
                                    <span class="material-symbols-outlined text-[15px]" aria-hidden="true">{{ $isSorted ? ($sorting['direction'] === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more' }}</span>
                                    <span class="sr-only">{{ __('analytics.sort.'.($nextDirection === 'asc' ? 'ascending' : 'descending')) }}</span>
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @foreach($columns as $column)
                                @php $cell = data_get($row, $column['key']); @endphp
                                <td @class(['font-semibold text-primary' => $loop->first, 'text-slate-600' => ! $loop->first])>{{ $formatValue($cell, $column['key']) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ max(1, $columns->count()) }}" class="py-16 text-center"><span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">query_stats</span><span class="text-sm text-slate-500">{{ __('analytics.empty') }}</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if((int) $pagination['last_page'] > 1)
            <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-6 py-4" aria-label="{{ __('analytics.pagination.label') }}" data-report-pagination>
                @php
                    $currentPage = (int) $pagination['page'];
                    $lastPage = (int) $pagination['last_page'];
                    $firstVisiblePage = max(1, $currentPage - 2);
                    $lastVisiblePage = min($lastPage, $currentPage + 2);
                @endphp
                <a href="{{ route('librarian.reports.index', array_merge(request()->query(), ['page' => max(1, $currentPage - 1)])) }}"
                   @class(['admin-btn admin-btn-secondary', 'pointer-events-none opacity-50' => $currentPage <= 1])
                   @if($currentPage <= 1) aria-disabled="true" tabindex="-1" @endif>{{ __('analytics.pagination.previous') }}</a>
                <div class="flex items-center gap-1">
                    @for($pageNumber = $firstVisiblePage; $pageNumber <= $lastVisiblePage; $pageNumber++)
                        <a href="{{ route('librarian.reports.index', array_merge(request()->query(), ['page' => $pageNumber])) }}"
                           @class(['inline-flex size-9 items-center justify-center rounded-lg text-sm font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-secondary', 'bg-primary text-white' => $pageNumber === $currentPage, 'bg-surface-container-low text-primary hover:bg-slate-100' => $pageNumber !== $currentPage])
                           @if($pageNumber === $currentPage) aria-current="page" @endif>{{ $pageNumber }}</a>
                    @endfor
                </div>
                <a href="{{ route('librarian.reports.index', array_merge(request()->query(), ['page' => min($lastPage, $currentPage + 1)])) }}"
                   @class(['admin-btn admin-btn-secondary', 'pointer-events-none opacity-50' => $currentPage >= $lastPage])
                   @if($currentPage >= $lastPage) aria-disabled="true" tabindex="-1" @endif>{{ __('analytics.pagination.next') }}</a>
            </nav>
        @endif
    </section>

    {{-- Attendance remains visible as an operational cross-check and preserves
         the established director workflow while official reports use
         the unified dataset above. --}}
    @if($canViewAttendance ?? false)<section class="admin-card mt-6" aria-labelledby="attendance-check-title">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="admin-label mb-1">{{ __('librarian.reports.visits') }}</p>
                <h2 id="attendance-check-title" class="font-headline text-2xl text-primary">{{ __('librarian.reports.visit_metrics.total') }}: {{ number_format((int) data_get($visitSummary ?? [], 'total', 0), 0, ',', ' ') }}</h2>
                <p class="mt-2 text-xs text-slate-500">{{ __('librarian.reports.visit_metrics.unique_readers') }}: {{ number_format((int) data_get($visitSummary ?? [], 'unique_readers', 0), 0, ',', ' ') }} · {{ __('librarian.reports.visit_metrics.busiest_day') }}: {{ data_get($visitSummary ?? [], 'busiest_day', '—') ?: '—' }}</p>
            </div>
            @can('reports.export')
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.export', array_merge($reportQuery, ['type' => 'visits', 'format' => 'csv'])) }}"><span class="material-symbols-outlined text-[18px]">download</span>CSV</a>
            @endcan
        </div>
    </section>@endif

    <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900">{{ __('analytics.official_note') }}</p>

    {{-- Stable markers for integrations that still discover the historical
         operational datasets. Their CSV endpoints remain supported. --}}
    <div class="sr-only" aria-hidden="true">
        <span>{{ __('librarian.reports.totals.issued') }}</span>
        <span>{{ __('librarian.reports.totals.returned') }}</span>
        <span>{{ __('librarian.reports.fund_usage') }}</span>
        <span>{{ __('librarian.reports.acquisitions') }}</span>
        <span data-report="udc-fund">Фонд по УДК-разделам</span>
    </div>
@endsection

@section('head')
    <style>
        @media print {
            .report-tabs, .report-filters, .report-actions { display: none !important; }
            .admin-card { box-shadow: none !important; break-inside: avoid; }
        }
    </style>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-report-filters]');
            const preset = form?.querySelector('[data-period-preset]');
            if (!form || !preset) return;

            form.querySelectorAll('input[type="date"]').forEach((input) => {
                input.addEventListener('input', () => { preset.value = 'custom'; });
            });
        })();
    </script>
@endpush
