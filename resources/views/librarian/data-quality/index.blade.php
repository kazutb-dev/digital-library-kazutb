@extends('layouts.librarian')

@section('title', __('data_quality.title').' — '.__('common.app_name'))

@section('content')
<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-headline text-4xl text-primary">{{ __('data_quality.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('data_quality.subtitle') }}</p>
        </div>
        <nav class="flex flex-wrap gap-2">
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.duplicates') }}">{{ __('data_quality.nav.duplicates') }}</a>
            @can('data_quality.import')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.imports') }}">{{ __('data_quality.nav.imports') }}</a>@endcan
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-cleanup') }}">{{ __('data_quality.nav.legacy') }}</a>
            @can('data_quality.view_reports')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.export', request()->query()) }}">{{ __('data_quality.actions.export') }}</a>@endcan
        </nav>
    </header>

    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($stats as $key => $stat)
            <div class="admin-card">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('data_quality.stats.'.$key) }}</div>
                <div class="mt-2 font-headline text-4xl text-primary">{{ $stat }}@if(str_contains($key, 'percent') || str_contains($key, 'rate'))% @endif</div>
            </div>
        @endforeach
    </section>

    @can('data_quality.scan')
    <section class="admin-card">
        <div class="flex flex-wrap items-end gap-4">
            <form method="POST" action="{{ route('librarian.data-quality.scans.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label><span class="admin-label">{{ __('data_quality.fields.scope') }}</span>
                    <select class="admin-input" name="scope">
                        <option value="all">all</option>
                        @foreach(array_keys(\App\Services\DataQuality\DataQualityScanner::SCOPES) as $scope)<option value="{{ $scope }}">{{ $scope }}</option>@endforeach
                    </select>
                </label>
                <button class="admin-btn admin-btn-primary" type="submit">{{ __('data_quality.actions.queue_scan') }}</button>
            </form>
            <p class="max-w-xl text-xs text-slate-500">HTTP создаёт отслеживаемый запуск, но не выполняет тяжёлый full scan. Worker запускает <code>php artisan library:data-quality:scan</code>.</p>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs">
            @foreach($scanRuns as $run)<span class="rounded-full border px-3 py-1"><strong>{{ $run->run_number }}</strong> · {{ $run->scope }} · {{ $run->status }} · {{ $run->records_scanned }}</span>@endforeach
        </div>
    </section>
    @endcan

    <form method="GET" class="admin-card grid gap-4 md:grid-cols-4">
        @foreach(['entity_type', 'rule_code', 'category'] as $field)
            <label><span class="admin-label">{{ __('data_quality.fields.'.($field === 'entity_type' ? 'entity' : ($field === 'rule_code' ? 'rule' : $field))) }}</span><input class="admin-input" name="{{ $field }}" value="{{ request($field) }}"></label>
        @endforeach
        <label><span class="admin-label">{{ __('data_quality.fields.severity') }}</span><select class="admin-input" name="severity"><option value=""></option>@foreach(\App\Models\DataQualityIssue::SEVERITIES as $severity)<option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ __('data_quality.severity.'.$severity) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.fields.status') }}</span><select class="admin-input" name="status"><option value=""></option>@foreach(\App\Models\DataQualityIssue::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('data_quality.statuses.'.$status) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.fields.assignee') }}</span><select class="admin-input" name="assigned_to"><option value=""></option>@foreach($assignees as $person)<option value="{{ $person->id }}" @selected((string)request('assigned_to') === (string)$person->id)>{{ $person->name }}</option>@endforeach</select></label>
        <label class="flex items-center gap-2 pt-7"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> {{ __('data_quality.stats.overdue') }}</label>
        <button class="admin-btn admin-btn-primary self-end" type="submit">{{ __('common.search') }}</button>
    </form>

    <section class="admin-card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>{{ __('data_quality.fields.issue') }}</th><th>{{ __('data_quality.fields.entity') }}</th><th>{{ __('data_quality.fields.rule') }}</th><th>{{ __('data_quality.fields.severity') }}</th><th>{{ __('data_quality.fields.status') }}</th><th>{{ __('data_quality.fields.assignee') }}</th><th>{{ __('data_quality.fields.due_at') }}</th></tr></thead>
                <tbody>
                @forelse($issues as $issue)
                    <tr>
                        <td><a class="font-semibold text-secondary" href="{{ route('librarian.data-quality.issues.show', $issue) }}">{{ $issue->issue_number }}</a><div class="mt-1 max-w-sm text-xs text-slate-500">{{ $issue->description }}</div></td>
                        <td><span class="font-mono text-xs">{{ $issue->entity_type }}:{{ $issue->entity_id }}</span><div class="text-xs text-slate-500">{{ $issue->field_name }}</div></td>
                        <td class="font-mono text-xs">{{ $issue->rule_code }}</td>
                        <td><span class="rounded-full border px-2 py-1 text-xs">{{ __('data_quality.severity.'.$issue->severity) }}</span></td>
                        <td>{{ __('data_quality.statuses.'.$issue->status) }}</td>
                        <td>{{ $issue->assignee?->name ?? '—' }}</td>
                        <td class="{{ $issue->due_at?->isPast() && !in_array($issue->status, ['resolved','ignored','false_positive']) ? 'font-semibold text-red-700' : '' }}">{{ $issue->due_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    </tr>
                @empty<tr><td colspan="7" class="py-12 text-center text-slate-500">{{ __('common.no_results') }}</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $issues->links() }}</div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        @foreach($distributions as $name => $rows)
            <div class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ $name }}</h2><div class="mt-4 space-y-2">@foreach($rows as $row)<div class="flex justify-between border-b py-2 text-sm"><span>{{ $row->{$name === 'rules' ? 'rule_code' : 'entity_type'} }}</span><strong>{{ $row->total }}</strong></div>@endforeach</div></div>
        @endforeach
    </section>
</div>
@endsection
