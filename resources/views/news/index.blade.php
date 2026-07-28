{{-- resources/views/news/index.blade.php --}}
{{-- Phase 3.f: news index canonical-exact rebuild per docs/design-exports/news_index_canonical --}}
@extends('layouts.public')

@php
  $lang = request()->query('lang', 'ru');
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  $chrome = [
      'ru' => [
          'title'           => 'Новости и анонсы — KazUTB Smart Library',
          'eyebrow'         => 'Институциональные обновления',
          'heading'         => 'Библиотечный вестник',
          'lead'            => 'Последние объявления, открытия в архивах и научные инициативы KazUTB Smart Library.',
          'featured_read'   => 'Читать полностью',
          'grid_heading'    => 'Последние статьи',
          'filter_all'      => 'Все темы',
          'filter_events'   => 'Мероприятия',
          'filter_research' => 'Исследования',
          'bento_eyebrow'   => 'Мероприятия',
          'bento_heading'   => 'Открытые лекции, презентации и академические конференции',
          'bento_body'      => 'Узнайте о ближайших событиях, симпозиумах и публичных программах KazUTB Smart Library.',
          'bento_cta'       => 'Смотреть мероприятия',
          'load_more'       => 'Загрузить ещё',
            'page_prev'       => 'Назад',
            'page_next'       => 'Вперёд',
      ],
      'kk' => [
          'title'           => 'Жаңалықтар мен хабарландырулар — KazUTB Smart Library',
          'eyebrow'         => 'Институционалдық жаңартулар',
          'heading'         => 'Кітапхана хабаршысы',
          'lead'            => 'KazUTB Smart Library соңғы хабарландырулары, мұрағат жаңалықтары және ғылыми бастамалары.',
          'featured_read'   => 'Толығырақ оқу',
          'grid_heading'    => 'Соңғы мақалалар',
          'filter_all'      => 'Барлық тақырыптар',
          'filter_events'   => 'Іс-шаралар',
          'filter_research' => 'Зерттеулер',
          'bento_eyebrow'   => 'Іс-шаралар',
          'bento_heading'   => 'Ашық лекциялар, презентациялар және академиялық конференциялар',
          'bento_body'      => 'KazUTB Smart Library алдағы іс-шаралары, симпозиумдар және ашық бағдарламалар туралы біліңіз.',
          'bento_cta'       => 'Барлық іс-шараларды қарау',
          'load_more'       => 'Тағы жүктеу',
            'page_prev'       => 'Артқа',
            'page_next'       => 'Алға',
      ],
      'en' => [
          'title'           => 'News and announcements — KazUTB Smart Library',
          'eyebrow'         => 'Institutional Updates',
          'heading'         => 'Library Dispatch',
          'lead'            => 'The latest announcements, archival discoveries, and scholarly initiatives from the KazUTB Smart Library.',
          'featured_read'   => 'Read full dispatch',
          'grid_heading'    => 'Recent Articles',
          'filter_all'      => 'All Topics',
          'filter_events'   => 'Events',
          'filter_research' => 'Research',
          'bento_eyebrow'   => 'Library Events',
          'bento_heading'   => 'Open lectures, collection showcases, and academic symposia',
          'bento_body'      => 'Discover upcoming events, symposia, and public programmes at KazUTB Smart Library.',
          'bento_cta'       => 'View all events',
          'load_more'       => 'Load More Dispatches',
            'page_prev'       => 'Previous',
            'page_next'       => 'Next',
      ],
  ][$lang];

  $featured = null;
  $rest = [];
  if ($showCanonicalHero) {
    foreach ($newsArticles as $article) {
      if ($featured === null && ! empty($article['featured'])) {
          $featured = $article;
          continue;
        }
        $rest[] = $article;
      }

      if ($featured === null && ! empty($newsArticles)) {
        $featured = $newsArticles[0];
        $rest = array_slice($newsArticles, 1);
      }
    } else {
      $rest = $newsArticles;
  }

    $pageItems = [];
    if ($lastPage <= 7) {
      for ($i = 1; $i <= $lastPage; $i++) {
        $pageItems[] = $i;
      }
    } else {
      $pageItems[] = 1;
      $start = max(2, $currentPage - 1);
      $end = min($lastPage - 1, $currentPage + 1);

      if ($start > 2) {
        $pageItems[] = '...';
      }

      for ($i = $start; $i <= $end; $i++) {
        $pageItems[] = $i;
      }

      if ($end < $lastPage - 1) {
        $pageItems[] = '...';
      }

      $pageItems[] = $lastPage;
  }

  $leadCards = array_slice($rest, 0, 3);
  $tailCards = array_slice($rest, 3);
