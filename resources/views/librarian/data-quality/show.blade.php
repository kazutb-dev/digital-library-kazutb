@extends('layouts.librarian')
@section('title', __('data_quality.object.title').' — '.__('data_quality.title'))
@section('content')
@php
    $record = $issue->entity_type === 'bibliographic_record' ? $entity : ($issue->entity_type === 'book_copy' ? $entity?->bibliographicRecord : null);
    $objectTitle = $record?->title ?? ($entity?->inventory_number ?? __('data_quality.entities.'.$issue->entity_type).' №'.$issue->entity_id);
@endphp
<div class="space-y-6">
    <x-admin.flash />
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>@endif

    <header>
        <a class="text-sm font-semibold text-secondary" href="{{ route('librarian.data-quality.index', ['lang' => app()->getLocale()]) }}">← {{ __('data_quality.object.back') }}</a>
        <p class="mt-5 text-xs font-bold uppercase tracking-[.16em] text-secondary">{{ __('data_quality.entities.'.$issue->entity_type) }}</p>
        <h1 class="mt-1 max-w-4xl font-headline text-4xl text-primary">{{ $objectTitle }}</h1>
        @if($record?->primary_author)<p class="mt-2 text-slate-600">{{ $record->primary_author }}</p>@endif
    </header>

    <section class="admin-card">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @if($record)<div><div class="admin-label">ISBN</div><strong>{{ $record->isbn ?: '—' }}</strong></div><div><div class="admin-label">{{ __('librarian.catalog.fields.udc_code') }}</div><strong>{{ $record->udc_code ?: '—' }}</strong></div><div><div class="admin-label">{{ __('librarian.catalog.fields.language') }}</div><strong>{{ __('librarian.catalog.languages.'.$record->language) }}</strong></div><div><div class="admin-label">{{ __('librarian.catalog.fields.publication_year') }}</div><strong>{{ $record->publication_year ?: '—' }}</strong></div>@endif
            @if($issue->entity_type === 'book_copy')<div><div class="admin-label">{{ __('librarian.copies.fields.inventory_number') }}</div><strong class="font-mono">{{ $entity?->inventory_number ?: '—' }}</strong></div><div><div class="admin-label">{{ __('librarian.copies.fields.barcode') }}</div><strong class="font-mono">{{ $entity?->barcode ?: '—' }}</strong></div><div><div class="admin-label">{{ __('data_quality.object.location') }}</div><strong>{{ $entity?->branch?->name ?? '—' }} · {{ $entity?->fund?->name ?? '—' }} · {{ $entity?->shelf_location ?? '—' }}</strong></div><div><div class="admin-label">{{ __('librarian.copies.fields.status') }}</div><strong>{{ $entity ? __('librarian.copies.statuses.'.$entity->status) : '—' }}</strong></div>@endif
        </div>
        <div class="mt-5 flex flex-wrap gap-3 border-t pt-5">
            @if($issue->entity_type === 'bibliographic_record' && $entity)<a class="admin-btn admin-btn-primary" href="{{ route('librarian.catalog.edit', [$entity, 'from' => 'data-quality', 'issue' => $issue->id, 'lang' => app()->getLocale()]) }}">{{ __('data_quality.actions.open_record') }}</a>@endif
            @if($issue->entity_type === 'book_copy' && $entity)<a class="admin-btn admin-btn-primary" href="{{ route('librarian.copies.edit', [$entity, 'from' => 'data-quality', 'issue' => $issue->id, 'lang' => app()->getLocale()]) }}">{{ __('data_quality.actions.open_copy') }}</a>@endif
            @if($nextIssue)<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.issues.show', [$nextIssue, 'lang' => app()->getLocale()]) }}">{{ __('data_quality.object.next') }} <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_forward</span></a>@endif
        </div>
    </section>

    <section>
        <div class="flex items-baseline justify-between gap-3"><h2 class="font-headline text-2xl text-primary">{{ __('data_quality.object.title') }}</h2><strong>{{ $relatedIssues->count() }}</strong></div>
        <div class="mt-4 space-y-4">
            @foreach($relatedIssues as $finding)
                <article class="admin-card border-l-4 {{ in_array($finding->severity, ['critical','high']) ? 'border-l-red-500' : ($finding->severity === 'medium' ? 'border-l-amber-500' : 'border-l-slate-300') }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h3 class="font-semibold text-primary">{{ __('data_quality.rules.'.$finding->rule_code) }}</h3><div class="mt-2 flex flex-wrap gap-2"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ __('data_quality.categories.'.$finding->category) }}</span><span class="rounded-full border px-2 py-1 text-xs">{{ __('data_quality.severity.'.$finding->severity) }}</span><span class="rounded-full border px-2 py-1 text-xs">{{ __('data_quality.statuses.'.$finding->status) }}</span></div></div>
                        <span class="text-xs text-slate-400">{{ $finding->last_detected_at?->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div><div class="admin-label">{{ __('data_quality.fields.value') }}</div><div class="mt-1 min-h-16 whitespace-pre-wrap break-words rounded-lg bg-red-50 p-3 text-sm">{{ $finding->current_value ?? '—' }}</div></div>
                        <div><div class="admin-label">{{ __('data_quality.fields.expected') }}</div><div class="mt-1 min-h-16 whitespace-pre-wrap break-words rounded-lg bg-emerald-50 p-3 text-sm">{{ $finding->suggested_action ?: $finding->expected_format }}</div></div>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $finding->description }}</p>
                    <details class="mt-4 border-t pt-3">
                        <summary class="cursor-pointer text-sm font-semibold text-secondary">{{ __('data_quality.actions.false_positive') }}</summary>
                        @can('data_quality.triage')<form class="mt-3 max-w-xl" method="POST" action="{{ route('librarian.data-quality.issues.false-positive', $finding) }}">@csrf<p class="mb-2 text-xs text-slate-500">{{ __('data_quality.messages.false_positive_help') }}</p><textarea class="admin-input" name="reason" required minlength="5" maxlength="2000"></textarea><button class="admin-btn admin-btn-secondary mt-2">{{ __('data_quality.actions.false_positive') }}</button></form>@endcan
                    </details>
                    <details class="mt-3 text-xs text-slate-500"><summary class="cursor-pointer">{{ __('data_quality.fields.technical_details') }}</summary><div class="mt-2 font-mono">{{ $finding->rule_code }} · {{ $finding->issue_number }}</div></details>
                </article>
            @endforeach
        </div>
    </section>

    <section class="admin-card">
        <h2 class="font-headline text-2xl text-primary">{{ __('data_quality.fields.assignee') }}</h2>
        <div class="mt-4 grid gap-5 lg:grid-cols-2">
            @can('data_quality.assign')<form method="POST" action="{{ route('librarian.data-quality.issues.assign', $issue) }}">@csrf<label><span class="admin-label">{{ __('data_quality.fields.assignee') }}</span><select class="admin-input" name="assigned_to">@foreach($assignees as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label><button class="admin-btn admin-btn-secondary mt-2">{{ __('data_quality.actions.assign') }}</button></form>@endcan
            @can('data_quality.triage')<form method="POST" action="{{ route('librarian.data-quality.issues.comments', $issue) }}">@csrf<label><span class="admin-label">{{ __('data_quality.actions.comment') }}</span><input class="admin-input" name="body" required maxlength="5000"></label><button class="admin-btn admin-btn-secondary mt-2">{{ __('data_quality.actions.comment') }}</button></form>@endcan
        </div>
        <div class="mt-5 space-y-3">@foreach($issue->comments as $comment)<div class="rounded-xl border p-3 text-sm"><strong>{{ $comment->author?->name ?? 'System' }}</strong><span class="ml-2 text-xs text-slate-400">{{ $comment->created_at }}</span><p class="mt-1">{{ $comment->body }}</p></div>@endforeach</div>
    </section>
</div>
@endsection
