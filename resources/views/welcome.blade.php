@extends('layouts.public', ['activePage' => 'home'])

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';

  $withLang = function (string $path, array $query = []) use ($lang): string {
      $normalizedPath = '/' . ltrim($path, '/');
      if ($normalizedPath === '//') {
          $normalizedPath = '/';
      }

      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }

      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

      return $normalizedPath . ($query ? ('?' . http_build_query($query)) : '');
  };

  // Homepage sections 2-6 (see resources/views/home/*.blade.php).
  // Copy lives in config/homepage_sections.php; $newArrivals and $udcCounts
  // are supplied by the `/` route and default to empty so the page still
  // renders if the route is ever simplified.
  $sections = config('homepage_sections', []);
  $newArrivals = $newArrivals ?? [];
  $udcCounts = $udcCounts ?? [];
  $publicStats = is_array($publicStats ?? null) ? $publicStats : [];
  $formatPublicCount = static fn (int $value): string => number_format($value, 0, '.', $lang === 'en' ? ',' : ' ');
  $homepageTruthCopy = [
      'ru' => [
          'titles' => 'Наименований в электронном каталоге',
          'copies' => 'Экземпляров в библиотечном фонде',
          'resources' => 'Ресурсов с опубликованными условиями доступа',
          'online' => 'Онлайн-каталог доступен круглосуточно',
      ],
      'kk' => [
          'titles' => 'Электрондық каталогтағы атаулар',
          'copies' => 'Кітапхана қорындағы даналар',
          'resources' => 'Қолжетімділік шарттары жарияланған ресурстар',
          'online' => 'Онлайн каталог тәулік бойы қолжетімді',
      ],
      'en' => [
          'titles' => 'Titles in the electronic catalogue',
          'copies' => 'Copies in the library collection',
          'resources' => 'Resources with published access conditions',
          'online' => 'The online catalogue is available around the clock',
      ],
  ][$lang];
  $homepageTruthStats = [];
  foreach ([
      ['source' => 'catalog_titles', 'label' => 'titles', 'icon' => 'menu_book'],
      ['source' => 'physical_copies', 'label' => 'copies', 'icon' => 'library_books'],
      ['source' => 'published_resources', 'label' => 'resources', 'icon' => 'database'],
  ] as $definition) {
      $value = $publicStats[$definition['source']] ?? null;
      if (is_int($value) && $value > 0) {
          $homepageTruthStats[] = [
              'value' => $formatPublicCount($value),
              'label' => $homepageTruthCopy[$definition['label']],
              'icon' => $definition['icon'],
              'source' => $definition['source'],
          ];
      }
  }
  $homepageHeroStats = $homepageTruthStats;
  $homepageHeroStats[] = [
      'value' => '24/7',
      'label' => $homepageTruthCopy['online'],
      'icon' => 'schedule',
      'source' => 'public_catalog_availability',
  ];

  $chrome = [
      'ru' => [
          'title'                    => 'Главная — Научная библиотека — Казахский университет технологии и бизнеса имени К. Кулажанова',
          'hero_h1'                  => 'Открывайте знания,',
          'hero_h1_accent'           => 'управляйте источниками.',
          'hero_lead'                => 'Электронный каталог библиотечного фонда, опубликованные внешние ресурсы и библиотечные сервисы в одном месте.',
          'search_placeholder'       => 'Поиск по каталогу, авторам, УДК…',
          'search_cta'               => 'Найти',
          'hero_img_alt'             => 'Интерьер библиотечного читального зала',
          'identity_brand' => 'Научная библиотека — Казахский университет технологии и бизнеса имени К. Кулажанова',
      ],
      'kk' => [
          'title'                    => 'Басты бет — Қ. Құлажанов атындағы Қазақ технология және бизнес университеті Кітапханасы',
          'hero_h1'                  => 'Білімді ашыңыз,',
          'hero_h1_accent'           => 'дереккөздерді басқарыңыз.',
          'hero_lead'                => 'Кітапхана қорының электрондық каталогы, жарияланған сыртқы ресурстар және кітапхана қызметтері бір жерде.',
          'search_placeholder'       => 'Каталог, авторлар, ӘЖЖ бойынша іздеу…',
          'search_cta'               => 'Іздеу',
          'hero_img_alt'             => 'Кітапхана оқу залының көрінісі',
          'identity_brand' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті Кітапханасы',
      ],
      'en' => [
          'title'                    => 'Home — Kazakh University of Technology and Business named after K. Kulazhanov',
          'hero_h1'                  => 'Discover Knowledge,',
          'hero_h1_accent'           => 'Curate Your Sources.',
          'hero_lead'                => 'The library collection catalogue, published external resources, and library services in one place.',
          'search_placeholder'       => 'Search by title, author, UDC…',
          'search_cta'               => 'Search',
          'hero_img_alt'             => 'Library reading room interior',
          'identity_brand' => 'Kazakh University of Technology and Business named after K. Kulazhanov Library',
      ],
  ];

  $copy = $chrome[$lang];

@endphp

@section('title', $copy['title'])
@section('meta_description', $copy['hero_lead'])
@section('body_class', 'homepage')

