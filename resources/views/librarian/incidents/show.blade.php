@extends('layouts.librarian')
@section('title', $incident->case_number.' — '.__('common.app_name'))
@section('content')
<x-admin.flash />
<x-admin.page-header :eyebrow="__('incidents.eyebrow')" :title="$incident->case_number" :subtitle="__('incidents.statuses.'.$incident->status)">
    <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.incidents.index') }}">{{ __('common.actions.back') }}</a>
</x-admin.page-header>

<div class="grid gap-6 xl:grid-cols-12">
<div class="space-y-6 xl:col-span-7">
    <section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.sections.loan') }}</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">
        <div><dt class="admin-label">{{ __('incidents.fields.reader') }}</dt><dd>{{ $incident->reader?->name }} · {{ $incident->reader?->readerProfile?->ticket_number }}</dd></div>
        <div><dt class="admin-label">{{ __('incidents.fields.loan') }}</dt><dd>#{{ $incident->loan_id }} · {{ $incident->loan?->issued_at?->format('d.m.Y') }}</dd></div>
        <div><dt class="admin-label">{{ __('incidents.fields.original_book') }}</dt><dd>{{ $incident->originalCopy?->bibliographicRecord?->title }}<br><span class="text-sm text-slate-500">{{ $incident->originalCopy?->bibliographicRecord?->primary_author }}</span></dd></div>
        <div><dt class="admin-label">{{ __('incidents.fields.original_copy') }}</dt><dd>{{ $incident->originalCopy?->inventory_number }} · {{ __('librarian.copies.statuses.'.$incident->originalCopy?->status) }}</dd></div>
    </dl>
    <div class="mt-5 border-t pt-4"><h3 class="font-bold">{{ __('incidents.sections.attachments') }}</h3><ul class="mt-2 text-sm">@forelse($incident->attachments as $attachment)<li>{{ $attachment->original_name }} · {{ number_format($attachment->size/1024,1) }} KB</li>@empty<li class="text-slate-500">—</li>@endforelse</ul>
    @can('incidents.create')<form class="mt-3 flex gap-2" method="POST" enctype="multipart/form-data" action="{{ route('librarian.incidents.attachments.store',$incident) }}">@csrf<input class="admin-input" type="file" name="attachment" accept="image/jpeg,image/png,image/webp" required><button class="admin-btn admin-btn-secondary">{{ __('incidents.actions.add_photo') }}</button></form>@endcan</div>
    </section>

    <section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.sections.incident') }}</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">
        <div><dt class="admin-label">{{ __('incidents.fields.type') }}</dt><dd>{{ __('incidents.types.'.$incident->incident_type) }}</dd></div>
        <div><dt class="admin-label">{{ __('incidents.fields.damage_severity') }}</dt><dd>{{ $incident->damage_severity ? __('incidents.damage_severities.'.$incident->damage_severity) : '—' }}</dd></div>
        <div class="sm:col-span-2"><dt class="admin-label">{{ __('incidents.fields.description') }}</dt><dd>{{ $incident->damage_description ?: $incident->notes ?: '—' }}</dd></div>
    </dl></section>

    <section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.sections.fines') }}</h2>
        @if($incident->fine)<p class="mt-4">{{ number_format((float)$incident->fine->amount,2,',',' ') }} ₸ · {{ __('librarian.fines.statuses.'.$incident->fine->status) }} @if($incident->fine_remains) · {{ __('incidents.fine_remains') }} @endif</p>@else<p class="mt-4 text-slate-500">{{ __('incidents.no_fine') }}</p>@endif
    </section>

    @foreach($incident->candidates as $candidate)
    <section class="admin-card"><div class="flex justify-between gap-4"><div><h2 class="font-headline text-2xl">{{ __('incidents.sections.candidate') }} #{{ $candidate->id }}</h2><p>{{ $candidate->title }} · {{ $candidate->author }}</p></div><span class="font-bold">{{ $candidate->match_score !== null ? $candidate->match_score.'%' : '—' }}</span></div>
        <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>{{ __('incidents.compare.field') }}</th><th>{{ __('incidents.compare.original') }}</th><th>{{ __('incidents.compare.candidate') }}</th></tr></thead><tbody>
        @foreach(['isbn','title','primary_author'=>'author','publisher','publication_year','language','resource_type','udc_code'] as $left=>$right) @php if(is_int($left)) $left=$right; $a=$incident->originalCopy?->bibliographicRecord?->{$left}; $b=$candidate->{$right}; @endphp
        <tr><td>{{ __('incidents.candidate_fields.'.$right) }}</td><td>{{ $a ?: '—' }}</td><td @class(['text-emerald-700 font-semibold'=>mb_strtolower((string)$a)===mb_strtolower((string)$b),'text-amber-800'=>mb_strtolower((string)$a)!==mb_strtolower((string)$b)])>{{ $b ?: '—' }}</td></tr>@endforeach
        </tbody></table></div>
        @if(!$candidate->bibliographic_record_id) @can('catalog.create_record')<form class="mt-3" method="POST" action="{{ route('librarian.incidents.candidates.draft',$candidate) }}">@csrf<button class="admin-btn admin-btn-secondary">{{ __('incidents.actions.create_draft') }}</button></form>@endcan @endif

        @can('incidents.review')
        @if(!in_array($candidate->status,['approved','rejected'],true))
        <form method="POST" action="{{ route('librarian.incidents.candidates.review',$candidate) }}" class="mt-5 border-t pt-5">@csrf
            <h3 class="font-bold">{{ __('incidents.review.title') }}</h3><div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach([... \App\Models\Catalog\ReplacementCandidate::REQUIRED_CRITERIA,'value_comparable','complete_set'] as $criterion)
            <label class="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm"><span>{{ __('incidents.criteria.'.$criterion) }}</span><select name="{{ $criterion }}" class="rounded border-slate-300 text-sm"><option value="1">{{ __('common.yes') }}</option><option value="0">{{ __('common.no') }}</option></select></label>
            @endforeach</div><textarea class="admin-input mt-3" name="reviewer_comment" placeholder="{{ __('incidents.fields.comment') }}"></textarea><button class="admin-btn admin-btn-primary mt-3">{{ __('incidents.actions.save_review') }}</button>
        </form>
        @endif
        @endcan

        @can('incidents.approve')
        @if($candidate->reviewed_at && !in_array($candidate->status,['approved','rejected'],true))
        <form method="POST" action="{{ route('librarian.incidents.candidates.decide',$candidate) }}" class="mt-5 border-t pt-5">@csrf
            <div class="grid gap-3 sm:grid-cols-2"><label><span class="admin-label">{{ __('incidents.fields.decision') }}</span><select class="admin-input" name="decision"><option value="approve">{{ __('incidents.actions.approve') }}</option><option value="reject">{{ __('incidents.actions.reject') }}</option><option value="clarify">{{ __('incidents.actions.clarify') }}</option></select></label>
            <label><span class="admin-label">{{ __('incidents.fields.resolution_type') }}</span><select class="admin-input" name="resolution_type">@foreach(\App\Models\Catalog\CirculationIncidentCase::RESOLUTIONS as $r)<option value="{{ $r }}">{{ __('incidents.resolutions.'.$r) }}</option>@endforeach</select></label></div>
            <textarea class="admin-input mt-3" required minlength="5" name="reason" placeholder="{{ __('incidents.fields.reason') }}"></textarea>
            <label class="mt-3 flex gap-2"><input type="checkbox" name="fine_remains" value="1">{{ __('incidents.fine_remains') }}</label>
            @can('incidents.approve_exception')<label class="mt-3 flex gap-2"><input type="checkbox" name="exception" value="1">{{ __('incidents.actions.exception') }}</label><div class="mt-2 flex flex-wrap gap-2">@foreach(\App\Models\Catalog\ReplacementCandidate::REQUIRED_CRITERIA as $criterion)<label class="text-xs"><input type="checkbox" name="exception_criteria[]" value="{{ $criterion }}"> {{ __('incidents.criteria.'.$criterion) }}</label>@endforeach</div>@endcan
            <button class="admin-btn admin-btn-primary mt-3">{{ __('incidents.actions.save_decision') }}</button>
        </form>@endif
        @endcan
    </section>
    @endforeach

    <section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.sections.audit') }}</h2><div class="mt-4 space-y-3">@forelse($auditEvents as $event)<div class="border-l-2 border-secondary pl-4"><strong>{{ $event->action_type }}</strong><div class="text-xs text-slate-500">{{ $event->actor_name }} · {{ $event->occurred_at?->format('d.m.Y H:i') }}</div><p class="text-sm">{{ $event->reason }}</p></div>@empty<p class="text-slate-500">—</p>@endforelse</div></section>
