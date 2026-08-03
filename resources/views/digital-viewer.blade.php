@php
  // Rendered inside the book card's iframe: the hosting page already supplies
  // the site header and footer, so drop them here.
  $embedded = request()->boolean('embedded');
@endphp
@extends($embedded ? 'layouts.embedded' : 'layouts.public', ['activePage' => ''])

@php
  $lang = app()->getLocale();
  $viewerTitle = ($material['title'] ?? null)
    ? $material['title'].' — '.__('ui.digital.reader')
    : __('ui.digital.viewer_title');
  $loginHref = '/login?redirect='.urlencode(request()->getRequestUri());
  if ($lang !== 'kk') {
      $loginHref .= '&lang='.$lang;
  }
  $catalogHref = $lang === 'kk' ? '/catalog' : '/catalog?lang='.$lang;
  $resumePage = $progress['page'] ?? null;

  // Built here rather than inline in @json() below: Blade's directive argument
  // parser mangles a multi-line array literal that contains nested [] lookups.
  $viewerConfig = [
      'streamUrl' => $material['streamUrl'] ?? null,
      'progressUrl' => $material['progressUrl'] ?? null,
      'resumePage' => $resumePage,
      'resumeZoom' => $progress['zoom'] ?? null,
  ];

  $viewerI18n = [
      'pageOf' => __('ui.digital.page_of', ['page' => ':page', 'total' => ':total']),
      'rendering' => __('ui.digital.rendering'),
      'outlineEmpty' => __('ui.digital.outline_empty'),
      'progressSaved' => __('ui.digital.progress_saved'),
      'errorTitle' => __('ui.digital.error_title'),
      'errorBody' => __('ui.digital.error_body'),
  ];
@endphp

@section('title', $viewerTitle)

