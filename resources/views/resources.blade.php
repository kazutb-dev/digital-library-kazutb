@extends('layouts.public')

@php
  // /resources now follows the same public-v2 editorial rhythm as /repository:
  // compact page hero, search, chips, count summary, resource records, and aside.
  // Data source remains App\Services\ExternalResourceService, fed by
  // config/external_resources.php.

  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'resources';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
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
  $allResources = $premiumResources
      ->map(static function ($resource) {
          $resource['resource_group'] = 'premium';
          return $resource;
      })
      ->concat($openResources->map(static function ($resource) {
          $resource['resource_group'] = 'open';
          return $resource;
      }))
      ->values();

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
          'title' => 'Институциональные ресурсы — KazUTB',
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
          'filter_all' => 'Все ресурсы',
          'search_placeholder' => 'Поиск по ресурсам, базам данных и поставщикам',
          'resources_one' => 'ресурс',
          'resources_few' => 'ресурса',
          'resources_many' => 'ресурсов',
          'access_remote_auth' => 'Удалённый доступ',
          'access_campus' => 'Доступ в кампусе',
          'access_open' => 'Открытый доступ',
          'provider_label' => 'Поставщик',
      ],
      'kk' => [
          'title' => 'Институционалдық ресурстар — KazUTB',
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
          'filter_all' => 'Барлық ресурстар',
          'search_placeholder' => 'Ресурстар, дерекқорлар және провайдерлер бойынша іздеу',
          'resources_one' => 'ресурс',
          'resources_few' => 'ресурс',
          'resources_many' => 'ресурс',
          'access_remote_auth' => 'Қашықтан қол жеткізу',
          'access_campus' => 'Кампус ішіндегі қолжетімділік',
          'access_open' => 'Ашық қол жеткізу',
          'provider_label' => 'Провайдер',
      ],
      'en' => [
          'title' => 'Institutional Resources — KazUTB',
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
          'filter_all' => 'All resources',
          'search_placeholder' => 'Search resources, databases, and providers',
          'resources_one' => 'resource',
          'resources_few' => 'resources',
          'resources_many' => 'resources',
          'access_remote_auth' => 'Remote access',
          'access_campus' => 'Campus access',
          'access_open' => 'Open access',
          'provider_label' => 'Provider',
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
  $resourceCount = $allResources->count();
  $countWord = static function (int $n) use ($copy, $lang): string {
      if ($lang !== 'kk') {
          return $n === 1 ? $copy['resources_one'] : $copy['resources_many'];
      }
      $mod10 = $n % 10;
      $mod100 = $n % 100;
      if ($mod10 === 1 && $mod100 !== 11) return $copy['resources_one'];
      if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) return $copy['resources_few'];
      return $copy['resources_many'];
  };
  $accessLabels = [
      'remote_auth' => $copy['access_remote_auth'],
      'campus' => $copy['access_campus'],
      'open' => $copy['access_open'],
  ];
  $resourceWords = [
      'lang' => $lang,
      'one' => $copy['resources_one'],
      'few' => $copy['resources_few'],
      'many' => $copy['resources_many'],
  ];
@endphp

@section('title', $copy['title'])

