@extends('layouts.librarian')

@php
    $columns = collect($payload['columns'] ?? []);
    $rows = collect($payload['rows'] ?? []);
    $metrics = collect($payload['metrics'] ?? []);
    $formatValue = static function ($value): string {
        if ($value === null || $value === '') return '—';
        if (is_array($value)) return collect($value)->implode(', ');
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_numeric($value)) return number_format((float) $value, ((float) $value === (float) (int) $value ? 0 : 2), ',', ' ');
        return (string) $value;
    };
@endphp

@section('title', __('analytics.reports.'.$snapshot->report_type.'.title').' R'.$snapshot->revision.' — '.__('common.app_name'))

@section('content')
    <x-admin.flash />
    <x-admin.page-header :eyebrow="__('official_reports.title')" :title="__('analytics.reports.'.$snapshot->report_type.'.title').' · '.$snapshot->report_number" :subtitle="__('official_reports.source_notice')" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.official.index') }}"><span class="material-symbols-outlined text-[18px]">arrow_back</span>{{ __('official_reports.archive') }}</a>
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $snapshot->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($snapshot->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900') }}">{{ __('official_reports.statuses.'.$snapshot->status) }}</span>
    </div>

    <div class="mb-6 rounded-xl border px-4 py-3 text-sm {{ $integrityOk ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}">
        <span class="font-bold">{{ $integrityOk ? __('official_reports.integrity_ok') : __('official_reports.integrity_failed') }}</span>
        <span class="mt-1 block break-all font-mono text-[11px]">{{ __('official_reports.fields.hash') }}: {{ $snapshot->source_hash }}</span>
    </div>

    <section class="admin-card mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 text-sm">
            <div><span class="admin-label">{{ __('official_reports.fields.number') }}</span><span class="font-mono">{{ $snapshot->report_number }}</span></div>
            <div><span class="admin-label">{{ __('official_reports.fields.period') }}</span>{{ $snapshot->period_from->timezone(config('app.library_timezone', 'Asia/Almaty'))->format('d.m.Y') }} — {{ $snapshot->period_to->timezone(config('app.library_timezone', 'Asia/Almaty'))->format('d.m.Y') }}</div>
            <div><span class="admin-label">{{ __('official_reports.fields.creator') }}</span>{{ $snapshot->creator?->name ?: '—' }}</div>
            <div><span class="admin-label">{{ __('official_reports.fields.created') }}</span>{{ $snapshot->created_at?->format('d.m.Y H:i') }}</div>
            <div><span class="admin-label">{{ __('official_reports.fields.status') }}</span>{{ __('official_reports.statuses.'.$snapshot->status) }}</div>
        </div>
        @if($snapshot->revision_note)<p class="mt-4 rounded-lg bg-surface-container-low p-3 text-sm">{{ $snapshot->revision_note }}</p>@endif
        @if($snapshot->decision_note)<p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm"><strong>{{ __('official_reports.decision_note') }}:</strong> {{ $snapshot->decision_note }}</p>@endif

        <div class="mt-5 flex flex-wrap gap-2">
            @can('downloadSource', $snapshot)<a class="admin-btn admin-btn-secondary" href="{{ route('librarian.reports.official.source', $snapshot) }}"><span class="material-symbols-outlined text-[18px]">data_object</span>{{ __('official_reports.actions.source') }}</a>@endcan
            @can('submit', $snapshot)<form method="POST" action="{{ route('librarian.reports.official.submit', $snapshot) }}">@csrf<button type="submit" class="admin-btn admin-btn-primary"><span class="material-symbols-outlined text-[18px]">send</span>{{ __('official_reports.actions.submit') }}</button></form>@endcan
            @can('delete', $snapshot)<form method="POST" action="{{ route('librarian.reports.official.destroy', $snapshot) }}">@csrf @method('DELETE')<button type="submit" class="admin-btn admin-btn-danger">{{ __('official_reports.actions.delete') }}</button></form>@endcan
            @can('archive', $snapshot)<form method="POST" action="{{ route('librarian.reports.official.archive', $snapshot) }}">@csrf<button type="submit" class="admin-btn admin-btn-secondary">{{ __('official_reports.actions.archive') }}</button></form>@endcan
        </div>

        @can('approve', $snapshot)
            <div class="mt-5 grid gap-3 border-t border-slate-100 pt-5 lg:grid-cols-2">
                <form method="POST" action="{{ route('librarian.reports.official.approve', $snapshot) }}" class="grid gap-2">@csrf<label><span class="admin-label">{{ __('official_reports.decision_note') }}</span><input class="admin-input" name="decision_note" maxlength="2000"></label><button type="submit" class="admin-btn admin-btn-primary">{{ __('official_reports.actions.approve') }}</button></form>
                <form method="POST" action="{{ route('librarian.reports.official.reject', $snapshot) }}" class="grid gap-2">@csrf<label><span class="admin-label">{{ __('official_reports.decision_note') }}</span><input class="admin-input" name="decision_note" minlength="3" maxlength="2000" required></label><button type="submit" class="admin-btn admin-btn-danger">{{ __('official_reports.actions.reject') }}</button></form>
            </div>
        @endcan
        @can('revise', $snapshot)
            <form method="POST" action="{{ route('librarian.reports.official.revisions.store', $snapshot) }}" class="mt-5 grid gap-2 border-t border-slate-100 pt-5 sm:grid-cols-[1fr_auto] sm:items-end">@csrf<label><span class="admin-label">{{ __('official_reports.fields.note') }}</span><input class="admin-input" name="revision_note" minlength="3" maxlength="2000" required></label><button type="submit" class="admin-btn admin-btn-secondary whitespace-nowrap">{{ __('official_reports.actions.revise') }}</button></form>
        @endcan
    </section>

    @if($metrics->isNotEmpty())<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach($metrics as $metric)<article class="admin-card"><span class="admin-label">{{ $metric['label'] ?? $metric['key'] }}</span><strong class="font-headline text-3xl text-primary">{{ $formatValue($metric['value'] ?? 0) }}</strong></article>@endforeach</div>@endif

    <section class="admin-card mb-6 overflow-hidden p-0">
        <div class="overflow-x-auto"><table class="admin-table min-w-[900px]"><thead><tr>@foreach($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>
        @forelse($rows as $row)<tr>@foreach($columns as $column)<td>{{ $formatValue(data_get($row, $column['key'])) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ max(1, $columns->count()) }}" class="py-12 text-center text-slate-500">{{ __('analytics.empty') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>

    @can('export', $snapshot)
        <section class="admin-card mb-6">
            <h2 class="font-headline text-2xl text-primary">{{ __('official_reports.actions.export') }}</h2>
            <div class="mt-4 flex flex-wrap gap-2">@foreach(\App\Models\ReportExportJob::FORMATS as $format)<form method="POST" action="{{ route('librarian.reports.official.exports.store', $snapshot) }}">@csrf<input type="hidden" name="format" value="{{ $format }}"><button type="submit" class="admin-btn admin-btn-secondary">{{ strtoupper($format) }}</button></form>@endforeach</div>
        </section>
    @endcan

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ __('official_reports.revisions') }}</h2><div class="mt-4 space-y-2">@foreach($revisions as $revision)<a class="flex justify-between rounded-lg bg-surface-container-low p-3 text-sm hover:bg-surface-container" href="{{ route('librarian.reports.official.show', $revision) }}"><span>R{{ $revision->revision }} · {{ __('official_reports.statuses.'.$revision->status) }}</span><span>{{ $revision->created_at?->format('d.m.Y H:i') }}</span></a>@endforeach</div></section>
        <section class="admin-card"><h2 class="font-headline text-2xl text-primary">{{ __('official_reports.exports') }}</h2><div class="mt-4 space-y-3">@forelse($exports as $export)<div class="rounded-lg border border-slate-100 p-3" data-export-status="{{ $export->status }}" data-export-status-url="{{ route('librarian.reports.official.exports.status', $export) }}"><div class="flex items-center justify-between"><strong>{{ strtoupper($export->format) }}</strong><span>{{ __('official_reports.statuses.'.$export->status) }}</span></div><div class="mt-2 h-1.5 overflow-hidden rounded bg-slate-100" role="progressbar" aria-label="{{ strtoupper($export->format) }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $export->progress }}"><span data-export-progress class="block h-full bg-secondary" style="width: {{ $export->progress }}%"></span></div><div class="mt-2 flex gap-2">@if($export->status === 'ready')<a class="text-xs font-bold text-secondary" href="{{ route('librarian.reports.official.exports.download', $export) }}">{{ __('official_reports.actions.download') }}</a>@elseif($export->status === 'failed')<form method="POST" action="{{ route('librarian.reports.official.exports.retry', $export) }}">@csrf<button class="text-xs font-bold text-red-700">{{ __('official_reports.actions.retry') }}</button></form>@endif</div></div>@empty<p class="text-sm text-slate-500">{{ __('analytics.empty') }}</p>@endforelse</div></section>
    </div>
@endsection

@section('head')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-export-status-url]').forEach((node) => {
                const poll = async () => {
                    try {
                        const response = await fetch(node.dataset.exportStatusUrl, {headers: {'Accept': 'application/json'}});
                        if (!response.ok) return;
                        const data = await response.json();
                        node.querySelector('[data-export-progress]').style.width = `${data.progress}%`;
                        node.querySelector('[role="progressbar"]').setAttribute('aria-valuenow', data.progress);
                        if (['queued', 'generating'].includes(data.status)) setTimeout(poll, 1500);
                        else if (data.status === 'ready') window.location.reload();
                    } catch (_) {}
                };
                if (['queued', 'generating'].includes(node.dataset.exportStatus)) poll();
            });
        });
    </script>
@endsection
