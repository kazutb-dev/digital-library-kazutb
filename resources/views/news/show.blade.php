{{-- resources/views/news/show.blade.php --}}
{{-- Phase 3.g: /news/{slug} detail canonical-exact rebuild per docs/design-exports/news_detail_canonical --}}
@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';
  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  $chrome = [
      'ru' => [
          'title_suffix'       => 'Казахский университет технологии и бизнеса имени К. Кулажанова',
          'back'               => 'Вернуться к новостям',
          'read_time'          => ':count мин. чтения',
        'highlights_heading' => 'Ключевые акценты',
          'tags_label'         => 'Теги:',
          'related_heading'    => 'Связанные материалы',
          'repository_heading' => 'Научная работа',
          'repository_cta'     => 'Открыть запись в репозитории',
      ],
      'kk' => [
          'title_suffix'       => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
          'back'               => 'Жаңалықтарға оралу',
          'read_time'          => ':count мин. оқу',
          'highlights_heading' => 'Негізгі тұстар',
          'tags_label'         => 'Тегтер:',
          'related_heading'    => 'Байланысты материалдар',
          'repository_heading' => 'Ғылыми жұмыс',
          'repository_cta'     => 'Репозиторий жазбасын ашу',
      ],
      'en' => [
          'title_suffix'       => 'Kazakh University of Technology and Business named after K. Kulazhanov',
          'back'               => 'Return to News & Announcements',
          'read_time'          => ':count min read',
          'highlights_heading' => 'Project Highlights',
          'tags_label'         => 'Tags:',
          'related_heading'    => 'Related Updates',
          'repository_heading' => 'Scholarly work',
          'repository_cta'     => 'Open repository record',
      ],
  ][$lang];

  $isEventDetail = ($activePage ?? '') === 'events' || ($article['schema_type'] ?? null) === 'Event';
  $detailIndexPath = $isEventDetail ? '/events' : '/news';
  $detailItemPrefix = $isEventDetail ? '/events/' : '/news/';
  if ($isEventDetail) {
      $chrome['back'] = [
          'ru' => 'Вернуться к мероприятиям',
          'kk' => 'Іс-шараларға оралу',
          'en' => 'Back to events',
      ][$lang];
  }

  $articleBodyText = collect($article['body'][$lang] ?? [])
      ->map(static fn ($block) => trim((string) ($block['text'] ?? '')))
      ->filter()
      ->implode(' ');
  preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($articleBodyText), $articleWords);
  $readMinutes = max(1, (int) ceil(count($articleWords[0] ?? []) / 200));
  $chrome['read_time'] = str_replace(':count', (string) $readMinutes, $chrome['read_time']);
  $sidebarRelated = array_slice($relatedArticles ?? [], 0, 3);
@endphp

@section('title', $article['title'][$lang] . ' — ' . $chrome['title_suffix'])
@section('meta_description', $article['excerpt'][$lang])

