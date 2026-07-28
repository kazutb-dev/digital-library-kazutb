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
      'back' => 'К репозиторию',
      'abstract_heading' => 'Аннотация',
      'meta_heading' => 'Метаданные работы',
      'meta_faculty' => 'Факультет',
      'meta_department' => 'Кафедра',
      'meta_udc' => 'Индекс УДК',
      'meta_language' => 'Язык работы',
      'meta_pages' => 'Объём',
      'meta_pages_unit' => 'с.',
      'meta_published' => 'Опубликовано',
      'meta_keywords' => 'Ключевые слова',
      'access_heading' => 'Доступ к полному тексту',
      'access_body' => 'Полный текст открывается авторизованным читателям университета в контролируемом просмотре. Скачивание и копирование файлов не предусмотрены — это условие публикации работ в репозитории.',
      'access_signin' => 'Войти для чтения',
      'access_dashboard' => 'Открыть кабинет',
      'related_heading' => 'Похожие работы',
    ],
    'kk' => [
      'back' => 'Репозиторийге оралу',
      'abstract_heading' => 'Аңдатпа',
      'meta_heading' => 'Жұмыс метадеректері',
      'meta_faculty' => 'Факультет',
      'meta_department' => 'Кафедра',
      'meta_udc' => 'ӘОЖ индексі',
      'meta_language' => 'Жұмыс тілі',
      'meta_pages' => 'Көлемі',
      'meta_pages_unit' => 'б.',
      'meta_published' => 'Жарияланды',
      'meta_keywords' => 'Кілт сөздер',
      'access_heading' => 'Толық мәтінге қолжетімділік',
      'access_body' => 'Толық мәтін университеттің авторизацияланған оқырмандарына бақыланатын қарау режимінде ашылады. Файлдарды жүктеп алу мен көшіру қарастырылмаған — бұл жұмыстарды репозиторийде жариялау шарты.',
      'access_signin' => 'Оқу үшін кіру',
      'access_dashboard' => 'Кабинетті ашу',
      'related_heading' => 'Ұқсас жұмыстар',
    ],
    'en' => [
      'back' => 'Back to Repository',
      'abstract_heading' => 'Abstract',
      'meta_heading' => 'Work metadata',
      'meta_faculty' => 'Faculty',
      'meta_department' => 'Department',
      'meta_udc' => 'UDC index',
      'meta_language' => 'Work language',
      'meta_pages' => 'Extent',
      'meta_pages_unit' => 'pp.',
      'meta_published' => 'Published',
      'meta_keywords' => 'Keywords',
      'access_heading' => 'Full-text access',
      'access_body' => 'The full text opens for signed-in university readers in the controlled viewer. File downloads and copying are not provided — this is a condition of publishing works in the repository.',
      'access_signin' => 'Sign in to read',
      'access_dashboard' => 'Open dashboard',
      'related_heading' => 'Related works',
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

  $langNames = [
    'ru' => ['ru' => 'Русский', 'kk' => 'Казахский', 'en' => 'Английский'],
    'kk' => ['ru' => 'Орыс тілі', 'kk' => 'Қазақ тілі', 'en' => 'Ағылшын тілі'],
    'en' => ['ru' => 'Russian', 'kk' => 'Kazakh', 'en' => 'English'],
  ][$lang];

  $w = $work['i18n'][$lang];
  $isAuthenticated = (bool) session('library.user');
  $publishedDisplay = \Carbon\Carbon::parse($work['published_at'])->format('d.m.Y');
@endphp

@section('title', $w['title'])

@section('content')
  <div class="repository-detail" data-section="repository-detail-page" data-work-slug="{{ $work['slug'] }}">
    <a class="repository-detail__back" href="{{ $routeWithLang('/repository') }}" data-test-id="repository-detail-back">
      <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      <span>{{ $copy['back'] }}</span>
    </a>

    <header class="repository-detail__header" data-section="repository-detail-header">
      <p class="repository-detail__eyebrow">{{ $typeLabels[$work['type']] }} · {{ $work['year'] }}</p>
      <h1 class="repository-detail__display">{{ $w['title'] }}</h1>
      <p class="repository-detail__authors">{{ implode(' · ', $w['authors']) }}</p>
    </header>

    <div class="repository-detail__columns">
      <div class="repository-detail__main">
        <section class="repository-detail__section" data-section="repository-detail-abstract">
          <h2 class="repository-detail__section-heading">{{ $copy['abstract_heading'] }}</h2>
          <p class="repository-detail__abstract">{{ $w['abstract'] }}</p>
        </section>

        <section class="repository-detail__section repository-detail__access" data-section="repository-detail-access">
          <h2 class="repository-detail__section-heading">
            <span class="material-symbols-outlined" aria-hidden="true">lock</span>
            {{ $copy['access_heading'] }}
          </h2>
          <p class="repository-detail__access-body">{{ $copy['access_body'] }}</p>
          @if($isAuthenticated)
            <a class="repository-detail__access-cta" href="{{ $routeWithLang('/dashboard') }}" data-test-id="repository-detail-access-cta">
              {{ $copy['access_dashboard'] }}
            </a>
          @else
            <a class="repository-detail__access-cta" href="{{ $routeWithLang('/login') }}" data-test-id="repository-detail-access-cta">
              {{ $copy['access_signin'] }}
            </a>
          @endif
        </section>

        @if($related->isNotEmpty())
          <section class="repository-detail__section" data-section="repository-detail-related">
            <h2 class="repository-detail__section-heading">{{ $copy['related_heading'] }}</h2>
            <div class="repository-detail__related-grid">
              @foreach($related as $relatedWork)
                @php $rw = $relatedWork['i18n'][$lang]; @endphp
                <a class="repository-detail__related-card"
                   href="{{ $routeWithLang('/repository/' . $relatedWork['slug']) }}"
                   data-test-id="repository-detail-related-{{ $relatedWork['slug'] }}">
                  <span class="repository-detail__related-eyebrow">{{ $typeLabels[$relatedWork['type']] }} · {{ $relatedWork['year'] }}</span>
                  <span class="repository-detail__related-title">{{ $rw['title'] }}</span>
                  <span class="repository-detail__related-authors">{{ implode(' · ', $rw['authors']) }}</span>
                </a>
              @endforeach
            </div>
          </section>
        @endif
      </div>

      <aside class="repository-detail__aside" data-section="repository-detail-meta" aria-label="{{ $copy['meta_heading'] }}">
        <h2 class="repository-detail__aside-heading">{{ $copy['meta_heading'] }}</h2>
        <dl class="repository-detail__meta">
          <div>
            <dt>{{ $copy['meta_faculty'] }}</dt>
            <dd>{{ $w['faculty'] }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_department'] }}</dt>
            <dd>{{ $w['department'] }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_udc'] }}</dt>
            <dd><span class="repository-detail__udc">{{ $work['udc'] }}</span></dd>
          </div>
          <div>
            <dt>{{ $copy['meta_language'] }}</dt>
            <dd>{{ $langNames[$work['work_language']] }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_pages'] }}</dt>
            <dd>{{ $work['pages'] }} {{ $copy['meta_pages_unit'] }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_published'] }}</dt>
            <dd><time datetime="{{ $work['published_at'] }}">{{ $publishedDisplay }}</time></dd>
          </div>
          <div>
            <dt>{{ $copy['meta_keywords'] }}</dt>
            <dd>
              <ul class="repository-detail__keywords" role="list">
                @foreach($w['keywords'] as $keyword)
                  <li>{{ $keyword }}</li>
                @endforeach
              </ul>
            </dd>
          </div>
        </dl>
      </aside>
    </div>
  </div>
@endsection

@section('head')
<style>
  .repository-detail {
    max-width: 1120px;
    margin: 0 auto;
    padding: 80px 16px 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .repository-detail {
      padding: 96px 32px;
    }
  }

  .repository-detail__back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #006a6a;
    text-decoration: none;
    margin-bottom: 40px;
    border-bottom: 1px solid transparent;
    padding-bottom: 2px;
    transition: border-color 0.2s ease;
  }

  .repository-detail__back:hover {
    border-bottom-color: #006a6a;
  }

  .repository-detail__back .material-symbols-outlined {
    font-size: 18px;
  }

  .repository-detail__header {
    max-width: 820px;
    margin-bottom: 56px;
  }

  .repository-detail__eyebrow {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #006a6a;
    margin: 0 0 16px;
  }

  .repository-detail__display {
    font-family: 'Newsreader', serif;
    font-weight: 400;
    font-size: 36px;
    line-height: 1.15;
    letter-spacing: -0.015em;
    color: #000613;
    margin: 0 0 18px;
  }

  @media (min-width: 768px) {
    .repository-detail__display {
      font-size: 46px;
    }
  }

  .repository-detail__authors {
    font-size: 16px;
    font-weight: 600;
    color: #191c1d;
    margin: 0;
  }

  .repository-detail__columns {
    display: grid;
    grid-template-columns: 1fr;
    gap: 48px;
  }

  @media (min-width: 1024px) {
    .repository-detail__columns {
      grid-template-columns: minmax(0, 1fr) 320px;
      gap: 64px;
      align-items: start;
    }
  }

  .repository-detail__main {
    display: flex;
    flex-direction: column;
    gap: 48px;
    min-width: 0;
  }

  .repository-detail__section-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Newsreader', serif;
    font-size: 24px;
    font-weight: 400;
    color: #000613;
    margin: 0 0 16px;
  }

  .repository-detail__section-heading .material-symbols-outlined {
    font-size: 22px;
    color: #006a6a;
  }

  .repository-detail__abstract {
    font-size: 16px;
    line-height: 1.7;
    color: #43474e;
    margin: 0;
    max-width: 680px;
  }

  .repository-detail__access {
    background: #ffffff;
    border-left: 4px solid #006a6a;
    border-radius: 8px;
    padding: 28px 32px;
  }

  .repository-detail__access-body {
    font-size: 14.5px;
    line-height: 1.65;
    color: #43474e;
    margin: 0 0 20px;
    max-width: 620px;
  }

  .repository-detail__access-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 28px;
    background: #006a6a;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    transition: background-color 0.2s ease;
  }

  .repository-detail__access-cta:hover {
    background: #00524f;
  }

  .repository-detail__related-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
  }

  @media (min-width: 640px) {
    .repository-detail__related-grid {
      grid-template-columns: 1fr 1fr;
    }
  }

  .repository-detail__related-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #ffffff;
    border-radius: 8px;
    padding: 24px;
    text-decoration: none;
    transition: background-color 0.3s ease;
  }

  .repository-detail__related-card:hover {
    background: #eef1f1;
  }

  .repository-detail__related-eyebrow {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #006a6a;
  }

  .repository-detail__related-title {
    font-family: 'Newsreader', serif;
    font-size: 18px;
    line-height: 1.3;
    color: #000613;
  }

  .repository-detail__related-authors {
    font-size: 13px;
    color: #43474e;
  }

  .repository-detail__aside {
    background: #ffffff;
    border-radius: 8px;
    padding: 28px;
  }

  .repository-detail__aside-heading {
    font-family: 'Newsreader', serif;
    font-size: 20px;
    font-weight: 400;
    color: #000613;
    margin: 0 0 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e1e3e4;
  }

  .repository-detail__meta {
    display: flex;
    flex-direction: column;
    gap: 18px;
    margin: 0;
  }

  .repository-detail__meta dt {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #43474e;
    margin-bottom: 4px;
  }

  .repository-detail__meta dd {
    font-size: 14.5px;
    line-height: 1.5;
    color: #191c1d;
    margin: 0;
  }

  .repository-detail__udc {
    display: inline-block;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 3px 10px;
    border: 1px solid rgba(196, 198, 207, 0.7);
    border-radius: 4px;
  }

  .repository-detail__keywords {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 4px 0 0;
    padding: 0;
    list-style: none;
  }

  .repository-detail__keywords li {
    font-size: 12px;
    font-weight: 500;
    color: #43474e;
    background: #eef1f1;
    border-radius: 999px;
    padding: 4px 12px;
  }
</style>
@endsection
