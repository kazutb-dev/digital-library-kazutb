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
  $rulesPageCopy = [
      'ru' => [
          'scope_label' => 'Структура документа',
          'scope_value' => '5 тематических разделов',
          'terms_label' => 'Сроки и доступные действия',
          'terms_value' => 'Показываются в личном кабинете',
      ],
      'kk' => [
          'scope_label' => 'Құжат құрылымы',
          'scope_value' => '5 тақырыптық бөлім',
          'terms_label' => 'Мерзімдер мен қолжетімді әрекеттер',
          'terms_value' => 'Жеке кабинетте көрсетіледі',
      ],
      'en' => [
          'scope_label' => 'Document structure',
          'scope_value' => '5 topic sections',
          'terms_label' => 'Due dates and available actions',
          'terms_value' => 'Shown in the reader account',
      ],
  ][$lang];
@endphp

@section('title', $header['headline'] . ' — ' . __('brand.university.full'))
@section('meta_description', $header['preamble'])

@section('content')
  <div class="public-page rules-page rules-canonical">
    <header class="public-page__intro public-page__intro--editorial" data-section="rules-header">
      <div class="public-container public-page__intro-grid">
        <div>
          <p class="public-eyebrow">{{ $header['eyebrow'] }}</p>
          <h1 class="public-page__title">{{ $header['headline'] }}</h1>
          <p class="public-page__lead">{{ $header['preamble'] }}</p>
        </div>
        <dl class="rules-canonical__doc-meta public-page__summary">
          <div>
            <dt>{{ $rulesPageCopy['scope_label'] }}</dt>
            <dd>{{ $rulesPageCopy['scope_value'] }}</dd>
          </div>
          <div>
            <dt>{{ $rulesPageCopy['terms_label'] }}</dt>
            <dd>{{ $rulesPageCopy['terms_value'] }}</dd>
          </div>
          @if(!empty($header['effective_date']) || !empty($lastReviewedAt))
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
          @endif
        </dl>
      </div>
    </header>

    <div class="public-page__body rules-page__body">
    <div class="public-container public-stack rules-page__stack">
    @include('partials.library-info-nav')
    <div class="rules-page__workspace">
    <nav class="rules-canonical__toc" data-section="rules-toc" aria-label="{{ $toc['label'] }}">
      <h2>{{ $toc['label'] }}</h2>
      <ul>
        @foreach($toc['items'] as $item)
          <li><a href="{{ $item['href'] }}">{{ $item['label'] }}</a></li>
        @endforeach
      </ul>
    </nav>

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
  </div>
@endsection

@section('scripts')
<script>
  (() => {
    const links = [...document.querySelectorAll('.rules-canonical__toc a[href^="#"]')];
    const sections = links.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
    if (!links.length || !sections.length || !('IntersectionObserver' in window)) return;

    const markCurrent = (id) => links.forEach((link) => {
      if (link.getAttribute('href') === `#${id}`) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
    });

    const observer = new IntersectionObserver((entries) => {
      const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (visible[0]) markCurrent(visible[0].target.id);
    }, { rootMargin: '-20% 0px -65% 0px', threshold: 0 });

    sections.forEach((section) => observer.observe(section));
    markCurrent(location.hash.slice(1) || sections[0].id);
  })();
</script>
@endsection