@section('head')
<link rel="stylesheet" href="{{ asset('css/home-sections.css') }}">
<style>
.homepage .page-main {
    margin-top: 0;
}
[data-section="homepage-canonical-page"] {
    --homepage-gutter: var(--page-inset);
    position: relative;
    overflow: visible;
}
[data-section="homepage-canonical-page"] > section,
[data-section="homepage-canonical-page"] > div:not(.sr-only) {
    width: 100%;
    max-width: none;
    margin-inline: auto;
    padding-inline: var(--homepage-gutter);
    box-sizing: border-box;
}
[data-section="homepage-canonical-hero"] {
    min-height: 70vh;
    min-height: 70svh;
    position: relative;
    isolation: isolate;
    z-index: 2;
    overflow: visible;
    color: #fff;
    background: #0b1830;
}
.homepage-hero__image {
    position: absolute;
    inset: 0;
    z-index: -4;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 48%;
    filter: saturate(1.12) contrast(1.05) brightness(1.06);
    transform: scale(1.025);
    animation: homepageHeroImageIn 1.4s cubic-bezier(.22, 1, .36, 1) both;
}
.homepage-hero__overlay {
    position: absolute;
    inset: 0;
    z-index: -3;
    background:
      linear-gradient(95deg, rgba(3, 18, 28, .92) 0%, rgba(7, 41, 55, .72) 25%, rgba(8, 57, 68, .28) 52%, rgba(8, 57, 68, .08) 76%, rgba(8, 57, 68, 0) 100%),
      linear-gradient(180deg, rgba(6, 13, 20, .64) 0%, rgba(6, 13, 20, .18) 40%, rgba(6, 13, 20, .38) 100%);
}
.homepage-hero__ambient {
    position: absolute;
    inset: 0;
    z-index: -2;
    opacity: .8;
    background:
      radial-gradient(ellipse 46% 42% at 12% 35%, rgba(232, 160, 32, .15), transparent 72%),
      radial-gradient(ellipse 38% 58% at 88% 72%, rgba(0, 172, 172, .15), transparent 72%),
      linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
    background-size: auto, auto, 56px 56px, 56px 56px;
    background-position: 0 0, 0 0, 0 28px, 28px 0;
    mask-image: linear-gradient(to bottom, black, transparent 86%);
}
.homepage-hero__content {
    width: 100%;
    max-width: none;
    margin: 0;
    min-height: 60vh;
    min-height: 60svh;
    padding: clamp(108px, 12.5vh, 138px) 0 clamp(34px, 5vh, 48px);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
    gap: clamp(24px, 4vw, 48px);
    align-items: center;
}
.homepage-hero__copy {
    max-width: 980px;
    position: relative;
    padding: 28px 24px 24px;
    margin-left: 0;
    border-radius: 8px;
    background: transparent;
    box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
}
.homepage-hero__title {
    max-width: 760px;
    margin-top: 18px;
    font-family: "Literata", serif;
    font-size: clamp(36px, 4.2vw, 58px);
    font-weight: 700;
    letter-spacing: -.045em;
    line-height: .84;
    text-wrap: balance;
    text-shadow: 0 4px 40px rgba(0, 0, 0, .38);
}
.homepage-hero__title em {
    color: #f3bd46;
    font-weight: 500;
}
.homepage-hero__lead {
    max-width: 560px;
    margin-top: 26px;
    color: rgba(255, 255, 255, .78);
    font-size: clamp(16px, 1.3vw, 19px);
    line-height: 1.72;
}
.homepage-hero__search {
    position: relative;
    z-index: 5;
    width: min(100%, 650px);
    margin-top: 22px;
    scroll-margin-top: calc(var(--site-header-h, 88px) + 20px);
    display: flex;
    align-items: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .18);
    background: rgba(255, 255, 255, .12);
    backdrop-filter: blur(22px) saturate(180%);
    -webkit-backdrop-filter: blur(22px) saturate(180%);
    box-shadow: inset 0 1px rgba(255, 255, 255, .12), 0 10px 35px rgba(0, 0, 0, .18);
    transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease, background-color .3s ease;
}
.homepage-hero__search:focus-within {
    transform: translateY(-2px);
    border-color: rgba(9, 186, 178, .9);
    box-shadow:
      inset 0 1px rgba(255, 255, 255, .14),
      0 10px 35px rgba(0, 0, 0, .18),
      0 0 0 3px rgba(9, 186, 178, .18);
}
.homepage-hero__search input {
    min-width: 0;
    flex: 1;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, .96);
    padding: 18px 12px;
    outline: 0;
    box-shadow: none;
    caret-color: #fff;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: textfield;
}
.homepage-hero__search input::placeholder {
    color: rgba(255, 255, 255, .70);
    opacity: 1;
}
.homepage-hero__search button {
    align-self: stretch;
    padding: 0 28px;
    border: 0;
    color: #fff;
    background: linear-gradient(135deg, rgba(9, 186, 178, .96), rgba(7, 138, 132, .96));
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .04em;
    transition: filter .2s ease, background-color .2s ease, box-shadow .2s ease;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
    appearance: none;
    -webkit-appearance: none;
}
.homepage-hero__search button:hover {
    filter: brightness(1.08);
}
.homepage-hero__search button:focus-visible {
    outline: 2px solid rgba(255, 255, 255, .5);
    outline-offset: -2px;
}
.homepage-hero__search input:focus,
.homepage-hero__search button:focus {
    outline: 0;
    box-shadow: none;
}
.homepage-hero__search svg {
    color: rgba(255, 255, 255, .75);
    stroke: currentColor !important;
}
.homepage-hero__book {
    justify-self: end;
    align-self: auto;
    width: min(100%, 360px);
    aspect-ratio: 0.66 / 1;
    position: relative;
    padding: 22px 24px 20px 34px;
    background:
      linear-gradient(135deg, rgba(255,255,255,.08), transparent 28%),
      radial-gradient(circle at 18% 18%, rgba(255,255,255,.14), transparent 22%),
      radial-gradient(circle at 85% 78%, rgba(255,255,255,.06), transparent 28%),
      linear-gradient(180deg, #a86d51 0%, #8c5a42 42%, #744934 100%);
    border-radius: 10px 12px 12px 10px;
    box-shadow: 0 28px 70px rgba(0, 0, 0, .28);
    overflow: hidden;
}
.homepage-hero__book::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 24px;
    background:
      linear-gradient(90deg, rgba(50, 28, 18, .98), rgba(78, 48, 33, .96) 48%, rgba(116, 72, 50, .92));
    border-radius: 10px 0 0 10px;
    box-shadow:
      inset -1px 0 0 rgba(255, 233, 221, .18),
      inset -5px 0 0 rgba(40, 24, 15, .18),
      1px 0 0 rgba(58, 34, 22, .26);
    pointer-events: none;
}
.homepage-hero__book::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(90deg, rgba(255,255,255,.08), transparent 12%, transparent 88%, rgba(0,0,0,.06)),
      linear-gradient(180deg, rgba(255,255,255,.06), transparent 18%, transparent 84%, rgba(0,0,0,.08)),
      radial-gradient(circle at 18% 18%, rgba(255,255,255,.12), transparent 17%),
      radial-gradient(circle at 72% 28%, rgba(255,255,255,.08), transparent 18%),
      radial-gradient(circle at 68% 82%, rgba(0,0,0,.08), transparent 22%);
    border: 1px solid rgba(255, 230, 210, .18);
    border-radius: 10px;
    pointer-events: none;
}
.homepage-hero__book-grain {
    position: absolute;
    inset: 0;
    opacity: .22;
    mix-blend-mode: soft-light;
    background-image:
      radial-gradient(rgba(255,255,255,.22) 0.7px, transparent 0.8px),
      radial-gradient(rgba(255,255,255,.12) 0.6px, transparent 0.7px);
    background-size: 6px 6px, 10px 10px;
    background-position: 0 0, 3px 5px;
    pointer-events: none;
}
.homepage-hero__book-shadow {
    position: absolute;
    inset: 16px 16px 16px auto;
    width: 1px;
    background: rgba(255, 235, 220, .18);
    box-shadow:
      -6px 0 0 rgba(255, 235, 220, .08),
      -12px 0 0 rgba(58, 34, 22, .16),
      -18px 0 0 rgba(255, 255, 255, .06);
    pointer-events: none;
}
.homepage-hero__book-badge {
    width: 78px;
    height: 78px;
    margin-top: 44px;
    border-radius: 99px;
    border: 1px solid rgba(255, 240, 230, .42);
    background: rgba(255, 246, 240, .08);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(58, 34, 22, .18);
}
.homepage-hero__book-badge img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 9px;
}
.homepage-hero__book-title {
    margin-top: 22px;
    color: #fff7f1;
    font-family: "Literata", serif;
    font-size: clamp(22px, 2.35vw, 30px);
    font-weight: 800;
    line-height: .95;
    letter-spacing: -.035em;
    text-wrap: balance;
}
.homepage-hero__book-title small {
    display: block;
    margin-top: 12px;
    color: rgba(255, 233, 221, .82);
    font-family: "Google Sans", sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.homepage-hero__book-ring {
    position: absolute;
    right: -46px;
    bottom: -48px;
    width: 160px;
    height: 160px;
    border: 1px solid rgba(255, 236, 226, .12);
    border-radius: 50%;
}
.homepage-hero__book-ring::before {
    content: "";
    position: absolute;
    inset: 18px;
    border: 1px solid rgba(255, 236, 226, .1);
    border-radius: 50%;
}
.homepage-hero__book-badge {
    position: relative;
}
.homepage-hero__book-badge::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
      radial-gradient(circle at 30% 26%, rgba(255,255,255,.22), transparent 28%),
      linear-gradient(135deg, rgba(255,255,255,.08), transparent 36%);
    pointer-events: none;
}
.homepage-hero__book-title::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 1px;
    background: rgba(255, 236, 226, .12);
}
.homepage-hero__book-title {
    position: relative;
}
.homepage-hero__scroll {
    position: absolute;
    z-index: 2;
    bottom: 28px;
    left: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, .5);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .2em;
    text-transform: uppercase;
    transform: translateX(-50%);
}
.homepage-hero__bridge {
    position: relative;
    z-index: 3;
    margin-top: -28px;
    margin-bottom: 8px;
    padding: 0 32px 12px;
}
.homepage-hero__bridge-inner {
    width: 100%;
    max-width: none;
    margin: 0 auto;
    padding: 22px 24px 22px;
    background: rgba(255, 255, 255, .98);
    border: 1px solid rgba(16, 41, 69, .07);
    border-top: 0;
    box-shadow: 0 14px 34px rgba(11, 24, 48, .06);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 0 0 18px 18px;
    position: relative;
}
.homepage-hero__bridge-inner::before {
    content: "";
    position: absolute;
    left: 24px;
    right: 24px;
    top: 0;
    height: 1px;
    background: linear-gradient(90deg, rgba(179, 139, 77, 0), rgba(179, 139, 77, .45), rgba(179, 139, 77, 0));
}
.homepage-hero__bridge-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
}
.homepage-hero__bridge-kicker {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #315646;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.homepage-hero__bridge-kicker::before {
    content: "";
    width: 28px;
    height: 1px;
    background: #b38b4d;
}
.homepage-hero__bridge h2 {
    margin: 10px 0 0;
    color: #102945;
    font-family: "Literata", serif;
    font-size: clamp(28px, 3.4vw, 44px);
    line-height: 1;
}
.homepage-hero__bridge p {
    max-width: 430px;
    margin: 0;
    color: rgba(37, 49, 45, .68);
    line-height: 1.7;
}
.homepage-hero__bridge-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.homepage-hero__bridge-card {
    min-height: 148px;
    padding: 18px 18px 20px;
    background: #fff;
    border: 1px solid rgba(16, 41, 69, .09);
    box-shadow: 0 14px 34px rgba(11, 24, 48, .06);
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
}
.homepage-hero__bridge-card:hover {
    transform: translateY(-3px);
    border-color: rgba(16, 41, 69, .16);
    box-shadow: 0 18px 42px rgba(11, 24, 48, .10);
}
.homepage-hero__bridge-card strong {
    display: block;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 34px;
    line-height: 1;
}
.homepage-hero__bridge-card b {
    display: block;
    margin-top: 10px;
    color: #102945;
    font-size: 13px;
    line-height: 1.25;
}
.homepage-hero__bridge-card small {
    display: block;
    margin-top: 6px;
    color: rgba(37, 49, 45, .56);
    font-size: 11px;
    line-height: 1.45;
}
.homepage-hero__scroll::after {
    content: "";
    width: 1px;
    height: 28px;
    background: linear-gradient(#e8a020, transparent);
    animation: homepageScrollPulse 1.8s ease-in-out infinite;
}
@keyframes homepageHeroImageIn {
    from { opacity: 0; transform: scale(1.08); }
    to { opacity: 1; transform: scale(1.025); }
}
@keyframes homepageHeroCardIn {
    from { opacity: 0; transform: translateY(28px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes homepageScrollPulse {
    0%, 100% { opacity: .45; transform: scaleY(.75); transform-origin: top; }
    50% { opacity: 1; transform: scaleY(1); transform-origin: top; }
}
.homepage-canonical__bento-img {
    position: absolute; inset: 0; width: 100%; height: 100%;
    object-fit: cover; transition: transform .7s; opacity: 1;
}
.homepage-canonical__bento-tile:hover .homepage-canonical__bento-img { transform: scale(1.05); }
.homepage-canonical__bento-tile:hover { transform: translateY(-4px); }
[data-section="homepage-canonical-page"] {
    overflow: hidden;
    background: #f5f3ee;
}
[data-section="homepage-canonical-page"] h2,
[data-section="homepage-canonical-page"] h3,
[data-section="homepage-canonical-page"] h4 {
    text-wrap: balance;
}
[data-section="homepage-canonical-gateway"],
[data-section="homepage-canonical-hub-slices"],
[data-section="homepage-canonical-updates"] {
    width: 100% !important;
    max-width: none !important;
    margin-inline: auto !important;
}
[data-section="homepage-hero-bridge"] {
    background: linear-gradient(180deg, rgba(245, 243, 238, .2) 0%, rgba(245, 243, 238, 1) 100%);
}
[data-section="homepage-canonical-gateway"] {
    padding: 132px var(--homepage-gutter) 142px !important;
}
[data-section="homepage-canonical-gateway"] > div:first-child {
    padding-bottom: 34px;
    border-bottom: 1px solid rgba(16, 41, 69, .14);
}
[data-section="homepage-canonical-gateway"] > div:last-child {
    counter-reset: gateway;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 0 !important;
    border-top: 1px solid rgba(16, 41, 69, .16);
}
.homepage-canonical__gateway-card {
    counter-increment: gateway;
    min-height: 170px;
    padding: 30px 20px !important;
    border: 0 !important;
    border-right: 1px solid rgba(16, 41, 69, .13) !important;
    border-bottom: 1px solid rgba(16, 41, 69, .13) !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
}
.homepage-canonical__gateway-card::before {
    content: "0" counter(gateway);
    position: absolute;
    top: 14px;
    right: 15px;
    color: rgba(16, 41, 69, .25);
    font-family: "Literata", serif;
    font-size: 12px;
}
.homepage-canonical__gateway-card:hover {
    z-index: 2;
    color: #fff;
    background: #102945 !important;
    transform: translateY(-4px);
}
.homepage-canonical__gateway-card:hover span {
    color: #fff !important;
}
.homepage-canonical__gateway-card > span:first-of-type,
.homepage-canonical__gateway-card > span:nth-of-type(2) {
    display: none;
}
.homepage-canonical__gateway-card .h-12 {
    width: 40px !important;
    height: 40px !important;
    border-radius: 0 !important;
    color: #b57900 !important;
    background: transparent !important;
    box-shadow: none !important;
}

[data-section="homepage-canonical-hub-slices"] {
    padding: 132px var(--homepage-gutter) 150px !important;
    background: #fff;
    box-shadow: 50vw 0 #fff, -50vw 0 #fff;
}
[data-section="homepage-canonical-hub-slices"] > div:last-child {
    gap: 18px !important;
}
.homepage-canonical__hub-card {
    border: 0 !important;
    border-radius: 0 !important;
    background: #f5f3ee !important;
    box-shadow: none !important;
}
.homepage-canonical__hub-card:nth-child(even) {
    transform: translateY(34px);
}
.homepage-canonical__hub-card > div:first-child {
    height: 240px !important;
}
.homepage-canonical__hub-card img {
    filter: saturate(.7);
    transition: transform .65s ease, filter .4s ease;
}
.homepage-canonical__hub-card:hover img {
    filter: saturate(1);
    transform: scale(1.04);
}
.homepage-canonical__hub-card > div:last-child {
    padding: 30px !important;
}

[data-section="homepage-canonical-updates"] {
    padding: 198px var(--homepage-gutter) 160px !important;
}
[data-section="homepage-canonical-updates"] > div:last-child {
    grid-template-columns: 1.12fr .88fr !important;
    gap: 18px !important;
}
.homepage-canonical__update-card {
    border: 0 !important;
    border-radius: 0 !important;
    background: #102945 !important;
    box-shadow: 0 26px 70px rgba(11, 24, 48, .14) !important;
}
.homepage-canonical__update-card:nth-child(2) {
    background: #e8a020 !important;
}
.homepage-canonical__update-card > div:first-child {
    height: 320px !important;
}
.homepage-canonical__update-card > div:last-child {
    padding: 38px !important;
}
.homepage-canonical__update-card h3,
.homepage-canonical__update-card p,
.homepage-canonical__update-card span {
    color: #fff !important;
}
.homepage-canonical__update-card:nth-child(2) h3,
.homepage-canonical__update-card:nth-child(2) p,
.homepage-canonical__update-card:nth-child(2) span {
    color: #102945 !important;
}
.homepage-canonical__update-card img {
    filter: saturate(.72);
    transition: transform .7s ease;
}
.homepage-canonical__update-card:hover img {
    transform: scale(1.04);
}

/* The hero card resembles a catalog passport, not the university promo card. */
.homepage-hero__card {
    position: relative;
    color: #102945;
    background:
      linear-gradient(rgba(16, 41, 69, .055) 1px, transparent 1px),
      #f4efe3;
    background-size: 100% 38px;
    border: 1px solid rgba(255, 255, 255, .62);
    box-shadow: 0 34px 90px rgba(0, 0, 0, .35);
}
.homepage-hero__card::before {
    content: "Kazakh University of Technology and Business named after K. Kulazhanov / DIGITAL HOLDINGS";
    position: absolute;
    top: 18px;
    right: 20px;
    color: rgba(16, 41, 69, .38);
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .16em;
}
.homepage-hero__card h2,
.homepage-hero__card p {
    color: #102945 !important;
}
.homepage-hero__card > p:nth-of-type(2) {
    color: rgba(16, 41, 69, .64) !important;
}
.homepage-hero__stats {
    border-top-color: rgba(16, 41, 69, .16);
}
.homepage-hero__stat + .homepage-hero__stat {
    border-left-color: rgba(16, 41, 69, .16);
}
.homepage-hero__stat strong {
    color: #102945;
}
.homepage-hero__stat span {
    color: rgba(16, 41, 69, .54);
}
.homepage-hero__card > a {
    color: #102945 !important;
}
@media (max-width: 1023px) {
    .homepage .page-main {
        margin-top: 0;
    }
    [data-section="homepage-canonical-hero"] {
        height: auto;
        min-height: 100svh;
    }
    .homepage-hero__content {
        height: auto;
        min-height: 100svh;
        padding: 154px 0 80px;
        grid-template-columns: 1fr;
    }
    .homepage-hero__copy {
        padding: 22px 18px 20px;
    }
    .homepage-hero__scroll {
        display: none;
    }
    .homepage-hero__bridge {
        margin-top: -18px;
        margin-bottom: 14px;
        padding: 0 24px 12px;
    }
    .homepage-hero__bridge-inner {
        padding: 18px 20px 20px;
    }
    .homepage-hero__bridge-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    [data-section="homepage-canonical-gateway"],
    [data-section="homepage-canonical-hub-slices"],
    [data-section="homepage-canonical-updates"] {
        padding: 88px 24px 96px !important;
    }
    [data-section="homepage-canonical-gateway"] > div:last-child {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    [data-section="homepage-canonical-hub-slices"] > div:last-child {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    .homepage-canonical__hub-card:nth-child(even) {
        transform: none;
    }
}
@media (max-width: 640px) {
    .homepage-hero__content {
        padding: 72px 0 64px;
        grid-template-columns: 1fr;
    }
    .homepage-hero__title {
        font-size: clamp(42px, 13vw, 58px);
    }
    [data-section="homepage-canonical-gateway"],
    [data-section="homepage-canonical-hub-slices"],
    [data-section="homepage-canonical-updates"] {
        padding: 72px 18px 78px !important;
    }
    [data-section="homepage-canonical-gateway"] > div:first-child,
    [data-section="homepage-canonical-hub-slices"] > div:first-child,
    [data-section="homepage-canonical-updates"] > div:first-child {
        margin-bottom: 34px !important;
    }
    [data-section="homepage-canonical-gateway"] > div:last-child,
    [data-section="homepage-canonical-hub-slices"] > div:last-child,
    [data-section="homepage-canonical-updates"] > div:last-child {
        grid-template-columns: 1fr !important;
    }
    .homepage-hero__bridge-head {
        flex-direction: column;
        align-items: flex-start;
    }
    .homepage-hero__book {
        display: none;
    }
    .homepage-canonical__gateway-card {
        min-height: 140px;
    }
    .homepage-canonical__update-card > div:first-child {
        height: 240px !important;
    }
    .homepage-canonical__update-card > div:last-child {
        padding: 28px !important;
    }
}

/* Editorial library system v2 */
.library-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    color: #9a6900;
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .2em;
    text-transform: uppercase;
}
.library-eyebrow::before {
    content: "";
    width: 28px;
    height: 1px;
    background: currentColor;
}
.library-section-head {
    display: grid !important;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, .55fr);
    align-items: end !important;
    gap: 80px;
    margin: 0 0 54px !important;
    padding: 0 0 34px !important;
    border-bottom: 1px solid rgba(16, 41, 69, .16);
}
.library-section-head h2 {
    max-width: 800px;
    margin-top: 16px !important;
}
.library-section-note {
    color: rgba(16, 41, 69, .64);
    font-size: 14px;
    line-height: 1.8;
}
.library-section-note a {
    display: inline-flex;
    margin-top: 18px;
    color: #102945;
    border-bottom: 1px solid #d69a18;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.library-collections {
    max-width: 1370px !important;
    padding: 122px 32px 136px !important;
}
.library-collection-stage {
    height: 680px !important;
    display: grid !important;
    grid-template-columns: minmax(0, 1.55fr) minmax(340px, .72fr) !important;
    grid-template-rows: 1fr !important;
    gap: 18px !important;
}
.library-collection-feature {
    position: relative;
    display: block;
    overflow: hidden;
    color: #fff;
    background: #102945;
}
.library-collection-feature > img,
.library-institution__feature > img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(.72) contrast(1.04);
    transition: transform .9s cubic-bezier(.22, 1, .36, 1), filter .5s ease;
}
.library-image-wash {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(11,24,48,.08), rgba(11,24,48,.88));
}
.library-collection-feature:hover > img,
.library-institution__feature:hover > img {
    transform: scale(1.035);
    filter: saturate(.95) contrast(1.04);
}
.library-folio {
    position: absolute;
    top: 26px;
    left: 28px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,.45);
    color: rgba(255,255,255,.72);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.library-collection-feature > div {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 1;
    max-width: 700px;
    padding: 44px;
}
.library-collection-feature > div > span,
.library-institution__feature small,
.library-journal small {
    color: #f3bd46;
    font-size: 9px;
    font-style: normal;
    font-weight: 850;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.library-collection-feature h3 {
    margin-top: 12px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: clamp(36px, 4vw, 56px);
    font-weight: 650;
    letter-spacing: -.035em;
    line-height: 1;
}
.library-collection-feature p {
    max-width: 570px;
    margin-top: 18px;
    color: rgba(255,255,255,.68);
    font-size: 14px;
    line-height: 1.7;
}
.library-collection-feature strong,
.library-institution__feature strong,
.library-journal strong {
    display: inline-block;
    margin-top: 24px;
    padding-bottom: 5px;
    border-bottom: 1px solid #e8a020;
    font-size: 10px;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.library-collection-minor {
    display: grid;
    grid-template-rows: 1fr 1fr;
    gap: 18px;
}
.library-collection-row {
    display: grid;
    grid-template-columns: 44% 1fr;
    min-height: 0;
    overflow: hidden;
    background: #fff;
    transition: transform .35s ease, box-shadow .35s ease;
}
.library-collection-row:hover {
    z-index: 2;
    transform: translateX(-8px);
    box-shadow: 20px 30px 70px rgba(11,24,48,.16);
}
.library-collection-row__image {
    overflow: hidden;
}
.library-collection-row__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(.65);
    transition: transform .65s ease, filter .35s ease;
}
.library-collection-row:hover img {
    transform: scale(1.05);
    filter: saturate(.95);
}
.library-collection-row__copy {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 30px 26px;
}
.library-collection-row__copy small {
    color: #9a6900;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .16em;
}
.library-collection-row__copy strong {
    margin-top: 13px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 25px;
    line-height: 1.06;
}
.library-collection-row__copy em {
    margin-top: 12px;
    color: rgba(16,41,69,.56);
    font-size: 11px;
    font-style: normal;
    line-height: 1.55;
}
.library-collection-row__copy b {
    position: absolute;
    right: 18px;
    bottom: 16px;
    color: #9a6900;
    font-weight: 500;
}
.library-collection-ledger {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    margin-top: 18px;
    border-top: 1px solid rgba(16,41,69,.15);
    border-bottom: 1px solid rgba(16,41,69,.15);
}
.library-collection-ledger > div {
    display: flex;
    align-items: baseline;
    gap: 16px;
    padding: 24px 28px;
    border-right: 1px solid rgba(16,41,69,.15);
}
.library-collection-ledger > div:last-child { border-right: 0; }
.library-collection-ledger strong {
    color: #102945;
    font-family: "Literata", serif;
    font-size: 28px;
}
.library-collection-ledger span {
    color: rgba(16,41,69,.5);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.library-services {
    margin: 0 !important;
    padding: 0 !important;
    background: #0c2037 !important;
}
.library-services__inner {
    max-width: 1370px !important;
    margin: 0 auto;
    padding: 132px 32px 140px !important;
    display: grid;
    grid-template-columns: minmax(280px, .7fr) minmax(0, 1.3fr);
    gap: clamp(70px, 9vw, 140px);
}
.library-services__intro {
    position: sticky;
    top: 180px;
    align-self: start;
}
.library-services__intro h2 {
    margin-top: 18px;
    color: #fff !important;
    font-family: "Literata", serif;
    font-size: clamp(44px, 5vw, 66px) !important;
    font-weight: 650;
    letter-spacing: -.04em;
    line-height: .98;
}
.library-services__intro > p {
    max-width: 440px;
    margin-top: 24px;
    color: rgba(255,255,255,.58) !important;
    font-size: 15px;
    line-height: 1.8;
}
.library-services__seal {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 38px;
    color: #f3bd46;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
}
.library-services__seal .material-symbols-outlined { font-size: 18px; }
.library-services__list {
    display: block !important;
    border-top: 1px solid rgba(255,255,255,.16) !important;
    border-bottom: 0 !important;
}
.library-service {
    min-height: 220px;
    display: grid;
    grid-template-columns: 42px 54px minmax(0, 1fr) 34px;
    align-items: center;
    gap: 24px;
    padding: 32px 12px !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(255,255,255,.16) !important;
    background: transparent !important;
    transition: padding .35s ease, background .35s ease;
}
.library-service:hover {
    padding-inline: 28px !important;
    background: rgba(255,255,255,.055) !important;
    transform: none !important;
}
.library-service__number {
    align-self: start;
    padding-top: 8px;
    color: rgba(255,255,255,.3);
    font-family: "Literata", serif;
    font-size: 13px;
}
.library-service__icon {
    width: 54px;
    height: 54px;
    display: grid !important;
    place-items: center;
    color: #102945;
    background: #e8a020;
}
.library-service h3 {
    color: #fff !important;
    font-family: "Literata", serif;
    font-size: 28px !important;
}
.library-service > div {
    min-height: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}
.library-service p {
    max-width: 560px;
    margin-top: 12px;
    color: rgba(255,255,255,.56) !important;
    font-size: 13px;
    line-height: 1.75;
}
.library-service > a {
    color: #f3bd46 !important;
    font-size: 22px;
    transition: transform .25s ease;
}
.library-service:hover > a { transform: translate(3px, -3px); }

.library-directory {
    max-width: 1370px !important;
    padding: 132px 32px 146px !important;
    display: grid;
    grid-template-columns: minmax(280px, .58fr) minmax(0, 1.42fr);
    gap: clamp(70px, 9vw, 150px);
}
.library-directory__intro {
    position: sticky;
    top: 180px;
    align-self: start;
    padding: 0 !important;
    border: 0 !important;
}
.library-directory__intro h2 {
    margin-top: 18px !important;
}
.library-directory__intro p {
    max-width: 420px;
    margin-top: 24px;
    color: rgba(16,41,69,.58);
    font-size: 14px;
    line-height: 1.8;
}
.library-directory__list {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    align-self: start;
    border-top: 1px solid rgba(16,41,69,.16);
}
.library-directory__item {
    min-height: 132px;
    display: grid;
    grid-template-columns: 28px 36px 1fr 18px;
    align-items: center;
    gap: 17px;
    padding: 22px 20px;
    border-right: 1px solid rgba(16,41,69,.14);
    border-bottom: 1px solid rgba(16,41,69,.14);
    transition: color .28s ease, background .28s ease, transform .28s ease;
}
.library-directory__item:hover {
    z-index: 2;
    color: #fff;
    background: #102945;
    transform: translateY(-3px);
}
.library-directory__number {
    color: rgba(16,41,69,.35);
    font-family: "Literata", serif;
    font-size: 12px;
}
.library-directory__item:hover .library-directory__number { color: rgba(255,255,255,.38); }
.library-directory__item > .material-symbols-outlined {
    color: #9a6900;
    font-size: 24px;
}
.library-directory__item small {
    display: block;
    color: rgba(16,41,69,.45);
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.library-directory__item:hover small { color: rgba(255,255,255,.5); }
.library-directory__item strong {
    display: block;
    margin-top: 6px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 19px;
}
.library-directory__item:hover strong { color: #fff; }
.library-directory__item > b { color: #9a6900; font-weight: 500; }

.library-institution {
    max-width: 1370px !important;
    padding: 130px 32px 154px !important;
}
.library-institution__layout {
    display: grid !important;
    grid-template-columns: minmax(0, 1.18fr) minmax(360px, .82fr) !important;
    gap: 18px !important;
}
.library-institution__feature {
    min-height: 720px;
    position: relative;
    display: block;
    overflow: hidden;
    color: #fff;
    background: #102945;
}
.library-institution__feature > div {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 1;
    max-width: 700px;
    padding: 46px;
}
.library-institution__feature h3 {
    margin-top: 13px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: clamp(38px, 4vw, 56px);
    line-height: 1;
}
.library-institution__feature p {
    margin-top: 18px;
    color: rgba(255,255,255,.65);
    font-size: 14px;
    line-height: 1.75;
}
.library-institution__index {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    border-top: 1px solid rgba(16,41,69,.16);
}
.library-institution__index > a {
    display: grid;
    grid-template-columns: 28px 40px 1fr 18px;
    align-items: start;
    gap: 18px;
    padding: 30px 20px;
    border-bottom: 1px solid rgba(16,41,69,.16);
    transition: padding .3s ease, background .3s ease;
}
.library-institution__index > a:hover {
    padding-inline: 30px;
    background: #f5f3ee;
}
.library-institution__index > a > span:first-child {
    color: rgba(16,41,69,.3);
    font-family: "Literata", serif;
    font-size: 12px;
}
.library-institution__index .material-symbols-outlined {
    color: #9a6900;
    font-size: 24px;
}
.library-institution__index small {
    color: #9a6900;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .14em;
    text-transform: uppercase;
}
.library-institution__index h3 {
    margin-top: 7px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 25px;
    line-height: 1.05;
}
.library-institution__index p {
    margin-top: 10px;
    color: rgba(16,41,69,.52);
    font-size: 11px;
    line-height: 1.6;
}
.library-institution__index b { color: #9a6900; font-weight: 500; }

.library-journal {
    max-width: 1370px !important;
    padding: 132px 32px 160px !important;
}
.library-journal__layout {
    display: grid !important;
    grid-template-columns: minmax(0, 1.35fr) minmax(340px, .65fr) !important;
    gap: 18px !important;
}
.library-journal__feature {
    min-height: 560px;
    display: grid;
    grid-template-columns: 56% 44%;
    overflow: hidden;
    background: #102945;
}
.library-journal__feature > div {
    position: relative;
    overflow: hidden;
}
.library-journal__feature img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(.7);
    transition: transform .8s ease;
}
.library-journal__feature:hover img { transform: scale(1.035); }
.library-journal__feature > div > span {
    position: absolute;
    top: 22px;
    left: 22px;
    padding: 9px 12px;
    color: #102945;
    background: #f4efe3;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.library-journal__feature article {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 44px;
    color: #fff;
}
.library-journal h3 {
    margin-top: 14px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: clamp(32px, 3.4vw, 48px);
    line-height: 1;
}
.library-journal__feature p,
.library-journal__event p {
    margin-top: 20px;
    color: rgba(255,255,255,.6);
    font-size: 13px;
    line-height: 1.75;
}
.library-journal__event {
    min-height: 560px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 44px;
    color: #102945;
    background:
      linear-gradient(rgba(16,41,69,.055) 1px, transparent 1px),
      #e8a020;
    background-size: 100% 42px;
    transition: transform .35s ease, box-shadow .35s ease;
}
.library-journal__event:hover {
    transform: translateY(-7px);
    box-shadow: 0 34px 80px rgba(11,24,48,.18);
}
.library-journal__event > .material-symbols-outlined {
    margin-bottom: auto;
    font-size: 46px;
}
.library-journal__event small { color: rgba(16,41,69,.62); }
.library-journal__event h3 { color: #102945; }
.library-journal__event p { color: rgba(16,41,69,.66); }
.library-journal__event strong { border-bottom-color: #102945; }

@media (max-width: 1023px) {
    .library-section-head,
    .library-services__inner,
    .library-directory {
        grid-template-columns: 1fr !important;
        gap: 34px;
    }
    .library-collection-stage {
        height: auto !important;
        grid-template-columns: 1fr !important;
    }
    .library-collection-feature { min-height: 600px; }
    .library-collection-minor {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 310px;
    }
    .library-services__intro,
    .library-directory__intro {
        position: static;
    }
    .library-institution__layout,
    .library-journal__layout {
        grid-template-columns: 1fr !important;
    }
    .library-institution__feature { min-height: 620px; }
}
@media (max-width: 640px) {
    .library-collections,
    .library-services__inner,
    .library-directory,
    .library-institution,
    .library-journal {
        padding: 78px 18px 88px !important;
    }
    .library-section-head {
        margin-bottom: 36px !important;
    }
    .library-collection-feature { min-height: 520px; }
    .library-collection-feature > div,
    .library-institution__feature > div {
        padding: 28px;
    }
    .library-collection-minor {
        grid-template-columns: 1fr;
        grid-template-rows: 270px 270px;
    }
    .library-collection-ledger {
        grid-template-columns: 1fr;
    }
    .library-collection-ledger > div {
        border-right: 0;
        border-bottom: 1px solid rgba(16,41,69,.15);
    }
    .library-service {
        grid-template-columns: 30px 44px 1fr;
        min-height: 190px;
        gap: 14px;
    }
    .library-service > a { display: none; }
    .library-service__icon { width: 44px; height: 44px; font-size: 20px; }
    .library-directory__list { grid-template-columns: 1fr !important; }
    .library-institution__feature { min-height: 540px; }
    .library-institution__index > a {
        grid-template-columns: 24px 32px 1fr;
        padding-inline: 8px;
    }
    .library-institution__index b { display: none; }
    .library-journal__feature {
        grid-template-columns: 1fr;
    }
    .library-journal__feature > div { min-height: 300px; }
    .library-journal__feature article,
    .library-journal__event { padding: 30px; }
    .library-journal__event { min-height: 480px; }
}

/* Independent library palette: turquoise, warm beige, and white */
[data-section="homepage-canonical-page"] {
    --library-teal-deep: #343936;
    --library-teal: #09bab2;
    --library-turquoise: #09bab2;
    --library-ink: #3e403a;
    --library-beige: #ffffff;
    --library-sand: #ffffff;
    --library-ivory: #ffffff;
    --library-white: #ffffff;
    background: var(--library-white);
}
[data-section="homepage-canonical-hero"] {
    background: var(--library-teal-deep);
}
.homepage-hero__overlay {
    background:
      linear-gradient(95deg, rgba(2, 22, 21, .82) 0%, rgba(4, 56, 53, .56) 34%, rgba(4, 64, 61, .18) 64%, rgba(4, 64, 61, .06) 100%),
      linear-gradient(180deg, rgba(12, 18, 18, .58) 0%, rgba(12, 18, 18, .18) 40%, rgba(12, 18, 18, .62) 100%);
}
.homepage-hero__ambient {
    background:
      radial-gradient(ellipse 46% 42% at 12% 35%, rgba(9, 186, 178, .08), transparent 72%),
      radial-gradient(ellipse 38% 58% at 88% 72%, rgba(255, 255, 255, .08), transparent 72%),
      linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
}
.homepage-hero__kicker::before {
    background: #09bab2;
    box-shadow: 0 0 16px rgba(9, 186, 178, .8);
}
.homepage-hero__title em {
    color: #a9f1ed;
    text-shadow: 0 4px 34px rgba(9, 186, 178, .2);
}
.homepage-hero__search button {
    color: #ffffff;
    background: #09bab2;
    box-shadow: inset 1px 0 rgba(0, 0, 0, .05);
}
.homepage-hero__topics a:hover {
    border-color: rgba(255, 255, 255, .5);
    background: rgba(255, 255, 255, .1);
}
.homepage-hero__scroll::after {
    background: linear-gradient(var(--library-beige), transparent);
}
.library-eyebrow,
.library-collection-row__copy small,
.library-collection-row__copy b,
.library-directory__item > b,
.library-institution__index small,
.library-institution__index b {
    color: #5d625e;
}
.library-directory__item > .material-symbols-outlined,
.library-institution__index .material-symbols-outlined {
    color: #09bab2;
}
.library-section-head,
.library-collection-ledger,
.library-collection-ledger > div,
.library-directory__list,
.library-directory__item,
.library-institution__index,
.library-institution__index > a {
    border-color: rgba(22, 76, 73, .16) !important;
}
.library-section-head h2,
.library-section-note a,
.library-collection-row__copy strong,
.library-collection-ledger strong,
.library-directory__intro h2,
.library-directory__item strong,
.library-institution__index h3,
.library-journal__event,
.library-journal__event h3 {
    color: var(--library-ink) !important;
}
.library-section-note,
.library-directory__intro p,
.library-collection-row__copy em,
.library-institution__index p {
    color: rgba(22, 76, 73, .62);
}
.library-section-note a,
.library-collection-feature strong,
.library-institution__feature strong,
.library-journal strong {
    border-bottom-color: var(--library-beige);
}
.library-collection-feature,
.library-institution__feature,
.library-journal__feature {
    background: var(--library-teal-deep);
}
.library-image-wash {
    background: linear-gradient(180deg, rgba(32,35,33,.04), rgba(32,35,33,.86));
}
.library-collection-feature > div > span,
.library-institution__feature small,
.library-journal small {
    color: #ffffff;
}
.library-collection-row {
    background: var(--library-white);
}
.library-collection-row:hover {
    box-shadow: 20px 30px 70px rgba(6, 75, 72, .14);
}
.library-services {
    color: var(--library-ink) !important;
    background:
      var(--library-white) !important;
    border-top: 1px solid rgba(62, 64, 58, .11);
    border-bottom: 1px solid rgba(62, 64, 58, .11);
}
.library-services__intro h2,
.library-service h3 {
    color: var(--library-ink) !important;
}
.library-services__intro > p,
.library-service p {
    color: rgba(62, 64, 58, .62) !important;
}
.library-services__seal,
.library-service > a {
    color: #09bab2 !important;
}
.library-service__icon {
    color: var(--library-ink);
    background: var(--library-beige);
}
.library-services__list,
.library-service {
    border-color: rgba(62, 64, 58, .13) !important;
}
.library-service__number { color: rgba(62, 64, 58, .3); }
.library-service:hover {
    background: var(--library-ivory) !important;
}
.library-directory__item:hover {
    background: var(--library-teal-deep);
}
.library-directory__item:hover > .material-symbols-outlined,
.library-directory__item:hover > b {
    color: var(--library-beige);
}
.library-institution {
    background: var(--library-white) !important;
    box-shadow: 50vw 0 var(--library-white), -50vw 0 var(--library-white) !important;
}
.library-institution__index > a:hover {
    background: var(--library-ivory);
}
.library-journal__event {
    background:
      linear-gradient(rgba(22,76,73,.055) 1px, transparent 1px),
      var(--library-white);
    border: 1px solid rgba(62, 64, 58, .12);
}
.library-journal__event strong { border-bottom-color: var(--library-ink); }
.library-journal__feature {
    color: var(--library-ink);
    background: var(--library-white);
    border: 1px solid rgba(62, 64, 58, .12);
}
.library-journal__feature article {
    color: var(--library-ink);
}
.library-journal__feature h3 {
    color: var(--library-ink);
}
.library-journal__feature p {
    color: rgba(62, 64, 58, .62);
}
.library-journal__feature small {
    color: #5d625e;
}

/* Library intelligence: a quiet, archival system for the homepage content. */
.library-intelligence {
    --archive-white: #ffffff;
    --archive-paper: #f6f5f0;
    --archive-paper-deep: #ece9df;
    --archive-ink: #25312d;
    --archive-forest: #315646;
    --archive-forest-deep: #203d32;
    --archive-sage: #839386;
    --archive-brass: #b38b4d;
    --archive-clay: #9a6652;
    --archive-line: rgba(37, 49, 45, .13);
    --library-section-title-size: clamp(42px, 3.5vw, 56px);
    color: var(--archive-ink);
    background: var(--archive-white);
    width: 100%;
    min-width: 0;
}
.library-intelligence__section {
    position: relative;
    width: 100% !important;
    min-width: 0;
    max-width: none !important;
    margin: 0 !important;
    padding: clamp(84px, 8vw, 132px) max(5vw, calc((100vw - 1440px) / 2)) !important;
    overflow: hidden;
}
.library-intelligence__section--paper {
    background:
      linear-gradient(rgba(49, 86, 70, .035) 1px, transparent 1px),
      linear-gradient(90deg, rgba(49, 86, 70, .035) 1px, transparent 1px),
      var(--archive-paper) !important;
    background-size: 48px 48px !important;
}
.library-intelligence__section--white {
    background: var(--archive-white) !important;
}
.library-intelligence__head {
    display: grid !important;
    grid-template-columns: minmax(0, 1.15fr) minmax(280px, .7fr);
    gap: 60px;
    align-items: end;
    max-width: 1440px;
    margin: 0 auto 52px !important;
    padding: 0 0 30px !important;
    border: 0 !important;
    border-bottom: 1px solid var(--archive-line) !important;
}
.library-intelligence__head > *,
.library-overview__layout > *,
.library-categories__layout > *,
.library-bookshelf > *,
.library-analytics__grid > * {
    min-width: 0;
}
.library-intelligence__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 18px;
    color: var(--archive-forest);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .17em;
    line-height: 1;
    text-transform: uppercase;
}
.library-intelligence__eyebrow::before {
    width: 28px;
    height: 1px;
    background: var(--archive-brass);
    content: "";
}
.library-intelligence__head h2 {
    max-width: 820px;
    margin: 0;
    color: var(--archive-ink) !important;
    font-family: var(--font-display, Georgia, serif);
    font-size: var(--library-section-title-size) !important;
    font-weight: 650;
    letter-spacing: -.042em;
    line-height: .98;
}
.library-intelligence__head > p {
    max-width: 520px;
    margin: 0 0 4px;
    color: rgba(37, 49, 45, .67);
    font-size: 16px;
    line-height: 1.75;
}
.library-intelligence__link {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    width: fit-content;
    margin-top: 22px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--archive-brass);
    color: var(--archive-ink);
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.library-intelligence__link span {
    color: var(--archive-brass);
    font-size: 17px;
    transition: transform .25s ease;
}
.library-intelligence__link:hover span {
    transform: translateX(4px);
}
.library-overview__layout {
    display: grid;
    grid-template-columns: minmax(0, .94fr) minmax(440px, 1.06fr);
    grid-template-rows: none !important;
    gap: 28px;
    height: auto !important;
    max-width: 1440px;
    margin: 0 auto;
}
.library-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid var(--archive-line);
    border-left: 1px solid var(--archive-line);
}
.library-metric {
    min-height: 220px;
    padding: clamp(26px, 3vw, 42px);
    background: var(--archive-white);
    border-right: 1px solid var(--archive-line);
    border-bottom: 1px solid var(--archive-line);
    transition: background .25s ease, transform .25s ease;
}
.library-metric:hover {
    z-index: 1;
    background: #fbfaf6;
    transform: translateY(-4px);
}
.library-metric__index {
    display: block;
    margin-bottom: 38px;
    color: var(--archive-brass);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10px;
    letter-spacing: .12em;
}
.library-metric strong {
    display: block;
    color: var(--archive-ink);
    font-family: var(--font-display, Georgia, serif);
    font-size: clamp(38px, 4vw, 58px);
    font-weight: 600;
    letter-spacing: -.04em;
    line-height: .9;
}
.library-metric b {
    display: block;
    margin-top: 16px;
    color: var(--archive-ink);
    font-size: 13px;
    font-weight: 800;
}
.library-metric small {
    display: block;
    margin-top: 6px;
    color: rgba(37, 49, 45, .52);
    font-size: 11px;
    line-height: 1.45;
}
.library-growth {
    position: relative;
    min-height: 520px;
    padding: clamp(30px, 4vw, 54px);
    color: #fff;
    background:
      radial-gradient(circle at 90% 0, rgba(179, 139, 77, .2), transparent 34%),
      var(--archive-forest-deep);
    overflow: hidden;
}
.library-growth::after {
    position: absolute;
    right: -100px;
    bottom: -170px;
    width: 360px;
    height: 360px;
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 50%;
    box-shadow: 0 0 0 38px rgba(255, 255, 255, .025), 0 0 0 76px rgba(255, 255, 255, .018);
    content: "";
}
.library-growth__top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
}
.library-growth__top small {
    display: block;
    margin-bottom: 9px;
    color: rgba(255, 255, 255, .52);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .15em;
    text-transform: uppercase;
}
.library-growth__top h3 {
    margin: 0;
    color: #fff;
    font-family: var(--font-display, Georgia, serif);
    font-size: clamp(27px, 3vw, 40px);
    font-weight: 600;
}
.library-growth__badge {
    flex: 0 0 auto;
    padding: 10px 13px;
    color: #f4ddae;
    background: rgba(255, 255, 255, .07);
    border: 1px solid rgba(255, 255, 255, .13);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
}
.library-growth__legend {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 14px 25px;
    margin-top: 32px;
    color: rgba(255, 255, 255, .68);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .05em;
}
.library-growth__legend span {
    display: inline-flex;
    align-items: center;
    gap: 9px;
}
.library-growth__legend i {
    width: 22px;
    height: 2px;
    background: var(--series-color);
}
.library-growth__chart {
    position: relative;
    z-index: 1;
    width: 100%;
    height: auto;
    margin-top: 18px;
    overflow: hidden;
}
.library-growth__chart .grid-line {
    stroke: rgba(255, 255, 255, .1);
    stroke-width: 1;
}
.library-growth__chart .vertical-guide {
    stroke: rgba(255, 255, 255, .045);
    stroke-width: 1;
}
.library-growth__chart .axis-label,
.library-growth__chart .month-label {
    fill: rgba(255, 255, 255, .44);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10px;
    letter-spacing: .04em;
}
.library-growth__chart .month-label {
    text-anchor: middle;
    text-transform: uppercase;
}
.library-growth__chart .area-primary {
    fill: url(#libraryUsageGradient);
}
.library-growth__chart .usage-line {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 3.5;
}
.library-growth__chart .usage-line--primary {
    stroke: #e0bd79;
}
.library-growth__chart .usage-line--secondary {
    stroke: #9ab8aa;
    stroke-dasharray: 6 7;
    stroke-width: 2.5;
}
.library-growth__chart .dot-primary,
.library-growth__chart .dot-secondary {
    fill: var(--archive-forest-deep);
    stroke-width: 2.5;
}
.library-growth__chart .dot-primary {
    stroke: #efd49d;
}
.library-growth__chart .dot-secondary {
    stroke: #a9cabc;
}
.library-growth__chart .latest-marker line {
    stroke: rgba(224, 189, 121, .48);
    stroke-width: 2;
}
.library-growth__chart .latest-marker rect {
    fill: #f2e1bb;
}
.library-growth__chart .latest-marker text {
    fill: var(--archive-forest-deep);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
    font-weight: 800;
    text-anchor: middle;
}
.library-categories__layout {
    display: grid;
    grid-template-columns: minmax(500px, .75fr) minmax(0, 1.25fr);
    gap: clamp(44px, 5vw, 80px);
    max-width: 1440px !important;
    margin: 0 auto;
    padding-inline: 0 !important;
}
.library-categories__intro {
    position: sticky;
    top: 150px;
    align-self: start;
    max-width: none !important;
    margin: 0 !important;
}
.library-categories__intro h2 {
    max-width: 520px;
    margin: 0;
    color: var(--archive-ink) !important;
    font-family: var(--font-display, Georgia, serif);
    font-size: var(--library-section-title-size) !important;
    font-weight: 650;
    letter-spacing: -.04em;
    line-height: .98;
    overflow-wrap: normal;
    word-break: normal;
}
.library-categories__intro > p {
    margin-top: 25px;
    color: rgba(37, 49, 45, .67) !important;
    font-size: 15px;
    line-height: 1.8;
}
.library-intelligence__link {
    color: var(--archive-ink) !important;
}
.library-category-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid var(--archive-line);
    border-left: 1px solid var(--archive-line);
}
.library-category {
    position: relative;
    min-height: 218px;
    padding: 28px;
    background: rgba(255, 255, 255, .77);
    border-right: 1px solid var(--archive-line);
    border-bottom: 1px solid var(--archive-line);
    overflow: hidden;
    transition: color .28s ease, background .28s ease;
}
.library-category {
    color: var(--archive-ink) !important;
}
.library-category::after {
    position: absolute;
    right: -40px;
    bottom: -60px;
    width: 140px;
    height: 140px;
    border: 1px solid rgba(49, 86, 70, .11);
    border-radius: 50%;
    content: "";
    transition: transform .35s ease;
}
.library-category:hover {
    color: #fff !important;
    background: var(--archive-forest);
}
.library-category:hover::after {
    border-color: rgba(255, 255, 255, .16);
    transform: scale(1.35);
}
.library-category__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}
.library-category__icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    color: var(--archive-forest);
    background: var(--archive-paper-deep);
}
.library-category__icon .material-symbols-outlined {
    font-size: 21px;
}
.library-category__count {
    color: rgba(37, 49, 45, .48);
    font: 11px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .08em;
}
.library-category h3 {
    position: relative;
    z-index: 1;
    max-width: 260px;
    margin: 31px 0 22px;
    color: inherit !important;
    font-family: var(--font-display, Georgia, serif);
    font-size: 22px !important;
    font-weight: 600;
    line-height: 1.14;
}
.library-category__scale {
    position: absolute;
    right: 28px;
    bottom: 28px;
    left: 28px;
    height: 2px;
    background: rgba(37, 49, 45, .12);
}
.library-category__scale span {
    display: block;
    width: var(--category-share);
    height: 100%;
    background: var(--archive-brass);
    transition: width .45s ease;
}
.library-category:hover .library-category__icon {
    color: #fff;
    background: rgba(255, 255, 255, .12);
}
.library-category:hover .library-category__count {
    color: rgba(255, 255, 255, .62);
}
.library-category:hover .library-category__scale {
    background: rgba(255, 255, 255, .15);
}
.library-books__layout {
    max-width: 1440px;
    margin: 0 auto;
    gap: 0 !important;
    border-top: 0 !important;
    grid-template-columns: none !important;
}
.library-bookshelf {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: clamp(18px, 2.4vw, 34px);
    padding: 20px 20px 0;
    border-bottom: 18px solid #d6d0c2;
    box-shadow: 0 10px 0 #f0ede5, 0 20px 28px rgba(37, 49, 45, .09);
}
.library-book {
    display: block;
    min-width: 0;
}
.library-book__cover {
    position: relative;
    min-height: 390px;
    padding: 34px 28px 30px 38px;
    color: #f9f6ed;
    background: var(--book-color, var(--archive-forest));
    border-radius: 2px 8px 8px 2px;
    box-shadow: -8px 8px 0 rgba(37, 49, 45, .08), 0 18px 34px rgba(37, 49, 45, .15);
    overflow: hidden;
    transform-origin: bottom center;
    transition: transform .35s cubic-bezier(.2, .8, .2, 1), box-shadow .35s ease;
}
.library-book:hover .library-book__cover {
    box-shadow: -9px 14px 0 rgba(37, 49, 45, .08), 0 30px 50px rgba(37, 49, 45, .2);
    transform: translateY(-10px) rotate(-.6deg);
}
.library-book__cover::before {
    position: absolute;
    inset: 0 auto 0 12px;
    width: 1px;
    background: rgba(255, 255, 255, .28);
    box-shadow: 3px 0 8px rgba(0, 0, 0, .18);
    content: "";
}
.library-book__cover::after {
    position: absolute;
    right: -90px;
    bottom: -96px;
    width: 250px;
    height: 250px;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 50%;
    box-shadow: 0 0 0 25px rgba(255, 255, 255, .035), 0 0 0 50px rgba(255, 255, 255, .025);
    content: "";
}
.library-book__cover--forest { --book-color: #315646; }
.library-book__cover--clay { --book-color: #9a6652; }
.library-book__cover--ink { --book-color: #293b45; }
.library-book__cover--sage { --book-color: #6d7d6d; }
.library-book__code {
    display: block;
    color: rgba(255, 255, 255, .62);
    font: 10px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .15em;
}
.library-book__ornament {
    display: grid;
    width: 48px;
    height: 48px;
    margin-top: 58px;
    border: 1px solid rgba(255, 255, 255, .34);
    border-radius: 50%;
    place-items: center;
}
.library-book__ornament .material-symbols-outlined {
    font-size: 20px;
}
.library-book__cover h3 {
    position: relative;
    z-index: 1;
    margin: 28px 0 0;
    color: inherit;
    font-family: var(--font-display, Georgia, serif);
    font-size: clamp(23px, 1.65vw, 26px);
    font-weight: 600;
    line-height: 1.06;
    overflow-wrap: anywhere;
}
.library-book__cover small {
    position: absolute;
    bottom: 28px;
    left: 38px;
    z-index: 1;
    color: rgba(255, 255, 255, .58);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.library-book__meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 23px 3px 26px;
    color: rgba(37, 49, 45, .63);
    font-size: 11px;
    letter-spacing: .03em;
}
.library-book__meta span:last-child {
    color: var(--archive-brass);
    font-weight: 800;
}
.library-analytics__grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1.22fr;
    gap: 1px !important;
    max-width: 1440px;
    margin: 0 auto;
    background: var(--archive-line);
    border: 1px solid var(--archive-line);
}
.library-analytics__panel {
    min-height: 420px;
    padding: clamp(28px, 3vw, 44px);
    background: var(--archive-white);
}
.library-analytics__panel > header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    min-height: 66px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--archive-line);
}
.library-analytics__panel h3 {
    margin: 0;
    color: var(--archive-ink);
    font-family: var(--font-display, Georgia, serif);
    font-size: 24px;
    font-weight: 600;
}
.library-analytics__panel header span {
    color: rgba(37, 49, 45, .43);
    font: 9px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .08em;
    text-align: right;
    text-transform: uppercase;
}
.library-language-chart {
    display: grid;
    grid-template-columns: 148px 1fr;
    gap: 30px;
    align-items: center;
    margin-top: 52px;
}
.library-donut {
    position: relative;
    width: 148px;
    height: 148px;
    border-radius: 50%;
    background: conic-gradient(var(--archive-forest) 0 52%, var(--archive-brass) 52% 83%, var(--archive-paper-deep) 83% 100%);
}
.library-donut::after {
    position: absolute;
    inset: 27px;
    display: grid;
    color: var(--archive-ink);
    background: #fff;
    border-radius: 50%;
    content: "8.9K";
    font-family: var(--font-display, Georgia, serif);
    font-size: 25px;
    font-weight: 650;
    place-items: center;
}
.library-language-legend {
    display: grid;
    gap: 16px;
}
.library-language-legend li {
    display: grid;
    grid-template-columns: 8px 1fr auto;
    gap: 10px;
    align-items: center;
    color: rgba(37, 49, 45, .67);
    font-size: 12px;
}
.library-language-legend i {
    width: 7px;
    height: 7px;
    background: var(--legend-color);
    border-radius: 50%;
}
.library-language-legend strong {
    color: var(--archive-ink);
    font-size: 12px;
}
.library-format-bars {
    display: grid;
    gap: 26px;
    margin-top: 42px;
}
.library-format-bar__label {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
    color: rgba(37, 49, 45, .68);
    font-size: 11px;
}
.library-format-bar__label strong {
    color: var(--archive-ink);
    font: 11px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
}
.library-format-bar__track {
    height: 5px;
    background: var(--archive-paper-deep);
}
.library-format-bar__track span {
    display: block;
    width: var(--format-share);
    height: 100%;
    background: var(--archive-forest);
}
.library-activity__value {
    margin-top: 34px;
    color: var(--archive-ink);
    font-family: var(--font-display, Georgia, serif);
    font-size: 42px;
    font-weight: 600;
    letter-spacing: -.04em;
}
.library-activity__value small {
    margin-left: 7px;
    color: var(--archive-forest);
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
}
.library-activity-chart {
    width: 100%;
    height: 155px;
    margin-top: 22px;
    overflow: visible;
}
.library-activity-chart .guide {
    stroke: rgba(37, 49, 45, .1);
    stroke-width: 1;
}
.library-activity-chart .bar {
    fill: #dfe4de;
}
.library-activity-chart .bar.is-current {
    fill: var(--archive-forest);
}
.library-activity-chart .trend {
    fill: none;
    stroke: var(--archive-brass);
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2.5;
}
.library-activity__months {
    display: flex;
    justify-content: space-around;
    color: rgba(37, 49, 45, .4);
    font: 9px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
    text-transform: uppercase;
}
@media (max-width: 1250px) {
    .library-categories__layout {
        grid-template-columns: 1fr;
    }
    .library-categories__intro {
        position: static;
        max-width: 720px !important;
    }
}
@media (max-width: 1100px) {
    .library-overview__layout,
    .library-categories__layout {
        grid-template-columns: 1fr;
    }
    .library-categories__intro {
        position: static;
        max-width: 720px;
    }
    .library-bookshelf {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 46px;
    }
    .library-analytics__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .library-analytics__panel:last-child {
        grid-column: 1 / -1;
    }
}
@media (max-width: 720px) {
    .library-intelligence {
        --library-section-title-size: clamp(31px, 9.2vw, 36px);
    }
    .library-intelligence__section {
        padding: 72px 20px !important;
    }
    .library-intelligence__head {
        grid-template-columns: 1fr;
        gap: 22px;
        margin-bottom: 34px !important;
    }
    .library-intelligence__head h2,
    .library-categories__intro h2 {
        font-size: clamp(36px, 12vw, 52px);
    }
    .library-metrics,
    .library-category-grid,
    .library-bookshelf,
    .library-analytics__grid {
        grid-template-columns: 1fr;
    }
    .library-metric {
        min-height: 180px;
    }
    .library-growth {
        min-height: 400px;
        padding: 28px 22px;
    }
    .library-growth__top {
        display: block;
    }
    .library-growth__badge {
        display: inline-block;
        margin-top: 15px;
    }
    .library-category {
        min-height: 196px;
    }
    .library-bookshelf {
        gap: 40px;
        padding-right: 8px;
        padding-left: 8px;
    }
    .library-book__cover {
        min-height: 410px;
    }
    .library-analytics__panel:last-child {
        grid-column: auto;
    }
    .library-language-chart {
        grid-template-columns: 125px 1fr;
        gap: 20px;
    }
    .library-donut {
        width: 125px;
        height: 125px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .homepage-hero__image,
    .homepage-hero__card,
    .homepage-hero__scroll::after,
    .library-metric,
    .library-category,
    .library-book__cover {
        animation: none;
        transition: none;
    }
}

</style>
@endsection

@section('content')
<div data-section="homepage-canonical-page">

  {{-- ── Hidden institutional identity mark (accessibility / test wiring) ── --}}
  <div id="hero-campus-mark" class="sr-only" aria-hidden="true">
    <span>{{ $copy['identity_brand'] }}</span>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       SECTION 1 — HERO
       ════════════════════════════════════════════════════════════ --}}
  <section data-section="homepage-canonical-hero">
    <img src="/images/news/campus-library.jpg"
         alt="{{ $copy['hero_img_alt'] }}"
         class="homepage-hero__image"
         fetchpriority="high">
    <div class="homepage-hero__overlay" aria-hidden="true"></div>
    <div class="homepage-hero__ambient" aria-hidden="true"></div>

    <div class="homepage-hero__content">
      <div class="homepage-hero__copy">
        <h1 class="homepage-hero__title">
          {{ $copy['hero_h1'] }}<br>
          <em>{{ $copy['hero_h1_accent'] }}</em>
        </h1>

        <p class="homepage-hero__lead">{{ $copy['hero_lead'] }}</p>

        <form id="heroSearch"
              data-test-id="homepage-canonical-search"
              class="homepage-hero__search"
              action="{{ $withLang('/catalog') }}"
              method="get">
          <svg class="ml-5" viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="20" height="20" style="flex:0 0 auto; fill:none; stroke:#627083; stroke-width:2; stroke-linecap:round; stroke-linejoin:round;">
            <circle cx="11" cy="11" r="6.5"></circle>
            <path d="M16 16l4.5 4.5"></path>
          </svg>
          <label class="sr-only" for="homepage-search">{{ $copy['search_placeholder'] }}</label>
          <input id="homepage-search"
                 type="search"
                 name="q"
                 placeholder="{{ $copy['search_placeholder'] }}">
          <button type="submit">{{ $copy['search_cta'] }}</button>
        </form>
      </div>

      <div class="homepage-hero__book" aria-hidden="true">
        <div class="homepage-hero__book-grain" aria-hidden="true"></div>
        <div class="homepage-hero__book-badge">
          <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true">
        </div>
        <div class="homepage-hero__book-title">
          {{ $lang === 'en' ? 'Kazakh University of Technology and Business' : ($lang === 'kk' ? 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті' : 'Казахский университет технологии и бизнеса имени К. Кулажанова') }}
          <small>{{ $lang === 'en' ? 'Academic library' : ($lang === 'kk' ? 'Академиялық кітапхана' : 'Академическая библиотека') }}</small>
        </div>
        <div class="homepage-hero__book-shadow" aria-hidden="true"></div>
        <div class="homepage-hero__book-ring" aria-hidden="true"></div>
      </div>

    </div>

  </section>

  <div class="homepage-hero-stats" data-section="homepage-hero-stats">
    <div class="homepage-hero-stats__inner">
      @foreach($homepageHeroStats as $stat)
        <article class="homepage-hero-stats__item" data-stat-source="{{ $stat['source'] }}">
          <span class="homepage-hero-stats__icon material-symbols-outlined" aria-hidden="true">{{ $stat['icon'] }}</span>
          <strong>{{ $stat['value'] }}</strong>
          <span>{{ $stat['label'] }}</span>
        </article>
      @endforeach
    </div>
  </div>

  @php
    $facultyBooks = $facultyBooks ?? [];
    $facultyStats = $facultyStats ?? [];
    $facultyShowcase = [
      'econ' => [
        'title' => $lang === 'ru' ? 'Экономическая библиотека' : ($lang === 'kk' ? 'Экономикалық кітапхана' : 'Economics Library'),
        'stats' => (int) ($facultyStats['econ'] ?? 0),
        'institution' => 'economic_library',
        'books' => collect($facultyBooks['econ'] ?? [])->filter(static fn (array $book): bool => (int) ($book['issueCount'] ?? 0) > 0)->values()->all(),
      ],
      'tech' => [
        'title' => $lang === 'ru' ? 'Технологическая библиотека' : ($lang === 'kk' ? 'Технологиялық кітапхана' : 'Technology Library'),
        'stats' => (int) ($facultyStats['tech'] ?? 0),
        'institution' => 'technology_library',
        'books' => collect($facultyBooks['tech'] ?? [])->filter(static fn (array $book): bool => (int) ($book['issueCount'] ?? 0) > 0)->values()->all(),
      ],
      'engit' => [
        'title' => $lang === 'ru' ? 'Библиотека колледжа' : ($lang === 'kk' ? 'Колледж кітапханасы' : 'College Library'),
        'stats' => (int) ($facultyStats['engit'] ?? 0),
        'institution' => 'college_library',
        'books' => collect($facultyBooks['engit'] ?? [])->filter(static fn (array $book): bool => (int) ($book['issueCount'] ?? 0) > 0)->values()->all(),
      ],
    ];
    $facultyShowcase = collect($facultyShowcase)
      ->filter(static fn (array $collection): bool => $collection['stats'] > 0 || $collection['books'] !== [])
      ->all();
  @endphp

  @if($facultyShowcase !== [])
  <section class="hs hs-section hs-section--ruled hs-section--wash homepage-faculty-showcase" data-section="homepage-faculty-picks">
    <header class="hs-head homepage-faculty-showcase__head">
      <div class="hs-head__copy">
        <p class="hs-kicker">{{ $lang === 'ru' ? 'Подразделения фонда' : ($lang === 'kk' ? 'Қор бөлімшелері' : 'Library collections') }}</p>
        <h2 class="hs-title">{{ $lang === 'ru' ? 'Книги по библиотечным фондам' : ($lang === 'kk' ? 'Кітапхана қорлары бойынша кітаптар' : 'Books by library collection') }}</h2>
        <p class="hs-lead">{{ $lang === 'ru' ? 'Данные сформированы по зарегистрированным экземплярам и числу выдач.' : ($lang === 'kk' ? 'Деректер тіркелген даналар мен берілім саны бойынша қалыптастырылған.' : 'The data is based on registered copies and recorded loans.') }}</p>
      </div>
      <a class="hs-link" href="{{ $withLang('/catalog') }}">
        {{ $lang === 'ru' ? 'Открыть каталог' : ($lang === 'kk' ? 'Каталогты ашу' : 'Open catalog') }}
        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
      </a>
    </header>

    <div class="homepage-faculty-showcase__grid">
      @foreach($facultyShowcase as $desk)
        <article class="homepage-faculty-showcase__desk">
          <div class="homepage-faculty-showcase__desk-top">
            <div class="homepage-faculty-showcase__desk-head">
              <h3>{{ $desk['title'] }}</h3>
            </div>
            <span class="homepage-faculty-showcase__desk-stat">
              {{ number_format($desk['stats'], 0, ',', ' ') }}
              {{ $lang === 'ru' ? 'экз.' : ($lang === 'kk' ? 'дана' : ($desk['stats'] === 1 ? 'copy' : 'copies')) }}
            </span>
          </div>
          <div class="homepage-faculty-showcase__books">
            <p class="homepage-faculty-showcase__books-label">{{ $lang === 'ru' ? 'Чаще выдаваемые книги' : ($lang === 'kk' ? 'Жиі берілетін кітаптар' : 'Most borrowed books') }}</p>
            @forelse($desk['books'] as $book)
              <a class="homepage-faculty-showcase__book-row" href="{{ $withLang('/book/'.rawurlencode($book['identifier'])) }}">
                <div
                  class="homepage-faculty-showcase__cover homepage-faculty-showcase__cover--{{ $book['tone'] }}"
                  @if(! empty($book['coverPath'])) style="background-image: url('{{ e($book['coverPath']) }}'); background-size: cover;" @endif
                  aria-hidden="true"
                >
                  @if(empty($book['coverPath']))<span>{{ mb_substr($book['title'], 0, 2) }}</span>@endif
                </div>
                <div class="homepage-faculty-showcase__book-meta">
                  <strong>{{ $book['title'] }}</strong>
                  <span>{{ $book['author'] }}</span>
                  <small>{{ $lang === 'ru' ? 'Доступно' : ($lang === 'kk' ? 'Қолжетімді' : 'Available') }}: {{ $book['copies'] }} {{ $lang === 'ru' ? 'экз.' : ($lang === 'kk' ? 'дана' : 'copies') }}</small>
                </div>
              </a>
            @empty
              <p class="homepage-faculty-showcase__empty">
                {{ $lang === 'ru' ? 'Данные о выдачах пока отсутствуют.' : ($lang === 'kk' ? 'Берілім туралы деректер әзірге жоқ.' : 'No loan data is available yet.') }}
              </p>
            @endforelse
          </div>
          <a href="{{ $withLang('/catalog', ['institution' => $desk['institution']]) }}">
            {{ $lang === 'ru' ? 'Показать все книги' : ($lang === 'kk' ? 'Барлық кітаптарды көрсету' : 'Show all books') }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
          </a>
        </article>
      @endforeach
    </div>
  </section>
  @endif

  <section class="hs homepage-usage" data-section="homepage-how-to-use-library">
    <header class="hs-head">
      <div class="hs-head__copy">
        <p class="hs-kicker">{{ $lang === 'ru' ? 'Как пользоваться библиотекой' : ($lang === 'kk' ? 'Кітапхананы қалай пайдалану керек' : 'How to use the library') }}</p>
        <h2 class="hs-title">{{ $lang === 'ru' ? 'Как пользоваться библиотекой' : ($lang === 'kk' ? 'Кітапхананы қалай пайдалану керек' : 'How to use the library') }}</h2>
        <p class="hs-lead">{{ $lang === 'ru' ? 'Получите доступ к фонду библиотеки всего за несколько простых шагов.' : ($lang === 'kk' ? 'Кітапхана қорына бірнеше қарапайым қадам арқылы қол жеткізіңіз.' : 'Get access to the library collection in just a few simple steps.') }}</p>
      </div>
    </header>

    <div class="homepage-usage__timeline" aria-label="{{ $lang === 'ru' ? 'Маршрут читателя' : ($lang === 'kk' ? 'Оқырман маршруты' : 'Reader journey') }}">
      @php
        $usageSteps = [
          [
            'icon' => 'badge',
            'title' => $lang === 'ru' ? 'Получите читательский билет' : ($lang === 'kk' ? 'Оқырман билетiн алыңыз' : 'Get a reader card'),
            'lead' => $lang === 'ru' ? 'Зарегистрируйтесь и получите электронный читательский билет.' : ($lang === 'kk' ? 'Тіркеліп, электронды оқырман билетiн алыңыз.' : 'Register and receive your digital reader card.'),
          ],
          [
            'icon' => 'search',
            'title' => $lang === 'ru' ? 'Найдите литературу' : ($lang === 'kk' ? 'Әдебиетті табыңыз' : 'Find materials'),
            'lead' => $lang === 'ru' ? 'Используйте каталог для поиска книг, журналов и научных публикаций.' : ($lang === 'kk' ? 'Каталог арқылы кітаптарды, журналдарды және ғылыми жарияланымдарды іздеңіз.' : 'Use the catalog to search for books, journals, and scholarly publications.'),
          ],
          [
            'icon' => 'menu_book',
            'title' => $lang === 'ru' ? 'Забронируйте книгу' : ($lang === 'kk' ? 'Кітапты брондаңыз' : 'Reserve a book'),
            'lead' => $lang === 'ru' ? 'При необходимости оформите предварительное бронирование.' : ($lang === 'kk' ? 'Қажет болса, алдын ала брондау рәсімін жасаңыз.' : 'If needed, place a reservation in advance.'),
          ],
          [
            'icon' => 'local_library',
            'title' => $lang === 'ru' ? 'Получите книгу' : ($lang === 'kk' ? 'Кітапты алыңыз' : 'Pick up the book'),
            'lead' => $lang === 'ru' ? 'Получите литературу в соответствующем абонементе или читальном зале.' : ($lang === 'kk' ? 'Әдебиетті тиісті абонементтен немесе оқу залынан алыңыз.' : 'Collect the material from the relevant desk or reading room.'),
          ],
          [
            'icon' => 'event_repeat',
            'title' => $lang === 'ru' ? 'Проверьте срок возврата' : ($lang === 'kk' ? 'Қайтару мерзімін тексеріңіз' : 'Check the due date'),
            'lead' => $lang === 'ru' ? 'Срок возврата и доступные действия указаны в личном кабинете.' : ($lang === 'kk' ? 'Қайтару мерзімі мен қолжетімді әрекеттер жеке кабинетте көрсетіледі.' : 'The due date and available actions are shown in your account.'),
          ],
          [
            'icon' => 'undo',
            'title' => $lang === 'ru' ? 'Верните книгу' : ($lang === 'kk' ? 'Кітапты қайтарыңыз' : 'Return the book'),
            'lead' => $lang === 'ru' ? 'Верните литературу в библиотеку в установленный срок.' : ($lang === 'kk' ? 'Әдебиетті белгіленген мерзімде кітапханаға қайтарыңыз.' : 'Return the material to the library by the due date.'),
          ],
        ];
      @endphp
      @foreach ($usageSteps as $step)
        <article class="homepage-usage__step">
          <div class="homepage-usage__step-icon" aria-hidden="true">
            <span class="material-symbols-outlined">{{ $step['icon'] }}</span>
          </div>
          <div class="homepage-usage__step-body">
            <span class="homepage-usage__step-label">{{ $lang === 'ru' ? 'Шаг' : ($lang === 'kk' ? 'Қадам' : 'Step') }} {{ $loop->iteration }}</span>
            <h3>{{ $step['title'] }}</h3>
            <p>{{ $step['lead'] }}</p>
          </div>
        </article>
      @endforeach
    </div>

    <aside class="homepage-usage__info" aria-label="{{ $lang === 'ru' ? 'Важно знать' : ($lang === 'kk' ? 'Білу маңызды' : 'Important to know') }}">
      <h3>{{ $lang === 'ru' ? 'Важно знать' : ($lang === 'kk' ? 'Білу маңызды' : 'Important to know') }}</h3>
      <p>{{ $lang === 'ru'
          ? 'Актуальные сроки выдачи и условия бронирования отображаются в личном кабинете. Условия внешних электронных ресурсов указаны в их карточках.'
          : ($lang === 'kk'
              ? 'Беру мерзімдері мен брондау шарттары жеке кабинетте көрсетіледі. Сыртқы электрондық ресурстардың шарттары олардың карточкаларында берілген.'
              : 'Current loan periods and reservation terms are shown in the reader account. Conditions for external electronic resources are stated on each resource card.') }}</p>
    </aside>

    <style>
      .homepage-usage {
        padding: 132px var(--homepage-gutter) 150px;
        background: #fff;
        border-top: 1px solid rgba(16, 41, 69, .10);
      }
      .homepage-usage__timeline {
        position: relative;
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0;
        margin-top: 20px;
        padding-top: 18px;
      }
      .homepage-usage__timeline::before {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        top: 54px;
        height: 1px;
        background: rgba(16, 41, 69, .14);
      }
      .homepage-usage__step {
        position: relative;
        padding: 0 14px 0 0;
      }
      .homepage-usage__step:not(:last-child)::after {
        content: "";
        position: absolute;
        top: 54px;
        right: -1px;
        width: 14px;
        height: 1px;
        background: rgba(16, 41, 69, .14);
      }
      .homepage-usage__step-icon {
        position: relative;
        z-index: 1;
        width: 54px;
        height: 54px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(16, 41, 69, .14);
        border-radius: 50%;
        background: #fff;
        color: #102945;
        transition: color .2s ease, border-color .2s ease, background-color .2s ease;
      }
      .homepage-usage__step-icon .material-symbols-outlined {
        font-size: 24px;
      }
      .homepage-usage__step-body {
        position: relative;
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid rgba(16, 41, 69, .10);
        transition: border-top-color .2s ease, background-color .2s ease;
      }
      .homepage-usage__step-label {
        display: block;
        margin-bottom: 8px;
        color: rgba(16, 41, 69, .58);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
      }
      .homepage-usage__step h3 {
        margin: 0 0 10px;
        color: #102945;
        font-family: "Literata", serif;
        font-size: 20px;
        line-height: 1.12;
        letter-spacing: -.03em;
      }
      .homepage-usage__step p {
        margin: 0;
        color: rgba(16, 41, 69, .70);
        font-size: 14px;
        line-height: 1.65;
      }
      .homepage-usage__step:hover .homepage-usage__step-icon {
        color: #09bab2;
        border-color: rgba(9, 186, 178, .45);
        background-color: rgba(9, 186, 178, .03);
      }
      .homepage-usage__step:hover .homepage-usage__step-body {
        background: rgba(9, 186, 178, .025);
        border-top-color: #09bab2;
      }
      .homepage-usage__info {
        margin-top: 34px;
        padding: 22px 24px;
        border: 1px solid rgba(16, 41, 69, .10);
        background: #fff;
      }
      .homepage-usage__info h3 {
        margin: 0 0 14px;
        color: #102945;
        font-family: "Literata", serif;
        font-size: 18px;
        line-height: 1.1;
      }
      .homepage-usage__info ul {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 22px;
        margin: 0;
        padding: 0;
        list-style: none;
      }
      .homepage-usage__info li {
        position: relative;
        padding-left: 16px;
        color: rgba(16, 41, 69, .72);
        font-size: 14px;
        line-height: 1.6;
      }
  .homepage-usage__info li::before {
        content: "";
        position: absolute;
        left: 0;
        top: .72em;
        width: 6px;
        height: 6px;
        background: #09bab2;
      }
      /* These sections used to be nested inside the usage section, which had
         no closing tag, so they inherited its content box and had to be
         forced back to 100vw with !important. The tag is closed now and they
         are ordinary .hs-section children of the page root, so only the rail
         bleed — a deliberate effect — is kept, written against classes so the
         rules no longer read as section markers. */
      .hs-rail {
        width: 100vw;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
      }
      .hs-rail__track {
        padding-inline: 0;
        grid-auto-columns: clamp(190px, 18vw, 250px);
        gap: 16px;
      }
      .homepage-usage {
        border: 0;
      }
      @media (max-width: 1200px) {
        .homepage-usage__timeline {
          grid-template-columns: repeat(3, minmax(0, 1fr));
          row-gap: 28px;
        }
        .homepage-usage__timeline::before {
          top: 54px;
        }
      }
      @media (max-width: 760px) {
        .homepage-usage {
          padding: 96px 24px 108px;
        }
        .homepage-usage__timeline {
          grid-template-columns: 1fr;
          row-gap: 0;
        }
        .homepage-usage__timeline::before,
        .homepage-usage__step:not(:last-child)::after {
          display: none;
        }
        .homepage-usage__step {
          padding: 0 0 18px;
          margin-bottom: 18px;
        }
        .homepage-usage__step + .homepage-usage__step {
          border-top: 1px solid rgba(16, 41, 69, .10);
          padding-top: 18px;
        }
        .homepage-usage__info ul {
          grid-template-columns: 1fr;
        }
      }
    </style>

  <style>
  .homepage-faculty-showcase {
    border: 0 !important;
    box-shadow: none !important;
  }
  .homepage-faculty-showcase__head {
    padding-top: clamp(14px, 2vw, 24px);
    padding-bottom: clamp(14px, 2vw, 24px);
    margin-bottom: clamp(28px, 3.4vw, 42px);
    border-bottom: 0;
  }
  .homepage-faculty-showcase__grid {
    display: flex;
    gap: 18px;
    align-items: stretch;
    flex-wrap: wrap;
  }
  .homepage-faculty-showcase__desk {
    position: relative;
    flex: 1 1 280px;
    min-width: 0;
    padding: 22px 22px 20px;
    border: none;
    background: linear-gradient(180deg, #fff 0%, #fcfbf8 100%);
    box-shadow: none;
    overflow: hidden;
    transition: background-color .22s ease, border-color .22s ease, box-shadow .22s ease;
  }
  .homepage-faculty-showcase__desk::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 2px;
    background: transparent;
    transition: background-color .22s ease;
  }
  .homepage-faculty-showcase__desk::after {
    content: "";
    position: absolute;
    inset: auto 18px 18px auto;
    width: 92px;
    height: 92px;
    background: radial-gradient(circle at center, rgba(9, 186, 178, .07), transparent 68%);
    opacity: .7;
  }
  .homepage-faculty-showcase__desk-top {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
    align-items: flex-start;
  }
  .homepage-faculty-showcase__desk-head {
    min-width: 0;
  }
  .homepage-faculty-showcase__desk-top h3 {
    position: relative;
    z-index: 1;
    margin: 0 0 6px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 22px;
    line-height: 1.08;
    letter-spacing: -.03em;
  }
  .homepage-faculty-showcase__desk-meta {
    display: block;
    color: rgba(16, 41, 69, .62);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
  }
  .homepage-faculty-showcase__desk-stat {
    flex: 0 0 auto;
    padding: 8px 10px;
    border: 1px solid rgba(9, 186, 178, .18);
    border-radius: 999px;
    color: #0f403f;
    background: rgba(9, 186, 178, .06);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .02em;
    white-space: nowrap;
  }
  .homepage-faculty-showcase__note {
    position: relative;
    z-index: 1;
    margin: 0;
    color: rgba(16, 41, 69, .72);
    font-size: 14px;
    line-height: 1.65;
  }
  .homepage-faculty-showcase__books {
    position: relative;
    z-index: 1;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid rgba(9, 186, 178, .14);
  }
  .homepage-faculty-showcase__books-label {
    margin: 0 0 12px;
    color: #0f403f;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
  }
  .homepage-faculty-showcase__book-row {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 12px;
    padding: 10px 0;
  }
  .homepage-faculty-showcase__desk a.homepage-faculty-showcase__book-row {
    display: grid;
    align-items: center;
    margin-top: 0;
    color: inherit;
    font-size: inherit;
    font-weight: inherit;
    letter-spacing: normal;
    text-transform: none;
    text-decoration: none;
  }
  .homepage-faculty-showcase__empty {
    margin: 0;
    color: rgba(16, 41, 69, .58);
    font-size: 13px;
  }
  .homepage-faculty-showcase__book-row + .homepage-faculty-showcase__book-row {
    border-top: 1px solid rgba(16, 41, 69, .08);
  }
  .homepage-faculty-showcase__cover {
    width: 56px;
    height: 82px;
    border-radius: 6px;
    display: flex;
    align-items: flex-end;
    justify-content: flex-start;
    padding: 8px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: -.04em;
    box-shadow: 0 10px 18px rgba(11, 24, 48, .14);
  }
  .homepage-faculty-showcase__cover--ink { background: linear-gradient(180deg, #102945, #284866); }
  .homepage-faculty-showcase__cover--forest { background: linear-gradient(180deg, #315646, #466f59); }
  .homepage-faculty-showcase__cover--sage { background: linear-gradient(180deg, #68836e, #93a78f); }
  .homepage-faculty-showcase__cover--clay { background: linear-gradient(180deg, #8f6a52, #af866a); }
  .homepage-faculty-showcase__cover--sand { background: linear-gradient(180deg, #b38b4d, #d2b277); }
  .homepage-faculty-showcase__cover span {
    display: block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .homepage-faculty-showcase__book-meta {
    min-width: 0;
  }
  .homepage-faculty-showcase__book-meta strong {
    display: block;
    color: #102945;
    font-size: 14px;
    line-height: 1.25;
  }
  .homepage-faculty-showcase__book-meta span,
  .homepage-faculty-showcase__book-meta small {
    display: block;
    margin-top: 3px;
    color: rgba(16, 41, 69, .70);
    font-size: 12px;
    line-height: 1.35;
  }
  .homepage-faculty-showcase__book-meta small {
    color: rgba(16, 41, 69, .58);
  }
  .homepage-faculty-showcase__desk a {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 22px;
    color: #0f403f;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
  }
  .homepage-faculty-showcase__desk:hover {
    background: linear-gradient(180deg, #fff 0%, #f8fcfb 100%);
    border-color: rgba(9, 186, 178, .24);
    box-shadow: inset 0 2px 0 #09bab2;
  }
  .homepage-hero-stats {
    position: absolute;
    left: 50%;
    top: calc(70svh - 66px);
    z-index: 4;
    width: min(calc(100vw - (var(--homepage-gutter) * 2)), 1200px);
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    pointer-events: none;
    transform: translateX(-50%);
  }
  .homepage-hero-stats__inner {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    min-height: 138px;
    background: #fff;
    border: 1px solid #e3e6e5;
  }
  .homepage-hero-stats__item {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    min-width: 0;
    padding: 22px 24px;
    border-right: 1px solid #e3e6e5;
    transition: border-top-color .2s ease, color .2s ease, background-color .2s ease;
  }
  .homepage-hero-stats__item:last-child {
    border-right: 0;
  }
  .homepage-hero-stats__item::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 2px;
    background: transparent;
    transition: background-color .2s ease;
  }
  .homepage-hero-stats__icon {
    color: #09bab2;
    font-size: 22px;
    width: fit-content;
    transition: transform .2s ease, color .2s ease;
  }
  .homepage-hero-stats__item strong {
    color: #102945;
    font-family: "Literata", serif;
    font-size: 41px;
    line-height: .96;
    font-weight: 700;
    letter-spacing: -.04em;
    text-wrap: balance;
  }
  .homepage-hero-stats__item span:last-child {
    color: #5c6866;
    font-family: "Google Sans", sans-serif;
    font-size: 14px;
    line-height: 1.45;
  }
  .homepage-hero-stats__item:hover {
    background: rgba(9, 186, 178, .025);
  }
  .homepage-hero-stats__item:hover::before {
    background: #09bab2;
  }
  .homepage-hero-stats__item:hover strong {
    color: #09bab2;
  }
  .homepage-hero-stats__item:hover .homepage-hero-stats__icon {
    transform: scale(1.08);
  }
  [data-section="homepage-faculty-picks"] {
    margin-top: -72px;
    padding-top: 158px !important;
    background: var(--hs-wash);
    position: relative;
    z-index: 3;
  }
  @media (max-width: 1024px) {
    .homepage-hero-stats__inner {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 640px) {
    .homepage-hero-stats {
      top: calc(70svh - 42px);
      width: calc(100vw - 24px);
    }
    [data-section="homepage-faculty-picks"] {
      margin-top: -48px;
      padding-top: 126px !important;
      background: var(--hs-wash);
      z-index: 3;
    }
    .homepage-hero-stats__inner {
      grid-template-columns: 1fr;
    }
    .homepage-hero-stats__item {
      border-right: 0;
      border-bottom: 1px solid #e3e6e5;
    }
    .homepage-hero-stats__item:last-child {
      border-bottom: 0;
    }
  }
  @media (max-width: 640px) {
    .homepage-faculty-showcase__desk h3 { font-size: 20px; }
    .homepage-faculty-showcase__book-row {
      grid-template-columns: auto minmax(0, 1fr);
    }
    .homepage-faculty-showcase__cover {
      width: 50px;
      height: 74px;
    }
  }
  @media (max-width: 1200px) {
    .homepage-hero__content {
      grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
      gap: clamp(20px, 3vw, 34px);
      padding-top: clamp(96px, 10vh, 124px);
      padding-bottom: clamp(28px, 4.5vh, 42px);
    }
    .homepage-hero__title {
      font-size: clamp(32px, 3.6vw, 52px);
    }
    .homepage-hero__book {
      width: min(100%, 300px);
    }
    .homepage-hero-stats {
      width: min(calc(100vw - (var(--homepage-gutter) * 2)), 1140px);
    }
    .homepage-faculty-showcase__grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .homepage-faculty-showcase__desk {
      flex: none;
    }
  }
  @media (max-width: 1024px) {
    .homepage-hero__content {
      grid-template-columns: minmax(0, 1.15fr) minmax(180px, .65fr);
      gap: 20px;
      padding-top: 118px;
    }
    .homepage-hero__copy {
      padding-right: 0;
    }
    .homepage-hero__title {
      font-size: clamp(30px, 4.5vw, 44px);
    }
    .homepage-hero__lead {
      max-width: 44ch;
    }
    .homepage-hero__search {
      width: 100%;
    }
    .homepage-hero__book {
      width: min(100%, 260px);
    }
    .homepage-hero-stats__inner {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .homepage-hero-stats {
      top: calc(70svh - 44px);
    }
    [data-section="homepage-faculty-picks"] {
      margin-top: -56px;
      padding-top: 140px !important;
    }
    .homepage-faculty-showcase__grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .homepage-usage {
      padding: 88px var(--homepage-gutter) 104px;
    }
    .homepage-usage__timeline {
      grid-template-columns: repeat(3, minmax(0, 1fr));
      row-gap: 28px;
    }
    .homepage-usage__timeline::before {
      top: 54px;
    }
  }
  @media (max-width: 768px) {
    .homepage-hero__content {
      grid-template-columns: 1fr;
      padding-top: 104px;
      padding-bottom: 36px;
      gap: 22px;
    }
    .homepage-hero__copy {
      order: 1;
      max-width: 100%;
      padding: 0;
    }
    .homepage-hero__search {
      width: 100%;
      max-width: none;
    }
    .homepage-hero__book {
      order: 3;
      justify-self: center;
      width: min(100%, 260px);
    }
    .homepage-hero__title {
      font-size: clamp(30px, 8vw, 42px);
    }
    .homepage-hero__lead {
      max-width: none;
      font-size: clamp(15px, 2.2vw, 17px);
    }
    .homepage-hero-stats {
      position: absolute;
      top: calc(70svh - 38px);
      width: calc(100vw - 24px);
    }
    .homepage-hero-stats__inner {
      grid-template-columns: 1fr;
    }
    .homepage-faculty-showcase__grid {
      grid-template-columns: 1fr;
    }
    [data-section="homepage-faculty-picks"] {
      margin-top: -44px;
      padding-top: 128px !important;
    }
    .homepage-usage {
      padding: 72px 20px 88px;
    }
    .homepage-usage__timeline {
      grid-template-columns: 1fr;
      row-gap: 0;
    }
    .homepage-usage__timeline::before,
    .homepage-usage__step:not(:last-child)::after {
      display: none;
    }
    .homepage-usage__step {
      padding: 0 0 18px;
      margin-bottom: 18px;
    }
    .homepage-usage__step + .homepage-usage__step {
      border-top: 1px solid rgba(16, 41, 69, .10);
      padding-top: 18px;
    }
    .homepage-usage__info ul {
      grid-template-columns: 1fr;
    }
  }
  @media (max-width: 576px) {
    .homepage-hero__content {
      padding-top: 88px;
      padding-bottom: 30px;
    }
    .homepage-hero__book {
      width: min(100%, 240px);
    }
    .homepage-hero-stats {
      width: calc(100vw - 20px);
    }
    [data-section="homepage-faculty-picks"] {
      padding-top: 120px !important;
    }
  }
  @media (max-width: 480px) {
    .homepage-hero__content {
      padding-top: 80px;
      padding-bottom: 28px;
    }
    .homepage-hero__search {
      flex-direction: column;
      align-items: stretch;
    }
    .homepage-hero__search svg {
      display: none;
    }
    .homepage-hero__search input {
      padding: 16px 18px;
    }
    .homepage-hero__search button {
      width: 100%;
      min-height: 52px;
    }
    .homepage-hero__book {
      width: min(100%, 230px);
    }
  }
  @media (max-width: 390px) {
    .homepage-hero__title {
      font-size: clamp(28px, 11vw, 38px);
    }
    .homepage-hero-stats {
      width: calc(100vw - 16px);
    }
  }
  </style>

  <script>
    (() => {
      const buildInfiniteArrivalsRail = () => {
        const track = document.getElementById('hs-arrivals-rail');
        if (!track) return;

        const baseCount = Math.floor(track.children.length / 2);
        if (!baseCount) return;

        const sourceItems = Array.from(track.children).slice(0, baseCount);
        const baseItems = sourceItems.map((node) => node.cloneNode(true));
        const viewportWidth = Math.max(window.innerWidth || 0, document.documentElement?.clientWidth || 0);
        const targetWidth = viewportWidth * 3.5;
        const gap = 20;
        const cycleWidth = sourceItems.reduce((sum, item) => {
          const width = item.getBoundingClientRect().width || item.offsetWidth || 0;
          return sum + width;
        }, 0) + (gap * Math.max(sourceItems.length - 1, 0));

        track.innerHTML = '';

        let cycleCount = 0;
        let totalWidth = 0;
        while (totalWidth < targetWidth || cycleCount < 2) {
          baseItems.forEach((item, index) => {
            const clone = item.cloneNode(true);
            if (cycleCount > 0 || index > 0) {
              clone.setAttribute('aria-hidden', 'true');
              clone.tabIndex = -1;
            }
            track.appendChild(clone);
          });

          cycleCount += 1;
          totalWidth = cycleWidth * cycleCount;
          if (cycleCount > 12) break;
        }

        const duration = Math.max(24, Math.min(72, totalWidth / 90));
        track.style.animationDuration = `${duration}s`;
      };

      let resizeTimer = null;
      const scheduleBuild = () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(buildInfiniteArrivalsRail, 120);
      };

      const init = () => {
        buildInfiniteArrivalsRail();
        window.addEventListener('resize', scheduleBuild, { passive: true });
      };

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
      } else {
        init();
      }
    })();
  </script>
  </section>{{-- /homepage-how-to-use-library --}}
  @include('home.repository')
  @include('home.news')
  @include('home.new-arrivals')
  @include('home.collections')
  @include('home.faq')
</div>{{-- /homepage-canonical-page --}}
@endsection
