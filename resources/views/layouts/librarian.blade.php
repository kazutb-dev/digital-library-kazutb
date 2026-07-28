@php
  $pageLang = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru';
  $librarianUser = is_array($librarianStaffUser ?? null) ? $librarianStaffUser : [];
  $userName = trim((string) ($librarianUser['name'] ?? 'Library Operator')) ?: 'Library Operator';
  $userTitle = trim((string) ($librarianUser['title'] ?? 'Librarian')) ?: 'Librarian';
  $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
  $loginRedirectUrl = $pageLang === 'ru' ? '/login' : ('/login?lang=' . $pageLang);
  $librarianRole = mb_strtolower(trim((string) ($librarianUser['role'] ?? '')));

  // Canonical librarian nav. Items without a dedicated /librarian/* route yet
  // fall back to the transitional /internal/* pages so operational continuity
  // is preserved during Phase 1 migration. Items with no current target use '#'.
  $librarianNav = [
      [
          'label' => 'Overview',
          'icon' => 'dashboard',
          'href' => route('librarian.overview'),
          'active' => request()->routeIs('librarian.overview'),
      ],
      [
          'label' => 'Circulation',
          'icon' => 'sync_alt',
          'href' => route('librarian.circulation'),
          'active' => request()->routeIs('librarian.circulation'),
      ],
      [
          'label' => 'Reservations',
          'icon' => 'bookmark_manager',
          'href' => '#',
          'active' => false,
      ],
      [
          'label' => 'Catalog',
          'icon' => 'menu_book',
          'href' => '/catalog',
          'active' => false,
      ],
      [
          'label' => 'Copies / Items',
          'icon' => 'inventory_2',
          'href' => '#',
          'active' => false,
      ],
      [
          'label' => 'Data Cleanup',
          'icon' => 'mop',
          'href' => route('librarian.data-cleanup'),
          'active' => request()->routeIs('librarian.data-cleanup'),
      ],
      [
          'label' => 'Scientific Repository',
          'icon' => 'school',
          'href' => route('librarian.repository'),
          'active' => request()->routeIs('librarian.repository'),
      ],
      [
          'label' => 'News',
          'icon' => 'newspaper',
          'href' => '#',
          'active' => false,
      ],
      [
          'label' => 'Reports',
          'icon' => 'analytics',
          'href' => '#',
          'active' => false,
      ],
      [
          'label' => 'Messages',
          'icon' => 'mail',
          'href' => '#',
          'active' => false,
      ],
  ];
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLang }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'Librarian Console — KazUTB Smart Library')</title>
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
    .material-symbols-outlined.fill { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  </style>
  @yield('head')
</head>
<body class="bg-surface text-on-surface antialiased flex min-h-screen">
  <aside class="bg-surface-container-low text-primary font-body h-screen w-72 flex-shrink-0 fixed left-0 top-0 hidden md:flex flex-col z-40 py-8 border-r-0">
    <div class="px-6 mb-10 flex items-center gap-4">
      <div class="w-12 h-12 rounded-full bg-primary-container flex items-center justify-center text-on-primary font-headline font-bold text-lg flex-shrink-0">{{ $userInitial }}</div>
      <div>
        <div class="font-headline text-lg font-bold text-primary-container leading-tight">Operations</div>
        <div class="text-on-surface-variant text-xs mt-0.5">Librarian Console</div>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 space-y-1" aria-label="Librarian navigation">
      @foreach ($librarianNav as $item)
        @php $isDisabled = ($item['href'] ?? '#') === '#'; @endphp
        @if ($item['active'])
          <a href="{{ $item['href'] }}" class="bg-white text-secondary font-semibold rounded-r-full shadow-sm flex items-center gap-3 py-3 px-6 w-[95%] transition-all duration-500" aria-current="page">
            <span class="material-symbols-outlined fill">{{ $item['icon'] }}</span>
            <span>{{ $item['label'] }}</span>
          </a>
        @elseif ($isDisabled)
          <span class="text-slate-600 py-3 px-6 flex items-center gap-3 w-[95%] rounded-r-full opacity-50 cursor-not-allowed select-none" aria-disabled="true" role="link" tabindex="-1">
            <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
            <span>{{ $item['label'] }}</span>
            <span class="ml-auto text-[10px] uppercase tracking-wider text-on-surface-variant/70 font-semibold">soon</span>
          </span>
        @else
          <a href="{{ $item['href'] }}" class="text-slate-600 py-3 px-6 flex items-center gap-3 w-[95%] rounded-r-full hover:bg-surface-container hover:pl-8 hover:text-secondary transition-all duration-500">
            <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
            <span>{{ $item['label'] }}</span>
          </a>
        @endif
      @endforeach
    </nav>

    <div class="px-6 mt-8 space-y-4">
      <a href="{{ route('librarian.circulation') }}" class="w-full bg-gradient-to-r from-primary to-primary-container text-on-primary py-3 rounded-md font-semibold hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
        <span class="material-symbols-outlined">add</span>
        <span>New Transaction</span>
      </a>
      <div class="pt-4 border-t border-surface-container-high border-opacity-50">
        <a href="#" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors opacity-60 pointer-events-none">
          <span class="material-symbols-outlined">settings</span>
          <span>Settings</span>
        </a>
        <button id="librarian-logout-btn" type="button" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors w-full text-left">
          <span class="material-symbols-outlined">logout</span>
          <span>Logout</span>
        </button>
      </div>
    </div>
  </aside>

  <main class="flex-1 md:ml-72 flex flex-col min-h-screen bg-surface relative">
    <header class="bg-surface-container-low/80 backdrop-blur-md text-primary tracking-tight w-full top-0 sticky z-30 flex justify-between items-center px-8 py-4 h-20 flex-shrink-0">
      <div class="flex items-center gap-6">
        <div class="font-headline italic text-primary-container text-xl hidden md:block">KazUTB Smart Library</div>
        <div class="relative ml-4 md:ml-12 hidden sm:block">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <span class="material-symbols-outlined text-outline text-[18px]">search</span>
          </div>
          <input class="bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 text-sm py-2 pl-10 pr-4 w-64 md:w-80 rounded-t-md transition-colors placeholder:text-outline/70" placeholder="Search operations..." type="text" />
        </div>
      </div>

      <div class="flex items-center gap-2 md:gap-4">
        <button class="text-slate-500 hover:text-primary-container hover:bg-slate-100 p-2 rounded-full transition-colors duration-300" type="button" aria-label="Notifications">
          <span class="material-symbols-outlined">notifications</span>
        </button>
        <button class="text-slate-500 hover:text-primary-container hover:bg-slate-100 p-2 rounded-full transition-colors duration-300 hidden sm:block" type="button" aria-label="Recent activity">
          <span class="material-symbols-outlined">history_edu</span>
        </button>
        <div class="h-9 w-9 ml-2 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-sm font-bold cursor-default" title="{{ $userName }} — {{ $userTitle }}">{{ $userInitial }}</div>
      </div>
    </header>

    <div class="flex-1 overflow-y-auto px-4 md:px-12 py-8 lg:py-12 pb-24">
      @yield('content')
    </div>
  </main>

  <script>
    document.getElementById('librarian-logout-btn')?.addEventListener('click', async () => {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/api/v1/logout', {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
      } catch (_) {}
      try { localStorage.removeItem('library.auth.user'); } catch (_) {}
      window.location.href = @json($loginRedirectUrl);
    });
  </script>
  @stack('scripts')
</body>
</html>
