@extends('layouts.librarian')

@section('title', __('librarian.catalog.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <x-admin.page-header
        :eyebrow="__('librarian.catalog.eyebrow')"
        :title="__('librarian.catalog.title')"
        :subtitle="__('librarian.catalog.subtitle')"
    >
        @canany(['copies.create', 'copies.edit'])
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.index') }}">
                <span class="material-symbols-outlined text-[19px]">inventory_2</span>
                {{ __('librarian.copies.title') }}
            </a>
        @endcanany
        @can('catalog.create_record')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.catalog.create') }}">
                <span class="material-symbols-outlined text-[19px]">library_add</span>
                {{ __('librarian.catalog.create') }}
            </a>
        @endcan
    </x-admin.page-header>

    @if ($draftCount > 0)
        <div role="status" class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="material-symbols-outlined text-[20px]">rule_folder</span>
            <span class="font-semibold">{{ __('librarian.catalog.drafts_pending', ['count' => $draftCount]) }}</span>
            <span class="text-amber-800">{{ __('librarian.overview.metrics.draft_records_hint') }}</span>
            @can('data_cleanup.access')
                <a
                    class="ml-auto inline-flex items-center gap-1 font-bold text-amber-900 underline underline-offset-2 hover:text-amber-950"
                    href="{{ route('librarian.data-cleanup') }}"
                >
                    {{ __('librarian.nav.data_cleanup') }}
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            @endcan
        </div>
    @endif

    <form method="GET" action="{{ route('librarian.catalog.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="sm:col-span-2">
                <span class="admin-label">{{ __('librarian.catalog.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('librarian.catalog.filters.search') }}"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.catalog.filters.resource_type') }}</span>
                <select class="admin-input" name="resource_type">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach (\App\Models\Catalog\BibliographicRecord::RESOURCE_TYPES as $type)
                        <option value="{{ $type }}" @selected(($filters['resource_type'] ?? '') === $type)>
                            {{ __('librarian.catalog.resource_types.'.$type) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.catalog.filters.language') }}</span>
                <select class="admin-input" name="language">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach (\App\Models\Catalog\BibliographicRecord::LANGUAGES as $language)
                        <option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>
                            {{ __('librarian.catalog.languages.'.$language) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.catalog.filters.udc') }}</span>
                <input class="admin-input" type="text" name="udc" value="{{ $filters['udc'] ?? '' }}" inputmode="numeric">
            </label>

            <div class="grid grid-cols-2 gap-3">
                <label>
                    <span class="admin-label">{{ __('librarian.catalog.filters.year_from') }}</span>
                    <input class="admin-input" type="number" name="year_from" min="1500" max="2100" step="1" value="{{ $filters['year_from'] ?? '' }}">
                </label>
                <label>
                    <span class="admin-label">{{ __('librarian.catalog.filters.year_to') }}</span>
                    <input class="admin-input" type="number" name="year_to" min="1500" max="2100" step="1" value="{{ $filters['year_to'] ?? '' }}">
                </label>
            </div>

            <label>
                <span class="admin-label">{{ __('librarian.catalog.filters.review') }}</span>
                <select class="admin-input" name="review">
                    <option value="">{{ __('common.filters.all') }}</option>
                    <option value="manual_review" @selected(($filters['review'] ?? '') === 'manual_review')>
                        {{ __('librarian.data_cleanup.issues.manual_review') }}
                    </option>
                    <option value="language_mismatch" @selected(($filters['review'] ?? '') === 'language_mismatch')>
                        {{ __('librarian.data_cleanup.issues.language_mismatch') }}
                    </option>
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.catalog.filters.state') }}</span>
                <select class="admin-input" name="state">
                    <option value="">{{ __('common.filters.all') }}</option>
                    <option value="complete" @selected(($filters['state'] ?? '') === 'complete')>{{ __('librarian.catalog.filters.state_complete') }}</option>
                    <option value="draft" @selected(($filters['state'] ?? '') === 'draft')>{{ __('librarian.catalog.filters.state_draft') }}</option>
                </select>
            </label>

            <div class="flex items-end gap-2">
                <button class="admin-btn admin-btn-primary flex-1" type="submit">
                    <span class="material-symbols-outlined text-[19px]">filter_alt</span>
                    {{ __('common.actions.apply_filters') }}
                </button>
                <a
                    class="admin-btn admin-btn-secondary px-3"
                    href="{{ route('librarian.catalog.index') }}"
                    title="{{ __('common.actions.clear_filters') }}"
                    aria-label="{{ __('common.actions.clear_filters') }}"
                >
                    <span class="material-symbols-outlined text-[19px]">filter_alt_off</span>
                </a>
            </div>
        </div>
    </form>

    {{-- ДИР 6.3 "массовое редактирование". Selection lives in this form, so
         the checkboxes in the table below submit with it. Only shared,
         non-identifying fields are offered. --}}
    @can('catalog.edit_record')
        <form method="POST" action="{{ route('librarian.catalog.bulk') }}" id="bulk-edit-form" class="mb-4">
            @csrf
            <div
                id="bulk-edit-panel"
                class="hidden rounded-xl border border-secondary bg-secondary-container/30 p-4"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-secondary">checklist</span>
                    <strong class="text-sm text-primary">
                        {{ __('librarian.catalog.bulk.selected') }}
                        <span id="bulk-selected-count">0</span>
                    </strong>
                    <button class="text-xs font-semibold text-secondary underline" type="button" id="bulk-clear">
                        {{ __('librarian.catalog.bulk.clear') }}
                    </button>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.language') }}</span>
                        <select class="admin-input" name="language">
                            <option value="">{{ __('librarian.catalog.bulk.leave_unchanged') }}</option>
                            @foreach (\App\Models\Catalog\BibliographicRecord::LANGUAGES as $language)
                                <option value="{{ $language }}">{{ __('librarian.catalog.languages.'.$language) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.resource_type') }}</span>
                        <select class="admin-input" name="resource_type">
                            <option value="">{{ __('librarian.catalog.bulk.leave_unchanged') }}</option>
                            @foreach (\App\Models\Catalog\BibliographicRecord::RESOURCE_TYPES as $type)
                                <option value="{{ $type }}">{{ __('librarian.catalog.resource_types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.category') }}</span>
                        <input class="admin-input" type="text" name="category" maxlength="128" placeholder="{{ __('librarian.catalog.bulk.leave_unchanged') }}">
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.udc_code') }}</span>
                        <input class="admin-input font-mono" type="text" name="udc_code" maxlength="64" placeholder="{{ __('librarian.catalog.bulk.leave_unchanged') }}">
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.data_cleanup.issues.manual_review') }}</span>
                        <select class="admin-input" name="needs_manual_review">
                            <option value="">{{ __('librarian.catalog.bulk.leave_unchanged') }}</option>
                            <option value="yes">{{ __('common.boolean.yes') }}</option>
                            <option value="no">{{ __('common.boolean.no') }}</option>
                        </select>
                    </label>
                </div>

                <button class="admin-btn admin-btn-primary mt-4" type="submit">
                    <span class="material-symbols-outlined text-[19px]">edit_note</span>
                    {{ __('librarian.catalog.bulk.apply') }}
                </button>
            </div>
        </form>
    @endcan

    <section class="admin-card overflow-hidden p-0">
        <div class="hidden overflow-x-auto md:block">
            <table class="admin-table min-w-[1180px]">
                <thead>
                    <tr>
                        @can('catalog.edit_record')
                            <th class="w-10">
                                <input type="checkbox" id="bulk-select-all" class="rounded border-slate-300" aria-label="{{ __('librarian.catalog.bulk.select_all') }}">
                            </th>
                        @endcan
                        <th>{{ __('librarian.catalog.fields.title') }}</th>
                        <th>{{ __('librarian.catalog.fields.primary_author') }}</th>
                        <th>{{ __('librarian.catalog.fields.publication_year') }}</th>
                        <th>{{ __('librarian.catalog.fields.resource_type') }}</th>
                        <th>{{ __('librarian.catalog.fields.language') }}</th>
                        <th>{{ __('librarian.catalog.fields.udc_code') }}</th>
                        <th>{{ __('librarian.nav.copies') }}</th>
                        <th>{{ __('common.fields.status') }}</th>
                        <th>{{ __('data_quality.title') }}</th>
                        <th>{{ __('common.fields.updated_at') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $extraAuthors = count($record->additional_authors ?? []);
                            $totalCopies = (int) ($record->copies_count ?? 0);
                            $availableCopies = (int) ($record->available_copies_count ?? 0);
                            $recordQuality = $qualityByRecord->get((string) $record->id, collect());
                            $importantQuality = $recordQuality->whereIn('severity', ['critical', 'high']);
                            // Only meaningful while working the language queue.
                            $confidence = ($filters['review'] ?? null) === 'language_mismatch'
                                ? $record->kazakhTitleConfidence()
                                : null;
                            $confidenceTone = [
                                'high' => 'border-emerald-300 bg-emerald-50 text-emerald-800',
                                'medium' => 'border-amber-300 bg-amber-50 text-amber-800',
                                'low' => 'border-red-300 bg-red-50 text-red-800',
                            ];
                        @endphp
                        <tr>
                            @can('catalog.edit_record')
                                <td>
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        form="bulk-edit-form"
                                        name="ids[]"
                                        value="{{ $record->id }}"
                                        data-bulk-checkbox
                                        aria-label="{{ $record->title }}"
                                    >
                                </td>
                            @endcan
                            <td>
                                <div class="min-w-64 max-w-md">
                                    @if ($confidence)
                                        <span
                                            class="mb-1 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-bold {{ $confidenceTone[$confidence['tier']] }}"
                                            title="{{ __('librarian.catalog.confidence.explain', ['kazakh' => $confidence['kazakh'], 'total' => $confidence['total']]) }}"
                                        >
                                            {{ __('librarian.catalog.confidence.'.$confidence['tier']) }}
                                            <span class="opacity-70">{{ $confidence['kazakh'] }}/{{ $confidence['total'] }}</span>
                                        </span>
                                    @endif
                                    @can('catalog.edit_record')
                                        <a class="block text-sm font-semibold text-primary hover:text-secondary" href="{{ route('librarian.catalog.edit', $record) }}">
                                            {{ $record->title }}
                                        </a>
                                    @else
                                        <span class="block text-sm font-semibold text-primary">{{ $record->title }}</span>
                                    @endcan
                                    @if ($record->subtitle)
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $record->subtitle }}</span>
                                    @endif
                                    @if ($record->isbn)
                                        <span class="mt-1 block font-mono text-[11px] text-slate-400">ISBN {{ $record->isbn }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="block text-slate-700">{{ $record->primary_author ?: '—' }}</span>
                                @if ($extraAuthors > 0)
                                    <span class="mt-0.5 block text-xs text-slate-400">+{{ $extraAuthors }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ $record->publication_year ?: '—' }}</td>
                            <td>
                                <x-admin.status-badge
                                    status="type"
                                    :label="__('librarian.catalog.resource_types.'.$record->resource_type)"
                                />
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ __('librarian.catalog.languages.'.$record->language) }}</td>
                            <td class="whitespace-nowrap font-mono text-xs text-slate-600">{{ $record->udc_code ?: '—' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="font-semibold {{ $availableCopies > 0 ? 'text-emerald-700' : 'text-slate-400' }}">{{ $availableCopies }}</span>
                                <span class="text-slate-400">/ {{ $totalCopies }}</span>
                            </td>
                            <td>
                                @if ($record->is_draft)
                                    <x-admin.status-badge status="pending" :label="__('librarian.catalog.draft_badge')" />
                                @else
                                    <x-admin.status-badge status="active" :label="__('librarian.catalog.complete_badge')" />
                                @endif
                            </td>
                            <td>
                                @if($recordQuality->isEmpty())
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><span class="material-symbols-outlined text-[16px]">check_circle</span>{{ __('data_quality.record.clean') }}</span>
                                @else
                                    <a class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800" href="{{ route('librarian.data-quality.index', ['q' => $record->title]) }}"><span class="material-symbols-outlined text-[16px]">warning</span>{{ $recordQuality->count() }}@if($importantQuality->isNotEmpty()) · {{ $importantQuality->count() }} {{ __('data_quality.stats.high_objects') }}@endif</a>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ $record->updated_at?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    @can('catalog.edit_record')
                                        <a
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                            href="{{ route('librarian.catalog.edit', $record) }}"
                                            title="{{ __('common.actions.edit') }}"
                                            aria-label="{{ __('common.actions.edit') }}"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </a>
                                    @endcan
                                    @can('copies.create')
                                        <a
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                            href="{{ route('librarian.copies.create', ['record' => $record->id]) }}"
                                            title="{{ __('librarian.catalog.add_copy') }}"
                                            aria-label="{{ __('librarian.catalog.add_copy') }}"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">add_box</span>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->can('catalog.edit_record') ? 12 : 11 }}" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">menu_book</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.catalog.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-200 md:hidden">
            @forelse ($records as $record)
                @php
                    $totalCopies = (int) ($record->copies_count ?? 0);
                    $availableCopies = (int) ($record->available_copies_count ?? 0);
                    $recordQuality = $qualityByRecord->get((string) $record->id, collect());
                    $importantQuality = $recordQuality->whereIn('severity', ['critical', 'high']);
                @endphp
                <article class="space-y-3 p-4" data-testid="catalog-record-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            @can('catalog.edit_record')
                                <a class="block break-words text-sm font-semibold text-primary hover:text-secondary" href="{{ route('librarian.catalog.edit', $record) }}">{{ $record->title }}</a>
                            @else
                                <strong class="block break-words text-sm text-primary">{{ $record->title }}</strong>
                            @endcan
                            <span class="mt-1 block break-words text-xs text-slate-500">{{ $record->primary_author ?: '—' }}</span>
                            @if ($record->isbn)<span class="mt-1 block break-all font-mono text-[11px] text-slate-400">ISBN {{ $record->isbn }}</span>@endif
                        </div>
                        @can('catalog.edit_record')
                            <input type="checkbox" class="mt-1 shrink-0 rounded border-slate-300" form="bulk-edit-form" name="ids[]" value="{{ $record->id }}" data-bulk-checkbox aria-label="{{ $record->title }}">
                        @endcan
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-xs">
                        <div><dt class="text-slate-500">{{ __('librarian.catalog.fields.publication_year') }}</dt><dd class="mt-1 text-slate-700">{{ $record->publication_year ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('librarian.nav.copies') }}</dt><dd class="mt-1"><span class="font-semibold {{ $availableCopies > 0 ? 'text-emerald-700' : 'text-slate-500' }}">{{ $availableCopies }}</span><span class="text-slate-400"> / {{ $totalCopies }}</span></dd></div>
                        <div><dt class="text-slate-500">{{ __('librarian.catalog.fields.language') }}</dt><dd class="mt-1 text-slate-700">{{ __('librarian.catalog.languages.'.$record->language) }}</dd></div>
                        <div><dt class="text-slate-500">{{ __('librarian.catalog.fields.udc_code') }}</dt><dd class="mt-1 break-all font-mono text-slate-700">{{ $record->udc_code ?: '—' }}</dd></div>
                    </dl>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($record->is_draft)
                            <x-admin.status-badge status="pending" :label="__('librarian.catalog.draft_badge')" />
                        @else
                            <x-admin.status-badge status="active" :label="__('librarian.catalog.complete_badge')" />
                        @endif
                        @if($recordQuality->isEmpty())
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700"><span class="material-symbols-outlined text-[16px]">check_circle</span>{{ __('data_quality.record.clean') }}</span>
                        @else
                            <a class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800" href="{{ route('librarian.data-quality.index', ['q' => $record->title]) }}"><span class="material-symbols-outlined text-[16px]">warning</span>{{ $recordQuality->count() }}@if($importantQuality->isNotEmpty()) · {{ $importantQuality->count() }} {{ __('data_quality.stats.high_objects') }}@endif</a>
                        @endif
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                        @can('catalog.edit_record')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.catalog.edit', $record) }}">{{ __('common.actions.edit') }}</a>@endcan
                        @can('copies.create')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.create', ['record' => $record->id]) }}">{{ __('librarian.catalog.add_copy') }}</a>@endcan
                    </div>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-slate-500">{{ __('librarian.catalog.empty') }}</p>
            @endforelse
        </div>

        <x-admin.pagination :paginator="$records" />
    </section>
@endsection

@push('scripts')
<script>
    // Selection state for the bulk editor. The checkboxes live in the table but
    // belong to #bulk-edit-form via form=, so the panel only has to track count
    // and visibility.
    (function () {
        var panel = document.getElementById('bulk-edit-panel');
        var counter = document.getElementById('bulk-selected-count');
        var selectAll = document.getElementById('bulk-select-all');
        var clear = document.getElementById('bulk-clear');

        if (!panel || !counter) {
            return;
        }

        function boxes() {
            return Array.prototype.slice.call(document.querySelectorAll('[data-bulk-checkbox]'));
        }

        function sync() {
            var checked = boxes().filter(function (b) { return b.checked; });
            counter.textContent = String(checked.length);
            panel.classList.toggle('hidden', checked.length === 0);

            if (selectAll) {
                selectAll.checked = checked.length > 0 && checked.length === boxes().length;
                selectAll.indeterminate = checked.length > 0 && checked.length < boxes().length;
            }
        }

        boxes().forEach(function (b) { b.addEventListener('change', sync); });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                boxes().forEach(function (b) { b.checked = selectAll.checked; });
                sync();
            });
        }

        if (clear) {
            clear.addEventListener('click', function () {
                boxes().forEach(function (b) { b.checked = false; });
                sync();
            });
        }

        sync();
    })();
</script>
@endpush
