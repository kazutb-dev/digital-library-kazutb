@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'leadership';

  $routeWithLang = static function (string $path) use ($lang): string {
      return $lang === 'kk' ? $path : $path . '?lang=' . $lang;
  };

  $header = $leadership['header'][$lang];
  $mandate = $leadership['mandate'][$lang];
  $supportCta = $leadership['support_cta'][$lang];
  $lastReviewedAt = $leadership['last_reviewed_at'];

  $profiles = collect($leadership['profiles'])->sortBy('order')->values();

  $reviewedLabel = [
    'ru' => 'Последняя проверка',
    'kk' => 'Соңғы тексеру',
    'en' => 'Last reviewed',
  ][$lang];
@endphp

@section('title', $header['headline'] . ' — KazUTB')

@section('content')
  <div class="leadership-canonical">
    <header class="leadership-canonical__header" data-section="leadership-header">
      <p class="leadership-canonical__eyebrow">{{ $header['eyebrow'] }}</p>
      <h1 class="leadership-canonical__display">{{ $header['headline'] }}</h1>
      <p class="leadership-canonical__lead">{{ $header['lede'] }}</p>
    </header>

    <section class="leadership-canonical__mandate" data-section="leadership-mandate">
      <div class="leadership-canonical__mandate-body">
        <p class="leadership-canonical__eyebrow">{{ $mandate['eyebrow'] }}</p>
        <h2>{{ $mandate['title'] }}</h2>
        <p>{{ $mandate['paragraph'] }}</p>
      </div>
      <dl class="leadership-canonical__reports-to" data-test-id="leadership-reports-to">
        <dt>{{ $mandate['reports_to_label'] }}</dt>
        <dd>{{ $mandate['reports_to_value'] }}</dd>
      </dl>
    </section>

    <section class="leadership-canonical__directory" data-section="leadership-directory">
      @foreach($profiles as $profile)
        @php
          $roleTitle = $profile['role_title'][$lang];
          $scopeLine = $profile['role_scope_line'][$lang];
          $description = $profile['role_description'][$lang];
          $fullName = $profile['full_name'][$lang] ?? null;
          $initials = $profile['portrait_initials'][$lang] ?? '·';
        @endphp
        <article class="leadership-canonical__card" data-leadership-slug="{{ $profile['slug'] }}">
          <span class="leadership-canonical__portrait" aria-hidden="true">{{ $initials }}</span>
          <div class="leadership-canonical__card-body">
            @if($fullName)
              <h3 class="leadership-canonical__name">{{ $fullName }}</h3>
              <p class="leadership-canonical__role">{{ $roleTitle }}</p>
            @else
              <h3 class="leadership-canonical__name">{{ $roleTitle }}</h3>
            @endif
            <p class="leadership-canonical__scope">{{ $scopeLine }}</p>
            <p class="leadership-canonical__description">{{ $description }}</p>
          </div>
        </article>
      @endforeach
    </section>

    <section class="leadership-canonical__support" data-section="leadership-support-cta">
      <p class="leadership-canonical__eyebrow">{{ $supportCta['eyebrow'] }}</p>
      <h2>{{ $supportCta['heading'] }}</h2>
      <p class="leadership-canonical__support-body">{{ $supportCta['body'] }}</p>
      <a class="leadership-canonical__support-cta" href="{{ $routeWithLang($supportCta['href']) }}" data-test-id="leadership-support-contacts-link">
        {{ $supportCta['label'] }}
      </a>
    </section>

    <p class="leadership-canonical__reviewed" data-test-id="leadership-last-reviewed">
      {{ $reviewedLabel }}: <time datetime="{{ $lastReviewedAt }}">{{ $lastReviewedAt }}</time>
    </p>
  </div>
@endsection