@endphp

@section('title', $chrome['title'])

@section('content')
<div class="news-canonical" data-section="news-canonical-page">

  {{-- ① Page Header --}}
  <header class="news-canonical__header" data-test-id="news-canonical-header">
    <p class="news-canonical__eyebrow">{{ $chrome['eyebrow'] }}</p>
    <h1 class="news-canonical__display">{{ $chrome['heading'] }}</h1>
    <p class="news-canonical__lead">{{ $chrome['lead'] }}</p>
  </header>

  {{-- ② Featured Lead Story (canonical block only on the first page) --}}
  @if($showCanonicalHero && $featured)
  <section class="news-canonical__featured" data-section="news-canonical-featured" data-test-id="news-canonical-featured">
    <a href="{{ $routeWithLang('/news/' . $featured['slug']) }}" class="news-canonical__featured-card">
      <div class="news-canonical__featured-image">
        @if(! empty($featured['hero']['image']))
          <img src="{{ asset($featured['hero']['image']) }}"
               alt="{{ $featured['hero']['alt'][$lang] ?? '' }}"
               loading="lazy" />
        @endif
      </div>
      <div class="news-canonical__featured-copy">
        <div class="news-canonical__featured-meta">
          <span class="news-canonical__featured-tag">{{ $featured['category'][$lang] }}</span>
          <span class="news-canonical__featured-date">{{ $featured['published_display'][$lang] }}</span>
        </div>
        <h2 class="news-canonical__featured-title">{{ $featured['title'][$lang] }}</h2>
        <p class="news-canonical__featured-excerpt">{{ $featured['excerpt'][$lang] }}</p>
        <span class="news-canonical__featured-link">
          {{ $chrome['featured_read'] }}
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </div>
    </a>
  </section>
  @endif

  {{-- ③ Articles Grid --}}
  <div data-section="news-canonical-grid">

    {{-- Filter bar / section header --}}
    <div class="news-canonical__grid-bar" data-test-id="news-canonical-filter">
      <h3 class="news-canonical__grid-heading">{{ $chrome['grid_heading'] }}</h3>
      <div class="news-canonical__filter-tabs" aria-label="{{ $chrome['filter_all'] }}">
        <button class="news-canonical__filter-tab news-canonical__filter-tab--active" type="button">{{ $chrome['filter_all'] }}</button>
        <button class="news-canonical__filter-tab" type="button">{{ $chrome['filter_events'] }}</button>
        <button class="news-canonical__filter-tab" type="button">{{ $chrome['filter_research'] }}</button>
      </div>
    </div>

    <div class="news-canonical__grid">

      @if($showCanonicalHero)
      {{-- Canonical first row: exactly the first three article cards --}}
      @foreach($leadCards as $article)
        <article class="news-canonical__card" data-test-id="news-canonical-article">
          <a href="{{ $routeWithLang('/news/' . $article['slug']) }}" class="news-canonical__card-link">
            <div class="news-canonical__card-image">
              @if(! empty($article['hero']['image']))
                <img src="{{ asset($article['hero']['image']) }}"
                     alt="{{ $article['hero']['alt'][$lang] ?? '' }}"
                     loading="lazy" />
              @endif
            </div>
            <div class="news-canonical__card-meta">
              <span class="news-canonical__card-category">{{ $article['category'][$lang] }}</span>
              <span class="news-canonical__card-dot" aria-hidden="true"></span>
              <span class="news-canonical__card-date">{{ $article['published_display'][$lang] }}</span>
            </div>
            <h4 class="news-canonical__card-title">{{ $article['title'][$lang] }}</h4>
            <p class="news-canonical__card-excerpt">{{ $article['excerpt'][$lang] }}</p>
          </a>
        </article>
      @endforeach

      {{-- Canonical second row left: events CTA bento spans two columns --}}
      <article class="news-canonical__bento" data-test-id="news-canonical-bento">
        <div>
          <div class="news-canonical__bento-eyebrow">
            <svg class="news-canonical__bento-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <rect x="1" y="3" width="14" height="12" rx="1.5" stroke="currentColor" stroke-width="1.4"/>
              <path d="M1 7h14M5 1v4M11 1v4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <span class="news-canonical__bento-label">{{ $chrome['bento_eyebrow'] }}</span>
          </div>
          <h4 class="news-canonical__bento-heading">{{ $chrome['bento_heading'] }}</h4>
          <p class="news-canonical__bento-body">{{ $chrome['bento_body'] }}</p>
        </div>
        <div class="news-canonical__bento-footer">
          <a href="{{ $routeWithLang('/events') }}" class="news-canonical__bento-cta">{{ $chrome['bento_cta'] }}</a>
        </div>
      </article>

      {{-- Canonical second row right: remaining cards continue after the bento --}}
      @foreach($tailCards as $article)
        <article class="news-canonical__card" data-test-id="news-canonical-article">
          <a href="{{ $routeWithLang('/news/' . $article['slug']) }}" class="news-canonical__card-link">
            <div class="news-canonical__card-image">
              @if(! empty($article['hero']['image']))
                <img src="{{ asset($article['hero']['image']) }}"
                     alt="{{ $article['hero']['alt'][$lang] ?? '' }}"
                     loading="lazy" />
              @endif
            </div>
            <div class="news-canonical__card-meta">
              <span class="news-canonical__card-category">{{ $article['category'][$lang] }}</span>
              <span class="news-canonical__card-dot" aria-hidden="true"></span>
              <span class="news-canonical__card-date">{{ $article['published_display'][$lang] }}</span>
            </div>
            <h4 class="news-canonical__card-title">{{ $article['title'][$lang] }}</h4>
            <p class="news-canonical__card-excerpt">{{ $article['excerpt'][$lang] }}</p>
          </a>
        </article>
      @endforeach
      @else
      @foreach($rest as $article)
        <article class="news-canonical__card" data-test-id="news-canonical-article">
          <a href="{{ $routeWithLang('/news/' . $article['slug']) }}" class="news-canonical__card-link">
            <div class="news-canonical__card-image">
              @if(! empty($article['hero']['image']))
                <img src="{{ asset($article['hero']['image']) }}"
                     alt="{{ $article['hero']['alt'][$lang] ?? '' }}"
                     loading="lazy" />
              @endif
            </div>
            <div class="news-canonical__card-meta">
              <span class="news-canonical__card-category">{{ $article['category'][$lang] }}</span>
              <span class="news-canonical__card-dot" aria-hidden="true"></span>
              <span class="news-canonical__card-date">{{ $article['published_display'][$lang] }}</span>
            </div>
            <h4 class="news-canonical__card-title">{{ $article['title'][$lang] }}</h4>
            <p class="news-canonical__card-excerpt">{{ $article['excerpt'][$lang] }}</p>
          </a>
        </article>
      @endforeach
      @endif

    </div>
  </div>

  {{-- ④ Load More --}}
  @if($currentPage < $lastPage)
    <div class="news-canonical__load-more">
      <a class="news-canonical__load-more-btn" href="{{ $routeWithLang('/news', ['page' => $currentPage + 1]) }}">{{ $chrome['load_more'] }}</a>
    </div>
  @else
    <div class="news-canonical__load-more">
      <span class="news-canonical__load-more-btn news-canonical__load-more-btn--disabled" aria-disabled="true">{{ $chrome['load_more'] }}</span>
    </div>
  @endif

