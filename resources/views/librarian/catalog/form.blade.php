@extends('layouts.librarian')

@section('title', ($record->exists ? __('librarian.catalog.edit') : __('librarian.catalog.new_record')).' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $duplicate = session('duplicate_warning');
        $duplicate = is_array($duplicate) ? $duplicate : null;
        $missingFields = $record->exists ? $record->missingRequiredFields() : [];
        $copies = $record->exists ? $record->copies : collect();
        $materials = $record->exists ? $record->electronicMaterials : collect();
        $relations = $record->exists ? $record->relatedRecords : collect();
        $sizeLabel = static function (?int $bytes): string {
            if (! $bytes) {
                return '—';
            }

            return $bytes >= 1048576
                ? number_format($bytes / 1048576, 1).' MB'
                : number_format(max(1, (int) round($bytes / 1024))).' KB';
        };
        $branchNames = $branches->pluck('name', 'id');
        $fundNames = $funds->pluck('name', 'id');
        $copyStatusTone = static fn (?string $status): string => match ($status) {
            'available' => 'active',
            'reserved', 'issued', 'in_processing', 'on_display', 'reserved_stock' => 'pending',
            'overdue', 'lost' => 'failed',
            'written_off', 'under_repair' => 'inactive',
            default => 'unknown',
        };
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.catalog.eyebrow')"
        :title="$record->exists ? __('librarian.catalog.edit') : __('librarian.catalog.new_record')"
        :subtitle="$record->exists ? $record->title : __('librarian.catalog.subtitle')"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.catalog.index') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
        @if ($record->exists)
            @can('copies.create')
                <a class="admin-btn admin-btn-primary" href="{{ route('librarian.copies.create', ['record' => $record->id]) }}">
                    <span class="material-symbols-outlined text-[19px]">add_box</span>
                    {{ __('librarian.catalog.add_copy') }}
                </a>
            @endcan
        @endif
    </x-admin.page-header>

    @if ($duplicate)
        <div role="alert" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <div class="flex items-center gap-2 font-bold">
                <span class="material-symbols-outlined text-[20px]">content_copy</span>
                <span>{{ __('librarian.catalog.duplicate_warning_title') }}</span>
            </div>
            <p class="mt-2 leading-6">
                {{ __('librarian.catalog.duplicate_warning_body', [
                    'title' => ($duplicate['title'] ?? null) ?: '—',
                    'author' => ($duplicate['author'] ?? null) ?: '—',
                    'year' => ($duplicate['year'] ?? null) ?: '—',
                ]) }}
            </p>
            @can('catalog.edit_record')
                @if (! empty($duplicate['id']))
                    <a
                        class="mt-3 inline-flex items-center gap-1 font-bold text-amber-900 underline underline-offset-2 hover:text-amber-950"
                        href="{{ route('librarian.catalog.edit', $duplicate['id']) }}"
                    >
                        <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                        {{ __('librarian.catalog.duplicate_open') }}
                    </a>
                @endif
            @endcan
        </div>
    @endif

    @if ($record->exists && $record->is_draft)
        <div role="status" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <div class="flex items-center gap-2 font-bold">
                <span class="material-symbols-outlined text-[20px]">edit_note</span>
                <span>{{ __('librarian.catalog.draft_badge') }}</span>
            </div>
            <p class="mt-2 leading-6">
                {{ __('librarian.catalog.draft_notice', [
                    'fields' => implode(', ', array_map(
                        static fn (string $field): string => __('librarian.catalog.fields.'.$field),
                        $missingFields,
                    )),
                ]) }}
            </p>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $record->exists ? route('librarian.catalog.update', $record) : route('librarian.catalog.store') }}"
        class="grid gap-6 lg:grid-cols-3 lg:items-start"
    >
        @csrf
        @if ($record->exists)
            @method('PATCH')
        @endif
        @if ($duplicate)
            <input type="hidden" name="confirmed_duplicate" value="1">
        @endif

        <div class="space-y-6 lg:col-span-2">
            <section class="admin-card">
                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('librarian.catalog.eyebrow') }}</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block">
                            <span class="admin-label">{{ __('librarian.catalog.fields.title') }} *</span>
                            <input class="admin-input" type="text" name="title" id="record-title-input" required maxlength="1000" value="{{ old('title', $record->title) }}">
                            @error('title')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </label>

                        {{-- ДИР §6.2: check before submitting, not after a
                             rejected save. Hits the same rule store() uses. --}}
                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <button class="admin-btn admin-btn-secondary" type="button" id="duplicate-check-btn">
                                <span class="material-symbols-outlined text-[19px]">plagiarism</span>
                                {{ __('librarian.catalog.duplicate_check.button') }}
                            </button>
                            <span class="text-xs text-slate-500">{{ __('librarian.catalog.duplicate_check.hint') }}</span>
                        </div>
                        <div id="duplicate-check-result" class="mt-3" role="status" aria-live="polite"></div>
                    </div>

                    <label class="sm:col-span-2">
                        <span class="admin-label">{{ __('librarian.catalog.fields.subtitle') }}</span>
                        <input class="admin-input" type="text" name="subtitle" maxlength="1000" value="{{ old('subtitle', $record->subtitle) }}">
                        @error('subtitle')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.primary_author') }}</span>
                        <input class="admin-input" type="text" name="primary_author" maxlength="255" value="{{ old('primary_author', $record->primary_author) }}">
                        @error('primary_author')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.author_mark') }}</span>
                        <input class="admin-input" type="text" name="author_mark" maxlength="16" value="{{ old('author_mark', $record->author_mark) }}">
                        <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.fields.author_mark_help') }}</span>
                        @error('author_mark')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label class="sm:col-span-2">
                        <span class="admin-label">{{ __('librarian.catalog.fields.additional_authors') }}</span>
                        <textarea class="admin-input" name="additional_authors" rows="3" maxlength="2000">{{ old('additional_authors', implode("\n", $record->additional_authors ?? [])) }}</textarea>
                        <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.fields.additional_authors_help') }}</span>
                        @error('additional_authors')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.publisher') }}</span>
                        <input class="admin-input" type="text" name="publisher" maxlength="255" value="{{ old('publisher', $record->publisher) }}">
                        @error('publisher')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.publication_year') }}</span>
                        <input class="admin-input" type="number" name="publication_year" id="publication-year-input" min="1500" max="2100" step="1" value="{{ old('publication_year', $record->publication_year) }}">
                        <span class="mt-1 hidden text-xs font-semibold text-amber-700" data-anomaly-for="publication_year"></span>
                        @error('publication_year')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.isbn') }}</span>
                        <input class="admin-input font-mono" type="text" name="isbn" id="isbn-input" maxlength="32" value="{{ old('isbn', $record->isbn) }}">
                        <span class="mt-1 hidden text-xs font-semibold text-amber-700" data-anomaly-for="isbn"></span>
                        @error('isbn')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.category') }}</span>
                        <input class="admin-input" type="text" name="category" maxlength="128" value="{{ old('category', $record->category) }}">
                        @error('category')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label class="sm:col-span-2">
                        <span class="admin-label">{{ __('librarian.catalog.fields.cover_path') }}</span>
                        <input class="admin-input" type="text" name="cover_path" maxlength="2048" value="{{ old('cover_path', $record->cover_path) }}" placeholder="https://…">
                        @error('cover_path')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 font-headline text-2xl text-primary">{{ __('librarian.catalog.fields.udc_code') }} · {{ __('librarian.catalog.fields.keywords') }}</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.udc_code') }}</span>
                        <input
                            class="admin-input font-mono"
                            type="text"
                            name="udc_code"
                            id="udc-code-input"
                            list="udc-code-options"
                            maxlength="64"
                            autocomplete="off"
                            value="{{ old('udc_code', $record->udc_code) }}"
                        >
                        <datalist id="udc-code-options"></datalist>
                        <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.fields.udc_help') }}</span>
                        <span class="mt-1 block text-xs font-semibold text-secondary" id="udc-code-hint"></span>
                        @error('udc_code')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label>
                        <span class="admin-label">{{ __('librarian.catalog.fields.keywords') }}</span>
                        <textarea class="admin-input" name="keywords" rows="3" maxlength="2000">{{ old('keywords', implode("\n", $record->keywords ?? [])) }}</textarea>
                        <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.fields.keywords_help') }}</span>
                        @error('keywords')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label class="sm:col-span-2">
                        <span class="admin-label">{{ __('librarian.catalog.fields.annotation') }}</span>
                        <textarea class="admin-input" name="annotation" rows="6" maxlength="10000">{{ old('annotation', $record->annotation) }}</textarea>
                        @error('annotation')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="admin-card">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-headline text-xl text-primary">{{ __('common.fields.status') }}</h2>
                    @if ($record->exists)
                        @if ($record->is_draft)
                            <x-admin.status-badge status="pending" :label="__('librarian.catalog.draft_badge')" />
                        @else
                            <x-admin.status-badge status="active" :label="__('librarian.catalog.complete_badge')" />
                        @endif
                    @endif
                </div>

                <div class="space-y-5">
                    <label class="block">
                        <span class="admin-label">{{ __('librarian.catalog.fields.resource_type') }} *</span>
                        <select class="admin-input" name="resource_type" required>
                            @foreach (\App\Models\Catalog\BibliographicRecord::RESOURCE_TYPES as $type)
                                <option value="{{ $type }}" @selected(old('resource_type', $record->resource_type) === $type)>
                                    {{ __('librarian.catalog.resource_types.'.$type) }}
                                </option>
                            @endforeach
                        </select>
                        @error('resource_type')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label class="block">
                        <span class="admin-label">{{ __('librarian.catalog.fields.language') }} *</span>
                        <select class="admin-input" name="language" required>
                            @foreach (\App\Models\Catalog\BibliographicRecord::LANGUAGES as $language)
                                <option value="{{ $language }}" @selected(old('language', $record->language) === $language)>
                                    {{ __('librarian.catalog.languages.'.$language) }}
                                </option>
                            @endforeach
                        </select>
                        @error('language')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <label class="block">
                        <span class="admin-label">{{ __('librarian.catalog.fields.notes') }}</span>
                        <textarea class="admin-input" name="notes" rows="4" maxlength="5000">{{ old('notes', $record->notes) }}</textarea>
                        @error('notes')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    {{-- ДИР §6.3 "пометка записи как проблемной": independent of
                         is_draft, so a complete-but-suspicious record can still
                         be queued for review. --}}
                    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <label class="flex items-start gap-2 text-sm font-semibold text-amber-900">
                            <input type="hidden" name="needs_manual_review" value="0">
                            <input
                                class="mt-0.5 rounded border-amber-300"
                                type="checkbox"
                                name="needs_manual_review"
                                value="1"
                                @checked((bool) old('needs_manual_review', $record->needs_manual_review))
                            >
                            <span>{{ __('librarian.catalog.manual_review.label') }}</span>
                        </label>
                        <p class="mt-1 text-xs text-amber-800">{{ __('librarian.catalog.manual_review.hint') }}</p>
                        <input
                            class="admin-input mt-3"
                            type="text"
                            name="review_note"
                            maxlength="500"
                            placeholder="{{ __('librarian.catalog.manual_review.note_placeholder') }}"
                            value="{{ old('review_note', $record->review_note) }}"
                        >
                        @error('review_note')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if ($record->exists)
                    <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">{{ __('librarian.catalog.fields.responsible_librarian') }}</dt>
                            <dd class="text-right font-semibold text-slate-700">{{ $record->responsibleLibrarian?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">{{ __('librarian.catalog.fields.last_updated') }}</dt>
                            <dd class="text-right font-semibold text-slate-700">{{ $record->updated_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-500">{{ __('librarian.nav.copies') }}</dt>
                            <dd class="text-right font-semibold text-slate-700">
                                {{ __('librarian.catalog.copies_count', [
                                    'total' => $copies->count(),
                                    'available' => $copies->where('status', 'available')->count(),
                                ]) }}
                            </dd>
                        </div>
                    </dl>
                @endif

                <div class="mt-6 flex flex-col gap-2">
                    <button class="admin-btn admin-btn-primary w-full" type="submit">
                        <span class="material-symbols-outlined text-[19px]">save</span>
                        {{ $duplicate
                            ? __('librarian.catalog.duplicate_confirm')
                            : ($record->exists ? __('common.actions.save_changes') : __('common.actions.create')) }}
                    </button>
                    <a class="admin-btn admin-btn-secondary w-full" href="{{ route('librarian.catalog.index') }}">
                        {{ __('common.actions.cancel') }}
                    </a>
                </div>
            </section>
        </aside>
    </form>

    @if ($record->exists)
        <section class="admin-card mt-6 overflow-hidden p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6">
                <div>
                    <h2 class="font-headline text-2xl text-primary">{{ __('librarian.catalog.copies_section') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ __('librarian.catalog.copies_count', [
                            'total' => $copies->count(),
                            'available' => $copies->where('status', 'available')->count(),
                        ]) }}
                    </p>

                    {{-- §9.3 — the cataloguer registering copies should see that
                         the count drives the real loan period readers get. Read
                         only: the scale itself is an admin setting. --}}
                    @php
                        $loanPeriods = app(\App\Services\Catalog\LoanPeriodPolicy::class);
                        $circulating = $copies->whereNotIn('status', ['lost', 'written_off'])->count();
                        $periodTier = $loanPeriods->describeCopyCount($circulating);
                    @endphp
                    <p class="mt-2 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                        <span class="material-symbols-outlined mt-px text-[16px] text-slate-400">schedule</span>
                        <span>
                            {{ __('librarian.catalog.loan_period_hint', [
                                'copies' => $circulating,
                                'days' => $periodTier['days'],
                            ]) }}
                            <span class="mt-0.5 block text-slate-500">
                                {{ __('librarian.catalog.loan_period_scale', [
                                    'scale' => collect($loanPeriods->scaleRows())
                                        ->map(fn (array $row): string => ($row['to'] === null ? $row['from'].'+' : $row['from'].'–'.$row['to']).' → '.$row['days'])
                                        ->implode(', '),
                                ]) }}
                            </span>
                        </span>
                    </p>
                </div>
                @can('copies.create')
                    <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.create', ['record' => $record->id]) }}">
                        <span class="material-symbols-outlined text-[19px]">add_box</span>
                        {{ __('librarian.catalog.add_copy') }}
                    </a>
                @endcan
            </div>

            @if ($copies->isNotEmpty())
                <div class="flex flex-wrap gap-2 px-6 pt-4 text-xs">
                    @foreach ($copies->groupBy('branch_id') as $branchId => $branchCopies)
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">
                            <span class="material-symbols-outlined text-[15px]">apartment</span>
                            {{ $branchNames[$branchId] ?? '—' }}
                            <strong class="text-primary">{{ $branchCopies->count() }}</strong>
                        </span>
                    @endforeach
                    @foreach ($copies->groupBy('fund_id') as $fundId => $fundCopies)
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-slate-600">
                            <span class="material-symbols-outlined text-[15px]">inventory_2</span>
                            {{ $fundNames[$fundId] ?? '—' }}
                            <strong class="text-primary">{{ $fundCopies->count() }}</strong>
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="mt-5 overflow-x-auto">
                <table class="admin-table min-w-[880px]">
                    <thead>
                        <tr>
                            <th>{{ __('librarian.copies.fields.inventory_number') }}</th>
                            <th>{{ __('librarian.copies.fields.barcode') }}</th>
                            <th>{{ __('librarian.copies.fields.branch') }}</th>
                            <th>{{ __('librarian.copies.fields.fund') }}</th>
                            <th>{{ __('librarian.copies.fields.shelf_location') }}</th>
                            <th>{{ __('librarian.copies.fields.status') }}</th>
                            <th class="text-right">{{ __('common.fields.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($copies as $copy)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-xs font-semibold text-primary">{{ $copy->inventory_number }}</td>
                                <td class="whitespace-nowrap font-mono text-xs text-slate-600">{{ $copy->barcode ?: '—' }}</td>
                                <td class="text-slate-700">{{ $copy->branch?->name ?? '—' }}</td>
                                <td class="text-slate-700">{{ $copy->fund?->name ?? '—' }}</td>
                                <td class="text-slate-600">{{ $copy->shelf_location ?: '—' }}</td>
                                <td>
                                    <x-admin.status-badge
                                        :status="$copyStatusTone($copy->status)"
                                        :label="__('librarian.copies.statuses.'.$copy->status)"
                                    />
                                </td>
                                <td>
                                    <div class="flex justify-end">
                                        <a
                                            class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-primary"
                                            href="{{ route('librarian.copies.show', $copy) }}"
                                            title="{{ __('librarian.copies.card') }}"
                                            aria-label="{{ __('librarian.copies.card') }}"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-14 text-center">
                                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">inventory_2</span>
                                    <span class="text-sm text-slate-500">{{ __('librarian.catalog.no_copies') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- §18: electronic materials attached to this record. Each row is its
             own form so a save never touches the metadata form above. --}}
        <section class="admin-card mt-6">
            <h2 class="font-headline text-2xl text-primary">{{ __('librarian.catalog.materials.section') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('librarian.catalog.materials.hint') }}</p>

            @if ($materials->isNotEmpty())
                <ul class="mt-5 space-y-3">
                    @foreach ($materials as $material)
                        <li class="rounded-xl border border-slate-200 bg-slate-50/60">
                            <details class="group" @if ($errors->hasAny(['title', 'external_url', 'file', 'file_type', 'access_level', 'license_terms']) && old('material_id') == $material->id) open @endif>
                                <summary class="flex cursor-pointer flex-wrap items-center gap-3 px-4 py-3">
                                    <span class="material-symbols-outlined text-[20px] text-slate-500">
                                        {{ $material->file_path ? 'draft' : 'link' }}
                                    </span>
                                    <span class="font-semibold text-primary">{{ $material->title }}</span>
                                    <x-admin.status-badge
                                        :status="$material->is_active ? 'active' : 'inactive'"
                                        :label="$material->is_active ? __('librarian.catalog.materials.active') : __('librarian.catalog.materials.inactive')"
                                    />
                                    <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-600">
                                        {{ __('librarian.catalog.materials.access_levels.'.$material->access_level) }}
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-600">
                                        {{ __('librarian.catalog.materials.file_types.'.$material->file_type) }}
                                    </span>
                                    @if ($material->allow_download)
                                        <span class="inline-flex items-center gap-1 text-xs text-slate-500">
                                            <span class="material-symbols-outlined text-[15px]">download</span>
                                            {{ __('librarian.catalog.materials.download_allowed') }}
                                        </span>
                                    @endif
                                    <span class="ml-auto text-xs text-slate-500">
                                        {{ $material->uploadedBy?->name ?? '—' }}
                                        @if ($material->file_size)
                                            · {{ $sizeLabel($material->file_size) }}
                                        @endif
                                    </span>
                                    <span class="material-symbols-outlined text-[20px] text-slate-400 group-open:rotate-180">expand_more</span>
                                </summary>

                                <div class="border-t border-slate-200 px-4 py-4">
                                    @if ($material->external_url)
                                        <p class="mb-4 break-all text-xs text-slate-500">
                                            <span class="font-semibold">{{ __('librarian.catalog.materials.fields.external_url') }}:</span>
                                            <a class="text-secondary underline underline-offset-2" href="{{ $material->external_url }}" target="_blank" rel="noopener">{{ $material->external_url }}</a>
                                        </p>
                                    @elseif ($material->file_path)
                                        <p class="mb-4 break-all text-xs text-slate-500">
                                            <span class="font-semibold">{{ __('librarian.catalog.materials.fields.file') }}:</span>
                                            <span class="font-mono">{{ $material->file_path }}</span>
                                        </p>
                                    @endif

                                    <form
                                        method="POST"
                                        action="{{ route('librarian.catalog.materials.update', [$record, $material]) }}"
                                        enctype="multipart/form-data"
                                        class="grid gap-4 sm:grid-cols-2"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="material_id" value="{{ $material->id }}">

                                        @include('librarian.catalog.partials.material-fields', ['material' => $material])

                                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                                            <button class="admin-btn admin-btn-primary" type="submit">
                                                <span class="material-symbols-outlined text-[19px]">save</span>
                                                {{ __('common.actions.save_changes') }}
                                            </button>
                                        </div>
                                    </form>

                                    <form
                                        method="POST"
                                        action="{{ route('librarian.catalog.materials.destroy', [$record, $material]) }}"
                                        class="mt-3"
                                        onsubmit="return confirm(@js(__('common.feedback.confirm_delete')));"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="admin-btn admin-btn-danger" type="submit">
                                            <span class="material-symbols-outlined text-[19px]">delete</span>
                                            {{ __('common.actions.delete') }}
                                        </button>
                                    </form>
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">cloud_off</span>
                    {{ __('librarian.catalog.materials.empty') }}
                </p>
            @endif

            <details class="mt-5 rounded-xl border border-slate-200" @if ($errors->any() && ! old('material_id')) open @endif>
                <summary class="cursor-pointer px-4 py-3 font-semibold text-secondary">
                    {{ __('librarian.catalog.materials.add') }}
                </summary>
                <form
                    method="POST"
                    action="{{ route('librarian.catalog.materials.store', $record) }}"
                    enctype="multipart/form-data"
                    class="grid gap-4 border-t border-slate-200 px-4 py-4 sm:grid-cols-2"
                >
                    @csrf
                    @include('librarian.catalog.partials.material-fields', ['material' => null])

                    <div class="sm:col-span-2">
                        <button class="admin-btn admin-btn-primary" type="submit">
                            <span class="material-symbols-outlined text-[19px]">add</span>
                            {{ __('librarian.catalog.materials.add') }}
                        </button>
                    </div>
                </form>
            </details>
        </section>

        {{-- §10.4: manual links between records. Written both ways by the
             controller, so removing from either side clears the pair. --}}
        <section class="admin-card mt-6">
            <h2 class="font-headline text-2xl text-primary">{{ __('librarian.catalog.relations.section') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('librarian.catalog.relations.hint') }}</p>

            @if ($relations->isNotEmpty())
                <ul class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @foreach ($relations as $related)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <span class="material-symbols-outlined text-[20px] text-slate-400">link</span>
                            <div class="min-w-0">
                                <a class="font-semibold text-primary underline-offset-2 hover:underline" href="{{ route('librarian.catalog.edit', $related) }}">
                                    {{ $related->title }}
                                </a>
                                <p class="text-xs text-slate-500">
                                    {{ collect([$related->primary_author, $related->publication_year, $related->isbn])
                                        ->filter(fn ($part) => trim((string) $part) !== '')
                                        ->implode(' · ') ?: '—' }}
                                </p>
                            </div>
                            <form
                                method="POST"
                                action="{{ route('librarian.catalog.relations.destroy', [$record, $related]) }}"
                                class="ml-auto"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-red-50 hover:text-error"
                                    type="submit"
                                    title="{{ __('librarian.catalog.relations.remove') }}"
                                    aria-label="{{ __('librarian.catalog.relations.remove') }}"
                                >
                                    <span class="material-symbols-outlined text-[20px]">link_off</span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">link_off</span>
                    {{ __('librarian.catalog.relations.empty') }}
                </p>
            @endif

            <form method="POST" action="{{ route('librarian.catalog.relations.store', $record) }}" class="mt-5 grid gap-4 sm:grid-cols-3 sm:items-end">
                @csrf
                <label class="sm:col-span-2">
                    <span class="admin-label">{{ __('librarian.catalog.relations.search_label') }}</span>
                    <input
                        class="admin-input"
                        type="text"
                        id="related-record-input"
                        list="related-record-options"
                        autocomplete="off"
                        placeholder="{{ __('librarian.catalog.relations.search_placeholder') }}"
                    >
                    <datalist id="related-record-options"></datalist>
                    <input type="hidden" name="related_record_id" id="related-record-id">
                    <span class="mt-1 block text-xs text-slate-500">{{ __('librarian.catalog.relations.search_help') }}</span>
                    @error('related_record_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </label>

                <button class="admin-btn admin-btn-primary" type="submit">
                    <span class="material-symbols-outlined text-[19px]">add_link</span>
                    {{ __('librarian.catalog.relations.add') }}
                </button>
            </form>
        </section>

        {{-- ДИР §6.3 "история исправлений" — per-record, so it is reachable
             without /admin/logs access. --}}
        <section class="admin-card mt-6">
            <h2 class="font-headline text-2xl text-primary">{{ __('librarian.catalog.history.section') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('librarian.catalog.history.hint') }}</p>

            @if ($history->isNotEmpty())
                <ol class="mt-5 space-y-3">
                    @foreach ($history as $entry)
                        @php
                            $old = (array) ($entry->old_values ?? []);
                            $new = (array) ($entry->new_values ?? []);
                            // Only fields that actually moved, so a bulk edit
                            // does not print the whole record every time.
                            $changed = collect(array_keys($new + $old))
                                ->filter(fn (string $key) => json_encode($old[$key] ?? null) !== json_encode($new[$key] ?? null))
                                ->values();
                        @endphp
                        <li class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="material-symbols-outlined text-[19px] text-slate-500">history</span>
                                <strong class="text-primary">{{ __('librarian.catalog.history.actions.'.$entry->action_type) }}</strong>
                                <span class="text-slate-600">{{ $entry->actor_name ?: '—' }}</span>
                                @if ($entry->actor_role)
                                    <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-500">{{ $entry->actor_role }}</span>
                                @endif
                                <span class="ml-auto text-xs text-slate-500">{{ $entry->occurred_at?->format('d.m.Y H:i') ?? '—' }}</span>
                            </div>

                            @if ($entry->reason)
                                <p class="mt-2 text-xs text-slate-600"><strong>{{ __('common.fields.reason') }}:</strong> {{ $entry->reason }}</p>
                            @endif

                            {{-- ДИР §6.3 — undo one change. Restores only the
                                 fields this entry altered; the revert is itself
                                 audited rather than erasing the entry. --}}
                            @if ($changed->isNotEmpty() && $entry->action_type === 'metadata.update')
                                @can('catalog.edit_record')
                                    <form
                                        method="POST"
                                        action="{{ route('librarian.catalog.revert', [$record, $entry->getKey()]) }}"
                                        class="mt-2"
                                        onsubmit="return confirm(@js(__('librarian.catalog.history_extra.revert_confirm')));"
                                    >
                                        @csrf
                                        <button class="inline-flex items-center gap-1 text-xs font-semibold text-secondary hover:underline" type="submit">
                                            <span class="material-symbols-outlined text-[16px]">undo</span>
                                            {{ __('librarian.catalog.history_extra.revert') }}
                                        </button>
                                    </form>
                                @endcan
                            @endif

                            @if ($changed->isNotEmpty())
                                <div class="mt-3 overflow-x-auto">
                                    <table class="w-full min-w-125 text-xs">
                                        <thead>
                                            <tr class="text-left text-slate-500">
                                                <th class="py-1 pr-3 font-semibold">{{ __('librarian.catalog.history.field') }}</th>
                                                <th class="py-1 pr-3 font-semibold">{{ __('librarian.catalog.history.was') }}</th>
                                                <th class="py-1 font-semibold">{{ __('librarian.catalog.history.became') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($changed as $field)
                                                @php
                                                    $render = static function ($value): string {
                                                        if ($value === null || $value === '') {
                                                            return '—';
                                                        }
                                                        if (is_bool($value)) {
                                                            return $value ? '✓' : '✗';
                                                        }

                                                        return is_scalar($value)
                                                            ? mb_strimwidth((string) $value, 0, 80, '…')
                                                            : mb_strimwidth((string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 80, '…');
                                                    };
                                                @endphp
                                                <tr class="border-t border-slate-200">
                                                    <td class="py-1 pr-3 font-semibold text-slate-700">
                                                        {{ __('librarian.catalog.fields.'.$field) }}
                                                    </td>
                                                    <td class="py-1 pr-3 text-red-700">{{ $render($old[$field] ?? null) }}</td>
                                                    <td class="py-1 text-emerald-700">{{ $render($new[$field] ?? null) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mt-5 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">history_toggle_off</span>
                    {{ __('librarian.catalog.history.empty') }}
                </p>
            @endif
        </section>

        @can('catalog.delete_record')
            <section class="admin-card mt-6 border border-red-200">
                <h2 class="font-headline text-2xl text-error">{{ __('librarian.catalog.delete_title') }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ __('librarian.catalog.delete_hint') }}</p>

                <form
                    method="POST"
                    action="{{ route('librarian.catalog.destroy', $record) }}"
                    class="mt-4 grid gap-4 lg:grid-cols-3 lg:items-end"
                    onsubmit="return confirm(@js(__('common.feedback.confirm_delete')));"
                >
                    @csrf
                    @method('DELETE')

                    <label class="lg:col-span-2">
                        <span class="admin-label">{{ __('common.fields.reason') }} *</span>
                        <textarea class="admin-input" name="reason" rows="3" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea>
                        @error('reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                    </label>

                    <button class="admin-btn admin-btn-danger" type="submit">
                        <span class="material-symbols-outlined text-[19px]">delete_forever</span>
                        {{ __('common.actions.delete') }}
                    </button>
                </form>
            </section>
        @endcan
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('udc-code-input');
        var options = document.getElementById('udc-code-options');
        var hint = document.getElementById('udc-code-hint');

        if (!input || !options) {
            return;
        }

        var endpoint = @js(route('librarian.catalog.udc-search'));
        var timer = null;
        var controller = null;

        function render(items, term) {
            options.textContent = '';
            var matched = '';

            items.forEach(function (item) {
                var code = String(item.code || '');
                var description = String(item.description || '');
                var option = document.createElement('option');
                option.value = code;
                option.label = description;
                option.textContent = description;
                options.appendChild(option);

                if (code === term) {
                    matched = description;
                }
            });

            if (hint) {
                hint.textContent = matched;
            }
        }

        function load(term) {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();

            fetch(endpoint + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(function (response) {
                    return response.ok ? response.json() : Promise.reject(response.status);
                })
                .then(function (payload) {
                    render(Array.isArray(payload.data) ? payload.data : [], term);
                })
                .catch(function () {});
        }

        input.addEventListener('input', function () {
            var term = input.value.trim();
            window.clearTimeout(timer);

            if (term === '') {
                options.textContent = '';
                if (hint) {
                    hint.textContent = '';
                }
                return;
            }

            timer = window.setTimeout(function () {
                load(term);
            }, 250);
        });

        if (input.value.trim() !== '') {
            load(input.value.trim());
        }
    })();
</script>

<script>
    // ДИР §6.2 — pre-submit duplicate check. Reads the live form values and
    // renders the match inline with a link, so the librarian can open and
    // compare the existing record instead of just being told one exists.
    (function () {
        var button = document.getElementById('duplicate-check-btn');
        var result = document.getElementById('duplicate-check-result');
        var titleInput = document.getElementById('record-title-input');

        if (!button || !result || !titleInput) {
            return;
        }

        var endpoint = @js(route('librarian.catalog.duplicate-check'));
        var ignore = @js($record->exists ? $record->getKey() : null);
        var copy = @js([
            'checking' => __('librarian.catalog.duplicate_check.checking'),
            'none' => __('librarian.catalog.duplicate_check.none'),
            'found' => __('librarian.catalog.duplicate_check.found'),
            'open' => __('librarian.catalog.duplicate_open'),
            'title_required' => __('librarian.catalog.duplicate_check.title_required'),
            'failed' => __('librarian.catalog.duplicate_check.failed'),
        ]);

        function value(name) {
            var el = document.querySelector('[name="' + name + '"]');
            return el ? el.value.trim() : '';
        }

        function box(tone, icon, html) {
            var tones = {
                info: 'border-slate-200 bg-slate-50 text-slate-700',
                ok: 'border-emerald-300 bg-emerald-50 text-emerald-900',
                warn: 'border-amber-300 bg-amber-50 text-amber-900',
            };
            result.innerHTML =
                '<div class="flex items-start gap-2 rounded-xl border px-4 py-3 text-sm ' + tones[tone] + '">' +
                '<span class="material-symbols-outlined text-[20px]">' + icon + '</span>' +
                '<div>' + html + '</div></div>';
        }

        function escapeHtml(value) {
            var div = document.createElement('div');
            div.textContent = String(value == null ? '' : value);
            return div.innerHTML;
        }

        button.addEventListener('click', function () {
            var title = titleInput.value.trim();
            if (title === '') {
                box('warn', 'error', escapeHtml(copy.title_required));
                titleInput.focus();
                return;
            }

            box('info', 'hourglass_top', escapeHtml(copy.checking));

            var params = new URLSearchParams();
            params.set('title', title);
            ['primary_author', 'publication_year', 'isbn'].forEach(function (name) {
                var v = value(name);
                if (v !== '') {
                    params.set(name, v);
                }
            });
            if (ignore) {
                params.set('ignore', ignore);
            }

            fetch(endpoint + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.ok ? response.json() : Promise.reject(response.status);
                })
                .then(function (payload) {
                    var dup = payload.duplicate;
                    if (!dup) {
                        box('ok', 'check_circle', escapeHtml(copy.none));
                        return;
                    }

                    var meta = [dup.author, dup.year, dup.isbn]
                        .filter(function (part) { return part !== null && String(part || '').trim() !== ''; })
                        .map(escapeHtml)
                        .join(' · ');

                    box(
                        'warn',
                        'content_copy',
                        '<p class="font-bold">' + escapeHtml(copy.found) + '</p>' +
                        '<p class="mt-1">' + escapeHtml(dup.title) + '</p>' +
                        (meta ? '<p class="mt-0.5 text-xs opacity-80">' + meta + '</p>' : '') +
                        '<a class="mt-2 inline-flex items-center gap-1 font-bold underline underline-offset-2" target="_blank" rel="noopener" href="' +
                            escapeHtml(payload.editUrl) + '">' +
                            '<span class="material-symbols-outlined text-[18px]">open_in_new</span>' +
                            escapeHtml(copy.open) + '</a>'
                    );
                })
                .catch(function () {
                    box('warn', 'error', escapeHtml(copy.failed));
                });
        });
    })();
</script>

<script>
    // ДИР §6.3 "подсветка аномалий" / §6.4 ISBN. Deliberately advisory: the
    // imported fund contains legitimate legacy ISBNs that fail the modern
    // checksum, so a bad value is highlighted, never blocked.
    (function () {
        var copy = @js([
            'isbn_checksum' => __('librarian.catalog.anomalies.isbn_checksum'),
            'isbn_length' => __('librarian.catalog.anomalies.isbn_length'),
            'year_future' => __('librarian.catalog.anomalies.year_future'),
            'year_old' => __('librarian.catalog.anomalies.year_old'),
        ]);
        var currentYear = @js((int) date('Y'));

        function warn(field, message) {
            var input = document.getElementById(field === 'isbn' ? 'isbn-input' : 'publication-year-input');
            var hint = document.querySelector('[data-anomaly-for="' + field + '"]');
            if (!input || !hint) {
                return;
            }

            if (message) {
                hint.textContent = message;
                hint.classList.remove('hidden');
                input.classList.add('border-amber-400', 'bg-amber-50');
            } else {
                hint.textContent = '';
                hint.classList.add('hidden');
                input.classList.remove('border-amber-400', 'bg-amber-50');
            }
        }

        function isbnValid(raw) {
            var digits = raw.replace(/[\s-]/g, '').toUpperCase();

            if (digits.length === 10) {
                if (!/^\d{9}[\dX]$/.test(digits)) {
                    return null;
                }
                var sum10 = 0;
                for (var i = 0; i < 10; i++) {
                    var ch = digits.charAt(i);
                    sum10 += (ch === 'X' ? 10 : Number(ch)) * (10 - i);
                }
                return sum10 % 11 === 0;
            }

            if (digits.length === 13) {
                if (!/^\d{13}$/.test(digits)) {
                    return null;
                }
                var sum13 = 0;
                for (var j = 0; j < 13; j++) {
                    sum13 += Number(digits.charAt(j)) * (j % 2 === 0 ? 1 : 3);
                }
                return sum13 % 10 === 0;
            }

            return null; // not an ISBN-10/13 shape at all
        }

        var isbnInput = document.getElementById('isbn-input');
        if (isbnInput) {
            var checkIsbn = function () {
                var raw = isbnInput.value.trim();
                if (raw === '') {
                    warn('isbn', '');
                    return;
                }
                var verdict = isbnValid(raw);
                warn('isbn', verdict === false ? copy.isbn_checksum : (verdict === null ? copy.isbn_length : ''));
            };
            isbnInput.addEventListener('input', checkIsbn);
            checkIsbn();
        }

        var yearInput = document.getElementById('publication-year-input');
        if (yearInput) {
            var checkYear = function () {
                var raw = yearInput.value.trim();
                if (raw === '') {
                    warn('publication_year', '');
                    return;
                }
                var year = Number(raw);
                if (year > currentYear) {
                    warn('publication_year', copy.year_future.replace(':year', String(currentYear)));
                } else if (year < 1500) {
                    warn('publication_year', copy.year_old);
                } else {
                    warn('publication_year', '');
                }
            };
            yearInput.addEventListener('input', checkYear);
            checkYear();
        }
    })();
</script>

@if ($record->exists)
<script>
    // Related-material picker. Same debounce/abort shape as the UDC lookup
    // above, but the datalist option value carries the record id so the
    // hidden field always submits a real key rather than free text.
    (function () {
        var input = document.getElementById('related-record-input');
        var options = document.getElementById('related-record-options');
        var hidden = document.getElementById('related-record-id');

        if (!input || !options || !hidden) {
            return;
        }

        var endpoint = @js(route('librarian.catalog.record-search'));
        var exclude = @js($record->getKey());
        var timer = null;
        var controller = null;
        var index = {};

        function render(items) {
            options.textContent = '';
            index = {};

            items.forEach(function (item) {
                var parts = [item.author, item.year, item.isbn].filter(Boolean);
                var label = String(item.title || '') + (parts.length ? ' — ' + parts.join(' · ') : '');
                index[label] = item.id;

                var option = document.createElement('option');
                option.value = label;
                options.appendChild(option);
            });

            syncHidden();
        }

        function syncHidden() {
            hidden.value = index[input.value] || '';
        }

        function load(term) {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();

            fetch(endpoint + '?q=' + encodeURIComponent(term) + '&exclude=' + encodeURIComponent(exclude), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(function (response) {
                    return response.ok ? response.json() : Promise.reject(response.status);
                })
                .then(function (payload) {
                    render(Array.isArray(payload.data) ? payload.data : []);
                })
                .catch(function () {});
        }

        input.addEventListener('input', function () {
            syncHidden();
            var term = input.value.trim();
            window.clearTimeout(timer);

            if (term === '') {
                options.textContent = '';
                index = {};
                hidden.value = '';
                return;
            }

            timer = window.setTimeout(function () {
                load(term);
            }, 250);
        });
    })();
</script>
@endif
@endpush
