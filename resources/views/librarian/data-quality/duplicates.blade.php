@extends('layouts.librarian')
@section('title', __('data_quality.nav.duplicates').' — '.__('data_quality.title'))
@section('content')
<div class="space-y-6">
    <header><a class="text-sm text-secondary" href="{{ route('librarian.data-quality.index', ['lang' => app()->getLocale()]) }}">← {{ __('data_quality.title') }}</a><h1 class="mt-2 font-headline text-4xl text-primary">{{ __('data_quality.nav.duplicates') }}</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('data_quality.duplicates.help') }}</p></header>
    @forelse($groups as $group)
        <section class="admin-card">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><strong>{{ $group->group_number }}</strong><div class="mt-2 flex flex-wrap gap-2"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs">{{ __('data_quality.duplicates.match.'.$group->match_level) }} · {{ $group->score }}%</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ __('data_quality.duplicates.statuses.'.$group->status) }}</span></div></div>@if($group->review_notes)<p class="max-w-xl text-sm text-slate-600">{{ $group->review_notes }}</p>@endif</div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach($group->members as $member)
                    <article class="rounded-xl border p-4"><h2 class="font-headline text-2xl">{{ $member->record?->title }}</h2><dl class="mt-3 text-sm">@foreach(['id','isbn','primary_author','publication_year','publisher','language','udc_code','resource_type'] as $field)<div class="grid grid-cols-3 border-b py-2"><dt class="text-slate-500">{{ Illuminate\Support\Facades\Lang::has('librarian.catalog.fields.'.$field) ? __('librarian.catalog.fields.'.$field) : strtoupper($field) }}</dt><dd class="col-span-2">{{ data_get($member->record, $field) ?? '—' }}</dd></div>@endforeach</dl><details class="mt-3 text-xs text-slate-500"><summary class="cursor-pointer">{{ __('data_quality.duplicates.technical_comparison') }}</summary><pre class="mt-2 overflow-auto rounded-lg bg-slate-50 p-3">{{ json_encode($member->match_details, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></article>
                @endforeach
            </div>
            @can('data_quality.merge')
                @if($group->members->count() >= 2 && $group->status === 'open')
                    <form class="mt-5 rounded-xl bg-slate-50 p-4" method="POST" action="{{ route('librarian.data-quality.merges.propose', $group) }}">@csrf
                        <div class="grid gap-4 md:grid-cols-2"><label><span class="admin-label">{{ __('data_quality.duplicates.target') }}</span><select class="admin-input" name="target_record_id">@foreach($group->members as $member)<option value="{{ $member->bibliographic_record_id }}">{{ $member->record?->title }}</option>@endforeach</select></label><label><span class="admin-label">{{ __('data_quality.duplicates.source') }}</span><select class="admin-input" name="source_record_id">@foreach($group->members->reverse() as $member)<option value="{{ $member->bibliographic_record_id }}">{{ $member->record?->title }}</option>@endforeach</select></label></div>
                        <div class="mt-4 grid gap-2 md:grid-cols-4">@foreach(['title','primary_author','publisher','publication_year','language','udc_code','isbn','annotation'] as $field)<label class="text-xs">{{ __('librarian.catalog.fields.'.$field) }}<select class="admin-input mt-1" name="field_selection[{{ $field }}]"><option value="target">{{ __('data_quality.duplicates.take_from_target') }}</option><option value="source">{{ __('data_quality.duplicates.take_from_source') }}</option></select></label>@endforeach</div>
                        <textarea class="admin-input mt-4" name="reason" required minlength="10" placeholder="{{ __('data_quality.fields.reason') }}"></textarea><button class="admin-btn admin-btn-primary mt-3">{{ __('data_quality.actions.propose_merge') }}</button>
                    </form>
                @endif
            @endcan
            @foreach(\App\Models\RecordMergeOperation::query()->where('duplicate_group_id',$group->id)->get() as $operation)
                <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border p-3">
                    <span>{{ $operation->operation_number }} · {{ __('data_quality.duplicates.merge_statuses.'.$operation->status) }}</span>
                    @can('data_quality.approve_merge')
                        @if($operation->status === 'proposed')
                            <form method="POST" action="{{ route('librarian.data-quality.merges.approve', $operation) }}">
                                @csrf
                                <button class="admin-btn admin-btn-secondary">{{ __('data_quality.actions.approve') }}</button>
                            </form>
                        @endif
                    @endcan
                    @can('data_quality.execute_merge')
                        @if($operation->status === 'approved')
                            <form method="POST" action="{{ route('librarian.data-quality.merges.execute', $operation) }}">
                                @csrf
                                <button class="admin-btn admin-btn-primary">{{ __('data_quality.actions.execute') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @endforeach
        </section>
    @empty
        <div class="admin-card text-center text-slate-500">{{ __('common.empty') }}</div>
    @endforelse
    {{ $groups->links() }}
</div>
@endsection
