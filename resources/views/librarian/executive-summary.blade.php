<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ $title }}</title><style>body{font-family:DejaVu Sans,sans-serif;color:#123}h1{font-size:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccd;padding:6px;text-align:left}th{background:#eef6f5}.meta{font-size:11px;color:#456}</style></head><body>
<h1>{{ $title }}</h1><p class="meta">{{ $dashboard['period']['from'] }} — {{ $dashboard['period']['to'] }}</p>
<table><thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody></table>
</body></html>