@section('head')
<style>
  .leadership-canonical {
    max-width: 1120px;
    margin: 0 auto;
    padding: 80px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .leadership-canonical {
      padding: 96px 32px;
    }
  }

  .leadership-canonical__eyebrow {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #006a6a;
    margin: 0 0 16px;
  }

  .leadership-canonical__header {
    max-width: 760px;
    margin-bottom: 64px;
  }

  .leadership-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 44px;
    line-height: 1.08;
    letter-spacing: -0.02em;
    color: #000613;
    margin: 0 0 24px;
  }

  @media (min-width: 768px) {
    .leadership-canonical__display {
      font-size: 56px;
    }
  }

  .leadership-canonical__lead {
    font-size: 18px;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
    max-width: 680px;
  }

  .leadership-canonical__mandate {
    display: flex;
    flex-direction: column;
    gap: 28px;
    background: #ffffff;
    border-left: 4px solid #006a6a;
    border-radius: 8px;
    padding: 36px;
    margin-bottom: 64px;
  }

  @media (min-width: 1024px) {
    .leadership-canonical__mandate {
      flex-direction: row;
      align-items: flex-start;
      gap: 56px;
    }
  }

  .leadership-canonical__mandate-body {
    flex: 1;
    min-width: 0;
  }

  .leadership-canonical__mandate h2 {
    font-family: 'Newsreader', serif;
    font-size: 26px;
    font-weight: 400;
    color: #000613;
    margin: 0 0 14px;
  }

  .leadership-canonical__mandate p {
    margin: 0;
    font-size: 15px;
    line-height: 1.7;
    color: #43474e;
  }

  .leadership-canonical__mandate .leadership-canonical__eyebrow {
    margin-bottom: 12px;
  }

  .leadership-canonical__reports-to {
    flex-shrink: 0;
    margin: 0;
    padding: 20px 24px;
    background: #eef1f1;
    border-radius: 8px;
    min-width: 220px;
  }

  .leadership-canonical__reports-to dt {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #43474e;
    margin-bottom: 6px;
  }

  .leadership-canonical__reports-to dd {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #000613;
  }

  .leadership-canonical__directory {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
    margin-bottom: 64px;
  }

  @media (min-width: 768px) {
    .leadership-canonical__directory {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  .leadership-canonical__card {
    background: #ffffff;
    border-radius: 8px;
    padding: 32px 28px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    transition: background-color 0.3s ease;
  }

  .leadership-canonical__card:hover {
    background: #eef1f1;
  }

  .leadership-canonical__portrait {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(0, 106, 106, 0.1);
    color: #006a6a;
    font-family: 'Newsreader', serif;
    font-size: 24px;
    font-weight: 600;
  }

  .leadership-canonical__name {
    font-family: 'Newsreader', serif;
    font-size: 21px;
    line-height: 1.25;
    color: #000613;
    margin: 0 0 4px;
  }

  .leadership-canonical__role {
    font-size: 14px;
    font-weight: 700;
    color: #191c1d;
    margin: 0 0 4px;
  }

  .leadership-canonical__scope {
    font-size: 12.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #006a6a;
    margin: 0 0 14px;
  }

  .leadership-canonical__description {
    font-size: 14px;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
  }

  .leadership-canonical__support {
    background: #ffffff;
    border-radius: 8px;
    border-top: 4px solid #006a6a;
    padding: 36px;
    margin-bottom: 32px;
  }

  .leadership-canonical__support h2 {
    font-family: 'Newsreader', serif;
    font-size: 26px;
    font-weight: 400;
    color: #000613;
    margin: 0 0 14px;
  }

  .leadership-canonical__support-body {
    font-size: 15px;
    line-height: 1.7;
    color: #43474e;
    margin: 0 0 24px;
    max-width: 620px;
  }

  .leadership-canonical__support-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 28px;
    background: #006a6a;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    transition: background-color 0.2s ease;
  }

  .leadership-canonical__support-cta:hover {
    background: #00524f;
  }

  .leadership-canonical__reviewed {
    margin: 0;
    font-size: 12.5px;
    color: #74777f;
  }
</style>
@endsection
