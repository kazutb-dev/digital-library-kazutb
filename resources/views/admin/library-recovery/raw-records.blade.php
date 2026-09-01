@extends('layouts.admin')

@section('title', __('library_recovery.raw.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.raw.title')" :subtitle="__('library_recovery.raw.note')" />
    @include('admin.library-recovery._nav')
    @php $rv = fn (string $g, ?string $v): string => $v && trans()->has('library_recovery.values.'.$g.'.'.$v) ? __('library_recovery.values.'.$g.'.'.$v) : ($v ?: '—'); @endphp

    <form method="GET" action="{{ $links['raw_records'] }}" class="admin-card mb-7 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
        <div class="md:col-span-2"><label class="admin-label" for="raw-q">{{ __('library_recovery.raw.search') }}</label><input id="raw-q" class="admin-input" name="q" value="{{ $filters['q'] ?? '' }}"></div>
        <div><label class="admin-label" for="raw-batch">{{ __('library_recovery.raw.batch_id') }}</label><input id="raw-batch" class="admin-input" type="number" min="1" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}"></div>
        <div><label class="admin-label" for="raw-tag">{{ __('library_recovery.raw.tag') }}</label><input id="raw-tag" class="admin-input font-mono" maxlength="8" name="tag" value="{{ $filters['tag'] ?? '' }}"></div>
        <div><label class="admin-label" for="raw-map">{{ __('library_recovery.raw.mapping_status') }}</label><select id="raw-map" class="admin-input" name="mapping_status"><option value="">{{ __('library_recovery.all') }}</option>@foreach($mappingStatuses as $status)<option value="{{ $status }}" @selected(($filters['mapping_status'] ?? '') === $status)>{{ $rv('mapping_status', $status) }}</option>@endforeach</select></div>
        <div><label class="admin-label" for="raw-apply">{{ __('library_recovery.raw.apply_status') }}</label><select id="raw-apply" class="admin-input" name="apply_status"><option value="">{{ __('library_recovery.all') }}</option>@foreach($applyStatuses as $status)<option value="{{ $status }}" @selected(($filters['apply_status'] ?? '') === $status)>{{ $rv('apply_status', $status) }}</option>@endforeach</select></div>
        <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-6"><button class="admin-btn admin-btn-primary">{{ __('library_recovery.apply_filters') }}</button><a class="admin-btn admin-btn-secondary" href="{{ $links['raw_records'] }}">{{ __('library_recovery.clear_filters') }}</a></div>
    </form>

    <section class="admin-card">
        <div class="overflow-x-auto"><table class="admin-table min-w-[980px]"><thead><tr><th>ID</th><th>{{ __('library_recovery.raw.batch_id') }}</th><th>{{ __('library_recovery.raw.source_doc_id') }}</th><th>{{ __('library_recovery.raw.control_number') }}</th><th>{{ __('library_recovery.raw.mapping_status') }}</th><th>{{ __('library_recovery.raw.apply_status') }}</th><th>{{ __('library_recovery.raw.catalogue_record') }}</th><th>{{ __('library_recovery.raw.source_hash') }}</th><th></th></tr></thead><tbody>
            @forelse ($records as $record)<tr><td>{{ $record->id }}</td><td>{{ $record->legacy_import_batch_id }}</td><td>{{ $record->source_doc_id }}</td><td>{{ $record->control_number ?: '—' }}</td><td>{{ $rv('mapping_status', $record->mapping_status) }}</td><td>{{ $rv('apply_status', $record->apply_status) }}</td><td>{{ $record->bibliographic_record_id ?: '—' }}</td><td class="max-w-[18rem] break-all font-mono text-[11px]">{{ $record->source_hash }}</td><td><a class="font-bold text-secondary hover:underline" href="{{ $record->detail_url }}">{{ __('library_recovery.view') }}</a></td></tr>@empty<tr><td colspan="9" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-5">{{ $records->links() }}</div>
    </section>
@endsection
