@php
  $pageLang = $pageLang ?? app()->getLocale();
  $pageLang = in_array($pageLang, ['kk', 'ru', 'en'], true) ? $pageLang : 'ru';
  $loginRedirectUrl = $pageLang === 'ru' ? '/login' : ('/login?lang=' . $pageLang);
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLang }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'KazUTB Smart Library')</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
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
            headline: ['Newsreader', 'serif'],
            body: ['Manrope', 'sans-serif'],
            label: ['Manrope', 'sans-serif']
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="/css/shell.css">
  <style>
    body { font-family: 'Manrope', sans-serif; }
    .serif-italic { font-family: 'Newsreader', serif; font-style: italic; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  </style>
  @yield('head')
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
