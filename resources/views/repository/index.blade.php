@extends('layouts.public')

@php
  $lang = request()->query('lang', 'ru');
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';
  $activePage = $activePage ?? 'repository';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  $copy = [
    'ru' => [
      'title' => 'Научный репозиторий',
      'hero_eyebrow' => 'Институциональный архив',
      'hero_title' => 'Научный репозиторий университета',
      'hero_body' => 'Дипломные работы, диссертации, статьи и отчёты, прошедшие модерацию библиотеки. Метаданные открыты всем; полный текст доступен авторизованным читателям в контролируемом просмотре — без скачивания.',
      'filter_all' => 'Все работы',
      'works_one' => 'работа',
      'works_few' => 'работы',
      'works_many' => 'работ',
      'details' => 'Карточка работы',
      'access_note' => 'Полный текст — после входа',
      'empty' => 'По выбранному типу пока нет опубликованных работ.',
    ],
    'kk' => [
      'title' => 'Ғылыми репозиторий',
      'hero_eyebrow' => 'Институционалдық мұрағат',
      'hero_title' => 'Университеттің ғылыми репозиторийі',
      'hero_body' => 'Кітапхана модерациясынан өткен дипломдық жұмыстар, диссертациялар, мақалалар мен есептер. Метадеректер барлығына ашық; толық мәтін авторизацияланған оқырмандарға бақыланатын қарау режимінде қолжетімді — жүктеп алусыз.',
      'filter_all' => 'Барлық жұмыстар',
      'works_one' => 'жұмыс',
      'works_few' => 'жұмыс',
      'works_many' => 'жұмыс',
      'details' => 'Жұмыс карточкасы',
      'access_note' => 'Толық мәтін — кіргеннен кейін',
      'empty' => 'Таңдалған түр бойынша жарияланған жұмыстар әзірге жоқ.',
    ],
    'en' => [
      'title' => 'Scholarly Repository',
      'hero_eyebrow' => 'Institutional archive',
      'hero_title' => 'University Scholarly Repository',
      'hero_body' => 'Theses, dissertations, articles, and reports moderated by the library. Metadata is open to everyone; full text is available to signed-in readers in the controlled viewer — no downloads.',
      'filter_all' => 'All works',
      'works_one' => 'work',
      'works_few' => 'works',
      'works_many' => 'works',
      'details' => 'View record',
      'access_note' => 'Full text after sign-in',
      'empty' => 'No published works of the selected type yet.',
    ],
  ][$lang];

  $typeLabels = [
    'ru' => [
      'bachelor_thesis' => 'Дипломная работа',
      'master_dissertation' => 'Магистерская диссертация',
      'phd_dissertation' => 'Докторская диссертация',
      'article' => 'Научная статья',
      'report' => 'Научный отчёт',
      'journal' => 'Журнальная публикация',
    ],
    'kk' => [
      'bachelor_thesis' => 'Дипломдық жұмыс',
      'master_dissertation' => 'Магистрлік диссертация',
      'phd_dissertation' => 'Докторлық диссертация',
      'article' => 'Ғылыми мақала',
      'report' => 'Ғылыми есеп',
      'journal' => 'Журналдық жарияланым',
    ],
    'en' => [
      'bachelor_thesis' => 'Bachelor Thesis',
      'master_dissertation' => 'Master Dissertation',
      'phd_dissertation' => 'PhD Dissertation',
      'article' => 'Research Article',
      'report' => 'Research Report',
      'journal' => 'Journal Publication',
    ],
  ][$lang];

  $totalCount = array_sum($typeCounts);
  $shownCount = count($works);
  $countWord = static function (int $n) use ($copy): string {
      $mod10 = $n % 10; $mod100 = $n % 100;
      if ($mod10 === 1 && $mod100 !== 11) return $copy['works_one'];
      if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) return $copy['works_few'];
      return $copy['works_many'];
  };
@endphp

@section('title', $copy['title'])