@section('content')
<div class="news-detail-canonical" data-section="news-detail-canonical-page">

  {{-- ── Article column ──────────────────────────────────────────────── --}}
  <article class="news-detail-canonical__article" data-section="news-detail-canonical-article">

    {{-- Back link --}}
    <a href="{{ $routeWithLang($detailIndexPath) }}"
       class="news-detail-canonical__back"
       data-test-id="news-detail-canonical-back">
      <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      {{ $chrome['back'] }}
    </a>

    {{-- Article header --}}
    <header class="news-detail-canonical__header">
      <div class="news-detail-canonical__meta">
        <span class="news-detail-canonical__category">{{ $article['category'][$lang] }}</span>
        <span class="news-detail-canonical__meta-sep" aria-hidden="true">•</span>
        <time datetime="{{ $article['published_at'] }}" class="news-detail-canonical__date">{{ $article['published_display'][$lang] }}</time>
        <span class="news-detail-canonical__meta-sep" aria-hidden="true">•</span>
        <span class="news-detail-canonical__read-time">{{ $chrome['read_time'] }}</span>
      </div>
      <h1 class="news-detail-canonical__title">{{ $article['title'][$lang] }}</h1>
      <p class="news-detail-canonical__subtitle">{{ $article['excerpt'][$lang] }}</p>
    </header>

    {{-- Hero figure --}}
    @if(! empty($article['hero']['image']))
    <figure class="news-detail-canonical__hero">
      <img src="{{ asset($article['hero']['image']) }}"
           alt="{{ $article['hero']['alt'][$lang] ?? '' }}"
           class="news-detail-canonical__hero-img"
           loading="lazy" />
      @if(! empty($article['hero']['alt'][$lang]))
      <figcaption class="news-detail-canonical__hero-caption">{{ $article['hero']['alt'][$lang] }}</figcaption>
      @endif
    </figure>
    @endif

    @if(!empty($article['event']['starts_at']))
    <section class="news-detail-canonical__highlight" aria-label="{{ __('news.editor.event_details') }}">
      <h2 class="news-detail-canonical__highlight-heading">{{ __('news.editor.event_details') }}</h2>
      <dl>
        <div><dt>{{ __('news.fields.starts_at') }}</dt><dd><time datetime="{{ $article['event']['starts_at'] }}">{{ $article['event']['starts_display'] }}</time></dd></div>
        @if($article['event']['ends_at'])<div><dt>{{ __('news.fields.ends_at') }}</dt><dd><time datetime="{{ $article['event']['ends_at'] }}">{{ $article['event']['ends_display'] }}</time></dd></div>@endif
        @if($article['event']['venue'])<div><dt>{{ __('news.fields.venue') }}</dt><dd>{{ $article['event']['venue'] }}</dd></div>@endif
        @if($article['event']['online_url'])<div><dt>{{ __('news.fields.online_url') }}</dt><dd><a href="{{ $article['event']['online_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('news.public.online') }}</a></dd></div>@endif
        @if($article['event']['organizer'])<div><dt>{{ __('news.fields.organizer') }}</dt><dd>{{ $article['event']['organizer'] }}</dd></div>@endif
      </dl>
      @if($article['event']['registration_url'])<form method="POST" action="{{ route('news.registration-click',$article['id']) }}">@csrf<button class="news-detail-canonical__cta-btn" type="submit">{{ __('news.actions.register') }}</button></form>@endif
    </section>
    @endif

    @if(! empty($article['repository']))
    <section class="news-detail-canonical__highlight" data-section="news-linked-repository">
      <h2 class="news-detail-canonical__highlight-heading">{{ $chrome['repository_heading'] }}</h2>
      <p>{{ $article['repository']['title'] }}@if($article['repository']['year']) · {{ $article['repository']['year'] }}@endif</p>
      <a class="news-detail-canonical__cta-btn"
         href="{{ $routeWithLang('/repository/' . $article['repository']['id']) }}"
         data-test-id="news-linked-repository-open">{{ $chrome['repository_cta'] }}</a>
    </section>
    @endif

    {{-- Article body --}}
    <div class="news-detail-canonical__body" data-test-id="news-detail-canonical-body">
      @foreach($article['body'][$lang] as $block)
        @switch($block['type'])
          @case('lead')
            <p class="news-detail-canonical__lead">{{ $block['text'] }}</p>
            @break
          @case('h2')
            <h2 class="news-detail-canonical__h2">{{ $block['text'] }}</h2>
            @break
          @case('list')
            <div class="news-detail-canonical__highlight">
              <h3 class="news-detail-canonical__highlight-heading">{{ $block['heading'] ?? $chrome['highlights_heading'] }}</h3>
              <ul class="news-detail-canonical__highlight-list">
                @foreach($block['items'] as $item)
                <li><strong>{{ $item['term'] }}</strong>: {{ $item['text'] }}</li>
                @endforeach
              </ul>
            </div>
            @break
          @default
            <p>{{ $block['text'] }}</p>
        @endswitch
      @endforeach

      {{-- Inline CTA --}}
      @if(! empty($article['cta']) && ! empty($article['cta'][$lang]))
      <div class="news-detail-canonical__cta" data-test-id="news-detail-canonical-cta">
        <h3 class="news-detail-canonical__cta-heading">{{ $article['cta'][$lang]['heading'] }}</h3>
        <p class="news-detail-canonical__cta-body">{{ $article['cta'][$lang]['body'] }}</p>
        <a href="{{ $routeWithLang($article['cta'][$lang]['href']) }}"
           class="news-detail-canonical__cta-btn">{{ $article['cta'][$lang]['label'] }}</a>
      </div>
      @endif
    </div>

    {{-- Article footer: the managed category is informational until the
         public payload exposes a stable category slug for filtering. --}}
    <footer class="news-detail-canonical__footer">
      <div class="news-detail-canonical__tags">
        <span class="news-detail-canonical__tags-label">{{ $chrome['tags_label'] }}</span>
        <span class="news-detail-canonical__tag">{{ $article['category'][$lang] }}</span>
      </div>
    </footer>

  </article>

  {{-- ── Sidebar ─────────────────────────────────────────────────────── --}}
  @if(! empty($sidebarRelated))
  <aside class="news-detail-canonical__sidebar" data-section="news-detail-canonical-sidebar">
    {{-- Related updates are sourced from published managed records. --}}
    <div class="news-detail-canonical__related" data-section="news-detail-canonical-related">
      <h3 class="news-detail-canonical__related-heading">{{ $chrome['related_heading'] }}</h3>
      <div class="news-detail-canonical__related-list">
        @foreach($sidebarRelated as $rel)
        <a href="{{ $routeWithLang($detailItemPrefix . $rel['slug']) }}"
           class="news-detail-canonical__related-item">
          @if(! empty($rel['hero']['image']))
          <img src="{{ asset($rel['hero']['image']) }}"
               alt="{{ $rel['hero']['alt'][$lang] ?? '' }}"
               class="news-detail-canonical__related-thumb"
               loading="lazy" />
          @else
          <div class="news-detail-canonical__related-thumb-placeholder">
            <span class="material-symbols-outlined" aria-hidden="true">article</span>
          </div>
          @endif
          <div class="news-detail-canonical__related-copy">
            <span class="news-detail-canonical__related-eyebrow">{{ $rel['category'][$lang] }}</span>
            <h4 class="news-detail-canonical__related-title">{{ $rel['title'][$lang] }}</h4>
            <time class="news-detail-canonical__related-date">{{ $rel['published_display'][$lang] }}</time>
          </div>
        </a>
        @endforeach
      </div>
    </div>
  </aside>
  @endif

