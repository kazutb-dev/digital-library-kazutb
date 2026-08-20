@extends('layouts.librarian')
@section('title', __('messages.title').' — '.__('common.app_name'))
@section('content')
<x-admin.flash />
<x-admin.page-header :eyebrow="__('messages.inbox')" :title="__('messages.title')" :subtitle="__('messages.subtitle')" />
@if(auth()->user()?->hasRole('bibliographer'))<div class="mb-6 rounded-xl border border-secondary/20 bg-secondary/5 p-4 text-sm text-on-surface-variant">{{ __('messages.bibliographer_scope') }}</div>@endif

<div class="mb-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
@foreach($summary as $key => $count)<a class="admin-card block p-4" href="{{ route('librarian.messages.index', $key === 'mine' ? ['mine'=>1] : ($key === 'unassigned' ? ['unassigned'=>1] : ($key === 'overdue' ? ['overdue'=>1] : ($key === 'approval' ? ['status'=>'response_prepared'] : [])))) }}"><span class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('messages.summary.'.$key) }}</span><strong class="mt-2 block font-headline text-3xl text-primary">{{ $count }}</strong></a>@endforeach
</div>

<form method="GET" class="admin-card mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    <div class="xl:col-span-2"><label class="admin-label" for="message-search">{{ __('common.filters.search') }}</label><input class="admin-input" id="message-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('messages.search_placeholder') }}"></div>
    <div><label class="admin-label" for="message-type">{{ __('messages.fields.type') }}</label><select class="admin-input" id="message-type" name="type"><option value="">{{ __('common.filters.all') }}</option>@foreach(\App\Models\ContactMessage::TYPES as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ __('messages.categories.'.$type) }}</option>@endforeach</select></div>
    <div><label class="admin-label" for="message-status">{{ __('messages.fields.status') }}</label><select class="admin-input" id="message-status" name="status"><option value="">{{ __('common.filters.all_statuses') }}</option>@foreach(\App\Models\ContactMessage::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('messages.statuses.'.$status) }}</option>@endforeach</select></div>
    <div class="flex items-end gap-2"><button class="admin-btn admin-btn-primary flex-1">{{ __('common.actions.apply_filters') }}</button><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.messages.index') }}">×</a></div>
</form>

<section class="admin-card overflow-hidden p-0"><div class="overflow-x-auto"><table class="admin-table min-w-[1050px]"><thead><tr><th>{{ __('messages.fields.ticket_number') }}</th><th>{{ __('messages.fields.subject') }}</th><th>{{ __('messages.fields.sender') }}</th><th>{{ __('messages.fields.status') }}</th><th>{{ __('messages.fields.priority') }}</th><th>{{ __('messages.fields.assigned_to') }}</th><th>{{ __('messages.fields.due_at') }}</th></tr></thead><tbody>
@forelse($messages as $message)<tr class="{{ $message->isOverdue() ? 'bg-red-50/50' : '' }}"><td><a class="font-bold text-secondary" href="{{ route('librarian.messages.show',$message) }}">{{ $message->ticket_number }}</a></td><td><strong class="block text-primary">{{ $message->subject }}</strong><small>{{ $message->messageCategory?->localizedName() }}</small></td><td>{{ $message->sender_name_snapshot ?: $message->sender?->name }}<small class="block text-slate-500">{{ $message->reader_ticket_snapshot }}</small></td><td><x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" /></td><td><x-admin.status-badge :status="$message->priority" :label="__('messages.priorities.'.$message->priority)" /></td><td>{{ $message->assignee?->name ?? __('messages.messages.unassigned') }}</td><td class="whitespace-nowrap {{ $message->isOverdue() ? 'font-bold text-red-700' : '' }}">{{ $message->due_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
@empty<tr><td colspan="7" class="py-12 text-center text-slate-500">{{ __('messages.messages.empty') }}</td></tr>@endforelse
</tbody></table></div><x-admin.pagination :paginator="$messages" /></section>
@endsection
