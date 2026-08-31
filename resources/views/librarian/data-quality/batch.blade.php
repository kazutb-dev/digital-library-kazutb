@extends('layouts.librarian')
@section('title', $batch->batch_number.' — '.__('data_quality.title'))
@section('content')
<div class="space-y-6">
    <header><a class="text-sm text-secondary" href="{{ route('librarian.data-quality.index', ['lang' => app()->getLocale()]) }}">← {{ __('data_quality.title') }}</a><h1 class="mt-2 font-headline text-4xl text-primary">{{ $batch->batch_number }}</h1><div class="mt-3 flex flex-wrap gap-2 text-sm"><span class="rounded-full bg-slate-100 px-3 py-1">{{ __('data_quality.batches.operations.'.$batch->operation_type) }}</span><span class="rounded-full bg-amber-50 px-3 py-1 font-semibold">{{ __('data_quality.batches.statuses.'.$batch->status) }}</span></div>@if($batch->reason)<p class="mt-3 text-sm text-slate-600">{{ $batch->reason }}</p>@endif</header>
    <section class="space-y-4">
        @forelse($batch->items as $item)
            <article class="admin-card"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold">{{ __('data_quality.batches.record') }} №{{ $item->entity_id }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ __('data_quality.batches.statuses.'.$item->status) }}</span></div><div class="mt-4 grid gap-4 lg:grid-cols-2"><div><h3 class="admin-label">{{ __('data_quality.batches.before') }}</h3><dl class="mt-2 space-y-2 rounded-xl bg-red-50 p-4 text-sm">@foreach((array)$item->before_snapshot as $field => $value)<div><dt class="text-xs text-slate-500">{{ __('data_quality.batches.fields.'.$field) }}</dt><dd class="break-words">{{ $value === null || $value === '' ? '—' : $value }}</dd></div>@endforeach</dl></div><div><h3 class="admin-label">{{ __('data_quality.batches.after') }}</h3><dl class="mt-2 space-y-2 rounded-xl bg-emerald-50 p-4 text-sm">@foreach((array)$item->after_snapshot as $field => $value)<div><dt class="text-xs text-slate-500">{{ __('data_quality.batches.fields.'.$field) }}</dt><dd class="break-words">{{ $value === null || $value === '' ? '—' : $value }}</dd></div>@endforeach</dl></div></div>@if($item->error_message)<p class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-800"><strong>{{ __('data_quality.batches.error') }}:</strong> {{ $item->error_message }}</p>@endif</article>
        @empty
            <div class="admin-card text-center text-slate-500">{{ __('common.empty') }}</div>
        @endforelse
    </section>
    <div class="flex gap-3">
        @can('data_quality.approve_bulk')
            @if($batch->status === 'previewed')
                <form method="POST" action="{{ route('librarian.data-quality.batches.approve', $batch) }}">
                    @csrf
                    <button class="admin-btn admin-btn-secondary">{{ __('data_quality.actions.approve') }}</button>
                </form>
            @endif
        @endcan
        @can('data_quality.bulk_edit')
            @if(in_array($batch->status, ['approved', 'previewed']))
                <form method="POST" action="{{ route('librarian.data-quality.batches.execute', $batch) }}">
                    @csrf
                    <button class="admin-btn admin-btn-primary">{{ __('data_quality.actions.execute') }}</button>
                </form>
            @endif
            @if(in_array($batch->status, ['completed', 'partially_completed']))
                <form method="POST" action="{{ route('librarian.data-quality.batches.rollback', $batch) }}">
                    @csrf
                    <button class="admin-btn admin-btn-secondary">{{ __('data_quality.actions.rollback') }}</button>
                </form>
            @endif
        @endcan
    </div>
</div>
@endsection
