@php
  $pageLang = $pageLang ?? app()->getLocale();
  $pageLang = in_array($pageLang, ['kk', 'ru', 'en'], true) ? $pageLang : 'kk';
  $activePage = $activePage ?? '';
  $isHomePage = $activePage === 'home';
  $isAuthenticated = (bool) session('library.user');

  // The reader notification bell is only meaningful for ordinary members —
  // /dashboard/* is closed to librarians and administrators.
  $navbarSessionRole = mb_strtolower(trim((string) (session('library.user')['role'] ?? '')));
  $navbarCanonicalRole = mb_strtolower(trim((string) (session('library.user')['canonical_role'] ?? '')));
  if ($navbarCanonicalRole === '') {
      $navbarCanonicalRole = $navbarSessionRole === 'reader' ? 'member' : $navbarSessionRole;
  }
  $navbarDashboardHref = match ($navbarCanonicalRole) {
      'admin' => '/admin',
      'member' => '/dashboard',
      default => '/librarian',
  };
  $isMemberReader = $isAuthenticated && $navbarSessionRole === 'reader';
  $unreadNotifications = 0;
  if ($isMemberReader && auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('reader_notifications')) {
      $unreadNotifications = \App\Models\Catalog\ReaderNotification::query()
          ->where('user_id', auth()->id())
          ->whereNull('read_at')
          ->count();
  }

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
      if ($pageLang !== 'kk') $query['lang'] = $pageLang;
      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

      return $normalizedPath . ($query ? ('?' . http_build_query($query)) : '');
  };
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
    min-height: var(--site-header-h);
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
    max-width: var(--page-max);
    margin-inline: auto;
    padding-inline: var(--page-pad);
    padding-block: 14px;
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
    gap: 22px;
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

  .hdr-icon svg {
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

  /* Shared language switcher stays visible on desktop and mobile. */
  .hdr-lang {
    display: inline-flex;
    position: relative;
  }

  /* The single accent element in the header. */
  .hdr-cta {
    display: inline-flex;
    align-items: center;
    padding: 10px 22px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    color: #ffffff;
    font-family: 'Google Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
    text-decoration: none;
    transition: background-color 250ms ease, border-color 250ms ease;
  }

  .hdr-cta:hover { background: rgba(255, 255, 255, 0.18); border-color: rgba(255, 255, 255, 0.3); }
  .site-header.is-solid .hdr-cta {
    background: rgba(15, 76, 129, 0.08);
    border-color: rgba(15, 76, 129, 0.12);
    color: var(--hdr-accent);
  }
  .site-header.is-solid .hdr-cta:hover {
    background: rgba(15, 76, 129, 0.12);
    border-color: rgba(15, 76, 129, 0.18);
  }
  .hdr-cta[href*='/dashboard'] {
    background: var(--hdr-accent);
    border-color: var(--hdr-accent);
    color: #ffffff;
    backdrop-filter: none;
    -webkit-backdrop-filter: none;
  }
  .hdr-cta[href*='/dashboard']:hover {
    background: #0c3c68;
    border-color: #0c3c68;
  }

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

  .hdr-panel--search {
    position: fixed;
    inset: var(--site-header-h) 0 0 0;
    top: var(--site-header-h);
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 130;
    display: block;
    margin: 0;
    padding: 20px 0 28px;
    border: 0;
    background: transparent;
    box-shadow: none;
  }

  .hdr-search__backdrop {
    position: absolute;
    inset: 0;
    border: 0;
    background: rgba(8, 15, 25, 0.72);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    cursor: pointer;
  }

  .hdr-search__sheet {
    position: relative;
    z-index: 1;
    width: min(100vw - 24px, 100%);
    height: calc(100% - 8px);
    margin-inline: auto;
    padding: clamp(16px, 2vw, 28px);
    border: 1px solid rgba(227, 230, 229, 0.16);
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr);
    gap: 18px;
  }

  .hdr-search__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
  }

  .hdr-search__title {
    margin: 0;
    color: var(--hdr-ink);
    font-family: 'Literata', Georgia, serif;
    font-size: clamp(24px, 2.2vw, 34px);
    line-height: 1.05;
    letter-spacing: -0.04em;
  }

  .hdr-search__meta {
    margin: 8px 0 0;
    color: var(--hdr-ink-soft);
    font-size: 13px;
    line-height: 1.4;
  }

  .hdr-search__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--hdr-line);
    background: #fff;
    color: var(--hdr-ink);
    cursor: pointer;
    flex: 0 0 auto;
  }

  .hdr-search__form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: stretch;
    gap: 12px;
  }

  .hdr-search__input {
    flex: 1;
    min-width: 0;
    width: 100%;
    padding: 16px 18px;
    border: 1px solid var(--hdr-line);
    background: #fff;
    font-family: inherit;
    font-size: 16px;
    color: var(--hdr-ink);
  }

  .hdr-search__input:focus {
    outline: none;
    border-color: var(--hdr-accent);
  }

  .hdr-search__submit {
    padding: 0 22px;
    min-height: 54px;
    border: 1px solid var(--hdr-accent);
    background: var(--hdr-accent);
    color: #fff;
    font-family: 'Google Sans', sans-serif;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
  }

  .hdr-search__results {
    min-height: 0;
    overflow: auto;
    padding-right: 2px;
  }

  .hdr-search__results-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: end;
    margin-bottom: 14px;
  }

  .hdr-search__results-head strong {
    color: var(--hdr-ink);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .hdr-search__results-head span {
    color: var(--hdr-ink-soft);
    font-size: 13px;
  }

  .hdr-search__results-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .hdr-search__result {
    display: grid;
    grid-template-columns: 104px minmax(0, 1fr);
    gap: 16px;
    min-height: 176px;
    padding: 16px;
    border: 1px solid rgba(229, 231, 235, 0.95);
    border-radius: 18px;
    background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
    text-decoration: none;
    transition:
      transform 200ms ease,
      border-color 200ms ease,
      background-color 200ms ease,
      box-shadow 200ms ease;
  }

  .hdr-search__result:hover {
    border-color: var(--hdr-accent);
    background: #ffffff;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
    transform: translateY(-2px);
  }

  .hdr-search__result-cover {
    width: 104px;
    min-height: 144px;
    border: 1px solid rgba(15, 32, 53, 0.12);
    border-radius: 14px;
    overflow: hidden;
    position: relative;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, 0.16),
      0 10px 20px rgba(15, 23, 42, 0.12);
  }

  .hdr-search__result-cover::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.06), transparent 28%),
      linear-gradient(180deg, transparent 56%, rgba(8, 15, 25, 0.22));
    pointer-events: none;
  }

  .hdr-search__result-cover--fallback {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 12px;
    color: #fff;
    background:
      radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.18), transparent 28%),
      linear-gradient(160deg, var(--hdr-search-cover-tone, #1f3a58), #0f2035 72%);
  }

  .hdr-search__result-cover--fallback::after {
    background:
      linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent 30%),
      linear-gradient(180deg, transparent 58%, rgba(8, 15, 25, 0.3));
  }

  .hdr-search__result-cover-tag {
    position: relative;
    z-index: 1;
    align-self: flex-start;
    padding: 4px 8px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.96);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
  }

  .hdr-search__result-cover-initial {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: flex-end;
    justify-content: flex-end;
    min-height: 64px;
    color: rgba(255, 255, 255, 0.96);
    font-family: 'Literata', Georgia, serif;
    font-size: 42px;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.06em;
  }

  .hdr-search__result-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    justify-content: center;
  }

  .hdr-search__result-kicker {
    margin: 0 0 8px;
    color: #5c6866;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
  }

  .hdr-search__result-title {
    margin: 0;
    color: var(--hdr-ink);
    font-family: 'Literata', Georgia, serif;
    font-size: 18px;
    line-height: 1.08;
    letter-spacing: -0.03em;
  }

  .hdr-search__result-author,
  .hdr-search__result-desc,
  .hdr-search__result-meta {
    margin: 0;
    color: var(--hdr-ink-soft);
    font-size: 13px;
    line-height: 1.45;
  }

  .hdr-search__result-desc {
    margin-top: 8px;
    color: #5c6866;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
  }

  .hdr-search__result-meta {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
    color: var(--hdr-ink-soft);
    font-size: 12px;
  }

  .hdr-search__result-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border: 1px solid rgba(15, 76, 129, 0.12);
    border-radius: 999px;
    background: rgba(15, 76, 129, 0.04);
    color: var(--hdr-ink-soft);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    white-space: nowrap;
  }

  .hdr-search__empty {
    padding: 28px;
    border: 1px dashed var(--hdr-line);
    background: rgba(255, 255, 255, 0.92);
    color: var(--hdr-ink-soft);
    font-size: 14px;
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
    .hdr-nav { gap: 14px; }
    .hdr-nav__link { font-size: 13px; }
    .hdr-cta, .hdr-signout { display: none; }
  }

  /* Tablet keeps a compact set; the rest moves into the menu. */
  @media (min-width: 1024px) {
    .hdr-nav { display: flex; }
    .hdr-burger { display: none; }
  }

  /* Tablet: a compact four-item nav; the remainder lives in the menu. */
  @media (min-width: 1024px) and (max-width: 1279px) {
    .hdr-nav { gap: 18px; }
    .hdr-nav__link { font-size: 13.5px; }
    .hdr-nav__link:not([data-nav-index='0']):not([data-nav-index='1']):not([data-nav-index='2']):not([data-nav-index='3']) {
      display: none;
    }
    .hdr-icon--shortlist { display: none; }
    .hdr-burger { display: inline-flex; }
  }

  .hdr-icon--shortlist,
  .hdr-icon--alerts {
    position: relative;
  }

  .hdr-shortlist-count {
    position: absolute;
    top: -7px;
    right: -7px;
    min-width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    border-radius: 999px;
    background: #006a6a;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    line-height: 1;
  }

  .hdr-shortlist-count[hidden] {
    display: none !important;
  }

  @media (min-width: 1440px) {
    .hdr-brand__org { display: -webkit-box; }
  }

  /* Inner pages start below the fixed header. */
  .site-shell:not(.homepage) .page-main {
    padding-top: var(--site-header-h);
  }

  /* ── Small-screen fit ──────────────────────────────────────
     Below the desktop breakpoint the action cluster must leave room for the
     brand: without this the burger is pushed past the viewport edge and only
     body{overflow-x:hidden} hides the damage. Per spec the mobile header keeps
     search, language and the burger; the shortlist lives inside the menu. */
  @media (max-width: 1279px) {
    .hdr-icon--shortlist { display: none; }
  }

  @media (max-width: 767px) {
    .site-header {
      min-height: 72px;
    }
    .site-header__inner {
      gap: 10px;
    }
    .hdr-nav {
      display: none;
    }
    .hdr-cta,
    .hdr-signout,
    .hdr-icon--shortlist {
      display: none !important;
    }
    .hdr-actions {
      gap: 4px;
      margin-left: auto;
    }
    .hdr-burger {
      display: inline-flex;
    }
    .hdr-panel--menu {
      width: min(94vw, 360px);
      max-height: 78vh;
    }
    .hdr-panel--search { padding: 12px 0 18px; }
    .hdr-search__sheet {
      width: min(100vw - 16px, 100%);
      padding: 16px;
      gap: 14px;
    }
    .hdr-search__form { grid-template-columns: 1fr; }
    .hdr-search__submit { width: 100%; }
    .hdr-search__results-list { grid-template-columns: 1fr; }
    .hdr-search__result { grid-template-columns: 74px minmax(0, 1fr); min-height: 0; }
    .hdr-search__result-cover { width: 74px; min-height: 104px; border-radius: 12px; }
    .hdr-search__result-cover-initial { font-size: 30px; min-height: 44px; }
  }

  @media (min-width: 768px) {
    .hdr-search__sheet {
      width: min(100vw - 32px, 1600px);
      height: calc(100% - 18px);
    }
  }

  @media (max-width: 520px) {
    .hdr-brand { gap: 10px; }
    .hdr-brand__mark { width: 38px; height: 38px; }
    .hdr-brand__name { font-size: 14px; }
    .hdr-actions { gap: 2px; padding-left: 4px; }
    .hdr-icon { width: 36px; height: 36px; }
  }
