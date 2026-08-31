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
        @can('copies.write_off')
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.write-off') }}"><span class="material-symbols-outlined text-[19px]">delete_sweep</span>{{ __('copy_writeoff.title') }}</a>
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

            <details class="sm:col-span-2 xl:col-span-6 rounded-xl border border-slate-200 bg-slate-50 p-4" @if(collect($filters)->except(['search','branch_id','fund_id','status','condition','barcode_status','storage_sigla','shelf_location'])->filter(fn($value) => $value !== null && $value !== '')->isNotEmpty()) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-primary">{{ __('copy_registry.advanced_filters') }}</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <label><span class="admin-label">{{ __('librarian.copies.fields.inventory_number') }}</span><input class="admin-input" name="inventory_number" value="{{ $filters['inventory_number'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('librarian.copies.fields.barcode') }}</span><input class="admin-input" name="barcode" value="{{ $filters['barcode'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.title') }}</span><input class="admin-input" name="title" value="{{ $filters['title'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.author') }}</span><input class="admin-input" name="author" value="{{ $filters['author'] ?? '' }}"></label>
                    <label><span class="admin-label">ISBN</span><input class="admin-input" name="isbn" value="{{ $filters['isbn'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('librarian.copies.fields.ksu_number') }}</span><input class="admin-input" name="ksu_number" value="{{ $filters['ksu_number'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.ksu_presence') }}</span><select class="admin-input" name="ksu_status"><option value="">{{ __('common.filters.all') }}</option><option value="with" @selected(($filters['ksu_status'] ?? '') === 'with')>{{ __('copy_registry.with_ksu') }}</option><option value="without" @selected(($filters['ksu_status'] ?? '') === 'without')>{{ __('copy_registry.without_ksu') }}</option></select></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.service_point') }}</span><input class="admin-input" name="service_point_code" list="copy-service-points" value="{{ $filters['service_point_code'] ?? '' }}"><datalist id="copy-service-points">@foreach($servicePoints as $point)<option value="{{ $point }}">@endforeach</datalist></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.registration_from') }}</span><input class="admin-input" type="date" name="registration_from" value="{{ $filters['registration_from'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.registration_to') }}</span><input class="admin-input" type="date" name="registration_to" value="{{ $filters['registration_to'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.price_min') }}</span><input class="admin-input" type="number" min="0" step="0.01" name="price_min" value="{{ $filters['price_min'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.price_max') }}</span><input class="admin-input" type="number" min="0" step="0.01" name="price_max" value="{{ $filters['price_max'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('librarian.copies.fields.acquisition_source') }}</span><input class="admin-input" name="acquisition_source" list="copy-sources" value="{{ $filters['acquisition_source'] ?? '' }}"><datalist id="copy-sources">@foreach($sourceOptions as $source)<option value="{{ $source }}">@endforeach</datalist></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.received_from') }}</span><input class="admin-input" name="received_from" value="{{ $filters['received_from'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.invoice') }}</span><input class="admin-input" name="invoice" value="{{ $filters['invoice'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.shelf_index') }}</span><input class="admin-input" name="shelf_index" value="{{ $filters['shelf_index'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.keywords') }}</span><input class="admin-input" name="keywords" value="{{ $filters['keywords'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.publication_place') }}</span><input class="admin-input" name="publication_place" value="{{ $filters['publication_place'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.publisher') }}</span><input class="admin-input" name="publisher" value="{{ $filters['publisher'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.publication_year') }}</span><input class="admin-input" name="publication_year" value="{{ $filters['publication_year'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.series') }}</span><input class="admin-input" name="series" value="{{ $filters['series'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.language') }}</span><input class="admin-input" name="language" value="{{ $filters['language'] ?? '' }}" placeholder="kk · ru · en"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.faculty') }}</span><input class="admin-input" name="faculty" value="{{ $filters['faculty'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.department') }}</span><input class="admin-input" name="department" value="{{ $filters['department'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.discipline') }}</span><input class="admin-input" name="discipline" value="{{ $filters['discipline'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.specialty') }}</span><input class="admin-input" name="specialty" value="{{ $filters['specialty'] ?? '' }}"></label>
                    <label><span class="admin-label">{{ __('librarian.copies.fields.accounting_type') }}</span><select class="admin-input" name="accounting_type"><option value="">{{ __('common.filters.all') }}</option>@foreach($accountingTypes as $accountingType)<option value="{{ $accountingType }}" @selected(($filters['accounting_type'] ?? '') === $accountingType)>{{ __('copy_registry.accounting_types.'.$accountingType) }}</option>@endforeach</select></label>
                    <label><span class="admin-label">{{ __('copy_registry.fields.written_off') }}</span><select class="admin-input" name="written_off"><option value="">{{ __('common.filters.all') }}</option><option value="yes" @selected(($filters['written_off'] ?? '') === 'yes')>{{ __('common.boolean.yes') }}</option><option value="no" @selected(($filters['written_off'] ?? '') === 'no')>{{ __('common.boolean.no') }}</option></select></label>
                    <label><span class="admin-label">{{ __('copy_lifecycle.inventory') }}</span><select class="admin-input" name="inventory_status"><option value="">{{ __('common.filters.all') }}</option>@foreach(\App\Models\Catalog\BookCopy::INVENTORY_STATUSES as $lifecycle)<option value="{{ $lifecycle }}" @selected(($filters['inventory_status'] ?? '') === $lifecycle)>{{ __('copy_lifecycle.inventory_statuses.'.$lifecycle) }}</option>@endforeach</select></label>
                    <label><span class="admin-label">{{ __('copy_lifecycle.circulation') }}</span><select class="admin-input" name="circulation_status"><option value="">{{ __('common.filters.all') }}</option>@foreach(\App\Models\Catalog\BookCopy::CIRCULATION_STATUSES as $availability)<option value="{{ $availability }}" @selected(($filters['circulation_status'] ?? '') === $availability)>{{ __('copy_lifecycle.circulation_statuses.'.$availability) }}</option>@endforeach</select></label>
                </div>
            </details>

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
        <div class="hidden overflow-x-auto lg:block">
            <table class="admin-table min-w-[1380px]">
                <thead>
                    <tr>
                        @can('barcodes.print_batch')<th class="w-10"><span class="sr-only">{{ __('librarian.copies.marking.selected_label') }}</span></th>@endcan
                        <th>{{ __('librarian.copies.fields.inventory_number') }} / {{ __('librarian.copies.fields.barcode') }}</th>
                        <th>{{ __('librarian.copies.fields.record') }}</th>
                        <th>{{ __('copy_registry.accounting') }}</th>
                        <th>{{ __('data_quality.object.location') }}</th>
                        <th>{{ __('librarian.copies.fields.status') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
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
                            <td class="min-w-56 text-xs text-slate-600">
                                <div><span class="font-semibold text-primary">{{ $copy->accounting_type && trans()->has('copy_registry.accounting_types.'.$copy->accounting_type) ? __('copy_registry.accounting_types.'.$copy->accounting_type) : ($copy->accounting_type ?: '—') }}</span> @if($copy->ksu_number)· {{ __('librarian.copies.fields.ksu_number') }} {{ $copy->ksu_number }}@endif</div>
                                <div class="mt-1">{{ $copy->price !== null ? number_format((float)$copy->price, 2, ',', ' ').' ₸' : '—' }} · {{ $copy->acquisition_source ?: '—' }}</div>
                                <div class="mt-1">{{ $copy->registration_date?->format('d.m.Y') ?? $copy->acquisition_date?->format('d.m.Y') ?? '—' }}</div>
                            </td>
                            <td>
                                <strong class="block truncate text-sm text-primary" title="{{ $copy->branch?->name }}">{{ $copy->branch?->name ?? '—' }}</strong>
                                <span class="mt-1 block truncate text-xs text-slate-500">{{ $copy->fund?->name ?? '—' }} · {{ $copy->storage_sigla ?: $copy->sigla_code ?: '—' }}</span>
                                <span class="mt-1 block truncate text-xs text-slate-500">{{ $copy->service_point_code ?: '—' }} · {{ $copy->shelf_index ?: $copy->shelf_location ?: '—' }}</span>
                            </td>
                            <td>
                                <x-admin.status-badge
                                    :status="$statusTone($copy->status)"
                                    :label="__('librarian.copies.statuses.'.$copy->status)"
                                />
                                <div class="mt-2 text-[11px] font-semibold text-slate-500">{{ __('copy_lifecycle.inventory_statuses.'.($copy->inventory_status ?: \App\Models\Catalog\BookCopy::separatedStateFor($copy->status)[0])) }} · {{ __('copy_lifecycle.circulation_statuses.'.($copy->circulation_status ?: \App\Models\Catalog\BookCopy::separatedStateFor($copy->status)[1])) }}</div>
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
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="admin-label">{{ __('data_quality.object.location') }}</dt><dd class="font-semibold">{{ $copy->branch?->name ?? '—' }}</dd><dd class="text-xs text-slate-500">{{ $copy->storage_sigla ?: '—' }} · {{ $copy->service_point_code ?: '—' }} · {{ $copy->shelf_index ?: $copy->shelf_location ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('librarian.copies.fields.barcode') }}</dt><dd class="font-mono">{{ $copy->barcode ?: '—' }}</dd></div><div><dt class="admin-label">{{ __('copy_registry.accounting') }}</dt><dd>{{ $copy->accounting_type ?: '—' }} · {{ $copy->ksu_number ?: '—' }}</dd><dd class="text-xs text-slate-500">{{ $copy->price !== null ? number_format((float)$copy->price, 2, ',', ' ').' ₸' : '—' }} · {{ $copy->acquisition_source ?: '—' }}</dd></div></dl>
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
