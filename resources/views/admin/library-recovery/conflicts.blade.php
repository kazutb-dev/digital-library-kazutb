@extends('layouts.admin')

@section('title', __('library_recovery.conflicts.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.conflicts.title')" :subtitle="__('library_recovery.conflicts.note')" />
    @include('admin.library-recovery._nav')

    <form method="GET" action="{{ $links['conflicts'] }}" class="admin-card mb-7 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
        <div class="md:col-span-2"><label class="admin-label" for="conflict-q">{{ __('library_recovery.conflicts.reason') }} / ID / {{ __('library_recovery.conflicts.current_value') }}</label><input id="conflict-q" class="admin-input" name="q" value="{{ $filters['q'] ?? '' }}"></div>
        <div><label class="admin-label" for="conflict-batch">{{ __('library_recovery.raw.batch_id') }}</label><input id="conflict-batch" class="admin-input" type="number" min="1" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}"></div>
        <div><label class="admin-label" for="conflict-entity">{{ __('library_recovery.conflicts.entity_type') }}</label><select id="conflict-entity" class="admin-input" name="entity_type"><option value="">{{ __('library_recovery.all') }}</option>@foreach($entityTypes as $value)<option value="{{ $value }}" @selected(($filters['entity_type'] ?? '') === $value)>{{ $value }}</option>@endforeach</select></div>
        <div><label class="admin-label" for="conflict-field">{{ __('library_recovery.conflicts.field_name') }}</label><select id="conflict-field" class="admin-input" name="field_name"><option value="">{{ __('library_recovery.all') }}</option>@foreach($fieldNames as $value)<option value="{{ $value }}" @selected(($filters['field_name'] ?? '') === $value)>{{ $value }}</option>@endforeach</select></div>
        <div><label class="admin-label" for="conflict-status">{{ __('library_recovery.conflicts.status') }}</label><select id="conflict-status" class="admin-input" name="status"><option value="">{{ __('library_recovery.all') }}</option>@foreach($statuses as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $value }}</option>@endforeach</select></div>
        <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-6"><button class="admin-btn admin-btn-primary">{{ __('library_recovery.apply_filters') }}</button><a class="admin-btn admin-btn-secondary" href="{{ $links['conflicts'] }}">{{ __('library_recovery.clear_filters') }}</a></div>
    </form>

    <section class="admin-card mb-7"><div class="flex flex-wrap gap-2">@foreach($summary as $item)<span class="rounded-full bg-surface-low px-3 py-2 text-xs font-semibold">{{ $item->entity_type }} · {{ $item->field_name }} · {{ $item->status }} · {{ $item->total }}</span>@endforeach</div></section>

    <section class="admin-card">
        <div class="overflow-x-auto"><table class="admin-table min-w-[1100px]"><thead><tr><th>ID</th><th>{{ __('library_recovery.conflicts.entity_type') }}</th><th>{{ __('library_recovery.conflicts.field_name') }}</th><th>{{ __('library_recovery.conflicts.status') }}</th><th>{{ __('library_recovery.conflicts.source_id') }}</th><th>{{ __('library_recovery.conflicts.current_value') }}</th><th>{{ __('library_recovery.conflicts.incoming_value') }}</th><th>{{ __('library_recovery.conflicts.reason') }}</th><th></th></tr></thead><tbody>
            @forelse($rows as $row)<tr><td>{{ $row->id }}</td><td>{{ $row->entity_type }} #{{ $row->entity_id ?: '—' }}</td><td class="font-mono text-xs">{{ $row->field_name }}</td><td>{{ $row->status }}</td><td>{{ $row->source_id ?: '—' }}</td><td class="max-w-xs whitespace-pre-wrap break-words">{{ $row->current_value ?: '—' }}</td><td class="max-w-xs whitespace-pre-wrap break-words">{{ $row->incoming_value ?: '—' }}</td><td class="max-w-xs break-words">{{ $row->reason }}</td><td><a class="font-bold text-secondary hover:underline" href="{{ $row->detail_url }}">{{ __('library_recovery.view') }}</a></td></tr>@empty<tr><td colspan="9" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-5">{{ $rows->links() }}</div>
    </section>
@endsection
