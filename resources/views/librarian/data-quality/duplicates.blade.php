@extends('layouts.librarian')
@section('title', __('data_quality.nav.duplicates').' — '.__('data_quality.title'))
@section('content')
<div class="space-y-6">
<header><a class="text-sm text-secondary" href="{{ route('librarian.data-quality.index') }}">← {{ __('data_quality.title') }}</a><h1 class="mt-2 font-headline text-4xl text-primary">{{ __('data_quality.nav.duplicates') }}</h1><p class="text-sm text-slate-500">Score — только подсказка. Тома, части, языки и форматы не объединяются автоматически.</p></header>
@foreach($groups as $group)
<section class="admin-card">
    <div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $group->group_number }}</strong> · {{ $group->match_level }} · {{ $group->score }}% · {{ $group->status }}</div><span>{{ $group->review_notes }}</span></div>
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach($group->members as $member)
        <article class="rounded-xl border p-4"><h2 class="font-headline text-2xl">{{ $member->record?->title }}</h2><dl class="mt-3 text-sm">@foreach(['id','isbn','primary_author','publication_year','publisher','language','udc_code','resource_type'] as $field)<div class="grid grid-cols-3 border-b py-2"><dt class="text-slate-500">{{ $field }}</dt><dd class="col-span-2">{{ data_get($member->record, $field) ?? '—' }}</dd></div>@endforeach</dl><pre class="mt-3 overflow-auto text-xs">{{ json_encode($member->match_details, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></article>
        @endforeach
    </div>
    @can('data_quality.merge')
    @if($group->members->count() >= 2 && $group->status === 'open')
    <form class="mt-5 rounded-xl bg-slate-50 p-4" method="POST" action="{{ route('librarian.data-quality.merges.propose', $group) }}">@csrf
        <div class="grid gap-4 md:grid-cols-2"><label><span class="admin-label">Target</span><select class="admin-input" name="target_record_id">@foreach($group->members as $member)<option value="{{ $member->bibliographic_record_id }}">{{ $member->record?->title }}</option>@endforeach</select></label><label><span class="admin-label">Source</span><select class="admin-input" name="source_record_id">@foreach($group->members->reverse() as $member)<option value="{{ $member->bibliographic_record_id }}">{{ $member->record?->title }}</option>@endforeach</select></label></div>
        <div class="mt-4 grid gap-2 md:grid-cols-4">@foreach(['title','primary_author','publisher','publication_year','language','udc_code','isbn','annotation'] as $field)<label class="text-xs">{{ $field }}<select class="admin-input mt-1" name="field_selection[{{ $field }}]"><option value="target">target</option><option value="source">source</option>@if(in_array($field,['additional_authors','keywords']))<option value="combine">combine</option>@endif</select></label>@endforeach</div>
        <textarea class="admin-input mt-4" name="reason" required minlength="10" placeholder="{{ __('data_quality.fields.reason') }}"></textarea><button class="admin-btn admin-btn-primary mt-3">{{ __('data_quality.actions.propose_merge') }}</button>
    </form>
    @endif
    @endcan
    @foreach(\App\Models\RecordMergeOperation::query()->where('duplicate_group_id',$group->id)->get() as $operation)
      <div class="mt-4 flex items-center gap-3 rounded-xl border p-3"><span>{{ $operation->operation_number }} · {{ $operation->status }}</span>@can('data_quality.approve_merge')@if($operation->status==='proposed')<form method="POST" action="{{ route('librarian.data-quality.merges.approve',$operation) }}">@csrf<button class="admin-btn admin-btn-secondary">{{ __('data_quality.actions.approve') }}</button></form>@endif@endcan @can('data_quality.execute_merge')@if($operation->status==='approved')<form method="POST" action="{{ route('librarian.data-quality.merges.execute',$operation) }}">@csrf<button class="admin-btn admin-btn-primary">{{ __('data_quality.actions.execute') }}</button></form>@endif@endcan</div>
    @endforeach
</section>
@endforeach
{{ $groups->links() }}
</div>
@endsection
