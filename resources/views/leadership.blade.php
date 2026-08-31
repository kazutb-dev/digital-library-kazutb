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
  $leadershipPageCopy = [
    'ru' => [
      'hero_eyebrow' => 'О библиотеке',
      'basis_label' => 'Основание публикации',
      'basis_value' => 'Официальная страница научной библиотеки',
      'contact_label' => 'Для обращений',
      'contact_value' => 'Официальные контакты библиотеки',
      'directory_eyebrow' => 'Ответственное лицо',
      'directory_title' => 'Подтверждённые сведения',
      'directory_body' => 'Публикуем только те данные, которые подтверждены официальным источником университета.',
      'verified' => 'Сведения подтверждены',
      'email' => 'E-mail',
      'extension' => 'Внутренний телефон',
    ],
    'kk' => [
      'hero_eyebrow' => 'Кітапхана туралы',
      'basis_label' => 'Жариялау негізі',
      'basis_value' => 'Ғылыми кітапхананың ресми беті',
      'contact_label' => 'Өтініштер үшін',
      'contact_value' => 'Кітапхананың ресми байланыс арналары',
      'directory_eyebrow' => 'Жауапты тұлға',
      'directory_title' => 'Расталған мәліметтер',
      'directory_body' => 'Университеттің ресми дереккөзімен расталған мәліметтер ғана жарияланады.',
      'verified' => 'Мәліметтер расталған',
      'email' => 'E-mail',
      'extension' => 'Ішкі телефон',
    ],
    'en' => [
      'hero_eyebrow' => 'About the library',
      'basis_label' => 'Publication basis',
      'basis_value' => 'Official Scientific Library page',
      'contact_label' => 'For inquiries',
      'contact_value' => 'Official library contact channels',
      'directory_eyebrow' => 'Responsible person',
      'directory_title' => 'Confirmed information',
      'directory_body' => 'Only details confirmed by an official university source are published here.',
      'verified' => 'Information confirmed',
      'email' => 'Email',
      'extension' => 'Extension',
    ],
  ][$lang];
@endphp

@section('title', $header['headline'].' — '.__('brand.university.full'))
@section('meta_description', $header['lede'])

@section('content')
<div class="public-page leadership-page">
  <header class="public-page__intro public-page__intro--editorial" data-section="leadership-header">
    <div class="public-container public-page__intro-grid">
      <div>
        <p class="public-eyebrow">{{ $leadershipPageCopy['hero_eyebrow'] }}</p>
        <h1 class="public-page__title">{{ $header['headline'] }}</h1>
        <p class="public-page__lead">{{ $header['lede'] }}</p>
      </div>
      <dl class="public-page__summary">
        <div><dt>{{ $leadershipPageCopy['basis_label'] }}</dt><dd>{{ $leadershipPageCopy['basis_value'] }}</dd></div>
        <div><dt>{{ $leadershipPageCopy['contact_label'] }}</dt><dd>{{ $leadershipPageCopy['contact_value'] }}</dd></div>
        @if(!empty($mandate['reports_to_value']))
          <div data-test-id="leadership-reports-to"><dt>{{ $mandate['reports_to_label'] }}</dt><dd>{{ $mandate['reports_to_value'] }}</dd></div>
        @endif
      </dl>
    </div>
  </header>

  <div class="public-page__body">
    <div class="public-container public-stack">
      @include('partials.library-info-nav')

      @if(!empty($mandate['paragraph']))
        <section class="public-panel leadership-page__mandate" data-section="leadership-mandate">
          <p class="public-eyebrow">{{ $mandate['eyebrow'] }}</p>
          <h2>{{ $mandate['title'] }}</h2>
          <p>{{ $mandate['paragraph'] }}</p>
        </section>
      @endif

      <section class="leadership-page__directory" aria-labelledby="leadership-directory-title" data-section="leadership-directory">
        <div class="public-section-head">
          <p class="public-eyebrow">{{ $leadershipPageCopy['directory_eyebrow'] }}</p>
          <h2 id="leadership-directory-title">{{ $leadershipPageCopy['directory_title'] }}</h2>
          <p>{{ $leadershipPageCopy['directory_body'] }}</p>
        </div>
        <div>
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
                       alt="{{ $fullName ?: $roleTitle }}"
                       loading="eager"
                       decoding="async">
                @else
                  <span class="leadership-page__portrait-empty" aria-hidden="true">{{ $profile['portrait_initials'][$lang] ?? '·' }}</span>
                @endif
              </div>
              <div>
                <span class="leadership-page__verified"><span class="material-symbols-outlined" aria-hidden="true">verified</span>{{ $leadershipPageCopy['verified'] }}</span>
                <h3>{{ $fullName ?: $roleTitle }}</h3>
                @if($fullName)<p class="leadership-page__role">{{ $roleTitle }}</p>@endif
                @if(!empty($profile['role_scope_line'][$lang]))<p class="leadership-page__scope">{{ $profile['role_scope_line'][$lang] }}</p>@endif
                @if(!empty($profile['role_description'][$lang]))<p>{{ $profile['role_description'][$lang] }}</p>@endif
                @if(!empty($profile['email']))<p><strong>{{ $leadershipPageCopy['email'] }}:</strong> <a href="mailto:{{ $profile['email'] }}">{{ $profile['email'] }}</a></p>@endif
                @if(!empty($profile['extension']))<p><strong>{{ $leadershipPageCopy['extension'] }}:</strong> {{ $profile['extension'] }}</p>@endif
                @if(!empty($profile['source_url']))
                  <p><a class="leadership-page__source" href="{{ $profile['source_url'] }}" target="_blank" rel="noopener noreferrer">{{ $profile['source_label'][$lang] }}<span class="material-symbols-outlined" aria-hidden="true">open_in_new</span></a></p>
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
