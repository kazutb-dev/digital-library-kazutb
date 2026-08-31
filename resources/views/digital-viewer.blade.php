@extends('layouts.embedded')
@php
  $lang = app()->getLocale();
  $viewerTitle = ($material['title'] ?? null) ? $material['title'].' — '.__('ui.digital.reader') : __('ui.digital.viewer_title');
  $loginHref = '/login?redirect='.urlencode(request()->getRequestUri()).($lang !== 'kk' ? '&lang='.$lang : '');
  $catalogHref = $lang === 'kk' ? '/catalog' : '/catalog?lang='.$lang;
  $backHref = $backUrl ?? $catalogHref;
  $resumePage = $progress['page'] ?? null;
  $readerPayload = [
    'config' => ['streamUrl' => $material['streamUrl'] ?? null, 'progressUrl' => $material['progressUrl'] ?? null, 'resumePage' => $resumePage, 'resumeZoom' => $progress['zoom'] ?? null],
    'i18n' => ['rendering' => __('ui.digital.rendering'), 'outlineEmpty' => __('ui.digital.outline_empty'), 'errorTitle' => __('ui.digital.error_title'), 'errorBody' => __('ui.digital.error_body'), 'pageLabel' => __('ui.digital.page_label')],
  ];
@endphp
@section('title', $viewerTitle)
@section('head')
  <link rel="stylesheet" href="/css/digital-reader.css?v=20260824-1">
  <link rel="stylesheet" href="/css/digital-reader-polish.css?v=20260824-12">
@endsection

@section('content')
<div class="reader-shell" id="viewer-root">
@if ($state !== 'ready')
  <header class="reader-simple-bar">
    <a href="{{ $backHref }}" class="reader-back-link">← {{ __('ui.digital.back') }}</a>
    <strong>{{ $material['title'] ?? __('ui.digital.viewer_title') }}</strong>
  </header>
  @php
    $stateTitle = match($state) { 'denied' => __('ui.digital.denied_title'), 'external' => __('ui.digital.external_title'), 'unsupported' => __('ui.digital.unsupported_title'), default => __('ui.digital.not_found_title') };
    $stateBody = match($state) { 'denied' => $deniedReason, 'external' => __('ui.digital.external_body'), 'unsupported' => __('ui.digital.unsupported_body'), default => __('ui.digital.not_found_body') };
  @endphp
  <section class="reader-state-card {{ $state === 'denied' ? 'viewer-denied' : 'viewer-error' }}" id="{{ $state === 'denied' ? 'viewer-denied' : 'viewer-error' }}">
    <span class="reader-state-icon" aria-hidden="true">{{ $state === 'denied' ? '🔒' : ($state === 'external' ? '↗' : '?') }}</span>
    <h1>{{ $stateTitle }}</h1><p>{{ $stateBody }}</p>
    <div class="reader-state-actions">
      @if ($state === 'denied' && !session('library.user'))<a href="{{ $loginHref }}" class="reader-primary-action">{{ __('ui.digital.sign_in') }}</a>@endif
      @if ($state === 'external')<a href="{{ $material['externalUrl'] }}" class="reader-primary-action" rel="noopener noreferrer" target="_blank">{{ __('ui.digital.external_open') }}</a>@endif
      @if ($state === 'unsupported' && $material['downloadUrl'])<a href="{{ $material['downloadUrl'] }}" class="reader-primary-action">{{ __('ui.digital.download') }}</a>@endif
      <a href="{{ $catalogHref }}" class="reader-secondary-action">{{ __('ui.digital.catalog') }}</a>
    </div>
  </section>
