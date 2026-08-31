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
  @vite('resources/css/app.css')
  <link rel="stylesheet" href="/fonts/fonts.css">
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
<body class="{{ trim('site-shell ' . $__env->yieldContent('body_class')) }}" data-library-tailwind-theme="public" data-library-tailwind-radius="compact">
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
