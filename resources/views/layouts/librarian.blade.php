@php
  $pageLang = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'ru';
  $staffUser = auth()->user();
  $librarianUser = is_array($librarianStaffUser ?? null) ? $librarianStaffUser : [];
  $userName = trim((string) ($staffUser?->name ?? $librarianUser['name'] ?? __('shell.librarian.operator'))) ?: __('shell.librarian.operator');
  $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
  $loginRedirectUrl = '/login';
  $librarianRole = mb_strtolower(trim((string) ($staffUser?->effectiveRole() ?? $librarianUser['canonical_role'] ?? $librarianUser['role'] ?? 'librarian')));
  $workspaceRole = match ($librarianRole) {
      'director' => 'director',
      'senior_librarian' => 'senior_librarian',
      'acquisitions' => 'acquisitions',
      'cataloguer' => 'cataloguer',
      'bibliographer' => 'bibliographer',
      default => 'librarian',
  };
  $userTitle = __('brand.workspace.'.$workspaceRole);

  // Canonical librarian navigation. Every entry maps to a real controller
  // route; items the current staff account lacks permission for are filtered
  // out rather than shown as dead links.
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
          'label' => match($pageLang) { 'ru' => 'Читатели', 'en' => 'Readers', default => 'Оқырмандар' },
          'icon' => 'group',
          'href' => route('librarian.readers.index'),
          'active' => request()->routeIs('librarian.readers.*'),
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
          'label' => __('workspace.sections.search.short'), 'icon' => 'search',
          'href' => route('librarian.workspace.search'), 'active' => request()->routeIs('librarian.workspace.search'),
          'permissions' => ['catalog.search'],
      ],
      [
          'label' => __('librarian.nav.data_cleanup'),
          'icon' => 'fact_check',
          'href' => route('librarian.data-quality.index'),
          'active' => request()->routeIs('librarian.data-quality.*') || request()->routeIs('librarian.data-cleanup*'),
          'permissions' => ['data_quality.view'],
      ],
      [
          'label' => __('workspace.sections.tasks.short'), 'icon' => 'task_alt',
          'href' => route('librarian.workspace.tasks'), 'active' => request()->routeIs('librarian.workspace.tasks*'),
          'permissions' => ['tasks.view'],
      ],
      [
          'label' => __('workspace.sections.orders.short'), 'icon' => 'shopping_cart',
          'href' => route('librarian.workspace.orders'), 'active' => request()->routeIs('librarian.workspace.orders*'),
          'permissions' => ['acquisitions.view'],
      ],
      [
          'label' => __('workspace.sections.movements.short'), 'icon' => 'sync_alt',
          'href' => route('librarian.workspace.movements'), 'active' => request()->routeIs('librarian.workspace.movements'),
          'permissions' => ['catalog.search', 'copies.edit'],
      ],
      [
          'label' => __('workspace.sections.edd.short'), 'icon' => 'request_quote',
          'href' => route('librarian.workspace.edd'), 'active' => request()->routeIs('librarian.workspace.edd*'),
          'permissions' => ['edd.view'],
      ],
      [
          'label' => __('workspace.sections.calendar.short'), 'icon' => 'calendar_month',
          'href' => route('librarian.workspace.calendar'), 'active' => request()->routeIs('librarian.workspace.calendar'),
          'permissions' => ['calendar.view'],
      ],
      [
          'label' => __('workspace.sections.periodicals.short'), 'icon' => 'newsstand',
          'href' => route('librarian.workspace.periodicals'), 'active' => request()->routeIs('librarian.workspace.periodicals*'),
          'permissions' => ['periodicals.view'],
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
          'label' => __('librarian.nav.repository'),
          'icon' => 'school',
          'href' => route('librarian.repository'),
          'active' => request()->routeIs('librarian.repository') || request()->routeIs('librarian.repository.*'),
          'permissions' => ['repository.upload', 'repository.update', 'repository.review_metadata', 'repository.review_rights', 'repository.request_changes', 'repository.approve', 'repository.publish', 'repository.withdraw'],
      ],
      [
          'label' => __('librarian.nav.digital_materials'),
          'icon' => 'picture_as_pdf',
          'href' => route('librarian.digital-materials.index'),
          'active' => request()->routeIs('librarian.digital-materials.*'),
          'permissions' => ['digital.upload', 'digital.review_metadata', 'digital.review_rights', 'digital.approve', 'digital.publish'],
      ],
      [
          'label' => __('librarian.nav.external_resources_review'),
          'icon' => 'fact_check',
          'href' => route('librarian.external-resources.review'),
          'active' => request()->routeIs('librarian.external-resources.*'),
          'permissions' => ['external_resources.review', 'external_resources.publish'],
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
          'permissions' => [
              'reports.view_acquisitions', 'reports.view_ops', 'reports.view_full', 'fines.view',
              'incidents.view_reports', 'data_quality.view_reports', 'data_quality.view',
              'news.view_analytics', 'messages.view_analytics', 'repository.view_analytics',
              'external_resources.view_analytics', 'staff_performance.view', 'system.logs',
          ],
      ],
      [
          'label' => __('librarian.nav.messages'),
          'icon' => 'mail',
          'href' => route('librarian.messages.index'),
          'active' => request()->routeIs('librarian.messages.*'),
          'permissions' => ['messages.view_all', 'messages.view_assigned'],
      ],
  ])->filter(
      fn (array $item): bool => $item['permissions'] === [] || ($staffUser?->canAny($item['permissions']) ?? false),
  )->values()->all();

  $quickActions = collect([
      ['label' => match($pageLang) { 'ru' => 'Выдать книгу', 'en' => 'Check out an item', default => 'Кітап беру' }, 'icon' => 'outbox', 'href' => route('librarian.circulation.issue'), 'permission' => 'circulation.issue'],
      ['label' => match($pageLang) { 'ru' => 'Принять книгу', 'en' => 'Check in an item', default => 'Кітапты қабылдау' }, 'icon' => 'move_to_inbox', 'href' => route('librarian.circulation.return'), 'permission' => 'circulation.return'],
      ['label' => match($pageLang) { 'ru' => 'Зарегистрировать читателя', 'en' => 'Register a patron', default => 'Оқырманды тіркеу' }, 'icon' => 'person_add', 'href' => route('librarian.readers.index'), 'permission' => 'circulation.issue'],
      ['label' => match($pageLang) { 'ru' => 'Добавить запись', 'en' => 'Add a record', default => 'Жазба қосу' }, 'icon' => 'library_add', 'href' => route('librarian.catalog.create'), 'permission' => 'catalog.create_record'],
      ['label' => match($pageLang) { 'ru' => 'Добавить экземпляр', 'en' => 'Add a copy', default => 'Дана қосу' }, 'icon' => 'add_box', 'href' => route('librarian.copies.create'), 'permission' => 'copies.create'],
      ['label' => match($pageLang) { 'ru' => 'Отметить посещение', 'en' => 'Record a visit', default => 'Келуді белгілеу' }, 'icon' => 'sensor_door', 'href' => route('librarian.visits.index'), 'permission' => 'visits.record'],
      ['label' => match($pageLang) { 'ru' => 'Открыть бронирования', 'en' => 'Open reservations', default => 'Брондауларды ашу' }, 'icon' => 'bookmark_manager', 'href' => route('librarian.reservations.index'), 'permission' => 'reservation.confirm'],
  ])->filter(fn (array $action): bool => $staffUser?->can($action['permission']) ?? false)->values()->all();
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
      @if($quickActions !== [])
        <details class="relative" data-operation-menu>
          <summary class="w-full cursor-pointer list-none bg-gradient-to-r from-primary to-primary-container text-on-primary py-3 rounded-md font-semibold hover:opacity-90 transition-opacity flex items-center justify-center gap-2" aria-label="{{ __('librarian.nav.new_transaction') }}">
            <span class="material-symbols-outlined">add</span>
            <span>{{ __('librarian.nav.new_transaction') }}</span>
          </summary>
          <div class="absolute bottom-[calc(100%+.5rem)] left-0 z-50 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
            @foreach($quickActions as $action)<a href="{{ $action['href'] }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><span class="material-symbols-outlined text-[19px]">{{ $action['icon'] }}</span><span>{{ $action['label'] }}</span></a>@endforeach
          </div>
        </details>
      @endif
      <div class="pt-4 border-t border-surface-container-high border-opacity-50">
        <a href="{{ route('librarian.profile.show') }}" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors">
          <span class="material-symbols-outlined">account_circle</span>
          <span>{{ __('librarian.staff_profile.my_profile') }}</span>
        </a>
        @can('system.settings')
          <a href="/admin/settings" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors">
            <span class="material-symbols-outlined">settings</span>
            <span>{{ __('librarian.nav.settings') }}</span>
          </a>
        @endcan
        @if ($staffUser?->hasRole('admin'))
          <a href="/admin/profile" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors">
            <span class="material-symbols-outlined">account_circle</span>
            <span>{{ __('admin.profile.title') }}</span>
          </a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button id="librarian-logout-btn" type="submit" class="text-slate-600 py-2 flex items-center gap-3 hover:text-secondary transition-colors w-full text-left">
            <span class="material-symbols-outlined">logout</span>
            <span>{{ __('librarian.nav.logout') }}</span>
          </button>
        </form>
      </div>
    </div>
  </aside>

  <main class="min-w-0 flex-1 overflow-x-hidden md:ml-72 flex flex-col min-h-screen bg-surface relative">
    <header class="bg-surface-container-low/80 backdrop-blur-md text-primary tracking-tight w-full top-0 sticky z-30 flex justify-between items-center px-4 md:px-8 py-4 h-20 flex-shrink-0">
      <div class="flex items-center gap-6">
        <x-library-brand variant="compact" :href="route('librarian.overview')" />
        @can('catalog.search')
          <form method="GET" action="{{ route('librarian.workspace.search') }}" class="relative ml-12 hidden xl:block" role="search" data-global-search>
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <span class="material-symbols-outlined text-outline text-[18px]">search</span>
            </div>
            <input
              class="bg-surface-container-highest border-0 border-b border-outline-variant/20 focus:border-secondary focus:ring-0 text-sm py-2 pl-10 pr-4 w-64 md:w-80 rounded-t-md transition-colors placeholder:text-outline/70"
              placeholder="{{ __('librarian.nav.search_placeholder') }}"
              aria-label="{{ __('librarian.nav.search_placeholder') }}"
              name="q"
              value="{{ request()->routeIs('librarian.workspace.search') ? request('q') : '' }}"
              type="search"
            />
          </form>
        @endcan
      </div>

      <div class="flex items-center gap-2 md:gap-4">
        <details class="relative md:hidden" data-mobile-navigation>
          <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-200 bg-white text-primary-container" aria-label="{{ __('shell.librarian.navigation') }}"><span class="material-symbols-outlined">menu</span></summary>
          <div class="fixed inset-x-4 top-[4.5rem] z-50 max-h-[calc(100vh-5.5rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 shadow-2xl">
            <nav class="space-y-1" aria-label="{{ __('shell.librarian.navigation') }}">
              @foreach($librarianNav as $item)<a href="{{ $item['href'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ $item['active'] ? 'bg-slate-100 font-bold text-secondary' : 'text-slate-700' }}"><span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span></a>@endforeach
            </nav>
            @if($quickActions !== [])<div class="mt-3 border-t pt-3"><div class="mb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ __('librarian.nav.new_transaction') }}</div>@foreach($quickActions as $action)<a href="{{ $action['href'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-700"><span class="material-symbols-outlined text-[20px]">{{ $action['icon'] }}</span><span>{{ $action['label'] }}</span></a>@endforeach</div>@endif
          </div>
        </details>
        <x-locale-switcher variant="light" />
        @php
          $librarianUnread = $staffUser
              ? \App\Models\Catalog\ReaderNotification::query()->where('user_id', $staffUser->getKey())->whereNull('read_at')->count()
              : 0;
        @endphp
        @canany(['messages.view_all', 'messages.view_assigned'])
          <a href="{{ route('librarian.messages.index') }}" class="relative text-slate-500 hover:text-primary-container hover:bg-slate-100 p-2 rounded-full transition-colors duration-300" aria-label="{{ __('librarian.nav.messages') }}">
            <span class="material-symbols-outlined">notifications</span>
            @if ($librarianUnread > 0)
              <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white">{{ $librarianUnread > 9 ? '9+' : $librarianUnread }}</span>
            @endif
          </a>
        @endcanany
        @can('system.logs')
          <a href="/admin/logs" class="text-slate-500 hover:text-primary-container hover:bg-slate-100 p-2 rounded-full transition-colors duration-300 hidden sm:block" aria-label="{{ __('admin.nav.audit_logs') }}">
            <span class="material-symbols-outlined">history_edu</span>
          </a>
        @endcan
        <a href="{{ route('librarian.profile.show') }}" class="h-9 w-9 ml-2 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-sm font-bold transition-opacity hover:opacity-85 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-secondary" aria-label="{{ __('librarian.staff_profile.open_profile') }}" title="{{ $userName }} — {{ $userTitle }}">{{ $userInitial }}</a>
      </div>
    </header>

    <div class="min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-4 md:px-12 py-8 lg:py-12 pb-24">
      @yield('content')
    </div>
  </main>

  <script>
    document.addEventListener('keydown', (event) => {
      const target = event.target;
      const isEditing = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement || target?.isContentEditable;
      if ((event.key === '/' && !isEditing) || (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey))) {
        event.preventDefault();
        document.querySelector('[data-global-search] input[name="q"]')?.focus();
      }
    });
  </script>
  @stack('scripts')
</body>
</html>
