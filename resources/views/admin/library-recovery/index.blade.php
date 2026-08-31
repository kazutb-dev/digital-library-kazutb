@extends('layouts.admin')

@section('title', __('library_recovery.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.title')" :subtitle="__('library_recovery.subtitle')" />
    @include('admin.library-recovery._nav', ['canManage' => $canManage])

    <p class="mb-7 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
        <span class="material-symbols-outlined text-[20px]">lock</span>
        {{ __('library_recovery.read_only_notice') }}
    </p>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.health.title') }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('library_recovery.health.note') }}</p>
        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($health as $key => $count)
                <article class="rounded-xl border {{ $count === null ? 'border-slate-200 bg-slate-50' : ((int) $count > 0 ? 'border-amber-200 bg-amber-50' : 'border-emerald-100 bg-emerald-50') }} p-4">
                    <p class="text-xs font-semibold leading-5 text-slate-600">{{ __('library_recovery.health.labels.'.$key) }}</p>
                    <strong class="mt-2 block font-headline text-3xl text-primary">{{ $count === null ? '—' : number_format($count, 0, ',', ' ') }}</strong>
                </article>
            @endforeach
        </div>
    </section>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.tables.title') }}</h2>
        <div class="mt-5 overflow-x-auto">
            <table class="admin-table min-w-[680px]">
                <thead><tr><th>{{ __('library_recovery.tables.table') }}</th><th>{{ __('library_recovery.tables.available') }}</th><th>{{ __('library_recovery.tables.rows') }}</th><th>SQL</th></tr></thead>
                <tbody>
                    @foreach ($tables as $table)
                        <tr>
                            <td class="font-semibold">{{ __('library_recovery.tables.labels.'.$table['label']) }}</td>
                            <td><span class="rounded-full px-3 py-1 text-xs font-bold {{ $table['available'] ? 'bg-emerald-100 text-emerald-900' : 'bg-red-100 text-red-900' }}">{{ $table['available'] ? __('library_recovery.yes') : __('library_recovery.no') }}</span></td>
                            <td>{{ $table['count'] === null ? '—' : number_format($table['count'], 0, ',', ' ') }}</td>
                            <td class="font-mono text-xs text-slate-500">{{ $table['table'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card mb-7">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.batches.title') }}</h2></div>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="admin-table min-w-[1100px]">
                <thead><tr><th>ID</th><th>{{ __('library_recovery.batches.package') }}</th><th>{{ __('library_recovery.batches.status') }}</th><th>{{ __('library_recovery.batches.documents') }}</th><th>{{ __('library_recovery.batches.copies') }}</th><th>{{ __('library_recovery.batches.fields') }}</th><th>{{ __('library_recovery.tables.labels.quarantine') }}</th><th>{{ __('library_recovery.tables.labels.conflicts') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($batches as $row)
                        @php($batch = $row['model'])
                        <tr>
                            <td class="font-mono">{{ $batch->id }}</td>
                            <td class="max-w-xs"><strong class="block break-words">{{ $batch->package_name }}</strong><span class="mt-1 block break-all font-mono text-[11px] text-slate-500">{{ $batch->package_sha256 }}</span></td>
                            <td><span class="rounded-full bg-surface-low px-3 py-1 text-xs font-bold">{{ $batch->status }}</span></td>
                            @foreach (['documents', 'copies', 'fields'] as $kind)
                                <td class="whitespace-nowrap">
                                    {{ number_format((int) $batch->{$kind.'_loaded'}, 0, ',', ' ') }} / {{ number_format((int) $batch->{$kind.'_expected'}, 0, ',', ' ') }}
                                    <span class="ml-1 {{ $row['matches'][$kind] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $row['matches'][$kind] ? __('library_recovery.batches.matches') : __('library_recovery.batches.mismatch') }}</span>
                                </td>
                            @endforeach
                            <td>{{ number_format($row['counts']['quarantine'], 0, ',', ' ') }}</td>
                            <td>{{ number_format($row['counts']['conflicts'], 0, ',', ' ') }}</td>
                            <td><a class="font-bold text-secondary hover:underline" href="{{ $row['detail_url'] }}">{{ __('library_recovery.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.mapping.title') }}</h2>
        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
            @foreach (['records', 'copies'] as $kind)
                <div class="rounded-xl border border-slate-200 p-5">
                    <h3 class="font-bold text-primary">{{ __('library_recovery.mapping.'.$kind) }}</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($mappingSummary[$kind] as $row)
                            <div class="flex items-center justify-between gap-4 rounded-lg bg-surface-low px-3 py-2 text-sm">
                                <span class="min-w-0 break-words">
                                    {{ $kind === 'records' ? ($row->mapping_status ?: '∅') : ($row->relation_status ?: '∅') }} · {{ $row->apply_status ?: '∅' }}
                                </span>
                                <strong>{{ number_format($row->total, 0, ',', ' ') }}</strong>
                            </div>
                        @empty<p class="text-sm text-slate-500">{{ __('library_recovery.empty') }}</p>@endforelse
                    </div>
                </div>
            @endforeach
            <div class="rounded-xl border border-slate-200 p-5">
                <h3 class="font-bold text-primary">{{ __('library_recovery.mapping.sources') }}</h3>
                <div class="mt-4 space-y-2">
                    @forelse ($sourceSummary as $row)
                        <div class="flex items-center justify-between gap-4 rounded-lg bg-surface-low px-3 py-2 text-sm"><span>{{ $row->source_system ?: '∅' }} · {{ $row->source_database ?: '∅' }}</span><strong>{{ $row->total }}</strong></div>
                    @empty<p class="text-sm text-slate-500">{{ __('library_recovery.empty') }}</p>@endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.semantics.title') }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('library_recovery.semantics.note') }}</p>
        <div class="mt-5 overflow-x-auto">
            <table class="admin-table min-w-[800px]">
                <thead><tr><th>{{ __('library_recovery.semantics.source_field') }}</th><th>{{ __('library_recovery.semantics.meaning') }}</th><th>{{ __('library_recovery.semantics.handling') }}</th></tr></thead>
                <tbody>@foreach (__('library_recovery.semantics.items') as $item)<tr><td class="whitespace-nowrap font-mono text-xs">{{ $item['field'] }}</td><td>{{ $item['meaning'] }}</td><td>{{ $item['handling'] }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </section>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.ksu.title') }}</h2>
        <div class="mt-5 space-y-5">
            @forelse ($ksuBooks as $book)
                <article class="rounded-xl border border-slate-200 p-5">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                        <div>
                            <h3 class="font-headline text-2xl text-primary">{{ $book->code }} · {{ $book->name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $book->description }}</p>
                            @if(filled($book->numbering_rule_evidence))<p class="mt-3 rounded-lg bg-surface-low p-3 text-sm"><strong>{{ __('library_recovery.ksu.evidence') }}:</strong> {{ $book->numbering_rule_evidence }}</p>@endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                            @foreach ([__('library_recovery.ksu.auto') => $book->auto_numbering_enabled, __('library_recovery.ksu.manual') => $book->requires_manual_decision, __('library_recovery.ksu.active') => $book->is_active] as $label => $enabled)
                                <div class="rounded-lg bg-surface-low p-3"><span class="block text-slate-500">{{ $label }}</span><strong>{{ $enabled ? __('library_recovery.yes') : __('library_recovery.no') }}</strong></div>
                            @endforeach
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                        <div><dt class="text-xs text-slate-500">{{ __('library_recovery.ksu.source') }}</dt><dd class="font-mono">{{ $book->legacy_source_table ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('library_recovery.ksu.format') }}</dt><dd>{{ $book->numbering_format ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('library_recovery.ksu.entries') }}</dt><dd class="font-bold">{{ $book->entries_count ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('library_recovery.ksu.conflicts') }}</dt><dd class="font-bold">{{ $book->conflicts_count ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">{{ __('library_recovery.ksu.open_conflicts') }}</dt><dd class="font-bold">{{ $book->open_conflicts_count ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-5 overflow-x-auto">
                        <table class="admin-table min-w-[760px]"><thead><tr><th>{{ __('library_recovery.ksu.year') }}</th><th>{{ __('library_recovery.ksu.last_number') }}</th><th>{{ __('library_recovery.ksu.observed') }}</th><th>{{ __('library_recovery.ksu.missing') }}</th><th>{{ __('library_recovery.ksu.duplicates') }}</th><th>{{ __('library_recovery.ksu.allocation') }}</th></tr></thead><tbody>
                            @forelse ($book->sequences as $sequence)<tr><td>{{ $sequence->year }}</td><td>{{ $sequence->last_number }}</td><td>{{ $sequence->min_observed ?? '—' }}–{{ $sequence->max_observed ?? '—' }}</td><td class="font-mono text-xs">{{ implode(', ', $sequence->missing_numbers ?? []) ?: '—' }}</td><td class="font-mono text-xs">{{ implode(', ', $sequence->duplicate_numbers ?? []) ?: '—' }}</td><td>{{ $sequence->allocation_enabled ? __('library_recovery.yes') : __('library_recovery.no') }}</td></tr>@empty<tr><td colspan="6" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>@endforelse
                        </tbody></table>
                    </div>
                </article>
            @empty<p class="text-sm text-slate-500">{{ __('library_recovery.empty') }}</p>@endforelse
        </div>
    </section>

    <div class="grid grid-cols-1 gap-7 xl:grid-cols-2">
        @foreach ([['quarantine', $quarantineSummary, ['kind', 'status']], ['conflicts', $conflictSummary, ['entity_type', 'field_name', 'status']]] as [$kind, $summary, $columns])
            <section class="admin-card">
                <div class="flex items-center justify-between gap-3"><h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.'.$kind.'.title') }}</h2><a class="font-bold text-secondary hover:underline" href="{{ $links[$kind] }}">{{ __('library_recovery.view') }}</a></div>
                <div class="mt-5 space-y-2">@forelse($summary->take(12) as $row)<div class="flex items-start justify-between gap-3 rounded-lg bg-surface-low px-3 py-2 text-sm"><span class="break-words">@foreach($columns as $column){{ $row->{$column} ?: '∅' }}{{ !$loop->last ? ' · ' : '' }}@endforeach</span><strong>{{ number_format($row->total, 0, ',', ' ') }}</strong></div>@empty<p class="text-slate-500">{{ __('library_recovery.empty') }}</p>@endforelse</div>
            </section>
        @endforeach
    </div>
@endsection
