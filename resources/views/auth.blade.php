@php
  $authLang = $lang ?? app()->getLocale();
  $authLang = in_array($authLang, ['kk', 'ru', 'en'], true) ? $authLang : 'ru';
  $pageCopy = is_array($copy ?? null) ? ($copy[$authLang] ?? $copy['ru'] ?? []) : [];
  $footerLinks = $pageCopy['footerLinks'] ?? [];
  $redirectTarget = request()->query('redirect', $authLang === 'ru' ? '/dashboard' : ('/dashboard?lang=' . $authLang));
@endphp
<!DOCTYPE html>
<html class="light" lang="{{ $authLang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $pageCopy['title'] ?? 'Sign in — KazUTB Smart Library' }}</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,200..800;1,6..72,200..800&family=Manrope:wght@200..800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'secondary-fixed-dim': '#76d6d5',
            'on-tertiary-container': '#76889d',
            'on-secondary-container': '#006e6e',
            'error': '#ba1a1a',
            'surface-tint': '#476083',
            'on-primary-fixed': '#001c3a',
            'surface-container-low': '#f3f4f5',
            'on-surface': '#191c1d',
            'on-error': '#ffffff',
            'secondary-container': '#90efef',
            'on-secondary': '#ffffff',
            'on-surface-variant': '#43474e',
            'inverse-on-surface': '#f0f1f2',
            'surface-container': '#edeeef',
            'surface-bright': '#f8f9fa',
            'on-primary-fixed-variant': '#2f486a',
            'surface-container-highest': '#e1e3e4',
            'surface': '#f8f9fa',
            'tertiary': '#000610',
            'primary-fixed-dim': '#afc8f0',
            'secondary-fixed': '#93f2f2',
            'on-secondary-fixed-variant': '#004f4f',
            'on-background': '#191c1d',
            'surface-variant': '#e1e3e4',
            'on-tertiary': '#ffffff',
            'surface-container-lowest': '#ffffff',
            'on-error-container': '#93000a',
            'error-container': '#ffdad6',
            'tertiary-fixed': '#d1e4fb',
            'inverse-surface': '#2e3132',
            'surface-container-high': '#e7e8e9',
            'primary-container': '#001f3f',
            'on-tertiary-fixed-variant': '#36485b',
            'primary': '#000613',
            'on-secondary-fixed': '#002020',
            'primary-fixed': '#d4e3ff',
            'outline-variant': '#c4c6cf',
            'on-primary-container': '#8ea5c6',
            'inverse-primary': '#afc8f0',
            'on-primary': '#ffffff',
            'background': '#f8f9fa',
            'outline': '#74777f',
            'tertiary-container': '#0d2031',
            'surface-dim': '#d9dadb',
            'on-tertiary-fixed': '#091d2e',
            'secondary': '#006a6a',
            'tertiary-fixed-dim': '#b5c8df'
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
    h1, h2, h3, .font-serif { font-family: 'Newsreader', serif; }
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
      display: inline-block;
      vertical-align: middle;
    }
    .auth-sidebar::after {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at top left, rgba(0, 106, 106, 0.16), transparent 30%),
        linear-gradient(135deg, rgba(0, 6, 19, 0.95), rgba(0, 31, 63, 0.88));
      pointer-events: none;
    }
    .auth-input {
      box-shadow: inset 0 -1px 0 rgba(116, 119, 127, 0.18);
    }
    .auth-input:focus {
      box-shadow: inset 0 -2px 0 rgba(0, 106, 106, 0.55);
    }
    .demo-card[disabled] {
      opacity: 0.65;
      cursor: wait;
    }
    .quick-fill-card {
      border: 1px solid rgba(196, 198, 207, 0.35);
    }
  </style>
