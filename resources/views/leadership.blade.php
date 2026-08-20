@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';
  $activePage = $activePage ?? 'leadership';
  $routeWithLang = static fn (string $path): string => $lang === 'kk' ? $path : $path.'?lang='.$lang;
  $header = $leadership['header'][$lang];
  $mandate = $leadership['mandate'][$lang];
  $supportCta = $leadership['support_cta'][$lang];
  $profiles = collect($leadership['profiles'])->sortBy('order')->values();
@endphp

@section('title', $header['headline'].' — '.__('brand.university.full'))
@section('meta_description', $header['lede'])

@section('content')
<div class="public-page leadership-page">
  <header class="public-page__intro public-page__intro--editorial" data-section="leadership-header">
    <div class="public-container public-page__intro-grid">
      <div>
        <p class="public-eyebrow">{{ $header['eyebrow'] }}</p>
        <h1 class="public-page__title">{{ $header['headline'] }}</h1>
        <p class="public-page__lead">{{ $header['lede'] }}</p>
      </div>
      @if(!empty($mandate['reports_to_value']))
        <dl class="public-page__summary" data-test-id="leadership-reports-to">
          <div><dt>{{ $mandate['reports_to_label'] }}</dt><dd>{{ $mandate['reports_to_value'] }}</dd></div>
        </dl>
      @endif
    </div>
  </header>

  <div class="public-page__body">
    <div class="public-container public-stack">
      @if(!empty($mandate['paragraph']))
        <section class="public-panel leadership-page__mandate" data-section="leadership-mandate">
          <p class="public-eyebrow">{{ $mandate['eyebrow'] }}</p>
          <h2>{{ $mandate['title'] }}</h2>
          <p>{{ $mandate['paragraph'] }}</p>
        </section>
      @endif

      <section aria-label="{{ $header['eyebrow'] }}" data-section="leadership-directory">
        <div class="public-card-grid public-card-grid--three">
          @foreach($profiles as $profile)
            @php
              $fullName = $profile['full_name'][$lang] ?? null;
              $roleTitle = $profile['role_title'][$lang];
              $portraitPath = trim((string) ($profile['portrait'] ?? ''));
              $hasPortrait = $portraitPath !== '' && is_file(public_path($portraitPath));
            @endphp
            <article class="public-card leadership-page__profile" data-leadership-slug="{{ $profile['slug'] }}">
              <div class="leadership-page__portrait">
                @if($hasPortrait)
                  <img src="/{{ ltrim($portraitPath, '/') }}"
                       alt="{{ $fullName }}"
                       loading="eager"
                       decoding="async">
                @else
                  <span class="leadership-page__portrait-empty" aria-hidden="true">{{ $profile['portrait_initials'][$lang] ?? '·' }}</span>
                @endif
              </div>
              <div>
                <h3>{{ $fullName ?: $roleTitle }}</h3>
                @if($fullName)<p class="leadership-page__role">{{ $roleTitle }}</p>@endif
                @if(!empty($profile['role_scope_line'][$lang]))<p class="leadership-page__scope">{{ $profile['role_scope_line'][$lang] }}</p>@endif
                @if(!empty($profile['role_description'][$lang]))<p>{{ $profile['role_description'][$lang] }}</p>@endif
                @if(!empty($profile['source_url']))
                  <p><a href="{{ $profile['source_url'] }}" target="_blank" rel="noopener noreferrer">{{ $profile['source_label'][$lang] }}</a></p>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      </section>

      <section class="public-callout" data-section="leadership-support-cta">
        <div><p class="public-eyebrow">{{ $supportCta['eyebrow'] }}</p><h2>{{ $supportCta['heading'] }}</h2><p>{{ $supportCta['body'] }}</p></div>
        <a class="public-button public-button--primary" href="{{ $routeWithLang($supportCta['href']) }}" data-test-id="leadership-support-contacts-link">{{ $supportCta['label'] }}<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
      </section>

    </div>
  </div>
</div>
@endsection