@else
  <header class="reader-topbar">
    <div class="reader-topbar-start">
      <div class="reader-leading-actions">
        <a href="{{ $backHref }}" class="reader-icon-button reader-back-button" id="reader-back-button" title="{{ __('ui.digital.back') }}" aria-label="{{ __('ui.digital.back') }}"><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></a>
        <button class="reader-icon-button" id="reader-sidebar-toggle" type="button" aria-controls="reader-sidebar" aria-expanded="false" title="Навигация"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
      </div>
      <div class="reader-book-meta"><span>{{ __('ui.digital.reader') }}</span><h1>{{ $material['title'] }}</h1></div>
    </div>
    <div class="reader-topbar-center" aria-label="Навигация по страницам">
      <button class="reader-icon-button" id="viewer-prev" type="button" aria-label="{{ __('ui.digital.prev_page') }}" disabled><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button>
      <div class="reader-page-counter"><input id="viewer-page-input" type="number" min="1" value="1" inputmode="numeric" aria-label="{{ __('ui.digital.page_label') }}"><span class="reader-page-divider">/</span><span id="viewer-page-total">—</span></div>
      <button class="reader-icon-button" id="viewer-next" type="button" aria-label="{{ __('ui.digital.next_page') }}" disabled><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button>
    </div>
    <div class="reader-topbar-end">
      <div class="reader-control-group" aria-label="Масштаб">
        <button class="reader-icon-button" id="viewer-zoom-out" type="button" aria-label="{{ __('ui.digital.zoom_out') }}"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M8 11h6m2 5 4 4"/></svg></button>
        <button class="reader-icon-button" id="viewer-zoom-fit" type="button" aria-label="{{ __('ui.digital.zoom_fit') }}"><svg viewBox="0 0 24 24"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5m13 5h5v-5"/></svg></button>
        <button class="reader-icon-button" id="viewer-zoom-in" type="button" aria-label="{{ __('ui.digital.zoom_in') }}"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M8 11h6m-3-3v6m5 2 4 4"/></svg></button>
      </div>
      <div class="reader-control-group reader-view-group" aria-label="Вид чтения">
        <button class="reader-icon-button reader-desktop-only" id="reader-spread-toggle" type="button" aria-pressed="true" aria-label="Режим разворота"><svg viewBox="0 0 24 24"><path d="M3 5.5A3.5 3.5 0 0 1 6.5 2H11v18H6.5A3.5 3.5 0 0 0 3 23V5.5Zm18 0A3.5 3.5 0 0 0 17.5 2H13v18h4.5A3.5 3.5 0 0 1 21 23V5.5Z"/></svg></button>
        <button class="reader-icon-button" id="reader-theme-toggle" type="button" aria-label="Тема чтения"><svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-1-.2-2-.5-2.8A7 7 0 0 1 12 3Z"/></svg></button>
        <button class="reader-icon-button reader-desktop-only" id="reader-fullscreen" type="button" aria-label="На весь экран"><svg viewBox="0 0 24 24"><path d="M8 3H3v5m13-5h5v5M8 21H3v-5m13 5h5v-5"/></svg></button>
        @if ($material['licenseTerms'])<details class="reader-license"><summary class="reader-icon-button" aria-label="{{ __('ui.digital.license_terms') }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/></svg></summary><div class="reader-license-card"><strong>{{ __('ui.digital.license_terms') }}</strong><p>{{ $material['licenseTerms'] }}</p></div></details>@endif
        @if ($material['downloadUrl'])<a href="{{ $material['downloadUrl'] }}" class="reader-icon-button" aria-label="{{ __('ui.digital.download') }}"><svg viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M5 21h14"/></svg></a>@endif
      </div>
    </div>
  </header>

  <div class="reader-workspace">
    <aside class="reader-sidebar" id="reader-sidebar" aria-hidden="true">
      <div class="reader-sidebar-head"><div class="reader-tabs" role="tablist"><button class="reader-tab is-active" id="reader-pages-tab" type="button">Страницы</button><button class="reader-tab" id="reader-outline-tab" type="button">{{ __('ui.digital.outline') }}</button></div><button class="reader-icon-button" id="reader-sidebar-close" type="button" aria-label="Закрыть"><svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
      <div class="reader-sidebar-panel" id="reader-pages-panel"><div class="reader-thumbnails" id="reader-thumbnails"></div></div>
      <div class="reader-sidebar-panel" id="reader-outline-panel" hidden><div class="reader-outline" id="viewer-outline-content"></div></div>
    </aside>
    <button class="reader-sidebar-scrim" id="reader-sidebar-scrim" type="button" aria-label="Закрыть"></button>
    <main class="reader-stage" id="viewer-stage">
      <div class="reader-loading" id="viewer-loading"><div class="reader-loader-book"><i></i><i></i><i></i></div><p>{{ __('ui.digital.loading') }}</p></div>
      <button class="reader-edge-nav reader-edge-prev" id="reader-edge-prev" type="button" aria-label="{{ __('ui.digital.prev_page') }}" disabled><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button>
      <div class="reader-book-scene" id="reader-book-scene" hidden><div class="reader-book" id="reader-book">
        <article class="reader-page reader-page-left" id="reader-page-left"><canvas id="viewer-canvas-left"></canvas><span class="reader-leaf-number" id="reader-left-number"></span></article><div class="reader-spine"></div>
        <article class="reader-page reader-page-right" id="reader-page-right"><canvas id="viewer-canvas" role="img" aria-label="{{ __('ui.digital.page_label') }}"></canvas><span class="reader-leaf-number" id="reader-right-number"></span></article>
      </div></div>
      <button class="reader-edge-nav reader-edge-next" id="reader-edge-next" type="button" aria-label="{{ __('ui.digital.next_page') }}" disabled><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button>
      <p class="reader-status" id="viewer-status" role="status" aria-live="polite">@if ($resumePage && $resumePage > 1){{ __('ui.digital.resume_hint', ['page' => $resumePage]) }}@endif</p>
    </main>
  </div>
  <footer class="reader-progress-bar">
    <div class="reader-progress-value"><strong id="reader-progress-copy">0%</strong><span>{{ __('ui.digital.read_progress') }}</span></div>
    <div class="reader-progress-track">
      <input id="reader-progress" type="range" min="1" max="1" value="1" aria-label="{{ __('ui.digital.page_label') }}">
      <div class="reader-progress-meta"><span id="reader-section-label"></span><span class="reader-hint"><kbd>&larr;</kbd><kbd>&rarr;</kbd> {{ __('ui.digital.turn_hint') }}</span></div>
    </div>
    <span class="reader-format"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6zM14 3v5h5"/></svg>{{ strtoupper($material['fileType']) }} · {{ $material['fileSize'] }}</span>
  </footer>
@endif
</div>
@if ($state === 'ready')
  <script id="digital-reader-config" type="application/json">@json($readerPayload)</script>
  <meta name="digital-reader-pdfjs" content="/vendor/pdfjs/build/pdf.min.mjs">
  <script type="module" src="/js/digital-reader.js?v=20260824-16"></script>
@endif
@endsection
