@extends('layouts.librarian')
@section('title', __('data_quality.nav.imports').' — '.__('data_quality.title'))
@section('content')
<div class="space-y-6">
    <header>
        <a class="text-sm text-secondary" href="{{ route('librarian.data-quality.index', ['lang' => app()->getLocale()]) }}">← {{ __('data_quality.title') }}</a>
        <h1 class="mt-2 font-headline text-4xl text-primary">{{ __('data_quality.nav.imports') }}</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('data_quality.imports.help') }}</p>
    </header>

    <form class="admin-card grid gap-4 md:grid-cols-2 xl:grid-cols-5" method="POST" enctype="multipart/form-data" action="{{ route('librarian.data-quality.imports.upload') }}">
        @csrf
        <label><span class="admin-label">{{ __('data_quality.imports.choose_file') }}</span><input class="admin-input" type="file" name="file" required></label>
        <label><span class="admin-label">{{ __('data_quality.imports.format_encoding') }}</span><select class="admin-input" name="source_format">@foreach($formats as $format)<option value="{{ $format }}">{{ strtoupper($format) }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.imports.default_mapping') }}</span><select class="admin-input" name="mapping_profile_id"><option value="">{{ __('data_quality.imports.default_mapping') }}</option>@foreach($profiles as $profile)<option value="{{ $profile->id }}">{{ $profile->name }} · v{{ $profile->version }}</option>@endforeach</select></label>
        <label><span class="admin-label">{{ __('data_quality.imports.detect_encoding') }}</span><select class="admin-input" name="encoding"><option value="">{{ __('data_quality.imports.detect_encoding') }}</option><option>UTF-8</option><option>Windows-1251</option></select></label>
        <button class="admin-btn admin-btn-primary self-end">{{ __('data_quality.actions.upload') }}</button>
    </form>

    <section class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>{{ __('data_quality.imports.batch') }}</th><th>{{ __('data_quality.imports.file') }}</th><th>{{ __('data_quality.imports.format_encoding') }}</th><th>{{ __('data_quality.fields.status') }}</th><th>{{ __('data_quality.imports.rows') }}</th><th>{{ __('data_quality.imports.result') }}</th></tr></thead>
            <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td><a class="font-semibold text-secondary" href="{{ route('librarian.data-quality.imports.show', [$batch, 'lang' => app()->getLocale()]) }}">{{ $batch->batch_number }}</a></td>
                        <td>{{ $batch->source_filename }}</td>
                        <td>{{ strtoupper($batch->source_format) }} · {{ $batch->detected_encoding }} <span class="text-xs text-slate-500">({{ $batch->encoding_confidence }}%)</span></td>
                        <td>{{ __('data_quality.imports.statuses.'.$batch->status) }}</td>
                        <td>{{ $batch->rows_total }} / {{ $batch->rows_imported }}</td>
                        <td>@if($batch->reconciliation)<dl class="space-y-1 text-xs">@foreach($batch->reconciliation as $key => $value)<div class="flex justify-between gap-4"><dt>{{ __('data_quality.imports.reconciliation.'.$key) }}</dt><dd class="font-semibold">{{ $value }}</dd></div>@endforeach</dl>@else<span class="text-slate-400">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('common.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $batches->links() }}
</div>
@endsection
