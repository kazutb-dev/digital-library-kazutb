@extends('layouts.public', ['activePage' => 'discover'])

@php
  // Phase 3 Cluster E — /discover canonical-led rebuild per
  // docs/design-exports/academic_discovery_hub_canonical/code.html.
  //
  // Retired the previous brochure-style discover shell (hero kicker + quote card +
  // orbital visual, disciplines section with volume panel, 4-step workflow,
  // institutional metadata tag cloud with live-fetch chips, 3-card bridge section,
  // and the legacy institutional-pathways data-test-id). The new layout mirrors
  // the canonical export's three-section structure:
  //
  //   1. Hero — display title + lead (no decorative visual, no quote card)
  //   2. Faculties & Departments bento — 4 cards in the canonical 2+1+1+2 layout,
  //      with tag chips naming top departments and each card linking into
  //      /catalog via the real faculty secondary-axis query.
  //   3. UDC Knowledge Pathways — 4 cards showing primary UDC axes with real
  //      KazUTB catalog coverage (004, 33, 62, 50), each linking into
  //      /catalog?udc=XXX as the primary discovery mode.
  //
  // UDC-first discovery remains the primary pathway; faculty/department cards are
  // a secondary browsing axis and deliberately stay below the UDC primary slot in
  // site-wide discovery logic (they appear higher in visual order per the
  // canonical export but each faculty card carries its own UDC label so the UDC
  // contract is never hidden).

  $lang = request()->query('lang', 'ru');
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'discover';

  $withLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $queryString = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($queryString !== '' ? ('?' . $queryString) : '');
  };

  // Canonical-led tri-lingual copy. Only chrome strings live here; the data rows
  // (faculty slug + UDC code + department slugs + icon + tag list) are shared
  // and projected per-locale through $facultyLabels / $udcLabels below.
  $copy = [
      'ru' => [
          'title' => 'Центр академического поиска — KazUTB Smart Library',
          'hero_title' => 'Центр академического поиска',
          'hero_body' => 'Навигация по совокупному институциональному знанию. Исследуйте профильные факультеты, углубляйтесь в Универсальную десятичную классификацию (УДК) или открывайте кураторский каталог.',
          'faculties_title' => 'Факультеты и кафедры',
          'udc_title' => 'Маршруты знаний',
          'udc_lead' => 'Структурное исследование через систему Универсальной десятичной классификации.',
          'udc_view_all' => 'Открыть полное дерево УДК',
      ],
      'kk' => [
          'title' => 'Академиялық ізденіс орталығы — KazUTB Smart Library',
          'hero_title' => 'Академиялық ізденіс орталығы',
          'hero_body' => 'Институционалдық білімнің жиынтығы бойынша навигация. Профильдік факультеттерді зерттеңіз, Әмбебап ондық жіктеуге (ӘОЖ) тереңдеңіз немесе кураторлық каталогты ашыңыз.',
          'faculties_title' => 'Факультеттер мен кафедралар',
          'udc_title' => 'Білім маршруттары',
          'udc_lead' => 'Әмбебап ондық жіктеу жүйесі арқылы құрылымдық зерттеу.',
          'udc_view_all' => 'ӘОЖ толық ағашын ашу',
      ],
      'en' => [
          'title' => 'Academic Discovery Hub — KazUTB Smart Library',
          'hero_title' => 'Academic Discovery Hub',
          'hero_body' => 'Navigate the sum of our institutional knowledge. Explore specialized faculties, deep-dive into the Universal Decimal Classification (UDC), or search the curated catalog.',
          'faculties_title' => 'Faculties & Departments',
          'udc_title' => 'Knowledge Pathways',
          'udc_lead' => 'Structured exploration via the Universal Decimal Classification system.',
          'udc_view_all' => 'View Full UDC Tree',
      ],
  ][$lang];

  // Faculty axis (secondary discovery). Each entry matches the real KazUTB
  // institutional structure already used in /catalog seed data. The `span`
  // property drives the canonical 2+1+1+2 bento pattern. Tags expose the
  // primary department names associated with the faculty, not decorative copy.
  $facultyLabels = [
      'technology' => [
          'ru' => ['title' => 'Технологический', 'tags' => ['Пищевые технологии', 'Лёгкая промышленность', 'Дизайн']],
          'kk' => ['title' => 'Технологиялық',   'tags' => ['Тамақ технологиялары', 'Жеңіл өнеркәсіп', 'Дизайн']],
          'en' => ['title' => 'Faculty of Technology', 'tags' => ['Food Tech', 'Light Industry', 'Design']],
      ],
      'economics' => [
          'ru' => ['title' => 'Экономика и бизнес', 'tags' => ['Менеджмент', 'Туризм']],
          'kk' => ['title' => 'Экономика және бизнес', 'tags' => ['Менеджмент', 'Туризм']],
          'en' => ['title' => 'Faculty of Economics & Business', 'tags' => ['Management', 'Tourism']],
      ],
      'engineering' => [
          'ru' => ['title' => 'Инжиниринг и ИТ', 'tags' => ['Информатика', 'Инженерные системы']],
          'kk' => ['title' => 'Инжиниринг және АТ', 'tags' => ['Информатика', 'Инженерлік жүйелер']],
          'en' => ['title' => 'Faculty of Engineering & IT', 'tags' => ['Computer Science', 'Engineering Systems']],
      ],
      'military' => [
          'ru' => ['title' => 'Военная кафедра', 'tags' => ['Военные науки', 'Тактическая подготовка']],
          'kk' => ['title' => 'Әскери кафедра',  'tags' => ['Әскери ғылымдар', 'Тактикалық дайындық']],
          'en' => ['title' => 'Military Department', 'tags' => ['Military Science', 'Tactical Training']],
      ],
  ];

  // Canonical bento: large + small + small + large (2+1+1+2 on md+).
  $faculties = [
      ['slug' => 'technology',   'udc' => '66',  'udc_label' => 'UDC 66–67',    'span' => 2],
      ['slug' => 'economics',    'udc' => '33',  'udc_label' => 'UDC 33',       'span' => 1],
      ['slug' => 'engineering',  'udc' => '004', 'udc_label' => 'UDC 004 / 62', 'span' => 1],
      ['slug' => 'military',     'udc' => '355', 'udc_label' => 'UDC 355',      'span' => 2],
  ];

  // UDC primary axis — real top-level KazUTB coverage areas. Icons mirror the
  // canonical library_books / account_balance / science / palette rhythm but
  // swap to palette/biotech tokens that match the domain content.
  $udcLabels = [
      '004' => [
          'ru' => 'Информатика и вычислительные системы',
          'kk' => 'Информатика және есептеу жүйелері',
          'en' => 'Computing & Information Science',
      ],
      '33' => [
          'ru' => 'Социальные науки, экономика, право',
          'kk' => 'Әлеуметтік ғылымдар, экономика, құқық',
          'en' => 'Social Sciences, Economics, Law',
      ],
      '62' => [
          'ru' => 'Прикладные науки, техника, технологии',
          'kk' => 'Қолданбалы ғылымдар, техника, технологиялар',
          'en' => 'Applied Sciences, Engineering, Technology',
      ],
      '50' => [
          'ru' => 'Естественные науки, математика',
          'kk' => 'Жаратылыстану ғылымдары, математика',
          'en' => 'Natural Sciences & Mathematics',
      ],
  ];

  $udcPathways = [
      ['code' => '004', 'icon' => 'memory'],
      ['code' => '33',  'icon' => 'account_balance'],
      ['code' => '62',  'icon' => 'engineering'],
      ['code' => '50',  'icon' => 'science'],
  ];
