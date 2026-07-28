@extends('layouts.public', ['activePage' => ''])

@php
  $lang = app()->getLocale();
  $viewerTitle = [
    'ru' => 'Электронный просмотр — Digital Library',
    'kk' => 'Электрондық қарау — Digital Library',
    'en' => 'Digital viewer — Digital Library',
  ][$lang] ?? 'Электронный просмотр — Digital Library';
@endphp

@section('title', $viewerTitle)

@section('head')
<style>
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
  }
  .viewer-toolbar-left {
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
    max-width: 540px;
  }
  .viewer-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: var(--muted);
    flex-shrink: 0;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-weight: 700;
  }
  .viewer-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    background: rgba(20, 105, 109, 0.08);
    color: var(--cyan);
    border: 1px solid rgba(20, 105, 109, 0.14);
  }
  .viewer-frame {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
      radial-gradient(circle at top, rgba(255,255,255,.5), transparent 28%),
      #f3f4f5;
    min-height: 600px;
    padding: 18px;
  }
  .viewer-frame iframe,
  .viewer-frame embed {
    width: 100%;
    height: 100%;
    border: 1px solid rgba(195, 198, 209, 0.55);
    border-radius: 8px;
    background: #fff;
    min-height: 600px;
    box-shadow: 0 6px 16px rgba(25, 28, 29, 0.03);
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
  .viewer-denied .lock-icon {
    font-size: 36px;
    margin-bottom: 10px;
    color: var(--blue);
  }
  .viewer-loading {
    color: var(--muted);
  }
  .viewer-loading .spinner {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(195, 198, 209, 0.55);
    border-top-color: var(--cyan);
    border-radius: 50%;
    animation: spin .8s linear infinite;
    margin: 0 auto 12px;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  @media (max-width: 768px) {
    .viewer-toolbar { padding: 10px 14px; flex-wrap: wrap; }
    .viewer-title { max-width: 220px; font-size: 1rem; }
    .viewer-frame { min-height: 400px; padding: 12px; }
    .viewer-frame iframe { min-height: 400px; }
  }
</style>
@endsection

@section('content')
<div class="viewer-wrap" id="viewer-root">
  <div class="viewer-loading" id="viewer-loading">
    <div class="spinner"></div>
    <p>{{ ['ru' => 'Загрузка материала...', 'kk' => 'Материал жүктелуде...', 'en' => 'Loading material...'][$lang] ?? 'Загрузка материала...' }}</p>
  </div>
</div>

<script>
  const MATERIAL_ID = @json($materialId);
  const STREAM_URL = `/api/v1/digital-materials/${encodeURIComponent(MATERIAL_ID)}/stream`;
  const IS_AUTHENTICATED = @json(session('library.user') !== null);
  const VIEWER_LANG = @json($lang);
  const VIEWER_I18N_MAP = {!! json_encode([
    'ru' => [
      'restricted' => 'Доступ к материалу ограничен.',
      'signIn' => 'Войти в систему',
      'back' => '← Назад',
      'backAction' => 'Вернуться',
      'title' => 'Контролируемый цифровой доступ',
      'permissionTitle' => 'Для доступа требуется разрешение библиотеки',
      'notFoundTitle' => 'Материал не найден',
      'notFoundBody' => 'Запрошенный электронный материал не существует или был удалён.',
      'catalog' => 'Перейти в каталог',
      'loadError' => 'Ошибка загрузки',
      'pdf' => '📄 PDF',
      'document' => '📁 Документ',
      'iframeTitle' => 'Просмотр документа',
      'inlineUnavailable' => 'Встроенный просмотр недоступен',
      'inlineUnavailableBody' => 'Этот формат файла нельзя показать прямо в читательском интерфейсе. При необходимости обратитесь к библиотекарю.',
      'errorTitle' => 'Ошибка',
      'errorBody' => 'Не удалось загрузить материал. Попробуйте позже.',
    ],
    'kk' => [
      'restricted' => 'Материалға қолжетімділік шектелген.',
      'signIn' => 'Жүйеге кіру',
      'back' => '← Артқа',
      'backAction' => 'Қайту',
      'title' => 'Бақыланатын цифрлық қолжетімділік',
      'permissionTitle' => 'Қолжетімділік үшін кітапхана рұқсаты қажет',
      'notFoundTitle' => 'Материал табылмады',
      'notFoundBody' => 'Сұралған электрондық материал жоқ немесе жойылған.',
      'catalog' => 'Каталогқа өту',
      'loadError' => 'Жүктеу қатесі',
      'pdf' => '📄 PDF',
      'document' => '📁 Құжат',
      'iframeTitle' => 'Құжатты қарау',
      'inlineUnavailable' => 'Кірістірілген қарау қолжетімсіз',
      'inlineUnavailableBody' => 'Бұл файл пішімін оқу интерфейсінде тікелей көрсету мүмкін емес. Қажет болса, кітапханашыға жүгініңіз.',
      'errorTitle' => 'Қате',
      'errorBody' => 'Материалды жүктеу мүмкін болмады. Кейінірек қайталап көріңіз.',
    ],
    'en' => [
      'restricted' => 'Access to this material is restricted.',
      'signIn' => 'Sign in',
      'back' => '← Back',
      'backAction' => 'Go back',
      'title' => 'Controlled digital access',
      'permissionTitle' => 'Access requires library permission',
      'notFoundTitle' => 'Material record not found',
      'notFoundBody' => 'The requested digital material does not exist or has been removed.',
      'catalog' => 'Open catalog',
      'loadError' => 'Load error',
      'pdf' => '📄 PDF',
      'document' => '📁 Document',
      'iframeTitle' => 'Document viewer',
      'inlineUnavailable' => 'Inline preview is not available',
      'inlineUnavailableBody' => 'This file format cannot be displayed directly in the reader surface. Use the library desk if assisted access is required.',
      'errorTitle' => 'Error',
      'errorBody' => 'Unable to load the material. Please try again later.',
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
  const VIEWER_I18N = VIEWER_I18N_MAP[VIEWER_LANG] || VIEWER_I18N_MAP.ru;

  function withLang(path) {
    const url = new URL(path, window.location.origin);
    if (VIEWER_LANG !== 'ru' && !url.searchParams.has('lang')) {
      url.searchParams.set('lang', VIEWER_LANG);
    }
    return `${url.pathname}${url.search}`;
  }

  async function initViewer() {
    const root = document.getElementById('viewer-root');
    const loading = document.getElementById('viewer-loading');

    try {
      const resp = await fetch(STREAM_URL, { method: 'HEAD' });

      if (resp.status === 403) {
        const data = await (await fetch(STREAM_URL)).json().catch(() => null);
        const reason = data?.error || VIEWER_I18N.restricted;
        loading.remove();
        const redirectTarget = `${window.location.pathname}${window.location.search}`;
        const loginBtn = !IS_AUTHENTICATED
          ? `<a href="${withLang(`/login?redirect=${encodeURIComponent(redirectTarget)}`)}" class="btn btn-primary" style="margin-bottom:8px;">${VIEWER_I18N.signIn}</a>`
          : '';
        root.innerHTML = `
          <div class="viewer-toolbar">
            <div class="viewer-toolbar-left">
              <a href="javascript:history.back()" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">${VIEWER_I18N.back}</a>
              <span class="viewer-title">${VIEWER_I18N.title}</span>
            </div>
          </div>
          <div class="viewer-frame">
            <div class="viewer-denied">
              <div class="lock-icon">🔒</div>
              <h2>${VIEWER_I18N.permissionTitle}</h2>
              <p>${escapeHtml(reason)}</p>
              ${loginBtn}
              <a href="javascript:history.back()" class="btn btn-ghost">${VIEWER_I18N.backAction}</a>
            </div>
          </div>`;
        return;
      }

      if (resp.status === 404) {
        loading.remove();
        root.innerHTML = `
          <div class="viewer-toolbar">
            <div class="viewer-toolbar-left">
              <a href="javascript:history.back()" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">${VIEWER_I18N.back}</a>
              <span class="viewer-title">${VIEWER_I18N.notFoundTitle}</span>
            </div>
          </div>
          <div class="viewer-frame">
            <div class="viewer-error">
              <h2>${VIEWER_I18N.notFoundTitle}</h2>
              <p>${VIEWER_I18N.notFoundBody}</p>
              <a href="${withLang('/catalog')}" class="btn btn-primary">${VIEWER_I18N.catalog}</a>
            </div>
          </div>`;
        return;
      }

      if (!resp.ok) {
        throw new Error(VIEWER_I18N.loadError);
      }

      const contentType = resp.headers.get('Content-Type') || '';
      const contentDisp = resp.headers.get('Content-Disposition') || '';
      const filenameMatch = contentDisp.match(/filename="?([^"]+)"?/);
      const filename = filenameMatch ? filenameMatch[1] : 'document';
      const fileSize = resp.headers.get('Content-Length');
      const isPdf = contentType.includes('pdf');

      loading.remove();

      const toolbarHtml = `
        <div class="viewer-toolbar">
          <div class="viewer-toolbar-left">
            <a href="javascript:history.back()" class="btn btn-ghost" style="padding:6px 14px;font-size:13px;">${VIEWER_I18N.back}</a>
            <span class="viewer-title">${escapeHtml(filename)}</span>
          </div>
          <div class="viewer-meta">
            ${isPdf ? `<span class="viewer-badge">${VIEWER_I18N.pdf}</span>` : `<span class="viewer-badge">${VIEWER_I18N.document}</span>`}
            ${fileSize ? `<span>${humanSize(parseInt(fileSize))}</span>` : ''}
          </div>
        </div>`;

      if (isPdf) {
        root.innerHTML = toolbarHtml +
          `<div class="viewer-frame">
            <iframe src="${STREAM_URL}#toolbar=0&navpanes=0" title="${VIEWER_I18N.iframeTitle}"></iframe>
          </div>`;
      } else {
        root.innerHTML = toolbarHtml +
          `<div class="viewer-frame">
            <div class="viewer-error">
              <h2>${VIEWER_I18N.inlineUnavailable}</h2>
              <p>${VIEWER_I18N.inlineUnavailableBody}</p>
              <a href="javascript:history.back()" class="btn btn-primary">${VIEWER_I18N.backAction}</a>
            </div>
          </div>`;
      }

    } catch (err) {
      loading.remove();
      root.innerHTML = `
        <div class="viewer-frame">
          <div class="viewer-error">
            <h2>${VIEWER_I18N.errorTitle}</h2>
            <p>${VIEWER_I18N.errorBody}</p>
            <a href="javascript:history.back()" class="btn btn-ghost">${VIEWER_I18N.backAction}</a>
          </div>
        </div>`;
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
  }

  function humanSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' МБ';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' КБ';
    return bytes + ' Б';
  }

  initViewer();
</script>
@endsection
