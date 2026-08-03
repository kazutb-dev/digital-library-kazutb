<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1 { color: #001f3f; font-size: 20px; margin-bottom: 4px; }
        p { color: #64748b; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; color: #334155; background: #f1f5f9; text-transform: uppercase; font-size: 8px; padding: 8px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
    </style>
</head>
<body>
    <h1>{{ __('reports.title') }} · {{ $reportTitle }}</h1>
    <p>{{ __('reports.generated_at', ['date' => $generatedAt->format('d.m.Y H:i')]) }}</p>
    <table>
        <thead>
            <tr>
                @foreach (array_keys($rows->first() ?? ['message' => '']) as $heading)
                    <th>{{ \Illuminate\Support\Facades\Lang::has('reports.columns.'.$heading) ? __('reports.columns.'.$heading) : $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_array($cell) ? implode(', ', $cell) : ($cell ?? '—') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td>{{ __('reports.no_data') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
