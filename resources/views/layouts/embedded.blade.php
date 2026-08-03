{{--
  Chrome-free layout for pages rendered inside an iframe (currently the digital
  viewer embedded in a book card). Site header, nav and footer are supplied by
  the hosting page, so repeating them here would give the reader two headers and
  waste vertical space that belongs to the document.
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
