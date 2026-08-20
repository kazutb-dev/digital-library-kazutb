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

    @php $markingPercent = $markingStats['total'] > 0 ? round(($markingStats['with'] / $markingStats['total']) * 100, 1) : 0; @endphp
    <section class="admin-card mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="admin-label">{{ __('librarian.copies.marking.progress_title') }}</p>
                <p class="mt-1 text-2xl font-bold text-primary">{{ number_format($markingStats['with'], 0, ',', ' ') }} / {{ number_format($markingStats['total'], 0, ',', ' ') }} <span class="text-base font-semibold text-slate-500">({{ $markingPercent }}%)</span></p>
            </div>
            <div class="flex flex-wrap gap-2 text-sm">
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.index', ['barcode_status' => 'with']) }}">{{ __('librarian.copies.marking.with_barcode') }}: {{ number_format($markingStats['with'], 0, ',', ' ') }}</a>
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.index', ['barcode_status' => 'without']) }}">{{ __('librarian.copies.marking.without_barcode') }}: {{ number_format($markingStats['without'], 0, ',', ' ') }}</a>
            </div>
        </div>
    </section>

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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <label class="min-w-0 sm:col-span-2">
                <span class="admin-label">{{ __('common.filters.search') }}</span>
                <span class="relative block min-w-0">
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

            <label>
                <span class="admin-label">{{ __('librarian.copies.filters.barcode_status') }}</span>
                <select class="admin-input" name="barcode_status">
                    <option value="">{{ __('common.filters.all') }}</option>
                    <option value="with" @selected(($filters['barcode_status'] ?? '') === 'with')>{{ __('librarian.copies.marking.with_barcode') }}</option>
                    <option value="without" @selected(($filters['barcode_status'] ?? '') === 'without')>{{ __('librarian.copies.marking.without_barcode') }}</option>
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.copies.fields.storage_sigla') }}</span>
                <input class="admin-input" name="storage_sigla" value="{{ $filters['storage_sigla'] ?? '' }}">
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.copies.fields.shelf_location') }}</span>
                <input class="admin-input" name="shelf_location" value="{{ $filters['shelf_location'] ?? '' }}">
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

    @can('barcodes.print_batch')
        <form id="barcode-batch-form" method="POST" action="{{ route('librarian.copies.barcode-batches.preview') }}" class="admin-card mb-4 flex flex-wrap items-center justify-between gap-3">
            @csrf
            <div><strong>{{ __('librarian.copies.marking.batch_title') }}</strong><p class="mt-1 text-xs text-slate-500">{{ __('librarian.copies.marking.batch_hint') }}</p></div>
            <div class="flex items-center gap-2"><span id="barcode-selected-count" class="text-sm text-slate-500">{{ __('librarian.copies.marking.selected', ['count' => 0]) }}</span><button id="barcode-batch-submit" class="admin-btn admin-btn-primary" type="submit" disabled>{{ __('librarian.copies.marking.prepare') }}</button></div>
        </form>
    @endcan

    <section class="admin-card overflow-hidden p-0">
        <div class="hidden lg:block">
            <table class="admin-table table-fixed">
                <thead>
                    <tr>
                        @can('barcodes.print_batch')<th class="w-10"><span class="sr-only">{{ __('librarian.copies.marking.selected_label') }}</span></th>@endcan
                        <th class="w-[17%]">{{ __('librarian.copies.fields.inventory_number') }}</th>
                        <th class="w-[29%]">{{ __('librarian.copies.fields.record') }}</th>
                        <th class="w-[27%]">{{ __('data_quality.object.location') }}</th>
                        <th class="w-[17%]">{{ __('librarian.copies.fields.status') }}</th>
                        <th class="w-[10%] text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($copies as $copy)
                        <tr>
                            @can('barcodes.print_batch')<td><input class="barcode-copy-selector h-4 w-4" form="barcode-batch-form" type="checkbox" name="copy_ids[]" value="{{ $copy->id }}" @disabled(filled($copy->barcode) || in_array($copy->status, ['lost', 'written_off'], true)) aria-label="{{ __('librarian.copies.marking.select_copy', ['inventory' => $copy->inventory_number]) }}"></td>@endcan
                            <td class="whitespace-nowrap">
                                <a class="font-mono text-sm font-bold text-primary-container hover:text-secondary hover:underline" href="{{ route('librarian.copies.show', $copy) }}">
                                    {{ $copy->inventory_number }}
                                </a>
                                @if ($copy->ksu_number)
                                    <span class="mt-0.5 block text-xs text-slate-400">{{ __('librarian.copies.fields.ksu_number') }}: {{ $copy->ksu_number }}</span>
                                @endif
                                <span class="mt-1 block truncate font-mono text-[11px] text-slate-500" title="{{ $copy->barcode }}">{{ $copy->barcode ?: '—' }}</span>
                            </td>
                            <td>
                                <span class="block truncate font-semibold text-primary" title="{{ $copy->bibliographicRecord?->title }}">
                                    {{ $copy->bibliographicRecord?->title ?? '—' }}
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">
                                    {{ $copy->bibliographicRecord?->primary_author ?: '—' }}@if ($copy->bibliographicRecord?->publication_year), {{ $copy->bibliographicRecord->publication_year }}@endif
                                </span>
                            </td>
                            <td>
                                <strong class="block truncate text-sm text-primary" title="{{ $copy->branch?->name }}">{{ $copy->branch?->name ?? '—' }}</strong>
                                <span class="mt-1 block truncate text-xs text-slate-500">{{ $copy->fund?->name ?? '—' }} · {{ $copy->shelf_location ?: '—' }}</span>
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$statusTone($copy->status)"
                                    :label="__('librarian.copies.statuses.'.$copy->status)"
                                />
                                <div class="mt-2"><x-admin.status-badge :status="$conditionTone($copy->condition)" :label="__('librarian.copies.conditions.'.$copy->condition)" /></div>
                                @if(($qualityByCopy->get((string)$copy->id)?->count() ?? 0) > 0)<a class="mt-2 flex items-center gap-1 text-xs font-semibold text-amber-700" href="{{ route('librarian.data-quality.index', ['q' => $copy->inventory_number]) }}"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">warning</span>{{ $qualityByCopy->get((string)$copy->id)->count() }}</a>@endif
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
                            <td colspan="6" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-5xl text-slate-300">inventory_2</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.copies.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y lg:hidden">
            @forelse($copies as $copy)
                <article class="p-4">
                    <div class="flex items-start justify-between gap-3"><div class="flex min-w-0 gap-3">@can('barcodes.print_batch')<input class="barcode-copy-selector mt-1 h-4 w-4 shrink-0" form="barcode-batch-form" type="checkbox" name="copy_ids[]" value="{{ $copy->id }}" @disabled(filled($copy->barcode) || in_array($copy->status, ['lost', 'written_off'], true)) aria-label="{{ __('librarian.copies.marking.select_copy', ['inventory' => $copy->inventory_number]) }}">@endcan<div class="min-w-0"><a class="font-mono text-sm font-bold text-primary-container" href="{{ route('librarian.copies.show', $copy) }}">{{ $copy->inventory_number }}</a><h2 class="mt-1 truncate font-semibold text-primary">{{ $copy->bibliographicRecord?->title ?? '—' }}</h2><p class="text-xs text-slate-500">{{ $copy->bibliographicRecord?->primary_author ?: '—' }}</p></div></div><x-admin.status-badge :status="$statusTone($copy->status)" :label="__('librarian.copies.statuses.'.$copy->status)" /></div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="admin-label">{{ __('data_quality.object.location') }}</dt><dd class="font-semibold">{{ $copy->branch?->name ?? '—' }}</dd><dd class="text-xs text-slate-500">{{ $copy->fund?->name ?? '—' }} · {{ $copy->shelf_location ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('librarian.copies.fields.barcode') }}</dt><dd class="font-mono">{{ $copy->barcode ?: '—' }}</dd></div></dl>
                    <div class="mt-4 flex items-center justify-between"><x-admin.status-badge :status="$conditionTone($copy->condition)" :label="__('librarian.copies.conditions.'.$copy->condition)" /><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.show', $copy) }}">{{ __('common.actions.open') }}</a></div>
                </article>
            @empty<div class="p-8 text-center text-sm text-slate-500">{{ __('librarian.copies.empty') }}</div>@endforelse
        </div>

        <x-admin.pagination :paginator="$copies" />
    </section>

    @can('barcodes.print_batch')
        @push('scripts')
            <script>
                (() => {
                    const selectors = [...document.querySelectorAll('.barcode-copy-selector')];
                    const count = document.getElementById('barcode-selected-count');
                    const submit = document.getElementById('barcode-batch-submit');
                    const update = () => {
                        const selected = selectors.filter((item) => item.checked).length;
                        count.textContent = @json(__('librarian.copies.marking.selected_template')).replace(':count', selected);
                        submit.disabled = selected === 0 || selected > 100;
                    };
                    selectors.forEach((item) => item.addEventListener('change', update));
                    update();
                })();
            </script>
        @endpush
    @endcan
@endsection
