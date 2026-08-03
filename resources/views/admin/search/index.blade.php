@extends('layouts.admin')

@section('title', __('admin.search.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('admin.search.eyebrow')"
        :title="__('admin.search.title')"
        :subtitle="__('admin.search.subtitle')"
    />

    <form method="GET" action="{{ route('admin.search') }}" class="admin-card mb-6">
        <label>
            <span class="admin-label">{{ __('admin.search.query_label') }}</span>
            <div class="flex gap-2">
                <input
                    class="admin-input"
                    type="search"
                    name="q"
                    value="{{ $query }}"
                    minlength="2"
                    placeholder="{{ __('admin.search.placeholder') }}"
                    autofocus
                >
                <button class="admin-btn admin-btn-primary shrink-0" type="submit">
                    <span class="material-symbols-outlined text-[19px]">search</span>
                    {{ __('common.actions.search') }}
                </button>
            </div>
        </label>
    </form>

    @if ($query === '' || mb_strlen($query) < 2)
        <section class="admin-card py-16 text-center">
            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">manage_search</span>
            <p class="text-sm text-slate-500">{{ __('admin.search.enter_query') }}</p>
        </section>
    @elseif ($groups === [])
        <section class="admin-card py-16 text-center">
            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">search_off</span>
            <p class="text-sm text-slate-500">{{ __('admin.search.no_results', ['query' => $query]) }}</p>
        </section>
    @else
        <p class="mb-4 text-sm text-slate-500">{{ __('admin.search.results_count', ['count' => $totalResults, 'query' => $query]) }}</p>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($groups as $group)
                <section class="admin-card p-0">
                    <h2 class="border-b border-slate-100 px-5 py-4 font-headline text-2xl text-primary">{{ $group['label'] }}</h2>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a class="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-slate-50" href="{{ $item['url'] }}">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-primary">{{ $item['title'] }}</span>
                                        @if ($item['subtitle'] !== '')
                                            <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $item['subtitle'] }}</span>
                                        @endif
                                    </span>
                                    <span class="material-symbols-outlined shrink-0 text-[18px] text-slate-400">chevron_right</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
@endsection
