@php
  $pageLang = $pageLang ?? app()->getLocale();
  $pageLang = in_array($pageLang, ['kk', 'ru', 'en'], true) ? $pageLang : 'ru';
  $activePage = $activePage ?? '';
  $isHomePage = $activePage === 'home';
  $isAuthenticated = (bool) session('library.user');

  $headerCopy = [
    'ru' => [
      'utility' => [['Главная', '/'], ['Подборка', '/shortlist']],
      'links' => [
        ['catalog', 'Каталог', '/catalog'],
        ['discover', 'Обзор', '/discover'],
        ['resources', 'Ресурсы', '/resources'],
        ['repository', 'Репозиторий', '/repository'],
        ['news', 'Новости', '/news'],
        ['events', 'События', '/events'],
      ],
      'institution' => 'Об институте',
      'institution_links' => [
        ['О библиотеке', '/about'],
        ['Руководство', '/leadership'],
        ['Правила библиотеки', '/rules'],
        ['Контакты', '/contacts'],
      ],
      'name' => 'КАЗАХСКИЙ УНИВЕРСИТЕТ ТЕХНОЛОГИИ И БИЗНЕСА ИМЕНИ К. КУЛАЖАНОВА',
      'menu' => 'Меню', 'guest' => 'Войти', 'dashboard' => 'Открыть кабинет', 'signout' => 'Выйти', 'lang_aria' => 'Переключатель языка',
    ],
    'kk' => [
      'utility' => [['Басты бет', '/'], ['Іріктеме', '/shortlist']],
      'links' => [
        ['catalog', 'Каталог', '/catalog'],
        ['discover', 'Шолу', '/discover'],
        ['resources', 'Ресурстар', '/resources'],
        ['repository', 'Репозиторий', '/repository'],
        ['news', 'Жаңалықтар', '/news'],
        ['events', 'Іс-шаралар', '/events'],
      ],
      'institution' => 'Институт туралы',
      'institution_links' => [
        ['Кітапхана туралы', '/about'],
        ['Басшылық', '/leadership'],
        ['Кітапхана ережелері', '/rules'],
        ['Байланыс', '/contacts'],
      ],
      'name' => 'Қ. ҚҰЛАЖАНОВ АТЫНДАҒЫ ҚАЗАҚ ТЕХНОЛОГИЯ ЖӘНЕ БИЗНЕС УНИВЕРСИТЕТІ',
      'menu' => 'Мәзір', 'guest' => 'Кіру', 'dashboard' => 'Кабинетті ашу', 'signout' => 'Шығу', 'lang_aria' => 'Тіл ауыстырғыш',
    ],
    'en' => [
      'utility' => [['Home', '/'], ['Shortlist', '/shortlist']],
      'links' => [
        ['catalog', 'Catalog', '/catalog'],
        ['discover', 'Discover', '/discover'],
        ['resources', 'Resources', '/resources'],
        ['repository', 'Repository', '/repository'],
        ['news', 'News', '/news'],
        ['events', 'Events', '/events'],
      ],
      'institution' => 'Institution',
      'institution_links' => [
        ['About the Library', '/about'],
        ['Leadership', '/leadership'],
        ['Library Rules', '/rules'],
        ['Contacts', '/contacts'],
      ],
      'name' => 'KAZAKH UNIVERSITY OF TECHNOLOGY AND BUSINESS NAMED AFTER K. KULAZHANOV',
      'menu' => 'Menu', 'guest' => 'Sign in', 'dashboard' => 'Open portal', 'signout' => 'Sign out', 'lang_aria' => 'Language switcher',
    ],
  ][$pageLang];

  $routeWithLang = static function (string $path, array $query = []) use ($pageLang): string {
      $normalizedPath = '/' . ltrim($path, '/');
      if ($normalizedPath === '//') $normalizedPath = '/';
      if ($pageLang !== 'ru') $query['lang'] = $pageLang;
      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');
      return $normalizedPath . ($query ? ('?' . http_build_query($query)) : '');
  };
  $headerHref = static fn (string $href): string => str_starts_with($href, 'http') ? $href : $routeWithLang($href);
  $localeLabels = ['kk' => 'KZ', 'ru' => 'RU', 'en' => 'EN'];
@endphp

