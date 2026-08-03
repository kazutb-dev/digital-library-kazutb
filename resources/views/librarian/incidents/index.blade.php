@extends('layouts.librarian')
@section('title', __('incidents.queue.title').' — '.__('common.app_name'))
@section('content')
<x-admin.flash />
<x-admin.page-header :eyebrow="__('incidents.eyebrow')" :title="__('incidents.queue.title')" :subtitle="__('incidents.queue.subtitle')" />

<form method="GET" class="admin-card mb-6 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
    <label><span class="admin-label">{{ __('incidents.fields.type') }}</span><select class="admin-input" name="type"><option value="">{{ __('common.filters.all') }}</option>@foreach(['lost','damaged'] as $v)<option value="{{ $v }}" @selected(request('type')===$v)>{{ __('incidents.types.'.$v) }}</option>@endforeach</select></label>
    <label><span class="admin-label">{{ __('incidents.fields.status') }}</span><select class="admin-input" name="status"><option value="">{{ __('common.filters.all') }}</option>@foreach(\App\Models\Catalog\CirculationIncidentCase::STATUSES as $v)<option value="{{ $v }}" @selected(request('status')===$v)>{{ __('incidents.statuses.'.$v) }}</option>@endforeach</select></label>
    <label><span class="admin-label">{{ __('incidents.fields.reader') }}</span><input class="admin-input" name="reader" value="{{ request('reader') }}"></label>
    <label><span class="admin-label">{{ __('incidents.fields.branch') }}</span><select class="admin-input" name="branch_id"><option value="">{{ __('common.filters.all') }}</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
    <label><span class="admin-label">{{ __('incidents.fields.assigned_to') }}</span><select class="admin-input" name="assigned_to"><option value="">{{ __('common.filters.all') }}</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((string)request('assigned_to')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></label>
    <div class="flex items-end gap-2"><button class="admin-btn admin-btn-primary" type="submit">{{ __('common.actions.apply') }}</button><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.incidents.index') }}">{{ __('common.actions.reset') }}</a></div>
    <div class="md:col-span-3 xl:col-span-6 flex flex-wrap gap-4 text-sm">
        @foreach(['has_fine','overdue','requires_director','awaiting_registration'] as $flag)<label class="flex items-center gap-2"><input type="checkbox" name="{{ $flag }}" value="1" @checked(request()->boolean($flag))>{{ __('incidents.filters.'.$flag) }}</label>@endforeach
    </div>
</form>

<section class="admin-card overflow-hidden p-0">
<div class="overflow-x-auto"><table class="admin-table"><thead><tr>
    <th>{{ __('incidents.fields.case_number') }}</th><th>{{ __('incidents.fields.reader') }}</th><th>{{ __('incidents.fields.original_book') }}</th><th>{{ __('incidents.fields.type') }}</th><th>{{ __('incidents.fields.status') }}</th><th>{{ __('incidents.fields.assigned_to') }}</th><th>{{ __('incidents.fields.due_at') }}</th><th>{{ __('incidents.fields.next_action') }}</th>
</tr></thead><tbody>
@forelse($cases as $case)
<tr>
    <td><a class="font-bold text-secondary" href="{{ route('librarian.incidents.show',$case) }}">{{ $case->case_number }}</a></td>
    <td>{{ $case->reader?->name }}<div class="text-xs text-slate-500">{{ $case->reader?->readerProfile?->category }}</div></td>
    <td>{{ $case->originalCopy?->bibliographicRecord?->title }}<div class="text-xs text-slate-500">{{ $case->originalCopy?->inventory_number }}</div></td>
    <td>{{ __('incidents.types.'.$case->incident_type) }}</td>
    <td>{{ __('incidents.statuses.'.$case->status) }}</td>
    <td>{{ $case->assignedTo?->name ?? '—' }}</td>
    <td @class(['text-red-700 font-semibold'=>$case->resolution_due_at?->isPast() && in_array($case->status,\App\Models\Catalog\CirculationIncidentCase::OPEN_STATUSES,true)])>{{ $case->resolution_due_at?->format('d.m.Y') ?? '—' }}</td>
    <td>{{ __('incidents.next_actions.'.$case->status) }} @if($case->fine)<div class="text-xs text-amber-800">{{ number_format((float)$case->fine->amount,0,',',' ') }} ₸</div>@endif</td>
</tr>
@empty<tr><td colspan="8" class="py-12 text-center text-slate-500">{{ __('incidents.queue.empty') }}</td></tr>@endforelse
</tbody></table></div>
<div class="p-4">{{ $cases->links() }}</div>
</section>
@endsection