@endphp

@section('title', $copy['title'])

@section('content')
  {{-- Cluster E — canonical-led rebuild of /discover per academic_discovery_hub_canonical.
       Section markers (canonical order): discover-canonical-hero,
       discover-canonical-faculties, discover-canonical-udc. --}}
  <div class="discover-canonical">

    {{-- 1. Hero — single column, display title + lead (no decorative chips/orbit/quote). --}}
    <section class="discover-canonical__hero" data-section="discover-canonical-hero">
      <div class="discover-canonical__hero-inner">
        <h1 class="discover-canonical__display">{{ $copy['hero_title'] }}</h1>
        <p class="discover-canonical__lead">{{ $copy['hero_body'] }}</p>
      </div>
    </section>

    {{-- 2. Faculties & Departments bento (2+1+1+2). Secondary faculty/department axis;
         each card hops into /catalog with the real institutional query contract. --}}
    <section class="discover-canonical__section" data-section="discover-canonical-faculties">
      <h2 class="discover-canonical__section-title">{{ $copy['faculties_title'] }}</h2>
      <div class="discover-canonical__bento">
        @foreach($faculties as $faculty)
          @php $labels = $facultyLabels[$faculty['slug']][$lang]; @endphp
          <a
            class="discover-canonical__bento-card discover-canonical__bento-card--span-{{ $faculty['span'] }}"
            href="{{ $withLang('/catalog', ['faculty' => $faculty['slug'], 'udc' => $faculty['udc']]) }}"
            data-faculty-slot
            data-faculty-slug="{{ $faculty['slug'] }}"
            data-faculty-udc="{{ $faculty['udc'] }}"
            data-test-id="discover-canonical-faculty-{{ $faculty['slug'] }}"
          >
            @if($faculty['span'] === 2)
              <div class="discover-canonical__bento-accent" aria-hidden="true"></div>
            @endif
            <div class="discover-canonical__bento-head">
              <h3 class="discover-canonical__bento-title">{{ $labels['title'] }}</h3>
              <span class="material-symbols-outlined discover-canonical__bento-arrow" aria-hidden="true">arrow_forward</span>
            </div>
            <div class="discover-canonical__bento-meta">
              <span class="discover-canonical__bento-udc">{{ $faculty['udc_label'] }}</span>
            </div>
            <div class="discover-canonical__bento-tags">
              @foreach($labels['tags'] as $tag)
                <span class="discover-canonical__bento-tag">{{ $tag }}</span>
              @endforeach
            </div>
          </a>
        @endforeach
      </div>
    </section>

    {{-- 3. UDC Knowledge Pathways — primary discovery axis. Each pathway card links
         into /catalog?udc=XXX, the canonical UDC-first discovery contract. --}}
    <section class="discover-canonical__section" data-section="discover-canonical-udc">
      <div class="discover-canonical__udc-head">
        <div class="discover-canonical__udc-head-copy">
          <h2 class="discover-canonical__section-title">{{ $copy['udc_title'] }}</h2>
          <p class="discover-canonical__udc-lead">{{ $copy['udc_lead'] }}</p>
        </div>
        <a
          class="discover-canonical__udc-view-all"
          href="{{ $withLang('/catalog', ['sort' => 'udc']) }}"
          data-test-id="discover-canonical-udc-view-all"
        >
          {{ $copy['udc_view_all'] }}
          <span class="material-symbols-outlined" aria-hidden="true">open_in_new</span>
        </a>
      </div>
      <div class="discover-canonical__udc-grid">
        @foreach($udcPathways as $pathway)
          <a
            class="discover-canonical__udc-card"
            href="{{ $withLang('/catalog', ['udc' => $pathway['code']]) }}"
            data-udc-slot
            data-udc-code="{{ $pathway['code'] }}"
            data-test-id="discover-canonical-udc-{{ $pathway['code'] }}"
          >
            <div class="discover-canonical__udc-code">UDC {{ $pathway['code'] }}</div>
            <h4 class="discover-canonical__udc-title">{{ $udcLabels[$pathway['code']][$lang] }}</h4>
            <div class="discover-canonical__udc-foot">
              <span class="material-symbols-outlined" aria-hidden="true">{{ $pathway['icon'] }}</span>
            </div>
          </a>
        @endforeach
      </div>
    </section>

  </div>
