@extends('layouts.admin')

@section('title', __('library_recovery.raw.detail_title', ['id' => $record->id]).' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.raw.detail_title', ['id' => $record->id])" :subtitle="__('library_recovery.raw.note')" />
    @include('admin.library-recovery._nav')

    <section class="admin-card mb-7">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                __('library_recovery.raw.batch_id') => $record->legacy_import_batch_id,
                __('library_recovery.raw.source_doc_id') => $record->source_doc_id,
                __('library_recovery.raw.control_number') => $record->control_number,
                __('library_recovery.raw.catalogue_record') => $record->bibliographicRecord ? ($record->bibliographicRecord->id.' · '.$record->bibliographicRecord->title) : $record->bibliographic_record_id,
                __('library_recovery.raw.mapping_status') => $record->mapping_status,
                __('library_recovery.raw.apply_status') => $record->apply_status,
                __('library_recovery.raw.record_type') => $record->record_type,
                __('library_recovery.raw.bibliographic_level') => $record->bibliographic_level,
                __('library_recovery.raw.leader') => $record->leader,
                __('library_recovery.raw.fixed_008') => $record->fixed_008_raw,
                __('library_recovery.raw.modified_raw') => $record->modified_raw,
            ] as $label => $value)<div class="min-w-0"><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-semibold">{{ filled($value) ? $value : '—' }}</dd></div>@endforeach
        </dl>
        <div class="mt-5 rounded-xl bg-surface-low p-4"><p class="text-xs text-slate-500">{{ __('library_recovery.raw.source_hash') }}</p><p class="mt-1 break-all font-mono text-xs">{{ $record->source_hash }}</p></div>
    </section>

    <div class="mb-7 grid grid-cols-1 gap-7 xl:grid-cols-2">
        <section class="admin-card min-w-0"><h2 class="mb-4 font-headline text-2xl text-primary">{{ __('library_recovery.raw.canonical') }}</h2>@include('admin.library-recovery._json', ['value' => $record->canonical])</section>
        <section class="admin-card min-w-0"><h2 class="mb-4 font-headline text-2xl text-primary">{{ __('library_recovery.raw.raw_payload') }}</h2>@include('admin.library-recovery._json', ['value' => $record->raw])</section>
    </div>

    <section class="admin-card">
        <h2 class="font-headline text-3xl text-primary">{{ __('library_recovery.raw.fields') }}</h2>
        <div class="mt-5 overflow-x-auto"><table class="admin-table min-w-[900px]"><thead><tr><th>{{ __('library_recovery.raw.tag') }}</th><th>{{ __('library_recovery.raw.indicators') }}</th><th>{{ __('library_recovery.raw.subfield') }}</th><th>{{ __('library_recovery.raw.occurrence') }}</th><th>{{ __('library_recovery.raw.known') }}</th><th>{{ __('library_recovery.raw.value') }}</th></tr></thead><tbody>
            @forelse($fields as $field)<tr><td class="font-mono font-bold">{{ $field->tag }}</td><td class="font-mono">{{ $field->indicator1 ?: '∅' }}{{ $field->indicator2 ?: '∅' }}</td><td class="font-mono">{{ $field->subfield_code ? '$'.$field->subfield_code : '—' }}</td><td>{{ $field->occurrence }}</td><td>{{ $field->is_known_tag ? __('library_recovery.yes') : __('library_recovery.no') }}</td><td class="max-w-xl whitespace-pre-wrap break-words">{{ $field->value }}</td></tr>@empty<tr><td colspan="6" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-5">{{ $fields->links() }}</div>
    </section>
@endsection
