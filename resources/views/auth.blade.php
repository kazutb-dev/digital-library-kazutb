@php
  /*
   * The sign-in screen is deliberately NOT part of the public shell: no navbar,
   * no footer, no page scroll. It fills exactly one viewport and behaves like a
   * gate rather than a content page.
   *
   * It still has to read as the same product, so instead of inventing a palette
   * it loads the site's own token sheet (public-pages-v2.css defines the
   * --portal-* variables on :root) and reuses that grammar: Literata headings,
   * ink/teal accents, square corners, hairline rules.
   */
  $authLang = $lang ?? app()->getLocale();
  $authLang = in_array($authLang, ['kk', 'ru', 'en'], true) ? $authLang : 'kk';
  $pageCopy = is_array($copy ?? null) ? ($copy[$authLang] ?? $copy['kk'] ?? []) : [];
  $redirectTarget = request()->query('redirect', '/dashboard');

  $routeWithLang = static fn (string $path): string => $path;
  $demoIconNames = [
      'student' => 'school',
      'teacher' => 'menu_book',
      'librarian' => 'local_library',
      'director' => 'monitoring',
      'senior_librarian' => 'explore',
      'acquisitions' => 'inventory_2',
      'cataloguer' => 'sell',
      'bibliographer' => 'manage_search',
      'admin' => 'admin_panel_settings',
  ];

  /*
   * Built here rather than inline in @json(): Blade's directive-argument parser
   * truncates a multi-line array literal that contains nested [] lookups.
   */
  $authI18n = [
      'ru' => [
          'authError' => 'Ошибка авторизации',
          'fillFields' => 'Заполните логин/email и пароль.',
          'submitting' => 'Входим…',
          'success' => 'Вход выполнен успешно. Перенаправление…',
          'submitError' => 'Не удалось выполнить вход',
          'demoSuccess' => 'Быстрый вход выполнен. Перенаправление…',
          'demoError' => 'Ошибка быстрого входа',
          'ssoPending' => 'Институциональный SSO будет подключён через канал доступа KazUTB.',
      ],
      'kk' => [
          'authError' => 'Кіру қатесі',
          'fillFields' => 'Логин/email мен құпиясөзді толтырыңыз.',
          'submitting' => 'Кіріп жатырмыз…',
          'success' => 'Кіру сәтті өтті. Қайта бағытталуда…',
          'submitError' => 'Кіру мүмкін болмады',
          'demoSuccess' => 'Жедел кіру орындалды. Қайта бағытталуда…',
          'demoError' => 'Жедел кіру қатесі',
          'ssoPending' => 'Институционалдық SSO KazUTB қолжетімділік арнасы арқылы қосылады.',
      ],
      'en' => [
          'authError' => 'Authentication failed',
          'fillFields' => 'Enter both login/email and password.',
          'submitting' => 'Signing in…',
          'success' => 'Sign-in successful. Redirecting…',
          'submitError' => 'Unable to sign in',
          'demoSuccess' => 'Quick sign-in completed. Redirecting…',
          'demoError' => 'Quick sign-in failed',
          'ssoPending' => 'Institutional SSO will be connected through the KazUTB access channel.',
      ],
  ];

  $authConfig = [
      'lang' => $authLang,
      'redirectTarget' => $redirectTarget,
      'submitLabel' => $pageCopy['submit'] ?? 'Log in',
  ];