</style>

<header id="siteHeader" class="site-header {{ $isHomePage ? '' : 'is-solid' }}">
  <div class="site-header__inner">

    {{-- Left: the canonical institutional lockup shared by every workspace. --}}
    <x-library-brand variant="public" :href="$routeWithLang('/')" />

    {{-- Centre: single-row primary navigation --}}
    <nav class="hdr-nav" aria-label="{{ __('ui.aria.main_navigation') }}">
      @foreach($headerCopy['links'] as $i => [$key, $label, $href])
        <a class="hdr-nav__link"
           href="{{ $routeWithLang($href) }}"
           data-nav-index="{{ $i }}"
           @if($activePage === $key) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>

    {{-- Right: search, quick links, language, account --}}
    <div class="hdr-actions">
      <details class="hdr-disclosure hdr-search" data-global-search>
        <summary class="hdr-icon" role="button" aria-label="{{ $headerCopy['search'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="11" cy="11" r="6.5"></circle>
            <path d="M16 16l4.5 4.5"></path>
          </svg>
        </summary>
        <div class="hdr-panel hdr-panel--search" role="dialog" aria-modal="true" aria-label="{{ $headerCopy['search'] }}">
          <button type="button" class="hdr-search__backdrop" data-search-dismiss aria-label="{{ $headerCopy['search'] }}"></button>
          <div class="hdr-search__sheet">
            <div class="hdr-search__head">
              <div>
                <h2 class="hdr-search__title">{{ $headerCopy['search'] }}</h2>
                <p class="hdr-search__meta" data-search-status>{{ $headerCopy['search_placeholder'] }}</p>
              </div>
              <button type="button" class="hdr-search__close" data-search-dismiss aria-label="{{ $pageLang === 'kk' ? 'Жабу' : ($pageLang === 'ru' ? 'Закрыть' : 'Close') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;">
                  <path d="M6 6l12 12"></path>
                  <path d="M18 6L6 18"></path>
                </svg>
              </button>
            </div>
            <form class="hdr-search__form" action="{{ $routeWithLang('/catalog') }}" method="get" role="search">
              @if($pageLang !== 'kk')
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
            <div class="hdr-search__results">
              <div class="hdr-search__results-head">
                <strong>{{ $headerCopy['search'] }}</strong>
                <span data-search-count></span>
              </div>
              <div class="hdr-search__results-list" data-search-results></div>
            </div>
          </div>
        </div>
      </details>

      <a class="hdr-icon hdr-icon--shortlist" href="{{ $routeWithLang('/shortlist') }}" aria-label="{{ $headerCopy['shortlist'] }}" title="{{ $headerCopy['shortlist'] }}">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M7 4.5h10a1 1 0 0 1 1 1v14.5l-6-3.5-6 3.5V5.5a1 1 0 0 1 1-1Z"></path>
        </svg>
        <span id="header-shortlist-count" class="hdr-shortlist-count" hidden>0</span>
      </a>

      @if($isMemberReader)
        <a class="hdr-icon hdr-icon--alerts"
           href="{{ $routeWithLang('/dashboard/notifications') }}"
           aria-label="{{ $unreadNotifications > 0 ? __('librarian.member.notifications.unread_count', ['count' => $unreadNotifications]) : $headerCopy['notifications'] }}"
           title="{{ $headerCopy['notifications'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6.5 17V10.5a5.5 5.5 0 1 1 11 0V17l1.5 2h-14l1.5-2Z"></path>
            <path d="M10 19.5a2 2 0 0 0 4 0"></path>
          </svg>
          @if($unreadNotifications > 0)
            <span class="hdr-shortlist-count">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
          @endif
        </a>
      @endif

      <x-locale-switcher
        variant="dark"
        :showLabel="false"
        class="locale-switcher locale-switcher--dark hdr-lang"
        style="background: transparent; border: none; box-shadow: none;"
      />

      @if($isAuthenticated)
        <a class="hdr-icon hdr-icon--account" href="{{ $routeWithLang($navbarDashboardHref) }}" aria-label="{{ $headerCopy['dashboard'] }}" title="{{ $headerCopy['dashboard'] }}">
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="12" cy="8.5" r="3.25"></circle>
            <path d="M5.5 19c0-3.6 2.9-6.5 6.5-6.5s6.5 2.9 6.5 6.5"></path>
          </svg>
        </a>
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
            <a class="hdr-menu__link" href="{{ $routeWithLang($href) }}" @if($activePage === $key) aria-current="page" @endif>{{ $label }}</a>
          @endforeach

          <p class="hdr-menu__group">{{ $headerCopy['institution'] }}</p>
          @foreach($headerCopy['institution_links'] as [$label, $href])
            <a class="hdr-menu__link hdr-menu__link--muted" href="{{ $routeWithLang($href) }}">{{ $label }}</a>
          @endforeach
          <a class="hdr-menu__link hdr-menu__link--muted" href="{{ $routeWithLang('/shortlist') }}">{{ $headerCopy['shortlist'] }}</a>
          @if($isMemberReader)
            <a class="hdr-menu__link hdr-menu__link--muted" href="{{ $routeWithLang('/dashboard/notifications') }}">
              {{ $headerCopy['notifications'] }}@if($unreadNotifications > 0) ({{ $unreadNotifications }})@endif
            </a>
          @endif

          <p class="hdr-menu__group">{{ $headerCopy['dashboard'] }}</p>
          @unless($isAuthenticated)
            <a class="hdr-menu__link hdr-menu__link--accent" href="{{ $routeWithLang('/login') }}">{{ $headerCopy['guest'] }}</a>
          @else
            <a class="hdr-menu__link hdr-menu__link--accent" href="{{ $routeWithLang($navbarDashboardHref) }}">{{ $headerCopy['dashboard'] }}</a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="hdr-menu__link hdr-menu__link--muted" type="submit" style="width:100%;border:0;background:transparent;text-align:left;font:inherit;cursor:pointer;">{{ $headerCopy['signout'] }}</button>
            </form>
          @endunless
        </nav>
      </details>
    </div>
  </div>
