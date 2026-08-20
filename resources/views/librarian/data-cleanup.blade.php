@extends('layouts.librarian')

@section('title', __('librarian.data_cleanup.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    <div class="mx-auto w-full max-w-7xl">
        <x-admin.page-header
            :eyebrow="__('librarian.data_cleanup.eyebrow')"
            :title="__('librarian.data_cleanup.title')"
            :subtitle="__('librarian.data_cleanup.subtitle')"
        >
            <span class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                <span class="material-symbols-outlined text-[19px] text-secondary">library_books</span>
                <span class="text-on-surface-variant">{{ __('librarian.data_cleanup.total_records') }}</span>
                <strong class="font-headline text-lg leading-none text-primary-container">{{ number_format($totalRecords, 0, ',', ' ') }}</strong>
            </span>
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.catalog.index') }}">
                <span class="material-symbols-outlined text-[19px]">menu_book</span>
                {{ __('librarian.nav.catalog') }}
            </a>
        </x-admin.page-header>

        {{-- Progress line: gives the day a visible floor, so a 9 000-item
             backlog does not read as an endless list (ДИР 6). --}}
        <section class="admin-card mb-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="font-headline text-xl text-primary-container">{{ __('librarian.data_cleanup.progress.title') }}</h2>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ __('librarian.data_cleanup.progress.summary', [
                            'resolved' => number_format($resolvedToday, 0, ',', ' '),
                            'open' => number_format($openTotal, 0, ',', ' '),
                        ]) }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="font-headline text-4xl leading-none text-secondary">{{ number_format($resolvedToday, 0, ',', ' ') }}</div>
                    <div class="text-xs uppercase tracking-[.14em] text-outline">{{ __('librarian.data_cleanup.progress.today') }}</div>
                </div>
            </div>
            @php
                // Relative to a day's worth of work, not to the whole backlog —
                // a bar that never visibly moves is worse than no bar.
                $dailyTarget = 50;
                $progressPct = min(100, (int) round(100 * $resolvedToday / max(1, $dailyTarget)));
            @endphp
            <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-secondary transition-all duration-500" style="width: {{ $progressPct }}%"></div>
            </div>
            <p class="mt-2 text-xs text-outline">{{ __('librarian.data_cleanup.progress.target', ['target' => $dailyTarget]) }}</p>
        </section>

        @php
            $groups = [
                \App\Services\Catalog\DataQualityQueues::GROUP_COMPLETION => 'assignment_add',
                \App\Services\Catalog\DataQualityQueues::GROUP_JUDGEMENT => 'balance',
                \App\Services\Catalog\DataQualityQueues::GROUP_RETYPING => 'keyboard',
            ];
        @endphp

        @foreach ($groups as $groupKey => $groupIcon)
            @php
                $groupIssues = collect($definitions)->filter(fn (array $d): bool => $d['group'] === $groupKey);
                // Distinct records, not the sum of the tiles: one record is
                // routinely in several queues at once.
                $groupTotal = $groupTotals[$groupKey] ?? 0;
            @endphp
            <section class="mb-8" aria-label="{{ __('librarian.data_cleanup.groups.'.$groupKey.'.title') }}">
                <div class="mb-3 flex flex-wrap items-baseline gap-3">
                    <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                        <span class="material-symbols-outlined text-[20px] text-secondary">{{ $groupIcon }}</span>
                        {{ __('librarian.data_cleanup.groups.'.$groupKey.'.title') }}
                    </h2>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                        {{ number_format($groupTotal, 0, ',', ' ') }}
                    </span>
                </div>
                <p class="mb-4 max-w-4xl text-sm leading-6 text-on-surface-variant">
                    {{ __('librarian.data_cleanup.groups.'.$groupKey.'.hint') }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($groupIssues as $issueKey => $definition)
                        @php $isActive = $issue === $issueKey; @endphp
                        <a
                            href="{{ route('librarian.data-cleanup', ['issue' => $issueKey]) }}"
                            @if ($isActive) aria-current="page" @endif
                            class="group flex flex-col justify-between rounded-xl border p-5 transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $isActive ? 'border-secondary bg-secondary-container/40 shadow-sm ring-1 ring-secondary' : 'border-slate-200 bg-white' }}"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <span class="material-symbols-outlined text-[22px] {{ $isActive ? 'text-secondary' : 'text-outline' }}">{{ $definition['icon'] }}</span>
                                <span class="font-headline text-3xl leading-none {{ ($counts[$issueKey] ?? 0) > 0 ? 'text-primary-container' : 'text-slate-300' }}">
                                    {{ number_format($counts[$issueKey] ?? 0, 0, ',', ' ') }}
                                </span>
                            </div>
                            <div class="mt-4">
                                <div class="text-sm font-semibold {{ $isActive ? 'text-secondary' : 'text-primary-container' }}">
                                    {{ __('librarian.data_cleanup.issues.'.$issueKey) }}
                                </div>
                                <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                                    {{ __('librarian.data_cleanup.issues.'.$issueKey.'_hint') }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="admin-card overflow-hidden p-0">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                        <span class="material-symbols-outlined text-[20px] text-secondary">{{ $definitions[$issue]['icon'] }}</span>
                        {{ __('librarian.data_cleanup.issues.'.$issue) }}
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                        {{ __('librarian.data_cleanup.issues.'.$issue.'_hint') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($mode === 'retype')
                        @can('catalog.edit_record')
                            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.data-cleanup.retype') }}">
                                <span class="material-symbols-outlined text-[19px]">keyboard</span>
                                {{ __('librarian.data_cleanup.retype.open') }}
                            </a>
                        @endcan
                    @elseif ($issue === 'manual_review')
                        @can('catalog.edit_record')
                            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.catalog.index', ['review' => $issue]) }}">
                                <span class="material-symbols-outlined text-[19px]">checklist</span>
                                {{ __('librarian.data_cleanup.open_in_bulk_editor') }}
                            </a>
                        @endcan
                    @endif
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-headline text-lg leading-none text-primary-container">
                        {{ number_format($counts[$issue] ?? 0, 0, ',', ' ') }}
                    </span>
                </div>
            </header>

            {{-- ДИР 6.3 — the language queue is not one list of 2 208 rows but
                 three routes of different risk, each with its own safe tool. --}}
            @if ($mode === 'tiers' && $tierCounts !== null)
                @php
                    $tierMeta = [
                        'high' => ['tone' => 'border-emerald-300 bg-emerald-50', 'text' => 'text-emerald-900', 'icon' => 'done_all', 'bulk' => true],
                        'medium' => ['tone' => 'border-amber-300 bg-amber-50', 'text' => 'text-amber-900', 'icon' => 'rule', 'bulk' => true],
                        'low' => ['tone' => 'border-red-300 bg-red-50', 'text' => 'text-red-900', 'icon' => 'pan_tool', 'bulk' => false],
                    ];
                @endphp
                <div class="grid gap-4 px-6 py-5 lg:grid-cols-3">
                    @foreach ($tierMeta as $tierKey => $meta)
                        <div class="rounded-xl border {{ $meta['tone'] }} p-5">
                            <div class="flex items-start justify-between gap-3">
                                <span class="material-symbols-outlined text-[22px] {{ $meta['text'] }}">{{ $meta['icon'] }}</span>
                                <span class="font-headline text-3xl leading-none {{ $meta['text'] }}">
                                    {{ number_format($tierCounts[$tierKey], 0, ',', ' ') }}
                                </span>
                            </div>
                            <div class="mt-3 text-sm font-bold {{ $meta['text'] }}">
                                {{ __('librarian.data_cleanup.tiers.'.$tierKey.'.title') }}
                            </div>
                            <p class="mt-1 text-xs leading-5 {{ $meta['text'] }} opacity-90">
                                {{ __('librarian.data_cleanup.tiers.'.$tierKey.'.hint') }}
                            </p>

                            <div class="mt-4 flex flex-col gap-2">
                                <a class="admin-btn admin-btn-secondary w-full" href="{{ route('librarian.data-cleanup', ['issue' => 'language_mismatch', 'tier' => $tierKey]) }}">
                                    <span class="material-symbols-outlined text-[19px]">visibility</span>
                                    {{ __('librarian.data_cleanup.tiers.review') }}
                                </a>
                                @if ($meta['bulk'])
                                    @can('catalog.edit_record')
                                        <a class="admin-btn admin-btn-primary w-full" href="{{ route('librarian.catalog.index', ['review' => 'language_mismatch', 'tier' => $tierKey]) }}">
                                            <span class="material-symbols-outlined text-[19px]">checklist</span>
                                            {{ __('librarian.data_cleanup.tiers.bulk') }}
                                        </a>
                                    @endcan
                                @else
                                    <span class="rounded-lg border border-red-200 bg-white/70 px-3 py-2 text-center text-xs font-semibold text-red-800">
                                        {{ __('librarian.data_cleanup.tiers.no_bulk') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($duplicates !== null)
                @forelse ($duplicates as $group)
                    <div class="border-b border-slate-100 px-6 py-5 last:border-b-0">
                        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-[.14em] text-outline">
                                    {{ __('librarian.data_cleanup.duplicate_group') }}
                                </p>
                                <h3 class="font-headline text-lg leading-tight text-primary-container">{{ $group['title'] ?? '—' }}</h3>
                            </div>
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                {{ __('librarian.data_cleanup.duplicate_records', ['count' => $group['records']->count()]) }}
                            </span>
                        </div>

                        <div class="overflow-x-auto rounded-lg border border-slate-100">
                            <table class="admin-table min-w-[720px]">
                                <thead>
                                    <tr>
                                        <th>{{ __('reports.columns.id') }}</th>
                                        <th>{{ __('librarian.catalog.fields.primary_author') }}</th>
                                        <th>{{ __('librarian.catalog.fields.publication_year') }}</th>
                                        <th>{{ __('librarian.catalog.fields.isbn') }}</th>
                                        <th>{{ __('librarian.reports.columns.copies') }}</th>
                                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['records'] as $record)
                                        <tr>
                                            <td class="whitespace-nowrap font-mono text-xs text-outline">{{ $record->getKey() }}</td>
                                            <td>{{ $record->primary_author ?? '—' }}</td>
                                            <td class="whitespace-nowrap">{{ $record->publication_year ?? '—' }}</td>
                                            <td class="whitespace-nowrap">{{ $record->isbn ?? '—' }}</td>
                                            <td class="whitespace-nowrap">{{ number_format($record->copies_count ?? $record->copies()->count(), 0, ',', ' ') }}</td>
                                            <td class="text-right">
                                                @can('catalog.edit_record')
                                                    <a class="inline-flex items-center gap-1 text-sm font-semibold text-secondary hover:underline" href="{{ route('librarian.catalog.edit', $record) }}">
                                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                                        {{ __('librarian.data_cleanup.fix_record') }}
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- ДИР 6.3 "сравнение похожих записей": field-by-field,
                             with rows that disagree highlighted, so the librarian
                             can see what actually differs before merging. --}}
                        @php
                            $compareFields = [
                                'title', 'subtitle', 'primary_author', 'publisher', 'publication_year',
                                'isbn', 'language', 'udc_code', 'category', 'resource_type', 'annotation',
                            ];
                            $cell = static function ($value): string {
                                if (is_array($value)) {
                                    $value = implode(', ', $value);
                                }

                                return trim((string) ($value ?? '')) === '' ? '—' : (string) $value;
                            };
                        @endphp
                        <details class="mt-4 rounded-lg border border-slate-200">
                            <summary class="cursor-pointer px-4 py-2 text-sm font-semibold text-secondary">
                                {{ __('librarian.data_cleanup.compare.toggle') }}
                            </summary>
                            <div class="overflow-x-auto border-t border-slate-200">
                                <table class="w-full min-w-150 text-sm">
                                    <thead>
                                        <tr class="bg-slate-50 text-left">
                                            <th class="px-4 py-2 font-semibold text-slate-600">{{ __('librarian.catalog.history.field') }}</th>
                                            @foreach ($group['records'] as $record)
                                                <th class="px-4 py-2 font-semibold text-primary">
                                                    #{{ $record->getKey() }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($compareFields as $field)
                                            @php
                                                $values = $group['records']->map(fn ($r) => $cell($r->{$field}));
                                                $differs = $values->unique()->count() > 1;
                                            @endphp
                                            <tr class="border-t border-slate-100 {{ $differs ? 'bg-amber-50' : '' }}">
                                                <td class="px-4 py-2 font-semibold {{ $differs ? 'text-amber-900' : 'text-slate-500' }}">
                                                    {{ __('librarian.catalog.fields.'.$field) }}
                                                    @if ($differs)
                                                        <span class="material-symbols-outlined align-middle text-[15px]">priority_high</span>
                                                    @endif
                                                </td>
                                                @foreach ($values as $value)
                                                    <td class="px-4 py-2 align-top {{ $differs ? 'font-semibold text-amber-900' : 'text-slate-600' }}">
                                                        {{ mb_strimwidth($value, 0, 120, '…') }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="border-t border-slate-200 px-4 py-2 text-xs text-slate-500">
                                {{ __('librarian.data_cleanup.compare.legend') }}
                            </p>
                        </details>
                    </div>
                @empty
                    <div class="py-16 text-center">
                        <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">verified</span>
                        <span class="text-sm text-slate-500">{{ __('librarian.data_cleanup.empty') }}</span>
                    </div>
                @endforelse
            @elseif ($copies !== null)
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[900px]">
                        <thead>
                            <tr>
                                <th>{{ __('librarian.copies.fields.inventory_number') }}</th>
                                <th>{{ __('librarian.catalog.fields.title') }}</th>
                                <th>{{ __('librarian.copies.fields.branch') }}</th>
                                <th>{{ __('librarian.copies.fields.shelf_location') }}</th>
                                <th class="text-right">{{ __('common.fields.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($copies as $copy)
                                <tr>
                                    <td class="whitespace-nowrap font-mono text-xs text-on-surface">{{ $copy->inventory_number }}</td>
                                    <td>
                                        <span class="block max-w-md truncate text-sm text-primary-container" title="{{ $copy->bibliographicRecord?->title }}">
                                            {{ $copy->bibliographicRecord?->title ?? '—' }}
                                        </span>
                                        <span class="block text-xs text-slate-500">{{ $copy->bibliographicRecord?->primary_author ?? '—' }}</span>
                                    </td>
                                    <td>
                                        @if ($copy->branch?->name)
                                            {{ $copy->branch->name }}
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (filled($copy->shelf_location))
                                            {{ $copy->shelf_location }}
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @can('copies.edit')
                                            <a class="inline-flex items-center gap-1 text-sm font-semibold text-secondary hover:underline" href="{{ route('librarian.copies.edit', $copy) }}">
                                                <span class="material-symbols-outlined text-[18px]">edit_location_alt</span>
                                                {{ __('librarian.data_cleanup.edit_copy_location') }}
                                            </a>
                                        @elsecan('copies.create')
                                            <a class="inline-flex items-center gap-1 text-sm font-semibold text-secondary hover:underline" href="{{ route('librarian.copies.show', $copy) }}">
                                                <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                                                {{ __('librarian.data_cleanup.view_copy') }}
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-16 text-center">
                                        <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">verified</span>
                                        <span class="text-sm text-slate-500">{{ __('librarian.data_cleanup.empty') }}</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-admin.pagination :paginator="$copies" />
            @elseif ($records === null)
                {{-- Tier landing: the three routes above are the whole screen. --}}
            @else
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[980px]">
                        <thead>
                            <tr>
                                <th>{{ __('librarian.catalog.fields.title') }}</th>
                                <th>{{ __('librarian.catalog.fields.primary_author') }}</th>
                                <th>{{ __('librarian.catalog.fields.publication_year') }}</th>
                                <th>{{ __('librarian.catalog.fields.udc_code') }}</th>
                                <th>{{ __('librarian.catalog.fields.isbn') }}</th>
                                <th>{{ __('librarian.reports.columns.copies') }}</th>
                                <th class="text-right">{{ __('common.fields.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr>
                                    <td>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="block max-w-md truncate text-sm font-semibold text-primary-container" title="{{ $record->title }}">
                                                {{ $record->title }}
                                            </span>
                                            @if ($record->is_draft)
                                                <x-admin.status-badge status="pending" :label="__('librarian.catalog.draft_badge')" />
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $record->primary_author ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ $record->publication_year ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ $record->udc_code ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ $record->isbn ?? '—' }}</td>
                                    <td class="whitespace-nowrap">{{ number_format($record->copies_count ?? $record->copies()->count(), 0, ',', ' ') }}</td>
                                    <td class="text-right">
                                        <div class="flex flex-col items-end gap-1">
                                            @can('catalog.edit_record')
                                                <a class="inline-flex items-center gap-1 text-sm font-semibold text-secondary hover:underline" href="{{ route('librarian.catalog.edit', $record) }}">
                                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                                    {{ __('librarian.data_cleanup.fix_record') }}
                                                </a>
                                            @endcan

                                            {{-- Lets the cataloguer check how the record reads to a
                                                 reader without losing their place in the queue. --}}
                                            <a
                                                class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-primary hover:underline"
                                                href="{{ '/book/'.rawurlencode($record->isbn ?: (string) $record->getKey()) }}"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                                {{ __('librarian.data_cleanup.open_public') }}
                                            </a>

                                            {{-- ДИР 6.3 — the third answer for a language mismatch:
                                                 a Russian edition that legitimately carries a Kazakh
                                                 subtitle. Clears the flag, keeps `language`. --}}
                                            @if ($issue === 'language_mismatch')
                                                @can('catalog.edit_record')
                                                    <form method="POST" action="{{ route('librarian.data-cleanup.parallel', $record) }}">
                                                        @csrf
                                                        <button class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 hover:underline" type="submit">
                                                            <span class="material-symbols-outlined text-[16px]">swap_horiz</span>
                                                            {{ __('librarian.data_cleanup.tiers.parallel_action') }}
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-16 text-center">
                                        <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">verified</span>
                                        <span class="text-sm text-slate-500">{{ __('librarian.data_cleanup.empty') }}</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-admin.pagination :paginator="$records" />
            @endif
        </section>

        <p class="mt-6 flex items-start gap-2 text-xs leading-5 text-outline">
            <span class="material-symbols-outlined text-[16px]">info</span>
            {{ __('librarian.data_cleanup.manual_note') }}
        </p>
    </div>
@endsection
