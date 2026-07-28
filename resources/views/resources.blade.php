@extends('layouts.public')

@php
  // Phase 3 Cluster D — /resources canonical-exact rebuild per
  // docs/design-exports/institutional_resources_canonical/code.html.
  //
  // Retired the previous pathways + filter-bar + featured/small-grid + support-section
  // shell; legacy markers (pathways id, filter-bar id, resource-grid data attr, support
  // section id, featured and small card modifiers, pathway panels, core-databases
  // label) are no longer emitted. The new layout mirrors the canonical export:
  //
  //   1. Hero (8/12 + 4/12 off-campus access card)
  //   2. Main two-column layout
  //      2a. Sidebar — "Refine Search" (Discipline + Resource Type, UI-only)
  //      2b. Content stack
  //          • "Premium Databases" — 2-col card grid (subscription databases,
  //            remote_auth + campus access types)
  //          • "Open Access Tools"  — list rows (open access type)
  //
  // Data source unchanged: App\Services\ExternalResourceService, fed by
  // config/external_resources.php — resource title, provider, url, description,
  // access_type are preserved verbatim. Tri-lingual support covers UI chrome
  // (hero, sidebar labels, section headings, access-type labels, chrome copy);
  // resource descriptions stay in their canonical language as supplied by config.

  $lang = request()->query('lang', 'ru');
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'resources';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $queryString = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($queryString !== '' ? ('?' . $queryString) : '');
  };

  $resources = $resources ?? collect();
  $premiumResources = collect($resources)->filter(
      fn ($r) => in_array($r['access_type'] ?? null, ['remote_auth', 'campus'], true)
          && ($r['status'] ?? 'active') !== 'inactive'
  )->values();
  $openResources = collect($resources)->filter(
      fn ($r) => ($r['access_type'] ?? null) === 'open'
          && ($r['status'] ?? 'active') !== 'inactive'
  )->values();

  $openIconFor = static function (array $resource): string {
      $category = $resource['category'] ?? null;
      return match ($category) {
          'open_access' => 'public',
          'analytics' => 'insights',
          'research_database' => 'dataset',
          'electronic_library' => 'menu_book',
          default => 'public',
      };
  };

  $emblemFor = static function (array $resource): string {
      $title = trim((string) ($resource['title'] ?? ''));
      if ($title !== '' && preg_match('/[A-Za-zА-Яа-яЁё]/u', $title, $m)) {
          return mb_strtoupper(mb_substr($m[0], 0, 1));
      }
      $slug = (string) ($resource['slug'] ?? '?');
      return mb_strtoupper(mb_substr($slug, 0, 1));
  };

  $copy = [
      'ru' => [
          'title' => 'Институциональные ресурсы — KazUTB Smart Library',
          'hero_eyebrow' => 'Справочник',
          'hero_title_a' => 'Институциональные',
          'hero_title_b' => 'ресурсы',
          'hero_body' => 'Кураторская коллекция академических баз данных, журналов и аналитических инструментов, доступных научному сообществу КазУТБ. Доступ требует институциональной авторизации.',
          'off_campus_title' => 'Доступ вне кампуса',
          'off_campus_cta' => 'Как настроить удалённый доступ',
          'sidebar_title' => 'Фильтр поиска',
          'sidebar_discipline' => 'Дисциплина',
          'sidebar_resource_type' => 'Тип ресурса',
          'discipline_engineering' => 'Инженерия и технологии',
          'discipline_sciences' => 'Естественные науки',
          'discipline_business' => 'Бизнес и экономика',
          'discipline_humanities' => 'Гуманитарные науки',
          'type_journals' => 'Журналы',
          'type_proceedings' => 'Материалы конференций',
          'type_datasets' => 'Наборы данных',
          'premium_title' => 'Премиальные базы данных',
          'premium_count_one' => ':count подписка',
          'premium_count_few' => ':count подписки',
          'premium_count_many' => ':count подписок',
          'premium_badge' => 'Институциональный доступ',
          'premium_cta' => 'Перейти к ресурсу',
          'open_title' => 'Инструменты открытого доступа',
          'open_count_label' => 'Открытый доступ',
          'open_cta' => 'Открыть инструмент',
      ],
      'kk' => [
          'title' => 'Институционалдық ресурстар — KazUTB Smart Library',
          'hero_eyebrow' => 'Анықтамалық',
          'hero_title_a' => 'Институционалдық',
          'hero_title_b' => 'ресурстар',
          'hero_body' => 'KazUTB ғылыми қауымдастығы үшін қолжетімді академиялық дерекқорлар, журналдар және аналитикалық құралдардың кураторлық жинағы. Қолжетімділік үшін институционалдық авторизация қажет.',
          'off_campus_title' => 'Кампустан тыс қол жеткізу',
          'off_campus_cta' => 'Қашықтан қол жеткізуді баптау',
          'sidebar_title' => 'Іздеуді нақтылау',
          'sidebar_discipline' => 'Пән',
          'sidebar_resource_type' => 'Ресурс түрі',
          'discipline_engineering' => 'Инженерия және технологиялар',
          'discipline_sciences' => 'Жаратылыстану ғылымдары',
          'discipline_business' => 'Бизнес және экономика',
          'discipline_humanities' => 'Гуманитарлық ғылымдар',
          'type_journals' => 'Журналдар',
          'type_proceedings' => 'Конференция материалдары',
          'type_datasets' => 'Деректер жиынтықтары',
          'premium_title' => 'Премиум дерекқорлар',
          'premium_count_one' => ':count жазылым',
          'premium_count_few' => ':count жазылым',
          'premium_count_many' => ':count жазылым',
          'premium_badge' => 'Институционалдық қолжетімділік',
          'premium_cta' => 'Ресурсқа өту',
          'open_title' => 'Ашық қол жеткізу құралдары',
          'open_count_label' => 'Ашық қол жеткізу',
          'open_cta' => 'Құралды ашу',
      ],
      'en' => [
          'title' => 'Institutional Resources — KazUTB Smart Library',
          'hero_eyebrow' => 'Directory',
          'hero_title_a' => 'Institutional',
          'hero_title_b' => 'Resources',
          'hero_body' => 'A curated collection of academic databases, journals, and analytical tools accessible to the KazUTB scholarly community. Access requires institutional authentication.',
          'off_campus_title' => 'Off-Campus Access',
          'off_campus_cta' => 'Configure Proxy Settings',
          'sidebar_title' => 'Refine Search',
          'sidebar_discipline' => 'Discipline',
          'sidebar_resource_type' => 'Resource Type',
          'discipline_engineering' => 'Engineering & Tech',
          'discipline_sciences' => 'Natural Sciences',
          'discipline_business' => 'Business & Economics',
          'discipline_humanities' => 'Humanities',
          'type_journals' => 'Journals',
          'type_proceedings' => 'Conference Proceedings',
          'type_datasets' => 'Datasets',
          'premium_title' => 'Premium Databases',
          'premium_count_one' => ':count Subscription',
          'premium_count_few' => ':count Subscriptions',
          'premium_count_many' => ':count Subscriptions',
          'premium_badge' => 'Institutional',
          'premium_cta' => 'Access Resource',
          'open_title' => 'Open Access Tools',
          'open_count_label' => 'Public',
          'open_cta' => 'Open Tool',
      ],
  ][$lang];

  $pluralizePremium = static function (int $n) use ($copy, $lang): string {
      if ($lang === 'ru') {
          $mod10 = $n % 10;
          $mod100 = $n % 100;
          if ($mod10 === 1 && $mod100 !== 11) { $key = 'premium_count_one'; }
          elseif (in_array($mod10, [2,3,4], true) && ! in_array($mod100, [12,13,14], true)) { $key = 'premium_count_few'; }
          else { $key = 'premium_count_many'; }
      } else {
          $key = $n === 1 ? 'premium_count_one' : 'premium_count_few';
      }
      return str_replace(':count', (string) $n, $copy[$key]);
  };
  $premiumCountLabel = $pluralizePremium($premiumResources->count());
