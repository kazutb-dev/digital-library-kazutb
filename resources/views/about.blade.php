@extends('layouts.public')

@php
  // Phase 3 Cluster cleanup (post-C) — about.blade.php is now about-only.
  // The former $activePage === 'contacts' branch and its supporting copy/data
  // (contacts, hours, duty, cta-band, Cluster B.3 location/fund rooms/visit
  // notes, Cluster B.4 secondary-copy keys) have been removed; /contacts is
  // served by the standalone canonical-exact resources/views/contacts.blade.php.
  // The remaining output is the Cluster B.5 canonical-exact /about per
  // docs/design-exports/about_library_canonical.
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';
  $activePage = $activePage ?? 'about';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }

      $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null && $value !== ''));

      return $path . ($queryString !== '' ? ('?' . $queryString) : '');
  };

  $copy = [
      'ru' => [
          'title' => 'О библиотеке — Казахский университет технологии и бизнеса имени К. Кулажанова',
          'collection_areas' => [
              [
                  'slug' => 'technology',
                  'title' => 'Инженерия и технологии',
                  'body' => 'Инженерные дисциплины, информатика, прикладная математика и технологические направления — основа технологического фонда университета.',
              ],
              [
                  'slug' => 'economy',
                  'title' => 'Экономика, менеджмент и право',
                  'body' => 'Экономические и управленческие дисциплины, финансы, право и смежные общественные науки — профильный фонд экономического направления.',
              ],
              [
                  'slug' => 'humanities',
                  'title' => 'Социально-гуманитарные дисциплины',
                  'body' => 'Языки, история, педагогика и общеобразовательные материалы, которые поддерживают преподавание и исследование за пределами профильных программ.',
              ],
              [
                  'slug' => 'college',
                  'title' => 'Учебные материалы колледжа',
                  'body' => 'Учебная литература, справочные издания и методические материалы для образовательных программ колледжа, включая предвузовскую подготовку.',
              ],
          ],
          'directory_rows' => [
              [
                  'slug' => 'rules',
                  'title' => 'Правила библиотеки',
                  'body' => 'Правила пользования фондом, цифровыми материалами и читательскими залами.',
                  'href' => '/rules',
                  'cta' => 'Открыть правила',
              ],
              [
                  'slug' => 'leadership',
                  'title' => 'Руководство библиотеки',
                  'body' => 'Состав и зоны ответственности руководства библиотеки университета.',
                  'href' => '/leadership',
                  'cta' => 'Открыть раздел',
              ],
              [
                  'slug' => 'contacts',
                  'title' => 'Контакты и расположение',
                  'body' => 'Подтверждённые адрес, режим работы и способы связаться с библиотекой.',
                  'href' => '/contacts',
                  'cta' => 'Открыть контакты',
              ],
          ],
          // Cluster B.5 — canonical /about (docs/design-exports/about_library_canonical).
          'canon_hero_eyebrow' => 'Учреждение',
          'canon_hero_title_a' => 'Сохраняем знание.',
          'canon_hero_title_b' => 'Поддерживаем исследования.',
          'canon_hero_body' => 'Научная библиотека — информационный центр университета: электронные ресурсы, поддержка учебного процесса, библиотечные сервисы и доступ к базам данных.',
          'canon_hero_badge_title' => 'Институциональная библиотека',
          'canon_hero_badge_body' => 'Поддержка академических программ кампуса и колледжа Казахский университет технологии и бизнеса имени К. Кулажанова.',
          'canon_mission_title' => 'Наша миссия',
          'canon_mission_body' => 'Поддержка образовательной системы университета, расширение информационных ресурсов, совершенствование технологической инфраструктуры, создание эффективной информационной среды для читателей и повышение качества информационного обслуживания.',
          'canon_mission_cta' => 'Открыть каталог',
          'canon_stats' => [
              ['value' => '3', 'label' => 'Языка интерфейса'],
          ],
          'canon_collection_header_title' => 'Профиль коллекции',
          'canon_collection_header_body' => 'Коллекция выстроена вокруг реальной академической программы Казахский университет технологии и бизнеса имени К. Кулажанова и покрывает четыре ключевых направления.',
          'canon_directory_title' => 'Институциональный справочник',
          'canon_areas_material_icons' => [
              'technology' => 'science',
              'economy' => 'business_center',
              'humanities' => 'history_edu',
              'college' => 'school',
          ],
      ],
      'kk' => [
          'title' => 'Кітапхана туралы — Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
          'collection_areas' => [
              [
                  'slug' => 'technology',
                  'title' => 'Инженерия және технологиялар',
                  'body' => 'Инженерлік пәндер, информатика, қолданбалы математика және технологиялық бағыттар — университеттің технологиялық қорының негізі.',
              ],
              [
                  'slug' => 'economy',
                  'title' => 'Экономика, менеджмент және құқық',
                  'body' => 'Экономикалық және басқарушылық пәндер, қаржы, құқық және іргелес қоғамдық ғылымдар — экономикалық бағыттың бейіндік қоры.',
              ],
              [
                  'slug' => 'humanities',
                  'title' => 'Әлеуметтік-гуманитарлық пәндер',
                  'body' => 'Тілдер, тарих, педагогика және жалпы білім беру материалдары — бейіндік бағдарламалардан тыс оқыту мен зерттеуді қолдайды.',
              ],
              [
                  'slug' => 'college',
                  'title' => 'Колледж оқу материалдары',
                  'body' => 'Колледждің білім беру бағдарламаларына арналған оқу әдебиеті, анықтамалық басылымдар мен әдістемелік материалдар, оның ішінде жоғары оқу алды дайындық.',
              ],
          ],
          'directory_rows' => [
              [
                  'slug' => 'rules',
                  'title' => 'Кітапхана ережелері',
                  'body' => 'Қорды, цифрлық материалдарды және оқу залдарын пайдалану ережелері.',
                  'href' => '/rules',
                  'cta' => 'Ережелерді ашу',
              ],
              [
                  'slug' => 'leadership',
                  'title' => 'Кітапхана басшылығы',
                  'body' => 'Университет кітапханасы басшылығының құрамы және жауапкершілік аймақтары.',
                  'href' => '/leadership',
                  'cta' => 'Бөлімді ашу',
              ],
              [
                  'slug' => 'contacts',
                  'title' => 'Байланыс және орналасу',
                  'body' => 'Расталған мекенжай, жұмыс режимі және кітапханамен байланысу жолдары.',
                  'href' => '/contacts',
                  'cta' => 'Байланысты ашу',
              ],
          ],
          // Cluster B.5 — canonical /about (docs/design-exports/about_library_canonical).
          'canon_hero_eyebrow' => 'Мекеме',
          'canon_hero_title_a' => 'Білімді сақтаймыз.',
          'canon_hero_title_b' => 'Зерттеуді қолдаймыз.',
          'canon_hero_body' => 'Ғылыми кітапхана — университеттің ақпараттық орталығы: электрондық ресурстар, оқу процесін қолдау, кітапхана сервистері және дерекқорларға қолжетімділік.',
          'canon_hero_badge_title' => 'Институционалдық кітапхана',
          'canon_hero_badge_body' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті университеті мен колледжінің академиялық бағдарламаларын қолдау.',
          'canon_mission_title' => 'Біздің миссиямыз',
          'canon_mission_body' => 'Университеттің білім беру жүйесін қолдау, ақпараттық ресурстарды кеңейту, технологиялық инфрақұрылымды жетілдіру, оқырмандар үшін тиімді ақпараттық орта құру және ақпараттық қызмет көрсету сапасын арттыру.',
          'canon_mission_cta' => 'Каталогты ашу',
          'canon_stats' => [
              ['value' => '3', 'label' => 'Интерфейс тілдері'],
          ],
          'canon_collection_header_title' => 'Қор профилі',
          'canon_collection_header_body' => 'Қор Қ. Құлажанов атындағы Қазақ технология және бизнес университеті академиялық бағдарламасының төңірегінде құрылған және төрт негізгі бағытты қамтиды.',
          'canon_directory_title' => 'Институционалдық анықтамалық',
          'canon_areas_material_icons' => [
              'technology' => 'science',
              'economy' => 'business_center',
              'humanities' => 'history_edu',
              'college' => 'school',
          ],
      ],
      'en' => [
          'title' => 'About — Kazakh University of Technology and Business named after K. Kulazhanov',
          'collection_areas' => [
              [
                  'slug' => 'technology',
                  'title' => 'Engineering and technology',
                  'body' => 'Engineering disciplines, computer science, applied mathematics, and technology tracks — the core of the university\'s technology fund.',
              ],
              [
                  'slug' => 'economy',
                  'title' => 'Economics, management and law',
                  'body' => 'Economics and management disciplines, finance, law, and adjacent social sciences — the profile fund for the economic track.',
              ],
              [
                  'slug' => 'humanities',
                  'title' => 'Social sciences and humanities',
                  'body' => 'Languages, history, pedagogy, and general-education materials that support teaching and research beyond the profile programmes.',
              ],
              [
                  'slug' => 'college',
                  'title' => 'College teaching materials',
                  'body' => 'Textbooks, reference works, and teaching methodology for the college programmes, including pre-university preparation.',
              ],
          ],
          'directory_rows' => [
              [
                  'slug' => 'rules',
                  'title' => 'Library rules',
                  'body' => 'Rules for using the collection, digital materials, and the reading rooms.',
                  'href' => '/rules',
                  'cta' => 'Open the rules',
              ],
              [
                  'slug' => 'leadership',
                  'title' => 'Library leadership',
                  'body' => 'The leadership team of the university library and their areas of responsibility.',
                  'href' => '/leadership',
                  'cta' => 'Open the section',
              ],
              [
                  'slug' => 'contacts',
                  'title' => 'Contacts and location',
                  'body' => 'Verified address, opening-hours information, and ways to reach the library.',
                  'href' => '/contacts',
                  'cta' => 'Open contacts',
              ],
          ],
          // Cluster B.5 — canonical /about (docs/design-exports/about_library_canonical).
          'canon_hero_eyebrow' => 'Institution',
          'canon_hero_title_a' => 'Preserving Knowledge.',
          'canon_hero_title_b' => 'Supporting Research.',
          'canon_hero_body' => 'The Scientific Library is the university’s information centre for electronic resources, learning support, library services, and database access.',
          'canon_hero_badge_title' => 'Institutional library',
          'canon_hero_badge_body' => 'Supporting the academic programmes of the Kazakh University of Technology and Business named after K. Kulazhanov university and college campus.',
          'canon_mission_title' => 'Our Mission',
          'canon_mission_body' => 'To support the university’s educational system, expand information resources, improve technological infrastructure, create an effective information environment for readers, and raise the quality of information services.',
          'canon_mission_cta' => 'Open catalog',
          'canon_stats' => [
              ['value' => '3', 'label' => 'Interface languages'],
          ],
          'canon_collection_header_title' => 'The Collection Profile',
          'canon_collection_header_body' => 'The collection is built around the university\'s real academic programme and covers four core areas.',
          'canon_directory_title' => 'Institutional Directory',
          'canon_areas_material_icons' => [
              'technology' => 'science',
              'economy' => 'business_center',
              'humanities' => 'history_edu',
              'college' => 'school',
          ],
      ],
  ][$lang];
