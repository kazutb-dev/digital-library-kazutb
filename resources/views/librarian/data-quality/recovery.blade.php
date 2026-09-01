@extends('layouts.librarian')

@section('title', __('recovery_quality.title').' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header :eyebrow="__('data_quality.title')" :title="__('recovery_quality.title')" :subtitle="__('recovery_quality.subtitle')">
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.index') }}">{{ __('recovery_quality.actions.standard_quality') }}</a>
        @can('ksu.manage')<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.ksu.conflicts') }}">{{ __('recovery_quality.actions.ksu_review') }}</a>@endcan
    </x-admin.page-header>

    <section @class(['admin-card mb-6 border-l-4', 'border-l-emerald-500' => $reconciliation['balanced'], 'border-l-red-500' => ! $reconciliation['balanced']])>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div><h2 class="font-headline text-2xl text-primary">{{ __('recovery_quality.reconciliation.title') }}</h2><p class="mt-1 text-sm text-slate-600">{{ __('recovery_quality.reconciliation.description') }}</p></div>
            <x-admin.status-badge :status="$reconciliation['balanced'] ? 'active' : 'critical'" :label="__('recovery_quality.reconciliation.'.($reconciliation['balanced'] ? 'balanced' : 'unbalanced'))" />
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-slate-50 p-4"><div class="admin-label">{{ __('recovery_quality.reconciliation.linked') }}</div><div class="mt-1 text-2xl font-bold text-primary">{{ number_format($reconciliation['linked'], 0, ',', ' ') }}</div></div>
            <div class="rounded-xl bg-slate-50 p-4"><div class="admin-label">{{ __('recovery_quality.reconciliation.unresolved') }}</div><div class="mt-1 text-2xl font-bold text-primary">{{ number_format($reconciliation['unresolved'], 0, ',', ' ') }}</div></div>
            <div class="rounded-xl bg-slate-50 p-4"><div class="admin-label">{{ __('recovery_quality.reconciliation.without_ksu') }}</div><div class="mt-1 text-2xl font-bold text-primary">{{ number_format($reconciliation['without_ksu'], 0, ',', ' ') }}</div></div>
            <div class="rounded-xl bg-primary-container p-4 text-white"><div class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ __('recovery_quality.reconciliation.total') }}</div><div class="mt-1 text-2xl font-bold">{{ number_format($reconciliation['total'], 0, ',', ' ') }} / {{ number_format($reconciliation['source_total'], 0, ',', ' ') }}</div></div>
        </div>
    </section>

    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($categories as $category)
            @php
                $categoryHref = match($category['route']) {
                    'ksu' => route('librarian.ksu.conflicts'),
                    'quarantine' => route('librarian.data-quality.recovery', ['queue'=>'quarantine']),
                    'conflicts' => route('librarian.data-quality.recovery', ['queue'=>'conflicts']),
                    'fund_raw' => route('librarian.data-quality.recovery', ['queue'=>'fund_raw']),
                    'without_ksu' => route('librarian.data-quality.recovery', ['queue'=>'without_ksu']),
                    'copies' => route('librarian.copies.index', $category['code'] === 'missing_barcode' ? ['barcode_status'=>'without'] : []),
                    'catalog' => route('librarian.catalog.index'),
                    'issues' => route('librarian.data-quality.index'),
                    default => route('librarian.data-quality.recovery'),
                };
                $tone = match($category['severity']) {'critical'=>'border-red-300 bg-red-50','high'=>'border-amber-300 bg-amber-50','medium'=>'border-yellow-200 bg-yellow-50',default=>'border-slate-200 bg-white'};
            @endphp
            <a class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm {{ $tone }}" href="{{ $categoryHref }}">
                <div class="flex items-start justify-between gap-2"><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('recovery_quality.taxonomy.'.$category['taxonomy']) }}</span><span class="rounded-full bg-white/80 px-2 py-0.5 text-xs font-bold text-slate-600">{{ __('recovery_quality.severity.'.$category['severity']) }}</span></div>
                <div class="mt-3 text-3xl font-bold text-primary">{{ number_format($category['count'], 0, ',', ' ') }}</div>
                <div class="mt-1 text-sm font-semibold text-primary">{{ __('recovery_quality.categories.'.$category['code']) }}</div>
            </a>
        @endforeach
    </section>

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="{{ __('recovery_quality.queue_navigation') }}">
        @foreach(['fund_raw','quarantine','conflicts','without_ksu'] as $queueCode)
            <a @class(['admin-btn', 'admin-btn-primary'=>$queue === $queueCode, 'admin-btn-secondary'=>$queue !== $queueCode]) href="{{ route('librarian.data-quality.recovery', ['queue'=>$queueCode]) }}">{{ __('recovery_quality.queues.'.$queueCode) }}</a>
        @endforeach
    </nav>
    <form method="GET" class="admin-card mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="queue" value="{{ $queue }}">
        <label class="min-w-64 flex-1"><span class="admin-label">{{ __('common.filters.search') }}</span><input class="admin-input" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100"></label>
        <button class="admin-btn admin-btn-primary">{{ __('common.actions.search') }}</button>
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.data-quality.recovery', ['queue'=>$queue]) }}">{{ __('common.actions.clear_filters') }}</a>
    </form>

    @if($queue === 'fund_raw')
        <div class="space-y-4">
            @forelse($rows as $copy)
                <article class="admin-card">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_2fr]">
                        <div><a class="font-mono font-bold text-secondary hover:underline" href="{{ route('librarian.copies.show', $copy) }}">{{ $copy->inventory_number }}</a><h2 class="mt-1 font-semibold text-primary">{{ $copy->bibliographicRecord?->title ?? '—' }}</h2><dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-1"><div><dt class="admin-label">T090w / fund_raw</dt><dd class="break-words rounded-lg bg-amber-50 px-3 py-2 font-mono text-amber-900">{{ $copy->fund_raw }}</dd></div><div><dt class="admin-label">{{ __('recovery_quality.fields.context') }}</dt><dd>{{ $copy->storage_sigla ?: $copy->sigla_code ?: '—' }} · {{ $copy->shelf_index ?: '—' }} · {{ $copy->fund?->name ?? '—' }}</dd></div></dl></div>
                        @can('legacy_recovery.resolve')
                            <form method="POST" action="{{ route('librarian.data-quality.recovery.fund.resolve', $copy) }}" class="grid gap-3 sm:grid-cols-2">@csrf
                                <label><span class="admin-label">{{ __('recovery_quality.fields.decision') }}</span><select class="admin-input" name="decision" required><option value="map_fund">{{ __('recovery_quality.decisions.map_fund') }}</option><option value="note">{{ __('recovery_quality.decisions.note') }}</option><option value="ignore">{{ __('recovery_quality.decisions.ignore') }}</option></select></label>
                                <label><span class="admin-label">{{ __('recovery_quality.fields.target_fund') }}</span><select class="admin-input" name="fund_id"><option value="">—</option>@foreach($funds as $fund)<option value="{{ $fund->id }}">{{ $fund->branch?->name }} · {{ $fund->name }}</option>@endforeach</select></label>
                                <label class="sm:col-span-2"><span class="admin-label">{{ __('recovery_quality.fields.decision_note') }}</span><textarea class="admin-input" name="decision_note" required minlength="5" maxlength="2000"></textarea></label>
                                <div class="sm:col-span-2 flex justify-end"><button class="admin-btn admin-btn-primary">{{ __('common.actions.confirm') }}</button></div>
                            </form>
                        @endcan
                    </div>
                </article>
            @empty<div class="admin-card py-10 text-center text-slate-500">{{ __('recovery_quality.empty') }}</div>@endforelse
        </div>
    @elseif($queue === 'conflicts')
        <div class="space-y-4">
            @forelse($rows as $conflict)
                <article class="admin-card">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold text-primary">{{ $conflict->entity_type }} #{{ $conflict->entity_id }} · {{ $conflict->field_name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $conflict->reason }} · source #{{ $conflict->source_id }}</p></div><x-admin.status-badge status="pending" :label="$conflict->status" /></div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2"><div class="rounded-xl border border-slate-200 p-3"><div class="admin-label">{{ __('recovery_quality.fields.current_value') }}</div><pre class="mt-2 whitespace-pre-wrap break-words text-xs">{{ $conflict->current_value ?? '—' }}</pre></div><div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><div class="admin-label">{{ __('recovery_quality.fields.source_value') }}</div><pre class="mt-2 whitespace-pre-wrap break-words text-xs">{{ $conflict->incoming_value ?? '—' }}</pre></div></div>
                    @can('legacy_recovery.resolve')<form method="POST" action="{{ route('librarian.data-quality.recovery.conflicts.resolve', $conflict) }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf<label><span class="admin-label">{{ __('recovery_quality.fields.decision') }}</span><select class="admin-input" name="decision"><option value="keep_current">{{ __('recovery_quality.decisions.keep_current') }}</option><option value="use_legacy">{{ __('recovery_quality.decisions.use_legacy') }}</option><option value="custom">{{ __('recovery_quality.decisions.custom') }}</option></select></label><label><span class="admin-label">{{ __('recovery_quality.fields.custom_value') }}</span><input class="admin-input" name="custom_value" maxlength="5000"></label><label><span class="admin-label">{{ __('recovery_quality.fields.decision_note') }}</span><input class="admin-input" name="resolution_note" required minlength="5" maxlength="2000"></label><div class="md:col-span-3 flex justify-end"><button class="admin-btn admin-btn-primary">{{ __('common.actions.confirm') }}</button></div></form>@endcan
                </article>
            @empty<div class="admin-card py-10 text-center text-slate-500">{{ __('recovery_quality.empty') }}</div>@endforelse
        </div>
    @elseif($queue === 'quarantine')
        <section class="admin-card overflow-x-auto"><table class="admin-table min-w-full"><thead><tr><th>ID</th><th>{{ __('recovery_quality.fields.kind') }}</th><th>INV / DOC</th><th>{{ __('librarian.copies.fields.inventory_number') }}</th><th>{{ __('librarian.copies.fields.barcode') }}</th><th>{{ __('recovery_quality.fields.reason') }}</th><th>{{ __('common.fields.actions') }}</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>#{{ $row->id }}</td><td>{{ trans()->has('library_recovery.values.quarantine_kind.'.$row->kind) ? __('library_recovery.values.quarantine_kind.'.$row->kind) : $row->kind }}</td><td>{{ $row->source_inv_id ?? '—' }} / {{ $row->source_doc_id ?? '—' }}</td><td class="font-mono">{{ data_get($row->payload, 'inventory_number', '—') }}</td><td class="font-mono">{{ data_get($row->payload, 'barcode', '—') ?: '—' }}</td><td class="max-w-md text-xs">{{ $row->reason }}</td><td><div class="min-w-72 space-y-2"><a class="admin-btn admin-btn-secondary" href="{{ route('librarian.catalog.create', ['quarantine'=>$row->id]) }}">{{ __('recovery_quality.actions.create_record') }}</a>@can('legacy_recovery.resolve')<form method="POST" action="{{ route('librarian.data-quality.recovery.quarantine.link', $row) }}" class="grid gap-2">@csrf<input class="admin-input" type="number" name="record_id" min="1" required placeholder="{{ __('recovery_quality.fields.record_id') }}"><input class="admin-input" name="reason" required minlength="5" maxlength="2000" placeholder="{{ __('recovery_quality.fields.decision_note') }}"><button class="admin-btn admin-btn-primary">{{ __('recovery_quality.actions.link_record') }}</button></form>@endcan</div></td></tr>@empty<tr><td colspan="7" class="py-10 text-center text-slate-500">{{ __('recovery_quality.empty') }}</td></tr>@endforelse</tbody></table></section>
    @else
        <section class="admin-card overflow-x-auto"><div class="mb-4 rounded-xl bg-sky-50 p-4 text-sm text-sky-900">{{ __('recovery_quality.without_ksu_info', ['count'=>$reconciliation['without_ksu'], 'orphans'=>$quarantinedOrphans]) }}</div><table class="admin-table min-w-full"><thead><tr><th>{{ __('librarian.copies.fields.inventory_number') }}</th><th>{{ __('librarian.copies.fields.record') }}</th><th>{{ __('librarian.copies.fields.registration_date') }}</th><th>{{ __('librarian.copies.fields.storage_sigla') }}</th></tr></thead><tbody>@forelse($rows as $copy)<tr><td><a class="font-mono font-semibold text-secondary hover:underline" href="{{ route('librarian.copies.show', $copy) }}">{{ $copy->inventory_number }}</a></td><td>{{ $copy->bibliographicRecord?->title ?? '—' }}</td><td>{{ $copy->registration_date?->format('d.m.Y') ?? '—' }}</td><td>{{ $copy->storage_sigla ?: '—' }}</td></tr>@empty<tr><td colspan="4" class="py-10 text-center text-slate-500">{{ __('recovery_quality.empty') }}</td></tr>@endforelse</tbody></table></section>
    @endif

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
