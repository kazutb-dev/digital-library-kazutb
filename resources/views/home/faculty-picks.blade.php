{{--
  Section 2 — Library lending desks.
--}}
@php
  $f = $sections[$lang]['faculty'];
  $desks = [
    [
      'abbr' => 'ЭК',
      'title' => $f['names']['econ'],
      'lead' => $lang === 'ru'
        ? 'Экономическая и управленческая литература, профильные учебники и периодика.'
        : ($lang === 'kk'
          ? 'Экономика және басқару әдебиеті, бейіндік оқулықтар мен мерзімді басылымдар.'
          : 'Economics, business and management resources.'),
      'meta' => $lang === 'ru' ? '1/203 · первый этаж' : ($lang === 'kk' ? '1/203 · 1-қабат' : '1/203 · first floor'),
      'chips' => [$lang === 'ru' ? 'Финансы' : ($lang === 'kk' ? 'Қаржы' : 'Finance'), $lang === 'ru' ? 'Менеджмент' : ($lang === 'kk' ? 'Менеджмент' : 'Management'), $lang === 'ru' ? 'Бизнес' : ($lang === 'kk' ? 'Бизнес' : 'Business')],
    ],
    [
      'abbr' => 'ТЕХ',
      'title' => $f['names']['tech'],
      'lead' => $lang === 'ru'
        ? 'Технические, производственные и прикладные издания для технологических направлений.'
        : ($lang === 'kk'
          ? 'Технологиялық бағыттарға арналған техникалық әрі қолданбалы басылымдар.'
          : 'Technical and applied resources for technology fields.'),
      'meta' => $lang === 'ru' ? '1/200 · первый этаж' : ($lang === 'kk' ? '1/200 · 1-қабат' : '1/200 · first floor'),
      'chips' => [$lang === 'ru' ? 'Материаловедение' : ($lang === 'kk' ? 'Материалтану' : 'Materials science'), $lang === 'ru' ? 'Стандартизация' : ($lang === 'kk' ? 'Стандарттау' : 'Standardisation'), $lang === 'ru' ? 'Технология' : ($lang === 'kk' ? 'Технология' : 'Technology')],
    ],
    [
      'abbr' => 'ИТ',
      'title' => $f['names']['engit'],
      'lead' => $lang === 'ru'
        ? 'Литература по программированию, инженерии, данным и цифровым системам.'
        : ($lang === 'kk'
          ? 'Бағдарламалау, инженерия, деректер және цифрлық жүйелер бойынша әдебиеттер.'
          : 'Programming, engineering, data and digital systems resources.'),
      'meta' => $lang === 'ru' ? '1/202 · первый этаж' : ($lang === 'kk' ? '1/202 · 1-қабат' : '1/202 · first floor'),
      'chips' => [$lang === 'ru' ? 'Программирование' : ($lang === 'kk' ? 'Бағдарламалау' : 'Programming'), $lang === 'ru' ? 'Сети' : ($lang === 'kk' ? 'Желілер' : 'Networks'), $lang === 'ru' ? 'Кибербезопасность' : ($lang === 'kk' ? 'Киберқауіпсіздік' : 'Cybersecurity')],
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
    <a class="hs-link" href="{{ $withLang('/contacts') }}">
      {{ $f['all'] }}
      <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </a>
  </header>

  <div class="homepage-faculty-showcase__grid">
    @foreach($desks as $desk)
      <article class="homepage-faculty-showcase__desk">
        <div class="homepage-faculty-showcase__desk-top">
          <span class="homepage-faculty-showcase__desk-abbr">{{ $desk['abbr'] }}</span>
          <span class="homepage-faculty-showcase__desk-meta">{{ $desk['meta'] }}</span>
        </div>
        <h3>{{ $desk['title'] }}</h3>
        <p class="homepage-faculty-showcase__note">{{ $desk['lead'] }}</p>
        <div class="homepage-faculty-showcase__chips">
          @foreach($desk['chips'] as $chip)
            <span>{{ $chip }}</span>
          @endforeach
        </div>
        <a href="{{ $withLang('/contacts') }}">
          {{ $f['cta'] }}
          <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </a>
      </article>
    @endforeach
  </div>
</section>
