@extends('layouts.member', ['title' => __('messages.title').' — '.__('common.app_name')])

@section('content')
<x-admin.flash />
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-[.15em] text-secondary">{{ __('messages.actions.submit') }}</p>
    <h1 class="mt-2 font-headline text-4xl text-primary md:text-5xl">{{ __('messages.title') }}</h1>
    <p class="mt-3 max-w-3xl text-on-surface-variant">{{ __('messages.subtitle') }}</p>
</header>

@if ($errors->any())
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(360px,.8fr)]">
    <form method="POST" enctype="multipart/form-data" action="{{ route('member.messages.store') }}" class="rounded-2xl bg-white p-6 shadow-sm md:p-8" data-message-form>
        @csrf
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="message-type">{{ __('messages.fields.type') }}</label>
                <select class="w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="message-type" name="type" required>
                    @foreach($messageTypes as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ __('messages.categories.'.$type) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="message-category">{{ __('messages.fields.category') }}</label>
                <select class="w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="message-category" name="category_id" required>
                    @foreach($messageCategories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ __('messages.categories.'.$category->message_type) }} — {{ $category->localizedName() }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="message-subject">{{ __('messages.fields.subject') }}</label>
                <input class="w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="message-subject" name="subject" minlength="5" maxlength="255" value="{{ old('subject') }}" required>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="message-body">{{ __('messages.fields.body') }}</label>
                <textarea class="min-h-44 w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="message-body" name="body" minlength="10" maxlength="20000" required>{{ old('body') }}</textarea>
            </div>
            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="message-channel">{{ __('messages.fields.preferred_contact_channel') }}</label>
                <select class="w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="message-channel" name="preferred_contact_channel"><option value="in_app">{{ __('messages.channels.in_app') }}</option><option value="email">{{ __('messages.channels.email') }}</option></select>
            </div>
            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="message-files">{{ __('messages.fields.attachments') }}</label>
                <input class="w-full rounded-md border border-outline-variant/30 bg-surface p-3" id="message-files" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.docx">
                <p class="mt-2 text-xs text-slate-500">{{ __('messages.messages.attachment_hint') }}</p>
            </div>
            <label class="md:col-span-2 flex items-start gap-3 rounded-xl bg-surface-container-low p-4 text-sm"><input class="mt-1" type="checkbox" name="contact_confirmed" value="1" required><span>{{ __('messages.contact_confirmed') }}</span></label>
        </div>
        <button class="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-6 py-3 text-sm font-bold text-white hover:opacity-90" type="submit"><span class="material-symbols-outlined text-[18px]">send</span>{{ __('common.actions.submit') }}</button>
    </form>

    <section>
        <div class="mb-4 flex items-center justify-between gap-3"><h2 class="font-headline text-2xl text-primary">{{ __('messages.history.title') }}</h2><form method="GET"><select class="rounded-md border border-outline-variant/30 bg-white px-3 py-2 text-sm" name="status" onchange="this.form.submit()"><option value="">{{ __('common.filters.all_statuses') }}</option>@foreach(\App\Models\ContactMessage::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('messages.statuses.'.$status) }}</option>@endforeach</select></form></div>
        <div class="space-y-4">
            @forelse($memberMessages as $message)
                <a class="block rounded-xl border border-slate-100 bg-white p-5 transition hover:border-secondary" href="{{ route('member.messages.show', $message) }}">
                    <div class="flex flex-wrap items-center justify-between gap-2"><strong class="text-primary">{{ $message->ticket_number }}</strong><x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" /></div>
                    <h3 class="mt-3 font-headline text-xl text-primary">{{ $message->subject }}</h3>
                    <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $message->body }}</p>
                    <p class="mt-3 text-xs text-slate-500">{{ $message->messageCategory?->localizedName() }} · {{ $message->created_at?->format('d.m.Y H:i') }}</p>
                </a>
            @empty<p class="rounded-xl bg-white p-6 text-slate-500">{{ __('messages.messages.empty') }}</p>@endforelse
        </div>
        <div class="mt-5">{{ $memberMessages->links() }}</div>
    </section>
</div>

@endsection
