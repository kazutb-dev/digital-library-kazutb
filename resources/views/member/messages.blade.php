@extends('layouts.member', ['title' => __('messages.title').' — '.__('common.app_name')])

@section('content')
    <header class="mb-10 md:mb-14">
        <p class="mb-3 text-xs font-bold uppercase tracking-[.15em] text-secondary">{{ __('messages.submitted') }}</p>
        <h1 class="font-headline text-4xl leading-none text-primary md:text-6xl">{{ __('messages.title') }}</h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-on-surface-variant">{{ __('messages.subtitle') }}</p>
    </header>

    @if (session('success'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
        <section class="lg:col-span-7">
            <form method="POST" enctype="multipart/form-data" action="{{ route('member.messages.store') }}" class="space-y-6 rounded-xl bg-surface-container-lowest p-6 shadow-[0_24px_48px_rgba(0,6,19,0.04)] md:p-8">
                @csrf
                <h2 class="border-b border-outline-variant/20 pb-4 font-headline text-2xl text-primary">{{ __('messages.actions.submit') }}</h2>

                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="member-message-category">{{ __('messages.fields.category') }}</label>
                    <select class="w-full rounded-md border-outline-variant/30 bg-surface focus:border-secondary focus:ring-secondary" id="member-message-category" name="category" required>
                        @foreach ($messageCategories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>
                                {{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$category) ? __('messages.categories.'.$category) : str($category)->replace(['_', '-'], ' ')->headline() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="member-message-subject">{{ __('messages.fields.subject') }}</label>
                    <input class="w-full rounded-md border-outline-variant/30 bg-surface focus:border-secondary focus:ring-secondary" id="member-message-subject" name="subject" required maxlength="255" value="{{ old('subject') }}">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="member-message-body">{{ __('messages.fields.body') }}</label>
                    <textarea class="w-full rounded-md border-outline-variant/30 bg-surface focus:border-secondary focus:ring-secondary" id="member-message-body" name="body" required maxlength="20000" rows="8">{{ old('body') }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-primary" for="member-message-attachments">{{ __('messages.fields.attachments') }}</label>
                    <input class="w-full rounded-md border border-outline-variant/30 bg-surface p-3 text-sm" id="member-message-attachments" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">
                    <p class="mt-2 text-xs text-on-surface-variant">{{ __('messages.messages.attachment_hint') }}</p>
                </div>
                <div class="flex justify-end">
                    <button class="flex items-center gap-2 rounded-md bg-gradient-to-r from-primary to-primary-container px-7 py-3 text-sm font-bold text-white hover:opacity-90" type="submit">
                        {{ __('common.actions.submit') }}<span class="material-symbols-outlined text-[18px]">send</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="lg:col-span-5">
            <h2 class="mb-5 border-b border-outline-variant/20 pb-4 font-headline text-2xl text-primary">{{ __('messages.history.title') }}</h2>
            <div class="space-y-4">
                @forelse ($memberMessages as $message)
                    <article class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-5">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <span class="rounded-full bg-secondary/10 px-2.5 py-1 text-xs font-bold text-secondary">
                                {{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$message->category) ? __('messages.categories.'.$message->category) : str($message->category)->replace(['_', '-'], ' ')->headline() }}
                            </span>
                            <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-bold text-on-surface-variant">{{ __('messages.statuses.'.$message->status) }}</span>
                        </div>
                        <h3 class="font-headline text-xl text-primary"><a href="{{ route('member.messages.show',$message) }}" class="hover:text-secondary">{{ $message->subject }}</a></h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-on-surface-variant">{{ $message->body }}</p>
                        <div class="mt-4 flex items-center justify-between text-xs text-on-surface-variant">
                            <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('d.m.Y H:i') }}</time>
                            <a class="font-semibold text-secondary" href="{{ route('member.messages.show',$message) }}">{{ __('common.actions.view') }}</a>
                        </div>
                        @if ($message->resolution_comment)
                            <p class="mt-4 rounded-lg bg-secondary/5 p-3 text-sm leading-6 text-primary">{{ $message->resolution_comment }}</p>
                        @endif
                    </article>
                @empty
                    <p class="rounded-xl bg-surface-container-low p-6 text-center text-sm text-on-surface-variant">{{ __('messages.messages.empty') }}</p>
                @endforelse
            </div>
            @if ($memberMessages instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="mt-5">{{ $memberMessages->links() }}</div>
            @endif
        </section>
    </div>
@endsection
