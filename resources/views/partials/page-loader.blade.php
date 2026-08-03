<div class="page-loader is-visible" id="page-loader" role="status" aria-live="polite" aria-label="Загрузка страницы">
  <div class="page-loader__scene" aria-hidden="true">
    <div class="page-loader__glow"></div>
    <div class="page-loader__book">
      <div class="page-loader__cover page-loader__cover--back"></div>
      <div class="page-loader__pages">
        <span class="page-loader__page page-loader__page--1"></span>
        <span class="page-loader__page page-loader__page--2"></span>
        <span class="page-loader__page page-loader__page--3"></span>
      </div>
      <div class="page-loader__cover page-loader__cover--front"></div>
      <div class="page-loader__spine"></div>
    </div>
  </div>
  <p class="page-loader__label">Казахский университет технологии и бизнеса имени К. Кулажанова</p>
</div>

<script>
  (() => {
    const loader = document.getElementById('page-loader');
    if (!loader) return;

    const minimumVisibleTime = 520;
    const startedAt = performance.now();
    let isClosing = false;

    const closeLoader = () => {
      if (isClosing) return;
      isClosing = true;
      const remaining = Math.max(0, minimumVisibleTime - (performance.now() - startedAt));
      window.setTimeout(() => {
        loader.classList.add('is-done');
        document.body.classList.remove('page-loading');
        window.setTimeout(() => loader.remove(), 520);
      }, remaining);
    };

    const showLoaderForNavigation = (event) => {
      const link = event.target.closest?.('a');
      if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
      if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin || (url.pathname === window.location.pathname && url.search === window.location.search)) return;
      loader.classList.remove('is-done');
      loader.classList.remove('is-visible');
      void loader.offsetWidth;
      loader.classList.add('is-visible');
      document.body.classList.add('page-loading');
    };

    document.body.classList.add('page-loading');
    document.addEventListener('click', showLoaderForNavigation, true);
    window.addEventListener('load', closeLoader, { once: true });
    window.addEventListener('pageshow', closeLoader, { once: true });
    if (document.readyState === 'complete') closeLoader();
  })();
</script>