@section('head')
<style>
  /* Vertical space the surrounding chrome takes, so the stage can claim the
     rest. Standalone carries the site header and footer; the embedded variant
     inside a book card carries only the viewer's own toolbar. */
  .viewer-wrap { --viewer-chrome: 232px; }
  body.embedded-viewer-body .viewer-wrap { --viewer-chrome: 78px; }

  .viewer-wrap {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 160px);
    background:
      radial-gradient(circle at top right, rgba(20,105,109,.06), transparent 22%),
      radial-gradient(circle at bottom left, rgba(0,30,64,.05), transparent 22%),
      linear-gradient(180deg, #fbfcfc 0%, #f8f9fa 100%);
  }
  .viewer-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 20px;
    background: rgba(255, 255, 255, 0.84);
    border-bottom: 1px solid rgba(195, 198, 209, 0.45);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 5;
  }
  .viewer-toolbar-left,
  .viewer-toolbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }
  .viewer-title {
    font-family: 'Newsreader', Georgia, serif;
    font-weight: 600;
    font-size: 1.2rem;
    color: var(--blue);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 420px;
  }
  .viewer-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    background: rgba(20, 105, 109, 0.08);
    color: var(--cyan);
    border: 1px solid rgba(20, 105, 109, 0.14);
    white-space: nowrap;
  }
  .viewer-ctl {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 9px;
    border-radius: 8px;
    border: 1px solid rgba(195, 198, 209, 0.7);
    background: #fff;
    color: var(--blue);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s ease, border-color .15s ease, opacity .15s ease;
  }
  .viewer-ctl:hover:not(:disabled) { background: rgba(20, 105, 109, 0.07); border-color: var(--cyan); }
  .viewer-ctl:disabled { opacity: .4; cursor: not-allowed; }
  .viewer-ctl[aria-pressed="true"] { background: rgba(20, 105, 109, 0.12); border-color: var(--cyan); }
  .viewer-pager {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--muted);
    white-space: nowrap;
  }
  .viewer-pager input {
    width: 56px;
    height: 34px;
    padding: 0 6px;
    text-align: center;
    border: 1px solid rgba(195, 198, 209, 0.7);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--blue);
    background: #fff;
  }
  .viewer-body { flex: 1; display: flex; min-height: 600px; }
  .viewer-outline {
    width: 268px;
    flex-shrink: 0;
    border-right: 1px solid rgba(195, 198, 209, 0.45);
    background: rgba(255,255,255,.6);
    padding: 16px 12px;
    overflow-y: auto;
    max-height: calc(100vh - 220px);
  }
  .viewer-outline[hidden] { display: none; }
  .viewer-outline h3 {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--muted);
    margin: 0 0 10px;
    padding: 0 6px;
  }
  .viewer-outline ul { list-style: none; margin: 0; padding: 0 0 0 10px; }
  .viewer-outline > ul { padding-left: 0; }
  .viewer-outline button {
    display: block;
    width: 100%;
    text-align: left;
    border: 0;
    background: none;
    padding: 6px;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.45;
    color: var(--blue);
    cursor: pointer;
  }
  .viewer-outline button:hover { background: rgba(20, 105, 109, 0.08); }
  .viewer-outline p { font-size: 13px; color: var(--muted); padding: 0 6px; margin: 0; }
  .viewer-stage {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    padding: 18px;
    /* A definite height turns the stage into a real scroll viewport. Without it
       the stage grew to whatever the canvas needed, so the whole page scrolled
       and the toolbar drifted away from the document. */
    height: calc(100vh - var(--viewer-chrome, 150px));
    overflow: auto;
    background:
      radial-gradient(circle at top, rgba(255,255,255,.5), transparent 28%),
      #f3f4f5;
  }
  .viewer-canvas-holder {
    position: relative;
    background: #fff;
    /* Lifted off the stage so the document reads as a sheet of paper rather
       than an image dropped on a grey field. */
    border: 1px solid rgba(195, 198, 209, 0.7);
    border-radius: 10px;
    box-shadow:
      0 1px 2px rgba(25, 28, 29, 0.06),
      0 12px 32px rgba(25, 28, 29, 0.12);
    line-height: 0;
    /* No max-width: at fit the page is already inside the stage, and clamping it
       here meant zooming past the stage width silently stopped magnifying. The
       stage scrolls in both axes instead, as a reader expects. */
    flex-shrink: 0;
  }
  .viewer-canvas-holder canvas { display: block; }
  .viewer-status {
    font-size: 12px;
    color: var(--muted);
    min-height: 18px;
    letter-spacing: .04em;
    text-align: center;
    max-width: 720px;
  }

  /* Licence terms, one click away in the toolbar. */
  .viewer-license-pop { position: relative; }
  .viewer-license-pop > summary {
    list-style: none;
    font-size: 15px;
  }
  .viewer-license-pop > summary::-webkit-details-marker { display: none; }
  .viewer-license-pop[open] > summary { background: rgba(20, 105, 109, 0.12); border-color: var(--cyan); }
  .viewer-license-body {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    z-index: 20;
    width: min(340px, calc(100vw - 40px));
    padding: 12px 14px;
    background: #fff;
    border: 1px solid rgba(195, 198, 209, 0.7);
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(25, 28, 29, 0.14);
    text-align: left;
  }
  .viewer-license-body strong {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 6px;
  }
  .viewer-license-body p {
    margin: 0;
    font-size: 12.5px;
    line-height: 1.6;
    color: var(--blue);
  }
  .viewer-error,
  .viewer-denied,
  .viewer-loading {
    position: relative;
    overflow: hidden;
    text-align: center;
    padding: 40px 24px;
    max-width: 560px;
    width: 100%;
    margin: 40px auto;
    background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(243,244,245,.94));
    border: 1px solid rgba(195, 198, 209, 0.55);
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(25, 28, 29, 0.04);
  }
  .viewer-error::after,
  .viewer-denied::after,
  .viewer-loading::after {
    content: '';
    position: absolute;
    inset: -30px -30px auto auto;
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(20,105,109,.08), transparent 72%);
    pointer-events: none;
  }
  .viewer-error h2,
  .viewer-denied h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 28px;
    font-weight: 600;
    margin: 0 0 12px;
    color: var(--blue);
  }
  .viewer-error p,
  .viewer-denied p {
    color: var(--muted);
    font-size: 15px;
    line-height: 1.7;
    margin: 0 0 18px;
  }
  .viewer-denied .lock-icon { font-size: 36px; margin-bottom: 10px; color: var(--blue); }
  .viewer-loading .spinner {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(195, 198, 209, 0.55);
    border-top-color: var(--cyan);
    border-radius: 50%;
    animation: viewer-spin .8s linear infinite;
    margin: 0 auto 12px;
  }
  @keyframes viewer-spin { to { transform: rotate(360deg); } }

  @media (max-width: 900px) {
    .viewer-outline { position: absolute; z-index: 4; height: 100%; background: #fff; }
    .viewer-body { position: relative; }
  }
  @media (max-width: 768px) {
    .viewer-toolbar { padding: 10px 14px; flex-wrap: wrap; }
    .viewer-title { max-width: 180px; font-size: 1rem; }
    .viewer-stage { min-height: 400px; padding: 12px; }
  }
</style>
@endsection

@section('content')
<div class="viewer-wrap" id="viewer-root">

  @if ($state === 'not_found')
    <div class="viewer-toolbar">
      <div class="viewer-toolbar-left">
        <a href="{{ $catalogHref }}" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">← {{ __('ui.digital.back') }}</a>
        <span class="viewer-title">{{ __('ui.digital.not_found_title') }}</span>
      </div>
    </div>
    <div class="viewer-error">
      <h2>{{ __('ui.digital.not_found_title') }}</h2>
      <p>{{ __('ui.digital.not_found_body') }}</p>
      <a href="{{ $catalogHref }}" class="btn btn-primary">{{ __('ui.digital.catalog') }}</a>
    </div>

  @elseif ($state === 'denied')
    <div class="viewer-toolbar">
      <div class="viewer-toolbar-left">
        <a href="{{ $catalogHref }}" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">← {{ __('ui.digital.back') }}</a>
        <span class="viewer-title">{{ __('ui.digital.viewer_title') }}</span>
      </div>
    </div>
    <div class="viewer-denied">
      <div class="lock-icon" aria-hidden="true">🔒</div>
      <h2>{{ __('ui.digital.denied_title') }}</h2>
      <p>{{ $deniedReason }}</p>
      @unless (session('library.user'))
        <a href="{{ $loginHref }}" class="btn btn-primary" style="margin-bottom:8px;">{{ __('ui.digital.sign_in') }}</a>
      @endunless
      <a href="{{ $catalogHref }}" class="btn btn-ghost">{{ __('ui.digital.catalog') }}</a>
    </div>

  @elseif ($state === 'external')
    <div class="viewer-toolbar">
      <div class="viewer-toolbar-left">
        <a href="{{ $catalogHref }}" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">← {{ __('ui.digital.back') }}</a>
        <span class="viewer-title">{{ $material['title'] }}</span>
      </div>
    </div>
    <div class="viewer-error">
      <h2>{{ __('ui.digital.external_title') }}</h2>
      <p>{{ __('ui.digital.external_body') }}</p>
      <a href="{{ $material['externalUrl'] }}" class="btn btn-primary" rel="noopener noreferrer" target="_blank">{{ __('ui.digital.external_open') }}</a>
    </div>

  @elseif ($state === 'unsupported')
    <div class="viewer-toolbar">
      <div class="viewer-toolbar-left">
        <a href="{{ $catalogHref }}" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">← {{ __('ui.digital.back') }}</a>
        <span class="viewer-title">{{ $material['title'] }}</span>
      </div>
      <div class="viewer-toolbar-right">
        @if ($material['downloadUrl'])
          <a href="{{ $material['downloadUrl'] }}" class="viewer-ctl">{{ __('ui.digital.download') }}</a>
        @endif
      </div>
    </div>
    <div class="viewer-error">
      <h2>{{ __('ui.digital.unsupported_title') }}</h2>
      <p>{{ __('ui.digital.unsupported_body') }}</p>
      <a href="{{ $catalogHref }}" class="btn btn-ghost">{{ __('ui.digital.catalog') }}</a>
    </div>

  @else
    <div class="viewer-toolbar">
      <div class="viewer-toolbar-left">
        <a href="{{ $catalogHref }}" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">← {{ __('ui.digital.back') }}</a>
        <button class="viewer-ctl" id="viewer-outline-toggle" type="button"
                aria-pressed="false" aria-controls="viewer-outline"
                title="{{ __('ui.digital.outline') }}" hidden>☰</button>
        <span class="viewer-title">{{ $material['title'] }}</span>
      </div>
      <div class="viewer-toolbar-right">
        <div class="viewer-pager">
          <button class="viewer-ctl" id="viewer-prev" type="button" title="{{ __('ui.digital.prev_page') }}" aria-label="{{ __('ui.digital.prev_page') }}" disabled>‹</button>
          <label class="sr-only" for="viewer-page-input">{{ __('ui.digital.page_label') }}</label>
          <input id="viewer-page-input" type="number" min="1" value="1" inputmode="numeric" aria-label="{{ __('ui.digital.page_label') }}">
          <span id="viewer-page-total">/ —</span>
          <button class="viewer-ctl" id="viewer-next" type="button" title="{{ __('ui.digital.next_page') }}" aria-label="{{ __('ui.digital.next_page') }}" disabled>›</button>
        </div>
        <button class="viewer-ctl" id="viewer-zoom-out" type="button" title="{{ __('ui.digital.zoom_out') }}" aria-label="{{ __('ui.digital.zoom_out') }}">−</button>
        <button class="viewer-ctl" id="viewer-zoom-fit" type="button" title="{{ __('ui.digital.zoom_fit') }}" aria-label="{{ __('ui.digital.zoom_fit') }}">⤢</button>
        <button class="viewer-ctl" id="viewer-zoom-in" type="button" title="{{ __('ui.digital.zoom_in') }}" aria-label="{{ __('ui.digital.zoom_in') }}">+</button>
        <span class="viewer-badge">{{ strtoupper($material['fileType']) }} · {{ $material['fileSize'] }}</span>
        {{-- Licence text used to sit permanently under the page, competing with
             the document on every turn. It is a term of use, not reading
             material: kept one click away instead. --}}
        @if ($material['licenseTerms'])
          <details class="viewer-license-pop">
            <summary class="viewer-ctl" title="{{ __('ui.digital.license_terms') }}" aria-label="{{ __('ui.digital.license_terms') }}">©</summary>
            <div class="viewer-license-body" role="note">
              <strong>{{ __('ui.digital.license_terms') }}</strong>
              <p>{{ $material['licenseTerms'] }}</p>
            </div>
          </details>
        @endif
        @if ($material['downloadUrl'])
          <a href="{{ $material['downloadUrl'] }}" class="viewer-ctl" title="{{ __('ui.digital.download') }}">↓</a>
        @endif
      </div>
    </div>

    <div class="viewer-body">
      <nav class="viewer-outline" id="viewer-outline" hidden aria-label="{{ __('ui.digital.outline') }}">
        <h3>{{ __('ui.digital.outline') }}</h3>
        <div id="viewer-outline-content"></div>
      </nav>
      <div class="viewer-stage" id="viewer-stage">
        <div class="viewer-loading" id="viewer-loading">
          <div class="spinner" aria-hidden="true"></div>
          <p>{{ __('ui.digital.loading') }}</p>
        </div>
        <div class="viewer-canvas-holder" id="viewer-canvas-holder" hidden>
          <canvas id="viewer-canvas" role="img" aria-label="{{ __('ui.digital.page_label') }}"></canvas>
        </div>
        {{-- Kept for screen readers and transient messages (rendering, resume
             hint, errors), but no longer repeats "Страница N из M" — the pager
             in the toolbar already shows it. When the PDF has an outline, this
             names the section the current page belongs to instead. --}}
        <p class="viewer-status" id="viewer-status" role="status" aria-live="polite">
          @if ($resumePage && $resumePage > 1){{ __('ui.digital.resume_hint', ['page' => $resumePage]) }}@endif
        </p>
      </div>
    </div>
  @endif

</div>

@if ($state === 'ready')
<script type="module">
  import * as pdfjs from '/vendor/pdfjs/build/pdf.min.mjs';

  // The worker and font data are served from our own origin: reader traffic to
  // licensed material must not be observable by a third-party CDN.
  pdfjs.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/build/pdf.worker.min.mjs';

  const CONFIG = @json($viewerConfig);
  const I18N = @json($viewerI18n);

  const els = {
    stage: document.getElementById('viewer-stage'),
    loading: document.getElementById('viewer-loading'),
    holder: document.getElementById('viewer-canvas-holder'),
    canvas: document.getElementById('viewer-canvas'),
    status: document.getElementById('viewer-status'),
    prev: document.getElementById('viewer-prev'),
    next: document.getElementById('viewer-next'),
    pageInput: document.getElementById('viewer-page-input'),
    pageTotal: document.getElementById('viewer-page-total'),
    zoomIn: document.getElementById('viewer-zoom-in'),
    zoomOut: document.getElementById('viewer-zoom-out'),
    zoomFit: document.getElementById('viewer-zoom-fit'),
    outline: document.getElementById('viewer-outline'),
    outlineContent: document.getElementById('viewer-outline-content'),
    outlineToggle: document.getElementById('viewer-outline-toggle'),
  };

  // Zoom is stepped by a ratio, so it works from any starting scale — including
  // fit scales above the old 3.0 ceiling. Bounds are absolute page scales.
  const ZOOM_RATIO = 1.25;
  const ZOOM_MIN = 0.2;
  const ZOOM_MAX = 8;

  let doc = null;
  let page = 1;
  // null means "fit to width" — recomputed on resize instead of being pinned to
  // a fixed scale, so the page keeps filling the stage.
  let zoom = null;
  // Flattened outline: [{page, title}] ascending, used to name the current
  // section under the page. Built once after the outline loads.
  let outlinePages = [];
  // The scale actually used for the last paint. In fit mode `zoom` is null, so
  // this is what the zoom buttons have to step away from — stepping from 1
  // instead would make the first "zoom in" shrink a page that was fitted larger.
  let lastScale = 1;
  let renderTask = null;
  // Guards against an out-of-order render: a fast reader can queue several page
  // turns, and only the newest one may be allowed to paint.
  let renderToken = 0;
  let progressTimer = null;
  let progressEnabled = CONFIG.progressUrl !== null;

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  }

  function setStatus(text) {
    els.status.textContent = text ?? '';
  }

  // Widest a fitted page is allowed to get. Small-format scans (some of these
  // textbooks are ~11 cm wide) would otherwise be blown up to the full width of
  // a 27" monitor, which makes the line length unreadable.
  const MAX_FIT_WIDTH = 1100;

  function fitScale(pdfPage) {
    // Fit to width and scroll vertically, the way every PDF reader behaves.
    // Fitting the *whole page* was tried and rejected: a portrait page inside a
    // short window then shrinks to a narrow strip, which is the very "small
    // island on a grey field" this was meant to fix.
    const available = Math.min(MAX_FIT_WIDTH, Math.max(240, els.stage.clientWidth - 40));
    const unscaled = pdfPage.getViewport({ scale: 1 });

    return available / unscaled.width;
  }

  async function renderPage(target) {
    if (!doc) return;

    page = Math.min(Math.max(1, target), doc.numPages);
    els.pageInput.value = String(page);
    els.prev.disabled = page <= 1;
    els.next.disabled = page >= doc.numPages;
    setStatus(I18N.rendering);

    const token = ++renderToken;

    if (renderTask) {
      renderTask.cancel();
      renderTask = null;
    }

    const pdfPage = await doc.getPage(page);

    if (token !== renderToken) return;

    const scale = zoom ?? fitScale(pdfPage);
    lastScale = scale;
    // Render at device resolution and scale back down with CSS, otherwise the
    // page looks soft on retina and most phone screens.
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const viewport = pdfPage.getViewport({ scale: scale * ratio });

    els.canvas.width = Math.floor(viewport.width);
    els.canvas.height = Math.floor(viewport.height);
    els.canvas.style.width = `${Math.floor(viewport.width / ratio)}px`;
    els.canvas.style.height = `${Math.floor(viewport.height / ratio)}px`;

    renderTask = pdfPage.render({
      canvasContext: els.canvas.getContext('2d'),
      viewport,
    });

    try {
      await renderTask.promise;
    } catch (error) {
      // A cancelled render is the expected outcome of a fast page turn.
      if (error?.name === 'RenderingCancelledException') return;
      throw error;
    } finally {
      if (token === renderToken) renderTask = null;
    }

    if (token !== renderToken) return;

    els.loading.hidden = true;
    els.holder.hidden = false;
    // The toolbar pager already states the page number, so this line carries the
    // section title instead — and stays empty when the PDF has no outline rather
    // than restating what is on screen two centimetres higher.
    setStatus(sectionLabel());
    queueProgressSave();
  }

  /**
   * Title of the outline entry the current page falls under, or '' when the
   * document has no usable outline.
   */
  function sectionLabel() {
    if (!outlinePages.length) return '';

    let current = '';
    for (const entry of outlinePages) {
      if (entry.page <= page) {
        current = entry.title;
      } else {
        break;
      }
    }

    return current;
  }

  function queueProgressSave() {
    if (!progressEnabled) return;

    // Readers page through quickly; debounce so a run of page turns writes once.
    clearTimeout(progressTimer);
    progressTimer = setTimeout(saveProgress, 1200);
  }

  async function saveProgress() {
    if (!progressEnabled) return;

    try {
      const response = await fetch(CONFIG.progressUrl, {
        method: 'PUT',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          page,
          totalPages: doc?.numPages ?? null,
          zoom: zoom === null ? 'fit' : String(zoom),
        }),
      });

      if (!response.ok) {
        progressEnabled = false;
        return;
      }

      const body = await response.json().catch(() => null);

      // A guest has no identity to store a position against — stop asking.
      if (body?.data?.stored !== true) {
        progressEnabled = false;
      }
    } catch (_) {
      progressEnabled = false;
    }
  }

  function stepZoom(direction) {
    // Relative stepping rather than a fixed ladder. The old ladder topped out at
    // 3.0, but these small-format scans fit at ~3.6, so "zoom in" from fit
    // snapped *down* to 3 and visibly shrank the page. A ratio always moves in
    // the direction asked, whatever the fit scale happens to be.
    const current = zoom ?? lastScale;
    const next = direction > 0 ? current * ZOOM_RATIO : current / ZOOM_RATIO;

    zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, next));
    renderPage(page).catch(showError);
  }

  /**
   * Resolve each outline entry to a 1-based page number so the current section
   * can be looked up by page. Entries whose destination cannot be resolved are
   * dropped rather than guessed at.
   *
   * @return {Promise<Array<{page: number, title: string}>>}
   */
  async function flattenOutline(items, out = []) {
    for (const item of items) {
      try {
        const resolved = typeof item.dest === 'string'
          ? await doc.getDestination(item.dest)
          : item.dest;
        const ref = resolved?.[0];

        if (ref) {
          const index = typeof ref === 'object' ? await doc.getPageIndex(ref) : Number(ref);
          if (Number.isFinite(index)) {
            out.push({ page: index + 1, title: item.title || '' });
          }
        }
      } catch (_) {
        // Broken entry: skip it, the rest of the outline is still useful.
      }

      if (item.items?.length) await flattenOutline(item.items, out);
    }

    return out.sort((a, b) => a.page - b.page);
  }

  function buildOutline(items, depth = 0) {
    const list = document.createElement('ul');

    for (const item of items) {
      const entry = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = item.title || '—';
      button.style.paddingLeft = `${6 + depth * 10}px`;
      button.addEventListener('click', () => goToDestination(item.dest));
      entry.appendChild(button);

      if (item.items?.length) {
        entry.appendChild(buildOutline(item.items, depth + 1));
      }

      list.appendChild(entry);
    }

    return list;
  }

  async function goToDestination(dest) {
    if (!doc || !dest) return;

    try {
      const resolved = typeof dest === 'string' ? await doc.getDestination(dest) : dest;
      const ref = resolved?.[0];

      if (!ref) return;

      const index = typeof ref === 'object'
        ? await doc.getPageIndex(ref)
        : Number(ref);

      await renderPage(index + 1);
    } catch (_) {
      // A broken outline entry should not take the reader out of the document.
    }
  }

  function showError(error) {
    console.error(error);
    els.loading.hidden = true;
    els.holder.hidden = true;
    els.stage.insertAdjacentHTML('afterbegin', `
      <div class="viewer-error">
        <h2></h2>
        <p></p>
      </div>`);
    const box = els.stage.firstElementChild;
    box.querySelector('h2').textContent = I18N.errorTitle;
    box.querySelector('p').textContent = I18N.errorBody;
  }

  async function init() {
    doc = await pdfjs.getDocument({
      url: CONFIG.streamUrl,
      withCredentials: true,
      // Without these, PDFs using CID-encoded or non-embedded fonts — common in
      // the Cyrillic part of the collection — render as blank glyphs.
      cMapUrl: '/vendor/pdfjs/cmaps/',
      cMapPacked: true,
      standardFontDataUrl: '/vendor/pdfjs/standard_fonts/',
    }).promise;

    els.pageTotal.textContent = `/ ${doc.numPages}`;
    els.pageInput.max = String(doc.numPages);

    if (CONFIG.resumeZoom && CONFIG.resumeZoom !== 'fit') {
      const parsed = Number(CONFIG.resumeZoom);
      if (Number.isFinite(parsed) && parsed > 0) {
        // A saved zoom was chosen against whatever window the reader had last
        // time. Restoring it blindly is how a 1.25 saved on a laptop turned into
        // a postage stamp on a wide monitor, so only honour it when it still
        // produces a page of sensible size; otherwise fall back to fit.
        const firstPage = await doc.getPage(Number(CONFIG.resumePage) || 1);
        const fit = fitScale(firstPage);
        zoom = parsed >= fit * 0.6 ? parsed : null;
      }
    }

    const outline = await doc.getOutline();

    if (outline?.length) {
      els.outlineContent.appendChild(buildOutline(outline));
      els.outlineToggle.hidden = false;
      outlinePages = await flattenOutline(outline);
    }

    await renderPage(Number(CONFIG.resumePage) || 1);
  }

  els.prev.addEventListener('click', () => renderPage(page - 1).catch(showError));
  els.next.addEventListener('click', () => renderPage(page + 1).catch(showError));
  els.zoomIn.addEventListener('click', () => stepZoom(1));
  els.zoomOut.addEventListener('click', () => stepZoom(-1));
  els.zoomFit.addEventListener('click', () => {
    zoom = null;
    renderPage(page).catch(showError);
  });

  els.pageInput.addEventListener('change', () => {
    const target = Number(els.pageInput.value);
    renderPage(Number.isFinite(target) ? target : page).catch(showError);
  });

  els.outlineToggle.addEventListener('click', () => {
    const open = els.outline.hidden;
    els.outline.hidden = !open;
    els.outlineToggle.setAttribute('aria-pressed', open ? 'true' : 'false');
    if (zoom === null) renderPage(page).catch(showError);
  });

  document.addEventListener('keydown', (event) => {
    // Leave the page-number field alone: arrows there adjust the value.
    if (event.target instanceof HTMLInputElement) return;

    if (event.key === 'ArrowRight' || event.key === 'PageDown') {
      event.preventDefault();
      renderPage(page + 1).catch(showError);
    } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
      event.preventDefault();
      renderPage(page - 1).catch(showError);
    }
  });

  let resizeTimer = null;
  window.addEventListener('resize', () => {
    if (zoom !== null) return;
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => renderPage(page).catch(showError), 180);
  });

  // The reader may close the tab mid-page; flush the pending position first.
  window.addEventListener('pagehide', () => {
    if (progressTimer) {
      clearTimeout(progressTimer);
      saveProgress();
    }
  });

  init().catch(showError);
</script>
@endif
@endsection