</head>
<body class="bg-surface text-on-surface overflow-x-hidden">
  <main class="min-h-screen flex flex-col md:flex-row">
    <section class="auth-sidebar hidden md:flex md:w-5/12 lg:w-[48%] bg-primary relative overflow-hidden flex-col justify-between px-10 py-12 lg:px-12 lg:py-14 text-on-primary">
      <div class="absolute inset-0 opacity-20">
        <img alt="Atmospheric library interior" class="w-full h-full object-cover" src="{{ asset('images/news/default-library.jpg') }}">
      </div>

      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-12">
          <span class="material-symbols-outlined text-secondary-fixed text-3xl" data-icon="account_balance">account_balance</span>
          <span class="text-xl font-headline italic tracking-tight">{{ $pageCopy['brand'] ?? 'KazUTB Smart Library' }}</span>
        </div>

        <div class="max-w-md">
          <h2 class="text-5xl lg:text-[3.55rem] font-headline mb-8 leading-[0.94] tracking-tight">{{ $pageCopy['displayHeadline'] ?? 'Preserving Knowledge, Empowering Research.' }}</h2>
          <p class="text-on-primary-container text-lg font-body leading-relaxed mb-12">{{ $pageCopy['lead'] ?? '' }}</p>
        </div>
      </div>

      <div class="relative z-10 space-y-8 max-w-md">
        <div class="flex items-start gap-4">
          <span class="material-symbols-outlined text-secondary mt-1" data-icon="verified_user">verified_user</span>
          <div>
            <h4 class="font-bold text-sm uppercase tracking-widest text-secondary-fixed mb-2">{{ $pageCopy['accessHeading'] ?? 'Secure Access' }}</h4>
            <p class="text-on-primary-container text-sm leading-snug">{{ $pageCopy['accessValue'] ?? '' }}</p>
          </div>
        </div>

        <div class="flex items-start gap-4">
          <span class="material-symbols-outlined text-secondary mt-1" data-icon="help">help</span>
          <div>
            <h4 class="font-bold text-sm uppercase tracking-widest text-secondary-fixed mb-2">{{ $pageCopy['supportHeading'] ?? 'Support' }}</h4>
            <p class="text-on-primary-container text-sm leading-snug">{{ $pageCopy['supportValue'] ?? '' }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="flex-1 flex flex-col justify-center bg-surface-container-lowest p-8 md:p-16 xl:p-24 relative">
      <div class="md:hidden mb-12 flex items-center gap-3">
        <span class="material-symbols-outlined text-secondary text-2xl" data-icon="account_balance">account_balance</span>
        <span class="text-lg font-headline italic text-primary">{{ $pageCopy['brand'] ?? 'KazUTB Smart Library' }}</span>
      </div>

      <div class="max-w-md w-full mx-auto">
        <header class="mb-12">
          <span class="sr-only">{{ $pageCopy['legacyHero'] ?? 'Вход в библиотечную систему' }}</span>
          <h1 class="text-4xl md:text-5xl font-headline text-primary mb-4 tracking-tight leading-[0.95]">{{ $pageCopy['formTitle'] ?? 'Sign in to the Library' }}</h1>
          <p class="text-on-surface-variant font-body leading-relaxed">{{ $pageCopy['formSubtitle'] ?? 'Sign in using your institutional credentials to explore our scientific archives.' }}</p>
        </header>

        <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-8" novalidate>
          @csrf
          <input type="hidden" name="device_name" value="web">
          @if($authLang !== 'ru')
            <input type="hidden" name="lang" value="{{ $authLang }}">
          @endif

          <div class="space-y-2">
            <label class="block text-xs font-bold tracking-widest uppercase text-outline" for="login">{{ $pageCopy['loginLabel'] ?? 'Institutional ID' }}</label>
            <div class="relative group">
              <input class="auth-input w-full bg-surface-container-highest border-0 py-4 px-4 pr-12 focus:ring-0 transition-all font-body text-primary placeholder:text-on-surface-variant/40" id="login" name="login" value="{{ old('login', old('email')) }}" placeholder="{{ $pageCopy['loginPlaceholder'] ?? '' }}" autocomplete="username" required type="text">
              <div class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center px-4">
                <span class="material-symbols-outlined text-on-surface-variant/50 text-xl" data-icon="badge">badge</span>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <div class="flex justify-between items-end">
              <label class="block text-xs font-bold tracking-widest uppercase text-outline" for="password">{{ $pageCopy['passwordLabel'] ?? 'Password' }}</label>
              <a class="text-xs font-bold text-secondary hover:underline transition-all" href="{{ $authLang === 'ru' ? '/contacts' : '/contacts?lang=' . $authLang }}">{{ $pageCopy['forgot'] ?? 'Forgot?' }}</a>
            </div>
            <div class="relative group">
              <input class="auth-input w-full bg-surface-container-highest border-0 py-4 px-4 pr-12 focus:ring-0 transition-all font-body text-primary placeholder:text-on-surface-variant/40" id="password" name="password" placeholder="{{ $pageCopy['passwordPlaceholder'] ?? '' }}" autocomplete="current-password" required type="password">
              <div class="absolute right-0 top-1/2 -translate-y-1/2 flex items-center px-4">
                <span class="material-symbols-outlined text-on-surface-variant/50 text-xl" data-icon="lock">lock</span>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between py-2">
            <label class="flex items-center gap-3 cursor-pointer group">
              <input class="h-5 w-5 rounded border-outline-variant text-secondary focus:ring-secondary/20" name="remember" type="checkbox">
              <span class="text-sm text-on-surface-variant group-hover:text-primary transition-colors">{{ $pageCopy['keepSigned'] ?? 'Keep me signed in for 30 days' }}</span>
            </label>
          </div>

          <button id="submit-btn" class="w-full py-5 bg-gradient-to-r from-primary to-primary-container text-on-primary font-bold tracking-widest uppercase text-sm rounded-lg hover:shadow-xl hover:shadow-primary/10 active:opacity-80 transition-all duration-300 flex justify-center items-center gap-2 group" type="submit">
            {{ $pageCopy['submit'] ?? 'Log in' }}
            <span class="material-symbols-outlined text-xl group-hover:translate-x-1 transition-transform" data-icon="arrow_forward">arrow_forward</span>
          </button>

          <div class="relative py-4">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-outline-variant/10"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-widest">
              <span class="bg-surface-container-lowest px-4 text-outline">{{ $pageCopy['divider'] ?? 'or access via' }}</span>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <button id="sso-access-btn" class="w-full py-4 px-6 bg-surface-container border border-outline-variant/20 rounded-lg flex items-center justify-center gap-3 hover:bg-surface-container-high transition-colors font-body text-on-surface group" type="button">
              <span class="material-symbols-outlined text-secondary" data-icon="language">language</span>
              <span class="text-sm font-semibold">{{ $pageCopy['sso'] ?? 'Institutional SSO' }}</span>
            </button>
          </div>

          <div id="form-message" class="min-h-[20px] text-sm {{ $errors->any() ? 'text-error' : 'text-on-surface-variant' }}" aria-live="polite">{{ $errors->first('login') }}</div>
        </form>

        <footer class="mt-16 text-center">
          <p class="text-xs text-outline leading-relaxed max-w-xs mx-auto">{{ $pageCopy['securityNotice'] ?? '' }}</p>
        </footer>

        @if(!empty($demoEnabled) && !empty($demoIdentities))
          <div class="mt-8 pt-6 border-t border-outline-variant/10" id="demo-login-block">
            <p class="demo-block-title text-sm font-semibold text-primary mb-1">{{ $pageCopy['demoTitle'] ?? 'Быстрый вход' }}</p>
            <p class="demo-block-subtitle text-sm text-on-surface-variant mb-4">{{ $pageCopy['demoSub'] ?? '' }}</p>
            <div class="grid grid-cols-1 gap-3">
              @foreach($demoIdentities as $identity)
                <button type="button" class="demo-card w-full border border-outline-variant/20 rounded-lg bg-surface-container px-4 py-3 text-left hover:bg-surface-container-high transition-colors" data-demo-slug="{{ $identity['slug'] }}" onclick="demoLogin('{{ $identity['slug'] }}', this)">
                  <span class="demo-card-label block font-semibold text-primary">{{ $identity['label'] }}</span>
                  <span class="demo-card-desc block mt-1 text-xs text-on-surface-variant">{{ $identity['description'] ?? '' }}</span>
                </button>
              @endforeach
            </div>

          </div>
        @endif
      </div>
    </section>
  </main>

  <footer class="bg-slate-50 border-t border-slate-200/20 flex flex-col md:flex-row justify-between items-center px-8 md:px-12 py-8 w-full gap-6">
    <div class="flex items-center">
      <span class="text-slate-500 font-sans text-sm tracking-wide">{{ $pageCopy['footerLegal'] ?? ('© ' . date('Y') . ' KazUTB Smart Library. All rights reserved.') }}</span>
    </div>
    <div class="flex flex-wrap justify-center gap-6 md:gap-8">
      @foreach($footerLinks as $link)
        <a class="text-slate-500 font-sans text-sm tracking-wide hover:text-slate-900 transition-all" href="{{ $authLang === 'ru' ? $link['href'] : $link['href'] . (str_contains($link['href'], '?') ? '&' : '?') . 'lang=' . $authLang }}">{{ $link['label'] }}</a>
      @endforeach
    </div>
  </footer>

  <script>
    const AUTH_USER_KEY = 'library.auth.user';
    const AUTH_LANG = @json($authLang);
    const AUTH_I18N_MAP = {!! json_encode([
      'ru' => [
        'authError' => 'Ошибка авторизации',
        'fillFields' => 'Заполните логин/email и пароль.',
        'submitting' => 'Входим...',
        'success' => 'Вход выполнен успешно. Перенаправление...',
        'submitError' => 'Не удалось выполнить вход',
        'submitDefault' => 'Log in',
        'demoSuccess' => 'Быстрый вход выполнен. Перенаправление...',
        'demoError' => 'Ошибка быстрого входа',
        'ssoPending' => 'Institutional SSO will be connected through the KazUTB access channel.',
      ],
      'kk' => [
        'authError' => 'Кіру қатесі',
        'fillFields' => 'Логин/email мен құпиясөзді толтырыңыз.',
        'submitting' => 'Кіріп жатырмыз...',
        'success' => 'Кіру сәтті өтті. Қайта бағытталуда...',
        'submitError' => 'Кіру мүмкін болмады',
        'submitDefault' => 'Log in',
        'demoSuccess' => 'Жедел кіру орындалды. Қайта бағытталуда...',
        'demoError' => 'Жедел кіру қатесі',
        'ssoPending' => 'Institutional SSO will be connected through the KazUTB access channel.',
      ],
      'en' => [
        'authError' => 'Authentication failed',
        'fillFields' => 'Enter both login/email and password.',
        'submitting' => 'Signing in...',
        'success' => 'Sign-in successful. Redirecting...',
        'submitError' => 'Unable to sign in',
        'submitDefault' => 'Log in',
        'demoSuccess' => 'Quick sign-in completed. Redirecting...',
        'demoError' => 'Quick sign-in failed',
        'ssoPending' => 'Institutional SSO will be connected through the KazUTB access channel.',
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
    const AUTH_I18N = AUTH_I18N_MAP[AUTH_LANG] || AUTH_I18N_MAP.ru;

    function withLang(path) {
      const url = new URL(path, window.location.origin);
      if (AUTH_LANG !== 'ru' && !url.searchParams.has('lang')) {
        url.searchParams.set('lang', AUTH_LANG);
      }
      return `${url.pathname}${url.search}`;
    }

    function getCsrfToken() {
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function showMessage(text, type) {
      const el = document.getElementById('form-message');
      if (!el) return;
      el.textContent = text;
      el.className = `min-h-[20px] text-sm ${type === 'error' ? 'text-error' : (type === 'success' ? 'text-secondary' : 'text-on-surface-variant')}`;
    }

    function clearMessage() {
      const el = document.getElementById('form-message');
      if (!el) return;
      el.textContent = '';
      el.className = 'min-h-[20px] text-sm text-on-surface-variant';
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

    function fillDemoCredentials(slug, loginValue, passwordValue) {
      const loginInput = document.getElementById('login');
      const passwordInput = document.getElementById('password');
      if (loginInput) loginInput.value = loginValue || '';
      if (passwordInput) passwordInput.value = passwordValue || '';
      showMessage(`Demo ${slug} credentials filled.`, 'info');
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
    }

    document.getElementById('login-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearMessage();

      const submitBtn = document.getElementById('submit-btn');
      const loginValue = (document.getElementById('login')?.value || '').trim();
      const passwordValue = (document.getElementById('password')?.value || '').trim();

      if (!loginValue || !passwordValue) {
        showMessage(AUTH_I18N.fillFields, 'error');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span>${AUTH_I18N.submitting}</span>`;

      try {
        await submitLogin(loginValue, passwordValue);
        showMessage(AUTH_I18N.success, 'success');
        const params = new URLSearchParams(window.location.search);
        const redirectTo = params.get('redirect') || @json($redirectTarget);
        window.setTimeout(() => {
          window.location.href = redirectTo.startsWith('/') ? redirectTo : withLang('/dashboard');
        }, 300);
      } catch (error) {
        showMessage(error?.message || AUTH_I18N.submitError, 'error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `{{ $pageCopy['submit'] ?? 'Log in' }}<span class="material-symbols-outlined text-xl group-hover:translate-x-1 transition-transform" data-icon="arrow_forward">arrow_forward</span>`;
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
        const redirectTo = params.get('redirect') || @json($redirectTarget);
        window.setTimeout(() => {
          window.location.href = redirectTo.startsWith('/') ? redirectTo : withLang('/dashboard');
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