</div>

<aside class="space-y-6 xl:col-span-5">
@can('incidents.review')<section class="admin-card"><form method="POST" action="{{ route('librarian.incidents.assign',$incident) }}">@csrf<label><span class="admin-label">{{ __('incidents.fields.assigned_to') }}</span><select class="admin-input" name="assigned_to">@foreach($staff as $person)<option value="{{ $person->id }}" @selected($incident->assigned_to===$person->id)>{{ $person->name }}</option>@endforeach</select></label><button class="admin-btn admin-btn-secondary mt-3">{{ __('incidents.actions.assign') }}</button></form></section>@endcan
@can('incidents.resolve')
@if(in_array($incident->status,\App\Models\Catalog\CirculationIncidentCase::OPEN_STATUSES,true))
<section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.sections.other_resolution') }}</h2>
<form class="mt-4 space-y-3" method="POST" action="{{ route('librarian.incidents.resolve',$incident) }}">@csrf
<select class="admin-input" name="resolution_type">@foreach(['fine','repair','write_off','monetary_compensation','no_charge'] as $r)<option value="{{ $r }}">{{ __('incidents.resolutions.'.$r) }}</option>@endforeach</select>
<textarea class="admin-input" name="reason" required minlength="5" placeholder="{{ __('incidents.fields.reason') }}"></textarea>
@can('fines.waive')<label class="flex gap-2 text-sm"><input type="checkbox" name="waive_fine" value="1">{{ __('incidents.actions.waive_fine') }}</label>@endcan
<button class="admin-btn admin-btn-primary">{{ __('incidents.actions.resolve') }}</button></form>
<form class="mt-3" method="POST" action="{{ route('librarian.incidents.cancel',$incident) }}">@csrf<input class="admin-input" name="reason" required minlength="5" placeholder="{{ __('incidents.fields.cancel_reason') }}"><button class="admin-btn admin-btn-danger mt-2">{{ __('incidents.actions.cancel') }}</button></form>
</section>@endif
@if(in_array($incident->status,['resolved','cancelled'],true))<section class="admin-card"><form method="POST" action="{{ route('librarian.incidents.reopen',$incident) }}">@csrf<textarea class="admin-input" name="reason" required minlength="5" placeholder="{{ __('incidents.fields.reason') }}"></textarea><button class="admin-btn admin-btn-secondary mt-2">{{ __('incidents.actions.reopen') }}</button></form></section>@endif
@endcan
@can('incidents.create')
<section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.actions.propose') }}</h2><p class="mt-2 text-sm text-slate-500">{{ __('incidents.catalog_search_hint') }}</p>
<form method="POST" action="{{ route('librarian.incidents.candidates.store',$incident) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
    <label class="sm:col-span-2"><span class="admin-label">{{ __('incidents.fields.catalog_record') }}</span><input id="incident-catalog-search" class="admin-input" type="search" placeholder="{{ __('incidents.actions.search_catalog') }}"><input id="incident-record-id" type="hidden" name="bibliographic_record_id"><div id="incident-catalog-results" class="mt-2 space-y-2"></div></label>
    @foreach(['isbn','author','title','work_title','publisher','publication_year','language','resource_type','udc_code','copy_condition','estimated_value'] as $field)
    <label @class(['sm:col-span-2'=>in_array($field,['title','work_title'],true)])><span class="admin-label">{{ __('incidents.candidate_fields.'.$field) }}</span>
    @if($field==='copy_condition')<select class="admin-input" name="{{ $field }}" required>@foreach(\App\Models\Catalog\BookCopy::CONDITIONS as $v)<option value="{{ $v }}">{{ __('librarian.copies.conditions.'.$v) }}</option>@endforeach</select>
    @else<input class="admin-input" name="{{ $field }}" @if($field==='publication_year') type="number" @elseif($field==='estimated_value') type="number" step="0.01" @endif>@endif</label>@endforeach
    <label class="sm:col-span-2"><span class="admin-label">{{ __('incidents.candidate_fields.content_description') }}</span><textarea class="admin-input" name="content_description"></textarea></label>
    <button class="admin-btn admin-btn-primary sm:col-span-2">{{ __('incidents.actions.propose') }}</button>
