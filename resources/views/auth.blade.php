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
      .auth-submit { min-height: 46px; margin-top: 16px; }
      .auth-message { margin-top: 11px; }
      .auth-notice { margin-top: 14px; padding-top: 12px; }
    }

    /* On desktop and tablet, the form and all nine demo roles share the
       available width instead of forcing the role grid below the fold. */
    @media (min-width: 768px) {
      .auth-pane__scroll {
        display: flex;
        align-items: center;
        justify-content: center;
      }
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
    }

    @media (max-width: 767px) {
      .auth-pane__bar { padding: 12px 24px; }
      .auth-pane__scroll { padding: 24px; }
      .auth-pane__inner { max-width: none; }
    }

    @media (max-width: 420px) {
      .auth-brand { padding-inline: 20px; }
      .auth-brand__name { font-size: 16px; }
      .auth-pane__bar { padding-inline: 24px; }
      .auth-locale a { min-width: 36px; padding-inline: 6px; }
    }

    @media (prefers-reduced-motion: reduce) {
      .auth-submit { transition: none; }
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
        <p class="auth-brand__title">{{ $pageCopy['displayHeadline'] ?? 'Preserving Knowledge, Empowering Research.' }}</p>
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

          <form id="login-form" class="auth-form" method="POST" action="{{ route('login') }}">
            @csrf
            <input type="hidden" name="device_name" value="web">
            <input type="hidden" name="redirect" value="{{ $redirectTarget }}">
            @if($authLang !== 'kk')
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

            <button id="submit-btn" class="auth-submit" type="submit">
              <span data-submit-label>{{ $pageCopy['submit'] ?? 'Log in' }}</span>
              <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
            </button>

            <div id="form-message" class="auth-message"
                 data-tone="{{ $errors->any() ? 'error' : 'info' }}"
                 aria-live="polite">{{ $errors->first('login') }}</div>
          </form>

          <p class="auth-notice">{{ $pageCopy['securityNotice'] ?? '' }}</p>
        </div>

      </div>
    </section>
  </div>

</body>
</html>
