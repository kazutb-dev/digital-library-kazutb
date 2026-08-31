@extends('layouts.admin')

@section('title', __('library_recovery.quarantine.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header :title="__('library_recovery.quarantine.title')" :subtitle="__('library_recovery.quarantine.note')" />
    @include('admin.library-recovery._nav')

    <form method="GET" action="{{ $links['quarantine'] }}" class="admin-card mb-7 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="md:col-span-2"><label class="admin-label" for="quarantine-q">{{ __('library_recovery.quarantine.reason') }} / ID</label><input id="quarantine-q" class="admin-input" name="q" value="{{ $filters['q'] ?? '' }}"></div>
        <div><label class="admin-label" for="quarantine-batch">{{ __('library_recovery.raw.batch_id') }}</label><input id="quarantine-batch" class="admin-input" type="number" min="1" name="batch_id" value="{{ $filters['batch_id'] ?? '' }}"></div>
        <div><label class="admin-label" for="quarantine-kind">{{ __('library_recovery.quarantine.kind') }}</label><select id="quarantine-kind" class="admin-input" name="kind"><option value="">{{ __('library_recovery.all') }}</option>@foreach($kinds as $kind)<option value="{{ $kind }}" @selected(($filters['kind'] ?? '') === $kind)>{{ $kind }}</option>@endforeach</select></div>
        <div><label class="admin-label" for="quarantine-status">{{ __('library_recovery.quarantine.status') }}</label><select id="quarantine-status" class="admin-input" name="status"><option value="">{{ __('library_recovery.all') }}</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-5"><button class="admin-btn admin-btn-primary">{{ __('library_recovery.apply_filters') }}</button><a class="admin-btn admin-btn-secondary" href="{{ $links['quarantine'] }}">{{ __('library_recovery.clear_filters') }}</a></div>
    </form>

    <section class="admin-card mb-7">
        <div class="flex flex-wrap gap-2">@foreach($summary as $item)<span class="rounded-full bg-surface-low px-3 py-2 text-xs font-semibold">{{ $item->kind }} · {{ $item->status }} · {{ $item->total }}</span>@endforeach</div>
    </section>

    <section class="admin-card">
        <div class="overflow-x-auto"><table class="admin-table min-w-[950px]"><thead><tr><th>ID</th><th>{{ __('library_recovery.quarantine.kind') }}</th><th>{{ __('library_recovery.quarantine.status') }}</th><th>{{ __('library_recovery.quarantine.batch') }}</th><th>{{ __('library_recovery.quarantine.source_doc_id') }}</th><th>{{ __('library_recovery.quarantine.source_inv_id') }}</th><th>{{ __('library_recovery.quarantine.reason') }}</th><th></th></tr></thead><tbody>
            @forelse($rows as $row)<tr><td>{{ $row->id }}</td><td><span class="rounded-full bg-surface-low px-3 py-1 text-xs font-bold">{{ $row->kind }}</span></td><td>{{ $row->status }}</td><td>{{ $row->batch?->package_name ?: $row->legacy_import_batch_id }}</td><td>{{ $row->source_doc_id ?: '—' }}</td><td>{{ $row->source_inv_id ?: '—' }}</td><td class="max-w-md break-words">{{ $row->reason ?: '—' }}</td><td><a class="font-bold text-secondary hover:underline" href="{{ $row->detail_url }}">{{ __('library_recovery.view') }}</a></td></tr>@empty<tr><td colspan="8" class="text-center text-slate-500">{{ __('library_recovery.empty') }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-5">{{ $rows->links() }}</div>
    </section>
@endsection
