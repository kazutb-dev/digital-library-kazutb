@extends('layouts.admin')

@section('title', __('library_recovery.conflicts.detail_title', ['id' => $conflict->id]).' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.conflicts.detail_title', ['id' => $conflict->id])" :subtitle="__('library_recovery.conflicts.note')" />
    @include('admin.library-recovery._nav', ['canManage' => $canManage])

    <section class="admin-card mb-7">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                __('library_recovery.conflicts.entity_type') => $conflict->entity_type,
                __('library_recovery.conflicts.entity_id') => $conflict->entity_id,
                __('library_recovery.conflicts.source_id') => $conflict->source_id,
                __('library_recovery.conflicts.field_name') => $conflict->field_name,
                __('library_recovery.conflicts.status') => $conflict->status,
                __('library_recovery.conflicts.reason') => $conflict->reason,
                __('library_recovery.conflicts.batch') => $conflict->batch ? ($conflict->batch->id.' · '.$conflict->batch->package_name) : $conflict->legacy_import_batch_id,
                __('library_recovery.conflicts.resolver') => $conflict->resolver?->name,
                __('library_recovery.conflicts.resolved_at') => $conflict->resolved_at?->format('Y-m-d H:i:s'),
            ] as $label => $value)<div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-semibold">{{ filled($value) ? $value : '—' }}</dd></div>@endforeach
        </dl>
        @if($conflict->batch)<div class="mt-5 rounded-xl bg-surface-low p-4"><p class="text-xs text-slate-500">{{ __('library_recovery.batches.sha256') }}</p><p class="mt-1 break-all font-mono text-xs">{{ $conflict->batch->package_sha256 }}</p></div>@endif
    </section>

    <div class="mb-7 grid grid-cols-1 gap-7 xl:grid-cols-2">
        <section class="admin-card min-w-0"><h2 class="font-headline text-2xl text-primary">{{ __('library_recovery.conflicts.current_value') }}</h2><pre class="mt-4 max-h-[32rem] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100">{{ $conflict->current_value ?: '—' }}</pre></section>
        <section class="admin-card min-w-0"><h2 class="font-headline text-2xl text-primary">{{ __('library_recovery.conflicts.incoming_value') }}</h2><pre class="mt-4 max-h-[32rem] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100">{{ $conflict->incoming_value ?: '—' }}</pre></section>
    </div>

    @if(filled($conflict->resolution_note))<section class="admin-card mb-7"><h2 class="font-headline text-2xl text-primary">{{ __('library_recovery.conflicts.resolution_note') }}</h2><p class="mt-3 whitespace-pre-wrap text-sm leading-6">{{ $conflict->resolution_note }}</p></section>@endif

    @if($canManage && $conflict->status === 'open' && filled($links['review'] ?? null))
        <aside class="rounded-xl border border-cyan-200 bg-cyan-50 p-5 text-cyan-950"><p class="text-sm leading-6">{{ __('library_recovery.conflicts.review_prompt') }}</p><a class="admin-btn admin-btn-primary mt-4" href="{{ $links['review'] }}?queue=conflicts">{{ __('library_recovery.nav.review') }}</a></aside>
    @endif
@endsection