@endphp

@section('title', $copy['title'])

@section('content')
  {{-- Cluster D — canonical-exact rebuild of /resources per institutional_resources_canonical.
       Section markers (canonical order): resources-canonical-hero, resources-canonical-main,
       resources-canonical-sidebar, resources-canonical-premium, resources-canonical-open-access. --}}
  <div class="resources-canonical">

    {{-- Hero: 8/12 copy + 4/12 off-campus access card. --}}
    <header class="resources-canonical__hero" data-section="resources-canonical-hero">
      <div class="resources-canonical__hero-copy">
        <span class="resources-canonical__eyebrow">{{ $copy['hero_eyebrow'] }}</span>
        <h1 class="resources-canonical__display">
          {{ $copy['hero_title_a'] }}<br>
          <span class="resources-canonical__display-italic">{{ $copy['hero_title_b'] }}</span>
        </h1>
        <p class="resources-canonical__lead">{{ $copy['hero_body'] }}</p>
      </div>
      <aside class="resources-canonical__hero-aside">
        <div class="resources-canonical__off-campus" data-test-id="resources-canonical-off-campus">
          <div class="resources-canonical__off-campus-icon" aria-hidden="true">
            <span class="material-symbols-outlined">vpn_key</span>
          </div>
          <div class="resources-canonical__off-campus-copy">
            <h3 class="resources-canonical__off-campus-title">{{ $copy['off_campus_title'] }}</h3>
            <a class="resources-canonical__off-campus-cta"
               href="{{ $routeWithLang('/contacts') }}"
               data-test-id="resources-canonical-off-campus-cta">
              {{ $copy['off_campus_cta'] }} →
            </a>
          </div>
        </div>
      </aside>
    </header>

    {{-- Main two-column layout: 1/4 sidebar + 3/4 categorized content. --}}
    <div class="resources-canonical__main" data-section="resources-canonical-main">

      {{-- Refine Search sidebar. Checkboxes are UI-only per canonical export. --}}
      <aside class="resources-canonical__sidebar" data-section="resources-canonical-sidebar">
        <div class="resources-canonical__sidebar-card">
          <h3 class="resources-canonical__sidebar-title">{{ $copy['sidebar_title'] }}</h3>

          <div class="resources-canonical__facet">
            <h4 class="resources-canonical__facet-heading">{{ $copy['sidebar_discipline'] }}</h4>
            <ul class="resources-canonical__facet-list">
              @foreach([
                  'engineering' => $copy['discipline_engineering'],
                  'sciences'    => $copy['discipline_sciences'],
                  'business'    => $copy['discipline_business'],
                  'humanities'  => $copy['discipline_humanities'],
              ] as $slug => $label)
                <li>
                  <label class="resources-canonical__facet-option" data-facet-slot data-facet-type="discipline" data-facet-slug="{{ $slug }}">
                    <input type="checkbox" name="discipline[]" value="{{ $slug }}">
                    <span>{{ $label }}</span>
                  </label>
                </li>
              @endforeach
            </ul>
          </div>

          <hr class="resources-canonical__facet-divider">

          <div class="resources-canonical__facet">
            <h4 class="resources-canonical__facet-heading">{{ $copy['sidebar_resource_type'] }}</h4>
            <ul class="resources-canonical__facet-list">
              @foreach([
                  'journals'    => $copy['type_journals'],
                  'proceedings' => $copy['type_proceedings'],
                  'datasets'    => $copy['type_datasets'],
              ] as $slug => $label)
                <li>
                  <label class="resources-canonical__facet-option" data-facet-slot data-facet-type="resource-type" data-facet-slug="{{ $slug }}">
                    <input type="checkbox" name="resource_type[]" value="{{ $slug }}">
                    <span>{{ $label }}</span>
                  </label>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      </aside>

      {{-- Categorized directory: Premium → card grid, Open Access → list rows. --}}
      <div class="resources-canonical__directory">

        <section class="resources-canonical__section" data-section="resources-canonical-premium">
          <div class="resources-canonical__section-head">
            <h2 class="resources-canonical__section-title">{{ $copy['premium_title'] }}</h2>
            <span class="resources-canonical__section-count" data-test-id="resources-canonical-premium-count">
              {{ $premiumCountLabel }}
            </span>
          </div>
          <div class="resources-canonical__card-grid">
            @foreach($premiumResources as $resource)
              <article
                class="resources-canonical__card"
                data-premium-resource
                data-resource-slug="{{ $resource['slug'] }}"
                data-resource-access="{{ $resource['access_type'] }}"
                data-test-id="resources-canonical-premium-card-{{ $resource['slug'] }}"
              >
                <div class="resources-canonical__card-body">
                  <div class="resources-canonical__card-head">
                    <div class="resources-canonical__card-emblem" aria-hidden="true">
                      <span>{{ $emblemFor($resource) }}</span>
                    </div>
                    <span class="resources-canonical__card-badge">
                      <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                      <span>{{ $copy['premium_badge'] }}</span>
                    </span>
                  </div>
                  <h3 class="resources-canonical__card-title">{{ $resource['title'] }}</h3>
                  <p class="resources-canonical__card-desc">{{ $resource['description'] }}</p>
                </div>
                <div class="resources-canonical__card-foot">
                  <span class="resources-canonical__card-provider">{{ $resource['provider'] }}</span>
                  <a class="resources-canonical__card-link"
                     href="{{ $resource['url'] }}"
                     target="_blank"
                     rel="noopener noreferrer"
                     data-test-id="resources-canonical-premium-link-{{ $resource['slug'] }}">
                    <span>{{ $copy['premium_cta'] }}</span>
                    <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                  </a>
                </div>
              </article>
            @endforeach
          </div>
        </section>

        <div class="resources-canonical__section-spacer" aria-hidden="true"></div>

        <section class="resources-canonical__section" data-section="resources-canonical-open-access">
          <div class="resources-canonical__section-head">
            <h2 class="resources-canonical__section-title">{{ $copy['open_title'] }}</h2>
            <span class="resources-canonical__section-count" data-test-id="resources-canonical-open-count">
              {{ $copy['open_count_label'] }}
            </span>
          </div>
          <div class="resources-canonical__list">
            @foreach($openResources as $resource)
              <div
                class="resources-canonical__row"
                data-open-resource
                data-resource-slug="{{ $resource['slug'] }}"
                data-test-id="resources-canonical-open-row-{{ $resource['slug'] }}"
              >
                <div class="resources-canonical__row-main">
                  <div class="resources-canonical__row-icon" aria-hidden="true">
                    <span class="material-symbols-outlined">{{ $openIconFor($resource) }}</span>
                  </div>
                  <div class="resources-canonical__row-copy">
                    <h3 class="resources-canonical__row-title">{{ $resource['title'] }}</h3>
                    <p class="resources-canonical__row-desc">{{ $resource['description'] }}</p>
                  </div>
                </div>
                <a class="resources-canonical__row-link"
                   href="{{ $resource['url'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   data-test-id="resources-canonical-open-link-{{ $resource['slug'] }}">
                  {{ $copy['open_cta'] }}
                </a>
              </div>
            @endforeach
          </div>
        </section>

      </div>
    </div>
  </div>