<style>
  .brand-mark {
    width: 108px;
    height: 108px;
    border: 3.5px solid rgba(255, 255, 255, 0.92);
    border-radius: 50%;
    background:
      radial-gradient(circle at 50% 48%, #fff 0 31%, transparent 32%),
      conic-gradient(from 210deg, #006a6a 0 18%, #fff 18% 50%, #0b2037 50% 100%);
    box-shadow:
      0 0 0 0.5px rgba(255, 255, 255, 0.3),
      0 16px 48px rgba(0, 0, 0, 0.38),
      0 2px 10px rgba(0, 0, 0, 0.2);
    position: relative;
    flex: 0 0 auto;
    overflow: hidden;
    will-change: transform, width, height;
    transition:
      width 0.42s cubic-bezier(0.23, 1, 0.32, 1),
      height 0.42s cubic-bezier(0.23, 1, 0.32, 1),
      border-color 0.42s ease,
      box-shadow 0.42s ease,
      transform 0.36s cubic-bezier(0.23, 1, 0.32, 1);
  }

  .brand-mark.has-image {
    background: #fff;
  }

  .brand-mark img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
  }

  .brand-mark:hover {
    transform: scale(1.09) rotate(-5deg);
    box-shadow:
      0 0 0 4px rgba(0, 106, 106, 0.45),
      0 22px 60px rgba(0, 0, 0, 0.45),
      0 4px 18px rgba(0, 0, 0, 0.26);
  }

  .official-header {
    position: fixed;
    top: 0;
    right: 0;
    left: 0;
    z-index: 140;
    background: transparent;
    border: 0;
    box-shadow: none;
    transition:
      background 0.22s ease,
      border-color 0.22s ease,
      box-shadow 0.22s ease;
  }

  .official-header:not(.scrolled) {
    background: linear-gradient(180deg, rgba(7, 25, 43, 0.24), transparent);
  }

  .official-header-main {
    min-height: 150px;
    transition: min-height 0.36s cubic-bezier(0.23, 1, 0.32, 1);
  }

  .official-header .official-nav-link,
  .official-header .official-brand-name {
    color: #fff;
    text-shadow: 0 2px 18px rgba(0, 0, 0, 0.72);
    transition:
      color 0.22s ease,
      text-shadow 0.22s ease;
  }

  .official-header.scrolled {
    background: rgba(255, 255, 255, 0.99);
    border: 0;
    box-shadow: 0 8px 30px rgba(15, 38, 66, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
  }

  .official-header.scrolled .official-header-main {
    min-height: 104px;
  }

  .official-header.scrolled .brand-mark {
    width: 72px;
    height: 72px;
    border-width: 2.5px;
    border-color: rgba(11, 32, 55, 0.28);
    box-shadow:
      0 6px 22px rgba(11, 32, 55, 0.18),
      0 0 0 0.5px rgba(255, 255, 255, 0.35);
  }

  .official-header.scrolled .official-nav-link,
  .official-header.scrolled .official-brand-name {
    color: #1c2b39;
    text-shadow: none;
  }

  .official-header .official-nav-link:hover,
  .official-header.scrolled .official-nav-link:hover {
    color: #006a6a;
  }

  .official-header:not(.scrolled) .official-nav-link:hover {
    color: #fff;
    border-color: #fff;
  }

  .official-brand-name {
    transition:
      color 0.42s ease,
      text-shadow 0.42s ease,
      font-size 0.42s ease,
      letter-spacing 0.42s ease;
  }

  .official-header.scrolled .official-brand-name {
    font-size: 12px;
    letter-spacing: 0.07em;
  }

  .utility-dropdown summary {
    list-style: none;
  }
  .utility-dropdown summary::-webkit-details-marker {
    display: none;
  }
  .utility-dropdown[open] summary {
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
  }

  @media (max-width: 1023px) {
    .brand-mark {
      width: 58px;
      height: 58px;
      border-width: 2px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22);
    }

    .official-header,
    .official-header.scrolled {
      background: rgba(255, 255, 255, 0.99);
    }

    .official-header .official-brand-name {
      color: #1c2b39;
      text-shadow: none;
    }

    .official-header-main,
    .official-header.scrolled .official-header-main {
      min-height: 74px;
    }

    .site-shell:not(.homepage) .page-main {
      padding-top: 114px;
    }
  }

  @media (min-width: 1024px) {
    .site-shell:not(.homepage) .page-main {
      padding-top: 144px;
    }
  }
</style>

<header id="mainHeader" class="official-header {{ $isHomePage ? '' : 'scrolled' }}">
  <div class="bg-[#0b2037]/95 text-white/75 backdrop-blur-md">
    <div class="mx-auto flex min-h-10 w-full max-w-[1370px] items-stretch justify-between px-4 sm:px-6">
      <nav class="hidden items-stretch md:flex" aria-label="University services">
        @foreach($headerCopy['utility'] as [$label, $href])
          <a href="{{ $headerHref($href) }}" class="flex items-center border-x border-white/[0.05] px-4 text-[12px] font-semibold transition hover:bg-white/[0.06] hover:text-white" @if(str_starts_with($href, 'http')) target="_blank" rel="noopener" @endif>{{ $label }}</a>
        @endforeach

        <details class="utility-dropdown relative flex">
          <summary class="flex cursor-pointer items-center gap-1 border-x border-white/[0.05] px-4 text-[12px] font-semibold transition hover:bg-white/[0.06] hover:text-white">
            {{ $headerCopy['institution'] }}
            <span aria-hidden="true" class="text-[10px] opacity-70">⌄</span>
          </summary>
          <nav class="absolute left-0 top-full z-50 grid w-60 overflow-hidden rounded-b-sm border border-slate-200 bg-white py-2 shadow-2xl" aria-label="{{ $headerCopy['institution'] }}">
            @foreach($headerCopy['institution_links'] as [$label, $href])
              <a href="{{ $headerHref($href) }}" class="px-5 py-2.5 text-[13px] font-semibold text-[#1c2b39] hover:bg-[#f2f6f5] hover:text-[#006a6a]">{{ $label }}</a>
            @endforeach
          </nav>
        </details>

        @unless($isAuthenticated)
          <a href="{{ $routeWithLang('/login') }}" class="flex items-center border-x border-white/[0.05] px-4 text-[12px] font-semibold transition hover:bg-white/[0.06] hover:text-white">{{ $headerCopy['guest'] }}</a>
        @endunless
        <a href="{{ $routeWithLang('/dashboard') }}" class="flex items-center border-x border-white/[0.05] px-4 text-[12px] font-semibold transition hover:bg-white/[0.06] hover:text-white">{{ $headerCopy['dashboard'] }}</a>
        @if($isAuthenticated)
          <a href="{{ $routeWithLang('/logout') }}" class="flex items-center border-x border-white/[0.05] px-4 text-[12px] font-semibold transition hover:bg-white/[0.06] hover:text-white">{{ $headerCopy['signout'] }}</a>
        @endif
      </nav>
      <div class="ml-auto flex items-stretch text-[11px] font-bold tracking-wide" role="group" data-locale-switcher aria-label="{{ $headerCopy['lang_aria'] }}">
        @foreach(['kk', 'ru', 'en'] as $locale)
          <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}" class="flex min-w-11 items-center justify-center border-x border-white/[0.05] px-3 transition {{ $pageLang === $locale ? 'bg-[#006a6a] text-white' : 'hover:bg-white/[0.06] hover:text-white' }}">{{ $localeLabels[$locale] }}</a>
        @endforeach
      </div>
    </div>
  </div>

  <div class="relative mx-auto w-full max-w-[1370px] px-4 sm:px-6">
    <div class="official-header-main hidden items-center justify-center gap-5 lg:flex">
      <nav class="flex flex-1 items-center justify-end gap-5" aria-label="{{ __('ui.aria.main_navigation') }}">
        @foreach(array_slice($headerCopy['links'], 0, 3) as [$key, $label, $href])
          <a href="{{ $headerHref($href) }}" class="official-nav-link inline-flex items-center gap-1 whitespace-nowrap border-b-[3px] border-transparent px-2 py-2 text-[15px] font-extrabold transition hover:-translate-y-0.5 {{ $activePage === $key ? 'border-[#006a6a]' : '' }}">{{ $label }}</a>
        @endforeach
      </nav>

      <a href="{{ $routeWithLang('/') }}" class="relative z-10 flex shrink-0 items-center justify-center self-start pt-2" aria-label="{{ __('ui.brand.home_aria') }}">
        <span class="brand-mark has-image">
          <img src="{{ asset('logo.png') }}" alt="{{ __('ui.brand.title') }} logo" loading="eager" decoding="async">
        </span>
      </a>

      <nav class="flex flex-1 items-center justify-start gap-5" aria-label="University navigation continued">
        @foreach(array_slice($headerCopy['links'], 3) as [$key, $label, $href])
          <a href="{{ $headerHref($href) }}" class="official-nav-link inline-flex items-center gap-1 whitespace-nowrap border-b-[3px] border-transparent px-2 py-2 text-[15px] font-extrabold transition hover:-translate-y-0.5 {{ $activePage === $key ? 'border-[#006a6a]' : '' }}">{{ $label }}</a>
        @endforeach
      </nav>
    </div>

    <div class="flex min-h-[74px] items-center justify-between gap-4 lg:hidden">
      <a href="{{ $routeWithLang('/') }}" class="flex items-center gap-3 text-[#1c2b39]">
        <span class="brand-mark has-image">
          <img src="{{ asset('logo.png') }}" alt="{{ __('ui.brand.title') }} logo" loading="eager" decoding="async">
        </span>
        <span class="official-brand-name max-w-[220px] font-serif text-[11px] font-bold leading-tight sm:max-w-md sm:text-sm">{{ $headerCopy['name'] }}</span>
      </a>
      <details class="relative">
        <summary class="cursor-pointer list-none rounded-sm bg-[#0b2037] px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">{{ $headerCopy['menu'] }}</summary>
        <nav class="absolute right-0 top-[calc(100%+10px)] z-50 grid w-64 overflow-hidden rounded-sm border border-slate-200 bg-white py-2 shadow-2xl">
          <a href="{{ $routeWithLang('/') }}" class="px-5 py-2.5 text-sm font-semibold text-[#1c2b39] hover:bg-[#f2f6f5] hover:text-[#006a6a]">{{ $headerCopy['utility'][0][0] }}</a>
          @foreach($headerCopy['links'] as [$key, $label, $href])
            <a href="{{ $headerHref($href) }}" class="px-5 py-2.5 text-sm font-semibold text-[#1c2b39] hover:bg-[#f2f6f5] hover:text-[#006a6a]">{{ $label }}</a>
          @endforeach
          <div class="mx-5 my-2 border-t border-slate-200"></div>
          @foreach($headerCopy['institution_links'] as [$label, $href])
            <a href="{{ $headerHref($href) }}" class="px-5 py-2 text-[13px] font-medium text-[#43474f] hover:bg-[#f2f6f5] hover:text-[#006a6a]">{{ $label }}</a>
          @endforeach
          <a href="{{ $headerHref('/shortlist') }}" class="px-5 py-2 text-[13px] font-medium text-[#43474f] hover:bg-[#f2f6f5] hover:text-[#006a6a]">{{ $headerCopy['utility'][1][0] }}</a>
          <div class="mx-5 my-2 border-t border-slate-200"></div>
          <a href="{{ $routeWithLang($isAuthenticated ? '/dashboard' : '/login') }}" class="px-5 py-2.5 text-sm font-bold text-[#006a6a]">{{ $isAuthenticated ? $headerCopy['dashboard'] : $headerCopy['guest'] }}</a>
          @if($isAuthenticated)
            <a href="{{ $routeWithLang('/logout') }}" class="px-5 py-2 text-[13px] font-medium text-[#43474f] hover:bg-[#f2f6f5] hover:text-[#006a6a]">{{ $headerCopy['signout'] }}</a>
          @endif
        </nav>
      </details>
    </div>

    <p class="official-brand-name pointer-events-none absolute inset-x-0 bottom-2 hidden px-8 text-center font-serif text-[17px] font-extrabold uppercase leading-tight tracking-[0.01em] lg:block">{{ $headerCopy['name'] }}</p>
  </div>
</header>

@if($isHomePage)
<script>
  (() => {
    const mainHeader = document.getElementById('mainHeader');
    if (!mainHeader) return;

    let scheduled = false;
    const syncHeader = () => {
      mainHeader.classList.toggle('scrolled', window.scrollY > 30);
      scheduled = false;
    };
    const scheduleHeaderSync = () => {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(syncHeader);
    };

    syncHeader();
    window.addEventListener('scroll', scheduleHeaderSync, { passive: true });
  })();
</script>
@endif
