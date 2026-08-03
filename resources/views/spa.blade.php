@php
    $spaLang = app()->getLocale();
    $spaTitles = [
        'ru' => 'Библиотека — Приложение',
        'kk' => 'Кітапхана — Қосымша',
        'en' => 'Library — Application',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $spaLang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $spaTitles[$spaLang] ?? $spaTitles['ru'] }}</title>
    @include('partials.favicons')
    <link rel="stylesheet" href="/fonts/fonts.css">
    @vite(['resources/js/spa/main.jsx'])
</head>
<body>
    <main aria-label="spa main workspace">
        <div id="spa-root"></div>
    </main>
</body>
</html>
