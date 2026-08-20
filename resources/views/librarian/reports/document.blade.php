@php
    $report = is_array($report ?? null) ? $report : [];
    $filters = is_array($filters ?? null) ? $filters : (array) ($report['filters'] ?? []);
    $metrics = collect($metrics ?? $report['metrics'] ?? [])->map(function ($metric, $key) {
        if (! is_array($metric)) {
            return ['key' => is_string($key) ? $key : 'total', 'value' => $metric];
        }
        $metric['key'] ??= is_string($key) ? $key : 'total';

        return $metric;
    })->values();
    $columns = collect($columns ?? $report['columns'] ?? [])->map(function ($column, $key) {
        if (is_string($column) && is_int($key)) {
            return ['key' => $column, 'label' => $column];
        }
        if (is_string($column)) {
            return ['key' => (string) $key, 'label' => $column];
        }
        $column = is_array($column) ? $column : (array) $column;
        $column['key'] ??= is_string($key) ? $key : ($column['field'] ?? '');
        $column['label'] ??= $column['title'] ?? $column['key'];

        return $column;
    })->filter(fn (array $column): bool => filled($column['key'] ?? null))->values();
    $rows = collect($rows ?? $report['rows'] ?? [])->map(fn ($row) => is_array($row) ? $row : (array) $row);
    $activeReport = (string) ($activeReport ?? $report['activeReport'] ?? 'acquisitions');
    $reportTitle = (string) ($reportTitle ?? __('analytics.reports.'.$activeReport.'.title'));
    $generatedAt = $generatedAt ?? now();
    $printMode = (bool) ($printMode ?? false);
    $reportNumber = $reportNumber ?? null;
    $reportRevision = $reportRevision ?? null;
    $approverName = $approverName ?? null;
    $from = $filters['date_from'] ?? $filters['from'] ?? null;
    $to = $filters['date_to'] ?? $filters['to'] ?? null;

    $formatValue = static function ($value, string $key = ''): string {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value)->format('d.m.Y H:i');
        }
        if (is_bool($value)) {
            return $value ? 'Да' : 'Нет';
        }
        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => filled($item))->implode(', ') ?: '—';
        }
        if (is_numeric($value)) {
            $decimals = str_contains($key, 'rate') || str_contains($key, 'percent') ? 1 : (((float) $value !== (float) (int) $value) ? 2 : 0);

            return number_format((float) $value, $decimals, ',', ' ');
        }

        return (string) $value;
    };

    $filterLabels = collect($filters)
        ->reject(fn ($value, $key) => blank($value) || in_array($key, ['preset'], true))
        ->mapWithKeys(function ($value, $key) {
            $normalized = match ((string) $key) { 'from' => 'date_from', 'to' => 'date_to', default => (string) $key };
            $translationKey = 'analytics.filters.'.$normalized;

            return [trans()->has($translationKey) ? __($translationKey) : str($normalized)->replace('_', ' ')->title()->toString() => $value];
        });
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { margin: 16mm 13mm 18mm; counter-increment: page; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #142b2b; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.45; }
        .toolbar { margin: -8px 0 14px; padding: 10px; border: 1px solid #dbe5e3; background: #f6faf9; text-align: right; }
        .toolbar button { border: 0; border-radius: 5px; padding: 8px 16px; color: white; background: #007d78; font-weight: bold; cursor: pointer; }
        .brand { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .brand td { vertical-align: top; }
        .brand-mark { width: 50px; height: 50px; border-radius: 50%; color: white; background: #0b4d4a; text-align: center; font-size: 16px; font-weight: bold; line-height: 50px; }
        .brand-name { margin: 2px 0 1px; color: #0b4d4a; font-size: 15px; font-weight: bold; }
        .muted { color: #657573; }
        .document-meta { text-align: right; }
        .rule { height: 3px; margin: 8px 0 18px; background: #00a8a0; }
        h1 { margin: 0 0 5px; color: #0a3937; font-size: 23px; line-height: 1.2; }
        h2 { margin: 19px 0 7px; color: #0a3937; font-size: 13px; }
        .period { margin: 0 0 14px; font-size: 10px; }
        .filters { margin: 0 0 14px; padding: 7px 9px; border: 1px solid #dbe5e3; background: #f7faf9; }
        .filters span { display: inline-block; margin: 2px 14px 2px 0; }
        .metrics { width: 100%; margin: 0 0 14px; border-collapse: separate; border-spacing: 5px; }
        .metrics td { width: 25%; padding: 9px; border: 1px solid #cfe1df; background: #f4f9f8; vertical-align: top; }
        .metric-label { color: #657573; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .metric-value { margin-top: 5px; color: #0b4d4a; font-size: 16px; font-weight: bold; }
        .data { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; page-break-after: auto; }
        .data th { padding: 6px 5px; color: white; background: #0b4d4a; border: 1px solid #0b4d4a; text-align: left; font-size: 7px; }
        .data td { padding: 5px; border: 1px solid #d8e0df; vertical-align: top; }
        .data tbody tr:nth-child(even) td { background: #f7faf9; }
        .empty { padding: 24px !important; color: #657573; text-align: center; }
        .note { margin-top: 14px; padding: 8px 9px; border-left: 3px solid #c77b30; background: #fff8ee; color: #6d4b27; }
        .signatures { width: 100%; margin-top: 28px; border-collapse: collapse; page-break-inside: avoid; }
        .signatures td { width: 50%; padding: 0 22px 0 0; vertical-align: bottom; }
        .signature-line { margin-top: 23px; border-bottom: 1px solid #52625f; }
        .footer { position: fixed; right: 0; bottom: -12mm; left: 0; color: #7b8987; font-size: 7px; text-align: center; }
        .page-number::after { content: counter(page); }
        @media print { .toolbar { display: none; } body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    @if($printMode)
        <div class="toolbar"><button type="button" onclick="window.print()">{{ __('analytics.formats.print') }}</button></div>
    @endif

    <table class="brand">
        <tr>
            <td style="width: 60px"><div class="brand-mark">КУТБ</div></td>
            <td><div class="brand-name">{{ __('common.app_name') }}</div><div class="muted">{{ __('analytics.eyebrow') }}</div></td>
            <td class="document-meta">{{ __('analytics.generated', ['date' => $generatedAt->format('d.m.Y H:i')]) }}<br><span class="muted">{{ $reportNumber ? __('official_reports.fields.number').': '.$reportNumber : 'ID: '.strtoupper($activeReport).'-'.$generatedAt->format('Ymd-His') }}</span>@if($reportRevision)<br><span class="muted">{{ __('official_reports.fields.revision') }}: {{ $reportRevision }} · {{ __('official_reports.fields.approver') }}: {{ $approverName ?: '—' }}</span>@endif</td>
        </tr>
    </table>
    <div class="rule"></div>

    <h1>{{ $reportTitle }}</h1>
    <p class="period">{{ __('analytics.period', ['from' => $from ?: '—', 'to' => $to ?: '—']) }}</p>

    @if($filterLabels->isNotEmpty())
        <div class="filters">
            @foreach($filterLabels as $label => $value)<span><strong>{{ $label }}:</strong> {{ $formatValue($value) }}</span>@endforeach
        </div>
    @endif

    @if($metrics->isNotEmpty())
        <table class="metrics">
            @foreach($metrics->chunk(4) as $metricRow)
                <tr>
                    @foreach($metricRow as $metric)
                        @php($metricKey = (string) ($metric['key'] ?? 'total'))
                        <td><div class="metric-label">{{ $metric['label'] ?? __('analytics.metrics.'.$metricKey) }}</div><div class="metric-value">{{ $formatValue($metric['value'] ?? $metric['total'] ?? 0, $metricKey) }}</div></td>
                    @endforeach
                    @for($padding = $metricRow->count(); $padding < 4; $padding++)<td></td>@endfor
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('analytics.table_title') }}</h2>
    <table class="data">
        <thead><tr>@foreach($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>@foreach($columns as $column)<td>{{ $formatValue(data_get($row, $column['key']), $column['key']) }}</td>@endforeach</tr>
            @empty
                <tr><td class="empty" colspan="{{ max(1, $columns->count()) }}">{{ __('analytics.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="note">{{ __('analytics.official_note') }}</div>
    <table class="signatures"><tr><td>{{ __('analytics.signatures.prepared_by') }}<div class="signature-line"></div></td><td>{{ __('analytics.signatures.approved_by') }}<div class="signature-line"></div></td></tr></table>
    <div class="footer">{{ __('common.app_name') }} · {{ $reportTitle }} · {{ __('official_reports.page') }} <span class="page-number"></span></div>
</body>
</html>
