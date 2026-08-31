@extends('layouts.librarian')

@section('title', __('librarian.copies.card').' '.$copy->inventory_number.' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />

    @php
        $record = $copy->bibliographicRecord;

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

        $eventIcon = static fn (string $event): string => match ($event) {
            'created' => 'add_circle',
            'updated' => 'edit_note',
            'location_changed', 'physical_location_corrected' => 'location_on',
            'physical_presence_confirmed' => 'where_to_vote',
            'transfer_received' => 'move_down',
            'issued' => 'logout',
            'returned' => 'login',
            'reserved' => 'bookmark',
            'status_change' => 'swap_horiz',
            'repair' => 'build',
            'repair_returned' => 'construction',
            'write_off' => 'inventory_2',
            'lost' => 'search_off',
            'incident' => 'report',
            default => 'history',
        };

        // History detail payloads are free-form JSON; render them as readable
        // pairs and translate known status codes instead of dumping raw values.
        $detailLabel = static function (string $key): string {
            return \Illuminate\Support\Facades\Lang::has('librarian.copies.fields.'.$key)
                ? __('librarian.copies.fields.'.$key)
                : $key;
        };

        $detailValue = static function ($value): string {
            if (is_bool($value)) {
                return $value ? __('common.boolean.yes') : __('common.boolean.no');
            }
            if (is_array($value)) {
                return implode(', ', array_map(static fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value));
            }
            if ($value === null || $value === '') {
                return '—';
            }
            $text = (string) $value;

            return \Illuminate\Support\Facades\Lang::has('librarian.copies.statuses.'.$text)
                ? __('librarian.copies.statuses.'.$text)
                : $text;
        };

        $activeLoan = $copy->activeLoan;
        $activeReservation = $copy->activeReservation;
    @endphp

    <x-admin.page-header
        :eyebrow="__('librarian.copies.card').' · '.$copy->inventory_number"
        :title="$record?->title ?? $copy->inventory_number"
        :subtitle="$record?->primary_author ?: null"
    >
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.index') }}">
            <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            {{ __('common.actions.back') }}
        </a>
        @if($copy->barcode)
            @can('barcodes.print')
                <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.label', $copy) }}" target="_blank" rel="noopener">
                    <span class="material-symbols-outlined text-[19px]">print</span>
                    {{ __('librarian.copies.print_label') }}
                </a>
            @endcan
        @endif
        @can('copies.edit')
            <a class="admin-btn admin-btn-primary" href="{{ route('librarian.copies.edit', $copy) }}">
                <span class="material-symbols-outlined text-[19px]">edit</span>
                {{ __('common.actions.edit') }}
            </a>
        @endcan
    </x-admin.page-header>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-admin.status-badge :status="$statusTone($copy->status)" :label="__('librarian.copies.statuses.'.$copy->status)" />
        <x-admin.status-badge :status="$conditionTone($copy->condition)" :label="__('librarian.copies.conditions.'.$copy->condition)" />
        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
            {{ __('copy_lifecycle.inventory') }}: {{ __('copy_lifecycle.inventory_statuses.'.($copy->inventory_status ?: \App\Models\Catalog\BookCopy::separatedStateFor($copy->status)[0])) }}
        </span>
        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
            {{ __('copy_lifecycle.circulation') }}: {{ __('copy_lifecycle.circulation_statuses.'.($copy->circulation_status ?: \App\Models\Catalog\BookCopy::separatedStateFor($copy->status)[1])) }}
        </span>
        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
            {{ __('librarian.copies.fields.access_restriction') }}: {{ __('librarian.copies.access_restrictions.'.$copy->access_restriction) }}
        </span>
        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">
            {{ __('librarian.copies.fields.issue_count') }}: {{ number_format((int) $copy->issue_count, 0, ',', ' ') }}
        </span>
    </div>

    <section class="admin-card mb-6 border-l-4 {{ $qualityIssues->isEmpty() ? 'border-l-emerald-500' : 'border-l-amber-500' }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="font-headline text-2xl text-primary">{{ __('data_quality.title') }}</h2><p class="mt-1 text-sm text-slate-600">{{ $qualityIssues->isEmpty() ? __('data_quality.record.clean') : trans_choice('data_quality.record.issue_count', $qualityIssues->count(), ['count' => $qualityIssues->count()]) }}</p></div>
            <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index', ['q' => $copy->inventory_number]) }}">{{ __('data_quality.record.open_issues') }}</a>
        </div>
        @if($qualityIssues->isNotEmpty())<div class="mt-4 flex flex-wrap gap-2">@foreach($qualityIssues as $qualityIssue)<a class="rounded-lg border px-3 py-2 text-sm hover:border-secondary" href="{{ route('librarian.data-quality.issues.show', $qualityIssue) }}">{{ __('data_quality.rules.'.$qualityIssue->rule_code) }} · {{ __('data_quality.severity.'.$qualityIssue->severity) }}</a>@endforeach</div>@endif
    </section>

    <section class="admin-card mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="admin-label">{{ __('librarian.copies.marking.title') }}</p>
                <h2 class="mt-1 font-headline text-2xl text-primary">{{ __('librarian.copies.marking.states.'.$markingState) }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $copy->barcode ? $copy->barcode : __('librarian.copies.marking.not_assigned') }}</p>
            </div>
            @if($copy->barcode)
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 font-mono text-sm font-semibold text-emerald-800">{{ $copy->barcode }}</span>
            @endif
        </div>

        @can('copies.edit')
            @if(blank($copy->barcode))
                <form class="mt-5 grid gap-4 border-t border-slate-100 pt-5 lg:grid-cols-[180px_minmax(0,1fr)_auto]" method="POST" action="{{ route('librarian.copies.barcode.assign', $copy) }}">
                    @csrf
                    <label><span class="admin-label">{{ __('librarian.copies.marking.assignment_mode') }}</span><select class="admin-input" name="mode" data-barcode-mode><option value="generate">{{ __('librarian.copies.marking.generate') }}</option><option value="existing">{{ __('librarian.copies.marking.scan_existing') }}</option></select></label>
                    <label><span class="admin-label">{{ __('librarian.copies.fields.barcode') }}</span><input class="admin-input font-mono" name="barcode" maxlength="64" autocomplete="off" placeholder="{{ __('librarian.copies.marking.scan_placeholder') }}" data-barcode-value disabled>@error('barcode')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <div class="flex flex-col justify-end gap-2"><label><span class="admin-label">{{ __('librarian.copies.marking.confirm_inventory') }}</span><input class="admin-input font-mono" name="inventory_number_confirmation" autocomplete="off" required></label><label class="flex items-start gap-2 text-xs text-slate-600"><input class="mt-0.5" type="checkbox" name="confirmed" value="1" required><span>{{ __('librarian.copies.marking.assignment_confirmation', ['inventory' => $copy->inventory_number]) }}</span></label><button class="admin-btn admin-btn-primary" type="submit">{{ __('librarian.copies.marking.assign') }}</button></div>
                </form>
            @else
                <div class="mt-5 grid gap-4 border-t border-slate-100 pt-5 lg:grid-cols-2">
                    @can('barcodes.print')
                        <div><p class="text-sm text-slate-600">{{ __('librarian.copies.marking.print_hint') }}</p><div class="mt-3 flex flex-wrap gap-2"><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.copies.label', $copy) }}" target="_blank" rel="noopener">{{ __('librarian.copies.print_label') }}</a><form method="POST" action="{{ route('librarian.copies.barcode.printed', $copy) }}">@csrf<button class="admin-btn admin-btn-secondary" type="submit">{{ __('librarian.copies.marking.mark_printed') }}</button></form></div></div>
                    @endcan
                    <form method="POST" action="{{ route('librarian.copies.barcode.confirm', $copy) }}">
                        @csrf
                        <label><span class="admin-label">{{ __('librarian.copies.marking.confirm_scan') }}</span><input class="admin-input font-mono" name="scanned_barcode" maxlength="64" autocomplete="off" required autofocus placeholder="{{ __('librarian.copies.marking.scan_placeholder') }}">@error('scanned_barcode')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                        <button class="admin-btn admin-btn-primary mt-3" type="submit">{{ __('librarian.copies.marking.confirm') }}</button>
                    </form>
                </div>
            @endif
        @endcan
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">inventory_2</span>
                    {{ __('librarian.copies.sections.identification') }}
                </h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.inventory_number') }}</dt>
                        <dd class="font-mono text-base font-bold text-primary">{{ $copy->inventory_number }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.barcode') }}</dt>
                        <dd class="font-mono text-base text-primary">{{ $copy->barcode ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.accounting_type') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->accounting_type ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.ksu_number') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->ksu_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.storage_sigla') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->storage_sigla ?: '—' }}</dd>
                    </div>
                    <div><dt class="admin-label">{{ __('copy_registry.fields.service_point') }}</dt><dd class="text-sm text-slate-700">{{ $copy->service_point_code ?: '—' }}</dd></div>
                    <div><dt class="admin-label">{{ __('copy_registry.fields.shelf_index') }}</dt><dd class="text-sm text-slate-700">{{ $copy->shelf_index ?: '—' }}</dd></div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.room') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->room ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.section') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->section ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.shelf_location') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->shelf_location ?: '—' }}</dd>
                    </div>
                    <div><dt class="admin-label">{{ __('copy_registry.fields.service_point') }}</dt><dd class="text-sm text-slate-700">{{ $copy->service_point_code ?: '—' }}</dd></div>
                    <div><dt class="admin-label">{{ __('copy_registry.fields.shelf_index') }}</dt><dd class="text-sm text-slate-700">{{ $copy->shelf_index ?: '—' }}</dd></div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.branch') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->branch?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.fund') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->fund?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.access_restriction') }}</dt>
                        <dd class="text-sm text-slate-700">{{ __('librarian.copies.access_restrictions.'.$copy->access_restriction) }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.record') }}</dt>
                        <dd class="text-sm text-slate-700">
                            @if ($record)
                                @can('catalog.edit_record')
                                    <a class="font-semibold text-secondary hover:underline" href="{{ route('librarian.catalog.edit', $record) }}">{{ $record->title }}</a>
                                @else
                                    {{ $record->title }}
                                @endcan
                                @if ($record->publication_year)
                                    <span class="text-slate-500">({{ $record->publication_year }})</span>
                                @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">ISBN</dt>
                        <dd class="font-mono text-sm text-slate-700">{{ $record?->isbn ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.catalog.fields.primary_author') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $record?->primary_author ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">location_on</span>
                    {{ __('librarian.copies.sections.location') }}
                </h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.branch') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->branch?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.branch_address') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->branch?->address ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.storage_sigla') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->storage_sigla ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.shelf_location') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->shelf_location ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.fund') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->fund?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">checklist</span>
                    {{ __('librarian.copies.sections.copy_status') }}
                </h2>
                <div class="flex flex-wrap items-center gap-3">
                    <x-admin.status-badge :status="$statusTone($copy->status)" :label="__('librarian.copies.statuses.'.$copy->status)" />
                    <span class="text-sm text-slate-600">{{ __('librarian.copies.unified_placement_status') }}</span>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">history</span>
                    {{ __('librarian.copies.sections.usage_history') }}
                </h2>

                <h3 class="admin-label mb-3">{{ __('librarian.copies.all_loans') }}</h3>
                <div class="mb-6 overflow-x-auto">
                    <table class="admin-table min-w-full">
                        <thead>
                            <tr>
                                <th>{{ __('librarian.reservations.reader') }}</th>
                                <th>{{ __('librarian.copies.fields.issued_at') }}</th>
                                <th>{{ __('librarian.copies.fields.due_at') }}</th>
                                <th>{{ __('librarian.copies.fields.returned_at') }}</th>
                                <th>{{ __('common.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($copy->loans as $loan)
                                <tr>
                                    <td>{{ $loan->reader?->name ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $loan->issued_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $loan->due_at?->format('d.m.Y') ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $loan->returned_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td>{{ \Illuminate\Support\Facades\Lang::has('librarian.circulation.loan_statuses.'.$loan->status) ? __('librarian.circulation.loan_statuses.'.$loan->status) : $loan->status }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h3 class="admin-label mb-3">{{ __('librarian.copies.all_reservations') }}</h3>
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-full">
                        <thead>
                            <tr>
                                <th>{{ __('librarian.reservations.reader') }}</th>
                                <th>{{ __('common.fields.created_at') }}</th>
                                <th>{{ __('librarian.reservations.expires_at') }}</th>
                                <th>{{ __('common.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($copy->reservations as $reservation)
                                <tr>
                                    <td>{{ $reservation->reader?->name ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $reservation->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="tabular-nums">{{ $reservation->expires_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td>{{ __('librarian.reservations.statuses.'.$reservation->status) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                @php
                    $lastLoan = $copy->loans->first();
                    $overdueDays = $activeLoan?->overdueDays() ?? 0;
                @endphp
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">event</span>
                    {{ __('librarian.copies.sections.terms') }}
                </h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.issued_at') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $lastLoan?->issued_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.due_at') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $activeLoan?->due_at?->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.overdue_state') }}</dt>
                        <dd class="text-sm {{ $overdueDays > 0 ? 'font-semibold text-red-700' : 'text-slate-700' }}">
                            {{ $overdueDays > 0 ? __('librarian.circulation.overdue_days', ['count' => $overdueDays]) : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.access_restriction') }}</dt>
                        <dd class="text-sm text-slate-700">{{ __('librarian.copies.access_restrictions.'.$copy->access_restriction) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">health_and_safety</span>
                    {{ __('librarian.copies.sections.physical_condition') }}
                </h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.condition') }}</dt>
                        <dd><x-admin.status-badge :status="$conditionTone($copy->condition)" :label="__('librarian.copies.conditions.'.$copy->condition)" /></dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.repair_history') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->history->where('event_type', 'repair')->count() }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="admin-label">{{ __('librarian.copies.fields.defect_description') }}</dt>
                        <dd class="whitespace-pre-line rounded-lg bg-surface-container-low px-3 py-2 text-sm text-slate-700">{{ $copy->defect_description ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">receipt_long</span>
                    {{ __('librarian.copies.sections.accounting') }}
                </h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.price') }}</dt>
                        <dd class="text-sm font-semibold text-primary">
                            {{ $copy->price !== null ? number_format((float) $copy->price, 0, ',', ' ').' ₸' : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.acquisition_source') }}</dt>
                        <dd class="text-sm text-slate-700">
                            {{ $copy->acquisition_source && trans()->has('librarian.copies.acquisition_sources.'.$copy->acquisition_source)
                                ? __('librarian.copies.acquisition_sources.'.$copy->acquisition_source)
                                : ($copy->acquisition_source ?: '—') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.supplier_name') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->supplier_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.acquisition_date') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->acquisition_date?->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.registration_date') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $copy->registration_date?->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.condition') }}</dt>
                        <dd><x-admin.status-badge :status="$conditionTone($copy->condition)" :label="__('librarian.copies.conditions.'.$copy->condition)" /></dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.copies.fields.issue_count') }}</dt>
                        <dd class="text-sm text-slate-700 tabular-nums">{{ number_format((int) $copy->issue_count, 0, ',', ' ') }}</dd>
                    </div>
                    @if($copy->writeoff_date || $copy->writeoff_act || $copy->writeoff_reason)
                        <div><dt class="admin-label">{{ __('copy_lifecycle.fields.writeoff_date') }}</dt><dd class="text-sm text-slate-700">{{ $copy->writeoff_date?->format('d.m.Y') ?? '—' }}</dd></div>
                        <div><dt class="admin-label">{{ __('copy_lifecycle.fields.writeoff_act') }}</dt><dd class="text-sm text-slate-700">{{ $copy->writeoff_act ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="admin-label">{{ __('copy_lifecycle.fields.writeoff_reason') }}</dt><dd class="whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $copy->writeoff_reason ?: '—' }}</dd></div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="admin-label">{{ __('librarian.copies.fields.balance_status') }}</dt>
                        <dd class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('librarian.copies.not_recorded') }}</dd>
                    </div>
                </dl>
            </section>

            @canany(['legacy_recovery.review', 'legacy_recovery.view'])
                <section class="admin-card">
                    <h2 class="mb-2 flex items-center gap-2 font-headline text-2xl text-primary"><span class="material-symbols-outlined text-secondary">database</span>{{ __('copy_lifecycle.legacy.title') }}</h2>
                    <p class="mb-5 text-xs text-slate-500">{{ __('copy_lifecycle.legacy.description') }}</p>
                    <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                        @foreach(['legacy_inv_id','legacy_doc_id','legacy_inventory_number','sigla_code','legacy_sigla_id','local_library_code','fund_raw','price_raw','accounting_mode_raw','legacy_state_raw','legacy_state_label','legacy_notes'] as $legacyField)
                            <div @class(['sm:col-span-2' => in_array($legacyField, ['legacy_notes'], true)])><dt class="admin-label">{{ __('copy_lifecycle.legacy.fields.'.$legacyField) }}</dt><dd class="break-words text-sm text-slate-700">{{ $copy->{$legacyField} !== null && $copy->{$legacyField} !== '' ? $copy->{$legacyField} : '—' }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endcanany

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">menu_book</span>
                    {{ __('librarian.copies.sections.bibliography') }}
                </h2>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="admin-label">{{ __('librarian.catalog.fields.title') }}</dt>
                        <dd class="text-sm font-semibold text-primary">{{ $record?->title ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">ISBN</dt>
                        <dd class="font-mono text-sm text-slate-700">{{ $record?->isbn ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.catalog.fields.publisher') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $record?->publisher ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.catalog.fields.publication_year') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $record?->publication_year ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.catalog.fields.language') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $record?->language ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="admin-label">{{ __('librarian.catalog.fields.category') }}</dt>
                        <dd class="text-sm text-slate-700">{{ $record?->category ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="admin-label">{{ __('librarian.copies.fields.print_run') }}</dt>
                        <dd class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('librarian.copies.not_recorded') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">history</span>
                    {{ __('librarian.copies.movement_log') }}
                </h2>

                @forelse ($copy->history as $entry)
                    <div class="relative flex gap-4 pb-6 last:pb-0">
                        <div class="flex flex-col items-center">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-surface-container-low text-secondary">
                                <span class="material-symbols-outlined text-[18px]">{{ $eventIcon($entry->event_type) }}</span>
                            </span>
                            @unless ($loop->last)
                                <span class="mt-1 w-px flex-1 bg-slate-200"></span>
                            @endunless
                        </div>
                        <div class="min-w-0 flex-1 pb-1">
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <strong class="text-sm text-primary">
                                    {{ \Illuminate\Support\Facades\Lang::has('librarian.copies.events.'.$entry->event_type) ? __('librarian.copies.events.'.$entry->event_type) : $entry->event_type }}
                                </strong>
                                <span class="text-xs text-slate-500 tabular-nums">{{ $entry->occurred_at?->format('d.m.Y H:i') ?? '—' }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span>{{ __('common.fields.updated_by') }}: {{ $entry->actor?->name ?? '—' }}</span>
                                <span>{{ __('librarian.reservations.reader') }}: {{ $entry->user?->name ?? '—' }}</span>
                            </div>
                            @if (! empty($entry->details))
                                <dl class="mt-2 grid gap-x-6 gap-y-1 rounded-lg bg-surface-container-low px-3 py-2 text-xs sm:grid-cols-2">
                                    @foreach ($entry->details as $key => $value)
                                        <div class="flex gap-2">
                                            <dt class="shrink-0 font-semibold text-slate-500">{{ $detailLabel((string) $key) }}:</dt>
                                            <dd class="min-w-0 break-words text-slate-700">{{ $detailValue($value) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center">
                        <span class="material-symbols-outlined mb-2 block text-5xl text-slate-300">history_toggle_off</span>
                        <span class="text-sm text-slate-500">{{ __('librarian.copies.history_empty') }}</span>
                    </div>
                @endforelse
            </section>
        </div>

        <div class="space-y-6">
            <section class="admin-card">
                <h2 class="mb-5 flex items-center gap-2 font-headline text-2xl text-primary">
                    <span class="material-symbols-outlined text-secondary">sync_alt</span>
                    {{ __('librarian.copies.current_loan') }}
                </h2>

                @if ($activeLoan)
                    @php $overdueDays = $activeLoan->overdueDays(); @endphp
                    <dl class="space-y-4">
                        <div>
                            <dt class="admin-label">{{ __('librarian.reservations.reader') }}</dt>
                            <dd class="text-sm font-semibold text-primary">{{ $activeLoan->reader?->name ?? '—' }}</dd>
                            @if ($activeLoan->reader?->email)
                                <dd class="text-xs text-slate-500">{{ $activeLoan->reader->email }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="admin-label">{{ __('librarian.circulation.due_date') }}</dt>
                            <dd class="text-sm text-slate-700 tabular-nums">{{ $activeLoan->due_at?->format('d.m.Y') ?? '—' }}</dd>
                        </div>
                        @if ($overdueDays > 0)
                            <div class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-800">
                                <span class="material-symbols-outlined text-[19px]">running_with_errors</span>
                                <span>{{ __('librarian.circulation.overdue_days', ['count' => $overdueDays]) }}</span>
                            </div>
                        @endif
                    </dl>
                @elseif ($activeReservation)
                    <dl class="space-y-4">
                        <div>
                            <dt class="admin-label">{{ __('librarian.copies.reserved_for') }}</dt>
                            <dd class="text-sm font-semibold text-primary">{{ $activeReservation->reader?->name ?? '—' }}</dd>
                            @if ($activeReservation->reader?->email)
                                <dd class="text-xs text-slate-500">{{ $activeReservation->reader->email }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="admin-label">{{ __('common.fields.status') }}</dt>
                            <dd><x-admin.status-badge status="pending" :label="__('librarian.reservations.statuses.'.$activeReservation->status)" /></dd>
                        </div>
                        @if ($activeReservation->expires_at)
                            <div>
                                <dt class="admin-label">{{ __('librarian.reservations.expires_at') }}</dt>
                                <dd class="text-sm text-slate-700 tabular-nums">{{ $activeReservation->expires_at->format('d.m.Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                @else
                    <div class="py-8 text-center">
                        <span class="material-symbols-outlined mb-2 block text-4xl text-slate-300">check_circle</span>
                        <span class="text-sm text-slate-500">{{ __('librarian.copies.no_current_loan') }}</span>
                    </div>
                @endif
            </section>

            @can('copies.edit')
                <section class="admin-card">
                    <h2 class="mb-2 flex items-center gap-2 font-headline text-2xl text-primary">
                        <span class="material-symbols-outlined text-secondary">rule_settings</span>
                        {{ __('librarian.copies.status_actions') }}
                    </h2>
                    <p class="mb-5 text-xs leading-5 text-slate-500">{{ __('librarian.copies.status_action_hint') }}</p>

                    @if ($activeLoan)
                        <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                            <span>{{ __('librarian.copies.status_blocked_by_loan') }}</span>
                        </div>
                    @else
                        <form method="POST" action="{{ route('librarian.copies.status', $copy) }}" class="space-y-4">
                            @csrf

                            <fieldset>
                                <legend class="admin-label">{{ __('common.fields.actions') }}</legend>
                                <div class="space-y-2">
                                    @foreach (['write_off' => 'delete_sweep', 'lost' => 'help', 'under_repair' => 'build', 'restore' => 'restore_from_trash'] as $action => $icon)
                                        @continue($action === 'write_off' && ! auth()->user()->can('copies.write_off'))
                                        @continue($action === 'restore' && $copy->status === 'written_off')
                                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm transition-colors hover:border-secondary hover:bg-surface-container-low">
                                            <input
                                                class="h-4 w-4 border-slate-300 text-secondary focus:ring-secondary"
                                                type="radio"
                                                name="action"
                                                value="{{ $action }}"
                                                required
                                                @checked(old('action') === $action)
                                            >
                                            <span class="material-symbols-outlined text-[19px] text-slate-500">{{ $icon }}</span>
                                            <span class="font-semibold text-primary">{{ __('librarian.copies.actions.'.$action) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('action')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            </fieldset>

                            <div class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4" data-writeoff-fields>
                                <p class="mb-4 text-sm text-amber-900">{{ __('copy_lifecycle.writeoff_warning') }}</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label><span class="admin-label">{{ __('copy_lifecycle.fields.writeoff_date') }}</span><input class="admin-input" type="date" name="writeoff_date" value="{{ old('writeoff_date', now()->toDateString()) }}" data-writeoff-required></label>
                                    <label><span class="admin-label">{{ __('copy_lifecycle.fields.writeoff_act') }}</span><input class="admin-input" name="writeoff_act" maxlength="128" value="{{ old('writeoff_act') }}" data-writeoff-required></label>
                                    <label class="sm:col-span-2"><span class="admin-label">{{ __('copy_lifecycle.fields.writeoff_reason') }}</span><textarea class="admin-input" name="writeoff_reason" minlength="5" maxlength="2000" data-writeoff-required>{{ old('writeoff_reason') }}</textarea></label>
                                </div>
                            </div>

                            <label class="block">
                                <span class="admin-label">{{ __('common.fields.reason') }}</span>
                                <textarea
                                    class="admin-input"
                                    name="comment"
                                    rows="4"
                                    minlength="5"
                                    maxlength="2000"
                                    required
                                >{{ old('comment') }}</textarea>
                                @error('comment')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                            </label>

                            <button class="admin-btn admin-btn-primary w-full" type="submit">
                                <span class="material-symbols-outlined text-[19px]">task_alt</span>
                                {{ __('common.actions.confirm') }}
                            </button>
                        </form>
                    @endif
                </section>
            @endcan
        </div>
    </div>

    @if(blank($copy->barcode))
        @push('scripts')
            <script>
                (() => {
                    const mode = document.querySelector('[data-barcode-mode]');
                    const value = document.querySelector('[data-barcode-value]');
                    if (!mode || !value) return;
                    const sync = () => { value.disabled = mode.value !== 'existing'; value.required = mode.value === 'existing'; if (!value.disabled) value.focus(); };
                    mode.addEventListener('change', sync); sync();
                })();
            </script>
        @endpush
    @endif

    @push('scripts')
        <script>
            (() => {
                const actions = document.querySelectorAll('input[name="action"]');
                const block = document.querySelector('[data-writeoff-fields]');
                if (!actions.length || !block) return;
                const sync = () => {
                    const selected = document.querySelector('input[name="action"]:checked');
                    const active = selected?.value === 'write_off';
                    block.classList.toggle('hidden', !active);
                    block.querySelectorAll('[data-writeoff-required]').forEach((field) => { field.required = active; });
                };
                actions.forEach((action) => action.addEventListener('change', sync));
                sync();
            })();
        </script>
    @endpush
@endsection
