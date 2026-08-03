@extends('layouts.admin')

@php
    $roleTotal = max(1, (int) $roleDistribution->sum('total'));
    $newsTotal = (int) $newsByStatus->sum('total');
    $reportTypes = [
        'user-activity' => __('reports.sections.user_activity'),
        'roles' => __('reports.sections.role_distribution'),
        'news' => __('reports.sections.news_statistics'),
        'messages' => __('reports.sections.message_volume'),
        'integrations' => __('reports.sections.integration_status'),
        'branches-funds' => __('reports.sections.funds'),
        'circulation' => __('reports.sections.circulation'),
        'catalog' => __('reports.sections.catalog'),
    ];
@endphp

@section('title', __('reports.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('reports.title')" :subtitle="__('reports.subtitle')" />

    <form method="GET" class="admin-card mb-7 flex flex-col gap-4 sm:flex-row sm:items-end">
        <div>
            <label class="admin-label" for="report-from">{{ __('common.filters.date_from') }}</label>
            <input class="admin-input" id="report-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label class="admin-label" for="report-to">{{ __('common.filters.date_to') }}</label>
            <input class="admin-input" id="report-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <button class="admin-btn admin-btn-primary" type="submit">{{ __('common.actions.apply_filters') }}</button>
        <a class="admin-btn admin-btn-secondary" href="{{ route('admin.reports.index') }}">{{ __('common.actions.clear_filters') }}</a>
    </form>

    <p class="mb-7 flex items-start gap-3 rounded-xl border border-teal-100 bg-teal-50/60 p-4 text-sm leading-6 text-teal-900">
        <span class="material-symbols-outlined text-[20px]">verified</span>{{ __('reports.data_integrity_notice') }}
    </p>

    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            [__('reports.metrics.total_users'), $totalUsers, 'group'],
            [__('reports.metrics.active_users'), $activeUsers, 'how_to_reg'],
            [__('reports.metrics.total_news'), $newsTotal, 'newspaper'],
            [__('reports.metrics.total_messages'), $messageTotal, 'inbox'],
        ] as [$label, $metric, $icon])
            <article class="admin-card">
                <span class="material-symbols-outlined text-secondary">{{ $icon }}</span>
                <p class="mt-5 text-xs font-bold uppercase tracking-[.07em] text-slate-500">{{ $label }}</p>
                <strong class="mt-1 block font-headline text-4xl text-primary">{{ number_format((int) $metric, 0, ',', ' ') }}</strong>
            </article>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-7 xl:grid-cols-12">
        <section class="admin-card xl:col-span-7">
            <div class="mb-6 flex items-center justify-between gap-3">
                <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.role_distribution') }}</h2>
                <a href="{{ route('admin.reports.show', ['type' => 'roles'] + $filters) }}" class="text-sm font-bold text-secondary hover:underline">{{ __('common.actions.view_details') }}</a>
            </div>
            <div class="space-y-5">
                @forelse ($roleDistribution as $row)
                    @php($percentage = round(((int) $row['total']) / $roleTotal * 100, 1))
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                            <span class="font-semibold">
                                {{ \Illuminate\Support\Facades\Lang::has('roles.names.'.$row['role']) ? __('roles.names.'.$row['role']) : $row['role'] }}
                            </span>
                            <span class="text-slate-500">{{ $row['total'] }} · {{ $percentage }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-secondary" style="width: {{ min(100, $percentage) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('reports.no_data') }}</p>
                @endforelse
            </div>
        </section>

        <section class="admin-card xl:col-span-5">
            <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.resolution_performance') }}</h2>
            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-surface-low p-5">
                    <span class="text-xs text-slate-500">{{ __('reports.metrics.resolution_rate') }}</span>
                    <strong class="mt-2 block font-headline text-4xl text-primary">{{ number_format($resolutionRate, 1) }}%</strong>
                </div>
                <div class="rounded-xl bg-surface-low p-5">
                    <span class="text-xs text-slate-500">{{ __('reports.metrics.average_resolution_time') }}</span>
                    <strong class="mt-2 block font-headline text-4xl text-primary">
                        {{ $averageResolutionHours === null ? '—' : number_format($averageResolutionHours, 1) }}
                    </strong>
                    @if($averageResolutionHours !== null)<small class="text-slate-500">{{ __('reports.units.hours', ['count' => '']) }}</small>@endif
                </div>
            </div>
            <div class="mt-5 divide-y divide-slate-100">
                @foreach ($messagesByStatus as $row)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <x-admin.status-badge :status="$row['status']" :label="__('messages.statuses.'.$row['status'])" />
                        <strong>{{ $row['total'] }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="admin-card xl:col-span-6">
            <div class="mb-5 flex items-center justify-between gap-3">
                <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.user_activity') }}</h2>
                <a href="{{ route('admin.reports.show', ['type' => 'user-activity'] + $filters) }}" class="text-sm font-bold text-secondary hover:underline">{{ __('common.actions.view_details') }}</a>
            </div>
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>{{ __('reports.dimensions.role') }}</th><th>{{ __('reports.metrics.audit_events') }}</th></tr></thead>
                    <tbody>
                        @forelse ($activityByRole as $row)
                            <tr>
                                <td>
                                    @if (empty($row['role']))
                                        {{ __('common.time.not_available') }}
                                    @else
                                        {{ \Illuminate\Support\Facades\Lang::has('roles.names.'.$row['role']) ? __('roles.names.'.$row['role']) : $row['role'] }}
                                    @endif
                                </td>
                                <td class="font-semibold">{{ $row['events'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-slate-500">{{ __('reports.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($providerDistribution as $row)
                    <span class="rounded-full bg-surface-low px-3 py-2 text-xs font-semibold">{{ __('admin.users.providers.'.$row['provider']) }} · {{ $row['total'] }}</span>
                @endforeach
            </div>
        </section>

        <section class="admin-card xl:col-span-6">
            <div class="mb-5 flex items-center justify-between gap-3">
                <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.news_statistics') }}</h2>
                <a href="{{ route('admin.reports.show', ['type' => 'news'] + $filters) }}" class="text-sm font-bold text-secondary hover:underline">{{ __('common.actions.view_details') }}</a>
            </div>
            <div class="grid grid-cols-2 gap-3">
                @foreach (['draft', 'scheduled', 'published', 'archived'] as $status)
                    @php($count = (int) (collect($newsByStatus)->firstWhere('status', $status)['total'] ?? 0))
                    <div class="rounded-xl bg-surface-low p-4">
                        <x-admin.status-badge :status="$status" :label="__('news.statuses.'.$status)" />
                        <strong class="mt-3 block font-headline text-3xl">{{ $count }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="admin-card xl:col-span-6">
            <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.integration_status') }}</h2>
            <div class="mt-5 space-y-3">
                @foreach ($integrationStatuses as $row)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-4">
                        <span class="text-sm font-semibold">{{ $row['integration'] }}</span>
                        <x-admin.status-badge :status="$row['status']" :label="__('common.status.'.$row['status'])" />
                    </div>
                @endforeach
            </div>
            @can('system.settings')
                <a href="{{ route('admin.integrations.index') }}" class="admin-btn admin-btn-secondary mt-5">{{ __('common.actions.view_details') }}</a>
            @endcan
        </section>

        <section class="admin-card xl:col-span-6">
            <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.funds') }}</h2>
            <div class="mt-5 grid grid-cols-2 gap-4">
                <div class="rounded-xl bg-surface-low p-5"><span class="text-xs text-slate-500">{{ __('admin.branches.title') }}</span><strong class="mt-2 block font-headline text-4xl">{{ $branchCount }}</strong></div>
                <div class="rounded-xl bg-surface-low p-5"><span class="text-xs text-slate-500">{{ __('admin.funds.title') }}</span><strong class="mt-2 block font-headline text-4xl">{{ $fundCount }}</strong></div>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-500">{{ __('reports.unavailable.funds') }}</p>
        </section>
    </div>

    <section class="mt-7 rounded-xl bg-primary p-6 text-white sm:p-8">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-cyan-200">{{ __('reports.sections.circulation') }}</p>
                <h2 class="mt-2 font-headline text-3xl">{{ $circulationAvailable ? __('common.available') : __('common.not_configured') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/65">{{ $circulationAvailable ? __('reports.data_integrity_notice') : __('reports.data_unavailable_circulation') }}</p>
            </div>
            <a href="{{ route('admin.reports.show', ['type' => 'circulation'] + $filters) }}" class="admin-btn bg-white text-primary hover:bg-slate-100">{{ __('common.actions.view_details') }}</a>
        </div>
    </section>

    <section class="mt-7 rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.14em] text-secondary">{{ __('reports.sections.catalog') }}</p>
                <h2 class="mt-2 font-headline text-3xl text-primary">{{ $catalogAvailable ? __('common.available') : __('common.not_configured') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $catalogAvailable ? __('reports.data_integrity_notice') : __('reports.unavailable.catalog') }}</p>
            </div>
            <a href="{{ route('admin.reports.show', ['type' => 'catalog'] + $filters) }}" class="admin-btn admin-btn-secondary">{{ __('common.actions.view_details') }}</a>
        </div>
    </section>

    @can('reports.export')
        <section class="admin-card mt-7">
            <h2 class="font-headline text-3xl text-primary">{{ __('reports.sections.exports') }}</h2>
            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($reportTypes as $type => $label)
                    <div class="rounded-xl border border-slate-100 p-4">
                        <strong class="block text-sm">{{ $label }}</strong>
                        <div class="mt-3 flex gap-2">
                            <a class="admin-btn admin-btn-secondary flex-1" href="{{ route('admin.reports.export', ['type' => $type, 'format' => 'csv'] + $filters) }}">CSV</a>
                            <a class="admin-btn admin-btn-secondary flex-1" href="{{ route('admin.reports.export', ['type' => $type, 'format' => 'pdf'] + $filters) }}">PDF</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endcan
@endsection
