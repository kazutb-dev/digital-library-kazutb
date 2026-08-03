@extends('layouts.librarian')

@php
    $statusTone = static fn (?string $status): string => match ((string) $status) {
        'draft' => 'inactive',
        'under_review' => 'pending',
        'rejected' => 'failed',
        'published' => 'published',
        'archived' => 'archived',
        default => 'approved',
    };

    $statusUrl = static fn (?string $status): string => route('librarian.repository', array_filter(
        array_merge(request()->except(['page', 'status']), ['status' => $status]),
        static fn ($value): bool => $value !== null && $value !== '',
    ));

    $activeStatus = (string) ($filters['status'] ?? '');
@endphp

@section('title', __('librarian.repository.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.repository.eyebrow')"
        :title="__('librarian.repository.title')"
        :subtitle="__('librarian.repository.subtitle')"
    >
        @can('repository.upload')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.repository.create') }}">
                <span class="material-symbols-outlined text-[19px]">upload_file</span>
                {{ __('librarian.repository.create') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mb-6 flex flex-wrap gap-2">
        <a
            href="{{ $statusUrl(null) }}"
            class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-bold transition {{ $activeStatus === '' ? 'border-primary-container bg-primary-container text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary' }}"
        >
            {{ __('common.filters.all') }}
            <span class="rounded-full {{ $activeStatus === '' ? 'bg-white/20' : 'bg-slate-100' }} px-1.5 py-0.5 text-[11px]">{{ $statusCounts->sum() }}</span>
        </a>
        @foreach (\App\Models\Catalog\RepositoryItem::STATUSES as $statusKey)
            <a
                href="{{ $statusUrl($statusKey) }}"
                class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-bold transition {{ $activeStatus === $statusKey ? 'border-primary-container bg-primary-container text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary' }}"
            >
                {{ __('librarian.repository.statuses.'.$statusKey) }}
                <span class="rounded-full {{ $activeStatus === $statusKey ? 'bg-white/20' : 'bg-slate-100' }} px-1.5 py-0.5 text-[11px]">{{ $statusCounts->get($statusKey, 0) }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('librarian.repository') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="sm:col-span-2 xl:col-span-1">
                <span class="admin-label">{{ __('librarian.repository.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('librarian.repository.filters.search') }}"
                        maxlength="200"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.repository.filters.status') }}</span>
                <select class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (\App\Models\Catalog\RepositoryItem::STATUSES as $statusKey)
                        <option value="{{ $statusKey }}" @selected($activeStatus === $statusKey)>{{ __('librarian.repository.statuses.'.$statusKey) }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.repository.filters.work_type') }}</span>
                <select class="admin-input" name="work_type">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach (\App\Models\Catalog\RepositoryItem::WORK_TYPES as $typeKey)
                        <option value="{{ $typeKey }}" @selected(($filters['work_type'] ?? '') === $typeKey)>{{ __('librarian.repository.work_types.'.$typeKey) }}</option>
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
                    href="{{ route('librarian.repository') }}"
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
            <table class="admin-table min-w-[1180px]">
                <thead>
                    <tr>
                        <th>{{ __('librarian.repository.fields.title') }}</th>
                        <th>{{ __('librarian.repository.fields.authors') }}</th>
                        <th>{{ __('librarian.repository.fields.work_type') }}</th>
                        <th>{{ __('librarian.repository.fields.year') }}</th>
                        <th>{{ __('librarian.repository.fields.department') }}</th>
                        <th>{{ __('common.fields.status') }}</th>
                        <th>{{ __('librarian.repository.fields.uploaded_by') }}</th>
                        <th>{{ __('librarian.repository.fields.published_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $work)
                        @php $workAuthors = collect($work->authors ?? [])->filter()->implode(', '); @endphp
                        <tr>
                            <td>
                                <a class="group block min-w-64 max-w-md" href="{{ route('librarian.repository.edit', $work) }}">
                                    <strong class="block text-sm text-primary group-hover:text-secondary">{{ $work->title }}</strong>
                                    <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                        @if ($work->udc_code)
                                            <span>{{ __('librarian.repository.fields.udc_code') }}: {{ $work->udc_code }}</span>
                                        @endif
                                        @if ($work->file_name)
                                            <span class="inline-flex items-center gap-1 text-slate-400">
                                                <span class="material-symbols-outlined text-[15px]">picture_as_pdf</span>
                                                {{ $work->file_name }}
                                            </span>
                                        @else
                                            <span class="text-slate-400">{{ __('librarian.repository.no_file') }}</span>
                                        @endif
                                    </span>
                                </a>
                            </td>
                            <td class="max-w-56 text-slate-600">{{ $workAuthors !== '' ? $workAuthors : '—' }}</td>
                            <td class="whitespace-nowrap text-slate-600">{{ $work->work_type ? __('librarian.repository.work_types.'.$work->work_type) : '—' }}</td>
                            <td class="whitespace-nowrap text-slate-600">{{ $work->year ?: '—' }}</td>
                            <td class="text-slate-600">{{ $work->department ?: '—' }}</td>
                            <td>
                                <x-admin.status-badge
                                    :status="$statusTone($work->status)"
                                    :label="__('librarian.repository.statuses.'.$work->status)"
                                />
                            </td>
                            <td class="text-slate-600">{{ $work->uploadedBy?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-slate-600">{{ $work->published_at?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">school</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.repository.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$items" />
    </section>
@endsection