@section('content')
  <div class="repository-canonical" data-section="repository-canonical-page">
    <header class="repository-canonical__header" data-section="repository-canonical-hero">
      <p class="repository-canonical__eyebrow">{{ $copy['hero_eyebrow'] }}</p>
      <h1 class="repository-canonical__display">{{ $copy['hero_title'] }}</h1>
      <p class="repository-canonical__lead">{{ $copy['hero_body'] }}</p>
    </header>

    <nav class="repository-canonical__filters" data-section="repository-canonical-filters" aria-label="{{ $copy['filter_all'] }}">
      <a href="{{ $routeWithLang('/repository') }}"
         class="repository-canonical__chip {{ $activeType === null ? 'repository-canonical__chip--active' : '' }}"
         data-test-id="repository-canonical-filter-all">
        {{ $copy['filter_all'] }}
        <span class="repository-canonical__chip-count">{{ $totalCount }}</span>
      </a>
      @foreach($typeLabels as $typeKey => $typeLabel)
        @continue(empty($typeCounts[$typeKey]))
        <a href="{{ $routeWithLang('/repository', ['type' => $typeKey]) }}"
           class="repository-canonical__chip {{ $activeType === $typeKey ? 'repository-canonical__chip--active' : '' }}"
           data-test-id="repository-canonical-filter-{{ str_replace('_', '-', $typeKey) }}">
          {{ $typeLabel }}
          <span class="repository-canonical__chip-count">{{ $typeCounts[$typeKey] }}</span>
        </a>
      @endforeach
    </nav>

    <p class="repository-canonical__summary" data-test-id="repository-canonical-count">
      {{ $shownCount }} {{ $countWord($shownCount) }}
    </p>

    <section class="repository-canonical__list" data-section="repository-canonical-list">
      @forelse($works as $work)
        @php $w = $work['i18n'][$lang]; @endphp
        <article class="repository-canonical__card" data-work-slug="{{ $work['slug'] }}" data-work-type="{{ $work['type'] }}">
          <aside class="repository-canonical__rail">
            <span class="repository-canonical__type">{{ $typeLabels[$work['type']] }}</span>
            <span class="repository-canonical__year">{{ $work['year'] }}</span>
            <span class="repository-canonical__udc" title="УДК">UDC {{ $work['udc'] }}</span>
          </aside>
          <div class="repository-canonical__body">
            <h2 class="repository-canonical__title">
              <a href="{{ $routeWithLang('/repository/' . $work['slug']) }}">{{ $w['title'] }}</a>
            </h2>
            <p class="repository-canonical__authors">{{ implode(' · ', $w['authors']) }}</p>
            <p class="repository-canonical__department">{{ $w['department'] }}</p>
            <p class="repository-canonical__abstract">{{ $w['abstract'] }}</p>
            <div class="repository-canonical__meta-row">
              <ul class="repository-canonical__keywords" role="list">
                @foreach(array_slice($w['keywords'], 0, 3) as $keyword)
                  <li>{{ $keyword }}</li>
                @endforeach
              </ul>
              <div class="repository-canonical__card-actions">
                <span class="repository-canonical__lock">
                  <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                  {{ $copy['access_note'] }}
                </span>
                <a class="repository-canonical__details-link"
                   href="{{ $routeWithLang('/repository/' . $work['slug']) }}"
                   data-test-id="repository-canonical-details-{{ $work['slug'] }}">
                  <span>{{ $copy['details'] }}</span>
                  <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                </a>
              </div>
            </div>
          </div>
        </article>
      @empty
        <p class="repository-canonical__empty">{{ $copy['empty'] }}</p>
      @endforelse
    </section>
  </div>
@endsection

@section('head')
<style>
  .repository-canonical {
    max-width: 1120px;
    margin: 0 auto;
    padding: 80px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .repository-canonical {
      padding: 96px 32px;
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

  .repository-canonical__filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
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