@endphp

@section('title', $copy['title'])
@section('meta_description', $copy['canon_hero_body'])

@section('content')
  {{-- Cluster B.5 — canonical-exact /about rebuild per docs/design-exports/about_library_canonical.
       Markers: about-canonical-hero, about-canonical-mission-stats,
       about-canonical-collection, about-canonical-directory. --}}
  <div class="about-canonical">
    <section class="about-canonical__section about-canonical__hero" data-section="about-canonical-hero">
      <div class="about-canonical__hero-copy">
        <div class="about-canonical__eyebrow">
          <span class="material-symbols-outlined" aria-hidden="true">account_balance</span>
          <span>{{ $copy['canon_hero_eyebrow'] }}</span>
        </div>
        <h1 class="about-canonical__display">
          {{ $copy['canon_hero_title_a'] }}<br>
          <span class="about-canonical__display-italic">{{ $copy['canon_hero_title_b'] }}</span>
        </h1>
        <p class="about-canonical__lead">{{ $copy['canon_hero_body'] }}</p>
      </div>
      <div class="about-canonical__hero-media">
        <div class="about-canonical__hero-frame" aria-hidden="true">
          <div class="about-canonical__hero-frame-inner">
            <span class="material-symbols-outlined about-canonical__hero-frame-glyph" aria-hidden="true">local_library</span>
          </div>
          <div class="about-canonical__hero-frame-overlay"></div>
        </div>
        <div class="about-canonical__hero-badge" data-test-id="about-canonical-hero-badge">
          <p class="about-canonical__hero-badge-title">{{ $copy['canon_hero_badge_title'] }}</p>
          <p class="about-canonical__hero-badge-body">{{ $copy['canon_hero_badge_body'] }}</p>
        </div>
      </div>
    </section>

    <section class="about-canonical__section about-canonical__bento" data-section="about-canonical-mission-stats">
      <div class="about-canonical__mission-card">
        <div>
          <h2 class="about-canonical__mission-title">{{ $copy['canon_mission_title'] }}</h2>
          <p class="about-canonical__mission-body">{{ $copy['canon_mission_body'] }}</p>
        </div>
        <div class="about-canonical__mission-cta-row">
          <a class="about-canonical__mission-cta" href="{{ $routeWithLang('/catalog') }}" data-test-id="about-canonical-mission-cta">
            {{ $copy['canon_mission_cta'] }}
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
          </a>
        </div>
      </div>
      <div class="about-canonical__stats-card">
        <div class="about-canonical__stats-glow" aria-hidden="true"></div>
        <div class="about-canonical__stats-inner">
          @foreach($copy['canon_stats'] as $stat)
            <div class="about-canonical__stat" data-stat-slot>
              <p class="about-canonical__stat-value">{{ $stat['value'] }}</p>
              <p class="about-canonical__stat-label">{{ $stat['label'] }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="about-canonical__section about-canonical__collection" data-section="about-canonical-collection">
      <div class="about-canonical__collection-head">
        <h2 class="about-canonical__collection-title">{{ $copy['canon_collection_header_title'] }}</h2>
        <p class="about-canonical__collection-body">{{ $copy['canon_collection_header_body'] }}</p>
      </div>
      <div class="about-canonical__collection-grid">
        @foreach($copy['collection_areas'] as $area)
          <article class="about-canonical__area" data-collection-area data-area-slug="{{ $area['slug'] }}">
            <div class="about-canonical__area-icon" aria-hidden="true">
              <span class="material-symbols-outlined">{{ $copy['canon_areas_material_icons'][$area['slug']] ?? 'menu_book' }}</span>
            </div>
            <h3 class="about-canonical__area-title">{{ $area['title'] }}</h3>
            <p class="about-canonical__area-body">{{ $area['body'] }}</p>
          </article>
        @endforeach
      </div>
    </section>

    <section class="about-canonical__section about-canonical__directory" data-section="about-canonical-directory">
      <h2 class="about-canonical__directory-title">{{ $copy['canon_directory_title'] }}</h2>
      <div class="about-canonical__directory-list">
        @foreach($copy['directory_rows'] as $row)
          <div class="about-canonical__directory-row" data-directory-slot data-directory-slug="{{ $row['slug'] }}">
            <div class="about-canonical__directory-col about-canonical__directory-col--title">
              <h3>{{ $row['title'] }}</h3>
            </div>
            <div class="about-canonical__directory-col about-canonical__directory-col--body">
              <p>{{ $row['body'] }}</p>
            </div>
            <div class="about-canonical__directory-col about-canonical__directory-col--action">
              <a
                class="about-canonical__directory-arrow"
                href="{{ $routeWithLang($row['href']) }}"
                aria-label="{{ $row['cta'] }}"
                data-test-id="about-canonical-directory-link-{{ $row['slug'] }}"
              >
                <span class="material-symbols-outlined" aria-hidden="true">north_east</span>
              </a>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  </div>
@endsection

@section('head')
<style>
  /* Cluster B.5 — canonical-exact /about (docs/design-exports/about_library_canonical).
     Scoped under .about-canonical; /contacts is served by a standalone view. */
  .about-canonical {
    max-width: 1280px;
    margin: 0 auto;
    padding: 96px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .about-canonical {
      padding: 128px 24px 96px;
    }
  }

  @media (min-width: 1024px) {
    .about-canonical {
      padding-left: 32px;
      padding-right: 32px;
    }
  }

  .about-canonical__section {
    margin-bottom: 128px;
  }

  .about-canonical__section:last-child {
    margin-bottom: 80px;
  }

  /* Hero — 3/5 + 2/5 split, eyebrow + display heading + lead + image with badge */
  .about-canonical__hero {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 48px;
    margin-top: 48px;
  }

  @media (min-width: 768px) {
    .about-canonical__hero {
      flex-direction: row;
      margin-top: 80px;
    }
  }

  .about-canonical__hero-copy {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 32px;
    width: 100%;
  }

  @media (min-width: 768px) {
    .about-canonical__hero-copy {
      width: 60%;
    }
  }

  .about-canonical__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 4px;
  }

  .about-canonical__eyebrow .material-symbols-outlined {
    font-size: 16px;
  }

  .about-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 300;
    font-size: 48px;
    line-height: 1.05;
    color: #000613;
    letter-spacing: -0.02em;
    margin: 0 0 0 -2px;
  }

  @media (min-width: 768px) {
    .about-canonical__display {
      font-size: 72px;
    }
  }

  .about-canonical__display-italic {
    font-style: italic;
    color: #001f3f;
  }

  .about-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 18px;
    line-height: 1.65;
    color: #43474e;
    max-width: 640px;
    margin: 0;
  }

  @media (min-width: 768px) {
    .about-canonical__lead {
      font-size: 20px;
    }
  }

  .about-canonical__hero-media {
    position: relative;
    width: 100%;
  }

  @media (min-width: 768px) {
    .about-canonical__hero-media {
      width: 40%;
    }
  }

  .about-canonical__hero-frame {
    position: relative;
    aspect-ratio: 4 / 5;
    background: #e7e8e9;
    border-radius: 12px;
    overflow: hidden;
    z-index: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .about-canonical__hero-frame-inner {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e1e3e4 0%, #d4e3ff 60%, #afc8f0 100%);
    mix-blend-mode: multiply;
    opacity: 0.9;
  }

  .about-canonical__hero-frame-glyph {
    font-size: 96px !important;
    color: #001f3f;
    opacity: 0.55;
  }

  .about-canonical__hero-frame-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,6,19,0.4), transparent);
  }

  .about-canonical__hero-badge {
    position: absolute;
    bottom: -32px;
    left: -32px;
    max-width: 280px;
    background: #ffffff;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 24px 48px rgba(0,6,19,0.04);
    display: none;
  }

  @media (min-width: 1024px) {
    .about-canonical__hero-badge {
      display: block;
    }
  }

  .about-canonical__hero-badge-title {
    font-family: 'Newsreader', serif;
    font-size: 22px;
    color: #000613;
    margin: 0 0 8px;
  }

  .about-canonical__hero-badge-body {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    line-height: 1.5;
    margin: 0;
  }

  /* Mission + Stats bento — 2/3 light card + 1/3 dark stats card */
  .about-canonical__bento {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
  }

  @media (min-width: 768px) {
    .about-canonical__bento {
      grid-template-columns: 2fr 1fr;
    }
  }

  .about-canonical__mission-card {
    background: #f3f4f5;
    border-radius: 12px;
    padding: 32px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 48px;
  }

  @media (min-width: 768px) {
    .about-canonical__mission-card {
      padding: 48px;
    }
  }

  .about-canonical__mission-title {
    font-family: 'Newsreader', serif;
    font-size: 30px;
    color: #000613;
    margin: 0 0 24px;
  }

  .about-canonical__mission-body {
    font-family: 'Manrope', sans-serif;
    font-size: 18px;
    line-height: 1.7;
    color: #43474e;
    max-width: 720px;
    margin: 0;
  }

  .about-canonical__mission-cta-row {
    display: flex;
    gap: 16px;
    margin-top: 32px;
  }

  .about-canonical__mission-cta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border: 0;
    border-bottom: 1px solid rgba(196,198,207,0.35);
    padding: 0 0 4px;
    font-family: 'Manrope', sans-serif;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
    transition: border-color 0.2s ease;
  }

  .about-canonical__mission-cta:hover {
    border-bottom-color: #006a6a;
  }

  .about-canonical__mission-cta .material-symbols-outlined {
    font-size: 16px;
  }

  .about-canonical__stats-card {
    position: relative;
    background: #000613;
    color: #ffffff;
    border-radius: 12px;
    padding: 32px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    overflow: hidden;
  }

  @media (min-width: 768px) {
    .about-canonical__stats-card {
      padding: 48px;
    }
  }

  .about-canonical__stats-glow {
    position: absolute;
    top: 0;
    right: 0;
    width: 256px;
    height: 256px;
    background: #001f3f;
    border-radius: 9999px;
    filter: blur(48px);
    margin: -80px -80px 0 0;
    opacity: 0.5;
  }

  .about-canonical__stats-inner {
    position: relative;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 32px;
  }

  .about-canonical__stat-value {
    font-family: 'Newsreader', serif;
    font-weight: 300;
    font-size: 48px;
    line-height: 1;
    margin: 0 0 8px;
    color: #ffffff;
  }

  .about-canonical__stat-label {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #6f88ad;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    margin: 0;
  }

  /* Collection Profile — 4-column icon+heading+body grid */
  .about-canonical__collection-head {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: baseline;
    gap: 16px;
    margin-bottom: 48px;
  }

  @media (min-width: 768px) {
    .about-canonical__collection-head {
      flex-direction: row;
    }
  }

  .about-canonical__collection-title {
    font-family: 'Newsreader', serif;
    font-size: 36px;
    color: #000613;
    margin: 0;
  }

  .about-canonical__collection-body {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    color: #43474e;
    max-width: 420px;
    margin: 0;
    line-height: 1.55;
  }

  .about-canonical__collection-grid {
    display: grid;
    grid-template-columns: 1fr;
    column-gap: 32px;
    row-gap: 64px;
  }

  @media (min-width: 768px) {
    .about-canonical__collection-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (min-width: 1024px) {
    .about-canonical__collection-grid {
      grid-template-columns: repeat(4, 1fr);
    }
  }

  .about-canonical__area {
    cursor: default;
  }

  .about-canonical__area-icon {
    width: 56px;
    height: 56px;
    border-radius: 9999px;
    background: #e7e8e9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
    transition: background-color 0.5s ease, color 0.5s ease;
  }

  .about-canonical__area:hover .about-canonical__area-icon {
    background: #90efef;
  }

  .about-canonical__area-icon .material-symbols-outlined {
    font-size: 28px;
    color: #000613;
    transition: color 0.5s ease;
  }

  .about-canonical__area:hover .about-canonical__area-icon .material-symbols-outlined {
    color: #006e6e;
  }

  .about-canonical__area-title {
    font-family: 'Newsreader', serif;
    font-size: 22px;
    color: #000613;
    margin: 0 0 12px;
  }

  .about-canonical__area-body {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    line-height: 1.6;
    margin: 0;
  }

  /* Institutional Directory — asymmetric list with NE arrow circles */
  .about-canonical__directory {
    background: #f3f4f5;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 80px;
  }

  @media (min-width: 768px) {
    .about-canonical__directory {
      padding: 64px;
    }
  }

  .about-canonical__directory-title {
    font-family: 'Newsreader', serif;
    font-size: 30px;
    color: #000613;
    margin: 0 0 48px;
  }

  .about-canonical__directory-list {
    display: flex;
    flex-direction: column;
    gap: 48px;
  }

  .about-canonical__directory-row {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 24px;
  }

  @media (min-width: 768px) {
    .about-canonical__directory-row {
      flex-direction: row;
      align-items: center;
      gap: 64px;
    }
  }

  .about-canonical__directory-col--title {
    width: 100%;
  }

  @media (min-width: 768px) {
    .about-canonical__directory-col--title {
      width: 25%;
    }
  }

  .about-canonical__directory-col--title h3 {
    font-family: 'Newsreader', serif;
    font-size: 22px;
    color: #000613;
    margin: 0;
  }

  .about-canonical__directory-col--body {
    width: 100%;
  }

  @media (min-width: 768px) {
    .about-canonical__directory-col--body {
      width: 50%;
    }
  }

  .about-canonical__directory-col--body p {
    font-family: 'Manrope', sans-serif;
    font-size: 15px;
    color: #43474e;
    line-height: 1.55;
    margin: 0;
  }

  .about-canonical__directory-col--action {
    width: 100%;
    display: flex;
    justify-content: flex-start;
  }

  @media (min-width: 768px) {
    .about-canonical__directory-col--action {
      width: 25%;
      justify-content: flex-end;
    }
  }

  .about-canonical__directory-arrow {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    border: 1px solid rgba(196,198,207,0.35);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #000613;
    text-decoration: none;
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
  }

  .about-canonical__directory-arrow:hover {
    background: #000613;
    color: #ffffff;
    border-color: #000613;
  }

  .about-canonical__directory-arrow .material-symbols-outlined {
    font-size: 16px;
  }
</style>
@endsection
