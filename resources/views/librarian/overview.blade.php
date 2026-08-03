@extends('layouts.librarian')

@section('title', __('librarian.overview.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $staffUser = auth()->user();
        $canonicalRole = (string) ($staffUser?->getRoleNames()->first() ?? '');
        $foundationRoles = ['director', 'senior_librarian', 'acquisitions', 'cataloguer', 'bibliographer'];
        $canSee = static fn (array $permissions): bool => $permissions === []
            || ($staffUser?->canAny($permissions) ?? false);

        $circulationPermissions = ['circulation.issue', 'circulation.return'];
        $reservationPermissions = ['reservation.confirm'];
        $repositoryPermissions = ['repository.upload', 'repository.approve', 'repository.publish'];
        $cleanupPermissions = ['data_cleanup.access'];
        $catalogEditPermissions = ['catalog.edit_record'];

        // Every card points at the section that owns the figure, and is hidden
        // outright when the account lacks the permission guarding that section.
        $metricCards = [
            [
                'key' => 'active_loans',
                'icon' => 'sync_alt',
                'href' => route('librarian.circulation'),
                'permissions' => $circulationPermissions,
            ],
            [
                'key' => 'overdue',
                'icon' => 'running_with_errors',
                'href' => route('librarian.circulation'),
                'permissions' => $circulationPermissions,
                'alert' => true,
            ],
            [
                'key' => 'pending_pulls',
                'icon' => 'bookmark_manager',
                'href' => route('librarian.reservations.index'),
                'permissions' => $reservationPermissions,
            ],
            [
                'key' => 'ready_pickups',
                'icon' => 'inventory',
                'href' => route('librarian.reservations.index'),
                'permissions' => $reservationPermissions,
            ],
            [
                'key' => 'draft_records',
                'icon' => 'edit_note',
                'href' => route('librarian.data-cleanup'),
                'permissions' => $cleanupPermissions,
            ],
            [
                'key' => 'repository_pending',
                'icon' => 'school',
                'href' => route('librarian.repository'),
                'permissions' => $repositoryPermissions,
            ],
            [
                'key' => 'pending_fines',
                'icon' => 'payments',
                'href' => route('librarian.fines.index'),
                'permissions' => ['fines.view'],
            ],
            [
                'key' => 'unresolved_messages',
                'icon' => 'mail',
                'href' => route('librarian.messages.index'),
                'permissions' => ['messages.view_all'],
            ],
        ];

        $visibleMetricCards = array_values(array_filter(
            $metricCards,
            static fn (array $card): bool => $canSee($card['permissions']),
        ));
    @endphp

    <div class="mx-auto w-full max-w-7xl">
        @if (in_array($canonicalRole, $foundationRoles, true))
            <x-role-foundation-notice :role="$canonicalRole" class="mb-6" />
        @endif

        <x-admin.page-header
            :eyebrow="__('librarian.overview.eyebrow')"
            :title="__('librarian.overview.title')"
            :subtitle="__('librarian.overview.subtitle')"
        >
            @can('circulation.issue')
                <a class="admin-btn admin-btn-primary" href="{{ route('librarian.circulation.issue') }}">
                    <span class="material-symbols-outlined text-[19px]">outbox</span>
                    {{ __('librarian.circulation.issue_title') }}
                </a>
            @endcan
            @can('circulation.return')
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation.return') }}">
                    <span class="material-symbols-outlined text-[19px]">move_to_inbox</span>
                    {{ __('librarian.circulation.return_title') }}
                </a>
            @endcan
        </x-admin.page-header>

        @if ($canSee([...$circulationPermissions, 'reports.view_full']))
        <section class="admin-card mb-6 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[22px] text-secondary">today</span>
                <p class="text-sm leading-6 text-on-surface-variant">
                    {{ __('librarian.overview.today_summary', [
                        'issued' => number_format($metrics['issued_today'], 0, ',', ' '),
                        'returned' => number_format($metrics['returned_today'], 0, ',', ' '),
                    ]) }}
                </p>
            </div>
            <div class="flex shrink-0 gap-8 sm:gap-10">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.14em] text-outline">
                        {{ __('librarian.overview.metrics.issued_today') }}
                    </div>
                    <div class="mt-1 font-headline text-3xl leading-none text-primary-container">
                        {{ number_format($metrics['issued_today'], 0, ',', ' ') }}
                    </div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.14em] text-outline">
                        {{ __('librarian.overview.metrics.returned_today') }}
                    </div>
                    <div class="mt-1 font-headline text-3xl leading-none text-primary-container">
                        {{ number_format($metrics['returned_today'], 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if ($visibleMetricCards !== [])
            <section class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('librarian.overview.eyebrow') }}">
                @foreach ($visibleMetricCards as $card)
                    @php
                        $value = (int) ($metrics[$card['key']] ?? 0);
                        $isAlert = ($card['alert'] ?? false) && $value > 0;
                    @endphp
                    <a
                        href="{{ $card['href'] }}"
                        class="group flex flex-col justify-between rounded-xl border p-5 transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $isAlert ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span class="material-symbols-outlined text-[22px] {{ $isAlert ? 'text-error' : 'text-secondary' }}">{{ $card['icon'] }}</span>
                            <span class="material-symbols-outlined text-[18px] text-slate-300 transition-colors group-hover:text-secondary">arrow_forward</span>
                        </div>
                        <div class="mt-5">
                            <div class="font-headline text-4xl leading-none {{ $isAlert ? 'text-error' : 'text-primary-container' }}">
                                {{ number_format($value, 0, ',', ' ') }}
                            </div>
                            <div class="mt-2 text-sm font-semibold {{ $isAlert ? 'text-on-error-container' : 'text-primary-container' }}">
                                {{ __('librarian.overview.metrics.'.$card['key']) }}
                            </div>
                            <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                                {{ __('librarian.overview.metrics.'.$card['key'].'_hint') }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </section>
        @endif

        @can('data_quality.view_reports')
            <section class="admin-card mb-8">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="font-headline text-2xl text-primary">{{ __('data_quality.title') }}</h2><p class="text-sm text-slate-500">{{ __('data_quality.subtitle') }}</p></div>
                    <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index') }}">{{ __('common.actions.open') }}</a>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach(['open','critical','overdue','migration_batches'] as $metric)
                        <div class="rounded-xl border p-4"><div class="text-xs uppercase tracking-wide text-slate-500">{{ $metric }}</div><strong class="mt-2 block font-headline text-3xl text-primary">{{ $qualityMetrics[$metric] }}</strong></div>
                    @endforeach
                </div>
            </section>
        @endcan

        <div class="grid gap-6 xl:grid-cols-2">
            @if ($canSee($circulationPermissions))
                <section class="admin-card overflow-hidden p-0">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                        <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                            <span class="material-symbols-outlined text-[20px] text-error">running_with_errors</span>
                            {{ __('librarian.overview.overdue_queue') }}
                        </h2>
                        <a class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-[.12em] text-secondary hover:underline" href="{{ route('librarian.circulation') }}">
                            {{ __('common.actions.open') }}
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[620px]">
                            <thead>
                                <tr>
                                    <th>{{ __('librarian.reservations.reader') }}</th>
                                    <th>{{ __('librarian.reservations.record') }}</th>
                                    <th>{{ __('librarian.circulation.due_date') }}</th>
                                    <th>{{ __('librarian.overview.metrics.overdue') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($overdueLoans as $loan)
                                    <tr>
                                        <td>
                                            <strong class="block text-sm text-primary-container">{{ $loan->reader?->name ?? '—' }}</strong>
                                            <span class="block text-xs text-slate-500">{{ $loan->reader?->email ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="block max-w-xs truncate text-sm text-on-surface" title="{{ $loan->copy?->bibliographicRecord?->title }}">
                                                {{ $loan->copy?->bibliographicRecord?->title ?? '—' }}
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                {{ __('librarian.copies.fields.inventory_number') }}: {{ $loan->copy?->inventory_number ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap text-on-surface-variant">
                                            {{ $loan->due_at?->format('d.m.Y') ?? '—' }}
                                        </td>
                                        <td class="whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">
                                                {{ __('librarian.circulation.overdue_days', ['count' => $loan->overdueDays()]) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-14 text-center">
                                            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">check_circle</span>
                                            <span class="text-sm text-slate-500">{{ __('librarian.overview.empty_overdue') }}</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($canSee($reservationPermissions))
                <section class="admin-card overflow-hidden p-0">
                    <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                        <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                            <span class="material-symbols-outlined text-[20px] text-secondary">inventory</span>
                            {{ __('librarian.overview.ready_queue') }}
                        </h2>
                        <a class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-[.12em] text-secondary hover:underline" href="{{ route('librarian.reservations.index') }}">
                            {{ __('common.actions.open') }}
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </header>

                    <div class="overflow-x-auto">
                        <table class="admin-table min-w-[560px]">
                            <thead>
                                <tr>
                                    <th>{{ __('librarian.reservations.reader') }}</th>
                                    <th>{{ __('librarian.reservations.record') }}</th>
                                    <th>{{ __('librarian.reservations.expires_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($readyReservations as $reservation)
                                    <tr>
                                        <td>
                                            <strong class="block text-sm text-primary-container">{{ $reservation->reader?->name ?? '—' }}</strong>
                                            <span class="block text-xs text-slate-500">{{ $reservation->reader?->email ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="block max-w-xs truncate text-sm text-on-surface" title="{{ $reservation->bibliographicRecord?->title }}">
                                                {{ $reservation->bibliographicRecord?->title ?? '—' }}
                                            </span>
                                            <span class="block text-xs text-slate-500">
                                                {{ $reservation->bibliographicRecord?->primary_author ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap text-on-surface-variant">
                                            {{ $reservation->expires_at?->format('d.m.Y H:i') ?? '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-14 text-center">
                                            <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">inbox</span>
                                            <span class="text-sm text-slate-500">{{ __('librarian.overview.empty_ready') }}</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        @if ($canSee(array_merge($catalogEditPermissions, $cleanupPermissions)))
            <section class="admin-card mt-6 overflow-hidden p-0">
                <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                    <h2 class="flex items-center gap-2 font-headline text-xl text-primary-container">
                        <span class="material-symbols-outlined text-[20px] text-secondary">edit_note</span>
                        {{ __('librarian.overview.draft_queue') }}
                    </h2>
                    @can('data_cleanup.access')
                        <a class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-[.12em] text-secondary hover:underline" href="{{ route('librarian.data-cleanup') }}">
                            {{ __('librarian.nav.data_cleanup') }}
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    @endcan
                </header>

                @forelse ($draftRecords as $record)
                    @php
                        $missingFields = $record->missingRequiredFields();
                        $missingLabels = array_map(
                            static fn (string $field): string => __('librarian.catalog.fields.'.$field),
                            $missingFields,
                        );
                    @endphp
                    <article class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @can('catalog.edit_record')
                                    <a class="font-headline text-lg leading-tight text-primary-container hover:text-secondary" href="{{ route('librarian.catalog.edit', $record) }}">
                                        {{ $record->title }}
                                    </a>
                                @else
                                    <span class="font-headline text-lg leading-tight text-primary-container">{{ $record->title }}</span>
                                @endcan
                                <x-admin.status-badge status="pending" :label="__('librarian.catalog.draft_badge')" />
                            </div>
                            <p class="mt-1 text-xs leading-5 text-on-surface-variant">
                                {{ $record->primary_author ?? '—' }} · {{ $record->publication_year ?? '—' }}
                            </p>
                            @if ($missingLabels !== [])
                                <p class="mt-2 text-xs leading-5 text-red-700">
                                    {{ __('librarian.catalog.draft_notice', ['fields' => implode(', ', $missingLabels)]) }}
                                </p>
                            @endif
                        </div>
                        @can('catalog.edit_record')
                            <a class="admin-btn admin-btn-secondary shrink-0 self-start" href="{{ route('librarian.catalog.edit', $record) }}">
                                <span class="material-symbols-outlined text-[19px]">edit</span>
                                {{ __('librarian.data_cleanup.fix_record') }}
                            </a>
                        @endcan
                    </article>
                @empty
                    <div class="py-14 text-center">
                        <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">task_alt</span>
                        <span class="text-sm text-slate-500">{{ __('librarian.overview.empty_drafts') }}</span>
                    </div>
                @endforelse
            </section>
        @endif

        <p class="mt-6 flex items-start gap-2 text-xs leading-5 text-outline">
            <span class="material-symbols-outlined text-[16px]">info</span>
            {{ __('librarian.overview.data_source_note') }}
        </p>
    </div>
@endsection
