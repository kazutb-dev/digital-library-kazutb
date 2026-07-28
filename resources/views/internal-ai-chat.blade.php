<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Library AI assistance — Internal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div
        id="twentyfirst-ai-chat-root"
        data-agent="{{ config('services.twentyfirst.agent') }}"
        data-token-url="{{ url('/api/v1/internal/ai-assistant/token') }}"
        data-session-url="{{ url('/api/v1/internal/ai-assistant/session') }}"
        data-thread-url="{{ url('/api/v1/internal/ai-assistant/thread') }}"
        data-staff-name="{{ $internalStaffUser['name'] ?? $internalStaffUser['login'] ?? 'Library staff' }}"
    ></div>
</body>
</html>