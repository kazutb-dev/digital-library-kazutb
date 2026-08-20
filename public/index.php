<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '';

ob_start(static function (string $output) use ($requestPath): string {
    if (preg_match('~^/book(?:/|$)~', $requestPath) && str_contains($output, '</body>')) {
        $bookDetailScript = <<<'HTML'
<script>
(function () {
  if (!window || !document) return;

  const toastId = 'book-copy-toast';
  const toastMessages = {
    ru: { copied: 'Скопировано', failed: 'Не удалось скопировать' },
    kk: { copied: 'Көшірілді', failed: 'Көшіру мүмкін болмады' },
    en: { copied: 'Copied', failed: 'Could not copy' },
  }[document.documentElement.lang] || { copied: 'Copied', failed: 'Could not copy' };
  const ensureToast = () => {
    let toast = document.getElementById(toastId);
    if (toast) return toast;

    toast = document.createElement('div');
    toast.id = toastId;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('aria-atomic', 'true');
    toast.hidden = true;
    toast.style.cssText = [
      'position:fixed',
      'left:50%',
      'bottom:24px',
      'transform:translateX(-50%) translateY(16px)',
      'z-index:9999',
      'display:flex',
      'align-items:center',
      'gap:10px',
      'padding:12px 16px',
      'border-radius:999px',
      'background:rgba(25,28,29,0.92)',
      'color:#fff',
      'box-shadow:0 16px 36px rgba(15,23,42,0.24)',
      'opacity:0',
      'pointer-events:none',
      'transition:opacity 180ms ease, transform 180ms ease',
      'font:600 14px/1.2 "Manrope", sans-serif',
    ].join(';');
    document.body.appendChild(toast);
    return toast;
  };

  let toastTimer = null;
  const showToast = (message) => {
    const toast = ensureToast();
    toast.hidden = false;
    toast.textContent = message;
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(-50%) translateY(16px)';
      window.setTimeout(() => {
        toast.hidden = true;
      }, 200);
    }, 1800);
  };

  const copyText = async (text) => {
    const value = String(text ?? '').trim();
    if (!value) return;

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
      }
      showToast(toastMessages.copied);
    } catch (_) {
      showToast(toastMessages.failed);
    }
  };

  window.copyCitation = copyText;

})();
</script>
HTML;

        $output = preg_replace('~</body>~i', $bookDetailScript.'</body>', $output, 1);
    }

    if ($requestPath === '/catalog' && str_contains($output, '</body>')) {
        $catalogScrollScript = <<<'HTML'
<script>
(function () {
  if (!window || !document) return;

  const params = new URLSearchParams(window.location.search);
  const shouldScrollOnLoad = Number(params.get('page') || '1') > 1;
  let shouldScrollAfterResultsUpdate = shouldScrollOnLoad;

  const scrollToBooks = () => {
    const resultsList = document.getElementById('catalog-results-list');
    if (!resultsList) return;

    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const y = Math.max(0, window.scrollY + resultsList.getBoundingClientRect().top - 120);
    window.scrollTo({ top: y, left: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
  };

  const scheduleScroll = () => {
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(scrollToBooks);
    });
  };

  document.addEventListener('click', (event) => {
    const link = event.target && typeof event.target.closest === 'function'
      ? event.target.closest('#catalog-pagination a[data-page]')
      : null;

    if (link) {
      shouldScrollAfterResultsUpdate = true;
    }
  }, true);

  if (shouldScrollOnLoad) {
    scheduleScroll();
  }
})();
</script>
HTML;

        $output = preg_replace('~</body>~i', $catalogScrollScript.'</body>', $output, 1);
        $output = str_replace('class="mt-3 flex flex-wrap items-center gap-2"', 'class="mb-3 flex flex-wrap items-center gap-2"', $output);
    }

    if ($requestPath === '/rules' && str_contains($output, 'rules-canonical__toc')) {
        $output = str_replace('class="public-v2__inset rules-v2__workspace"', 'class="public-v2__inset public-v2__workspace rules-v2__workspace"', $output);
        $output = str_replace('top: 128px;', 'top: 124px;', $output);
        $output = str_replace('<span>(Library Usage Rules)</span>', '', $output);
        $output = preg_replace(
            '~<div class="rules-canonical__callout">[\s\S]*?</div>~',
            '',
            $output,
            1
        );
        $output = str_replace("font-family: 'Newsreader', serif;", "font-family: 'Literata', serif;", $output);
        $output = str_replace('font-size: clamp(2.35rem, 5vw, 3.75rem);', 'font-size: clamp(2rem, 4vw, 3.25rem);', $output);
        $output = str_replace('font-size: clamp(1.45rem, 3.1vw, 1.875rem);', 'font-size: clamp(1.2rem, 2.4vw, 1.5rem);', $output);
        $output = str_replace('font-size: 1.125rem;', 'font-size: clamp(16px, 1.25vw, 18px);', $output);
        $output = preg_replace(
            '~  \\.rules-canonical__section h2 \{\n.*?color: #000613;\n  \}~s',
            "  .rules-canonical__section h2 {\n    display: block;\n    margin: 0 0 12px;\n    color: var(--portal-ink);\n    font-family: 'Literata', serif;\n    font-size: 22px;\n    line-height: 1.3;\n  }",
            $output,
            1
        );
        $output = str_replace('font-size: 1.3rem;', 'font-size: 1.15rem;', $output);
        $output = str_replace('font-size: 1.4rem;', 'font-size: 1.2rem;', $output);
        $output = str_replace('font-size: 1.7rem;', 'font-size: 1.35rem;', $output);
        $output = str_replace('font-size: 1rem;', 'font-size: 0.9375rem;', $output);
        $output = str_replace('font-size: 0.9375rem;', 'font-size: 0.875rem;', $output);
    }

    if ($requestPath === '/rules' && str_contains($output, '</head>')) {
        $rulesTypography = <<<'HTML'
<style>
body:not(.homepage) .rules-v2 .rules-canonical__section {
  font-family: 'Google Sans', sans-serif;
}
body:not(.homepage) .rules-v2 .rules-canonical__section h2 {
  display: block !important;
  margin: 0 0 12px !important;
  color: var(--portal-ink) !important;
  font-family: "Literata", serif !important;
  font-size: 22px !important;
  font-weight: 700 !important;
  line-height: 1.3 !important;
}
body:not(.homepage) .rules-v2 .rules-canonical__num {
  display: inline-block !important;
  margin-right: 0.2em !important;
  color: var(--portal-ink) !important;
  font-family: "Literata", serif !important;
  font-size: 22px !important;
  font-weight: 700 !important;
  line-height: 1.3 !important;
}
body:not(.homepage) .rules-v2 .rules-canonical__num::after {
  content: "." !important;
}
</style>
HTML;

        $output = str_replace('</head>', $rulesTypography.'</head>', $output);
    }

    return $output;
});

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
