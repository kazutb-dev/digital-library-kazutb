@extends('layouts.admin')

@section('title', __('messages.title').' — '.__('common.admin_portal'))

@section('content')
    <x-admin.page-header
        :eyebrow="__('messages.inbox')"
        :title="__('messages.title')"
        :subtitle="__('messages.subtitle')"
    >
        @can('reports.export')
            <a href="{{ route('admin.messages.export', request()->query()) }}" class="admin-btn admin-btn-secondary">
                <span class="material-symbols-outlined text-[19px]">download</span>
                {{ __('messages.export') }}
            </a>
        @endcan
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.messages.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="md:col-span-2 xl:col-span-2">
                <label for="message-search" class="admin-label">{{ __('common.filters.search') }}</label>
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        id="message-search"
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        maxlength="160"
                        placeholder="{{ __('messages.search_placeholder') }}"
                    >
                </div>
            </div>

            <div>
                <label for="message-category" class="admin-label">{{ __('messages.fields.category') }}</label>
                <select id="message-category" class="admin-input" name="category">
                    <option value="">{{ __('common.filters.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$category) ? __('messages.categories.'.$category) : $category }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="message-status" class="admin-label">{{ __('messages.fields.status') }}</label>
                <select id="message-status" class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (\App\Models\ContactMessage::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('messages.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="message-priority" class="admin-label">{{ __('messages.fields.priority') }}</label>
                <select id="message-priority" class="admin-input" name="priority">
                    <option value="">{{ __('common.fields.all') }}</option>
                    @foreach (\App\Models\ContactMessage::PRIORITIES as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ __('messages.priorities.'.$priority) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="message-date-from" class="admin-label">{{ __('common.filters.date_from') }}</label>
                <input id="message-date-from" class="admin-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </div>

            <div>
                <label for="message-date-to" class="admin-label">{{ __('common.filters.date_to') }}</label>
                <input id="message-date-to" class="admin-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </div>

            <div>
                <label for="message-sort" class="admin-label">{{ __('common.filters.sort_by') }}</label>
                <select id="message-sort" class="admin-input" name="sort">
                    @foreach ([
                        'created_at' => __('common.fields.created_at'),
                        'updated_at' => __('common.fields.updated_at'),
                        'priority' => __('messages.fields.priority'),
                        'status' => __('messages.fields.status'),
                    ] as $sort => $label)
                        <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'created_at') === $sort)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="message-direction" class="admin-label">{{ __('common.actions.sort') }}</label>
                <select id="message-direction" class="admin-input" name="direction">
                    <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>{{ __('common.filters.sort_descending') }}</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>{{ __('common.filters.sort_ascending') }}</option>
                </select>
            </div>

            <div class="flex items-end gap-2 md:col-span-2 xl:col-span-4">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a href="{{ route('admin.messages.index') }}" class="admin-btn admin-btn-secondary">
                    {{ __('common.actions.clear_filters') }}
                </a>
            </div>
        </div>
    </form>

    <section class="overflow-hidden rounded-xl bg-white shadow-[0_12px_35px_rgba(0,6,19,.035)]">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[1120px]">
                <thead>
                    <tr>
                        <th>{{ __('messages.fields.subject') }}</th>
                        <th>{{ __('messages.fields.sender') }}</th>
                        <th>{{ __('messages.fields.category') }}</th>
                        <th>{{ __('messages.fields.status') }}</th>
                        <th>{{ __('messages.fields.priority') }}</th>
                        <th>{{ __('messages.fields.assigned_to') }}</th>
                        <th>{{ __('messages.fields.received_at') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr>
                            <td class="max-w-sm">
                                <a href="{{ route('admin.messages.show', $message) }}" class="block min-w-64 group">
                                    <strong class="block font-headline text-lg leading-5 text-primary group-hover:text-secondary">{{ $message->subject }}</strong>
                                    <small class="mt-1 block line-clamp-2 leading-5 text-slate-500">{{ $message->body }}</small>
                                    <small class="mt-1 block text-xs text-slate-400">#{{ $message->id }}</small>
                                </a>
                            </td>
                            <td>
                                <span class="block font-semibold text-primary">{{ $message->sender?->name ?? $message->sender_email }}</span>
                                @if ($message->sender)
                                    <small class="block text-slate-500">{{ $message->sender_email }}</small>
                                @endif
                            </td>
                            <td>{{ \Illuminate\Support\Facades\Lang::has('messages.categories.'.$message->category) ? __('messages.categories.'.$message->category) : $message->category }}</td>
                            <td>
                                <x-admin.status-badge :status="$message->status" :label="__('messages.statuses.'.$message->status)" />
                            </td>
                            <td>
                                <x-admin.status-badge :status="$message->priority" :label="__('messages.priorities.'.$message->priority)" />
                            </td>
                            <td>{{ $message->assignee?->name ?? __('messages.messages.unassigned') }}</td>
                            <td class="whitespace-nowrap">
                                <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->format('d.m.Y H:i') }}</time>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    @if ($message->status === 'open' && auth()->user()?->can('messages.resolve'))
                                        <form method="POST" action="{{ route('admin.messages.update', $message) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="in_review">
                                            <input type="hidden" name="assigned_to" value="{{ $message->assigned_to }}">
                                            <input type="hidden" name="priority" value="{{ $message->priority }}">
                                            <input type="hidden" name="resolution_comment" value="{{ $message->resolution_comment }}">
                                            <button type="submit" class="admin-btn admin-btn-secondary whitespace-nowrap px-3 py-2">
                                                {{ __('messages.actions.start_review') }}
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.messages.show', $message) }}" class="admin-btn admin-btn-primary px-3 py-2">
                                        <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                                        {{ __('common.actions.view') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-14 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">inbox</span>
                                <p class="text-slate-500">{{ __('messages.messages.empty') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.pagination :paginator="$messages" />
    </section>
@endsection
