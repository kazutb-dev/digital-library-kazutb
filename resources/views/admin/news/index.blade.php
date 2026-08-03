@extends('layouts.admin')

@section('title', __('news.title').' — '.__('common.admin_portal'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('news.ledger')"
        :title="__('news.title')"
        :subtitle="__('news.subtitle')"
    >
        @if (auth()->user()?->can('reports.export') && auth()->user()?->can('news.edit_any'))
            <a href="{{ route('admin.news.export', request()->query()) }}" class="admin-btn admin-btn-secondary">
                <span class="material-symbols-outlined text-[19px]">download</span>
                {{ __('news.export') }}
            </a>
        @endif
        @can('news.create')
            <a href="{{ route('admin.news.create') }}" class="admin-btn admin-btn-primary">
                <span class="material-symbols-outlined text-[19px]">add</span>
                {{ __('news.create') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (\App\Models\News::STATUSES as $status)
            <a
                href="{{ route('admin.news.index', array_filter(['status' => $status])) }}"
                @class([
                    'admin-card flex items-center justify-between border p-4 transition hover:-translate-y-0.5',
                    'border-secondary ring-1 ring-secondary' => ($filters['status'] ?? null) === $status,
                    'border-transparent' => ($filters['status'] ?? null) !== $status,
                ])
            >
                <span class="text-sm font-semibold text-slate-600">{{ __('news.statuses.'.$status) }}</span>
                <span class="font-headline text-3xl text-primary">{{ number_format((int) ($statusCounts[$status] ?? 0), 0, '.', ' ') }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.news.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="md:col-span-2 xl:col-span-2">
                <label for="news-search" class="admin-label">{{ __('common.filters.search') }}</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        id="news-search"
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        maxlength="160"
                        placeholder="{{ __('news.search_placeholder') }}"
                    >
                </div>
            </div>

            <div>
                <label for="news-status" class="admin-label">{{ __('news.fields.status') }}</label>
                <select id="news-status" class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (\App\Models\News::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('news.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="news-category" class="admin-label">{{ __('news.fields.category') }}</label>
                <select id="news-category" class="admin-input" name="category">
                    <option value="">{{ __('common.filters.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ \Illuminate\Support\Facades\Lang::has('news.categories.'.$category) ? __('news.categories.'.$category) : $category }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="news-language" class="admin-label">{{ __('news.fields.language') }}</label>
                <select id="news-language" class="admin-input" name="language">
                    <option value="">{{ __('common.fields.all') }}</option>
                    @foreach (\App\Models\News::LANGUAGES as $language)
                        <option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>{{ __('news.languages.'.$language) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="news-sort" class="admin-label">{{ __('common.filters.sort_by') }}</label>
                <select id="news-sort" class="admin-input" name="sort">
                    @foreach ([
                        'created_at' => __('news.fields.created_at'),
                        'updated_at' => __('news.fields.updated_at'),
                        'publish_at' => __('news.fields.published_at'),
                        'title' => __('news.fields.title'),
                    ] as $sort => $label)
                        <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'created_at') === $sort)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="news-direction" class="admin-label">{{ __('common.actions.sort') }}</label>
                <select id="news-direction" class="admin-input" name="direction">
                    <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>{{ __('common.filters.sort_descending') }}</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>{{ __('common.filters.sort_ascending') }}</option>
                </select>
            </div>

            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-5">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a href="{{ route('admin.news.index') }}" class="admin-btn admin-btn-secondary">
                    {{ __('common.actions.clear_filters') }}
                </a>
            </div>
        </div>
    </form>

    <section class="overflow-hidden rounded-xl bg-white shadow-[0_12px_35px_rgba(0,6,19,.035)]">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1050px]">
                <thead>
                    <tr>
                        <th>{{ __('news.fields.title') }}</th>
                        <th>{{ __('news.fields.category') }}</th>
                        <th>{{ __('news.fields.language') }}</th>
                        <th>{{ __('news.fields.status') }}</th>
                        <th>{{ __('news.fields.show_on_homepage') }}</th>
                        <th>{{ __('news.fields.published_at') }}</th>
                        <th>{{ __('news.fields.created_by') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($newsItems as $newsItem)
                        <tr>
                            <td class="max-w-md">
                                <div class="flex min-w-64 items-start gap-3">
                                    @if ($newsItem->cover_image)
                                        <img
                                            src="{{ asset('storage/'.$newsItem->cover_image) }}"
                                            alt="{{ __('news.cover.alt', ['title' => $newsItem->title]) }}"
                                            class="h-14 w-16 shrink-0 rounded-lg object-cover"
                                        >
                                    @else
                                        <span class="flex h-14 w-16 shrink-0 items-center justify-center rounded-lg bg-primary-container text-white">
                                            <span class="material-symbols-outlined">newspaper</span>
                                        </span>
                                    @endif
                                    <span>
                                        <strong class="block font-headline text-lg leading-5 text-primary">{{ $newsItem->title }}</strong>
                                        @if ($newsItem->excerpt)
                                            <small class="mt-1 block line-clamp-2 leading-5 text-slate-500">{{ $newsItem->excerpt }}</small>
                                        @endif
                                        <small class="mt-1 block text-xs text-slate-400">#{{ $newsItem->id }}</small>
                                    </span>
                                </div>
                            </td>
                            <td>{{ \Illuminate\Support\Facades\Lang::has('news.categories.'.$newsItem->category) ? __('news.categories.'.$newsItem->category) : $newsItem->category }}</td>
                            <td>
                                <span class="font-semibold uppercase text-slate-600">{{ $newsItem->language }}</span>
                            </td>
                            <td>
                                <x-admin.status-badge :status="$newsItem->status" :label="__('news.statuses.'.$newsItem->status)" />
                            </td>
                            <td>
                                <span @class([
                                    'inline-flex items-center gap-1 text-xs font-semibold',
                                    'text-secondary' => $newsItem->show_on_homepage,
                                    'text-slate-400' => ! $newsItem->show_on_homepage,
                                ])>
                                    <span class="material-symbols-outlined text-[18px]">{{ $newsItem->show_on_homepage ? 'home' : 'home_off' }}</span>
                                    {{ $newsItem->show_on_homepage ? __('common.boolean.yes') : __('common.boolean.no') }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap">
                                @if ($newsItem->publish_at)
                                    <time datetime="{{ $newsItem->publish_at->toIso8601String() }}">
                                        {{ $newsItem->publish_at->setTimezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                    </time>
                                @else
                                    <span class="text-slate-400">{{ __('common.time.not_available') }}</span>
                                @endif
                            </td>
                            <td>
                                {{ $newsItem->creator?->name ?? __('common.time.not_available') }}
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.news.edit', $newsItem) }}" class="admin-btn admin-btn-secondary px-3 py-2">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                        {{ __('common.actions.edit') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-14 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">newspaper</span>
                                <p class="text-slate-500">{{ __('news.messages.empty') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.pagination :paginator="$newsItems" />
    </section>
@endsection
