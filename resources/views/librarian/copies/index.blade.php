@extends('layouts.librarian')

@section('title', __('librarian.copies.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $copyStatuses = \App\Models\Catalog\BookCopy::STATUSES;
        $copyConditions = \App\Models\Catalog\BookCopy::CONDITIONS;

        $activeFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->all();

        $statusLink = static function (?string $status) use ($activeFilters): string {
            $params = $activeFilters;
            unset($params['status']);
            if ($status !== null) {
                $params['status'] = $status;
            }

            return route('librarian.copies.index', $params);
        };

        $statusTone = static fn (string $status): string => match ($status) {
            'available' => 'active',
            'reserved', 'in_processing' => 'pending',
            'under_repair' => 'scheduled',
            'overdue' => 'expired',
            'lost' => 'critical',
            'written_off', 'reserved_stock' => 'inactive',
            default => 'issued',
        };

        $conditionTone = static fn (string $condition): string => match ($condition) {
            'new' => 'active',
            'worn' => 'pending',
            'damaged' => 'critical',
            default => 'good',
        };

        $currentStatus = $filters['status'] ?? null;
        $totalCopies = (int) $statusCounts->sum();
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.copies.eyebrow')"
        :title="__('librarian.copies.title')"
        :subtitle="__('librarian.copies.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.catalog.index') }}">
            <span class="material-symbols-outlined text-[19px]">menu_book</span>
            {{ __('librarian.nav.catalog') }}
        </a>
        @can('copies.create')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.copies.create') }}">
                <span class="material-symbols-outlined text-[19px]">add_box</span>
                {{ __('librarian.copies.create') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mb-6 flex flex-wrap gap-2">
        <a
            class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-semibold transition-colors {{ $currentStatus === null ? 'border-primary-container bg-primary-container text-on-primary' : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary' }}"
            href="{{ $statusLink(null) }}"
        >
            <span>{{ __('common.filters.all') }}</span>
            <span class="rounded-full bg-black/10 px-1.5 py-0.5 tabular-nums">{{ number_format($totalCopies, 0, ',', ' ') }}</span>
        </a>
        @foreach ($copyStatuses as $status)
            @php $statusTotal = (int) $statusCounts->get($status, 0); @endphp
            <a
                class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-semibold transition-colors {{ $currentStatus === $status ? 'border-primary-container bg-primary-container text-on-primary' : 'border-slate-200 bg-white text-slate-600 hover:border-secondary hover:text-secondary' }} {{ $statusTotal === 0 && $currentStatus !== $status ? 'opacity-60' : '' }}"
                href="{{ $statusLink($status) }}"
            >
                <span>{{ __('librarian.copies.statuses.'.$status) }}</span>
                <span class="rounded-full bg-black/10 px-1.5 py-0.5 tabular-nums">{{ number_format($statusTotal, 0, ',', ' ') }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('librarian.copies.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <label class="sm:col-span-2">
                <span class="admin-label">{{ __('common.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('librarian.copies.filters.search') }}"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.copies.filters.branch') }}</span>
                <select class="admin-input" name="branch_id">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.copies.filters.fund') }}</span>
                <select class="admin-input" name="fund_id">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach ($funds as $fund)
                        <option value="{{ $fund->id }}" @selected((int) ($filters['fund_id'] ?? 0) === $fund->id)>{{ $fund->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.copies.filters.status') }}</span>
                <select class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach ($copyStatuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('librarian.copies.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.copies.filters.condition') }}</span>
                <select class="admin-input" name="condition">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach ($copyConditions as $condition)
                        <option value="{{ $condition }}" @selected(($filters['condition'] ?? '') === $condition)>{{ __('librarian.copies.conditions.'.$condition) }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-6">
                <button class="admin-btn admin-btn-primary" type="submit">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('librarian.copies.index') }}"
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
            <table class="admin-table min-w-[1200px]">
                <thead>
                    <tr>
                        <th>{{ __('librarian.copies.fields.inventory_number') }}</th>
                        <th>{{ __('librarian.copies.fields.barcode') }}</th>
                        <th>{{ __('librarian.copies.fields.record') }}</th>
                        <th>{{ __('librarian.copies.fields.branch') }}</th>
                        <th>{{ __('librarian.copies.fields.fund') }}</th>
                        <th>{{ __('librarian.copies.fields.shelf_location') }}</th>
                        <th>{{ __('librarian.copies.fields.condition') }}</th>
                        <th>{{ __('librarian.copies.fields.status') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($copies as $copy)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a class="font-mono text-sm font-bold text-primary-container hover:text-secondary hover:underline" href="{{ route('librarian.copies.show', $copy) }}">
                                    {{ $copy->inventory_number }}
                                </a>
                                @if ($copy->ksu_number)
                                    <span class="mt-0.5 block text-xs text-slate-400">{{ __('librarian.copies.fields.ksu_number') }}: {{ $copy->ksu_number }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap font-mono text-xs text-slate-600">{{ $copy->barcode ?: '—' }}</td>
                            <td>
                                <span class="block max-w-md truncate font-semibold text-primary" title="{{ $copy->bibliographicRecord?->title }}">
                                    {{ $copy->bibliographicRecord?->title ?? '—' }}
                                </span>
                                <span class="mt-0.5 block max-w-md truncate text-xs text-slate-500">
                                    {{ $copy->bibliographicRecord?->primary_author ?: '—' }}@if ($copy->bibliographicRecord?->publication_year), {{ $copy->bibliographicRecord->publication_year }}@endif
                                </span>
                            </td>
                            <td class="text-slate-600">{{ $copy->branch?->name ?? '—' }}</td>
                            <td class="text-slate-600">{{ $copy->fund?->name ?? '—' }}</td>
                            <td class="text-slate-600">{{ $copy->shelf_location ?: '—' }}</td>
                            <td>
                                <x-admin.status-badge
                                    :status="$conditionTone($copy->condition)"
                                    :label="__('librarian.copies.conditions.'.$copy->condition)"
                                />
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$statusTone($copy->status)"
                                    :label="__('librarian.copies.statuses.'.$copy->status)"
                                />
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    <a
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                        href="{{ route('librarian.copies.show', $copy) }}"
                                        title="{{ __('common.actions.view_details') }}"
                                        aria-label="{{ __('common.actions.view_details') }}"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    <a
                                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                        href="{{ route('librarian.copies.label', $copy) }}"
                                        target="_blank"
                                        rel="noopener"
                                        title="{{ __('librarian.copies.print_label') }}"
                                        aria-label="{{ __('librarian.copies.print_label') }}"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">qr_code_2</span>
                                    </a>
                                    @can('copies.edit')
                                        <a
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                            href="{{ route('librarian.copies.edit', $copy) }}"
                                            title="{{ __('common.actions.edit') }}"
                                            aria-label="{{ __('common.actions.edit') }}"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-5xl text-slate-300">inventory_2</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.copies.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$copies" />
    </section>
@endsection