@endphp
<!DOCTYPE html>
<html lang="{{ $authLang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ __('auth.title').' — '.__('brand.library.name') }}</title>
  @include('partials.favicons')
  <link rel="stylesheet" href="/fonts/fonts.css">
  {{-- Loaded for its :root --portal-* design tokens, so this screen cannot
       drift from the palette the rest of the site uses. --}}
  <link rel="stylesheet" href="/css/public-pages-v2.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }

    html, body {
      height: 100%;
      margin: 0;
      /* The gate never scrolls the page. Anything that does not fit scrolls
         inside the right-hand pane instead, so no control becomes unreachable
         on a short viewport. */
      overflow: hidden;
    }

    body {
      background: var(--portal-wash);
      color: var(--portal-ink);
      font-family: 'Google Sans', sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    .material-symbols-outlined {
      font-family: 'Material Symbols Outlined';
      font-weight: normal;
      font-style: normal;
      line-height: 1;
      letter-spacing: normal;
      text-transform: none;
      white-space: nowrap;
      direction: ltr;
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    .auth-screen {
      display: grid;
      /* dvh keeps the gate exactly one viewport tall on mobile browsers whose
         toolbars change the vh reference mid-scroll. */
      height: 100vh;
      height: 100dvh;
      grid-template-columns: minmax(0, 0.72fr) minmax(0, 1.28fr);
    }

    /* ── Left: institutional pane ─────────────────────────────── */

    .auth-brand {
      position: relative;
      display: grid;
      grid-template-rows: auto minmax(0, 1fr) auto;
      gap: 32px;
      overflow: hidden;
      padding: clamp(32px, 3.4vw, 56px);
      /* Fallback behind the photo: shows while the image loads and if it 404s,
         so the white text is never left on a bare background. */
      background: #0b1830;
      color: #fff;
    }

    /* Same photograph and the same treatment as the homepage hero, so the gate
       reads as the same front door rather than a separate screen. */
    .auth-brand__photo {
      position: absolute;
      inset: 0;
      z-index: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center 48%;
      filter: saturate(1.12) contrast(1.05) brightness(1.06);
    }

    /* Legibility layer. The gate puts text over the full height of the image,
       not just its left edge, so this leans darker than the homepage overlay. */
    .auth-brand__scrim {
      position: absolute;
      inset: 0;
      z-index: 1;
      background:
        linear-gradient(100deg, rgba(3, 18, 28, .94) 0%, rgba(7, 41, 55, .86) 45%, rgba(8, 57, 68, .62) 100%),
        linear-gradient(180deg, rgba(6, 13, 20, .5) 0%, rgba(6, 13, 20, .28) 42%, rgba(6, 13, 20, .58) 100%);
    }

    .auth-brand__ambient {
      position: absolute;
      inset: 0;
      z-index: 2;
      opacity: .8;
      background:
        radial-gradient(ellipse 46% 42% at 12% 30%, rgba(232, 160, 32, .13), transparent 72%),
        radial-gradient(ellipse 38% 58% at 88% 74%, rgba(0, 172, 172, .16), transparent 72%);
    }

    /* Everything authored below the layers has to sit above them. */
    .auth-brand > *:not(.auth-brand__photo):not(.auth-brand__scrim):not(.auth-brand__ambient) {
      position: relative;
      z-index: 3;
    }

    .auth-brand__lockup {
      display: flex;
      align-items: center;
      gap: 14px;
      text-decoration: none;
      color: inherit;
    }

    .auth-brand__mark {
      width: 54px;
      height: 54px;
      flex-shrink: 0;
      object-fit: contain;
    }

    .auth-brand__name {
      display: block;
      font-size: 17px;
      font-weight: 700;
      letter-spacing: -.01em;
    }

    .auth-brand__kicker {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 0 0 18px;
      color: #6fe3dc;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
    }

    .auth-brand__kicker::before {
      width: 22px;
      height: 1px;
      background: var(--portal-accent);
      content: '';
    }

    .auth-brand__title {
      margin: 0;
      max-width: 15ch;
      font-family: 'Literata', serif;
      font-size: clamp(30px, 3.4vw, 50px);
      font-weight: 700;
      letter-spacing: -.05em;
      line-height: 1;
    }

    .auth-brand__lead {
      margin: 20px 0 0;
      max-width: 46ch;
      color: rgba(255, 255, 255, .72);
      font-size: clamp(14px, 1.05vw, 16px);
      line-height: 1.6;
    }

    .auth-brand__headline { align-self: center; }

    .auth-brand__facts {
      display: grid;
      gap: 16px;
      padding-top: 24px;
      border-top: 1px solid rgba(255, 255, 255, .18);
    }

    .auth-brand__fact {
      display: grid;
      grid-template-columns: 26px minmax(0, 1fr);
      gap: 14px;
      align-items: start;
    }

    .auth-brand__fact .material-symbols-outlined {
      color: #6fe3dc;
      font-size: 24px;
    }

    .auth-brand__fact h2 {
      margin: 0 0 5px;
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
    }

    .auth-brand__fact p {
      margin: 0;
      color: rgba(255, 255, 255, .68);
      font-size: 13px;
      line-height: 1.55;
    }

    /* ── Right: form pane ─────────────────────────────────────── */

    .auth-pane {
      display: flex;
      flex-direction: column;
      min-height: 0;
      background: var(--portal-paper);
      border-left: 1px solid var(--portal-line);
    }

    .auth-pane__bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-shrink: 0;
      min-height: 60px;
      padding: 16px clamp(24px, 3vw, 56px);
      border-bottom: 1px solid var(--portal-line);
    }

    .auth-pane__back {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      min-height: 32px;
      color: var(--portal-muted);
      font-size: 13px;
      text-decoration: none;
    }

    .auth-pane__back:hover { color: var(--portal-ink); }
    .auth-pane__back .material-symbols-outlined { font-size: 18px; }

    .auth-locale {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .auth-locale a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 36px;
      min-height: 32px;
      padding: 4px 8px;
      border: 1px solid var(--portal-line);
      color: var(--portal-muted);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .06em;
      text-decoration: none;
    }

    .auth-locale a[aria-current='true'] {
      border-color: var(--portal-ink);
      background: var(--portal-ink);
      color: #fff;
    }

    /* The only scroll container on the page. */
    .auth-pane__scroll {
      flex: 1;
      min-height: 0;
      overflow-y: auto;
      padding: clamp(24px, 2.5vw, 40px) clamp(24px, 3vw, 48px);
      scrollbar-color: var(--portal-line) transparent;
      scrollbar-width: thin;
    }

    .auth-pane__inner {
      width: 100%;
      max-width: 460px;
    }

    .auth-head h1 {
      margin: 0;
      font-family: 'Literata', serif;
      font-size: clamp(25px, 2.2vw, 33px);
      font-weight: 700;
      letter-spacing: -.035em;
      line-height: 1.08;
    }

    .auth-head p {
      margin: 12px 0 0;
      color: var(--portal-muted);
      font-size: 14.5px;
      line-height: 1.6;
    }

    .auth-form { margin-top: 28px; }
    .auth-field + .auth-field { margin-top: 18px; }

    .auth-label-row {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 7px;
    }

    .auth-label {
      color: var(--portal-muted);
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
    }

    .auth-forgot {
      color: #078f89;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
    }

    .auth-forgot:hover { text-decoration: underline; }

    .auth-control { position: relative; }

    .auth-control input {
      width: 100%;
      height: 52px;
      padding: 0 46px 0 15px;
      border: 1px solid var(--portal-line);
      border-radius: 0;
      background: #fff;
      color: var(--portal-ink);
      font-family: inherit;
      font-size: 15.5px;
    }

    .auth-control input:focus {
      border-color: var(--portal-accent);
      outline: 2px solid rgba(9, 186, 178, .18);
      outline-offset: 0;
    }

    .auth-control .material-symbols-outlined {
      position: absolute;
      top: 50%;
      right: 15px;
      transform: translateY(-50%);
      color: var(--portal-muted);
      font-size: 19px;
      pointer-events: none;
    }

    .auth-remember {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 18px;
      color: var(--portal-muted);
      font-size: 13.5px;
      cursor: pointer;
    }

    .auth-remember input {
      width: 17px;
      height: 17px;
      accent-color: var(--portal-accent);
    }

    /* Matches the site's primary action: flat, teal, uppercase, square. */
    .auth-submit {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      min-height: 52px;
      margin-top: 22px;
      border: 1px solid #0f766a;
      border-radius: 0;
      background: #0f766a;
      color: #fff;
      font-family: inherit;
      font-size: 12.5px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      cursor: pointer;
      transition: background-color 200ms ease, border-color 200ms ease;
    }

    .auth-submit:hover:not(:disabled) { background: #0b5e55; border-color: #0b5e55; }
    .auth-submit:disabled { opacity: .6; cursor: wait; }
    .auth-submit .material-symbols-outlined { font-size: 19px; }

    .auth-divider {
      display: flex;
      align-items: center;
      gap: 14px;
      margin: 22px 0;
      color: var(--portal-muted);
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: .14em;
      text-transform: uppercase;
    }

    .auth-divider::before,
    .auth-divider::after {
      flex: 1;
      height: 1px;
      background: var(--portal-line);
      content: '';
    }

    .auth-sso {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      min-height: 48px;
      border: 1px solid var(--portal-line);
      border-radius: 0;
      background: var(--portal-wash);
      color: var(--portal-ink);
      font-family: inherit;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 200ms ease, border-color 200ms ease;
    }

    .auth-sso:hover { border-color: var(--portal-accent); background: #fff; }
    .auth-sso .material-symbols-outlined { color: #078f89; font-size: 19px; }

    .auth-message {
      min-height: 20px;
      margin-top: 16px;
      font-size: 13.5px;
      line-height: 1.5;
    }

    .auth-message[data-tone='error'] { color: #b3261e; }
    .auth-message[data-tone='success'] { color: #078f89; }
    .auth-message[data-tone='info'] { color: var(--portal-muted); }

    .auth-notice {
      margin: 20px 0 0;
      padding-top: 16px;
      border-top: 1px solid var(--portal-line);
      color: var(--portal-muted);
      font-size: 12px;
      line-height: 1.6;
    }

    /* ── Quick access ─────────────────────────────────────────── */

    .auth-demo {
      width: 100%;
      max-width: 700px;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--portal-line);
    }

    .auth-demo h2 {
      margin: 0;
      font-family: 'Literata', serif;
      font-size: 19px;
      letter-spacing: -.03em;
    }

    .auth-demo > p {
      margin: 6px 0 0;
      color: var(--portal-muted);
      font-size: 13px;
    }

    .auth-demo__grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 8px;
      margin-top: 16px;
    }

    .auth-demo__grid form { margin: 0; }

    .demo-card {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      width: 100%;
      height: 100%;
      min-height: 84px;
      padding: 12px;
      border: 1px solid var(--portal-line);
      border-radius: 0;
      background: #fff;
      color: var(--portal-ink);
      font-family: inherit;
      text-align: left;
      cursor: pointer;
      transition: border-color 200ms ease, background-color 200ms ease;
    }

    .demo-card:hover:not([disabled]) { border-color: var(--portal-accent); background: var(--portal-wash); }
    .demo-card[disabled] { opacity: .55; cursor: wait; }

    .demo-card-label {
      display: block;
      color: var(--portal-ink);
      font-size: 13.5px;
      font-weight: 700;
      overflow-wrap: anywhere;
    }

    .demo-card-desc {
      display: block;
      margin-top: 3px;
      color: var(--portal-muted);
      font-size: 11.5px;
      line-height: 1.45;
      overflow-wrap: anywhere;
    }

    /* ── Short viewports ──────────────────────────────────────── */

    /* Laptops at 1366×640-ish are common here. Compress the vertical rhythm so
       the whole gate still fits one viewport instead of handing the reader an
       inner scrollbar for the sake of 100px. */
    @media (max-height: 760px) {
      .auth-brand { gap: 18px; padding: 24px clamp(24px, 3vw, 40px); }
      .auth-brand__kicker { margin-bottom: 12px; }
      .auth-brand__lead { margin-top: 12px; }
      .auth-brand__facts { gap: 12px; padding-top: 16px; }
      .auth-pane__bar { padding-block: 12px; }
      .auth-pane__scroll { padding-block: 22px; }
      .auth-head p { margin-top: 8px; font-size: 13.5px; }
      .auth-form { margin-top: 20px; }
      .auth-field + .auth-field { margin-top: 13px; }
      .auth-control input { height: 46px; }
      .auth-remember { margin-top: 13px; }
      .auth-submit { min-height: 46px; margin-top: 16px; }
      .auth-divider { margin: 15px 0; }
      .auth-sso { min-height: 43px; }
      .auth-message { margin-top: 11px; }
      .auth-notice { margin-top: 14px; padding-top: 12px; }
      .demo-card { min-height: 76px; padding: 10px; }
    }

    /* On desktop and tablet, the form and all nine demo roles share the
       available width instead of forcing the role grid below the fold. */
    @media (min-width: 768px) {
      .auth-pane__scroll {
        display: grid;
        grid-template-columns: minmax(300px, .82fr) minmax(0, 1.18fr);
        align-content: center;
        align-items: center;
        gap: clamp(24px, 2.5vw, 40px);
      }

      .auth-demo {
        align-self: center;
        max-width: none;
        margin-top: 0;
        padding: 0 0 0 clamp(24px, 2.5vw, 40px);
        border-top: 0;
        border-left: 1px solid var(--portal-line);
      }
    }

    @media (min-width: 1181px) {
      .auth-demo__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .auth-demo__grid form:last-child:nth-child(odd) { grid-column: 1 / -1; }
    }

    /* ── Narrow viewports ─────────────────────────────────────── */

    @media (max-width: 1180px) {
      .auth-screen { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr); }

      /* Collapsed to a banner: on one short viewport the form has to win the
         available height, so the marketing copy and the fact list step aside. */
      .auth-brand {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 14px;
        padding: 14px 20px;
      }

      .auth-brand__mark { width: 38px; height: 38px; }
      .auth-brand__headline,
      .auth-brand__facts { display: none; }

      .auth-pane { border-left: 0; }
      .auth-demo { max-width: none; }
    }

    @media (max-width: 767px) {
      .auth-pane__bar { padding: 12px 24px; }
      .auth-pane__scroll { padding: 24px; }
      .auth-pane__inner { max-width: none; }
      .auth-demo { max-width: none; }
      .auth-demo__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .auth-demo__grid form:last-child:nth-child(odd) { grid-column: 1 / -1; }
      .demo-card { min-height: 104px; }
    }

    @media (max-width: 420px) {
      .auth-brand { padding-inline: 20px; }
      .auth-brand__name { font-size: 16px; }
      .auth-pane__bar { padding-inline: 24px; }
      .auth-locale a { min-width: 36px; padding-inline: 6px; }
      .auth-demo__grid { gap: 8px; }
      .demo-card { padding: 10px 8px; }
      .demo-card-icon { font-size: 18px; }
      .demo-card-label { font-size: 12.5px; }
      .demo-card-desc { font-size: 10.5px; }
    }

    @media (prefers-reduced-motion: reduce) {
      .auth-submit,
      .auth-sso,
      .demo-card { transition: none; }
    }
  </style>
</head>
<body>
  <div class="auth-screen">
    <section class="auth-brand">
      <img class="auth-brand__photo" src="/images/news/campus-library.jpg" alt="" fetchpriority="high" decoding="async">
      <div class="auth-brand__scrim" aria-hidden="true"></div>
      <div class="auth-brand__ambient" aria-hidden="true"></div>

      <x-library-brand variant="auth" :href="$routeWithLang('/')" class="auth-brand__lockup" />

      <div class="auth-brand__headline">
        <p class="auth-brand__kicker">{{ $pageCopy['legacyHero'] ?? 'Вход в библиотечную систему' }}</p>
        <h1 class="auth-brand__title">{{ $pageCopy['displayHeadline'] ?? 'Preserving Knowledge, Empowering Research.' }}</h1>
        <p class="auth-brand__lead">{{ $pageCopy['lead'] ?? '' }}</p>
      </div>

      <div class="auth-brand__facts">
        <div class="auth-brand__fact">
          <span class="material-symbols-outlined" aria-hidden="true">verified_user</span>
          <div>
            <h2>{{ $pageCopy['accessHeading'] ?? 'Secure Access' }}</h2>
            <p>{{ $pageCopy['accessValue'] ?? '' }}</p>
          </div>
        </div>
        <div class="auth-brand__fact">
          <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
          <div>
            <h2>{{ $pageCopy['supportHeading'] ?? 'Support' }}</h2>
            <p>{{ $pageCopy['supportValue'] ?? '' }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="auth-pane">
      <div class="auth-pane__bar">
        <a class="auth-pane__back" href="{{ $routeWithLang('/') }}">
          <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
          {{ $pageCopy['footerLinks'][3]['label'] ?? 'На главную' }}
        </a>
        <x-locale-switcher variant="light" />
      </div>

      <div class="auth-pane__scroll">
        <div class="auth-pane__inner">
          <div class="auth-head">
            <h1>{{ $pageCopy['formTitle'] ?? 'Sign in to the Library' }}</h1>
            <p>{{ $pageCopy['formSubtitle'] ?? '' }}</p>
          </div>

          <form id="login-form" class="auth-form" method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <input type="hidden" name="device_name" value="web">
            @if($authLang !== 'ru')
              <input type="hidden" name="lang" value="{{ $authLang }}">
            @endif

            <div class="auth-field">
              <div class="auth-label-row">
                <label class="auth-label" for="login">{{ $pageCopy['loginLabel'] ?? 'Institutional ID' }}</label>
              </div>
              <div class="auth-control">
                <input id="login" name="login" type="text" value="{{ old('login', old('email')) }}"
                       placeholder="{{ $pageCopy['loginPlaceholder'] ?? '' }}"
                       autocomplete="username" required>
                <span class="material-symbols-outlined" aria-hidden="true">badge</span>
              </div>
            </div>

            <div class="auth-field">
              <div class="auth-label-row">
                <label class="auth-label" for="password">{{ $pageCopy['passwordLabel'] ?? 'Password' }}</label>
                <a class="auth-forgot" href="{{ $routeWithLang('/contacts') }}">{{ $pageCopy['forgot'] ?? 'Forgot?' }}</a>
              </div>
              <div class="auth-control">
                <input id="password" name="password" type="password"
                       placeholder="{{ $pageCopy['passwordPlaceholder'] ?? '' }}"
                       autocomplete="current-password" required>
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>
              </div>
            </div>

            <label class="auth-remember">
              <input name="remember" type="checkbox">
              <span>{{ $pageCopy['keepSigned'] ?? 'Keep me signed in for 30 days' }}</span>
            </label>

            <button id="submit-btn" class="auth-submit" type="submit">
              <span data-submit-label>{{ $pageCopy['submit'] ?? 'Log in' }}</span>
              <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </button>

            <p class="auth-divider">{{ $pageCopy['divider'] ?? 'or access via' }}</p>

            <button id="sso-access-btn" class="auth-sso" type="button">
              <span class="material-symbols-outlined" aria-hidden="true">language</span>
              <span>{{ $pageCopy['sso'] ?? 'Institutional SSO' }}</span>
            </button>

            <div id="form-message" class="auth-message"
                 data-tone="{{ $errors->any() ? 'error' : 'info' }}"
                 aria-live="polite">{{ $errors->first('login') }}</div>
          </form>

          <p class="auth-notice">{{ $pageCopy['securityNotice'] ?? '' }}</p>
        </div>

        {{--
          RBAC quick sign-in. Each card is a real form POSTing to
          /login/demo/{role} with a CSRF token, so the request goes through the
          same session and authorization path as a normal login — no fetch(),
          no client-side passwords. Rendered only when APP_DEMO_LOGIN_ENABLED
          is on; the route itself 404s otherwise.
        --}}
        @if(!empty($rbacDemoEnabled) && !empty($rbacDemoIdentities))
          <section class="auth-demo" id="demo-login-block">
            <h2 class="demo-block-title">{{ $pageCopy['demoTitle'] ?? 'Быстрый вход' }}</h2>
            <p class="demo-block-subtitle">{{ $pageCopy['demoSub'] ?? '' }}</p>
            <div class="auth-demo__grid">
              @foreach($rbacDemoIdentities as $slug => $identity)
                <form method="POST" action="{{ url('/login/demo/' . $slug) }}">
                  @csrf
                  <button type="submit" class="demo-card" data-demo-slug="{{ $slug }}">
                    <span class="material-symbols-outlined demo-card-icon" aria-hidden="true">{{ $demoIconNames[$slug] ?? 'person' }}</span>
                    <span class="demo-card-copy">
                      <span class="demo-card-label">{{ $identity['label'] }}</span>
                      <span class="demo-card-desc">{{ $identity['description'] ?? '' }}</span>
                    </span>
                  </button>
                </form>
              @endforeach
            </div>
          </section>
        @endif
      </div>
    </section>
  </div>

  <script>
    const AUTH_USER_KEY = 'library.auth.user';
    const AUTH_CONFIG = @json($authConfig);
    const AUTH_I18N_MAP = @json($authI18n);
    const AUTH_I18N = AUTH_I18N_MAP[AUTH_CONFIG.lang] || AUTH_I18N_MAP.ru;

    function withLang(path) {
      const url = new URL(path, window.location.origin);
      if (AUTH_CONFIG.lang !== 'kk' && !url.searchParams.has('lang')) {
        url.searchParams.set('lang', AUTH_CONFIG.lang);
      }
      return `${url.pathname}${url.search}`;
    }

    function safeLocalRedirect(path) {
      if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//') || path.includes('\\')) {
        return withLang('/dashboard');
      }

      const url = new URL(path, window.location.origin);
      if (url.origin !== window.location.origin) {
        return withLang('/dashboard');
      }

      return `${url.pathname}${url.search}${url.hash}`;
    }

    function getCsrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function showMessage(text, tone) {
      const el = document.getElementById('form-message');
      if (!el) return;
      el.textContent = text;
      el.dataset.tone = tone || 'info';
    }

    function clearMessage() {
      showMessage('', 'info');
    }

    const DEMO_CREDENTIALS = {
      librarian: { login: 'demo_librarian', password: 'DemoLibrarian2026!' },
      admin: { login: 'demo_admin', password: 'DemoAdmin2026!' },
      teacher: { login: 'demo_teacher', password: 'DemoTeacher2026!' },
      student: { login: 'demo_student', password: 'DemoStudent2026!' },
    };

    function resolveDemoSlug(loginValue, passwordValue) {
      const normalized = String(loginValue || '').trim().toLowerCase();
      return Object.entries(DEMO_CREDENTIALS).find(([, identity]) => {
        return identity.password === passwordValue && identity.login.toLowerCase() === normalized;
      })?.[0] || null;
    }

    async function submitLogin(loginValue, passwordValue) {
      const demoSlug = resolveDemoSlug(loginValue, passwordValue);
      if (demoSlug) {
        await demoLogin(demoSlug, null, true);
        return;
      }

      const payload = { password: passwordValue, device_name: 'web' };

      if (loginValue.includes('@')) {
        payload.email = loginValue;
      } else {
        payload.login = loginValue;
      }

      const response = await fetch('/api/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data?.message || AUTH_I18N.authError);
      }

      if (data?.user) {
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(data.user));
      }

      return data;
    }

    document.getElementById('login-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearMessage();

      const submitBtn = document.getElementById('submit-btn');
      const submitLabel = submitBtn?.querySelector('[data-submit-label]');
      const loginValue = (document.getElementById('login')?.value || '').trim();
      const passwordValue = (document.getElementById('password')?.value || '').trim();

      if (!loginValue || !passwordValue) {
        showMessage(AUTH_I18N.fillFields, 'error');
        return;
      }

      submitBtn.disabled = true;
      // Swap only the label so the button keeps its icon and layout.
      if (submitLabel) submitLabel.textContent = AUTH_I18N.submitting;

      try {
        const result = await submitLogin(loginValue, passwordValue);
        showMessage(AUTH_I18N.success, 'success');
        const params = new URLSearchParams(window.location.search);
        const redirectTo = params.get('redirect') || result?.landing || AUTH_CONFIG.redirectTarget;
        window.setTimeout(() => {
          window.location.href = safeLocalRedirect(redirectTo);
        }, 300);
      } catch (error) {
        showMessage(error?.message || AUTH_I18N.submitError, 'error');
      } finally {
        submitBtn.disabled = false;
        if (submitLabel) submitLabel.textContent = AUTH_CONFIG.submitLabel;
      }
    });

    document.getElementById('sso-access-btn')?.addEventListener('click', () => {
      showMessage(AUTH_I18N.ssoPending, 'info');
    });

    async function demoLogin(slug, btn, keepDisabled = false) {
      clearMessage();
      const allCards = document.querySelectorAll('.demo-card');
      allCards.forEach(card => card.disabled = true);

      try {
        const response = await fetch('/api/demo-auth/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
          },
          body: JSON.stringify({ role: slug }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(data?.message || AUTH_I18N.demoError);
        }

        if (data?.user) {
          localStorage.setItem(AUTH_USER_KEY, JSON.stringify(data.user));
        }

        showMessage(AUTH_I18N.demoSuccess, 'success');
        const params = new URLSearchParams(window.location.search);
        const redirectTo = params.get('redirect') || data?.landing || AUTH_CONFIG.redirectTarget;
        window.setTimeout(() => {
          window.location.href = safeLocalRedirect(redirectTo);
        }, 300);
      } catch (error) {
        showMessage(error?.message || AUTH_I18N.demoError, 'error');
        allCards.forEach(card => card.disabled = false);
      } finally {
        if (!keepDisabled) {
          allCards.forEach(card => card.disabled = false);
        }
      }
    }
  </script>
</body>
</html>
    .demo-card-icon {
      flex: 0 0 auto;
      margin-top: 1px;
      color: #078f89;
      font-size: 20px;
    }

    .demo-card-copy {
      min-width: 0;
    }
