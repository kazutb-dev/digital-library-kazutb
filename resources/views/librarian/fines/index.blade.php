@extends('layouts.librarian')

@section('title', __('librarian.fines.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $statusTone = [
            'pending' => 'pending',
            'paid' => 'active',
            'waived' => 'inactive',
        ];

        $reasonTone = [
            'overdue' => 'expiring_soon',
            'lost' => 'critical',
            'damaged' => 'high',
        ];

        $canManageFines = auth()->user()?->can('fines.manage') ?? false;
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.fines.eyebrow')"
        :title="__('librarian.fines.title')"
        :subtitle="__('librarian.fines.subtitle')"
    />

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="admin-card flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                <span class="material-symbols-outlined">payments</span>
            </span>
            <div class="min-w-0">
                <p class="admin-label mb-1">{{ __('librarian.fines.pending_total') }}</p>
                <p class="font-headline text-3xl leading-none tracking-tight text-primary">
                    {{ number_format($pendingTotal, 0, ',', ' ') }} ₸
                </p>
            </div>
        </div>

        <div class="admin-card flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                <span class="material-symbols-outlined">receipt_long</span>
            </span>
            <div class="min-w-0">
                <p class="admin-label mb-1">{{ __('librarian.fines.pending_count') }}</p>
                <p class="font-headline text-3xl leading-none tracking-tight text-primary">
                    {{ number_format($pendingCount, 0, ',', ' ') }}
                </p>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('librarian.fines.index') }}" class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label>
                <span class="admin-label">{{ __('librarian.fines.filters.search') }}</span>
                <span class="relative block">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-slate-400">search</span>
                    <input
                        class="admin-input pl-10"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="{{ __('librarian.fines.filters.search') }}"
                    >
                </span>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.fines.filters.status') }}</span>
                <select class="admin-input" name="status">
                    <option value="">{{ __('common.filters.all_statuses') }}</option>
                    @foreach (\App\Models\Catalog\Fine::STATUSES as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ __('librarian.fines.statuses.'.$status) }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="admin-label">{{ __('librarian.fines.filters.reason') }}</span>
                <select class="admin-input" name="reason">
                    <option value="">{{ __('common.filters.all') }}</option>
                    @foreach (\App\Models\Catalog\Fine::REASONS as $reason)
                        <option value="{{ $reason }}" @selected(($filters['reason'] ?? '') === $reason)>
                            {{ __('librarian.fines.reasons.'.$reason) }}
                        </option>
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
                    href="{{ route('librarian.fines.index') }}"
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
            <table class="admin-table min-w-[1280px]">
                <thead>
                    <tr>
                        <th>{{ __('librarian.reservations.reader') }}</th>
                        <th class="text-right">{{ __('librarian.fines.amount') }}</th>
                        <th>{{ __('common.fields.reason') }}</th>
                        <th>{{ __('librarian.reservations.record') }}</th>
                        <th>{{ __('librarian.fines.charged_at') }}</th>
                        <th>{{ __('common.fields.status') }}</th>
                        <th>{{ __('common.fields.notes') }}</th>
                        <th class="text-right">{{ __('common.fields.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fines as $fine)
                        @php
                            $reader = $fine->reader;
                            $ticket = $reader?->readerProfile?->ticket_number;
                            $record = $fine->loan?->copy?->bibliographicRecord ?? $fine->copy?->bibliographicRecord;
                            $isPending = $fine->status === 'pending';
                        @endphp
                        <tr>
                            <td>
                                <div class="flex min-w-52 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-headline text-lg font-semibold text-primary">
                                        {{ mb_strtoupper(mb_substr((string) ($reader?->name ?: '—'), 0, 1)) }}
                                    </span>
                                    <span class="min-w-0">
                                        <strong class="block truncate text-sm text-primary">{{ $reader?->name ?? '—' }}</strong>
                                        <span class="block truncate font-mono text-xs text-slate-500" title="{{ __('librarian.circulation.ticket') }}">
                                            {{ $ticket ?: '—' }}
                                        </span>
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <strong class="font-headline text-base font-bold {{ $isPending ? 'text-red-700' : 'text-primary' }}">
                                    {{ number_format((float) $fine->amount, 0, ',', ' ') }} ₸
                                </strong>
                            </td>
                            <td class="whitespace-nowrap">
                                <x-admin.status-badge
                                    :status="$reasonTone[$fine->reason] ?? $fine->reason"
                                    :label="__('librarian.fines.reasons.'.$fine->reason)"
                                />
                            </td>
                            <td>
                                <div class="min-w-56 max-w-md">
                                    @if ($record)
                                        @can('catalog.edit_record')
                                            <a class="block text-sm font-semibold text-primary hover:text-secondary" href="{{ route('librarian.catalog.edit', $record) }}">
                                                {{ $record->title }}
                                            </a>
                                        @else
                                            <span class="block text-sm font-semibold text-primary">{{ $record->title }}</span>
                                        @endcan
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $record->primary_author ?: '—' }}</span>
                                    @else
                                        <span class="text-sm text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-slate-600">
                                {{ $fine->charged_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap">
                                <x-admin.status-badge
                                    :status="$statusTone[$fine->status] ?? $fine->status"
                                    :label="__('librarian.fines.statuses.'.$fine->status)"
                                />
                                @unless ($isPending)
                                    <span class="mt-1.5 block text-xs text-slate-500">
                                        {{ __('librarian.fines.resolved_at') }}: {{ $fine->resolved_at?->format('d.m.Y H:i') ?? '—' }}
                                    </span>
                                    <span class="block text-xs text-slate-400">{{ $fine->resolvedBy?->name ?? '—' }}</span>
                                @endunless
                            </td>
                            <td>
                                @if (filled($fine->notes))
                                    <p class="line-clamp-2 max-w-xs text-xs leading-5 text-slate-600" title="{{ $fine->notes }}">{{ $fine->notes }}</p>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex min-w-56 flex-col items-end gap-2">
                                    @if ($isPending && $canManageFines)
                                        <form method="POST" action="{{ route('librarian.fines.resolve', $fine) }}" class="w-full">
                                            @csrf
                                            <input type="hidden" name="action" value="paid">
                                            <button class="admin-btn admin-btn-primary w-full px-3 py-1.5 text-xs" type="submit">
                                                <span class="material-symbols-outlined text-[17px]">paid</span>
                                                {{ __('librarian.fines.mark_paid') }}
                                            </button>
                                        </form>

                                        <details class="w-full">
                                            <summary class="admin-btn admin-btn-danger w-full cursor-pointer px-3 py-1.5 text-xs">
                                                <span class="material-symbols-outlined text-[17px]">money_off</span>
                                                {{ __('librarian.fines.waive') }}
                                            </summary>
                                            <form method="POST" action="{{ route('librarian.fines.resolve', $fine) }}" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-left">
                                                @csrf
                                                <input type="hidden" name="action" value="waived">
                                                <p class="mb-2 text-xs leading-5 text-slate-500">{{ __('librarian.fines.waive_hint') }}</p>
                                                <label class="block">
                                                    <span class="admin-label">{{ __('librarian.fines.waive_reason') }}</span>
                                                    <textarea
                                                        class="admin-input"
                                                        name="reason"
                                                        rows="3"
                                                        minlength="5"
                                                        maxlength="1000"
                                                        required
                                                    >{{ old('reason') }}</textarea>
                                                </label>
                                                @error('reason')
                                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                                @enderror
                                                <button class="admin-btn admin-btn-danger mt-2 w-full px-3 py-1.5 text-xs" type="submit">
                                                    {{ __('common.actions.confirm') }}
                                                </button>
                                            </form>
                                        </details>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">payments</span>
                                <span class="text-sm text-slate-500">{{ __('librarian.fines.empty') }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.pagination :paginator="$fines" />
    </section>
@endsection
