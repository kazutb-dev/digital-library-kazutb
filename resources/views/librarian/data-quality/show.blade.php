@extends('layouts.librarian')

@section('title', $issue->issue_number.' — '.__('data_quality.title'))

@section('content')
<div class="space-y-6">
    <header><a class="text-sm text-secondary" href="{{ route('librarian.data-quality.index') }}">← {{ __('data_quality.title') }}</a><h1 class="mt-2 font-headline text-4xl text-primary">{{ $issue->issue_number }}</h1><p class="mt-1 text-slate-600">{{ $issue->description }}</p></header>
    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>@endif

    <section class="grid gap-5 lg:grid-cols-2">
        <div class="admin-card space-y-3">
            <h2 class="font-headline text-2xl text-primary">{{ __('data_quality.fields.issue') }}</h2>
            @foreach(['entity_type','entity_id','rule_code','category','severity','status','field_name','expected_format','first_detected_at','last_detected_at','occurrence_count','due_at'] as $field)
                <div class="grid grid-cols-3 gap-3 border-b py-2 text-sm"><span class="text-slate-500">{{ $field }}</span><strong class="col-span-2 break-words">{{ data_get($issue, $field) ?? '—' }}</strong></div>
            @endforeach
        </div>
        <div class="admin-card">
            <h2 class="font-headline text-2xl text-primary">{{ __('data_quality.fields.entity') }}</h2>
            @if($entity)
                <pre class="mt-4 max-h-96 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($entity->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
            @else<p class="mt-4 text-slate-500">Entity no longer exists.</p>@endif
        </div>
    </section>

    <section class="admin-card">
        <h2 class="font-headline text-2xl text-primary">Before / proposed after</h2>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div><div class="admin-label">{{ __('data_quality.fields.value') }}</div><pre class="min-h-24 whitespace-pre-wrap rounded-xl border bg-red-50 p-4 text-sm">{{ $issue->current_value ?? 'NULL' }}</pre></div>
            <div><div class="admin-label">{{ __('data_quality.fields.expected') }}</div><pre class="min-h-24 whitespace-pre-wrap rounded-xl border bg-emerald-50 p-4 text-sm">{{ $issue->suggested_action ?: $issue->expected_format }}</pre></div>
        </div>
        @if($characters)
            <details class="mt-4"><summary class="cursor-pointer text-sm font-semibold">Unicode / bytes</summary><div class="mt-3 flex flex-wrap gap-2">@foreach($characters as $character)<span class="rounded border px-2 py-1 font-mono text-xs">{{ $character['character'] }} · {{ $character['codepoint'] }} · {{ $character['hex'] }}</span>@endforeach</div></details>
        @endif
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        @can('data_quality.correct')
        <form class="admin-card" method="POST" action="{{ route('librarian.data-quality.issues.correct', $issue) }}">
            @csrf
            <h2 class="font-headline text-2xl text-primary">{{ __('data_quality.actions.correct') }}</h2>
            @if($issue->field_name)
                <label class="mt-4 block"><span class="admin-label">{{ $issue->field_name }}</span><textarea class="admin-input min-h-28" name="changes[{{ $issue->field_name }}]">{{ old('changes.'.$issue->field_name, data_get($issue->context, 'suggestion', $issue->current_value)) }}</textarea></label>
                <label class="mt-4 block"><span class="admin-label">{{ __('data_quality.fields.reason') }}</span><textarea class="admin-input" name="reason" required></textarea></label>
                <button class="admin-btn admin-btn-primary mt-4" type="submit">{{ __('data_quality.actions.correct') }}</button>
            @else<p class="mt-4 text-sm text-slate-500">Эта integrity-проблема исправляется в профильном workflow сущности.</p>@endif
        </form>
        @endcan
        <div class="admin-card space-y-5">
            @can('data_quality.assign')<form method="POST" action="{{ route('librarian.data-quality.issues.assign', $issue) }}">@csrf<label><span class="admin-label">{{ __('data_quality.fields.assignee') }}</span><select class="admin-input" name="assigned_to">@foreach($assignees as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label><button class="admin-btn admin-btn-secondary mt-2">{{ __('data_quality.actions.assign') }}</button></form>@endcan
            @can('data_quality.triage')
                <form method="POST" action="{{ route('librarian.data-quality.issues.false-positive', $issue) }}">@csrf<label><span class="admin-label">{{ __('data_quality.fields.reason') }}</span><textarea class="admin-input" name="reason" required></textarea></label><button class="admin-btn admin-btn-secondary mt-2">{{ __('data_quality.actions.false_positive') }}</button></form>
                <form method="POST" action="{{ route('librarian.data-quality.issues.ignore', $issue) }}">@csrf<div class="grid grid-cols-2 gap-3"><input class="admin-input" type="date" name="ignored_until" required><input class="admin-input" name="reason" required placeholder="{{ __('data_quality.fields.reason') }}"></div><button class="admin-btn admin-btn-secondary mt-2">{{ __('data_quality.actions.ignore') }}</button></form>
            @endcan
        </div>
    </section>

    <section class="admin-card">
        <h2 class="font-headline text-2xl text-primary">Comments & audit</h2>
        @can('data_quality.triage')<form class="mt-4 flex gap-3" method="POST" action="{{ route('librarian.data-quality.issues.comments', $issue) }}">@csrf<input class="admin-input" name="body" required><button class="admin-btn admin-btn-secondary">{{ __('data_quality.actions.comment') }}</button></form>@endcan
        <div class="mt-5 space-y-3">@foreach($issue->comments as $comment)<div class="rounded-xl border p-3 text-sm"><strong>{{ $comment->author?->name ?? 'System' }}</strong><span class="ml-2 text-xs text-slate-400">{{ $comment->created_at }}</span><p class="mt-1">{{ $comment->body }}</p></div>@endforeach</div>
        <div class="mt-6 overflow-x-auto"><table class="admin-table"><thead><tr><th>Time</th><th>Event</th><th>Actor</th><th>Reason</th></tr></thead><tbody>@foreach($history as $event)<tr><td>{{ $event->occurred_at }}</td><td class="font-mono text-xs">{{ $event->action_type }}</td><td>{{ $event->actor_name }}</td><td>{{ $event->reason }}</td></tr>@endforeach</tbody></table></div>
    </section>
</div>
@endsection