</div>
@endsection

@section('head')
@php
  $structuredData = [
    '@context' => 'https://schema.org',
    '@type' => $article['schema_type'] ?? 'NewsArticle',
    'headline' => $article['title'][$lang],
    'description' => $article['excerpt'][$lang],
    'datePublished' => $article['published_at'],
    'url' => $routeWithLang(url()->current()),
  ];
  if (($article['schema_type'] ?? null) === 'Event' && !empty($article['event']['starts_at'])) {
    $structuredData['startDate'] = $article['event']['starts_at'];
    $structuredData['endDate'] = $article['event']['ends_at'] ?: null;
    if ($article['event']['venue']) {
      $structuredData['location'] = ['@type' => 'Place', 'name' => $article['event']['venue']];
    } elseif ($article['event']['online_url']) {
      $structuredData['location'] = ['@type' => 'VirtualLocation', 'url' => $article['event']['online_url']];
    }
  }
@endphp
<meta property="og:type" content="{{ ($article['schema_type'] ?? null) === 'Event' ? 'website' : 'article' }}">
<meta property="og:title" content="{{ $article['title'][$lang] }}">
<meta property="og:description" content="{{ $article['excerpt'][$lang] }}">
<meta property="og:url" content="{{ $routeWithLang(url()->current()) }}">
<script type="application/ld+json">@json(array_filter($structuredData, static fn ($value) => $value !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
<style>
  /* ============================================================
     news-detail-canonical — Phase 3.g
     Scoped to /news/{slug} detail only.
     Canonical source: docs/design-exports/news_detail_canonical/code.html
     ============================================================ */

  /* ── Layout ─────────────────────────────────────────────────── */

  .news-detail-canonical {
    max-width: 1280px;
    margin: 0 auto;
    padding: 32px 16px 80px;
    display: flex;
    flex-direction: column;
    gap: 48px;
  }

  @media (min-width: 1024px) {
    .news-detail-canonical {
      flex-direction: row;
      gap: 64px;
      align-items: start;
      padding: 32px 32px 80px;
    }
    .news-detail-canonical__sidebar {
      position: sticky;
      top: 96px;
      align-self: flex-start;
    }
  }

  .news-detail-canonical__article {
    flex: 2 2 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 32px;
  }

  .news-detail-canonical__title,
  .news-detail-canonical__subtitle,
  .news-detail-canonical__body,
  .news-detail-canonical__related-title {
    overflow-wrap: anywhere;
  }

  .news-detail-canonical__sidebar {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 48px;
  }

  /* ── Back link ───────────────────────────────────────────────── */

  .news-detail-canonical__back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: color .2s;
  }
  .news-detail-canonical__back:hover { color: #004f4f; }
  .news-detail-canonical__back .material-symbols-outlined {
    font-size: 20px;
    transition: transform .2s;
  }
  .news-detail-canonical__back:hover .material-symbols-outlined { transform: translateX(-4px); }

  /* ── Article Header ─────────────────────────────────────────── */

  .news-detail-canonical__header {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .news-detail-canonical__meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    color: #43474e;
  }

  .news-detail-canonical__category {
    background: #e7e8e9;
    padding: 2px 12px;
    border-radius: 2px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #000613;
  }

  .news-detail-canonical__meta-sep { color: #74777f; }

  .news-detail-canonical__date,
  .news-detail-canonical__read-time {
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
  }

  .news-detail-canonical__title {
    font-family: 'Newsreader', Georgia, serif;
    font-size: clamp(2.25rem, 4.4vw, 3.5rem);
    font-weight: 400;
    line-height: 1.1;
    letter-spacing: -.02em;
    color: #000613;
    margin: 0 -4px;
  }

  .news-detail-canonical__subtitle {
    font-family: 'Manrope', sans-serif;
    font-size: 1.125rem;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
  }

  /* ── Hero ────────────────────────────────────────────────────── */

  .news-detail-canonical__hero {
    margin: 0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,6,19,.08);
  }

  .news-detail-canonical__hero-img {
    width: 100%;
    height: auto;
    display: block;
    aspect-ratio: 16 / 9;
    object-fit: cover;
  }

  .news-detail-canonical__hero-caption {
    margin: 12px 0 0;
    padding: 0 4px;
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    color: #74777f;
    font-style: italic;
    text-align: center;
  }

  /* ── Article Body ────────────────────────────────────────────── */

  .news-detail-canonical__body {
    font-family: 'Manrope', sans-serif;
    font-size: 1.0625rem;
    line-height: 1.8;
    color: #191c1d;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }
  .news-detail-canonical__body p { margin: 0; }

  .news-detail-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 1.1875rem;
    line-height: 1.65;
    color: #000613;
    font-weight: 500;
    margin: 0;
  }

  .news-detail-canonical__h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.75rem;
    font-weight: 400;
    line-height: 1.2;
    color: #000613;
    margin: 8px 0 0;
  }

  .news-detail-canonical__highlight {
    background: #ffffff;
    padding: 32px;
    border-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,6,19,.04);
    border: 1px solid rgba(196,198,207,.2);
    margin: 8px 0;
  }

  .news-detail-canonical__highlight-heading {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.25rem;
    font-weight: 400;
    color: #000613;
    margin: 0 0 16px;
  }

  .news-detail-canonical__highlight-list {
    list-style: disc;
    padding-left: 20px;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    color: #43474e;
    font-family: 'Manrope', sans-serif;
  }

  /* ── Inline CTA ─────────────────────────────────────────────── */

  .news-detail-canonical__cta {
    background: #f3f4f5;
    border-radius: 8px;
    padding: 32px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    margin-top: 8px;
  }

  .news-detail-canonical__cta-heading {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.375rem;
    font-weight: 400;
    color: #000613;
    margin: 0;
  }

  .news-detail-canonical__cta-body {
    font-family: 'Manrope', sans-serif;
    font-size: .9375rem;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
  }

  .news-detail-canonical__cta-btn {
    display: inline-block;
    background: #001f3f;
    color: #ffffff;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    font-weight: 600;
    padding: 8px 24px;
    border-radius: 6px;
    text-decoration: none;
    transition: opacity .2s;
  }
  .news-detail-canonical__cta-btn:hover { opacity: .88; }

  /* ── Article Footer ─────────────────────────────────────────── */

  .news-detail-canonical__footer {
    margin-top: 8px;
    padding-top: 32px;
    border-top: 1px solid rgba(196,198,207,.2);
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
  }

  .news-detail-canonical__tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
  }

  .news-detail-canonical__tags-label {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
  }

  .news-detail-canonical__tag {
    background: #f3f4f5;
    color: #000613;
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 500;
    padding: 4px 12px;
    border-radius: 999px;
    text-decoration: none;
    transition: background .2s;
  }
  .news-detail-canonical__tag:hover { background: #edeeef; }

  .news-detail-canonical__share {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .news-detail-canonical__share-label {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
  }

  .news-detail-canonical__share-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000613;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,6,19,.08);
    transition: background .2s;
  }
  .news-detail-canonical__share-btn:hover { background: #e7e8e9; }
  .news-detail-canonical__share-btn .material-symbols-outlined { font-size: 20px; }

  /* ── Author / Editorial Card ─────────────────────────────────── */

  .news-detail-canonical__author {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,6,19,.06);
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .news-detail-canonical__author-info {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .news-detail-canonical__author-portrait {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,6,19,.08);
  }

  .news-detail-canonical__author-name {
    font-family: 'Manrope', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    color: #000613;
    margin: 0;
  }

  .news-detail-canonical__author-role {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    margin: 4px 0 0;
  }

  .news-detail-canonical__author-bio {
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
  }

  .news-detail-canonical__author-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    font-weight: 600;
    text-decoration: none;
    transition: color .2s;
    margin-top: 4px;
  }
  .news-detail-canonical__author-link:hover { color: #004f4f; }
  .news-detail-canonical__author-link .material-symbols-outlined { font-size: 16px; }

  /* ── Related Updates ─────────────────────────────────────────── */

  .news-detail-canonical__related {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .news-detail-canonical__related-heading {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.375rem;
    font-weight: 400;
    color: #000613;
    margin: 0;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(196,198,207,.2);
  }

  .news-detail-canonical__related-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .news-detail-canonical__related-item {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    text-decoration: none;
    color: inherit;
    padding: 12px;
    margin: -12px;
    border-radius: 8px;
    transition: background .2s;
  }
  .news-detail-canonical__related-item:hover { background: #ffffff; }

  .news-detail-canonical__related-thumb {
    width: 96px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,6,19,.08);
  }

  .news-detail-canonical__related-thumb-placeholder {
    width: 96px;
    height: 80px;
    border-radius: 6px;
    flex-shrink: 0;
    background: #e7e8e9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c4c6cf;
  }
  .news-detail-canonical__related-thumb-placeholder .material-symbols-outlined { font-size: 28px; }

  .news-detail-canonical__related-copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
  }

  .news-detail-canonical__related-eyebrow {
    font-family: 'Manrope', sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #006a6a;
  }

  .news-detail-canonical__related-title {
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    font-weight: 700;
    color: #000613;
    line-height: 1.3;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color .2s;
  }
  .news-detail-canonical__related-item:hover .news-detail-canonical__related-title { color: #006a6a; }

  .news-detail-canonical__related-date {
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    color: #74777f;
    margin-top: 4px;
  }

  /* ── Newsletter ──────────────────────────────────────────────── */

  .news-detail-canonical__newsletter {
    background: linear-gradient(135deg, #000613, #001f3f);
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,6,19,.2);
    position: relative;
    overflow: hidden;
  }

  .news-detail-canonical__newsletter-bg-icon {
    position: absolute;
    right: -16px;
    bottom: -16px;
    opacity: .1;
    pointer-events: none;
  }
  .news-detail-canonical__newsletter-bg-icon .material-symbols-outlined {
    font-size: 120px;
    color: #ffffff;
  }

  .news-detail-canonical__newsletter-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .news-detail-canonical__newsletter-heading {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.375rem;
    font-weight: 400;
    color: #ffffff;
    margin: 0;
  }

  .news-detail-canonical__newsletter-body {
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    line-height: 1.65;
    color: rgba(196,210,232,.75);
    margin: 0;
  }

  .news-detail-canonical__newsletter-form {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .news-detail-canonical__newsletter-input {
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(196,198,207,.4);
    border-radius: 6px;
    padding: 8px 16px;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    color: #ffffff;
    outline: none;
    transition: border-color .2s, background-color .2s;
  }
  .news-detail-canonical__newsletter-input::placeholder { color: rgba(255,255,255,.78); }
  .news-detail-canonical__newsletter-input:focus {
    border-color: #76d6d5;
    background: rgba(255,255,255,.18);
  }

  .news-detail-canonical__newsletter-btn {
    background: #006a6a;
    color: #ffffff;
    font-family: 'Manrope', sans-serif;
    font-size: .875rem;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: background .2s;
  }
  .news-detail-canonical__newsletter-btn:hover { background: #004f4f; }
</style>
@endsection
