@extends('layouts.librarian')

@php
    $statusTone = static fn (?string $status): string => match ((string) $status) {
        'draft' => 'inactive',
        'scheduled' => 'scheduled',
        'published' => 'published',
        'archived' => 'archived',
        default => 'unknown',
    };

    $categoryLabel = static fn (?string $category): string => $category
        ? (\Illuminate\Support\Facades\Lang::has('news.categories.'.$category) ? __('news.categories.'.$category) : $category)
        : '—';
@endphp

@section('title', __('librarian.news.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.news.eyebrow')"
        :title="__('librarian.news.title')"
        :subtitle="__('librarian.news.subtitle')"
    >
        @can('news.create')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.news.create') }}">
                <span class="material-symbols-outlined text-[19px]">post_add</span>
                {{ __('librarian.news.create') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div role="note" class="mb-6 flex items-start gap-3 rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-semibold text-cyan-900">
        <span class="material-symbols-outlined text-[20px]">info</span>
        <span>{{ __('librarian.news.scope_note') }}</span>
    </div>

    <form method="GET" action="{{ route('librarian.news.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="sm:col-span-2">
                <span class="admin-label">{{ __('common.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('common.filters.search_placeholder') }}"
                        maxlength="200"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.news.fields.status') }}</span>
                <select class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (['draft', 'scheduled', 'published', 'archived'] as $statusKey)
                        <option value="{{ $statusKey }}" @selected(($filters['status'] ?? '') === $statusKey)>{{ __('librarian.news.statuses.'.$statusKey) }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button class="admin-btn admin-btn-primary flex-1" type="submit">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('librarian.news.index') }}"
                    title="{{ __('common.actions.clear_filters') }}"
                    aria-label="{{ __('common.actions.clear_filters') }}"
                >
                    <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
                </a>
            </div>
        </div>
    </form>

    <section class="admin-card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1080px]">
                <thead>
                    <tr>
                        <th>{{ __('librarian.news.fields.title') }}</th>
                        <th>{{ __('librarian.news.fields.category') }}</th>
                        <th>{{ __('librarian.news.fields.language') }}</th>
                        <th>{{ __('librarian.news.fields.status') }}</th>
                        <th>{{ __('librarian.news.fields.show_on_homepage') }}</th>
                        <th>{{ __('common.fields.created_at') }}</th>
                        <th>{{ __('news.fields.publish_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($news as $newsItem)
                        <tr>
                            <td>
                                <a class="group block min-w-64 max-w-lg" href="{{ route('librarian.news.edit', $newsItem) }}">
                                    <strong class="block text-sm text-primary group-hover:text-secondary">{{ $newsItem->title }}</strong>
                                    @if ($newsItem->excerpt)
                                        <span class="mt-1 block truncate text-xs text-slate-500">{{ $newsItem->excerpt }}</span>
                                    @endif
                                </a>
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ $categoryLabel($newsItem->category) }}</td>
                            <td class="whitespace-nowrap text-slate-600">
                                {{ $newsItem->language && \Illuminate\Support\Facades\Lang::has('common.languages.'.$newsItem->language) ? __('common.languages.'.$newsItem->language) : ($newsItem->language ?: '—') }}
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$statusTone($newsItem->status)"
                                    :label="__('librarian.news.statuses.'.$newsItem->status)"
                                />
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($newsItem->show_on_homepage)
                                    <span class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-700">
                                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                        {{ __('common.boolean.yes') }}
                                    </span>
                                @else
                                    <span class="text-sm text-slate-400">{{ __('common.boolean.no') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ $newsItem->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-slate-600">{{ $newsItem->publish_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">newspaper</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.news.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$news" />
    </section>
@endsection
