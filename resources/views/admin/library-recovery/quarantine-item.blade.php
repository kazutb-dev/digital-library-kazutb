@extends('layouts.admin')

@section('title', __('library_recovery.quarantine.detail_title', ['id' => $row->id]).' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.quarantine.detail_title', ['id' => $row->id])" :subtitle="__('library_recovery.quarantine.note')" />
    @include('admin.library-recovery._nav', ['canManage' => $canManage])

    <section class="admin-card mb-7">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                __('library_recovery.quarantine.kind') => $row->kind,
                __('library_recovery.quarantine.status') => $row->status,
                __('library_recovery.quarantine.batch') => $row->batch ? ($row->batch->id.' · '.$row->batch->package_name) : $row->legacy_import_batch_id,
                __('library_recovery.quarantine.source_doc_id') => $row->source_doc_id,
                __('library_recovery.quarantine.source_inv_id') => $row->source_inv_id,
                __('library_recovery.quarantine.reason') => $row->reason,
            ] as $label => $value)<div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-semibold">{{ filled($value) ? $value : '—' }}</dd></div>@endforeach
        </dl>
        @if($row->batch)<div class="mt-5 rounded-xl bg-surface-low p-4"><p class="text-xs text-slate-500">{{ __('library_recovery.batches.sha256') }}</p><p class="mt-1 break-all font-mono text-xs">{{ $row->batch->package_sha256 }}</p></div>@endif
    </section>

    <section class="admin-card mb-7 min-w-0"><h2 class="mb-4 font-headline text-2xl text-primary">{{ __('library_recovery.quarantine.payload') }}</h2>@include('admin.library-recovery._json', ['value' => $row->payload])</section>

    @if($canManage && filled($links['review'] ?? null))
        <aside class="rounded-xl border border-cyan-200 bg-cyan-50 p-5 text-cyan-950"><p class="text-sm leading-6">{{ __('library_recovery.quarantine.review_prompt') }}</p><a class="admin-btn admin-btn-primary mt-4" href="{{ $links['review'] }}?queue=quarantine">{{ __('library_recovery.nav.review') }}</a></aside>
    @endif
@endsection
