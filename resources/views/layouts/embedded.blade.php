{{--
  Chrome-free reading layout for the digital viewer, both standalone and inside
  an iframe. The reading room owns the full viewport; the site header and footer
  belong to the catalogue, not to the document surface.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="robots" content="noindex" />
    <title>@yield('title', __('ui.digital.viewer_title'))</title>
    @include('partials.favicons')
    <link rel="stylesheet" href="/fonts/fonts.css" />
    <link rel="stylesheet" href="/css/public-pages-v2.css" />
    {{-- @yield pairs with the @section('head') the viewer publishes; @stack
         covers anything pushed instead. Only having the latter meant the
         embedded viewer rendered with none of its own CSS. --}}
    @yield('head')
    @stack('head')
</head>
<body class="embedded-viewer-body">
    @yield('content')
    @stack('scripts')
</body>
</html>
