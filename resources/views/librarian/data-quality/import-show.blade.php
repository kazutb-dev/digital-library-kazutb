@extends('layouts.librarian')
@section('title', $batch->batch_number.' — '.__('data_quality.nav.imports'))
@section('content')
<div class="space-y-6">
    <header>
        <a class="text-sm text-secondary" href="{{ route('librarian.data-quality.imports', ['lang' => app()->getLocale()]) }}">← {{ __('data_quality.nav.imports') }}</a>
        <h1 class="mt-2 font-headline text-4xl text-primary">{{ $batch->batch_number }}</h1>
        <div class="mt-3 flex flex-wrap gap-2 text-sm"><span class="rounded-full bg-slate-100 px-3 py-1">{{ $batch->source_filename }}</span><span class="rounded-full bg-amber-50 px-3 py-1 font-semibold">{{ __('data_quality.imports.statuses.'.$batch->status) }}</span></div>
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">{{ __('data_quality.imports.safety_notice') }}</p>
        <p class="mt-3 text-sm text-slate-600">{{ __('data_quality.imports.detected_encoding', ['detected' => $batch->detected_encoding, 'selected' => $batch->selected_encoding]) }}</p>
        <details class="mt-2 text-xs text-slate-500"><summary class="cursor-pointer">{{ __('data_quality.fields.technical_details') }}</summary><p class="mt-2 font-mono">{{ __('data_quality.imports.checksum') }}: {{ $batch->checksum }}</p></details>
    </header>

    <section class="space-y-4">
        @forelse($batch->rows as $row)
            <article class="admin-card">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold text-primary">{{ __('data_quality.imports.row') }} {{ $row->source_row_id }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ __('data_quality.imports.statuses.'.$row->status) }}</span></div>
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    <div><h3 class="admin-label">{{ __('data_quality.imports.normalized') }}</h3><dl class="mt-2 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">@foreach((array)$row->normalized_payload as $field => $value)<div><dt class="text-xs text-slate-500">{{ Illuminate\Support\Facades\Lang::has('librarian.catalog.fields.'.$field) ? __('librarian.catalog.fields.'.$field) : str_replace('_', ' ', $field) }}</dt><dd class="break-words">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd></div>@endforeach</dl></div>
                    <div><h3 class="admin-label">{{ __('data_quality.imports.errors') }}</h3><div class="mt-2 rounded-xl bg-red-50 p-4 text-sm">@forelse((array)$row->validation_errors as $error)<p>{{ Illuminate\Support\Facades\Lang::has('data_quality.imports.validation.'.$error) ? __('data_quality.imports.validation.'.$error) : $error }}</p>@empty<p class="text-emerald-700">{{ __('data_quality.imports.no_errors') }}</p>@endforelse</div></div>
                    <div><h3 class="admin-label">{{ __('data_quality.imports.duplicates') }}</h3><div class="mt-2 rounded-xl bg-amber-50 p-4 text-sm">@forelse((array)$row->duplicate_candidates as $candidate)<p>№{{ data_get($candidate, 'id') }} · {{ data_get($candidate, 'score') }}% · {{ __('data_quality.duplicates.match.'.data_get($candidate, 'level', 'possible')) }}</p>@empty<p class="text-emerald-700">{{ __('data_quality.imports.no_duplicates') }}</p>@endforelse</div></div>
                </div>
                <form class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4" method="POST" action="{{ route('librarian.data-quality.imports.rows.decision', $row) }}">@csrf<label class="min-w-64"><span class="admin-label">{{ __('data_quality.imports.decision') }}</span><select class="admin-input" name="action">@foreach(['review','create','update','skip'] as $action)<option value="{{ $action }}" @selected($row->proposed_action===$action)>{{ __('data_quality.imports.actions.'.$action) }}</option>@endforeach</select></label><button class="admin-btn admin-btn-secondary">{{ __('data_quality.imports.save_decision') }}</button></form>
            </article>
        @empty
            <div class="admin-card text-center text-slate-500">{{ __('common.empty') }}</div>
        @endforelse
    </section>

    <div class="flex gap-3">
        @can('data_quality.approve_import')
            @if(in_array($batch->status, ['staged', 'review_required', 'validation_failed']))
                <form method="POST" action="{{ route('librarian.data-quality.imports.approve', $batch) }}">
                    @csrf
                    <button class="admin-btn admin-btn-secondary">{{ __('data_quality.actions.approve') }}</button>
                </form>
            @endif
        @endcan
        @can('data_quality.import')
            @if($batch->status === 'ready')
                <form method="POST" action="{{ route('librarian.data-quality.imports.execute', $batch) }}">
                    @csrf
                    <button class="admin-btn admin-btn-primary">{{ __('data_quality.actions.execute') }}</button>
                </form>
            @endif
        @endcan
    </div>
</div>
@endsection
