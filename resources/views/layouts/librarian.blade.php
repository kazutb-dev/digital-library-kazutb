@php
  $pageLang = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'kk';
  $librarianUser = is_array($librarianStaffUser ?? null) ? $librarianStaffUser : [];
  $userName = trim((string) ($librarianUser['name'] ?? __('shell.librarian.operator'))) ?: __('shell.librarian.operator');
  $userTitle = trim((string) ($librarianUser['title'] ?? __('shell.librarian.role'))) ?: __('shell.librarian.role');
  $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
  $loginRedirectUrl = '/login';
  $librarianRole = mb_strtolower(trim((string) ($librarianUser['canonical_role'] ?? $librarianUser['role'] ?? 'librarian')));
  $workspaceRole = match ($librarianRole) {
      'director' => 'director',
      'senior_librarian' => 'senior_librarian',
      'acquisitions' => 'acquisitions',
      'cataloguer' => 'cataloguer',
      'bibliographer' => 'bibliographer',
      default => 'librarian',
  };

  // Canonical librarian navigation. Every entry maps to a real controller
  // route; items the current staff account lacks permission for are filtered
  // out rather than shown as dead links.
  $staffUser = auth()->user();
  $librarianNav = collect([
      [
          'label' => __('librarian.nav.overview'),
          'icon' => 'dashboard',
          'href' => route('librarian.overview'),
          'active' => request()->routeIs('librarian.overview'),
          'permissions' => [],
      ],
      [
          'label' => __('librarian.nav.circulation'),
          'icon' => 'sync_alt',
          'href' => route('librarian.circulation'),
          'active' => request()->routeIs('librarian.circulation') || request()->routeIs('librarian.circulation.*'),
          'permissions' => ['circulation.issue', 'circulation.return'],
      ],
      [
          'label' => __('librarian.nav.visits'),
          'icon' => 'sensor_door',
          'href' => route('librarian.visits.index'),
          'active' => request()->routeIs('librarian.visits.*'),
          'permissions' => ['visits.record'],
      ],
      [
          'label' => __('librarian.nav.reservations'),
          'icon' => 'bookmark_manager',
          'href' => route('librarian.reservations.index'),
          'active' => request()->routeIs('librarian.reservations.*'),
          'permissions' => ['reservation.confirm'],
      ],
      [
          'label' => __('librarian.nav.catalog'),
          'icon' => 'menu_book',
          'href' => route('librarian.catalog.index'),
          'active' => request()->routeIs('librarian.catalog.*'),
          'permissions' => [
              'catalog.search',
              'catalog.view_full_metadata',
              'catalog.view_udc',
              'catalog.create_record',
              'catalog.edit_record',
          ],
      ],
      [
          'label' => __('librarian.nav.copies'),
          'icon' => 'inventory_2',
          'href' => route('librarian.copies.index'),
          'active' => request()->routeIs('librarian.copies.*'),
          'permissions' => ['copies.create', 'copies.edit', 'copies.delete'],
      ],
      [
          'label' => __('librarian.nav.inventory'),
          'icon' => 'barcode_scanner',
          'href' => route('librarian.inventory.index'),
          'active' => request()->routeIs('librarian.inventory.*'),
          'permissions' => ['inventory.view'],
      ],
      [
          'label' => __('librarian.nav.fines'),
          'icon' => 'payments',
          'href' => route('librarian.fines.index'),
          'active' => request()->routeIs('librarian.fines.*'),
          'permissions' => ['fines.view'],
      ],
      [
          'label' => __('incidents.nav'),
          'icon' => 'report_problem',
          'href' => route('librarian.incidents.index'),
          'active' => request()->routeIs('librarian.incidents.*'),
          'permissions' => ['incidents.view'],
      ],
      [
          'label' => __('librarian.nav.data_cleanup'),
          'icon' => 'mop',
          'href' => route('librarian.data-quality.index'),
          'active' => request()->routeIs('librarian.data-quality.*') || request()->routeIs('librarian.data-cleanup*'),
          'permissions' => ['data_quality.view'],
      ],
      [
          'label' => __('librarian.nav.repository'),
          'icon' => 'school',
          'href' => route('librarian.repository'),
          'active' => request()->routeIs('librarian.repository') || request()->routeIs('librarian.repository.*'),
          'permissions' => ['repository.upload', 'repository.approve', 'repository.publish'],
      ],
      [
          'label' => __('librarian.nav.news'),
          'icon' => 'newspaper',
          'href' => route('librarian.news.index'),
          'active' => request()->routeIs('librarian.news.*'),
          'permissions' => ['news.create', 'news.edit_own'],
      ],
      [
          'label' => __('librarian.nav.reports'),
          'icon' => 'analytics',
          'href' => route('librarian.reports.index'),
          'active' => request()->routeIs('librarian.reports.*'),
          'permissions' => ['reports.view_ops', 'reports.view_full'],
      ],
      [
          'label' => __('librarian.nav.messages'),
          'icon' => 'mail',
          'href' => route('librarian.messages.index'),
          'active' => request()->routeIs('librarian.messages.*'),
          'permissions' => ['messages.view_all'],
      ],
  ])->filter(
      fn (array $item): bool => $item['permissions'] === [] || ($staffUser?->canAny($item['permissions']) ?? false),
  )->values()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLang }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', __('brand.workspace.'.$workspaceRole).' — '.__('brand.library.name'))</title>
  @include('partials.favicons')
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="stylesheet" href="/fonts/fonts.css">
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
    [x-cloak] { display: none !important; }
    /* Shared control-plane utility classes — identical to the admin console so
       the x-admin.* components render the same way in both workspaces. */
    .admin-input {
        width: 100%; border: 1px solid #d8dade; background: #fff; border-radius: .5rem;
        padding: .68rem .8rem; font-size: .875rem; color: #191c1d;
    }
    .admin-input:focus { border-color: #006a6a; box-shadow: 0 0 0 2px rgba(0,106,106,.12); outline: none; }
    .admin-label { display: block; margin-bottom: .4rem; color: #43474e; font-size: .72rem; font-weight: 700; letter-spacing: .055em; text-transform: uppercase; }
    .admin-card { border-radius: .75rem; background: #fff; padding: 1.5rem; box-shadow: 0 12px 35px rgba(0,6,19,.035); }
    .admin-btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; border-radius: .5rem; padding: .68rem 1rem; font-size: .875rem; font-weight: 700; transition: .2s ease; }
    .admin-btn-primary { color: #fff; background: #001f3f; }
    .admin-btn-primary:hover { background: #000613; }
    .admin-btn-secondary { color: #006a6a; background: #fff; border: 1px solid #d8dade; }
    .admin-btn-secondary:hover { background: #f3f4f5; }
    .admin-btn-danger { color: #93000a; background: #fff; border: 1px solid #ffc7c2; }
    .admin-btn-danger:hover { background: #fff0ee; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { padding: .85rem 1rem; text-align: left; background: #f3f4f5; color: #43474e; font-size: .7rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
    .admin-table td { padding: 1rem; border-bottom: 1px solid #edeeef; vertical-align: top; font-size: .875rem; }
    .admin-table tbody tr:hover { background: rgba(243,244,245,.65); }
    details > summary { list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
  </style>
  @yield('head')
</head>
<body class="bg-surface text-on-surface antialiased flex min-h-screen">
  <aside class="bg-surface-container-low text-primary font-body h-screen w-72 flex-shrink-0 fixed left-0 top-0 hidden md:flex flex-col z-40 py-8 border-r-0">
    <div class="px-5 mb-7">
      <x-library-brand variant="sidebar" :href="route('librarian.overview')" />
      <div class="mt-4 border-t border-surface-container-high pt-3 pl-1" data-workspace-role>
        <div class="text-[10px] font-bold uppercase tracking-[.15em] text-secondary">{{ __('brand.workspace.label') }}</div>
        <div class="mt-1 text-sm font-bold text-primary-container">{{ __('brand.workspace.'.$workspaceRole) }}</div>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 space-y-1" aria-label="{{ __('shell.librarian.navigation') }}">
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
            <span class="ml-auto text-[10px] uppercase tracking-wider text-on-surface-variant/70 font-semibold">{{ __('shell.librarian.coming_soon') }}</span>
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
      @can('circulation.issue')
        <a href="{{ route('librarian.circulation.issue') }}" class="w-full bg-gradient-to-r from-primary to-primary-container text-on-primary py-3 rounded-md font-semibold hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
          <span class="material-symbols-outlined">add</span>
          <span>{{ __('librarian.nav.new_transaction') }}</span>
        </a>
      @endcan
      <div class="pt-4 border-t border-surface-container-high border-opacity-50">
        @can('system.settings')
          <a href="/admin/settings" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors">
            <span class="material-symbols-outlined">settings</span>
            <span>{{ __('librarian.nav.settings') }}</span>
          </a>
        @endcan
        @if ($staffUser?->hasAnyRole(['librarian', 'admin']))
          <a href="/admin/profile" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors">
            <span class="material-symbols-outlined">account_circle</span>
            <span>{{ __('admin.profile.title') }}</span>
          </a>
        @endif
        <button id="librarian-logout-btn" type="button" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors w-full text-left">
          <span class="material-symbols-outlined">logout</span>
          <span>{{ __('librarian.nav.logout') }}</span>
        </button>
      </div>
    </div>
  </aside>

  <main class="flex-1 md:ml-72 flex flex-col min-h-screen bg-surface relative">
    <header class="bg-surface-container-low/80 backdrop-blur-md text-primary tracking-tight w-full top-0 sticky z-30 flex justify-between items-center px-8 py-4 h-20 flex-shrink-0">
      <div class="flex items-center gap-6">
        <x-library-brand variant="compact" :href="route('librarian.overview')" />
        @can('catalog.search')
          <form method="GET" action="{{ route('librarian.catalog.index') }}" class="relative ml-4 md:ml-12 hidden sm:block" role="search">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="material-symbols-outlined text-outline text-[18px]">search</span>
            </div>
            <input
              class="bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 text-sm py-2 pl-10 pr-4 w-64 md:w-80 rounded-t-md transition-colors placeholder:text-outline/70"
              placeholder="{{ __('librarian.nav.search_placeholder') }}"
              aria-label="{{ __('librarian.nav.search_placeholder') }}"
              name="search"
              value="{{ request('search') }}"
              type="search"
            />
          </form>
        @endcan
      </div>

      <div class="flex items-center gap-2 md:gap-4">
        <x-locale-switcher variant="light" />
        @php
          $librarianUnread = $staffUser
              ? \App\Models\Catalog\ReaderNotification::query()->where('user_id', $staffUser->getKey())->whereNull('read_at')->count()
              : 0;
        @endphp
        @can('messages.view_all')
          <a href="{{ route('librarian.messages.index') }}" class="relative text-slate-500 hover:text-primary-container hover:bg-slate-100 p-2 rounded-full transition-colors duration-300" aria-label="{{ __('librarian.nav.messages') }}">
            <span class="material-symbols-outlined">notifications</span>
            @if ($librarianUnread > 0)
              <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white">{{ $librarianUnread > 9 ? '9+' : $librarianUnread }}</span>
            @endif
          </a>
        @endcan
        @can('system.logs')
          <a href="/admin/logs" class="text-slate-500 hover:text-primary-container hover:bg-slate-100 p-2 rounded-full transition-colors duration-300 hidden sm:block" aria-label="{{ __('admin.nav.audit_logs') }}">
            <span class="material-symbols-outlined">history_edu</span>
          </a>
        @endcan
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
