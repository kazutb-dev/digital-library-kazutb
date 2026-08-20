@extends('layouts.member', ['title' => $message->subject])
@section('content')
<x-admin.flash />
<a class="text-sm font-semibold text-secondary" href="{{ route('member.messages') }}">← {{ __('messages.title') }}</a>
<header class="mt-4 rounded-2xl bg-white p-6 md:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-bold text-secondary">{{ $message->ticket_number }}</p><h1 class="mt-2 font-headline text-3xl text-primary md:text-4xl">{{ $message->subject }}</h1></div><x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" /></div>
    <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3"><div><dt class="admin-label">{{ __('messages.fields.category') }}</dt><dd>{{ $message->messageCategory?->localizedName() }}</dd></div><div><dt class="admin-label">{{ __('messages.fields.received_at') }}</dt><dd>{{ $message->created_at?->format('d.m.Y H:i') }}</dd></div><div><dt class="admin-label">{{ __('messages.fields.due_at') }}</dt><dd>{{ $message->due_at?->format('d.m.Y H:i') ?? '—' }}</dd></div></dl>
</header>

<section class="mt-6 space-y-4" aria-label="{{ __('messages.history.title') }}">
@foreach($message->publicThreadEntries as $entry)
    <article class="rounded-2xl border p-5 {{ $entry->author_type === 'user' ? 'ml-auto max-w-3xl border-secondary/20 bg-secondary/5' : 'mr-auto max-w-3xl border-slate-100 bg-white' }}">
        <div class="flex justify-between gap-3 text-xs text-slate-500"><strong>{{ $entry->author?->name ?? __('messages.system.library') }}</strong><time>{{ $entry->created_at?->format('d.m.Y H:i') }}</time></div>
        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $entry->body }}</p>
        @if($entry->is_official_response)<p class="mt-3 text-xs font-bold uppercase tracking-wide text-secondary">{{ __('messages.official_response') }}</p>@endif
    </article>
@endforeach
</section>

@if($message->messageAttachments->isNotEmpty())
<section class="mt-6 rounded-2xl bg-white p-6"><h2 class="font-headline text-2xl">{{ __('messages.fields.attachments') }}</h2><div class="mt-4 flex flex-wrap gap-3">@foreach($message->messageAttachments as $attachment)<a class="admin-btn admin-btn-secondary" href="{{ route('member.messages.attachments.show', [$message, $attachment]) }}"><span class="material-symbols-outlined">download</span>{{ $attachment->original_name }}</a>@endforeach</div></section>
@endif

@if(!in_array($message->status, ['resolved','rejected','closed'], true))
<form class="mt-6 rounded-2xl bg-white p-6" method="POST" enctype="multipart/form-data" action="{{ route('member.messages.reply', $message) }}">@csrf<label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="reply-body">{{ __('messages.actions.reply') }}</label><textarea class="min-h-32 w-full rounded-md border border-outline-variant/30 bg-surface p-3 focus:border-secondary focus:ring-secondary" id="reply-body" name="body" required maxlength="20000"></textarea><input class="mt-4 w-full rounded-md border border-outline-variant/30 bg-surface p-3" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.docx"><button class="mt-4 inline-flex items-center rounded-md bg-primary px-6 py-3 text-sm font-bold text-white hover:opacity-90">{{ __('messages.actions.reply') }}</button></form>
@endif

@if($message->status === 'resolved' && $message->satisfaction_score === null)
<form class="mt-6 rounded-2xl bg-white p-6" method="POST" action="{{ route('member.messages.feedback', $message) }}">@csrf<label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="feedback-score">{{ __('messages.satisfaction') }}</label><select class="w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="feedback-score" name="score">@foreach(range(5,1) as $score)<option value="{{ $score }}">{{ $score }}</option>@endforeach</select><textarea class="mt-3 min-h-24 w-full rounded-md border border-outline-variant/30 bg-surface p-3" name="comment" maxlength="2000"></textarea><button class="mt-3 rounded-md bg-primary px-6 py-3 text-sm font-bold text-white">{{ __('common.actions.save') }}</button></form>
@endif
@endsection
