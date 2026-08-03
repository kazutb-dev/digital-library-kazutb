@extends('layouts.admin')

@php
    $isEditing = $newsItem->exists;
    $selectedStatus = old('status', $newsItem->status ?? 'draft');
    $coverUrl = $newsItem->cover_image ? asset('storage/'.$newsItem->cover_image) : null;
@endphp

@section('title', ($isEditing ? __('news.edit') : __('news.create')).' — '.__('common.admin_portal'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('news.ledger')"
        :title="$isEditing ? __('news.edit') : __('news.create')"
        :subtitle="__('news.messages.single_language_notice')"
    >
        <a href="{{ route('admin.news.index') }}" class="admin-btn admin-btn-secondary">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('admin.news.update', $newsItem) : route('admin.news.store') }}"
        enctype="multipart/form-data"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
    >
        @csrf
        @if ($isEditing)
            @method('PATCH')
        @endif

        <div class="space-y-6">
            <section class="admin-card">
                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('news.details') }}</h2>

                <div class="space-y-5">
                    <div>
                        <label for="news-title" class="admin-label">{{ __('news.fields.title') }}</label>
                        <input
                            id="news-title"
                            class="admin-input"
                            type="text"
                            name="title"
                            value="{{ old('title', $newsItem->title) }}"
                            maxlength="255"
                            required
                            autofocus
                        >
                        @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="news-body" class="admin-label">{{ __('news.fields.body') }}</label>
                        <textarea
                            id="news-body"
                            class="admin-input min-h-80 resize-y leading-7"
                            name="body"
                            maxlength="100000"
                            required
                        >{{ old('body', $newsItem->body) }}</textarea>
                        @error('body')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('news.fields.cover_image') }}</h2>

                <div class="grid gap-5 sm:grid-cols-[220px_minmax(0,1fr)] sm:items-center">
                    <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-surface-low">
                        @if ($coverUrl)
                            <img
                                src="{{ $coverUrl }}"
                                alt="{{ __('news.cover.alt', ['title' => $newsItem->title]) }}"
                                class="h-full w-full object-cover"
                            >
                        @else
                            <span class="text-center text-slate-400">
                                <span class="material-symbols-outlined block text-5xl">image</span>
                                <small>{{ __('news.cover.none') }}</small>
                            </span>
                        @endif
                    </div>
                    <div>
                        <label for="news-cover" class="admin-label">{{ $coverUrl ? __('news.actions.replace_cover') : __('common.actions.upload') }}</label>
                        <input
                            id="news-cover"
                            class="admin-input file:mr-3 file:rounded-md file:border-0 file:bg-primary-container file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                            type="file"
                            name="cover_image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('news.cover.hint') }}</p>
                        @error('cover_image')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="admin-card">
                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('news.fields.status') }}</h2>

                <div class="space-y-5">
                    <div>
                        <label for="news-category" class="admin-label">{{ __('news.fields.category') }}</label>
                        <select id="news-category" class="admin-input" name="category" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(old('category', $newsItem->category) === $category)>{{ \Illuminate\Support\Facades\Lang::has('news.categories.'.$category) ? __('news.categories.'.$category) : $category }}</option>
                            @endforeach
                        </select>
                        @error('category')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="news-language" class="admin-label">{{ __('news.fields.language') }}</label>
                        <select id="news-language" class="admin-input" name="language" required>
                            @foreach (\App\Models\News::LANGUAGES as $language)
                                <option value="{{ $language }}" @selected(old('language', $newsItem->language) === $language)>{{ __('news.languages.'.$language) }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('news.messages.single_language_notice') }}</p>
                        @error('language')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="news-status" class="admin-label">{{ __('news.fields.status') }}</label>
                        <select id="news-status" class="admin-input" name="status" required>
                            @foreach (\App\Models\News::STATUSES as $status)
                                <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ __('news.statuses.'.$status) }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="news-publish-at" class="admin-label">{{ __('news.fields.publish_at') }}</label>
                        <input
                            id="news-publish-at"
                            class="admin-input"
                            type="datetime-local"
                            name="publish_at"
                            value="{{ old('publish_at', $newsItem->publish_at?->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}"
                        >
                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('news.messages.publish_at_required') }}</p>
                        @error('publish_at')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-xl bg-surface-low p-4">
                        <input type="hidden" name="show_on_homepage" value="0">
                        <label for="news-homepage" class="flex cursor-pointer items-start gap-3">
                            <input
                                id="news-homepage"
                                class="mt-1 rounded border-slate-300 text-secondary focus:ring-secondary"
                                type="checkbox"
                                name="show_on_homepage"
                                value="1"
                                @checked((bool) old('show_on_homepage', $newsItem->show_on_homepage))
                            >
                            <span>
                                <strong class="block text-sm text-primary">{{ __('news.fields.show_on_homepage') }}</strong>
                                <small class="mt-1 block leading-5 text-slate-500">{{ __('news.actions.feature') }}</small>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary w-full">
                        <span class="material-symbols-outlined text-[19px]">save</span>
                        {{ __('common.actions.save_changes') }}
                    </button>
                </div>
            </section>

            @if ($isEditing)
                <section class="admin-card text-sm">
                    <h2 class="mb-4 font-headline text-xl text-primary">{{ __('news.details') }}</h2>
                    <dl class="space-y-3">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('news.fields.created_by') }}</dt>
                            <dd class="text-right font-semibold">{{ $newsItem->creator?->name ?? __('common.time.not_available') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('news.fields.created_at') }}</dt>
                            <dd class="text-right">{{ $newsItem->created_at?->format('d.m.Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('news.fields.updated_at') }}</dt>
                            <dd class="text-right">{{ $newsItem->updated_at?->format('d.m.Y H:i') }}</dd>
                        </div>
                        @if ($newsItem->publisher)
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ __('news.fields.published_by') }}</dt>
                                <dd class="text-right font-semibold">{{ $newsItem->publisher->name }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            @endif
        </aside>
    </form>

    @if ($isEditing && auth()->user()?->can('news.delete'))
        <details class="admin-card mt-6 border border-red-100">
            <summary class="flex cursor-pointer items-center justify-between gap-4 font-semibold text-red-800">
                <span class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                    {{ __('common.actions.delete') }}
                </span>
                <span class="material-symbols-outlined">expand_more</span>
            </summary>
            <form method="POST" action="{{ route('admin.news.destroy', $newsItem) }}" class="mt-5 max-w-2xl">
                @csrf
                @method('DELETE')
                <p class="mb-4 text-sm leading-6 text-slate-600">{{ __('news.messages.delete_confirm', ['title' => $newsItem->title]) }}</p>
                <label for="news-delete-reason" class="admin-label">{{ __('common.fields.reason') }}</label>
                <textarea id="news-delete-reason" class="admin-input min-h-24" name="reason" minlength="5" maxlength="1000" required>{{ old('reason') }}</textarea>
                @error('reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="admin-btn admin-btn-danger mt-4">
                    <span class="material-symbols-outlined text-[19px]">delete_forever</span>
                    {{ __('common.actions.delete') }}
                </button>
            </form>
        </details>
    @endif
@endsection
