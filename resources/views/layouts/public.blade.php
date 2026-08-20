@php
  $pageLang = $pageLang ?? app()->getLocale();
  $pageLang = in_array($pageLang, ['kk', 'ru', 'en'], true) ? $pageLang : 'kk';
  $loginRedirectUrl = '/login';

  $defaultMetaDescriptions = [
      'ru' => 'Научная библиотека Казахского университета технологии и бизнеса имени К. Кулажанова: электронный каталог, ресурсы, научный репозиторий, новости и услуги.',
      'kk' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университетінің ғылыми кітапханасы: электрондық каталог, ресурстар, ғылыми репозиторий, жаңалықтар және қызметтер.',
      'en' => 'Scientific Library of the Kazakh University of Technology and Business named after K. Kulazhanov: electronic catalogue, resources, scholarly repository, news, and services.',
  ];
  $pageDescription = trim(strip_tags($__env->yieldContent('meta_description')));
  if ($pageDescription === '') {
      $pageDescription = $defaultMetaDescriptions[$pageLang];
  }

  // Locale is part of the public page identity. Keep meaningful search and
  // pagination parameters, while dropping tracking-only values and arrays.
  $canonicalQuery = [];
  foreach (request()->query() as $key => $value) {
      $normalisedKey = mb_strtolower((string) $key);
      if ($normalisedKey === 'lang'
          || str_starts_with($normalisedKey, 'utm_')
          || in_array($normalisedKey, ['gclid', 'fbclid', 'yclid'], true)
          || ! is_scalar($value)
          || trim((string) $value) === '') {
          continue;
      }
      $canonicalQuery[(string) $key] = (string) $value;
  }
  ksort($canonicalQuery);
  $canonicalPath = request()->path() === '/' ? '/' : '/'.ltrim(request()->path(), '/');
  $urlForLocale = static function (string $locale) use ($canonicalPath, $canonicalQuery): string {
      $query = $canonicalQuery;
      if ($locale !== 'kk') {
          $query['lang'] = $locale;
      }

      return url($canonicalPath).($query === [] ? '' : '?'.http_build_query($query));
  };
  $canonicalUrl = $urlForLocale($pageLang);
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLang }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', __('brand.library.name').' — '.__('brand.university.full'))</title>
  <meta name="description" content="{{ $pageDescription }}" />
  <link rel="canonical" href="{{ $canonicalUrl }}" />
  @foreach(['kk', 'ru', 'en'] as $alternateLocale)
    <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ $urlForLocale($alternateLocale) }}" />
  @endforeach
  <link rel="alternate" hreflang="x-default" href="{{ $urlForLocale('kk') }}" />
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
            'on-tertiary': '#ffffff',
            'surface-variant': '#e1e3e4',
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
            headline: ['Literata', 'serif'],
            body: ['Google Sans', 'sans-serif'],
            label: ['Google Sans', 'sans-serif']
          }
        }
      }
    }
  </script>
  @php
    // These public styles are edited outside Vite, so their URL must change
    // whenever their contents change. A stable URL allowed browsers to keep a
    // pre-redesign copy: the shell stayed styled while page components did not.
    $publicCssUrl = static function (string $path): string {
        $relativePath = ltrim($path, '/');
        $absolutePath = public_path($relativePath);
        $version = is_file($absolutePath)
            ? substr((string) hash_file('sha256', $absolutePath), 0, 16)
            : 'missing';

        return '/'.$relativePath.'?v='.$version;
    };
  @endphp
  <link rel="stylesheet" href="{{ $publicCssUrl('css/shell.css') }}">
  <link rel="stylesheet" href="{{ $publicCssUrl('css/home-sections.css') }}">
  <style>
    body { font-family: 'Google Sans', sans-serif; }
    .serif-italic { font-family: 'Literata', serif; font-style: italic; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  </style>
  @yield('head')
  <link rel="stylesheet" href="{{ $publicCssUrl('css/public-unified.css') }}">
  <link rel="stylesheet" href="{{ $publicCssUrl('css/public-pages-v2.css') }}">
</head>
<body class="{{ trim('site-shell ' . $__env->yieldContent('body_class')) }}">
  <a href="#main-content" class="skip-link">{{ __('ui.skip_to_main') }}</a>

  @include('partials.navbar', ['activePage' => $activePage ?? ''])

  <main id="main-content" class="page-main" tabindex="-1">
    @yield('content')
  </main>

  @include('partials.footer')

  @if(session('library.user'))
  <script>
    document.getElementById('shared-logout-btn')?.addEventListener('click', async () => {
      try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/api/v1/logout', {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
      } catch (_) {}
      localStorage.removeItem('library.auth.user');
      window.location.href = @json($loginRedirectUrl);
    });
  </script>
  @endif

  @yield('scripts')
</body>
</html>
