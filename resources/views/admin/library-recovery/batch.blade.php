@extends('layouts.admin')

@section('title', __('library_recovery.batches.detail_title', ['id' => $batch->id]).' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.batches.detail_title', ['id' => $batch->id])" :subtitle="$batch->package_name" />
    @include('admin.library-recovery._nav')
    @php $rv = fn (string $g, ?string $v): string => $v && trans()->has('library_recovery.values.'.$g.'.'.$v) ? __('library_recovery.values.'.$g.'.'.$v) : ($v ?: '—'); @endphp

    <section class="admin-card mb-7">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><p class="text-xs text-slate-500">{{ __('library_recovery.batches.status') }}</p><strong class="mt-1 block">{{ $batch->status }}</strong></div>
            <div><p class="text-xs text-slate-500">{{ __('library_recovery.batches.source') }}</p><strong class="mt-1 block">{{ $batch->source_system }} · {{ $batch->source_database ?: '—' }}</strong></div>
            <div><p class="text-xs text-slate-500">{{ __('library_recovery.batches.bytes') }}</p><strong class="mt-1 block">{{ $batch->package_bytes === null ? '—' : number_format($batch->package_bytes, 0, ',', ' ') }}</strong></div>
            <div><p class="text-xs text-slate-500">{{ __('library_recovery.batches.started_at') }}</p><strong class="mt-1 block">{{ $batch->started_at?->format('Y-m-d H:i:s') ?: '—' }}</strong></div>
        </div>
        <div class="mt-5 rounded-xl bg-surface-low p-4"><p class="text-xs text-slate-500">{{ __('library_recovery.batches.sha256') }}</p><p class="mt-1 break-all font-mono text-xs">{{ $batch->package_sha256 }}</p></div>
    </section>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.batches.matches') }}</h2>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach (['documents', 'copies', 'fields'] as $kind)
                <article class="rounded-xl border {{ $matches[$kind] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5">
                    <h3 class="font-bold">{{ __('library_recovery.batches.'.$kind) }}</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><span class="block text-xs text-slate-500">{{ __('library_recovery.batches.expected') }}</span><strong class="text-xl">{{ number_format((int) $batch->{$kind.'_expected'}, 0, ',', ' ') }}</strong></div><div><span class="block text-xs text-slate-500">{{ __('library_recovery.batches.loaded') }}</span><strong class="text-xl">{{ number_format((int) $batch->{$kind.'_loaded'}, 0, ',', ' ') }}</strong></div></div>
                </article>
            @endforeach
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-5">
            @foreach ($counts as $key => $count)<div class="rounded-xl bg-surface-low p-4"><span class="block text-xs text-slate-500">{{ __('library_recovery.tables.labels.'.$key) }}</span><strong class="mt-1 block text-xl">{{ $count === null ? '—' : number_format($count, 0, ',', ' ') }}</strong></div>@endforeach
        </div>
    </section>

    <div class="mb-7 grid grid-cols-1 gap-7 xl:grid-cols-3">
        @foreach ([['validation', $batch->validation], ['reconciliation', $batch->reconciliation], ['apply_stats', $batch->apply_stats]] as [$label, $value])
            <section class="admin-card min-w-0"><h2 class="mb-4 font-headline text-2xl text-primary">{{ __('library_recovery.batches.'.$label) }}</h2>@include('admin.library-recovery._json', ['value' => $value])</section>
        @endforeach
    </div>

    <section class="admin-card mb-7">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.batches.recent_records') }}</h2>
        <div class="mt-5 overflow-x-auto"><table class="admin-table min-w-[850px]"><thead><tr><th>ID</th><th>{{ __('library_recovery.raw.source_doc_id') }}</th><th>{{ __('library_recovery.raw.control_number') }}</th><th>{{ __('library_recovery.raw.mapping_status') }}</th><th>{{ __('library_recovery.raw.apply_status') }}</th><th>{{ __('library_recovery.raw.source_hash') }}</th><th></th></tr></thead><tbody>
            @forelse($rawRecords as $record)<tr><td>{{ $record->id }}</td><td>{{ $record->source_doc_id }}</td><td>{{ $record->control_number ?: '—' }}</td><td>{{ $rv('mapping_status', $record->mapping_status) }}</td><td>{{ $rv('apply_status', $record->apply_status) }}</td><td class="max-w-xs break-all font-mono text-[11px]">{{ $record->source_hash }}</td><td><a href="{{ $record->detail_url }}" class="font-bold text-secondary hover:underline">{{ __('library_recovery.view') }}</a></td></tr>@empty<tr><td colspan="7" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <div class="grid grid-cols-1 gap-7 xl:grid-cols-2">
        @foreach ([['quarantine', $quarantineSummary, ['kind', 'status']], ['conflicts', $conflictSummary, ['entity_type', 'field_name', 'status']]] as [$kind, $summary, $columns])
            <section class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ __('library_recovery.'.$kind.'.title') }}</h2><div class="mt-4 space-y-2">@forelse($summary as $row)<div class="flex items-center justify-between gap-3 rounded-lg bg-surface-low px-3 py-2 text-sm"><span>@foreach($columns as $column){{ $row->{$column} ?: '∅' }}{{ !$loop->last ? ' · ' : '' }}@endforeach</span><strong>{{ $row->total }}</strong></div>@empty<p class="text-slate-500">{{ __('library_recovery.empty') }}</p>@endforelse</div></section>
        @endforeach
    </div>
@endsection
