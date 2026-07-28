@php
  $pageLang = $pageLang ?? app()->getLocale();
  $pageLang = in_array($pageLang, ['kk', 'ru', 'en'], true) ? $pageLang : 'ru';
  $activePage = $activePage ?? '';
  $isHomePage = $activePage === 'home';
  $isAuthenticated = (bool) session('library.user');

  $headerCopy = [
    'ru' => [
      'org' => 'Казахский университет технологии и бизнеса имени К. Кулажанова',
      'links' => [
        ['home', 'Главная', '/'],
        ['catalog', 'Каталог', '/catalog'],
        ['resources', 'Ресурсы', '/resources'],
        ['repository', 'Репозиторий', '/repository'],
        ['news', 'Новости', '/news'],
        ['events', 'События', '/events'],
        ['contacts', 'Контакты', '/contacts'],
      ],
      'institution' => 'Об институте',
      'institution_links' => [
        ['О библиотеке', '/about'],
        ['Руководство', '/leadership'],
        ['Правила библиотеки', '/rules'],
        ['Обзор фонда', '/discover'],
      ],
      'search' => 'Поиск',
      'search_placeholder' => 'Название, автор, УДК…',
      'shortlist' => 'Подборка',
      'notifications' => 'Уведомления',
      'menu' => 'Меню',
      'guest' => 'Войти',
      'dashboard' => 'Открыть кабинет',
      'signout' => 'Выйти',
      'lang_aria' => 'Переключатель языка',
    ],
    'kk' => [
      'org' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
      'links' => [
        ['home', 'Басты бет', '/'],
        ['catalog', 'Каталог', '/catalog'],
        ['resources', 'Ресурстар', '/resources'],
        ['repository', 'Репозиторий', '/repository'],
        ['news', 'Жаңалықтар', '/news'],
        ['events', 'Іс-шаралар', '/events'],
        ['contacts', 'Байланыс', '/contacts'],
      ],
      'institution' => 'Институт туралы',
      'institution_links' => [
        ['Кітапхана туралы', '/about'],
        ['Басшылық', '/leadership'],
        ['Кітапхана ережелері', '/rules'],
        ['Қорға шолу', '/discover'],
      ],
      'search' => 'Іздеу',
      'search_placeholder' => 'Атауы, авторы, ӘОЖ…',
      'shortlist' => 'Іріктеме',
      'notifications' => 'Хабарламалар',
      'menu' => 'Мәзір',
      'guest' => 'Кіру',
      'dashboard' => 'Кабинетті ашу',
      'signout' => 'Шығу',
      'lang_aria' => 'Тіл ауыстырғыш',
    ],
    'en' => [
      'org' => 'Kazakh University of Technology and Business named after K. Kulazhanov',
      'links' => [
        ['home', 'Home', '/'],
        ['catalog', 'Catalog', '/catalog'],
        ['resources', 'Resources', '/resources'],
        ['repository', 'Repository', '/repository'],
        ['news', 'News', '/news'],
        ['events', 'Events', '/events'],
        ['contacts', 'Contacts', '/contacts'],
      ],
      'institution' => 'Institution',
      'institution_links' => [
        ['About the Library', '/about'],
        ['Leadership', '/leadership'],
        ['Library Rules', '/rules'],
        ['Browse the collection', '/discover'],
      ],
      'search' => 'Search',
      'search_placeholder' => 'Title, author, UDC…',
      'shortlist' => 'Shortlist',
      'notifications' => 'Notifications',
      'menu' => 'Menu',
      'guest' => 'Sign in',
      'dashboard' => 'Open portal',
      'signout' => 'Sign out',
      'lang_aria' => 'Language switcher',
    ],
  ][$pageLang];

  $routeWithLang = static function (string $path, array $query = []) use ($pageLang): string {
      [$path, $existing] = array_pad(explode('?', $path, 2), 2, '');
      parse_str($existing, $inherited);
      $query = array_merge($inherited, $query);

      $normalizedPath = '/' . ltrim($path, '/');
      if ($normalizedPath === '//') $normalizedPath = '/';
      if ($pageLang !== 'ru') $query['lang'] = $pageLang;
      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

      return $normalizedPath . ($query ? ('?' . http_build_query($query)) : '');
  };
  $localeLabels = ['kk' => 'KZ', 'ru' => 'RU', 'en' => 'EN'];
@endphp

