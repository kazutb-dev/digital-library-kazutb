@extends('layouts.librarian')

@php
    $isEditing = $item->exists;
    $selectedStatus = old('status', $item->status ?? 'draft');

    $statusTone = static fn (?string $status): string => match ((string) $status) {
        'draft' => 'inactive',
        'scheduled' => 'scheduled',
        'published' => 'published',
        'archived' => 'archived',
        default => 'unknown',
    };

    $categoryLabel = static fn (string $category): string => \Illuminate\Support\Facades\Lang::has('news.categories.'.$category)
        ? __('news.categories.'.$category)
        : $category;
@endphp

@section('title', ($isEditing ? __('librarian.news.edit') : __('librarian.news.create')).' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.news.eyebrow')"
        :title="$isEditing ? __('librarian.news.edit') : __('librarian.news.create')"
        :subtitle="$isEditing ? $item->title : __('librarian.news.scope_note')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.news.index') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
    </x-admin.page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('librarian.news.update', $item) : route('librarian.news.store') }}"
        class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
    >
        @csrf
        @if ($isEditing)
            @method('PATCH')
        @endif

        <div class="space-y-6">
            <section class="admin-card space-y-5">
                <div>
                    <label for="news-title" class="admin-label">{{ __('librarian.news.fields.title') }}</label>
                    <input
                        id="news-title"
                        class="admin-input"
                        type="text"
                        name="title"
                        value="{{ old('title', $item->title) }}"
                        maxlength="500"
                        required
                        autofocus
                    >
                    @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="news-excerpt" class="admin-label">{{ __('librarian.news.fields.excerpt') }}</label>
                    <textarea
                        id="news-excerpt"
                        class="admin-input min-h-24 resize-y leading-6"
                        name="excerpt"
                        maxlength="1000"
                    >{{ old('excerpt', $item->excerpt) }}</textarea>
                    @error('excerpt')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="news-body" class="admin-label">{{ __('librarian.news.fields.body') }}</label>
                    <textarea
                        id="news-body"
                        class="admin-input min-h-96 resize-y leading-7"
                        name="body"
                        maxlength="65000"
                        required
                    >{{ old('body', $item->body) }}</textarea>
                    @error('body')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="admin-card space-y-5">
                <div>
                    <label for="news-category" class="admin-label">{{ __('librarian.news.fields.category') }}</label>
                    <select id="news-category" class="admin-input" name="category" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category', $item->category) === $category)>{{ $categoryLabel((string) $category) }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="news-language" class="admin-label">{{ __('librarian.news.fields.language') }}</label>
                    <select id="news-language" class="admin-input" name="language" required>
                        @foreach (['ru', 'kk', 'en'] as $languageKey)
                            <option value="{{ $languageKey }}" @selected(old('language', $item->language) === $languageKey)>{{ __('common.languages.'.$languageKey) }}</option>
                        @endforeach
                    </select>
                    @error('language')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="news-status" class="admin-label">{{ __('librarian.news.fields.status') }}</label>
                    <select id="news-status" class="admin-input" name="status" required>
                        @foreach (['draft', 'published'] as $statusKey)
                            <option value="{{ $statusKey }}" @selected($selectedStatus === $statusKey)>{{ __('librarian.news.statuses.'.$statusKey) }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-xl bg-surface-container-low p-4">
                    <input type="hidden" name="show_on_homepage" value="0">
                    <label for="news-homepage" class="flex cursor-pointer items-start gap-3">
                        <input
                            id="news-homepage"
                            class="mt-1 rounded border-slate-300 text-secondary focus:ring-secondary"
                            type="checkbox"
                            name="show_on_homepage"
                            value="1"
                            @checked((bool) old('show_on_homepage', $item->show_on_homepage))
                        >
                        <span class="text-sm font-semibold text-primary">{{ __('librarian.news.fields.show_on_homepage') }}</span>
                    </label>
                    @error('show_on_homepage')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="admin-btn admin-btn-primary w-full">
                    <span class="material-symbols-outlined text-[19px]">save</span>
                    {{ $isEditing ? __('common.actions.save_changes') : __('common.actions.save') }}
                </button>
            </section>

            @if ($isEditing)
                <section class="admin-card text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('common.fields.status') }}</span>
                        <x-admin.status-badge
                            :status="$statusTone($item->status)"
                            :label="__('librarian.news.statuses.'.$item->status)"
                        />
                    </div>
                    <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('common.fields.created_at') }}</dt>
                            <dd class="text-right">{{ $item->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('common.fields.updated_at') }}</dt>
                            <dd class="text-right">{{ $item->updated_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('news.fields.publish_at') }}</dt>
                            <dd class="text-right">{{ $item->publish_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if ($item->status === 'published' && $item->slug)
                        <a
                            class="admin-btn admin-btn-secondary mt-4 w-full"
                            href="{{ route('news.show', $item->slug) }}"
                            target="_blank"
                            rel="noopener"
                        >
                            <span class="material-symbols-outlined text-[19px]">open_in_new</span>
                            {{ __('common.actions.preview') }}
                        </a>
                    @endif
                </section>
            @endif
        </aside>
    </form>
@endsection
