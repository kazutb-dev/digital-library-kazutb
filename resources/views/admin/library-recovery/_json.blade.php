@php
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
<pre class="max-h-[32rem] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100">{{ $encoded === false || $encoded === 'null' ? '—' : $encoded }}</pre>