<style>
  /* ============================================================
     Site header — single row, institutional.
     Transparent over the homepage hero, solid white once scrolled
     or on any inner page. No second tier, no centred wordmark.
     ============================================================ */
  :root {
    --site-header-h: 88px;
    --hdr-ink: #1f2937;
    --hdr-ink-soft: #4b5563;
    --hdr-accent: #0f4c81;
    --hdr-line: #e5e7eb;
  }

  .site-header {
    position: fixed;
    inset-inline: 0;
    top: 0;
    z-index: 140;
    height: var(--site-header-h);
    display: flex;
    align-items: center;
    background: transparent;
    border-bottom: 1px solid transparent;
    transition:
      background-color 250ms ease,
      border-color 250ms ease,
      box-shadow 250ms ease;
  }

  .site-header.is-solid {
    background: #ffffff;
    border-bottom-color: var(--hdr-line);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  }

  .site-header__inner {
    width: 100%;
    max-width: 1440px;
    margin-inline: auto;
    padding-inline: clamp(16px, 3vw, 40px);
    display: flex;
    align-items: center;
    gap: clamp(16px, 2.5vw, 40px);
  }

  /* ── Brand ─────────────────────────────────────────────── */
  .hdr-brand {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 0 0 auto;
    text-decoration: none;
    min-width: 0;
  }

  .hdr-brand__mark {
    flex: 0 0 auto;
    width: 72px;
    height: 72px;
    object-fit: contain;
    display: block;
    background: transparent;
  }

  .hdr-brand__text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
  }

  .hdr-brand__name {
    font-family: 'Literata', Georgia, serif;
    font-size: 19px;
    font-weight: 600;
    line-height: 1.1;
    letter-spacing: -0.005em;
    color: #ffffff;
    white-space: nowrap;
    transition: color 250ms ease;
  }

  .hdr-brand__org {
    display: none;
    font-size: 10px;
    font-weight: 500;
    line-height: 1.3;
    letter-spacing: 0.035em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.72);
    max-width: 252px;
    /* Two lines keeps the lockup balanced against the single-line name. */
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    display: -webkit-box;
    overflow: hidden;
    transition: color 250ms ease;
  }

  .site-header.is-solid .hdr-brand__name { color: var(--hdr-ink); }
  .site-header.is-solid .hdr-brand__org { color: #6b7280; }

  /* ── Primary navigation ────────────────────────────────── */
  .hdr-nav {
    display: none;
    flex: 1 1 0;
    min-width: 0;
    align-items: center;
    justify-content: center;
    gap: 14px;
  }

  .hdr-nav__link {
    position: relative;
    padding: 6px 0;
    font-family: 'Google Sans', sans-serif;
    font-size: 15.5px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: 0.01em;
    white-space: nowrap;
    color: rgba(255, 255, 255, 0.92);
    text-decoration: none;
    transition: color 250ms ease;
  }

  /* Hover is a colour change plus a hairline — never a filled pill. */
  .hdr-nav__link::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 0;
    height: 1px;
    background: currentColor;
    transition: width 250ms ease;
  }

  .hdr-nav__link:hover::after,
  .hdr-nav__link[aria-current='page']::after {
    width: 100%;
  }

  .site-header.is-solid .hdr-nav__link { color: var(--hdr-ink); }
  .site-header.is-solid .hdr-nav__link:hover,
  .site-header.is-solid .hdr-nav__link[aria-current='page'] { color: var(--hdr-accent); }

  /* ── Right-hand actions ────────────────────────────────── */
  .hdr-actions {
    display: flex;
    align-items: center;
    gap: 7px;
    flex: 0 0 auto;
    margin-left: auto;
    padding-left: 12px;
  }

  .hdr-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.92);
    cursor: pointer;
    text-decoration: none;
    transition: color 250ms ease;
  }

  .hdr-icon svg,
  .hdr-lang__trigger svg {
    width: 21px;
    height: 21px;
    display: block;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  .hdr-icon:hover { color: #ffffff; }
  .site-header.is-solid .hdr-icon { color: var(--hdr-ink); }
  .site-header.is-solid .hdr-icon:hover { color: var(--hdr-accent); }

  /* Language switcher — plain text, no chips. */
  .hdr-lang {
    display: none;
    position: relative;
  }

  .hdr-lang__trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.92);
    cursor: pointer;
    transition: color 250ms ease;
  }

  .hdr-lang__trigger:hover { color: #ffffff; }
  .site-header.is-solid .hdr-lang__trigger { color: var(--hdr-ink); }
  .site-header.is-solid .hdr-lang__trigger:hover { color: var(--hdr-accent); }

  .hdr-lang__panel {
    position: absolute;
    top: calc(100% + 14px);
    right: 0;
    z-index: 10;
    min-width: 120px;
    padding: 8px 0;
    background: #ffffff;
    border: 1px solid var(--hdr-line);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
  }

  .hdr-lang__link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    font-family: 'Google Sans', sans-serif;
    color: var(--hdr-ink);
    font-size: 13.5px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-decoration: none;
  }

  .hdr-lang__link:hover {
    background: #f3f4f6;
    color: var(--hdr-accent);
  }

  .hdr-lang__link[aria-current='true'] {
    color: var(--hdr-accent);
  }

  /* The single accent element in the header. */
  .hdr-cta {
    display: inline-flex;
    align-items: center;
    padding: 10px 22px;
    background: var(--hdr-accent);
    border: 1px solid var(--hdr-accent);
    color: #ffffff;
    font-family: 'Google Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
    text-decoration: none;
    transition: background-color 250ms ease, border-color 250ms ease;
  }

  .hdr-cta:hover { background: #0c3c68; border-color: #0c3c68; }

  .hdr-signout {
    font-family: 'Google Sans', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    white-space: nowrap;
    transition: color 250ms ease;
  }

  .hdr-signout:hover { color: #ffffff; }
  .site-header.is-solid .hdr-signout { color: var(--hdr-ink-soft); }
  .site-header.is-solid .hdr-signout:hover { color: var(--hdr-accent); }

  /* ── Disclosure panels (search / burger) ───────────────── */
  .hdr-disclosure { position: relative; display: flex; }
  .hdr-disclosure > summary { list-style: none; }
  .hdr-disclosure > summary::-webkit-details-marker { display: none; }

  .hdr-panel {
    position: absolute;
    top: calc(100% + 14px);
    right: 0;
    z-index: 10;
    background: #ffffff;
    border: 1px solid var(--hdr-line);
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
  }

  .hdr-panel--search { width: min(92vw, 420px); padding: 16px; }

  .hdr-search__form { display: flex; align-items: center; gap: 8px; }

  .hdr-search__input {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    border: 1px solid var(--hdr-line);
    background: #fff;
    font-family: inherit;
    font-size: 14px;
    color: var(--hdr-ink);
  }

  .hdr-search__input:focus {
    outline: none;
    border-color: var(--hdr-accent);
  }

  .hdr-search__submit {
    padding: 10px 18px;
    border: 1px solid var(--hdr-accent);
    background: var(--hdr-accent);
    color: #fff;
    font-family: 'Google Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
  }

  .hdr-panel--menu { width: min(92vw, 320px); padding: 10px 0; max-height: 74vh; overflow-y: auto; }

  .hdr-menu__link {
    display: block;
    padding: 10px 20px;
    font-family: 'Google Sans', sans-serif;
    font-size: 14.5px;
    font-weight: 500;
    color: var(--hdr-ink);
    text-decoration: none;
  }

  .hdr-menu__link:hover { background: #f3f4f6; color: var(--hdr-accent); }

  .hdr-menu__group {
    padding: 14px 20px 6px;
    margin-top: 6px;
    border-top: 1px solid var(--hdr-line);
    font-family: 'Google Sans', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #9ca3af;
  }

  .hdr-menu__link--muted { font-size: 13.5px; color: var(--hdr-ink-soft); }
  .hdr-menu__link--accent { font-weight: 600; color: var(--hdr-accent); }

  .hdr-burger { display: inline-flex; }

  /* ── Focus ─────────────────────────────────────────────── */
  .site-header a:focus-visible,
  .site-header button:focus-visible,
  .site-header summary:focus-visible,
  .site-header input:focus-visible {
    outline: 2px solid var(--hdr-accent);
    outline-offset: 3px;
  }

  .site-header:not(.is-solid) a:focus-visible,
  .site-header:not(.is-solid) summary:focus-visible {
    outline-color: #ffffff;
  }

  /* ── Breakpoints ───────────────────────────────────────── */
  @media (max-width: 1023px) {
    :root { --site-header-h: 72px; }

    .site-header,
    .site-header.is-solid {
      background: #ffffff;
      border-bottom-color: var(--hdr-line);
    }

    .hdr-brand__mark { width: 58px; height: 58px; }
    .hdr-brand__name { font-size: 16px; color: var(--hdr-ink); }
    .hdr-brand__org { display: none; }
    .hdr-icon { color: var(--hdr-ink); }
    .hdr-icon:hover { color: var(--hdr-accent); }
    .hdr-lang a { color: #9ca3af; }
    .hdr-lang a[aria-current='true'] { color: var(--hdr-accent); }
    .hdr-lang__sep { color: var(--hdr-line); }
    .hdr-cta, .hdr-signout { display: none; }
  }

  @media (min-width: 640px) {
    .hdr-lang { display: inline-flex; }
  }

  /* Tablet keeps a compact set; the rest moves into the menu. */
  @media (min-width: 1024px) {
    .hdr-nav { display: flex; }
    .hdr-burger { display: none; }
  }

  /* Tablet: a compact four-item nav; the remainder lives in the menu. */
  @media (min-width: 1024px) and (max-width: 1279px) {
    .hdr-nav { gap: 12px; }
    .hdr-nav__link { font-size: 13.5px; }
    .hdr-nav__link:not([data-nav-index='0']):not([data-nav-index='1']):not([data-nav-index='2']):not([data-nav-index='3']) {
      display: none;
    }
    .hdr-icon--shortlist { display: none; }
    .hdr-burger { display: inline-flex; }
  }

  @media (min-width: 1440px) {
    .hdr-brand__org { display: -webkit-box; }
  }

  /* Inner pages start below the fixed header. */
  .site-shell:not(.homepage) .page-main {
    padding-top: var(--site-header-h);
  }
</style>

<header id="siteHeader" class="site-header {{ $isHomePage ? '' : 'is-solid' }}">
  <div class="site-header__inner">

    {{-- Left: logo + institutional lockup --}}
    <a class="hdr-brand" href="{{ $routeWithLang('/') }}" aria-label="{{ __('ui.brand.home_aria') }}">
      <img class="hdr-brand__mark"
           src="{{ asset('logo.png') }}"
           alt=""
           width="72" height="72"
           loading="eager" decoding="async">
      <span class="hdr-brand__text">
        <span class="hdr-brand__name">{{ __('ui.brand.title') }}</span>
        <span class="hdr-brand__org">{{ $headerCopy['org'] }}</span>
      </span>
    </a>

    {{-- Centre: single-row primary navigation --}}
    <nav class="hdr-nav" aria-label="{{ __('ui.aria.main_navigation') }}">
      @foreach($headerCopy['links'] as $i => [$key, $label, $href])
        <a class="hdr-nav__link"
           href="{{ $routeWithLang($href) }}"
           data-nav-index="{{ $i }}"
           @if($activePage === $key) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>

    {{-- Right: search, quick links, language, single accent action --}}
    <div class="hdr-actions">
      <details class="hdr-disclosure">
        <summary class="hdr-icon" role="button" aria-label="{{ $headerCopy['search'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="11" cy="11" r="6.5"></circle>
            <path d="M16 16l4.5 4.5"></path>
          </svg>
        </summary>
        <div class="hdr-panel hdr-panel--search">
          <form class="hdr-search__form" action="{{ $routeWithLang('/catalog') }}" method="get" role="search">
            @if($pageLang !== 'ru')
              <input type="hidden" name="lang" value="{{ $pageLang }}">
            @endif
            <label class="sr-only" for="site-search-input">{{ $headerCopy['search'] }}</label>
            <input id="site-search-input"
                   class="hdr-search__input"
                   type="search"
                   name="q"
                   autocomplete="off"
                   placeholder="{{ $headerCopy['search_placeholder'] }}">
            <button type="submit" class="hdr-search__submit">{{ $headerCopy['search'] }}</button>
          </form>
        </div>
      </details>

      <a class="hdr-icon hdr-icon--shortlist" href="{{ $routeWithLang('/shortlist') }}" aria-label="{{ $headerCopy['shortlist'] }}" title="{{ $headerCopy['shortlist'] }}">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M7 4.5h10a1 1 0 0 1 1 1v14.5l-6-3.5-6 3.5V5.5a1 1 0 0 1 1-1Z"></path>
        </svg>
      </a>

      @if($isAuthenticated)
        <a class="hdr-icon" href="{{ $routeWithLang('/dashboard/notifications') }}" aria-label="{{ $headerCopy['notifications'] }}" title="{{ $headerCopy['notifications'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15.5 17.5H8.5"></path>
            <path d="M6.5 17.5h11"></path>
            <path d="M18 17.5H6l1.2-1.8c.5-.8.8-1.7.8-2.7V10a4 4 0 0 1 8 0v3c0 1 .3 1.9.8 2.7L18 17.5Z"></path>
            <path d="M10.5 19.5a1.5 1.5 0 0 0 3 0"></path>
          </svg>
        </a>
      @endif

      <details class="hdr-disclosure hdr-lang" data-locale-switcher>
        <summary class="hdr-lang__trigger" role="button" aria-label="{{ $headerCopy['lang_aria'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="12" cy="12" r="8.5"></circle>
            <path d="M3.5 12h17"></path>
            <path d="M12 3.5c2.6 2.2 4 5 4 8.5s-1.4 6.3-4 8.5c-2.6-2.2-4-5-4-8.5s1.4-6.3 4-8.5Z"></path>
          </svg>
        </summary>
        <div class="hdr-lang__panel" role="menu" aria-label="{{ $headerCopy['lang_aria'] }}">
          @foreach(['kk', 'ru', 'en'] as $locale)
            <a class="hdr-lang__link"
               href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}"
               @if($pageLang === $locale) aria-current="true" @endif>
              <span>{{ $localeLabels[$locale] }}</span>
              @if($pageLang === $locale)
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:16px;height:16px;">
                  <path d="M5.5 12.5l4 4 9-10"></path>
                </svg>
              @endif
            </a>
          @endforeach
        </div>
      </details>

      @if($isAuthenticated)
        <a class="hdr-cta" href="{{ $routeWithLang('/dashboard') }}">{{ $headerCopy['dashboard'] }}</a>
        <a class="hdr-signout" href="{{ $routeWithLang('/logout') }}">{{ $headerCopy['signout'] }}</a>
      @else
        <a class="hdr-cta" href="{{ $routeWithLang('/login') }}">{{ $headerCopy['guest'] }}</a>
      @endif

      {{-- Menu: full navigation on mobile, overflow + institution links on desktop --}}
      <details class="hdr-disclosure hdr-burger">
        <summary class="hdr-icon" role="button" aria-label="{{ $headerCopy['menu'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4.5 7h15"></path>
            <path d="M4.5 12h15"></path>
            <path d="M4.5 17h15"></path>
          </svg>
        </summary>
        <nav class="hdr-panel hdr-panel--menu" aria-label="{{ $headerCopy['menu'] }}">
          @foreach($headerCopy['links'] as [$key, $label, $href])
            <a class="hdr-menu__link" href="{{ $routeWithLang($href) }}">{{ $label }}</a>
          @endforeach

          <p class="hdr-menu__group">{{ $headerCopy['institution'] }}</p>
          @foreach($headerCopy['institution_links'] as [$label, $href])
            <a class="hdr-menu__link hdr-menu__link--muted" href="{{ $routeWithLang($href) }}">{{ $label }}</a>
          @endforeach
          <a class="hdr-menu__link hdr-menu__link--muted" href="{{ $routeWithLang('/shortlist') }}">{{ $headerCopy['shortlist'] }}</a>

          <p class="hdr-menu__group">{{ $headerCopy['dashboard'] }}</p>
          @unless($isAuthenticated)
            <a class="hdr-menu__link hdr-menu__link--accent" href="{{ $routeWithLang('/login') }}">{{ $headerCopy['guest'] }}</a>
          @endunless
          <a class="hdr-menu__link hdr-menu__link--accent" href="{{ $routeWithLang('/dashboard') }}">{{ $headerCopy['dashboard'] }}</a>
          @if($isAuthenticated)
            <a class="hdr-menu__link hdr-menu__link--muted" href="{{ $routeWithLang('/logout') }}">{{ $headerCopy['signout'] }}</a>
          @endif
        </nav>
      </details>
    </div>
  </div>
</header>

@if($isHomePage)
<script>
  (() => {
    const header = document.getElementById('siteHeader');
    if (!header) return;

    let scheduled = false;
    const sync = () => {
      header.classList.toggle('is-solid', window.scrollY > 24);
      scheduled = false;
    };

    const schedule = () => {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(sync);
    };

    sync();
    window.addEventListener('scroll', schedule, { passive: true });
  })();
</script>
@endif

<script>
  // Close header disclosures on outside click and on Escape.
  (() => {
    const panels = document.querySelectorAll('#siteHeader .hdr-disclosure');
    if (!panels.length) return;

    document.addEventListener('click', (event) => {
      panels.forEach((panel) => {
        if (panel.open && !panel.contains(event.target)) panel.open = false;
      });
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      panels.forEach((panel) => { panel.open = false; });
    });
  })();
</script>
