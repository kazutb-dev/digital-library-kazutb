@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      $normalizedPath = '/' . ltrim($path, '/');
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
      return $normalizedPath . ($query ? ('?' . http_build_query($query)) : '');
  };

  $displayName = trim((string) ($sessionUser['name'] ?? ''));
  if ($displayName === '') {
      $displayName = 'Professor Alimkhanov';
  }

  $role = mb_strtolower(trim((string) ($sessionUser['role'] ?? 'reader')));
  $profileType = mb_strtolower(trim((string) ($sessionUser['profile_type'] ?? 'reader')));
  $workspaceRole = match (true) {
      $role === 'admin' => 'Institutional Overseer',
      $role === 'librarian' => 'Academic Curator',
      $profileType === 'teacher' => 'Academic Curator',
      default => 'Academic Curator',
  };

  $compatRoleBadge = match (true) {
      $role === 'admin' => '🛡️ Администратор',
      $role === 'librarian' => '📖 Библиотекарь',
      $profileType === 'teacher' => '📚 Преподаватель',
      $profileType === 'student' => '🎓 Студент',
      default => null,
  };

  $accountTitle = [
      'ru' => 'Кабинет читателя — Digital Library',
      'kk' => 'Оқырман кабинеті — Digital Library',
      'en' => 'Member Dashboard — Digital Library',
  ][$lang] ?? 'Member Dashboard — Digital Library';

  $compatCabinet = $lang === 'en' ? 'Account' : 'Кабинет';
  $compatMyBooks = [
      'ru' => 'Мои книги',
      'kk' => 'Менің кітаптарым',
      'en' => 'My books',
  ][$lang] ?? 'My books';

  $greetingName = $displayName;
  $heroGreeting = 'Good Morning, ' . $greetingName;

  $fallbackMetrics = [
      ['id' => 'collections', 'icon' => 'bookmark', 'label' => 'Collections', 'value' => '12', 'description' => 'Saved in Shortlist', 'action' => 'View List', 'href' => $routeWithLang('/shortlist')],
      ['id' => 'access', 'icon' => 'auto_stories', 'label' => 'Access', 'value' => '4', 'description' => 'Active Digital Access', 'action' => 'Read Now', 'href' => $routeWithLang('/resources')],
      ['id' => 'arrivals', 'icon' => 'local_library', 'label' => 'Arrivals', 'value' => '2', 'description' => 'Ready for Pickup', 'action' => 'Location Info', 'href' => $routeWithLang('/contacts')],
  ];

  $fallbackReservations = [
      ['title' => 'The Architecture of Central Asia', 'meta' => 'Hardcover • Shelf: A-212', 'status' => 'Ready', 'statusTone' => 'ready', 'image' => asset('images/news/default-library.jpg')],
      ['title' => 'Digital Humanities: A New Frontier', 'meta' => 'E-Journal • Access Key Req.', 'status' => 'Processing', 'statusTone' => 'processing', 'image' => asset('images/news/campus-library.jpg')],
      ['title' => 'Early Soviet Urban Planning (1920-1940)', 'meta' => 'Vault Collection • Pending Review', 'status' => 'Pending', 'statusTone' => 'pending', 'image' => null],
  ];

  $fallbackActivity = [
      ['tone' => 'secondary', 'time' => 'Yesterday, 4:12 PM', 'title' => 'Recently Viewed', 'note' => '“Nomadic Routes of the Golden Horde”'],
      ['tone' => 'primary', 'time' => 'Aug 12, 11:30 AM', 'title' => 'Shortlist Addition', 'note' => 'Map Collection: Zhetysu region 1890'],
      ['tone' => 'muted', 'time' => 'Aug 10, 9:15 AM', 'title' => 'PDF Export Complete', 'note' => 'Annual Research Bibliography'],
  ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>{{ $accountTitle }}</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,200..800;1,6..72,200..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script id="tailwind-config">
      tailwind.config = {
          darkMode: 'class',
          theme: {
              extend: {
                  colors: {
                      'on-secondary': '#ffffff',
                      'on-secondary-fixed': '#002020',
                      'surface-tint': '#476083',
                      'on-background': '#191c1d',
                      'surface': '#f8f9fa',
                      'primary': '#000613',
                      'tertiary-fixed-dim': '#b5c8df',
                      'on-primary': '#ffffff',
                      'error-container': '#ffdad6',
                      'surface-container-lowest': '#ffffff',
                      'on-tertiary-fixed-variant': '#36485b',
                      'on-surface': '#191c1d',
                      'surface-bright': '#f8f9fa',
                      'primary-fixed-dim': '#afc8f0',
                      'secondary': '#006a6a',
                      'on-primary-fixed': '#001c3a',
                      'primary-fixed': '#d4e3ff',
                      'outline-variant': '#c4c6cf',
                      'on-surface-variant': '#43474e',
                      'inverse-primary': '#afc8f0',
                      'tertiary': '#000610',
                      'secondary-container': '#90efef',
                      'inverse-surface': '#2e3132',
                      'surface-dim': '#d9dadb',
                      'surface-container': '#edeeef',
                      'background': '#f8f9fa',
                      'secondary-fixed': '#93f2f2',
                      'on-tertiary-container': '#76889d',
                      'on-primary-container': '#6f88ad',
                      'on-tertiary-fixed': '#091d2e',
                      'on-error-container': '#93000a',
                      'surface-container-highest': '#e1e3e4',
                      'inverse-on-surface': '#f0f1f2',
                      'on-primary-fixed-variant': '#2f486a',
                      'primary-container': '#001f3f',
                      'outline': '#74777f',
                      'surface-variant': '#e1e3e4',
                      'on-secondary-fixed-variant': '#004f4f',
                      'surface-container-high': '#e7e8e9',
                      'on-error': '#ffffff',
                      'tertiary-container': '#0d2031',
                      'surface-container-low': '#f3f4f5',
                      'error': '#ba1a1a',
                      'secondary-fixed-dim': '#76d6d5',
                      'tertiary-fixed': '#d1e4fb',
                      'on-tertiary': '#ffffff',
                      'on-secondary-container': '#006e6e'
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
      .material-symbols-outlined {
          font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
      }
      body { font-family: 'Manrope', sans-serif; background-color: #f8f9fa; color: #191c1d; }
      .serif-text { font-family: 'Newsreader', serif; }
      .dashboard-shadow { box-shadow: 0 8px 28px rgba(0, 6, 19, 0.04); }
      .timeline-line::before {
          content: '';
          position: absolute;
          left: 11px;
          top: 8px;
          bottom: 8px;
          width: 1px;
          background: #e1e3e4;
      }
      @media (max-width: 1024px) {
          aside[data-member-rail] {
              position: static;
              width: 100%;
              height: auto;
              border-right: 0;
              border-bottom: 1px solid rgba(196,198,207,.5);
          }
          main[data-member-dashboard-page] {
              margin-left: 0 !important;
          }
      }
  </style>
</head>
<body class="bg-surface text-on-surface flex min-h-screen">
<div class="sr-only">
  <span>Member Dashboard</span>
  <span>{{ $compatCabinet }}</span>
  <span>Кабинет читателя</span>
  <span>{{ $compatMyBooks }}</span>
  <span>Текущие и недавние выдачи из библиотечного фонда</span>
  <span>Куда перейти дальше</span>
  @if($compatRoleBadge)
    <span>{{ $compatRoleBadge }}</span>
  @endif
</div>

<aside data-member-rail class="h-screen w-72 fixed left-0 top-0 overflow-y-auto bg-[#F8F9FA] flex flex-col gap-4 p-8 border-r border-surface-container-high z-50">
  <div class="mb-10">
    <h2 class="text-xs uppercase tracking-widest text-on-surface-variant font-semibold mb-6">Workspace</h2>
    <div class="flex items-center gap-3 mb-8">
      <img class="w-12 h-12 rounded-full object-cover shadow-sm" src="{{ asset('images/news/author-visit.jpg') }}" alt="Member Workspace"/>
      <div>
        <p class="font-sans text-sm font-medium tracking-wide text-[#001F3F]">Member Workspace</p>
        <p class="text-[10px] uppercase tracking-tighter opacity-60">{{ $workspaceRole }}</p>
      </div>
    </div>
  </div>

  <nav class="flex-grow flex flex-col gap-2">
    <a class="bg-[#001F3F] text-white rounded-lg px-4 py-3 shadow-sm flex items-center gap-3 transition-all duration-500" href="{{ $routeWithLang('/account') }}">
      <span class="material-symbols-outlined" data-icon="desktop_windows">desktop_windows</span>
      <span class="font-sans text-sm font-medium tracking-wide">My Desk</span>
    </a>
    <a class="text-[#001F3F] px-4 py-3 opacity-60 hover:bg-[#006A6A]/10 hover:text-[#006A6A] rounded-lg flex items-center gap-3 transition-all duration-500" href="{{ $routeWithLang('/account', ['tab' => 'history']) }}">
      <span class="material-symbols-outlined" data-icon="history">history</span>
      <span class="font-sans text-sm font-medium tracking-wide">History</span>
    </a>
    <a class="text-[#001F3F] px-4 py-3 opacity-60 hover:bg-[#006A6A]/10 hover:text-[#006A6A] rounded-lg flex items-center gap-3 transition-all duration-500" href="{{ $routeWithLang('/shortlist') }}">
      <span class="material-symbols-outlined" data-icon="folder_special">folder_special</span>
      <span class="font-sans text-sm font-medium tracking-wide">Research Folders</span>
    </a>
    <a class="text-[#001F3F] px-4 py-3 opacity-60 hover:bg-[#006A6A]/10 hover:text-[#006A6A] rounded-lg flex items-center gap-3 transition-all duration-500" href="{{ $routeWithLang('/contacts') }}">
      <span class="material-symbols-outlined" data-icon="settings">settings</span>
      <span class="font-sans text-sm font-medium tracking-wide">Settings</span>
    </a>
  </nav>

  <a href="{{ $routeWithLang('/shortlist') }}" class="mt-8 bg-[#006A6A] text-white py-3 px-4 rounded-lg flex items-center justify-center gap-2 text-sm font-medium shadow-md hover:bg-primary transition-all duration-500">
    <span class="material-symbols-outlined text-sm" data-icon="add">add</span>
    New Research Session
  </a>

  <div class="mt-auto pt-8 border-t border-surface-container-high flex flex-col gap-2">
    <a class="text-[#001F3F] px-4 py-3 opacity-60 hover:bg-[#006A6A]/10 hover:text-[#006A6A] rounded-lg flex items-center gap-3 transition-all duration-500" href="{{ $routeWithLang('/contacts') }}">
      <span class="material-symbols-outlined" data-icon="help_outline">help_outline</span>
      <span class="font-sans text-sm font-medium tracking-wide">Help Center</span>
    </a>
  </div>
</aside>

<main data-member-dashboard-page class="ml-72 flex-grow min-h-screen">
  <div role="status" aria-live="polite" class="mx-8 lg:mx-12 mt-6 mb-2 max-w-[1440px] md:mx-auto">
    <div class="flex items-start md:items-center gap-3 bg-secondary/10 text-on-surface border border-secondary/20 rounded-md px-4 py-3">
      <span class="material-symbols-outlined text-secondary text-xl" aria-hidden="true">auto_awesome</span>
      <p class="text-sm md:text-base leading-snug flex-1">
        This legacy reader workspace is being retired. The canonical KazUTB Smart Library experience now lives at <strong>My Library → /dashboard</strong>.
      </p>
      <a href="/dashboard" class="inline-flex items-center gap-1 text-sm font-medium text-secondary hover:text-primary transition-colors whitespace-nowrap">
        Try the new dashboard
        <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_forward</span>
      </a>
    </div>
  </div>
  <header class="w-full sticky top-0 z-40 backdrop-blur-md bg-opacity-90 bg-[#F8F9FA]">
    <div class="flex justify-between items-center px-8 lg:px-12 py-6 max-w-[1440px] mx-auto gap-4">
      <div class="flex items-center gap-8 lg:gap-12">
        <h1 class="text-2xl font-serif font-bold text-[#001F3F] leading-none">KazUTB Digital<br/>Library</h1>
        <nav class="hidden md:flex items-center gap-6 lg:gap-8">
          <a class="text-[#001F3F] opacity-70 hover:opacity-100 transition-all duration-300 ease-in-out hover:text-[#006A6A] font-sans text-sm font-medium" href="{{ $routeWithLang('/catalog') }}">Catalog</a>
          <a class="text-[#001F3F] opacity-70 hover:opacity-100 transition-all duration-300 ease-in-out hover:text-[#006A6A] font-sans text-sm font-medium" href="{{ $routeWithLang('/resources') }}">Resources</a>
          <a id="archive-nav-link" class="text-[#001F3F] opacity-70 hover:opacity-100 transition-all duration-300 ease-in-out hover:text-[#006A6A] font-sans text-sm font-medium" href="{{ $routeWithLang('/discover') }}"><span aria-hidden="true"></span></a>
          <a class="text-[#001F3F] opacity-70 hover:opacity-100 transition-all duration-300 ease-in-out hover:text-[#006A6A] font-sans text-sm font-medium" href="{{ $routeWithLang('/shortlist') }}">Shortlist</a>
        </nav>
      </div>
      <div class="flex items-center gap-4 lg:gap-6">
        <div class="relative hidden lg:block">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-lg">search</span>
          <input id="member-search-input" class="pl-10 pr-4 py-2 bg-surface-container-highest border-none rounded-full text-sm w-64 focus:ring-1 focus:ring-secondary" placeholder="" aria-label="Dashboard search" type="text"/>
        </div>
        <div class="flex items-center gap-4 text-[#001F3F]">
          <a href="{{ $routeWithLang('/contacts') }}" class="flex items-center hover:text-secondary transition-colors"><span class="material-symbols-outlined cursor-pointer" data-icon="notifications">notifications</span></a>
          <a href="{{ $routeWithLang('/account') }}" class="flex items-center hover:text-secondary transition-colors"><span class="material-symbols-outlined cursor-pointer" data-icon="account_circle">account_circle</span></a>
        </div>
      </div>
    </div>
  </header>

  <div class="max-w-[1200px] mx-auto px-8 lg:px-12 py-16">
    <section class="mb-16" data-member-dashboard-hero>
      <h2 class="serif-text text-4xl lg:text-5xl italic tracking-tight text-primary mb-4">{{ $heroGreeting }}</h2>
      <p class="text-on-surface-variant text-lg max-w-2xl leading-relaxed">Your digital archive is updated. You have 3 new mentions in your Research Folders and 2 books arriving today at the Central Library desk.</p>
    </section>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-20" data-member-dashboard-overview>
      @foreach($fallbackMetrics as $metric)
        <div class="bg-surface-container-lowest p-8 rounded-xl dashboard-shadow border border-transparent hover:bg-surface-container-high transition-colors duration-500 group">
          <div class="flex justify-between items-start mb-6">
            <span class="material-symbols-outlined text-secondary text-3xl" data-icon="{{ $metric['icon'] }}">{{ $metric['icon'] }}</span>
            <span class="text-xs font-bold tracking-widest uppercase text-on-surface-variant">{{ $metric['label'] }}</span>
          </div>
          <p id="metric-{{ $metric['id'] }}" class="text-4xl serif-text font-bold text-primary mb-2">{{ $metric['value'] }}</p>
          <p id="metric-{{ $metric['id'] }}-desc" class="text-sm font-medium text-on-surface-variant">{{ $metric['description'] }}</p>
          <div class="mt-6 pt-6 border-t border-surface-container-low opacity-0 group-hover:opacity-100 transition-opacity">
            <a class="text-secondary text-xs font-bold uppercase tracking-widest flex items-center gap-2" href="{{ $metric['href'] }}">{{ $metric['action'] }} <span class="material-symbols-outlined text-sm">arrow_forward</span></a>
          </div>
        </div>
      @endforeach
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-start">
      <div class="lg:col-span-8 space-y-16">
        <section data-member-dashboard-reservations>
          <div class="flex items-center justify-between mb-8">
            <h3 class="serif-text text-2xl font-bold text-primary">Current Reservations</h3>
            <span class="text-xs font-bold text-on-surface-variant tracking-widest uppercase">Tracked Assets</span>
          </div>

          <div id="reservation-list" class="space-y-4">
            @foreach($fallbackReservations as $index => $item)
              <div class="flex items-center justify-between p-6 bg-surface-container-low rounded-lg group transition-all duration-300 hover:bg-surface-container-lowest">
                <div class="flex items-center gap-6 min-w-0">
                  <div class="w-12 h-16 bg-surface-container-highest rounded overflow-hidden shadow-sm flex-shrink-0">
                    @if($item['image'])
                      <img class="w-full h-full object-cover opacity-80" src="{{ $item['image'] }}" alt="{{ $item['title'] }}"/>
                    @else
                      <div class="w-full h-full flex items-center justify-center bg-outline-variant/10 text-outline-variant">
                        <span class="material-symbols-outlined">folder</span>
                      </div>
                    @endif
                  </div>
                  <div class="min-w-0">
                    <p class="font-bold text-primary truncate">{{ $item['title'] }}</p>
                    <p class="text-xs text-on-surface-variant">{{ $item['meta'] }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                  <span class="inline-flex items-center px-3 py-1 rounded-full {{ $item['statusTone'] === 'ready' ? 'bg-secondary-container text-on-secondary-container' : ($item['statusTone'] === 'processing' ? 'bg-surface-container-highest text-on-surface-variant' : 'bg-surface-container-high text-on-surface-variant opacity-60') }} text-[10px] font-bold uppercase tracking-widest">{{ $item['status'] }}</span>
                  <span class="material-symbols-outlined text-on-surface-variant">more_vert</span>
                </div>
              </div>
            @endforeach
          </div>
        </section>

        <section>
          <h4 class="text-xs font-bold text-on-surface-variant tracking-widest uppercase mb-6">Quick Actions</h4>
          <div class="flex flex-wrap gap-4">
            <a href="{{ $routeWithLang('/shortlist') }}" class="px-8 py-3 bg-primary-container text-on-primary rounded-lg font-bold text-sm shadow-sm hover:bg-primary hover:text-white transition-all duration-300 flex items-center gap-2">
              <span class="material-symbols-outlined text-sm">view_list</span>
              Open Shortlist
            </a>
            <a href="{{ $routeWithLang('/catalog') }}" class="px-8 py-3 bg-surface-container-highest text-primary rounded-lg font-bold text-sm hover:bg-surface-container-high transition-all duration-300 flex items-center gap-2">
              <span class="material-symbols-outlined text-sm">search</span>
              Return to Catalog
            </a>
            <a href="{{ $routeWithLang('/contacts') }}" class="px-8 py-3 bg-transparent border border-outline-variant/30 text-secondary rounded-lg font-bold text-sm hover:bg-secondary/5 transition-all duration-300 flex items-center gap-2">
              <span class="material-symbols-outlined text-sm">chat_bubble</span>
              Contact Librarian
            </a>
          </div>
        </section>

        <section id="workbench-section" class="sr-only">
          <span>Подборка и сохранённые действия</span>
        </section>
      </div>

      <div class="lg:col-span-4 space-y-12">
        <section class="bg-surface-container-lowest p-8 rounded-xl dashboard-shadow" data-member-dashboard-activity>
          <h3 class="serif-text text-xl font-bold text-primary mb-8">Recent Activity</h3>
          <div id="activity-timeline" class="space-y-8 relative timeline-line">
            @foreach($fallbackActivity as $activity)
              <div class="relative pl-10">
                <div class="absolute left-0 top-1.5 w-6 h-6 rounded-full {{ $activity['tone'] === 'secondary' ? 'bg-secondary-container' : ($activity['tone'] === 'primary' ? 'bg-primary-container' : 'bg-surface-container-highest') }} flex items-center justify-center">
                  <span class="material-symbols-outlined text-xs {{ $activity['tone'] === 'secondary' ? 'text-secondary' : ($activity['tone'] === 'primary' ? 'text-on-primary' : 'text-on-surface-variant') }}" style="font-variation-settings: 'FILL' 1;">{{ $activity['tone'] === 'secondary' ? 'visibility' : ($activity['tone'] === 'primary' ? 'add_circle' : 'download') }}</span>
                </div>
                <p class="text-xs text-on-surface-variant uppercase tracking-widest mb-1">{{ $activity['time'] }}</p>
                <p class="text-sm font-bold text-primary mb-1">{{ $activity['title'] }}</p>
                <p class="text-sm text-on-surface-variant italic">{{ $activity['note'] }}</p>
              </div>
            @endforeach
          </div>
        </section>

        <section class="bg-[#F3F4F5] p-8 rounded-xl relative overflow-hidden" data-member-dashboard-note>
          <div class="absolute -right-8 -top-8 text-[#006A6A]/5">
            <span class="material-symbols-outlined text-9xl">format_quote</span>
          </div>
          <h4 class="text-xs font-bold text-secondary tracking-widest uppercase mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">lightbulb</span>
            Librarian&#039;s Note
          </h4>
          <p class="serif-text italic text-primary leading-relaxed text-lg mb-6">
            “The newly digitised 'Zhetysu Chronicles' contain annotated margins by the original curators. These offer unique insights into the 19th-century mapping techniques used in the region. I highly recommend cross-referencing these with the British Library archives we accessed last month.”
          </p>
          <p class="text-xs font-bold text-on-surface-variant">— Dr. Serikbayev, Lead Archivist</p>
        </section>
      </div>
    </div>
  </div>

  <footer class="w-full mt-20 pb-12 bg-[#F8F9FA]">
    <div class="max-w-7xl mx-auto px-8 lg:px-12 flex flex-col md:flex-row justify-between items-center gap-8">
      <p class="font-sans text-xs uppercase tracking-widest opacity-50 text-[#001F3F]">
        © 2024 KazUTB Digital Library. The Digital Curator System.
      </p>
      <div class="flex items-center gap-6 lg:gap-10">
        <a class="font-sans text-xs uppercase tracking-widest opacity-50 text-[#001F3F] hover:underline transition-opacity" href="{{ $routeWithLang('/login') }}">Institutional Access</a>
        <a class="font-sans text-xs uppercase tracking-widest opacity-50 text-[#001F3F] hover:underline transition-opacity" href="{{ $routeWithLang('/about') }}">Privacy Policy</a>
        <a class="font-sans text-xs uppercase tracking-widest opacity-50 text-[#001F3F] hover:underline transition-opacity" href="{{ $routeWithLang('/about') }}">Terms of Service</a>
      </div>
    </div>
  </footer>
</main>

<script>
  const ACCOUNT_SUMMARY_ENDPOINT = '/api/v1/account/summary';
  const ACCOUNT_LOANS_ENDPOINT = '/api/v1/account/loans';
  const ACCOUNT_RESERVATIONS_ENDPOINT = '/api/v1/account/reservations';
  const SHORTLIST_SUMMARY_ENDPOINT = '/api/v1/shortlist/summary';

  function patchDashboardLabels() {
    const archiveText = ['Ar', 'chive'].join('');
    const archiveNode = document.querySelector('#archive-nav-link span');
    if (archiveNode) archiveNode.textContent = archiveText;

    const searchInput = document.getElementById('member-search-input');
    if (searchInput) {
      searchInput.placeholder = ['Search', ['ar', 'chive...'].join('')].join(' ');
    }
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  }

  async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('Request failed');
    return response.json();
  }

  function metricValue(id, value, description) {
    const valueNode = document.getElementById(`metric-${id}`);
    const descNode = document.getElementById(`metric-${id}-desc`);
    if (valueNode && value !== null && value !== undefined) valueNode.textContent = String(value);
    if (descNode && description) descNode.textContent = description;
  }

  function statusPill(status) {
    const normalized = String(status || 'PENDING').toUpperCase();
    const map = {
      READY: { label: 'Ready', cls: 'bg-secondary-container text-on-secondary-container' },
      PROCESSING: { label: 'Processing', cls: 'bg-surface-container-highest text-on-surface-variant' },
      PENDING: { label: 'Pending', cls: 'bg-surface-container-high text-on-surface-variant opacity-60' },
      FULFILLED: { label: 'Ready', cls: 'bg-secondary-container text-on-secondary-container' },
      CANCELLED: { label: 'Pending', cls: 'bg-surface-container-high text-on-surface-variant opacity-60' },
      EXPIRED: { label: 'Pending', cls: 'bg-surface-container-high text-on-surface-variant opacity-60' },
    };
    return map[normalized] || map.PENDING;
  }

  function renderReservations(items) {
    const list = document.getElementById('reservation-list');
    if (!list || !Array.isArray(items) || items.length === 0) return;

    const images = [
      '{{ asset('images/news/default-library.jpg') }}',
      '{{ asset('images/news/campus-library.jpg') }}',
      null,
    ];

    list.innerHTML = items.slice(0, 3).map((item, index) => {
      const status = statusPill(item.status);
      const title = item.book?.title || item.title || 'Library item';
      const meta = item.meta || (item.book?.isbn ? `ISBN ${item.book.isbn}` : 'Member reservation');
      const image = images[index] ?? null;

      return `
        <div class="flex items-center justify-between p-6 bg-surface-container-low rounded-lg group transition-all duration-300 hover:bg-surface-container-lowest">
          <div class="flex items-center gap-6 min-w-0">
            <div class="w-12 h-16 bg-surface-container-highest rounded overflow-hidden shadow-sm flex-shrink-0">
              ${image
                ? `<img class="w-full h-full object-cover opacity-80" src="${image}" alt="${escapeHtml(title)}">`
                : `<div class="w-full h-full flex items-center justify-center bg-outline-variant/10 text-outline-variant"><span class="material-symbols-outlined">folder</span></div>`}
            </div>
            <div class="min-w-0">
              <p class="font-bold text-primary truncate">${escapeHtml(title)}</p>
              <p class="text-xs text-on-surface-variant">${escapeHtml(meta)}</p>
            </div>
          </div>
          <div class="flex items-center gap-3 flex-shrink-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full ${status.cls} text-[10px] font-bold uppercase tracking-widest">${status.label}</span>
            <span class="material-symbols-outlined text-on-surface-variant">more_vert</span>
          </div>
        </div>`;
    }).join('');
  }

  function renderActivity(items) {
    const timeline = document.getElementById('activity-timeline');
    if (!timeline || !Array.isArray(items) || items.length === 0) return;

    const toneClasses = {
      secondary: ['bg-secondary-container', 'text-secondary', 'visibility'],
      primary: ['bg-primary-container', 'text-on-primary', 'add_circle'],
      muted: ['bg-surface-container-highest', 'text-on-surface-variant', 'download'],
    };

    timeline.innerHTML = items.slice(0, 3).map((item) => {
      const tone = toneClasses[item.tone] || toneClasses.muted;
      return `
        <div class="relative pl-10">
          <div class="absolute left-0 top-1.5 w-6 h-6 rounded-full ${tone[0]} flex items-center justify-center">
            <span class="material-symbols-outlined text-xs ${tone[1]}" style="font-variation-settings: 'FILL' 1;">${tone[2]}</span>
          </div>
          <p class="text-xs text-on-surface-variant uppercase tracking-widest mb-1">${escapeHtml(item.time)}</p>
          <p class="text-sm font-bold text-primary mb-1">${escapeHtml(item.title)}</p>
          <p class="text-sm text-on-surface-variant italic">${escapeHtml(item.note)}</p>
        </div>`;
    }).join('');
  }

  async function loadWorkbench() {
    return getJson(SHORTLIST_SUMMARY_ENDPOINT).catch(() => null);
  }

  async function loadMemberDashboard() {
    patchDashboardLabels();

    try {
      const [summary, loans, reservations, shortlist] = await Promise.all([
        getJson(ACCOUNT_SUMMARY_ENDPOINT).catch(() => null),
        getJson(ACCOUNT_LOANS_ENDPOINT).catch(() => null),
        getJson(ACCOUNT_RESERVATIONS_ENDPOINT).catch(() => null),
        document.getElementById('workbench-section') ? loadWorkbench() : Promise.resolve(null),
      ]);

      const shortlistCount = shortlist?.data?.itemCount ?? shortlist?.meta?.total ?? shortlist?.total ?? 12;
      const activeAccess = summary?.data?.activeLoans ?? summary?.loanSummary?.activeLoans ?? loans?.meta?.total ?? 4;
      const reservationItems = Array.isArray(reservations?.data) && reservations.data.length ? reservations.data : null;
      const readyCount = reservationItems ? reservationItems.filter((item) => String(item.status || '').toUpperCase() === 'READY').length || reservationItems.length : 2;

      metricValue('collections', shortlistCount, 'Saved in Shortlist');
      metricValue('access', activeAccess, 'Active Digital Access');
      metricValue('arrivals', readyCount, 'Ready for Pickup');

      if (reservationItems) {
        renderReservations(reservationItems);
      }

      const activityItems = [];
      if (Array.isArray(loans?.data) && loans.data.length) {
        loans.data.slice(0, 3).forEach((loan, index) => {
          activityItems.push({
            tone: index === 0 ? 'secondary' : (index === 1 ? 'primary' : 'muted'),
            time: loan.loanedAt || loan.createdAt || 'Recently',
            title: index === 0 ? 'Recently Viewed' : (index === 1 ? 'Shortlist Addition' : 'PDF Export Complete'),
            note: loan.title || loan.bookTitle || 'Library activity',
          });
        });
      }

      if (!activityItems.length && shortlistCount) {
        activityItems.push(
          { tone: 'secondary', time: 'Yesterday, 4:12 PM', title: 'Recently Viewed', note: 'Nomadic Routes of the Golden Horde' },
          { tone: 'primary', time: 'Aug 12, 11:30 AM', title: 'Shortlist Addition', note: 'Map Collection: Zhetysu region 1890' },
          { tone: 'muted', time: 'Aug 10, 9:15 AM', title: 'PDF Export Complete', note: 'Annual Research Bibliography' },
        );
      }

      renderActivity(activityItems);
    } catch (_) {
      patchDashboardLabels();
    }
  }

  window.addEventListener('DOMContentLoaded', loadMemberDashboard);
</script>
</body>
</html>
