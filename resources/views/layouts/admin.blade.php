@php
  $pageLang = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru';
  $adminUser = is_array($internalStaffUser ?? null) ? $internalStaffUser : [];
  $userName = trim((string) ($adminUser['name'] ?? 'Library Administrator')) ?: 'Library Administrator';
  $userTitle = trim((string) ($adminUser['title'] ?? 'Administrator')) ?: 'Administrator';
  $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
  $loginRedirectUrl = $pageLang === 'ru' ? '/login' : ('/login?lang=' . $pageLang);
  $adminNav = [
      ['label' => 'Governance Dashboard', 'icon' => 'dashboard', 'href' => route('admin.overview'), 'active' => request()->routeIs('admin.overview')],
      ['label' => 'User & Role Management', 'icon' => 'group', 'href' => route('admin.users'), 'active' => request()->routeIs('admin.users')],
      ['label' => 'Governance & Logs', 'icon' => 'gavel', 'href' => route('admin.logs'), 'active' => request()->routeIs('admin.logs')],
      ['label' => 'News Management', 'icon' => 'campaign', 'href' => route('admin.news'), 'active' => request()->routeIs('admin.news')],
      ['label' => 'Feedback Inbox', 'icon' => 'inbox', 'href' => route('admin.feedback'), 'active' => request()->routeIs('admin.feedback')],
      ['label' => 'Reports & Analytics', 'icon' => 'analytics', 'href' => route('admin.reports'), 'active' => request()->routeIs('admin.reports')],
      ['label' => 'System Settings', 'icon' => 'settings_suggest', 'href' => route('admin.settings'), 'active' => request()->routeIs('admin.settings')],
  ];
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLang }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'Admin Portal — KazUTB Smart Library')</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'surface-bright': '#f8f9fa',
            'inverse-primary': '#afc8f0',
            'on-surface-variant': '#43474e',
            'inverse-on-surface': '#f0f1f2',
            'on-secondary': '#ffffff',
            'surface': '#f8f9fa',
            'surface-container-low': '#f3f4f5',
            'primary-fixed-dim': '#afc8f0',
            'on-secondary-fixed': '#002020',
            'tertiary-fixed': '#d1e4fb',
            'primary-fixed': '#d4e3ff',
            'surface-container-highest': '#e1e3e4',
            'on-tertiary-fixed-variant': '#36485b',
            'surface-tint': '#476083',
            'secondary': '#006a6a',
            'tertiary': '#000610',
            'outline': '#74777f',
            'error-container': '#ffdad6',
            'on-tertiary-container': '#76889d',
            'background': '#f8f9fa',
            'on-secondary-fixed-variant': '#004f4f',
            'error': '#ba1a1a',
            'on-surface': '#191c1d',
            'primary': '#000613',
            'on-primary': '#ffffff',
            'on-error': '#ffffff',
            'surface-container-high': '#e7e8e9',
            'on-error-container': '#93000a',
            'inverse-surface': '#2e3132',
            'secondary-fixed-dim': '#76d6d5',
            'primary-container': '#001f3f',
            'outline-variant': '#c4c6cf',
            'on-primary-fixed': '#001c3a',
            'surface-container': '#edeeef',
            'on-tertiary-fixed': '#091d2e',
            'on-background': '#191c1d',
            'on-primary-container': '#6f88ad',
            'tertiary-fixed-dim': '#b5c8df',
            'on-secondary-container': '#006e6e',
            'secondary-fixed': '#93f2f2',
            'secondary-container': '#90efef',
            'surface-container-lowest': '#ffffff',
            'on-primary-fixed-variant': '#2f486a',
            'surface-dim': '#d9dadb',
            'tertiary-container': '#0d2031'
          },
          borderRadius: {
            DEFAULT: '0.125rem',
            lg: '0.25rem',
            xl: '0.5rem',
            full: '0.75rem'
          },
          fontFamily: {
            headline: ['Newsreader', 'serif'],
            body: ['Manrope', 'sans-serif'],
            label: ['Manrope', 'sans-serif']
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Manrope', sans-serif; }
    .font-headline { font-family: 'Newsreader', serif; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  </style>
  @yield('head')
</head>
<body class="bg-surface text-on-surface antialiased flex min-h-screen">
  <nav class="bg-surface-container-lowest text-primary font-body h-screen w-72 fixed left-0 top-0 hidden md:flex flex-col z-40 pt-4 pb-6 shadow-none">
    <div class="px-6 mb-12 flex items-center gap-4">
      <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-on-primary font-headline font-bold">{{ $userInitial }}</div>
      <div>
        <h2 class="font-headline text-2xl font-black text-primary leading-tight">Admin Portal</h2>
        <p class="text-sm text-on-surface-variant">High-Control Governance</p>
      </div>
    </div>

    <div class="flex-1 flex flex-col gap-2 overflow-y-auto">
      @foreach ($adminNav as $item)
        <a href="{{ $item['href'] }}" class="{{ $item['active'] ? 'bg-primary text-white rounded-md mx-2 px-4 py-3 flex items-center gap-3' : 'text-on-surface/70 mx-2 px-4 py-3 flex items-center gap-3 hover:bg-primary/5 transition-all duration-200 ease-in-out' }}">
          <span class="material-symbols-outlined" @if($item['active']) style="font-variation-settings: 'FILL' 1;" @endif>{{ $item['icon'] }}</span>
          <span>{{ $item['label'] }}</span>
        </a>
      @endforeach
    </div>

    <div class="px-4 mt-auto">
      <a href="{{ route('admin.news') }}" class="w-full py-3 mb-6 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-md font-medium text-sm hover:opacity-90 transition-opacity inline-flex items-center justify-center">Post Announcement</a>
      <div class="flex flex-col gap-2">
        <a href="/librarian/data-cleanup" class="text-on-surface/70 mx-2 px-4 py-2 flex items-center gap-3 hover:bg-primary/5 transition-all duration-200 text-sm">
          <span class="material-symbols-outlined text-[20px]">open_in_new</span>
          <span>Librarian Console →</span>
        </a>
        <button id="admin-logout-btn" type="button" class="text-on-surface/70 mx-2 px-4 py-2 flex items-center gap-3 hover:bg-primary/5 transition-all duration-200 text-sm text-left">
          <span class="material-symbols-outlined text-[20px]">logout</span>
          <span>Logout</span>
        </button>
      </div>
    </div>
  </nav>

  <main class="flex-1 md:ml-72 flex flex-col min-h-screen">
    <header class="bg-white/80 backdrop-blur-xl text-primary tracking-tight text-[1.75rem] w-full top-0 sticky bg-surface-container-low border-b-0 shadow-sm shadow-primary/5 flex justify-between items-center px-8 py-3 z-30">
      <div class="flex items-center gap-4">
        <span class="font-headline text-xl font-bold text-primary md:hidden">Admin Portal</span>
        <div class="hidden md:flex relative items-center">
          <span class="material-symbols-outlined absolute left-3 text-on-surface-variant">search</span>
          <input class="pl-10 pr-4 py-2 bg-surface-container-highest border-b border-transparent focus:border-secondary outline-none rounded-t-md w-96 text-[1rem] font-body text-on-surface placeholder:text-on-surface-variant/60 transition-colors" placeholder="Search across collections, patrons, and metrics..." type="text" />
        </div>
      </div>

      <div class="flex items-center gap-4">
        <button class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface transition-colors duration-300" type="button">
          <span class="material-symbols-outlined">notifications</span>
        </button>
        <button class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface transition-colors duration-300" type="button">
          <span class="material-symbols-outlined">help_outline</span>
        </button>
        <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary overflow-hidden ml-2 flex items-center justify-center text-sm font-bold">{{ $userInitial }}</div>
      </div>
    </header>

    <div class="p-8 lg:p-12 max-w-7xl mx-auto w-full">
      @yield('content')
    </div>
  </main>

  <script>
    document.getElementById('admin-logout-btn')?.addEventListener('click', async () => {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/api/v1/logout', {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
      } catch (_) {}
      localStorage.removeItem('library.auth.user');
      window.location.href = @json($loginRedirectUrl);
    });
  </script>
  @stack('scripts')
</body>
</html>