</form></section>
@endcan

@can('incidents.register_replacement')
@if($incident->status==='awaiting_registration')
<section class="admin-card"><h2 class="font-headline text-2xl">{{ __('incidents.sections.registration') }}</h2><form method="POST" action="{{ route('librarian.incidents.register',$incident) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
@foreach(['bibliographic_record_id','inventory_number','barcode','shelf_location','storage_sigla','registration_date','price'] as $field)<label><span class="admin-label">{{ __('incidents.registration.'.$field) }}</span><input class="admin-input" name="{{ $field }}" @if($field==='registration_date') type="date" value="{{ today()->toDateString() }}" @elseif(in_array($field,['bibliographic_record_id','price'],true)) type="number" @endif @if(in_array($field,['inventory_number','barcode','storage_sigla','registration_date'],true)) required @endif></label>@endforeach
<label><span class="admin-label">{{ __('incidents.fields.branch') }}</span><select class="admin-input" required name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
<label><span class="admin-label">{{ __('incidents.fields.fund') }}</span><select class="admin-input" name="fund_id"><option value="">—</option>@foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->name }}</option>@endforeach</select></label>
<label><span class="admin-label">{{ __('incidents.registration.condition') }}</span><select class="admin-input" name="condition">@foreach(['new','good','worn'] as $v)<option value="{{ $v }}">{{ __('librarian.copies.conditions.'.$v) }}</option>@endforeach</select></label>
<label class="sm:col-span-2"><span class="admin-label">{{ __('incidents.fields.notes') }}</span><textarea class="admin-input" name="notes"></textarea></label><button class="admin-btn admin-btn-primary sm:col-span-2">{{ __('incidents.actions.register') }}</button>
</form></section>@endif
@endcan
</aside></div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    var input = document.getElementById('incident-catalog-search');
    var results = document.getElementById('incident-catalog-results');
    if (!input || !results) return;
    var timer;
    input.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            var q = input.value.trim();
            results.replaceChildren();
            if (q.length < 2) return;
            fetch(@json(route('librarian.incidents.catalog-search')) + '?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    (payload.data || []).forEach(function (record) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'block w-full rounded-lg border bg-white p-3 text-left text-sm hover:border-secondary';
                        button.textContent = record.title + ' · ' + (record.primary_author || '—') + ' · ' + (record.isbn || 'ISBN —');
                        button.addEventListener('click', function () {
                            document.getElementById('incident-record-id').value = record.id;
                            ['isbn','title','publisher','publication_year','language','resource_type','udc_code'].forEach(function (field) {
                                var target = document.querySelector('[name="' + field + '"]');
                                if (target && record[field] !== null) target.value = record[field];
                            });
                            var author = document.querySelector('[name="author"]');
                            if (author) author.value = record.primary_author || '';
                            input.value = '#' + record.id + ' · ' + record.title;
                            results.replaceChildren();
                        });
                        results.appendChild(button);
                    });
                });
        }, 250);
    });
})();
</script>
@endpush
