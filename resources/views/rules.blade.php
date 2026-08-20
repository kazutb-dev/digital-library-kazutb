@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';
  $activePage = $activePage ?? 'rules';

  $routeWithLang = static function (string $path) use ($lang): string {
      return $lang === 'kk' ? $path : $path . '?lang=' . $lang;
  };

  $header = $rules['header'][$lang];
  $toc = $rules['toc'][$lang];
  $general = $rules['general'][$lang];
  $borrowing = $rules['borrowing'][$lang];
  $digital = $rules['digital'][$lang];
  $conduct = $rules['conduct'][$lang];
  $penalties = $rules['penalties'][$lang];
  $footerMeta = $rules['footer_meta'][$lang];
  $lastReviewedAt = $rules['last_reviewed_at'];
@endphp

@section('title', $header['headline'] . ' — ' . __('brand.university.full'))
@section('meta_description', $header['preamble'])

@section('content')
  <div class="rules-canonical public-v2 rules-v2">
    <header class="public-v2__hero rules-v2__hero" data-section="rules-header">
      <div class="public-v2__inset public-v2__hero-grid">
        <div>
          <p class="public-v2__kicker">{{ $header['eyebrow'] }}</p>
          <h1 class="public-v2__title">{{ $header['headline'] }}</h1>
          <p class="public-v2__lead">{{ $header['preamble'] }}</p>
        </div>
        @if(!empty($header['effective_date']) || !empty($lastReviewedAt))
          <dl class="rules-canonical__doc-meta public-v2__hero-note">
            @if(!empty($header['effective_date']))
              <div data-test-id="rules-effective-date">
                <dt>{{ $header['effective_label'] }}</dt>
                <dd><time datetime="{{ $header['effective_date'] }}">{{ $header['effective_date'] }}</time></dd>
              </div>
            @endif
            @if(!empty($lastReviewedAt))
              <div data-test-id="rules-last-reviewed">
                <dt>{{ $header['reviewed_label'] }}</dt>
                <dd><time datetime="{{ $lastReviewedAt }}">{{ $lastReviewedAt }}</time></dd>
              </div>
            @endif
          </dl>
        @endif
      </div>
    </header>

    <div class="public-v2__body">
    <div class="public-v2__inset rules-v2__workspace">
    <aside class="rules-canonical__toc" data-section="rules-toc" aria-label="{{ $toc['label'] }}">
      <h2>{{ $toc['label'] }}</h2>
      <ul>
        @foreach($toc['items'] as $item)
          <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
        @endforeach
      </ul>
    </aside>

    <article class="rules-canonical__article">
      {{-- 1. General provisions --}}
      <section class="rules-canonical__section" id="general" data-section="rules-general">
        <h2><span class="rules-canonical__num">{{ $general['number'] }}</span>{{ $general['title'] }}</h2>
        <div class="rules-canonical__panel rules-canonical__panel--elevated">
          <p>{{ $general['lede'] }}</p>
          <ul>
            @foreach($general['items'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
      </section>

      {{-- 2. Borrowing and returns --}}
      <section class="rules-canonical__section" id="borrowing" data-section="rules-borrowing">
        <h2><span class="rules-canonical__num">{{ $borrowing['number'] }}</span>{{ $borrowing['title'] }}</h2>
        <p class="rules-canonical__section-lede">{{ $borrowing['lede'] }}</p>
        <div class="rules-canonical__audience-grid">
          @foreach($borrowing['groups'] as $group)
            <div class="rules-canonical__panel rules-canonical__audience" data-audience-slot>
              <span class="material-symbols-outlined rules-canonical__icon" aria-hidden="true">{{ $group['icon'] }}</span>
              <h3>{{ $group['audience'] }}</h3>
              <dl class="rules-canonical__audience-rows">
                @foreach($group['rows'] as $row)
                  <div>
                    <dt>{{ $row['label'] }}</dt>
                    <dd>{{ $row['value'] }}</dd>
                  </div>
                @endforeach
              </dl>
            </div>
          @endforeach
        </div>
        <ul class="rules-canonical__notes">
          @foreach($borrowing['notes'] as $note)
            <li>{{ $note }}</li>
          @endforeach
        </ul>
      </section>

      {{-- 3. Digital access --}}
      <section class="rules-canonical__section" id="digital" data-section="rules-digital-access">
        <h2><span class="rules-canonical__num">{{ $digital['number'] }}</span>{{ $digital['title'] }}</h2>
        <div class="rules-canonical__panel rules-canonical__panel--soft">
          <p>{{ $digital['lede'] }}</p>
          <ul>
            @foreach($digital['items'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
      </section>

      {{-- 4. Code of conduct --}}
      <section class="rules-canonical__section" id="conduct" data-section="rules-conduct">
        <h2><span class="rules-canonical__num">{{ $conduct['number'] }}</span>{{ $conduct['title'] }}</h2>
        <div class="rules-canonical__panel">
          <p>{{ $conduct['lede'] }}</p>
          <ul>
            @foreach($conduct['items'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
      </section>

      {{-- 5. Violations and penalties --}}
      <section class="rules-canonical__section" id="penalties" data-section="rules-penalties">
        <h2><span class="rules-canonical__num">{{ $penalties['number'] }}</span>{{ $penalties['title'] }}</h2>
        <div class="rules-canonical__panel">
          <p>{{ $penalties['lede'] }}</p>
          <ul>
            @foreach($penalties['items'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>

        @if(!empty($penalties['suspension_ladder']))
          <h3 class="rules-canonical__subheading">{{ $penalties['suspension_ladder_label'] }}</h3>
          <ol class="rules-canonical__ladder">
            @foreach($penalties['suspension_ladder'] as $step)
              <li>
                <span class="rules-canonical__ladder-step">{{ $loop->iteration }}</span>
                <div>
                  <strong>{{ $step['level'] }}</strong>
                  <p>{{ $step['detail'] }}</p>
                </div>
              </li>
            @endforeach
          </ol>
        @endif

        <div class="rules-canonical__callout">
          <span class="material-symbols-outlined" aria-hidden="true">balance</span>
          <p><strong>{{ $penalties['appeal_label'] }}.</strong> {{ $penalties['appeal_text'] }}</p>
        </div>
      </section>

      {{-- Footer meta — related links + document version --}}
      <section class="rules-canonical__footer-meta" data-section="rules-footer-meta">
        <p class="rules-canonical__policy">{{ $footerMeta['eyebrow'] }}</p>
        <h2>{{ $footerMeta['heading'] }}</h2>
        <p class="rules-canonical__footer-body">{{ $footerMeta['body'] }}</p>
        <div class="rules-canonical__footer-links">
          <a class="rules-canonical__footer-link rules-canonical__footer-link--primary"
             href="{{ $routeWithLang($footerMeta['contacts_href']) }}"
             data-test-id="rules-contacts-link">
            {{ $footerMeta['contacts_label'] }}
          </a>
          <a class="rules-canonical__footer-link"
             href="{{ $routeWithLang($footerMeta['leadership_href']) }}"
             data-test-id="rules-leadership-link">
            {{ $footerMeta['leadership_label'] }}
          </a>
        </div>
        @if(!empty($footerMeta['version_value']))
          <p class="rules-canonical__version">{{ $footerMeta['version_label'] }}: {{ $footerMeta['version_value'] }}</p>
        @endif
      </section>
    </article>
    </div>
    </div>
  </div>
@endsection

@section('head')
<style>
  .rules-canonical {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 64px;
    font-family: 'Manrope', sans-serif;
  }

  .rules-canonical__toc {
    display: none;
  }

  .rules-canonical__article {
    max-width: 768px;
    width: 100%;
    flex: 1;
  }

  .rules-canonical__header {
    margin-bottom: 72px;
  }

  .rules-canonical__policy {
    margin: 0 0 16px;
    font-size: 0.875rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #006a6a;
  }

  .rules-canonical__header h1 {
    margin: 0 0 24px;
    font-family: 'Newsreader', serif;
    font-size: clamp(2.35rem, 5vw, 3.75rem);
    line-height: 1.08;
    letter-spacing: -0.015em;
    color: #000613;
  }

  .rules-canonical__header h1 span {
    display: block;
    margin-top: 6px;
    font-size: clamp(1.45rem, 3.1vw, 1.875rem);
    font-weight: 500;
    line-height: 1.18;
    color: #43474e;
  }

  .rules-canonical__lead {
    margin: 0 0 28px;
    font-size: 1.125rem;
    line-height: 1.72;
    color: #43474e;
  }

  .rules-canonical__doc-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 0;
  }

  .rules-canonical__doc-meta > div {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 10px 16px;
    background: #ffffff;
    border: 1px solid rgba(196, 198, 207, 0.6);
    border-radius: 6px;
  }

  .rules-canonical__doc-meta dt {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #43474e;
  }

  .rules-canonical__doc-meta dd {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: #000613;
  }

  .rules-canonical__section {
    margin-bottom: 88px;
    scroll-margin-top: 128px;
  }

  .rules-canonical__section h2 {
    display: flex;
    align-items: baseline;
    gap: 16px;
    margin: 0 0 28px;
    padding-bottom: 16px;
    border-bottom: 1px solid #d9dadb;
    font-family: 'Newsreader', serif;
    font-size: clamp(1.9rem, 3.2vw, 2.3rem);
    line-height: 1.12;
    color: #000613;
  }

  .rules-canonical__num {
    font-family: 'Newsreader', serif;
    font-size: 0.62em;
    color: #006a6a;
  }

  .rules-canonical__section-lede {
    margin: 0 0 28px;
    font-size: 1rem;
    line-height: 1.7;
    color: #43474e;
  }

  .rules-canonical__panel {
    padding: 32px;
    border-radius: 8px;
    background: #ffffff;
  }

  .rules-canonical__panel--elevated {
    box-shadow: 0 24px 48px -12px rgba(0, 6, 19, 0.04);
  }

  .rules-canonical__panel--soft {
    background: #eef1f1;
  }

  .rules-canonical__panel p {
    margin: 0;
    font-size: 0.9375rem;
    line-height: 1.78;
    color: #191c1d;
  }

  .rules-canonical__panel ul {
    margin: 22px 0 0;
    padding-left: 20px;
    font-size: 0.9375rem;
    line-height: 1.78;
    color: #43474e;
    display: grid;
    gap: 10px;
  }

  .rules-canonical__audience-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
    margin-bottom: 24px;
  }

  .rules-canonical__icon {
    display: inline-flex;
    margin-bottom: 12px;
    font-size: 1.9rem;
    color: #006a6a;
  }

  .rules-canonical__audience h3 {
    margin: 0 0 16px;
    font-family: 'Newsreader', serif;
    font-size: 1.3rem;
    line-height: 1.25;
    color: #000613;
  }

  .rules-canonical__audience-rows {
    display: grid;
    gap: 10px;
    margin: 0;
  }

  .rules-canonical__audience-rows > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eef1f1;
  }

  .rules-canonical__audience-rows > div:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .rules-canonical__audience-rows dt {
    font-size: 0.8125rem;
    color: #43474e;
  }

  .rules-canonical__audience-rows dd {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #000613;
    text-align: right;
  }

  .rules-canonical__notes {
    margin: 0;
    padding-left: 20px;
    font-size: 0.875rem;
    line-height: 1.7;
    color: #43474e;
    display: grid;
    gap: 8px;
  }

  .rules-canonical__subheading {
    margin: 36px 0 18px;
    font-family: 'Newsreader', serif;
    font-size: 1.4rem;
    color: #000613;
  }

  .rules-canonical__ladder {
    list-style: none;
    margin: 0 0 28px;
    padding: 0;
    display: grid;
    gap: 14px;
  }

  .rules-canonical__ladder li {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    background: #ffffff;
    border-left: 4px solid #006a6a;
    border-radius: 8px;
    padding: 18px 22px;
  }

  .rules-canonical__ladder-step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(0, 106, 106, 0.1);
    color: #006a6a;
    font-family: 'Newsreader', serif;
    font-size: 1rem;
    font-weight: 600;
  }

  .rules-canonical__ladder strong {
    display: block;
    font-size: 0.9375rem;
    color: #000613;
    margin-bottom: 4px;
  }

  .rules-canonical__ladder p {
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.65;
    color: #43474e;
  }

  .rules-canonical__callout {
    padding: 16px 20px;
    border-radius: 6px;
    background: #e1e3e4;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }

  .rules-canonical__callout .material-symbols-outlined {
    font-size: 1.3rem;
    color: #006a6a;
    line-height: 1.4;
  }

  .rules-canonical__callout p {
    margin: 0;
    font-size: 0.875rem;
    line-height: 1.65;
    color: #43474e;
  }

  .rules-canonical__callout strong {
    color: #000613;
  }

  .rules-canonical__footer-meta {
    padding: 36px;
    border-radius: 8px;
    background: #ffffff;
    border-top: 4px solid #006a6a;
  }

  .rules-canonical__footer-meta h2 {
    margin: 0 0 14px;
    font-family: 'Newsreader', serif;
    font-size: 1.7rem;
    line-height: 1.2;
    color: #000613;
  }

  .rules-canonical__footer-body {
    margin: 0 0 24px;
    font-size: 0.9375rem;
    line-height: 1.7;
    color: #43474e;
    max-width: 560px;
  }

  .rules-canonical__footer-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
  }

  .rules-canonical__footer-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 24px;
    border-radius: 6px;
    border: 1px solid rgba(0, 106, 106, 0.4);
    color: #006a6a;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.2s ease, color 0.2s ease;
  }

  .rules-canonical__footer-link:hover {
    background: rgba(0, 106, 106, 0.08);
  }

  .rules-canonical__footer-link--primary {
    background: #006a6a;
    border-color: #006a6a;
    color: #ffffff;
  }

  .rules-canonical__footer-link--primary:hover {
    background: #00524f;
    color: #ffffff;
  }

  .rules-canonical__version {
    margin: 0;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    color: #74777f;
  }

  @media (min-width: 640px) {
    .rules-canonical__audience-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (min-width: 768px) {
    .rules-canonical {
      padding: 128px 48px 120px;
      flex-direction: row;
      gap: 64px;
      align-items: flex-start;
    }

    .rules-canonical__toc {
      display: block;
      width: 256px;
      flex-shrink: 0;
      position: sticky;
      top: 128px;
      height: fit-content;
    }

    .rules-canonical__toc h2 {
      margin: 0 0 24px;
      padding-left: 16px;
      border-left: 1px solid rgba(196, 198, 207, 0.7);
      font-family: 'Newsreader', serif;
      font-size: 1.5rem;
      font-weight: 500;
      color: #000613;
    }

    .rules-canonical__toc ul {
      margin: 0;
      padding: 0 0 0 16px;
      border-left: 1px solid rgba(196, 198, 207, 0.7);
      list-style: none;
      display: grid;
      gap: 16px;
    }

    .rules-canonical__toc a {
      text-decoration: none;
      font-size: 0.9375rem;
      line-height: 1.5;
      color: #43474e;
      transition: color 0.2s ease;
    }

    .rules-canonical__toc a:hover,
    .rules-canonical__toc a:focus-visible {
      color: #006a6a;
    }
  }

  @media (min-width: 1024px) {
    .rules-canonical__audience-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
</style>
@endsection
