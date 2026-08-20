{{--
  Legacy collection navigation. The active homepage builds this section from
  live catalog counts in welcome.blade.php; keep this fallback claim-free.
--}}
@php
  $f = $sections[$lang]['faculty'];
  $desks = [
    [
      'abbr' => 'ЭК',
      'title' => $f['names']['econ'],
      'institution' => 'economic_library',
    ],
    [
      'abbr' => 'ТЕХ',
      'title' => $f['names']['tech'],
      'institution' => 'technology_library',
    ],
    [
      'abbr' => 'ИТ',
      'title' => $f['names']['engit'],
      'institution' => 'college_library',
    ],
  ];
@endphp

<section class="hs hs-section hs-section--ruled homepage-faculty-showcase" data-section="homepage-faculty-picks">
  <header class="hs-head homepage-faculty-showcase__head">
    <div class="hs-head__copy">
      <p class="hs-kicker">{{ $f['kicker'] }}</p>
      <h2 class="hs-title">{{ $f['title'] }}</h2>
      <p class="hs-lead">{{ $f['lead'] }}</p>
    </div>
    <a class="hs-link" href="{{ $withLang('/catalog') }}">
      {{ $f['all'] }}
      <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </a>
  </header>

  <div class="homepage-faculty-showcase__grid">
    @foreach($desks as $desk)
      <article class="homepage-faculty-showcase__desk">
        <div class="homepage-faculty-showcase__desk-top">
          <span class="homepage-faculty-showcase__desk-abbr">{{ $desk['abbr'] }}</span>
        </div>
        <h3>{{ $desk['title'] }}</h3>
        <a href="{{ $withLang('/catalog', ['institution' => $desk['institution']]) }}">
          {{ $f['cta'] }}
          <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </a>
      </article>
    @endforeach
  </div>
</section>
