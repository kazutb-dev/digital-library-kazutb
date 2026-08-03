{{-- Public scholarly repository index — Master.md §20.3.
     Data source: App\Models\Catalog\RepositoryItem (repository_items table),
     served by App\Http\Controllers\RepositoryController::index(). --}}
@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'repository';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  // Preserve the active facets when switching one of them.
  $filterUrl = static function (array $overrides) use ($routeWithLang, $activeType, $activeYear, $search): string {
      return $routeWithLang('/repository', array_merge([
          'q' => $search,
          'work_type' => $activeType,
          'year' => $activeYear,
      ], $overrides));
  };

  $copy = [
    'ru' => [
      'title' => 'Научный репозиторий',
      'hero_eyebrow' => 'Институциональный архив',
      'hero_title' => 'Научный репозиторий университета',
      'hero_body' => 'Дипломные работы, диссертации, статьи и отчёты, прошедшие модерацию библиотеки. Метаданные открыты всем; полный текст доступен авторизованным читателям университета.',
      'filter_all' => 'Все работы',
      'filter_year_all' => 'Все годы',
      'search_placeholder' => 'Название, автор или аннотация',
      'search_submit' => 'Найти',
      'search_reset' => 'Сбросить',
      'works_one' => 'работа',
      'works_few' => 'работы',
      'works_many' => 'работ',
      'details' => 'Карточка работы',
      'access_note' => 'Полный текст — после входа',
      'empty' => 'По заданным условиям опубликованных работ не найдено.',
      'empty_all' => 'В репозитории пока нет опубликованных работ.',
      'page_prev' => 'Назад',
      'page_next' => 'Вперёд',
    ],
    'kk' => [
      'title' => 'Ғылыми репозиторий',
      'hero_eyebrow' => 'Институционалдық мұрағат',
      'hero_title' => 'Университеттің ғылыми репозиторийі',
      'hero_body' => 'Кітапхана модерациясынан өткен дипломдық жұмыстар, диссертациялар, мақалалар мен есептер. Метадеректер барлығына ашық; толық мәтін университеттің авторизацияланған оқырмандарына қолжетімді.',
      'filter_all' => 'Барлық жұмыстар',
      'filter_year_all' => 'Барлық жылдар',
      'search_placeholder' => 'Атауы, авторы немесе аңдатпасы',
      'search_submit' => 'Іздеу',
      'search_reset' => 'Тазалау',
      'works_one' => 'жұмыс',
      'works_few' => 'жұмыс',
      'works_many' => 'жұмыс',
      'details' => 'Жұмыс карточкасы',
      'access_note' => 'Толық мәтін — кіргеннен кейін',
      'empty' => 'Көрсетілген шарттар бойынша жарияланған жұмыстар табылмады.',
      'empty_all' => 'Репозиторийде әзірге жарияланған жұмыстар жоқ.',
      'page_prev' => 'Артқа',
      'page_next' => 'Алға',
    ],
    'en' => [
      'title' => 'Scholarly Repository',
      'hero_eyebrow' => 'Institutional archive',
      'hero_title' => 'University Scholarly Repository',
      'hero_body' => 'Theses, dissertations, articles, and reports moderated by the library. Metadata is open to everyone; the full text is available to authorised university readers.',
      'filter_all' => 'All works',
      'filter_year_all' => 'All years',
      'search_placeholder' => 'Title, author, or abstract',
      'search_submit' => 'Search',
      'search_reset' => 'Reset',
      'works_one' => 'work',
      'works_few' => 'works',
      'works_many' => 'works',
      'details' => 'View record',
      'access_note' => 'Full text after sign-in',
      'empty' => 'No published works match the selected filters.',
      'empty_all' => 'The repository has no published works yet.',
      'page_prev' => 'Previous',
      'page_next' => 'Next',
    ],
  ][$lang];

  $shownCount = $items->total();
  $countWord = static function (int $n) use ($copy): string {
      $mod10 = $n % 10; $mod100 = $n % 100;
      if ($mod10 === 1 && $mod100 !== 11) return $copy['works_one'];
      if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) return $copy['works_few'];
      return $copy['works_many'];
  };

  $hasFilters = $search !== null || $activeType !== null || $activeYear !== null;

  // Windowed page list — two neighbours either side of the current page.
  $currentPage = $items->currentPage();
  $lastPage = $items->lastPage();
  $pageItems = [];
  if ($lastPage > 1) {
      $from = max(1, $currentPage - 2);
      $to = min($lastPage, $currentPage + 2);
      if ($from > 1) { $pageItems[] = 1; }
      if ($from > 2) { $pageItems[] = '…'; }
      for ($p = $from; $p <= $to; $p++) { $pageItems[] = $p; }
      if ($to < $lastPage - 1) { $pageItems[] = '…'; }
      if ($to < $lastPage) { $pageItems[] = $lastPage; }
  }
