@php
  /** @var array|null $memberReader */
  $memberReader = $memberReader ?? session('library.user');
  $memberUser = auth()->user();

  $displayName = $memberReader['display_name'] ?? ($memberReader['name'] ?? ($memberReader['full_name'] ?? ($memberReader['login'] ?? __('roles.names.member'))));
  $profileType = mb_strtolower(trim((string) ($memberReader['profile_type'] ?? 'member')));
  $profileLabel = in_array($profileType, ['student', 'teacher', 'employee'], true)
      ? __('shell.member.profile_types.'.$profileType)
      : __('roles.names.member');
  $initial = mb_strtoupper(mb_substr((string) $displayName, 0, 1));

  // Real unread in-app notification count (Master.md 15.6) for the bell/badge.
  $memberUnreadNotifications = 0;
  $memberAccessibility = [];
  if ($memberUser?->can('notifications.view_own') && \Illuminate\Support\Facades\Schema::hasTable('reader_notifications')) {
      $memberUnreadNotifications = \App\Models\Catalog\ReaderNotification::query()
          ->where('user_id', $memberUser->getKey())
          ->whereNull('read_at')
          ->count();
  }
  if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('reader_profiles')) {
      $memberAccessibility = auth()->user()->readerProfile?->accessibility_preferences ?? [];
  }
  $memberAccessibilityClasses = collect([
      data_get($memberAccessibility, 'high_contrast') ? 'member-high-contrast' : null,
      data_get($memberAccessibility, 'large_text') ? 'member-large-text' : null,
  ])->filter()->implode(' ');

  $memberNav = [
      [
          'label' => __('librarian.member_portal.nav.dashboard'),
          'icon' => 'dashboard',
          'href' => route('member.dashboard'),
          'active' => request()->routeIs('member.dashboard'),
          'permission' => 'member.dashboard.view',
      ],
      [
          'label' => __('librarian.member_portal.nav.loans'),
          'icon' => 'book',
          'href' => route('member.loans'),
          'active' => request()->routeIs('member.loans'),
          'permission' => 'loans.view_own',
      ],
      [
          'label' => __('librarian.member_portal.nav.card'),
          'icon' => 'badge',
          'href' => route('member.card'),
          'active' => request()->routeIs('member.card'),
          'permission' => 'reader_card.view_own',
      ],
      [
          'label' => __('librarian.member_portal.nav.reservations'),
          'icon' => 'book_online',
          'href' => route('member.reservations'),
          'active' => request()->routeIs('member.reservations'),
          'permission' => 'reservation.view_own',
      ],
      [
          'label' => __('librarian.member_portal.nav.collections'),
          'icon' => 'bookmark',
          'href' => route('member.collections.index'),
          'active' => request()->routeIs('member.collections.*'),
          'permission' => ['collections.manage_own', 'collections.view_public'],
      ],
      [
          'label' => __('librarian.member_portal.nav.history'),
          'icon' => 'history',
          'href' => route('member.history'),
          'active' => request()->routeIs('member.history'),
          'permission' => 'circulation.view_own_history',
      ],
      [
          'label' => __('librarian.member_portal.nav.fines'),
          'icon' => 'payments',
          'href' => route('member.fines'),
          'active' => request()->routeIs('member.fines'),
          'permission' => 'fines.view_own',
      ],
      [
          'label' => __('incidents.nav'),
          'icon' => 'report_problem',
          'href' => route('member.incidents.index'),
          'active' => request()->routeIs('member.incidents.*'),
          'permission' => 'incidents.view_own',
      ],
      [
          'label' => __('librarian.member_portal.nav.notifications'),
          'icon' => 'notifications',
          'href' => route('member.notifications'),
          'active' => request()->routeIs('member.notifications'),
          'badge' => $memberUnreadNotifications,
          'permission' => 'notifications.view_own',
      ],
      [
          'label' => __('librarian.member_portal.nav.digital'),
          'icon' => 'devices',
          'href' => route('member.digital-materials'),
          'active' => request()->routeIs('member.digital-materials'),
          'permission' => 'digital.view_metadata',
      ],
      [
          'label' => __('librarian.member_portal.nav.messages'),
          'icon' => 'chat_bubble',
          'href' => route('member.messages'),
          'active' => request()->routeIs('member.messages'),
          'permission' => 'messages.view_own',
      ],
      [
          'label' => __('librarian.member_portal.nav.profile'),
          'icon' => 'manage_accounts',
          'href' => route('member.profile'),
          'active' => request()->routeIs('member.profile*'),
      ],
  ];
  $memberNav = collect($memberNav)->filter(function (array $item) use ($memberUser): bool {
      $permissions = \Illuminate\Support\Arr::wrap($item['permission'] ?? []);

      return $permissions === []
          || ($memberUser !== null && collect($permissions)->contains(fn (string $permission): bool => $memberUser->can($permission)));
  })->values()->all();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title ?? __('brand.workspace.reader').' — '.__('brand.library.name') }}</title>
  @include('partials.favicons')
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <link rel="stylesheet" href="/fonts/fonts.css" />
  @vite('resources/css/app.css')
  <style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #e1e3e4; border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: #c4c6cf; }
    .member-large-text { font-size: 112.5%; }
    .member-high-contrast { --tw-text-opacity: 1; filter: contrast(1.14); }
    .member-high-contrast a:focus-visible,
    .member-high-contrast button:focus-visible,
    .member-high-contrast input:focus-visible,
    .member-high-contrast select:focus-visible { outline: 3px solid #006a6a; outline-offset: 3px; }
  </style>
</head>
<body class="{{ $memberAccessibilityClasses }} bg-surface text-on-surface font-body antialiased min-h-screen selection:bg-secondary-container selection:text-on-secondary-container" data-library-tailwind-radius="compact">

  <!-- Top Navigation -->
  <nav class="fixed top-0 inset-x-0 z-40 bg-white/80 backdrop-blur-lg h-20 flex items-center justify-between px-6 md:px-12 shadow-[0_24px_48px_rgba(0,31,63,0.04)]">
    <div class="flex items-center gap-6 md:gap-12">
      <x-library-brand variant="cabinet" />
      @can('catalog.search')
        <form action="{{ route('member.search') }}" method="GET" class="hidden md:flex relative w-80 bg-surface-container-highest h-10 items-center px-4 rounded-t-DEFAULT border-b border-outline-variant/20 focus-within:border-secondary transition-colors">
          <span class="material-symbols-outlined text-outline mr-3 text-[20px]">search</span>
          <input name="q" type="search" autocomplete="off" aria-label="{{ __('librarian.member_portal.search.query') }}" placeholder="{{ __('librarian.member_portal.search.placeholder') }}" class="bg-transparent border-none outline-none w-full text-sm text-on-surface-variant font-body placeholder:text-outline placeholder:font-light focus:ring-0" />
        </form>
      @endcan
    </div>
    <div class="flex items-center gap-4 md:gap-6">
      <x-locale-switcher variant="light" />
      @can('catalog.search')
        <a href="{{ route('member.search') }}" class="hidden md:inline-flex items-center gap-2 text-sm font-body text-on-surface-variant hover:text-secondary transition-colors">
          <span class="material-symbols-outlined text-[20px]">local_library</span>
          <span>{{ __('librarian.member_portal.search.title') }}</span>
        </a>
      @endcan
      @can('notifications.view_own')
        <a href="{{ route('member.notifications') }}"
           class="relative inline-flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-secondary"
           title="{{ __('librarian.member.notifications.title') }}"
           aria-label="{{ $memberUnreadNotifications > 0 ? __('librarian.member.notifications.unread_count', ['count' => $memberUnreadNotifications]) : __('librarian.member.notifications.title') }}">
          <span class="material-symbols-outlined text-[22px]" @if (request()->routeIs('member.notifications')) style="font-variation-settings: 'FILL' 1;" @endif>notifications</span>
          @if ($memberUnreadNotifications > 0)
            <span class="absolute -right-0.5 -top-0.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full border-2 border-white bg-secondary px-1 text-[10px] font-bold leading-none text-on-secondary">{{ $memberUnreadNotifications > 99 ? '99+' : $memberUnreadNotifications }}</span>
          @endif
        </a>
      @endcan
      <div class="flex items-center gap-3">
        <div class="hidden sm:flex w-10 h-10 rounded-full bg-primary-container text-on-primary font-headline text-lg items-center justify-center" aria-hidden="true">{{ $initial }}</div>
        <div class="hidden md:flex flex-col leading-tight">
          <span class="text-sm font-body font-semibold text-primary">{{ $displayName }}</span>
          <span class="text-[11px] uppercase tracking-widest text-on-surface-variant">{{ $profileLabel }}</span>
        </div>
      </div>
      <form method="POST" action="/logout" class="hidden md:block">
        @csrf
        <button type="submit" class="text-sm font-body text-on-surface-variant hover:text-error transition-colors inline-flex items-center gap-1">
          <span class="material-symbols-outlined text-[18px]">logout</span>
          <span>{{ __('shell.member.sign_out') }}</span>
        </button>
      </form>
    </div>
  </nav>

  <!-- Side Navigation (desktop) -->
  <aside class="hidden md:flex fixed left-0 top-20 h-[calc(100vh-5rem)] w-72 bg-white/70 border-r border-surface-variant/60 flex-col py-8 z-30">
    <div class="px-8 pb-8 mb-4 border-b border-surface-variant/60">
      <div class="text-[10px] font-bold uppercase tracking-[.16em] text-secondary mb-1">{{ __('brand.workspace.label') }}</div>
      <div class="text-base font-bold text-primary">{{ __('brand.workspace.reader') }}</div>
    </div>
    <nav class="flex-1 flex flex-col space-y-1 w-full" aria-label="{{ __('shell.member.navigation') }}">
      @foreach ($memberNav as $item)
        @php
          $isDisabled = (bool) ($item['disabled'] ?? false);
          $classes = 'flex items-center gap-4 px-8 py-3 font-label text-sm uppercase tracking-widest transition-all duration-300 ease-out';
          if ($item['active']) {
              $classes .= ' text-secondary font-bold border-r-2 border-secondary bg-secondary/5';
          } elseif ($isDisabled) {
              $classes .= ' text-on-surface-variant/40 cursor-not-allowed';
          } else {
              $classes .= ' text-on-surface-variant hover:bg-surface-container-low hover:pl-10 hover:text-primary';
          }
        @endphp
        <a href="{{ $item['href'] }}"
           class="{{ $classes }}"
           @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif>
          <span class="material-symbols-outlined text-[20px]" @if ($item['active']) style="font-variation-settings: 'FILL' 1;" @endif>{{ $item['icon'] }}</span>
          <span>{{ $item['label'] }}</span>
          @if ((int) ($item['badge'] ?? 0) > 0)
            <span class="ml-auto inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-secondary px-1.5 text-[11px] font-bold leading-none text-on-secondary normal-case tracking-normal"
                  aria-label="{{ __('librarian.member.notifications.unread_count', ['count' => $item['badge']]) }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
          @endif
          @if ($isDisabled)
            <span class="ml-auto text-[10px] tracking-wider text-outline normal-case">{{ __('shell.librarian.coming_soon') }}</span>
          @endif
        </a>
      @endforeach
    </nav>
    <div class="px-8 pt-6 border-t border-surface-variant/60">
      <a href="/catalog" class="w-full bg-gradient-to-r from-primary to-primary-container text-on-primary py-3 rounded-md font-label text-sm uppercase tracking-widest hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
        <span class="material-symbols-outlined text-[18px]">search</span>
        <span>{{ __('shell.member.browse_catalog') }}</span>
      </a>
    </div>
  </aside>

  <!-- Mobile bottom nav -->
  <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-surface-container-lowest shadow-[0_-8px_24px_rgba(0,6,19,0.06)] flex justify-around items-center h-16">
    @foreach (array_slice($memberNav, 0, 4) as $item)
      @php $isDisabled = (bool) ($item['disabled'] ?? false); @endphp
      <a href="{{ $item['href'] }}" class="flex flex-col items-center gap-1 p-2 transition-colors {{ $item['active'] ? 'text-secondary font-bold' : ($isDisabled ? 'text-outline/50' : 'text-on-surface-variant hover:text-primary') }}">
        <span class="material-symbols-outlined text-[22px]" @if ($item['active']) style="font-variation-settings: 'FILL' 1;" @endif>{{ $item['icon'] }}</span>
        <span class="text-[10px] uppercase tracking-wider">{{ $item['label'] }}</span>
      </a>
    @endforeach
  </nav>

  <!-- Content canvas -->
  <main class="pt-28 pb-24 md:pb-16 md:ml-72 px-6 md:px-12 lg:px-16 max-w-7xl mx-auto">
    @yield('content')
  </main>

</body>
</html>