</header>

<script>
  window.refreshHeaderShortlistCount = async function refreshHeaderShortlistCount() {
    const badge = document.getElementById('header-shortlist-count');
    if (!badge) return;

    try {
      const response = await fetch('/api/v1/shortlist/summary', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!response.ok) return;

      const payload = await response.json();
      const total = Math.max(0, Number(payload?.data?.total || 0));
      badge.textContent = total > 99 ? '99+' : String(total);
      badge.hidden = total <= 0;
    } catch (_) {}
  };

  window.refreshHeaderShortlistCount();
</script>

<script>
  (() => {
    const searchDialog = document.querySelector('#siteHeader .hdr-search');
    if (!searchDialog) return;

    const pageLang = @json($pageLang);
    const catalogBase = @json($routeWithLang('/catalog'));
    const searchCopy = {
      ru: {
        loading: 'Показываем популярные материалы каталога',
        loadingQuery: 'Ищем по запросу',
        recommended: 'Рекомендуемые материалы из каталога',
        empty: 'По вашему запросу ничего не найдено. Попробуйте другое название, автора или тему.',
        error: 'Не удалось загрузить результаты. Попробуйте ещё раз через несколько секунд.',
        fallback: 'Каталожная запись',
        authorFallback: 'Автор не указан',
        udcLabel: 'УДК',
        count: 'материалов',
        yearLabel: 'Год',
        languageLabel: 'Язык',
        availabilityLabel: 'Наличие',
        resourceTypes: {
          book: 'Книга',
          journal: 'Журнал',
          article: 'Статья',
          thesis: 'Диссертация',
          dissertation: 'Диссертация',
          archive: 'Архив',
          electronic: 'Электронный ресурс',
          manuscript: 'Рукопись',
        },
      },
      kk: {
        loading: 'Каталогтың танымал материалдарын көрсетеміз',
        loadingQuery: 'Сұрау бойынша іздеу жүріп жатыр',
        recommended: 'Каталогтан ұсынылған материалдар',
        empty: 'Сұрауыңыз бойынша нәтиже табылмады. Атауды, авторды немесе тақырыпты өзгертіп көріңіз.',
        error: 'Нәтижелерді жүктеу мүмкін болмады. Бірнеше секундтан кейін қайталап көріңіз.',
        fallback: 'Каталогтық жазба',
        authorFallback: 'Автор көрсетілмеген',
        udcLabel: 'ӘОЖ',
        count: 'материал',
        yearLabel: 'Жыл',
        languageLabel: 'Тіл',
        availabilityLabel: 'Қолжетімділік',
        resourceTypes: {
          book: 'Кітап',
          journal: 'Журнал',
          article: 'Мақала',
          thesis: 'Диссертация',
          dissertation: 'Диссертация',
          archive: 'Мұрағат',
          electronic: 'Электрондық ресурс',
          manuscript: 'Қолжазба',
        },
      },
      en: {
        loading: 'Showing popular catalog materials',
        loadingQuery: 'Searching for',
        recommended: 'Recommended catalog materials',
        empty: 'No results were found. Try another title, author, or subject.',
        error: 'Unable to load results. Please try again in a few seconds.',
        fallback: 'Catalog record',
        authorFallback: 'Author not specified',
        udcLabel: 'UDC',
        count: 'materials',
        yearLabel: 'Year',
        languageLabel: 'Language',
        availabilityLabel: 'Availability',
        resourceTypes: {
          book: 'Book',
          journal: 'Journal',
          article: 'Article',
          thesis: 'Thesis',
          dissertation: 'Dissertation',
          archive: 'Archive',
          electronic: 'Electronic resource',
          manuscript: 'Manuscript',
        },
      },
    }[pageLang] || {
      loading: 'Showing popular catalog materials',
      loadingQuery: 'Searching for',
      recommended: 'Recommended catalog materials',
      empty: 'No results were found.',
      error: 'Unable to load results.',
      fallback: 'Catalog record',
      authorFallback: 'Author not specified',
      udcLabel: 'UDC',
      count: 'materials',
      yearLabel: 'Year',
      languageLabel: 'Language',
      availabilityLabel: 'Availability',
      resourceTypes: {
        book: 'Book',
        journal: 'Journal',
        article: 'Article',
        thesis: 'Thesis',
        dissertation: 'Dissertation',
        archive: 'Archive',
        electronic: 'Electronic resource',
        manuscript: 'Manuscript',
      },
    };
    const input = searchDialog.querySelector('#site-search-input');
    const form = searchDialog.querySelector('.hdr-search__form');
    const backdrop = searchDialog.querySelector('.hdr-search__backdrop');
    const results = searchDialog.querySelector('[data-search-results]');
    const count = searchDialog.querySelector('[data-search-count]');
    const status = searchDialog.querySelector('[data-search-status]');
    const dismissButtons = searchDialog.querySelectorAll('[data-search-dismiss]');
    const header = document.getElementById('siteHeader');
    const body = document.body;
    const defaultStatus = status?.textContent || '';
    let debounceId = null;
    let activeController = null;

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const withLang = (path, query = {}) => {
      const url = new URL(path, window.location.origin);
      Object.entries(query).forEach(([key, value]) => {
        if (value !== null && value !== undefined && String(value).trim() !== '') {
          url.searchParams.set(key, value);
        }
      });
      if (pageLang !== 'kk') url.searchParams.set('lang', pageLang);
      return url.toString();
    };

    const buildDescription = (item) => {
      const annotation = String(item?.annotation || '').trim();
      if (annotation) return annotation;
      const subtitle = String(item?.title?.subtitle || '').trim();
      if (subtitle) return subtitle;
      const subjects = Array.isArray(item?.classification)
        ? item.classification.map((subject) => String(subject?.label || '').trim()).filter(Boolean)
        : [];
      return subjects.slice(0, 2).join(' · ');
    };

    const renderResults = (items, query) => {
      const normalized = Array.isArray(items) ? items.slice(0, 6) : [];
      const q = String(query || '').trim();

      if (status) {
        status.textContent = q
          ? `${searchCopy.loadingQuery} “${q}”…`
          : searchCopy.recommended;
      }

      if (count) {
        count.textContent = normalized.length ? `${normalized.length} ${searchCopy.count}` : '';
      }

      if (!results) return;

      if (!normalized.length) {
        results.innerHTML = `<div class="hdr-search__empty">${escapeHtml(searchCopy.empty)}</div>`;
        return;
      }

      results.innerHTML = normalized.map((item, index) => {
        const titleText = String(item?.title?.display || item?.title?.raw || searchCopy.fallback);
        const authorText = String(item?.primaryAuthor || searchCopy.authorFallback);
        const publisherText = String(item?.publisher?.name || '');
        const yearText = String(item?.publicationYear || '—');
        const isbnText = String(item?.isbn?.raw || '');
        const udcText = String(item?.udc?.raw || '');
        const languageLabel = String(item?.language?.label || '');
        const resourceTypeKey = String(item?.resourceType || '').toLowerCase();
        const resourceTypeText = searchCopy.resourceTypes?.[resourceTypeKey] || (resourceTypeKey ? resourceTypeKey : searchCopy.fallback);
        const description = buildDescription(item);
        const coverPath = String(item?.coverUrl || item?.coverPath || item?.cover?.medium || item?.cover?.small || '');
        const coverUrl = coverPath
          ? (coverPath.startsWith('http://') || coverPath.startsWith('https://') || coverPath.startsWith('/') ? coverPath : `/storage/${coverPath.replace(/^\/+/, '')}`)
          : '';
        const detailIdentifier = isbnText || String(item?.id || '');
        const href = detailIdentifier
          ? withLang(`/book/${encodeURIComponent(detailIdentifier)}`)
          : withLang('/catalog', q ? { q } : {});
        const chips = [];
        if (item?.copies?.available !== undefined) {
          chips.push(`${searchCopy.availabilityLabel} ${item.copies.available}`);
        }
        if (publisherText) chips.push(publisherText);
        if (yearText !== '—') chips.push(`${searchCopy.yearLabel} ${yearText}`);
        if (languageLabel) chips.push(`${searchCopy.languageLabel} ${languageLabel}`);
        if (udcText !== '') chips.push(`${searchCopy.udcLabel} ${udcText}`);
        const keywordText = Array.isArray(item?.keywords)
          ? item.keywords.map((keyword) => String(keyword).trim()).filter(Boolean).slice(0, 2).join(' · ')
          : '';
        const coverTone = ['#102945', '#0f4c81', '#006a6a', '#315646', '#5c4b2e', '#5f6f85'][index % 6];
        const coverClass = coverUrl
          ? 'hdr-search__result-cover hdr-search__result-cover--image'
          : 'hdr-search__result-cover hdr-search__result-cover--fallback';
        const coverLabel = titleText.trim().charAt(0).toUpperCase() || 'B';

        return `
          <a class="hdr-search__result" href="${href}">
            <div class="${coverClass}"${coverUrl ? ` style="background-image:url('${escapeHtml(coverUrl)}')"` : ` style="--hdr-search-cover-tone:${coverTone};"`}>
              <span class="hdr-search__result-cover-tag">${escapeHtml(resourceTypeText)}</span>
              <span class="hdr-search__result-cover-initial">${escapeHtml(coverLabel)}</span>
            </div>
            <div class="hdr-search__result-copy">
              <div class="hdr-search__result-kicker">${escapeHtml(publisherText || searchCopy.fallback)}</div>
              <h3 class="hdr-search__result-title">${escapeHtml(titleText)}</h3>
              <p class="hdr-search__result-author">${escapeHtml(authorText)}</p>
              ${description ? `<p class="hdr-search__result-desc">${escapeHtml(description)}</p>` : ''}
              <div class="hdr-search__result-meta">
                ${chips.length ? chips.slice(0, 4).map((chip) => `<span class="hdr-search__result-chip">${escapeHtml(chip)}</span>`).join('') : `<span class="hdr-search__result-chip">${escapeHtml(searchCopy.fallback)}</span>`}
                ${keywordText ? `<span class="hdr-search__result-chip">${escapeHtml(keywordText)}</span>` : ''}
              </div>
            </div>
          </a>
        `;
      }).join('');
    };

    const lockBody = (locked) => {
      body.style.overflow = locked ? 'hidden' : '';
    };

    const closeSearch = () => {
      if (activeController) {
        activeController.abort();
      }
      searchDialog.open = false;
      lockBody(false);
    };

    const openSearch = () => {
      lockBody(true);
      window.requestAnimationFrame(() => {
        input?.focus();
        loadResults(input?.value || '');
      });
    };

    async function loadResults(query) {
      const currentQuery = String(query || '').trim();

      if (activeController) {
        activeController.abort();
      }
      activeController = new AbortController();

      if (status) {
        status.textContent = currentQuery
          ? `${searchCopy.loadingQuery} “${currentQuery}”…`
          : searchCopy.loading;
      }

      const params = new URLSearchParams({ limit: '6', sort: 'popular' });
      if (currentQuery) params.set('q', currentQuery);
      if (pageLang !== 'kk') params.set('lang', pageLang);

      try {
        const response = await fetch(`/api/v1/catalog-db?${params.toString()}`, {
          headers: { Accept: 'application/json' },
          signal: activeController.signal,
        });
        const payload = await response.json();
        renderResults(Array.isArray(payload?.data) ? payload.data : [], currentQuery);
      } catch (error) {
        if (error?.name === 'AbortError') return;
        if (results) {
          results.innerHTML = `<div class="hdr-search__empty">${escapeHtml(searchCopy.error)}</div>`;
        }
      }
    }

    searchDialog.addEventListener('toggle', () => {
      if (searchDialog.open) openSearch();
      else closeSearch();
    });

    input?.addEventListener('input', () => {
      window.clearTimeout(debounceId);
      debounceId = window.setTimeout(() => loadResults(input.value), 220);
    });

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      const query = String(input?.value || '').trim();
      const url = new URL(catalogBase, window.location.origin);
      if (query) url.searchParams.set('q', query);
      if (pageLang !== 'kk') url.searchParams.set('lang', pageLang);
      window.location.href = url.toString();
    });

    dismissButtons.forEach((button) => {
      button.addEventListener('click', closeSearch);
    });

    backdrop?.addEventListener('click', closeSearch);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && searchDialog.open) {
        closeSearch();
      }
    });
  })();
</script>

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