@endphp

@section('title', $copy['title'])

@section('content')
  <div class="repository-canonical public-v2 repository-v2" data-section="repository-canonical-page">
    <header class="public-v2__hero repository-canonical__header" data-section="repository-canonical-hero">
      <div class="public-v2__inset public-v2__hero-grid">
      <div>
        <p class="public-v2__kicker">{{ $copy['hero_eyebrow'] }}</p>
        <h1 class="public-v2__title">{{ $copy['hero_title'] }}</h1>
        <p class="public-v2__lead">{{ $copy['hero_body'] }}</p>
      </div>
      <aside class="public-v2__hero-note">
        <strong>{{ $publishedTotal }}</strong>
        <span>{{ $copy['filter_all'] }}</span>
      </aside>
      </div>
    </header>

    <div class="public-v2__body">
    <div class="public-v2__inset">
    <form method="GET" action="/repository" class="repository-canonical__search-form">
      @if($lang !== 'kk')
        <input type="hidden" name="lang" value="{{ $lang }}">
      @endif
      @if($activeType !== null)
        <input type="hidden" name="work_type" value="{{ $activeType }}">
      @endif
      @if($activeYear !== null)
        <input type="hidden" name="year" value="{{ $activeYear }}">
      @endif
      <label class="public-v2__search">
        <span class="material-symbols-outlined" aria-hidden="true">search</span>
        <input type="search" name="q" value="{{ $search }}" placeholder="{{ $copy['search_placeholder'] }}"
               aria-label="{{ $copy['search_placeholder'] }}" data-test-id="repository-canonical-search">
        <button type="submit">{{ $copy['search_submit'] }}</button>
      </label>
      @if($hasFilters)
        <a class="repository-canonical__reset" href="{{ $routeWithLang('/repository') }}"
           data-test-id="repository-canonical-reset">{{ $copy['search_reset'] }}</a>
      @endif
    </form>

    <nav class="repository-canonical__filters" data-section="repository-canonical-filters" aria-label="{{ $copy['filter_all'] }}">
      <a href="{{ $filterUrl(['work_type' => null]) }}"
         class="repository-canonical__chip {{ $activeType === null ? 'repository-canonical__chip--active' : '' }}"
         data-test-id="repository-canonical-filter-all">
        {{ $copy['filter_all'] }}
        <span class="repository-canonical__chip-count">{{ $publishedTotal }}</span>
      </a>
      @foreach(\App\Models\Catalog\RepositoryItem::WORK_TYPES as $typeKey)
        @continue(empty($typeCounts[$typeKey]))
        <a href="{{ $filterUrl(['work_type' => $typeKey]) }}"
           class="repository-canonical__chip {{ $activeType === $typeKey ? 'repository-canonical__chip--active' : '' }}"
           data-test-id="repository-canonical-filter-{{ str_replace('_', '-', $typeKey) }}">
          {{ __('librarian.repository.work_types.'.$typeKey) }}
          <span class="repository-canonical__chip-count">{{ $typeCounts[$typeKey] }}</span>
        </a>
      @endforeach
    </nav>

    @if($years->isNotEmpty())
      <nav class="repository-canonical__filters repository-canonical__filters--years" data-section="repository-canonical-years" aria-label="{{ $copy['filter_year_all'] }}">
        <a href="{{ $filterUrl(['year' => null]) }}"
           class="repository-canonical__chip repository-canonical__chip--year {{ $activeYear === null ? 'repository-canonical__chip--active' : '' }}"
           data-test-id="repository-canonical-year-all">{{ $copy['filter_year_all'] }}</a>
        @foreach($years as $yearValue)
          <a href="{{ $filterUrl(['year' => $yearValue]) }}"
             class="repository-canonical__chip repository-canonical__chip--year {{ $activeYear === $yearValue ? 'repository-canonical__chip--active' : '' }}"
             data-test-id="repository-canonical-year-{{ $yearValue }}">{{ $yearValue }}</a>
        @endforeach
      </nav>
    @endif

    <p class="repository-canonical__summary" data-test-id="repository-canonical-count">
      {{ $shownCount }} {{ $countWord($shownCount) }}
    </p>

    <div class="repository-v2__layout">
    <section class="repository-canonical__list" data-section="repository-canonical-list">
      @forelse($items as $item)
        @php
          $authors = collect($item->authors ?? [])->filter()->values();
          $keywords = collect($item->keywords ?? [])->filter()->values();
        @endphp
        <article class="repository-canonical__card" data-work-id="{{ $item->getKey() }}" data-work-type="{{ $item->work_type }}">
          <aside class="repository-canonical__rail">
            <span class="repository-canonical__type">{{ __('librarian.repository.work_types.'.$item->work_type) }}</span>
            <span class="repository-canonical__year">{{ $item->year ?? '—' }}</span>
            @if(filled($item->udc_code))
              <span class="repository-canonical__udc" title="{{ __('librarian.repository.fields.udc_code') }}">UDC {{ $item->udc_code }}</span>
            @endif
          </aside>
          <div class="repository-canonical__body">
            <h2 class="repository-canonical__title">
              <a href="{{ $routeWithLang('/repository/' . $item->getKey()) }}">{{ $item->title }}</a>
            </h2>
            <p class="repository-canonical__authors">{{ $authors->isNotEmpty() ? $authors->implode(' · ') : '—' }}</p>
            <p class="repository-canonical__department">{{ $item->department ?: '—' }}</p>
            @if(filled($item->abstract))
              <p class="repository-canonical__abstract">{{ \Illuminate\Support\Str::limit($item->abstract, 320) }}</p>
            @endif
            <div class="repository-canonical__meta-row">
              <ul class="repository-canonical__keywords" role="list">
                @foreach($keywords->take(3) as $keyword)
                  <li>{{ $keyword }}</li>
                @endforeach
              </ul>
              <div class="repository-canonical__card-actions">
                <span class="repository-canonical__lock">
                  <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                  {{ $copy['access_note'] }}
                </span>
                <a class="repository-canonical__details-link"
                   href="{{ $routeWithLang('/repository/' . $item->getKey()) }}"
                   data-test-id="repository-canonical-details-{{ $item->getKey() }}">
                  <span>{{ $copy['details'] }}</span>
                  <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </a>
              </div>
            </div>
          </div>
        </article>
      @empty
        <div class="public-v2__empty" data-test-id="repository-canonical-empty">
          <span class="material-symbols-outlined" aria-hidden="true">article</span>
          <h3>{{ $hasFilters ? $copy['empty'] : $copy['empty_all'] }}</h3>
        </div>
      @endforelse

      @if($items->hasPages())
        <nav class="repository-canonical__pagination" aria-label="{{ $copy['filter_all'] }}" data-section="repository-canonical-pagination">
          @if($items->currentPage() > 1)
            <a href="{{ $filterUrl(['page' => $items->currentPage() - 1]) }}" rel="prev">{{ $copy['page_prev'] }}</a>
          @endif
          @foreach($pageItems as $pageNumber)
            @if($pageNumber === '…')
              <span aria-hidden="true">…</span>
            @else
              <a href="{{ $filterUrl(['page' => $pageNumber === 1 ? null : $pageNumber]) }}"
                 aria-current="{{ $pageNumber === $currentPage ? 'page' : 'false' }}"
                 class="{{ $pageNumber === $currentPage ? 'is-active' : '' }}">{{ $pageNumber }}</a>
            @endif
          @endforeach
          @if($items->currentPage() < $items->lastPage())
            <a href="{{ $filterUrl(['page' => $items->currentPage() + 1]) }}" rel="next">{{ $copy['page_next'] }}</a>
          @endif
        </nav>
      @endif
    </section>
    <aside class="repository-v2__aside">
      <strong>{{ $copy['hero_eyebrow'] }}</strong>
      <p>{{ $copy['hero_body'] }}</p>
      <p>{{ $copy['access_note'] }}</p>
    </aside>
    </div>
    </div>
    </div>
  </div>
