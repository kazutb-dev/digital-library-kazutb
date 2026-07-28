@extends('layouts.public')

@php
  $lang = request()->query('lang', 'ru');
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'events';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  $copy = $events['chrome'][$lang];
  $eventItems = $events['items'];
@endphp

@section('title', $copy['title'])

@section('content')
  <div class="events-canonical">
    {{-- Header — canonical display heading + lead paragraph. --}}
    <header class="events-canonical__header" data-section="events-canonical-header">
      <h1 class="events-canonical__display">{{ $copy['hero_title'] }}</h1>
      <p class="events-canonical__lead">{{ $copy['hero_body'] }}</p>
    </header>

    {{-- Events list — vertical stack, first event carries the teal highlight rail. --}}
    <section class="events-canonical__list" data-section="events-canonical-list">
      @foreach($eventItems as $index => $event)
        @php
          $e = $event['i18n'][$lang];
          $isFeatured = ! empty($event['featured']);
          $eventSlug = $event['slug'];
        @endphp
        <article
          class="events-canonical__card {{ $isFeatured ? 'events-canonical__card--featured' : '' }}"
          data-event-slot
          data-event-slug="{{ $eventSlug }}"
          data-event-category="{{ $event['category_slug'] }}"
          @if($isFeatured) data-event-featured="true" @endif
        >
          <aside class="events-canonical__date" aria-hidden="false">
            <span class="events-canonical__eyebrow">{{ $e['category'] }}</span>
            <time class="events-canonical__date-day" datetime="{{ $event['iso_date'] }}">{{ $e['date_month_day'] }}</time>
            <span class="events-canonical__date-meta">{{ $e['date_year_time'] }}</span>
          </aside>
          <div class="events-canonical__body">
            <h2 class="events-canonical__title">{{ $e['title'] }}</h2>
            <p class="events-canonical__description">{{ $e['description'] }}</p>
            <div class="events-canonical__footer">
              <div class="events-canonical__venue">
                <span class="material-symbols-outlined" aria-hidden="true">location_on</span>
                <span>{{ $e['venue'] }}</span>
              </div>
              <a class="events-canonical__details-link"
                 href="{{ $routeWithLang('/events/' . $eventSlug) }}"
                 data-test-id="events-canonical-details-{{ $eventSlug }}">
                <span>{{ $copy['event_details_cta'] }}</span>
                <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
              </a>
            </div>
          </div>
        </article>
      @endforeach
    </section>

    {{-- Load More — UI-only surface for now; real pagination arrives with /events/{slug} module. --}}
    <div class="events-canonical__load-more-wrap" data-section="events-canonical-load-more">
      <button type="button" class="events-canonical__load-more" data-test-id="events-canonical-load-more">
        {{ $copy['load_more'] }}
      </button>
    </div>
  </div>
@endsection

@section('head')
<style>
  .events-canonical {
    max-width: 1120px;
    margin: 0 auto;
    padding: 80px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .events-canonical {
      padding: 96px 32px;
    }
  }

  .events-canonical__header {
    margin-bottom: 80px;
    max-width: 760px;
  }

  .events-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 44px;
    line-height: 1.08;
    letter-spacing: -0.02em;
    color: #000613;
    margin: 0 0 24px;
  }

  @media (min-width: 768px) {
    .events-canonical__display {
      font-size: 56px;
    }
  }

  .events-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 18px;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
    max-width: 640px;
  }

  .events-canonical__list {
    display: flex;
    flex-direction: column;
    gap: 48px;
    margin-bottom: 64px;
  }

  .events-canonical__card {
    background: #ffffff;
    padding: 32px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 32px;
    min-width: 0;
  }

  @media (min-width: 768px) {
    .events-canonical__card {
      flex-direction: row;
    }
  }

  .events-canonical__card:hover {
    background: #e7e8e9;
  }

  .events-canonical__date {
    flex-shrink: 0;
    border-left: 4px solid #e1e3e4;
    padding: 8px 0 8px 24px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
  }

  @media (min-width: 768px) {
    .events-canonical__date {
      width: 25%;
    }
  }

  .events-canonical__card--featured .events-canonical__date {
    border-left-color: #006a6a;
  }

  .events-canonical__eyebrow {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #43474e;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
  }

  .events-canonical__card--featured .events-canonical__eyebrow {
    color: #006a6a;
  }

  .events-canonical__date-day {
    font-family: 'Newsreader', serif;
    font-size: 30px;
    line-height: 1.1;
    color: #000613;
    margin-bottom: 4px;
    display: block;
  }

  .events-canonical__date-meta {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    color: #43474e;
  }

  .events-canonical__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .events-canonical__title {
    font-family: 'Newsreader', serif;
    font-size: 24px;
    line-height: 1.25;
    color: #000613;
    margin: 0 0 12px;
    transition: color 0.3s ease;
  }

  .events-canonical__card:hover .events-canonical__title {
    color: #006a6a;
  }

  .events-canonical__description {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    line-height: 1.65;
    color: #43474e;
    margin: 0 0 24px;
    max-width: 640px;
  }

  .events-canonical__footer {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: auto;
    align-items: flex-start;
    width: 100%;
  }

  @media (min-width: 640px) {
    .events-canonical__footer {
      flex-direction: row;
      align-items: center;
      gap: 24px;
    }
  }

  .events-canonical__venue {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
  }

  .events-canonical__venue .material-symbols-outlined {
    font-size: 18px;
  }

  .events-canonical__details-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    padding-bottom: 2px;
    white-space: nowrap;
    transition: border-color 0.2s ease;
  }

  .events-canonical__details-link:hover {
    border-bottom-color: #006a6a;
  }

  .events-canonical__details-link .material-symbols-outlined {
    font-size: 16px;
  }

  .events-canonical__controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    margin-top: 16px;
  }

  .events-canonical__page-status {
    margin: 0;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    text-align: center;
  }

  .events-canonical__controls-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
  }

  .events-canonical__nav-btn,
  .events-canonical__page-btn {
    background: transparent;
    border: 1px solid rgba(196, 198, 207, 0.5);
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 500;
    padding: 12px 32px;
    border-radius: 6px;
    text-decoration: none;
    text-align: center;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }

  .events-canonical__nav-btn:hover,
  .events-canonical__page-btn:hover {
    background: #e1e3e4;
  }
</style>
@endsection
