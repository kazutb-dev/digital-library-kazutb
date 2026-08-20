@extends('layouts.librarian')
@section('title', __('data_quality.title').' — '.__('common.app_name'))
@section('content')
@php($localeQuery = ['lang' => app()->getLocale()])
<div class="space-y-6">
    <x-admin.flash />
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="font-headline text-4xl text-primary">{{ __('data_quality.title') }}</h1><p class="mt-1 text-sm text-slate-500">{{ __('data_quality.subtitle') }}</p></div>
        <nav class="flex flex-wrap gap-2"><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.duplicates', $localeQuery) }}">{{ __('data_quality.nav.duplicates') }}</a>@can('data_quality.view_reports')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.export', array_merge(request()->query(), $localeQuery)) }}">{{ __('data_quality.actions.export') }}</a>@endcan</nav>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('data_quality.inbox.title') }}">
        @foreach(['records_attention','copies_attention','critical_objects','high_objects'] as $key)
            <div class="admin-card"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('data_quality.stats.'.$key) }}</div><div class="mt-2 font-headline text-4xl text-primary">{{ number_format((int)$stats[$key], 0, ',', ' ') }}</div></div>
        @endforeach
    </section>

    <section class="admin-card">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><div class="admin-label">{{ __('data_quality.coverage.records') }}</div><strong class="text-lg">{{ number_format($coverage['records_checked'], 0, ',', ' ') }} / {{ number_format($coverage['records_total'], 0, ',', ' ') }}</strong></div>
            <div><div class="admin-label">{{ __('data_quality.coverage.copies') }}</div><strong class="text-lg">{{ number_format($coverage['copies_checked'], 0, ',', ' ') }} / {{ number_format($coverage['copies_total'], 0, ',', ' ') }}</strong></div>
            <div><div class="admin-label">{{ __('data_quality.stats.clean_percent') }}</div><strong class="text-lg">{{ $stats['clean_percent'] === null ? '—' : $stats['clean_percent'].'%' }}</strong>@if($stats['clean_percent'] === null)<p class="mt-1 text-xs text-amber-700">{{ __('data_quality.coverage.not_scanned') }}</p>@endif</div>
            <div><div class="admin-label">{{ __('data_quality.coverage.last_scan') }}</div><strong class="text-sm">{{ $coverage['last_scan']?->timezone(config('app.library_timezone'))->format('d.m.Y H:i') ?? __('data_quality.coverage.never') }}</strong><div class="mt-1 text-xs text-slate-500">{{ __('data_quality.coverage.rules') }}: {{ $coverage['active_rules'] }}</div></div>
        </div>
        <details class="mt-5 border-t pt-4 text-sm text-slate-600"><summary class="cursor-pointer font-semibold text-secondary">{{ __('data_quality.stats.raw_findings') }}: {{ number_format($stats['raw_findings'], 0, ',', ' ') }}</summary><p class="mt-2">{{ __('data_quality.analytics.raw_help') }}</p></details>
    </section>

    @can('data_quality.scan')
        <details class="admin-card"><summary class="cursor-pointer font-semibold text-primary">{{ __('data_quality.actions.queue_scan') }}</summary><div class="mt-4 flex flex-wrap items-end gap-4"><form method="POST" action="{{ route('librarian.data-quality.scans.store') }}" class="flex flex-wrap items-end gap-3">@csrf<label><span class="admin-label">{{ __('data_quality.fields.scope') }}</span><select class="admin-input" name="scope"><option value="all">{{ __('common.filters.all') }}</option>@foreach(array_keys(\App\Services\DataQuality\DataQualityScanner::SCOPES) as $scope)<option value="{{ $scope }}">{{ __('data_quality.entities.'.\App\Services\DataQuality\DataQualityScanner::ENTITY_TYPES[$scope]) }}</option>@endforeach</select></label><button class="admin-btn admin-btn-primary">{{ __('data_quality.actions.queue_scan') }}</button></form><p class="max-w-xl text-xs text-slate-500">{{ __('data_quality.coverage.scan_help') }}</p></div></details>
    @endcan

    <section class="admin-card">
        <h2 class="font-headline text-2xl text-primary">{{ __('data_quality.priorities.title') }}</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@foreach(['p1','p2','p3','p4'] as $priority)<a class="rounded-xl border p-4 transition hover:border-secondary hover:bg-secondary-container/10" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['priority' => $priority, 'work_type' => $priority === 'p4' ? 'recommendation' : null])) }}"><span class="text-xs font-bold uppercase tracking-widest text-secondary">{{ strtoupper($priority) }}</span><strong class="mt-1 block text-sm text-primary">{{ __('data_quality.priorities.'.$priority) }}</strong><span class="mt-2 block font-headline text-2xl">{{ number_format($priorityCounts[$priority] ?? 0, 0, ',', ' ') }}</span></a>@endforeach</div>
    </section>

    <section>
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="font-headline text-2xl text-primary">{{ __('data_quality.inbox.title') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('data_quality.inbox.objects_note') }}</p></div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', $localeQuery) }}">{{ __('data_quality.inbox.all') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['mine' => 1])) }}">{{ __('data_quality.inbox.mine') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['severity' => 'critical'])) }}">{{ __('data_quality.inbox.critical') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['category' => 'encoding'])) }}">{{ __('data_quality.inbox.encoding') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['category' => 'duplicates'])) }}">{{ __('data_quality.inbox.duplicates') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['category' => 'udc'])) }}">{{ __('data_quality.inbox.udc') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['entity_type' => 'book_copy'])) }}">{{ __('data_quality.inbox.copies') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['work_type' => 'recommendation'])) }}">{{ __('data_quality.inbox.recommendations') }}</a><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', array_merge($localeQuery, ['status' => 'resolved'])) }}">{{ __('data_quality.inbox.resolved') }}</a>
            </nav>
        </div>
        @if(!request()->hasAny(['work_type','status']))<p class="mt-3 rounded-lg bg-slate-100 px-4 py-3 text-xs text-slate-600">{{ __('data_quality.inbox.default_help') }}</p>@endif
    </section>

    <form method="GET" class="admin-card grid gap-4 md:grid-cols-4">
        <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
        <label class="md:col-span-2"><span class="admin-label">{{ __('data_quality.fields.search') }}</span><input class="admin-input" name="q" value="{{ request('q') }}" placeholder="{{ __('data_quality.fields.search_hint') }}"></label>
        <label><span class="admin-label">{{ __('data_quality.fields.entity') }}</span><select class="admin-input" name="entity_type"><option value=""></option>@foreach(['bibliographic_record','book_copy','reader_profile','loan','fine','reservation'] as $entity)<option value="{{ $entity }}" @selected(request('entity_type') === $entity)>{{ __('data_quality.entities.'.$entity) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.fields.category') }}</span><select class="admin-input" name="category"><option value=""></option>@foreach(collect($ruleCatalogue)->pluck('category')->unique()->sort() as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ __('data_quality.categories.'.$category) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.fields.severity') }}</span><select class="admin-input" name="severity"><option value=""></option>@foreach(\App\Models\DataQualityIssue::SEVERITIES as $severity)<option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ __('data_quality.severity.'.$severity) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.fields.status') }}</span><select class="admin-input" name="status"><option value=""></option>@foreach(\App\Models\DataQualityIssue::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('data_quality.statuses.'.$status) }}</option>@endforeach</select></label>
        <button class="admin-btn admin-btn-primary self-end">{{ __('common.actions.search') }}</button>
    </form>

    <section class="admin-card overflow-hidden p-0" data-testid="quality-object-inbox">
        <div class="hidden overflow-x-auto md:block"><table class="admin-table min-w-[900px]"><thead><tr><th>{{ __('data_quality.fields.entity') }}</th><th>{{ __('data_quality.object.findings') }}</th><th>{{ __('data_quality.fields.severity') }}</th><th>{{ __('data_quality.object.categories') }}</th><th>{{ __('data_quality.object.last_check') }}</th><th class="text-right">{{ __('common.fields.actions') }}</th></tr></thead><tbody>
        @forelse($objects as $object)
            @php($entity = $object->entity)
            <tr data-testid="quality-object-row"><td>
                @if($object->entity_type === 'bibliographic_record')<strong class="block max-w-lg text-primary">{{ $entity?->title ?? __('data_quality.entities.bibliographic_record').' №'.$object->entity_id }}</strong><span class="text-xs text-slate-500">{{ $entity?->primary_author ?: '—' }}@if($entity?->isbn) · ISBN {{ $entity->isbn }}@endif</span>
                @elseif($object->entity_type === 'book_copy')<strong class="block font-mono text-primary">{{ $entity?->inventory_number ?? __('data_quality.entities.book_copy').' №'.$object->entity_id }}</strong><span class="block max-w-lg text-xs text-slate-500">{{ $entity?->bibliographicRecord?->title ?? '—' }}</span><span class="text-xs text-slate-500">{{ $entity?->branch?->name ?? '—' }} · {{ $entity?->fund?->name ?? '—' }} · {{ $entity?->shelf_location ?? '—' }}</span>
                @else<strong>{{ __('data_quality.entities.'.$object->entity_type) }} №{{ $object->entity_id }}</strong>@endif
                <span class="mt-1 block text-[10px] text-slate-400">{{ $object->entry_issue?->issue_number }}</span>
            </td><td><strong class="font-headline text-xl">{{ $object->finding_count }}</strong></td><td><span class="rounded-full border px-2 py-1 text-xs">{{ __('data_quality.severity.'.$object->max_severity) }}</span></td><td><div class="flex max-w-sm flex-wrap gap-1">@foreach($object->categories as $category)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ __('data_quality.categories.'.$category) }}</span>@endforeach</div></td><td class="whitespace-nowrap text-sm text-slate-500">{{ $object->last_detected_at ? \Illuminate\Support\Carbon::parse($object->last_detected_at)->format('d.m.Y H:i') : '—' }}</td><td class="text-right">@if($object->entry_issue)<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.issues.show', [$object->entry_issue, 'lang' => app()->getLocale()]) }}">{{ __('data_quality.actions.open') }}</a>@endif</td></tr>
        @empty<tr><td colspan="6" class="py-12 text-center text-slate-500">{{ __('data_quality.messages.empty') }}</td></tr>@endforelse
        </tbody></table></div>

        <div class="divide-y divide-slate-200 md:hidden">
            @forelse($objects as $object)
                @php($entity = $object->entity)
                <article class="space-y-3 p-4" data-testid="quality-object-card">
                    <div>
                        @if($object->entity_type === 'bibliographic_record')
                            <strong class="block break-words text-sm text-primary">{{ $entity?->title ?? __('data_quality.entities.bibliographic_record').' №'.$object->entity_id }}</strong>
                            <span class="mt-1 block text-xs text-slate-500">{{ $entity?->primary_author ?: '—' }}@if($entity?->isbn) · ISBN {{ $entity->isbn }}@endif</span>
                        @elseif($object->entity_type === 'book_copy')
                            <strong class="block break-all font-mono text-sm text-primary">{{ $entity?->inventory_number ?? __('data_quality.entities.book_copy').' №'.$object->entity_id }}</strong>
                            <span class="mt-1 block break-words text-xs text-slate-500">{{ $entity?->bibliographicRecord?->title ?? '—' }}</span>
                            <span class="mt-1 block break-words text-xs text-slate-500">{{ $entity?->branch?->name ?? '—' }} · {{ $entity?->fund?->name ?? '—' }} · {{ $entity?->shelf_location ?? '—' }}</span>
                        @else
                            <strong class="block break-words text-sm text-primary">{{ __('data_quality.entities.'.$object->entity_type) }} №{{ $object->entity_id }}</strong>
                        @endif
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-xs">
                        <div><dt class="text-slate-500">{{ __('data_quality.object.findings') }}</dt><dd class="mt-1 font-headline text-xl text-primary">{{ $object->finding_count }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('data_quality.fields.severity') }}</dt><dd class="mt-1"><span class="inline-flex rounded-full border px-2 py-1">{{ __('data_quality.severity.'.$object->max_severity) }}</span></dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-1">@foreach($object->categories as $category)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ __('data_quality.categories.'.$category) }}</span>@endforeach</div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                        <span class="text-xs text-slate-500">{{ __('data_quality.object.last_check') }}: {{ $object->last_detected_at ? \Illuminate\Support\Carbon::parse($object->last_detected_at)->format('d.m.Y H:i') : '—' }}</span>
                        @if($object->entry_issue)<a class="admin-btn admin-btn-secondary shrink-0" href="{{ route('librarian.data-quality.issues.show', [$object->entry_issue, 'lang' => app()->getLocale()]) }}">{{ __('data_quality.actions.open') }}</a>@endif
                    </div>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-slate-500">{{ __('data_quality.messages.empty') }}</p>
            @endforelse
        </div>
        <div class="p-4"><x-admin.pagination :paginator="$objects" /></div>
    </section>

    <section class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ __('data_quality.analytics.title') }}</h2><div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('data_quality.analytics.problem') }}</th><th>{{ __('data_quality.analytics.objects') }}</th><th>{{ __('data_quality.analytics.findings') }}</th></tr></thead><tbody>@foreach($distributions['rules'] as $row)<tr><td>{{ __('data_quality.rules.'.$row->rule_code) }}</td><td>{{ number_format($row->objects, 0, ',', ' ') }}</td><td>{{ number_format($row->total, 0, ',', ' ') }}</td></tr>@endforeach</tbody></table></div></section>
</div>
@endsection