@endsection

@section('head')
<style>
  /* Cluster E — /discover canonical-led rebuild.
     Scoped to .discover-canonical; mirrors academic_discovery_hub_canonical/code.html. */

  .discover-canonical {
    max-width: 1280px;
    margin: 0 auto;
    padding: 128px 32px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (max-width: 767px) {
    .discover-canonical { padding: 96px 16px 80px; }
  }

  /* --- Hero --------------------------------------------------------------- */
  .discover-canonical__hero {
    position: relative;
    margin-bottom: 128px;
  }

  .discover-canonical__hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, #f3f4f5 0%, #f8f9fa 100%);
    border-radius: 12px;
    z-index: -1;
  }

  .discover-canonical__hero-inner {
    max-width: 56rem;
    padding: 64px 32px 48px;
  }

  .discover-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 48px;
    line-height: 1.05;
    color: #000613;
    letter-spacing: -0.02em;
    margin: 0 0 24px -2px;
  }

  @media (min-width: 768px) {
    .discover-canonical__display { font-size: 60px; }
  }

  .discover-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 20px;
    line-height: 1.7;
    color: #43474e;
    max-width: 40rem;
    margin: 0;
  }

  /* --- Section shell ----------------------------------------------------- */
  .discover-canonical__section { margin-bottom: 128px; }

  .discover-canonical__section:last-child { margin-bottom: 0; }

  .discover-canonical__section-title {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 30px;
    color: #000613;
    margin: 0 0 48px;
    letter-spacing: -0.01em;
  }

  /* --- Faculties bento (2 + 1 + 1 + 2) ----------------------------------- */
  .discover-canonical__bento {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
  }

  @media (min-width: 768px) {
    .discover-canonical__bento {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }

  .discover-canonical__bento-card {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 240px;
    padding: 32px;
    border-radius: 12px;
    background: #ffffff;
    color: inherit;
    text-decoration: none;
    overflow: hidden;
    transition: background-color 0.5s ease, transform 0.3s ease;
  }

  .discover-canonical__bento-card:hover {
    background: #e7e8e9;
    transform: translateY(-2px);
  }

  @media (min-width: 768px) {
    .discover-canonical__bento-card--span-2 { grid-column: span 2 / span 2; }
  }

  .discover-canonical__bento-accent {
    position: absolute;
    top: 0;
    right: 0;
    width: 256px;
    height: 256px;
    background: linear-gradient(to bottom left, rgba(175, 200, 240, 0.2), transparent);
    border-bottom-left-radius: 100%;
    z-index: 0;
    pointer-events: none;
  }

  .discover-canonical__bento-head {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    z-index: 1;
  }

  .discover-canonical__bento-title {
    font-family: 'Newsreader', serif;
    font-weight: 500;
    font-size: 24px;
    color: #000613;
    margin: 0;
    letter-spacing: -0.01em;
  }

  .discover-canonical__bento-arrow {
    color: #006a6a;
    opacity: 0;
    transform: translateX(0);
    transition: opacity 0.3s ease, transform 0.3s ease;
  }

  .discover-canonical__bento-card:hover .discover-canonical__bento-arrow {
    opacity: 1;
    transform: translateX(4px);
  }

  .discover-canonical__bento-meta {
    position: relative;
    margin-bottom: auto;
    padding-bottom: 16px;
    z-index: 1;
  }

  .discover-canonical__bento-udc {
    display: inline-block;
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 600;
    color: #006a6a;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    background: rgba(144, 239, 239, 0.25);
    padding: 4px 10px;
    border-radius: 6px;
  }

  .discover-canonical__bento-tags {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 24px;
    z-index: 1;
  }

  .discover-canonical__bento-tag {
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 500;
    color: #43474e;
    background: #f8f9fa;
    border: 1px solid rgba(196, 198, 207, 0.2);
    padding: 4px 12px;
    border-radius: 2px;
  }

  /* --- UDC pathways ------------------------------------------------------- */
  .discover-canonical__udc-head {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 48px;
    align-items: flex-start;
  }

  @media (min-width: 768px) {
    .discover-canonical__udc-head {
      flex-direction: row;
      align-items: flex-end;
      justify-content: space-between;
    }
  }

  .discover-canonical__udc-head-copy {
    max-width: 40rem;
  }

  .discover-canonical__udc-head-copy .discover-canonical__section-title {
    margin-bottom: 16px;
  }

  .discover-canonical__udc-lead {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
  }

  .discover-canonical__udc-view-all {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
    transition: color 0.2s ease;
  }

  .discover-canonical__udc-view-all:hover { color: #004f4f; }

  .discover-canonical__udc-view-all .material-symbols-outlined {
    font-size: 16px;
  }

  .discover-canonical__udc-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
  }

  @media (min-width: 768px) {
    .discover-canonical__udc-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (min-width: 1024px) {
    .discover-canonical__udc-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }

  .discover-canonical__udc-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 192px;
    padding: 24px;
    background: #f3f4f5;
    border: 1px solid rgba(196, 198, 207, 0.1);
    border-radius: 12px;
    color: inherit;
    text-decoration: none;
    transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.3s ease;
  }

  .discover-canonical__udc-card:hover {
    background: #edeeef;
    border-color: rgba(0, 106, 106, 0.2);
    transform: translateY(-2px);
  }

  .discover-canonical__udc-code {
    font-family: 'Manrope', sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #006a6a;
    margin-bottom: 8px;
    letter-spacing: 0.04em;
  }

  .discover-canonical__udc-title {
    font-family: 'Newsreader', serif;
    font-weight: 500;
    font-size: 18px;
    line-height: 1.35;
    color: #000613;
    margin: 0;
  }

  .discover-canonical__udc-foot {
    margin-top: auto;
    padding-top: 16px;
    display: flex;
    justify-content: flex-end;
  }

  .discover-canonical__udc-foot .material-symbols-outlined {
    font-size: 24px;
    color: #74777f;
  }
</style>
@endsection
