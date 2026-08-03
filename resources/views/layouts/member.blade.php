@php
  /** @var array|null $memberReader */
  $memberReader = $memberReader ?? session('library.user');

  $displayName = $memberReader['display_name'] ?? ($memberReader['name'] ?? ($memberReader['full_name'] ?? ($memberReader['login'] ?? __('roles.names.member'))));
  $profileType = $memberReader['profile_type'] ?? 'student';
  $profileLabel = __('shell.member.profile_types.'.(in_array($profileType, ['student', 'teacher', 'employee'], true) ? $profileType : 'student'));
  $initial = mb_strtoupper(mb_substr((string) $displayName, 0, 1));

  // Real unread in-app notification count (Master.md §15.6) for the bell/badge.
  $memberUnreadNotifications = 0;
  $memberAccessibility = [];
  if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('reader_notifications')) {
      $memberUnreadNotifications = \App\Models\Catalog\ReaderNotification::query()
          ->where('user_id', auth()->id())
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
      ],
      [
          'label' => __('librarian.member_portal.nav.loans'),
          'icon' => 'book',
          'href' => route('member.loans'),
          'active' => request()->routeIs('member.loans'),
      ],
      [
          'label' => __('librarian.member_portal.nav.card'),
          'icon' => 'badge',
          'href' => route('member.card'),
          'active' => request()->routeIs('member.card'),
      ],
      [
          'label' => __('librarian.member_portal.nav.reservations'),
          'icon' => 'book_online',
          'href' => route('member.reservations'),
          'active' => request()->routeIs('member.reservations'),
      ],
      [
          'label' => __('librarian.member_portal.nav.collections'),
          'icon' => 'bookmark',
          'href' => route('member.collections.index'),
          'active' => request()->routeIs('member.collections.*'),
      ],
      [
          'label' => __('librarian.member_portal.nav.history'),
          'icon' => 'history',
          'href' => route('member.history'),
          'active' => request()->routeIs('member.history'),
      ],
      [
          'label' => __('librarian.member_portal.nav.fines'),
          'icon' => 'payments',
          'href' => route('member.fines'),
          'active' => request()->routeIs('member.fines'),
      ],
      [
          'label' => __('incidents.nav'),
          'icon' => 'report_problem',
          'href' => route('member.incidents.index'),
          'active' => request()->routeIs('member.incidents.*'),
      ],
      [
          'label' => __('librarian.member_portal.nav.notifications'),
          'icon' => 'notifications',
          'href' => route('member.notifications'),
          'active' => request()->routeIs('member.notifications'),
          'badge' => $memberUnreadNotifications,
      ],
      [
          'label' => __('librarian.member_portal.nav.digital'),
          'icon' => 'devices',
          'href' => route('member.digital-materials'),
          'active' => request()->routeIs('member.digital-materials'),
      ],
      [
          'label' => __('librarian.member_portal.nav.messages'),
          'icon' => 'chat_bubble',
          'href' => route('member.messages'),
          'active' => request()->routeIs('member.messages'),
      ],
      [
          'label' => __('librarian.member_portal.nav.profile'),
          'icon' => 'manage_accounts',
          'href' => route('member.profile'),
          'active' => request()->routeIs('member.profile*'),
      ],
  ];
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
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'surface': '#f8f9fa',
            'surface-bright': '#f8f9fa',
            'surface-dim': '#d9dadb',
            'surface-variant': '#e1e3e4',
            'surface-container-lowest': '#ffffff',
            'surface-container-low': '#f3f4f5',
            'surface-container': '#edeeef',
            'surface-container-high': '#e7e8e9',
            'surface-container-highest': '#e1e3e4',
            'background': '#f8f9fa',
            'primary': '#000613',
            'primary-container': '#001f3f',
            'on-primary': '#ffffff',
            'on-primary-container': '#6f88ad',
            'primary-fixed': '#d4e3ff',
            'primary-fixed-dim': '#afc8f0',
            'on-primary-fixed': '#001c3a',
            'secondary': '#006a6a',
            'on-secondary': '#ffffff',
            'secondary-container': '#90efef',
            'on-secondary-container': '#006e6e',
            'secondary-fixed': '#93f2f2',
            'secondary-fixed-dim': '#76d6d5',
            'on-secondary-fixed': '#002020',
            'on-secondary-fixed-variant': '#004f4f',
            'tertiary': '#000610',
            'tertiary-container': '#0d2031',
            'tertiary-fixed': '#d1e4fb',
            'tertiary-fixed-dim': '#b5c8df',
            'on-tertiary-fixed': '#091d2e',
            'on-tertiary-fixed-variant': '#36485b',
            'on-tertiary-container': '#76889d',
            'on-tertiary': '#ffffff',
            'error': '#ba1a1a',
            'error-container': '#ffdad6',
            'on-error': '#ffffff',
            'on-error-container': '#93000a',
            'outline': '#74777f',
            'outline-variant': '#c4c6cf',
            'on-surface': '#191c1d',
            'on-surface-variant': '#43474e',
            'inverse-surface': '#2e3132',
            'inverse-on-surface': '#f0f1f2',
            'inverse-primary': '#afc8f0',
          },
          borderRadius: {
            DEFAULT: '0.125rem',
            lg: '0.25rem',
            xl: '0.5rem',
            full: '0.75rem',
          },
          fontFamily: {
            headline: ['Newsreader', 'serif'],
            body: ['Manrope', 'sans-serif'],
            label: ['Manrope', 'sans-serif'],
          },
        },
      },
    };
  </script>
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
<body class="{{ $memberAccessibilityClasses }} bg-surface text-on-surface font-body antialiased min-h-screen selection:bg-secondary-container selection:text-on-secondary-container">

  <!-- Top Navigation -->
  <nav class="fixed top-0 inset-x-0 z-40 bg-white/80 backdrop-blur-lg h-20 flex items-center justify-between px-6 md:px-12 shadow-[0_24px_48px_rgba(0,31,63,0.04)]">
    <div class="flex items-center gap-6 md:gap-12">
      <x-library-brand variant="cabinet" />
      <form action="{{ route('member.search') }}" method="GET" class="hidden md:flex relative w-80 bg-surface-container-highest h-10 items-center px-4 rounded-t-DEFAULT border-b border-outline-variant/20 focus-within:border-secondary transition-colors">
        <span class="material-symbols-outlined text-outline mr-3 text-[20px]">search</span>
        <input name="q" type="search" autocomplete="off" aria-label="{{ __('librarian.member_portal.search.query') }}" placeholder="{{ __('librarian.member_portal.search.placeholder') }}" class="bg-transparent border-none outline-none w-full text-sm text-on-surface-variant font-body placeholder:text-outline placeholder:font-light focus:ring-0" />
      </form>
    </div>
    <div class="flex items-center gap-4 md:gap-6">
      <x-locale-switcher variant="light" />
      <a href="{{ route('member.search') }}" class="hidden md:inline-flex items-center gap-2 text-sm font-body text-on-surface-variant hover:text-secondary transition-colors">
        <span class="material-symbols-outlined text-[20px]">local_library</span>
        <span>{{ __('librarian.member_portal.search.title') }}</span>
      </a>
      <a href="{{ route('member.notifications') }}"
         class="relative inline-flex h-10 w-10 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-secondary"
         title="{{ __('librarian.member.notifications.title') }}"
         aria-label="{{ $memberUnreadNotifications > 0 ? __('librarian.member.notifications.unread_count', ['count' => $memberUnreadNotifications]) : __('librarian.member.notifications.title') }}">
        <span class="material-symbols-outlined text-[22px]" @if (request()->routeIs('member.notifications')) style="font-variation-settings: 'FILL' 1;" @endif>notifications</span>
        @if ($memberUnreadNotifications > 0)
          <span class="absolute -right-0.5 -top-0.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full border-2 border-white bg-secondary px-1 text-[10px] font-bold leading-none text-on-secondary">{{ $memberUnreadNotifications > 99 ? '99+' : $memberUnreadNotifications }}</span>
        @endif
      </a>
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
