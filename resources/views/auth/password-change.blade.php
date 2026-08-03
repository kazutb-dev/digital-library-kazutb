<!DOCTYPE html>
<html lang="{{ in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.profile.forced_title') }} — {{ __('brand.library.name') }}</title>
    @include('partials.favicons')
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .font-headline { font-family: 'Newsreader', serif; }
    </style>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
    <main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
        <div class="mb-7 flex items-start justify-between gap-4 border-b border-slate-100 pb-5">
            <x-library-brand variant="compact" />
            <x-locale-switcher variant="light" />
        </div>
        <span class="material-symbols-outlined mb-4 block text-4xl text-[#006a6a]">lock_reset</span>
        <h1 class="font-headline text-3xl text-slate-900">{{ __('admin.profile.forced_title') }}</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('admin.profile.forced_hint') }}</p>

        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('admin.profile.temporary_password_label') }}</span>
                <input class="w-full rounded-lg border-slate-300 text-sm focus:border-[#006a6a] focus:ring-[#006a6a]" type="password" name="current_password" required autocomplete="current-password" autofocus>
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('admin.profile.new_password') }}</span>
                <input class="w-full rounded-lg border-slate-300 text-sm focus:border-[#006a6a] focus:ring-[#006a6a]" type="password" name="password" required minlength="12" autocomplete="new-password">
            </label>
            <label class="block">
                <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">{{ __('admin.profile.confirm_password') }}</span>
                <input class="w-full rounded-lg border-slate-300 text-sm focus:border-[#006a6a] focus:ring-[#006a6a]" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
            </label>
            <button class="w-full rounded-lg bg-[#001f3f] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#000613]" type="submit">
                {{ __('admin.profile.change_password_action') }}
            </button>
        </form>

        <form method="POST" action="{{ url('/logout') }}" class="mt-4 text-center">
            @csrf
            <button class="text-xs font-semibold text-slate-500 hover:text-slate-800" type="submit">{{ __('admin.nav.logout') }}</button>
        </form>
    </main>
</body>
</html>
