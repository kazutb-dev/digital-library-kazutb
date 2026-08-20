@extends('layouts.librarian')

@section('title', __('official_reports.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header :eyebrow="__('analytics.eyebrow')" :title="__('official_reports.title')" :subtitle="__('official_reports.subtitle')" />

    <div class="mb-6 flex flex-wrap justify-end gap-2">
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.index') }}">
            <span class="material-symbols-outlined text-[18px]">monitoring</span>{{ __('official_reports.open_live') }}
        </a>
    </div>

    @can('create', \App\Models\OfficialReportSnapshot::class)
        <section class="admin-card mb-6">
            <h2 class="font-headline text-2xl text-primary">{{ __('official_reports.create') }}</h2>
            <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('official_reports.source_notice') }}</p>
            <form method="POST" action="{{ route('librarian.reports.official.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @csrf
                <label><span class="admin-label">{{ __('official_reports.fields.type') }}</span><select class="admin-input" name="report" required>@foreach($definitions as $definition)<option value="{{ $definition->code }}">{{ __($definition->titleKey) }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('official_reports.fields.period') }}</span><select class="admin-input" name="preset">@foreach(\App\Services\Reports\ReportFilters::PRESETS as $preset)<option value="{{ $preset }}" @selected($preset === 'month')>{{ __('analytics.presets.'.$preset) }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('official_reports.fields.from') }}</span><input class="admin-input" type="date" name="from" value="{{ now(config('app.library_timezone', 'Asia/Almaty'))->startOfMonth()->toDateString() }}"></label>
                <label><span class="admin-label">{{ __('official_reports.fields.to') }}</span><input class="admin-input" type="date" name="to" value="{{ now(config('app.library_timezone', 'Asia/Almaty'))->toDateString() }}"></label>

                @foreach(['branch_id', 'fund_id', 'resource_type', 'user_segment', 'language', 'status', 'subject', 'access_type', 'operation', 'acquisition_source'] as $filter)
                    <label>
                        <span class="admin-label">{{ __('analytics.filters.'.$filter) }}</span>
                        <select class="admin-input" name="{{ $filter }}">
                            <option value="">{{ __('analytics.filters.all') }}</option>
                            @foreach($filterOptions[$filter] ?? [] as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
                <label><span class="admin-label">{{ __('analytics.filters.udc') }}</span><input class="admin-input" name="udc" maxlength="64"></label>
                <label class="sm:col-span-2"><span class="admin-label">{{ __('official_reports.fields.note') }}</span><input class="admin-input" name="revision_note" maxlength="2000"></label>
                <div class="flex items-end"><button class="admin-btn admin-btn-primary w-full" type="submit"><span class="material-symbols-outlined text-[18px]">lock</span>{{ __('official_reports.actions.create') }}</button></div>
            </form>
        </section>
    @endcan

    <section class="admin-card overflow-hidden p-0">
        <header class="flex flex-col gap-4 border-b border-slate-100 p-6 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="font-headline text-2xl text-primary">{{ __('official_reports.archive') }}</h2>
            <form class="grid gap-2 sm:grid-cols-3" method="GET">
                <label><span class="admin-label">{{ __('official_reports.fields.type') }}</span><select class="admin-input" name="type"><option value="">{{ __('analytics.filters.all') }}</option>@foreach($definitions as $definition)<option value="{{ $definition->code }}" @selected(($filters['type'] ?? '') === $definition->code)>{{ __($definition->titleKey) }}</option>@endforeach</select></label>
                <label><span class="admin-label">{{ __('official_reports.fields.status') }}</span><select class="admin-input" name="status"><option value="">{{ __('analytics.filters.all') }}</option>@foreach(\App\Models\OfficialReportSnapshot::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('official_reports.statuses.'.$status) }}</option>@endforeach</select></label>
                <button class="admin-btn admin-btn-secondary" type="submit">{{ __('analytics.filters.apply') }}</button>
            </form>
        </header>
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[900px]">
                <thead><tr><th>{{ __('official_reports.fields.number') }}</th><th>{{ __('official_reports.fields.type') }}</th><th>{{ __('official_reports.fields.revision') }}</th><th>{{ __('official_reports.fields.status') }}</th><th>{{ __('official_reports.fields.period') }}</th><th>{{ __('official_reports.fields.creator') }}</th><th>{{ __('official_reports.fields.created') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse($snapshots as $snapshot)
                        <tr>
                            <td class="font-mono text-xs">{{ $snapshot->report_number }}</td>
                            <td class="font-semibold text-primary">{{ __('analytics.reports.'.$snapshot->report_type.'.title') }}</td>
                            <td>R{{ $snapshot->revision }}</td>
                            <td><span class="rounded-full bg-surface-container-low px-2.5 py-1 text-xs font-bold">{{ __('official_reports.statuses.'.$snapshot->status) }}</span></td>
                            <td>{{ $snapshot->period_from->timezone(config('app.library_timezone', 'Asia/Almaty'))->format('d.m.Y') }} — {{ $snapshot->period_to->timezone(config('app.library_timezone', 'Asia/Almaty'))->format('d.m.Y') }}</td>
                            <td>{{ $snapshot->creator?->name ?: '—' }}</td>
                            <td>{{ $snapshot->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="text-right"><a class="font-bold text-secondary hover:underline" href="{{ route('librarian.reports.official.show', $snapshot) }}">{{ __('common.actions.view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-14 text-center text-sm text-slate-500">{{ __('official_reports.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($snapshots->hasPages())<div class="border-t border-slate-100 p-5">{{ $snapshots->links() }}</div>@endif
    </section>
@endsection