@endsection

@section('head')
<style>
  /* Cluster D — /resources canonical-exact rebuild.
     Scoped to .resources-canonical; mirrors institutional_resources_canonical/code.html. */

  .resources-canonical {
    max-width: 1280px;
    margin: 0 auto;
    padding: 96px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .resources-canonical { padding: 128px 32px 96px; }
  }

  /* --- Hero --------------------------------------------------------------- */
  .resources-canonical__hero {
    display: grid;
    grid-template-columns: 1fr;
    gap: 48px;
    align-items: end;
    margin-bottom: 80px;
  }

  @media (min-width: 1024px) {
    .resources-canonical__hero {
      grid-template-columns: repeat(12, minmax(0, 1fr));
    }
  }

  @media (min-width: 1024px) {
    .resources-canonical__hero-copy { grid-column: span 8 / span 8; }
  }

  .resources-canonical__eyebrow {
    display: block;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.15em;
    margin-bottom: 16px;
  }

  .resources-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 48px;
    line-height: 1.05;
    color: #000613;
    letter-spacing: -0.02em;
    margin: 0 0 24px -2px;
  }

  @media (min-width: 768px) {
    .resources-canonical__display { font-size: 60px; }
  }

  .resources-canonical__display-italic {
    font-style: italic;
    color: #001f3f;
  }

  .resources-canonical__lead {
    font-family: 'Manrope', sans-serif;
    font-size: 18px;
    line-height: 1.7;
    color: #43474e;
    max-width: 640px;
    margin: 0;
  }

  .resources-canonical__hero-aside {
    display: flex;
    justify-content: flex-start;
  }

  @media (min-width: 1024px) {
    .resources-canonical__hero-aside {
      grid-column: span 4 / span 4;
      justify-content: flex-end;
    }
  }

  .resources-canonical__off-campus {
    background: #f3f4f5;
    padding: 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    max-width: 360px;
  }

  .resources-canonical__off-campus-icon {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    background: #90efef;
    color: #006e6e;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .resources-canonical__off-campus-title {
    font-family: 'Newsreader', serif;
    font-size: 18px;
    color: #000613;
    margin: 0 0 4px;
  }

  .resources-canonical__off-campus-cta {
    display: inline-block;
    color: #006a6a;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    margin-top: 2px;
  }

  .resources-canonical__off-campus-cta:hover { text-decoration: underline; }

  /* --- Main layout -------------------------------------------------------- */
  .resources-canonical__main {
    display: grid;
    grid-template-columns: 1fr;
    gap: 48px;
  }

  @media (min-width: 1024px) {
    .resources-canonical__main {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }

  /* --- Sidebar ----------------------------------------------------------- */
  .resources-canonical__sidebar { min-width: 0; }

  @media (min-width: 1024px) {
    .resources-canonical__sidebar { grid-column: span 1 / span 1; }
  }

  .resources-canonical__sidebar-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
  }

  @media (min-width: 1024px) {
    .resources-canonical__sidebar-card {
      position: sticky;
      top: 128px;
    }
  }

  .resources-canonical__sidebar-title {
    font-family: 'Newsreader', serif;
    font-size: 20px;
    color: #000613;
    margin: 0 0 24px;
  }

  .resources-canonical__facet-heading {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #191c1d;
    margin: 0 0 12px;
  }

  .resources-canonical__facet-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .resources-canonical__facet-option {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    cursor: pointer;
    transition: color 0.2s ease;
  }

  .resources-canonical__facet-option:hover { color: #000613; }

  .resources-canonical__facet-option input[type="checkbox"] {
    width: 14px;
    height: 14px;
    border: 1px solid #c4c6cf;
    border-radius: 2px;
    accent-color: #006a6a;
  }

  .resources-canonical__facet-divider {
    border: 0;
    border-top: 1px solid rgba(196, 198, 207, 0.2);
    margin: 24px 0;
  }

  /* --- Directory (content column) ---------------------------------------- */
  .resources-canonical__directory {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
  }

  @media (min-width: 1024px) {
    .resources-canonical__directory { grid-column: span 3 / span 3; }
  }

  .resources-canonical__section-head {
    display: flex;
    align-items: baseline;
    gap: 16px;
    margin-bottom: 24px;
  }

  .resources-canonical__section-title {
    font-family: 'Newsreader', serif;
    font-size: 28px;
    color: #000613;
    margin: 0;
  }

  @media (min-width: 768px) {
    .resources-canonical__section-title { font-size: 30px; }
  }

  .resources-canonical__section-count {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    color: #43474e;
    background: #e7e8e9;
    padding: 4px 10px;
    border-radius: 6px;
  }

  .resources-canonical__section-spacer { height: 32px; }

  /* --- Premium card grid ------------------------------------------------- */
  .resources-canonical__card-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
  }

  @media (min-width: 768px) {
    .resources-canonical__card-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  .resources-canonical__card {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    min-height: 240px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: background-color 0.3s ease, transform 0.3s ease;
  }

  .resources-canonical__card:hover {
    background: #e7e8e9;
    transform: translateY(-2px);
  }

  .resources-canonical__card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
  }

  .resources-canonical__card-emblem {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: #e1e3e4;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Newsreader', serif;
    font-size: 20px;
    font-weight: 700;
    color: #000613;
  }

  .resources-canonical__card-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 500;
    color: #091d2e;
    background: #d1e4fb;
    padding: 4px 8px;
    border-radius: 9999px;
  }

  .resources-canonical__card-badge .material-symbols-outlined { font-size: 14px; }

  .resources-canonical__card-title {
    font-family: 'Newsreader', serif;
    font-size: 22px;
    color: #000613;
    margin: 0 0 8px;
    transition: color 0.3s ease;
  }

  .resources-canonical__card:hover .resources-canonical__card-title { color: #006a6a; }

  .resources-canonical__card-desc {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    line-height: 1.6;
    color: #43474e;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .resources-canonical__card-foot {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid rgba(196, 198, 207, 0.25);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .resources-canonical__card-provider {
    font-family: 'Manrope', sans-serif;
    font-size: 11px;
    font-weight: 500;
    color: #74777f;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  .resources-canonical__card-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
  }

  .resources-canonical__card:hover .resources-canonical__card-link { text-decoration: underline; }

  .resources-canonical__card-link .material-symbols-outlined { font-size: 16px; }

  /* --- Open access list -------------------------------------------------- */
  .resources-canonical__list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .resources-canonical__row {
    background: #ffffff;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: background-color 0.3s ease;
  }

  @media (min-width: 768px) {
    .resources-canonical__row {
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
    }
  }

  .resources-canonical__row:hover { background: #e7e8e9; }

  .resources-canonical__row-main {
    display: flex;
    align-items: center;
    gap: 20px;
    min-width: 0;
  }

  .resources-canonical__row-icon {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    background: rgba(144, 239, 239, 0.3);
    color: #006e6e;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .resources-canonical__row-title {
    font-family: 'Newsreader', serif;
    font-size: 18px;
    color: #000613;
    margin: 0 0 4px;
  }

  .resources-canonical__row-desc {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    line-height: 1.55;
    color: #43474e;
    margin: 0;
  }

  .resources-canonical__row-link {
    display: inline-block;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #000613;
    background: transparent;
    border: 1px solid rgba(196, 198, 207, 0.4);
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.2s ease, border-color 0.2s ease;
  }

  .resources-canonical__row-link:hover {
    color: #006a6a;
    border-color: rgba(0, 106, 106, 0.5);
  }
</style>
@endsection