@section('content')
  <div class="resources-canonical public-v2 resources-v2">
    <header class="public-v2__hero resources-canonical__hero" data-section="resources-canonical-hero">
      <div class="public-v2__inset public-v2__hero-grid">
        <div>
          <p class="public-v2__kicker">{{ $copy['hero_eyebrow'] }}</p>
          <h1 class="public-v2__title">
            {{ $copy['hero_title_a'] }} {{ $copy['hero_title_b'] }}
          </h1>
          <p class="public-v2__lead">{{ $copy['hero_body'] }}</p>
        </div>
        <aside class="public-v2__hero-note">
          <strong>{{ $resourceCount }}</strong>
          <span>{{ $copy['filter_all'] }}</span>
        </aside>
      </div>
    </header>

    <div class="public-v2__body resources-canonical__main" data-section="resources-canonical-main">
      <div class="public-v2__inset">
        <label class="public-v2__search">
          <span class="material-symbols-outlined" aria-hidden="true">search</span>
          <input type="search" data-resource-search placeholder="{{ $copy['search_placeholder'] }}">
          <button type="button">{{ $copy['premium_cta'] }}</button>
        </label>

        <nav class="repository-canonical__filters resources-v2__filters" data-section="resources-canonical-filters" aria-label="{{ $copy['filter_all'] }}">
          <button class="repository-canonical__chip repository-canonical__chip--active is-active" type="button" data-resource-category="all">
            {{ $copy['filter_all'] }}
            <span class="repository-canonical__chip-count">{{ $resourceCount }}</span>
          </button>
          <button class="repository-canonical__chip" type="button" data-resource-category="premium">
            {{ $copy['premium_title'] }}
            <span class="repository-canonical__chip-count">{{ $premiumResources->count() }}</span>
          </button>
          <button class="repository-canonical__chip" type="button" data-resource-category="open">
            {{ $copy['open_title'] }}
            <span class="repository-canonical__chip-count">{{ $openResources->count() }}</span>
          </button>
        </nav>

        <p class="repository-canonical__summary resources-v2__summary" data-resource-count>
          {{ $resourceCount }} {{ $countWord($resourceCount) }}
        </p>

        <div class="repository-v2__layout resources-v2__layout">
          <section class="repository-canonical__list resources-canonical__list" data-section="resources-canonical-list">
            @forelse($allResources as $resource)
              @php
                $isPremium = ($resource['resource_group'] ?? null) === 'premium';
                $resourceIcon = $isPremium ? 'lock' : $openIconFor($resource);
                $resourceCta = $isPremium ? $copy['premium_cta'] : $copy['open_cta'];
                $accessLabel = $accessLabels[$resource['access_type'] ?? 'open'] ?? $copy['open_count_label'];
                $logoSrc = ! empty($resource['logo_path'])
                    ? asset('storage/' . ltrim($resource['logo_path'], '/'))
                    : ($resource['logo'] ?? null);
              @endphp
              <article
                class="resources-canonical__card"
                data-resource-card
                data-resource-category="{{ $resource['resource_group'] }}"
                data-resource-slug="{{ $resource['slug'] }}"
                data-resource-access="{{ $resource['access_type'] }}"
                data-test-id="resources-canonical-card-{{ $resource['slug'] }}"
              >
                <aside class="resources-canonical__rail">
                  @if($isPremium)
                    <span class="resources-canonical__type">{{ $copy['premium_title'] }}</span>
                  @endif
                  <span class="resources-canonical__emblem" aria-hidden="true">
                    @if($logoSrc)
                      <img src="{{ $logoSrc }}" alt="" loading="lazy" decoding="async">
                    @else
                      {{ $emblemFor($resource) }}
                    @endif
                  </span>
                  <span class="resources-canonical__access">
                    <span class="material-symbols-outlined" aria-hidden="true">{{ $resourceIcon }}</span>
                    {{ $accessLabel }}
                  </span>
                </aside>
                <div class="resources-canonical__body">
                  <h2 class="resources-canonical__card-title">
                    <a href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer">
                      {{ $resource['title'] }}
                    </a>
                  </h2>
                  <p class="resources-canonical__provider">
                    {{ $copy['provider_label'] }}: {{ $resource['provider'] }}
                  </p>
                  <p class="resources-canonical__card-desc">{{ $resource['description'] }}</p>
                  <div class="resources-canonical__meta-row">
                    <span class="resources-canonical__card-badge">
                      <span class="material-symbols-outlined" aria-hidden="true">{{ $isPremium ? 'vpn_key' : 'public' }}</span>
                      <span>{{ $accessLabel }}</span>
                    </span>
                    <a class="repository-canonical__details-link resources-canonical__card-link"
                       href="{{ $resource['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       data-test-id="resources-canonical-link-{{ $resource['slug'] }}">
                      <span>{{ $resourceCta }}</span>
                      <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                    </a>
                  </div>
                </div>
              </article>
            @empty
              <div class="public-v2__empty">
                <span class="material-symbols-outlined" aria-hidden="true">database_off</span>
                <h3>{{ $copy['filter_all'] }}</h3>
              </div>
            @endforelse
          </section>
          <aside class="repository-v2__aside resources-v2__aside">
            <strong>{{ $copy['off_campus_title'] }}</strong>
            <p>{{ $copy['hero_body'] }}</p>
            <a class="resources-canonical__off-campus-cta"
               href="{{ $routeWithLang('/contacts') }}"
               data-test-id="resources-canonical-off-campus-cta">
              {{ $copy['off_campus_cta'] }} →
            </a>
          </aside>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('head')
<style>
  /* Cluster D — /resources canonical-exact rebuild.
     Scoped to .resources-canonical; mirrors institutional_resources_canonical/code.html. */

  .resources-canonical {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .resources-canonical { padding: 0; }
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

@section('scripts')
<script>
  const resourceSearch = document.querySelector('[data-resource-search]');
  const resourceCategories = document.querySelectorAll('[data-resource-category]');
  const resourceCount = document.querySelector('[data-resource-count]');
  const resourceWords = @json($resourceWords);

  function resourceCountWord(count) {
    if (resourceWords.lang !== 'kk') {
      return count === 1 ? resourceWords.one : resourceWords.many;
    }
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (mod10 === 1 && mod100 !== 11) return resourceWords.one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return resourceWords.few;
    return resourceWords.many;
  }

  function filterResources(category = document.querySelector('[data-resource-category].is-active')?.dataset.resourceCategory || 'all') {
    const query = resourceSearch?.value.trim().toLocaleLowerCase() || '';
    let visibleCount = 0;
    document.querySelectorAll('[data-resource-card]').forEach((card) => {
      const cardCategory = card.dataset.resourceCategory || 'open';
      const categoryMatches = category === 'all' || category === cardCategory;
      const searchMatches = query === '' || card.textContent.toLocaleLowerCase().includes(query);
      const isVisible = categoryMatches && searchMatches;
      card.hidden = !isVisible;
      if (isVisible) visibleCount += 1;
    });
    if (resourceCount) {
      resourceCount.textContent = `${visibleCount} ${resourceCountWord(visibleCount)}`;
    }
  }

  resourceSearch?.addEventListener('input', () => filterResources());
  resourceCategories.forEach((button) => button.addEventListener('click', () => {
    resourceCategories.forEach((item) => {
      item.classList.toggle('repository-canonical__chip--active', item === button);
      item.classList.toggle('is-active', item === button);
    });
    filterResources(button.dataset.resourceCategory);
  }));
</script>
@endsection