</div>
@endsection

@section('head')
<style>
  /* ============================================================
     news-canonical — Phase 3.f
     Scoped to /news index only.
     Canonical source: docs/design-exports/news_index_canonical/code.html
     ============================================================ */

  .news-canonical {
    padding: 48px 24px 96px;
    max-width: 1280px;
    margin: 0 auto;
  }

  /* ── Page Header ─────────────────────────────────────────── */

  .news-canonical__header {
    margin-bottom: 64px;
    max-width: 720px;
  }

  .news-canonical__eyebrow {
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: #006a6a;
    margin: 0 0 16px;
  }

  .news-canonical__display {
    font-family: 'Newsreader', Georgia, serif;
    font-size: clamp(2.5rem, 5vw, 3.75rem);
    font-weight: 300;
    line-height: 1.05;
    letter-spacing: -.03em;
    color: #000613;
    margin: 0 0 24px;
  }

  .news-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 1.125rem;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
    max-width: 64ch;
  }

  /* ── Featured Lead Story ─────────────────────────────────── */

  .news-canonical__featured {
    margin-bottom: 96px;
  }

  .news-canonical__featured-card {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    transition: background .5s ease;
  }
  .news-canonical__featured-card:hover { background: #e7e8e9; }

  .news-canonical__featured-image {
    width: 100%;
    height: 260px;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
  }
  .news-canonical__featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .7s ease-out;
  }
  .news-canonical__featured-card:hover .news-canonical__featured-image img {
    transform: scale(1.05);
  }

  .news-canonical__featured-copy {
    padding: 32px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .news-canonical__featured-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
  }

  .news-canonical__featured-tag {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 4px;
    background: #e1e3e4;
    color: #000613;
    font-family: 'Manrope', sans-serif;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
  }

  .news-canonical__featured-date {
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    color: #43474e;
  }

  .news-canonical__featured-title {
    font-family: 'Newsreader', Georgia, serif;
    font-size: clamp(1.5rem, 2.5vw, 2rem);
    font-weight: 400;
    line-height: 1.2;
    color: #000613;
    margin: 0 0 16px;
  }

  .news-canonical__featured-excerpt {
    font-family: 'Manrope', sans-serif;
    font-size: .9375rem;
    line-height: 1.65;
    color: #43474e;
    margin: 0 0 32px;
    max-width: 56ch;
  }

  .news-canonical__featured-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    font-weight: 700;
    transition: color .3s;
  }
  .news-canonical__featured-card:hover .news-canonical__featured-link { color: #004f4f; }

  /* ── Articles Grid ────────────────────────────────────────── */

  .news-canonical__grid-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(196,198,207,.25);
    padding-bottom: 16px;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 12px;
  }

  .news-canonical__grid-heading {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.5rem;
    font-weight: 400;
    color: #000613;
    margin: 0;
  }

  .news-canonical__filter-tabs {
    display: flex;
    gap: 16px;
    min-width: 0;
    flex-wrap: wrap;
  }

  .news-canonical__filter-tab {
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0 0 4px;
    cursor: pointer;
    color: #43474e;
    transition: color .25s;
    white-space: nowrap;
  }
  .news-canonical__filter-tab:hover { color: #000613; }
  .news-canonical__filter-tab--active {
    color: #006a6a;
    border-bottom-color: #006a6a;
  }

  .news-canonical__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
  }

  /* ── Article Card ─────────────────────────────────────────── */

  .news-canonical__card { cursor: pointer; }

  .news-canonical__card-link {
    display: block;
    text-decoration: none;
    color: inherit;
  }

  .news-canonical__card-image {
    width: 100%;
    height: 192px;
    border-radius: 8px;
    overflow: hidden;
    background: #f3f4f5;
    margin-bottom: 24px;
    position: relative;
  }
  .news-canonical__card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    opacity: .9;
    transition: transform .5s ease-out;
  }
  .news-canonical__card:hover .news-canonical__card-image img { transform: scale(1.05); }

  .news-canonical__card-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
  }

  .news-canonical__card-category {
    font-family: 'Manrope', sans-serif;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #006a6a;
  }

  .news-canonical__card-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: #c4c6cf;
    display: inline-block;
    flex-shrink: 0;
  }

  .news-canonical__card-date {
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    color: #43474e;
  }

  .news-canonical__card-title {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.25rem;
    font-weight: 400;
    line-height: 1.25;
    color: #000613;
    margin: 0 0 12px;
    transition: color .3s;
  }
  .news-canonical__card:hover .news-canonical__card-title { color: #006a6a; }

  .news-canonical__card-excerpt {
    font-family: 'Manrope', sans-serif;
    font-size: .9rem;
    line-height: 1.6;
    color: #43474e;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  /* ── Bento Highlight ──────────────────────────────────────── */

  .news-canonical__bento {
    background: #001f3f;
    border-radius: 12px;
    padding: 32px 40px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 280px;
  }

  .news-canonical__bento-eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
  }

  .news-canonical__bento-icon { color: #76d6d5; flex-shrink: 0; }

  .news-canonical__bento-label {
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .16em;
    text-transform: uppercase;
    color: #76d6d5;
  }

  .news-canonical__bento-heading {
    font-family: 'Newsreader', Georgia, serif;
    font-size: clamp(1.5rem, 2.5vw, 2rem);
    font-weight: 300;
    line-height: 1.2;
    color: #ffffff;
    margin: 0 0 16px;
    max-width: 80%;
  }

  .news-canonical__bento-body {
    font-family: 'Manrope', sans-serif;
    font-size: .9rem;
    line-height: 1.65;
    color: rgba(255,255,255,.75);
    margin: 0;
    max-width: 56ch;
  }

  .news-canonical__bento-footer {
    margin-top: 48px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }

  .news-canonical__bento-cta {
    display: inline-block;
    border: 1px solid rgba(196,198,207,.25);
    background: transparent;
    color: #ffffff;
    padding: 8px 24px;
    border-radius: 6px;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    text-decoration: none;
    transition: background .3s;
  }
  .news-canonical__bento-cta:hover {
    background: rgba(255,255,255,.08);
    color: #ffffff;
  }

  /* ── Pagination ───────────────────────────────────────────── */

  .news-canonical__pagination {
    margin-top: 80px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .news-canonical__page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    font-weight: 700;
    color: #000613;
    background: none;
    border: 1px solid rgba(196,198,207,.25);
    border-radius: 8px;
    text-decoration: none;
    cursor: pointer;
    transition: color .3s, border-color .3s;
    white-space: nowrap;
  }
  .news-canonical__page-btn:hover {
    color: #006a6a;
    border-color: #006a6a;
  }

  /* ── Responsive ───────────────────────────────────────────── */

  @media (min-width: 768px) {
    .news-canonical { padding: 48px 48px 96px; }

    .news-canonical__featured-card { flex-direction: row; }

    .news-canonical__featured-image {
      width: 60%;
      height: auto;
      min-height: 320px;
    }

    .news-canonical__featured-copy {
      width: 40%;
      padding: 48px;
    }

    .news-canonical__grid { grid-template-columns: repeat(2, 1fr); }

    .news-canonical__bento { grid-column: span 2; }
  }

  @media (min-width: 1024px) {
    .news-canonical__grid { grid-template-columns: repeat(3, 1fr); }

    .news-canonical__bento { grid-column: span 2; }
  }

  .news-canonical__load-more {
    display: flex;
    justify-content: center;
    margin-top: 56px;
  }

  .news-canonical__load-more-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 32px;
    border: 1px solid rgba(196, 198, 207, 0.6);
    border-radius: 6px;
    background: transparent;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.2s ease, border-color 0.2s ease;
  }

  .news-canonical__load-more-btn:hover {
    background: #e7e8e9;
    border-color: #006a6a;
  }

  .news-canonical__load-more-btn--disabled {
    color: #74777f;
    cursor: default;
    pointer-events: none;
  }
</style>
@endsection