@endsection

@section('head')
<style>
  .repository-canonical {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 0;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .repository-canonical {
      padding: 0;
    }
  }

  .repository-canonical__header {
    margin-bottom: 56px;
    max-width: 760px;
  }

  .repository-canonical__eyebrow {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #006a6a;
    margin: 0 0 16px;
  }

  .repository-canonical__display {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 44px;
    line-height: 1.08;
    letter-spacing: -0.02em;
    color: #000613;
    margin: 0 0 24px;
  }

  @media (min-width: 768px) {
    .repository-canonical__display {
      font-size: 56px;
    }
  }

  .repository-canonical__lead {
    font-size: 18px;
    line-height: 1.65;
    color: #43474e;
    margin: 0;
    max-width: 680px;
  }

  .repository-canonical__search-form {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
  }

  .repository-canonical__search-form .public-v2__search {
    flex: 1 1 320px;
  }

  .repository-canonical__reset {
    font-size: 13px;
    font-weight: 600;
    color: #43474e;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    padding-bottom: 2px;
    transition: border-color 0.2s ease, color 0.2s ease;
  }

  .repository-canonical__reset:hover {
    color: #006a6a;
    border-bottom-color: #006a6a;
  }

  .repository-canonical__filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
  }

  .repository-canonical__filters--years {
    margin-top: -6px;
  }

  .repository-canonical__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border: 1px solid rgba(196, 198, 207, 0.6);
    border-radius: 6px;
    background: #ffffff;
    color: #191c1d;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
  }

  .repository-canonical__chip--year {
    padding: 7px 14px;
    font-size: 12.5px;
  }

  .repository-canonical__chip:hover {
    border-color: #006a6a;
    color: #006a6a;
  }

  .repository-canonical__chip--active {
    background: #006a6a;
    border-color: #006a6a;
    color: #ffffff;
  }

  .repository-canonical__chip--active:hover {
    color: #ffffff;
  }

  .repository-canonical__chip-count {
    font-size: 11.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    background: rgba(0, 106, 106, 0.1);
    color: #006a6a;
  }

  .repository-canonical__chip--active .repository-canonical__chip-count {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
  }

  .repository-canonical__summary {
    margin: 0 0 32px;
    font-size: 13px;
    color: #43474e;
  }

  .repository-canonical__list {
    display: flex;
    flex-direction: column;
    gap: 40px;
  }

  .repository-canonical__card {
    background: #ffffff;
    padding: 32px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 28px;
    min-width: 0;
    transition: background-color 0.3s ease;
  }

  @media (min-width: 768px) {
    .repository-canonical__card {
      flex-direction: row;
      gap: 32px;
    }
  }

  .repository-canonical__card:hover {
    background: #eef1f1;
  }

  .repository-canonical__rail {
    flex-shrink: 0;
    border-left: 4px solid #006a6a;
    padding: 8px 0 8px 24px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    min-width: 0;
  }

  @media (min-width: 768px) {
    .repository-canonical__rail {
      width: 25%;
    }
  }

  .repository-canonical__type {
    font-size: 13px;
    font-weight: 600;
    color: #006a6a;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }

  .repository-canonical__year {
    font-family: 'Newsreader', serif;
    font-size: 30px;
    line-height: 1.1;
    color: #000613;
  }

  .repository-canonical__udc {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: #43474e;
    padding: 3px 8px;
    border: 1px solid rgba(196, 198, 207, 0.7);
    border-radius: 4px;
  }

  .repository-canonical__body {
    flex: 1;
    min-width: 0;
  }

  .repository-canonical__title {
    font-family: 'Newsreader', serif;
    font-size: 24px;
    line-height: 1.25;
    margin: 0 0 10px;
  }

  .repository-canonical__title a {
    color: #000613;
    text-decoration: none;
    transition: color 0.3s ease;
  }

  .repository-canonical__title a:hover {
    color: #006a6a;
  }

  .repository-canonical__authors {
    font-size: 14.5px;
    font-weight: 600;
    color: #191c1d;
    margin: 0 0 4px;
  }

  .repository-canonical__department {
    font-size: 13px;
    color: #43474e;
    margin: 0 0 16px;
  }

  .repository-canonical__abstract {
    font-size: 15px;
    line-height: 1.65;
    color: #43474e;
    margin: 0 0 20px;
    max-width: 680px;
  }

  .repository-canonical__meta-row {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  @media (min-width: 768px) {
    .repository-canonical__meta-row {
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
    }
  }

  .repository-canonical__keywords {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .repository-canonical__keywords li {
    font-size: 12px;
    font-weight: 500;
    color: #43474e;
    background: #eef1f1;
    border-radius: 999px;
    padding: 4px 12px;
  }

  .repository-canonical__card:hover .repository-canonical__keywords li {
    background: #ffffff;
  }

  .repository-canonical__card-actions {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
  }

  .repository-canonical__lock {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    color: #43474e;
    white-space: nowrap;
  }

  .repository-canonical__lock .material-symbols-outlined {
    font-size: 16px;
  }

  .repository-canonical__details-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    padding-bottom: 2px;
    white-space: nowrap;
    transition: border-color 0.2s ease;
  }

  .repository-canonical__details-link:hover {
    border-bottom-color: #006a6a;
  }

  .repository-canonical__details-link .material-symbols-outlined {
    font-size: 16px;
  }

  .repository-canonical__pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
  }

  .repository-canonical__pagination a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    padding: 8px 12px;
    border: 1px solid rgba(196, 198, 207, 0.6);
    border-radius: 6px;
    background: #ffffff;
    color: #191c1d;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: border-color 0.2s ease, color 0.2s ease;
  }

  .repository-canonical__pagination a:hover {
    border-color: #006a6a;
    color: #006a6a;
  }

  .repository-canonical__pagination a.is-active {
    background: #006a6a;
    border-color: #006a6a;
    color: #ffffff;
  }

  .repository-canonical__empty {
    font-size: 15px;
    color: #43474e;
    background: #ffffff;
    border-radius: 8px;
    padding: 32px;
    margin: 0;
  }
</style>
@endsection
