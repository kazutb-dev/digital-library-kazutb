@extends('layouts.librarian')

@section('title', __('librarian.messages.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $activeStatus = (string) ($filters['status'] ?? '');

        $chipUrl = static fn (?string $status): string => route('librarian.messages.index', array_filter(array_merge(
            request()->except(['page', 'status']),
            ['status' => $status],
        )));

        $chipClass = static fn (bool $active): string => $active
            ? 'border-secondary bg-secondary text-white'
            : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary';
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.messages.eyebrow')"
        :title="__('librarian.messages.title')"
        :subtitle="__('librarian.messages.subtitle')"
    />

    @if ($bibliographicScope ?? false)
        <div class="mb-6 rounded-xl border border-secondary/20 bg-secondary/5 px-5 py-4 text-sm leading-6 text-on-surface-variant">
            {{ __('messages.bibliographer_scope') }}
        </div>
    @endif

    <div class="mb-6 flex flex-wrap gap-2">
        <a
            href="{{ $chipUrl(null) }}"
            class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors {{ $chipClass($activeStatus === '') }}"
            @if ($activeStatus === '') aria-current="page" @endif
        >
            {{ __('common.filters.all') }}
            <span class="text-xs font-bold opacity-70">{{ number_format((int) $statusCounts->sum(), 0, ',', ' ') }}</span>
        </a>

        @foreach (\App\Models\ContactMessage::STATUSES as $status)
            <a
                href="{{ $chipUrl($status) }}"
                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors {{ $chipClass($activeStatus === $status) }}"
                @if ($activeStatus === $status) aria-current="page" @endif
            >
                {{ __('messages.statuses.'.$status) }}
                <span class="text-xs font-bold opacity-70">{{ number_format((int) ($statusCounts[$status] ?? 0), 0, ',', ' ') }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('librarian.messages.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="md:col-span-2">
                <label for="message-search" class="admin-label">{{ __('common.filters.search') }}</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        id="message-search"
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        maxlength="200"
                        placeholder="{{ __('messages.search_placeholder') }}"
                    >
                </div>
                @error('search')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="message-status" class="admin-label">{{ __('messages.fields.status') }}</label>
                <select id="message-status" class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (\App\Models\ContactMessage::STATUSES as $status)
                        <option value="{{ $status }}" @selected($activeStatus === $status)>{{ __('messages.statuses.'.$status) }}</option>
                    @endforeach
                </select>
                @error('status')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="admin-btn admin-btn-primary flex-1">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('librarian.messages.index') }}"
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
                        <th>{{ __('messages.fields.subject') }}</th>
                        <th>{{ __('librarian.messages.sender') }}</th>
                        <th>{{ __('messages.fields.category') }}</th>
                        <th>{{ __('common.fields.status') }}</th>
                        <th>{{ __('common.fields.priority') }}</th>
                        <th>{{ __('messages.fields.assigned_to') }}</th>
                        <th>{{ __('librarian.messages.received') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $contactMessage)
                        <tr>
                            <td class="max-w-sm">
                                <a href="{{ route('librarian.messages.show', $contactMessage) }}" class="group block min-w-64">
                                    <strong class="block font-headline text-lg leading-5 text-primary group-hover:text-secondary">{{ $contactMessage->subject }}</strong>
                                    <small class="mt-1 block line-clamp-2 leading-5 text-slate-500">{{ $contactMessage->body }}</small>
                                    <small class="mt-1 block text-xs text-slate-400">#{{ $contactMessage->id }}</small>
                                </a>
                            </td>
                            <td>
                                <span class="block font-semibold text-primary">{{ $contactMessage->sender?->name ?? $contactMessage->sender_email }}</span>
                                @if ($contactMessage->sender)
                                    <small class="block text-slate-500">{{ $contactMessage->sender_email }}</small>
                                @endif
                            </td>
                            <td class="text-slate-600">
                                {{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$contactMessage->category)
                                    ? __('messages.categories.'.$contactMessage->category)
                                    : $contactMessage->category }}
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$contactMessage->status"
                                    :label="__('messages.statuses.'.$contactMessage->status)"
                                />
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$contactMessage->priority"
                                    :label="__('messages.priorities.'.$contactMessage->priority)"
                                />
                            </td>
                            <td class="text-slate-600">{{ $contactMessage->assignee?->name ?? __('messages.messages.unassigned') }}</td>
                            <td class="whitespace-nowrap text-slate-600">
                                <time datetime="{{ $contactMessage->created_at?->toIso8601String() }}">
                                    {{ $contactMessage->created_at?->format('d.m.Y H:i') ?? '—' }}
                                </time>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">inbox</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.messages.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$messages" />
    </section>
@endsection
