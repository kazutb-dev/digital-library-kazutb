@extends('layouts.librarian')

@section('title', __('librarian.circulation.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $loanStatusLabels = [
            'active' => __('librarian.copies.statuses.issued'),
            'overdue' => __('librarian.copies.statuses.overdue'),
            'returned' => __('librarian.copies.events.returned'),
            'lost' => __('librarian.copies.statuses.lost'),
        ];
        $loanStatusTone = [
            'active' => 'active',
            'overdue' => 'expired',
            'returned' => 'inactive',
            'lost' => 'critical',
        ];

        $metrics = [
            [
                'key' => 'active',
                'icon' => 'book_2',
                'value' => $activeCount,
                'alert' => false,
            ],
            [
                'key' => 'overdue',
                'icon' => 'running_with_errors',
                'value' => $overdueCount,
                'alert' => $overdueCount > 0,
            ],
            [
                'key' => 'issued_today',
                'icon' => 'outbox',
                'value' => $issuedToday,
                'alert' => false,
            ],
            [
                'key' => 'returned_today',
                'icon' => 'inbox',
                'value' => $returnedToday,
                'alert' => false,
            ],
            [
                'key' => 'issued_week',
                'icon' => 'date_range',
                'value' => $issuedWeek,
                'alert' => false,
            ],
        ];
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.circulation.eyebrow')"
        :title="__('librarian.circulation.title')"
        :subtitle="__('librarian.circulation.subtitle')"
    >
        @can('circulation.issue')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.circulation.issue') }}">
                <span class="material-symbols-outlined text-[19px]">outbox</span>
                {{ __('librarian.circulation.issue_title') }}
            </a>
        @endcan
        @can('circulation.return')
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.circulation.return') }}">
                <span class="material-symbols-outlined text-[19px]">inbox</span>
                {{ __('librarian.circulation.return_title') }}
            </a>
        @endcan
    </x-admin.page-header>

    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('librarian.circulation.title') }}">
        @foreach ($metrics as $metric)
            <div class="admin-card p-5 {{ $metric['alert'] ? 'border border-red-200 bg-red-50' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <p class="admin-label mb-0 {{ $metric['alert'] ? 'text-red-700' : '' }}">
                        {{ __('librarian.circulation.metrics.'.$metric['key']) }}
                    </p>
                    <span class="material-symbols-outlined text-[20px] {{ $metric['alert'] ? 'text-red-500' : 'text-slate-300' }}">{{ $metric['icon'] }}</span>
                </div>
                <p class="mt-3 font-headline text-4xl leading-none {{ $metric['alert'] ? 'text-red-700' : 'text-primary' }}">
                    {{ number_format((int) $metric['value'], 0, ',', ' ') }}
                </p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-12">
        <section class="admin-card overflow-hidden p-0 xl:col-span-7">
            <header class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-4">
                <h2 class="font-headline text-xl text-primary">{{ __('librarian.circulation.overdue_list') }}</h2>
                @if ($overdueCount > 0)
                    <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">
                        {{ number_format((int) $overdueCount, 0, ',', ' ') }}
                    </span>
                @endif
            </header>

            <div class="overflow-x-auto">
                <table class="admin-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>{{ __('librarian.reservations.reader') }}</th>
                            <th>{{ __('librarian.reservations.record') }}</th>
                            <th>{{ __('librarian.circulation.due_date') }}</th>
                            <th>{{ __('librarian.circulation.metrics.overdue') }}</th>
                            @can('circulation.renew')
                                <th class="text-right">{{ __('common.fields.actions') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($overdueLoans as $loan)
                            @php
                                $overdueDays = $loan->overdueDays();
                                $copy = $loan->copy;
                                $record = $copy?->bibliographicRecord;
                            @endphp
                            <tr>
                                <td>
                                    @if ($loan->reader)
                                        <a
                                            class="font-semibold text-primary hover:text-secondary"
                                            href="{{ route('librarian.circulation.issue', ['reader' => $loan->reader->getKey()]) }}"
                                        >{{ $loan->reader->name }}</a>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $loan->reader->email ?? '—' }}</span>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="block max-w-[22rem] truncate font-medium text-primary" title="{{ $record?->title }}">{{ $record?->title ?? '—' }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">
                                        {{ $record?->primary_author ?? '—' }}
                                        @if ($copy?->inventory_number)
                                            · {{ $copy->inventory_number }}
                                        @endif
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-slate-600">{{ $loan->due_at?->format('d.m.Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">
                                        {{ __('librarian.circulation.overdue_days', ['count' => $overdueDays]) }}
                                    </span>
                                </td>
                                @can('circulation.renew')
                                    <td>
                                        <form method="POST" action="{{ route('librarian.circulation.renew', $loan) }}" class="flex justify-end">
                                            @csrf
                                            <button class="admin-btn admin-btn-secondary px-3 py-1.5 text-xs" type="submit">
                                                <span class="material-symbols-outlined text-[17px]">more_time</span>
                                                {{ __('librarian.circulation.renew') }}
                                            </button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()?->can('circulation.renew') ? 5 : 4 }}" class="py-16 text-center">
                                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">event_available</span>
                                    <span class="text-sm text-slate-500">{{ __('librarian.circulation.no_overdue') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-card overflow-hidden p-0 xl:col-span-5">
            <header class="border-b border-slate-100 px-6 py-4">
                <h2 class="font-headline text-xl text-primary">{{ __('librarian.circulation.recent_activity') }}</h2>
            </header>

            <div class="overflow-x-auto">
                <table class="admin-table min-w-[540px]">
                    <thead>
                        <tr>
                            <th>{{ __('librarian.reservations.record') }}</th>
                            <th>{{ __('librarian.reservations.reader') }}</th>
                            <th>{{ __('common.fields.status') }}</th>
                            <th class="whitespace-nowrap">{{ __('librarian.reports.columns.issued') }}</th>
                            <th class="whitespace-nowrap">{{ __('librarian.reports.columns.returned') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentLoans as $loan)
                            @php
                                $status = (string) $loan->status;
                                $record = $loan->copy?->bibliographicRecord;
                            @endphp
                            <tr>
                                <td>
                                    <span class="block max-w-[16rem] truncate font-medium text-primary" title="{{ $record?->title }}">{{ $record?->title ?? '—' }}</span>
                                    @if ($loan->copy?->inventory_number)
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $loan->copy->inventory_number }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($loan->reader)
                                        <a
                                            class="text-primary hover:text-secondary"
                                            href="{{ route('librarian.circulation.issue', ['reader' => $loan->reader->getKey()]) }}"
                                        >{{ $loan->reader->name }}</a>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-admin.status-badge
                                        :status="$loanStatusTone[$status] ?? 'unknown'"
                                        :label="$loanStatusLabels[$status] ?? $status"
                                    />
                                </td>
                                <td class="whitespace-nowrap text-slate-600">{{ $loan->issued_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-slate-600">{{ $loan->returned_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-16 text-center">
                                    <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">history</span>
                                    <span class="text-sm text-slate-500">{{ __('librarian.circulation.no_activity') }}</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
