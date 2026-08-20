{{-- Public scholarly repository record — Master.md 20.3.
     Data source: App\Models\Catalog\RepositoryItem (repository_items table),
     served by App\Http\Controllers\RepositoryController::show().
     $canReadFull is decided in the controller; /repository/{id}/download
     repeats the same check, so the button is a hint, not the gate. --}}
@extends('layouts.public')

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'kk';
  $activePage = $activePage ?? 'repository';

  $routeWithLang = static function (string $path, array $query = []) use ($lang): string {
      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }
      $qs = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
      return $path . ($qs !== '' ? ('?' . $qs) : '');
  };

  $copy = [
    'ru' => [
      'back' => 'К репозиторию',
      'abstract_heading' => 'Аннотация',
      'abstract_missing' => 'Аннотация к этой работе не указана.',
      'meta_heading' => 'Метаданные работы',
      'meta_department' => 'Кафедра / факультет',
      'meta_udc' => 'Индекс УДК',
      'meta_language' => 'Язык работы',
      'meta_type' => 'Вид работы',
      'meta_year' => 'Год',
      'meta_published' => 'Опубликовано',
      'meta_keywords' => 'Ключевые слова',
      'meta_file' => 'Файл работы',
      'meta_supervisor' => 'Научный руководитель',
      'meta_university' => 'Университет',
      'meta_doi' => 'DOI',
      'meta_source' => 'Источник',
      'meta_rights_holder' => 'Правообладатель',
      'meta_licence' => 'Лицензия',
      'meta_access' => 'Политика доступа',
      'access_heading' => 'Доступ к полному тексту',
      'access_body_open' => 'Метаданные и полный текст работы открыты всем посетителям, включая гостей.',
      'access_body_authenticated' => 'Полный текст доступен после входа под университетской учётной записью.',
      'access_body_guest' => 'Метаданные работы открыты всем. Полный текст открывается после входа под университетской учётной записью.',
      'access_body_denied' => 'Метаданные работы открыты всем. Полный текст этой работы недоступен для вашей учётной записи — обратитесь в библиотеку.',
      'access_body_embargo' => 'Метаданные открыты, но полный текст находится под эмбарго и пока недоступен.',
      'access_no_file' => 'Файл полного текста для этой работы не загружен.',
      'access_view' => 'Открыть в PDF-просмотрщике',
      'access_download' => 'Скачать PDF',
      'access_signin' => 'Войти для чтения',
      'related_heading' => 'Похожие работы',
      'news_heading' => 'Новости об этой работе',
      'withdrawn' => 'Работа отозвана. Метаданные сохранены как публичная запись; полный текст недоступен.',
      'citation' => 'Библиографическая ссылка',
    ],
    'kk' => [
      'back' => 'Репозиторийге оралу',
      'abstract_heading' => 'Аңдатпа',
      'abstract_missing' => 'Бұл жұмыстың аңдатпасы көрсетілмеген.',
      'meta_heading' => 'Жұмыс метадеректері',
      'meta_department' => 'Кафедра / факультет',
      'meta_udc' => 'ӘОЖ индексі',
      'meta_language' => 'Жұмыс тілі',
      'meta_type' => 'Жұмыс түрі',
      'meta_year' => 'Жылы',
      'meta_published' => 'Жарияланды',
      'meta_keywords' => 'Кілт сөздер',
      'meta_file' => 'Жұмыс файлы',
      'meta_supervisor' => 'Ғылыми жетекші',
      'meta_university' => 'Университет',
      'meta_doi' => 'DOI',
      'meta_source' => 'Дереккөз',
      'meta_rights_holder' => 'Құқық иесі',
      'meta_licence' => 'Лицензия',
      'meta_access' => 'Қолжетімділік саясаты',
      'access_heading' => 'Толық мәтінге қолжетімділік',
      'access_body_open' => 'Жұмыстың метадеректері мен толық мәтіні қонақтарды қоса алғанда, барлық келушілерге ашық.',
      'access_body_authenticated' => 'Толық мәтін университеттік тіркелгімен кіргеннен кейін қолжетімді.',
      'access_body_guest' => 'Жұмыстың метадеректері барлығына ашық. Толық мәтін университеттік тіркелгімен кіргеннен кейін ашылады.',
      'access_body_denied' => 'Жұмыстың метадеректері барлығына ашық. Бұл жұмыстың толық мәтіні сіздің тіркелгіңізге қолжетімсіз — кітапханаға хабарласыңыз.',
      'access_body_embargo' => 'Метадеректер ашық, бірақ толық мәтінге эмбарго қойылған және ол әзірге қолжетімсіз.',
      'access_no_file' => 'Бұл жұмыстың толық мәтін файлы жүктелмеген.',
      'access_view' => 'PDF қарау құралында ашу',
      'access_download' => 'PDF жүктеп алу',
      'access_signin' => 'Оқу үшін кіру',
      'related_heading' => 'Ұқсас жұмыстар',
      'news_heading' => 'Осы жұмыс туралы жаңалықтар',
      'withdrawn' => 'Жұмыс кері қайтарылды. Метадеректер ашық жазба ретінде сақталды; толық мәтін қолжетімсіз.',
      'citation' => 'Библиографиялық сілтеме',
    ],
    'en' => [
      'back' => 'Back to Repository',
      'abstract_heading' => 'Abstract',
      'abstract_missing' => 'No abstract has been recorded for this work.',
      'meta_heading' => 'Work metadata',
      'meta_department' => 'Department / faculty',
      'meta_udc' => 'UDC index',
      'meta_language' => 'Work language',
      'meta_type' => 'Work type',
      'meta_year' => 'Year',
      'meta_published' => 'Published',
      'meta_keywords' => 'Keywords',
      'meta_file' => 'Work file',
      'meta_supervisor' => 'Supervisor',
      'meta_university' => 'University',
      'meta_doi' => 'DOI',
      'meta_source' => 'Source',
      'meta_rights_holder' => 'Rights holder',
      'meta_licence' => 'Licence',
      'meta_access' => 'Access policy',
      'access_heading' => 'Full-text access',
      'access_body_open' => 'The metadata and full text are open to every visitor, including guests.',
      'access_body_authenticated' => 'The full text is available after signing in with a university account.',
      'access_body_guest' => 'Metadata is open to everyone. The full text opens after signing in with a university account.',
      'access_body_denied' => 'Metadata is open to everyone. The full text of this work is not available to your account — please contact the library.',
      'access_body_embargo' => 'Metadata is public, but the full text is under embargo and is not yet available.',
      'access_no_file' => 'No full-text file has been uploaded for this work.',
      'access_view' => 'Open in PDF viewer',
      'access_download' => 'Download PDF',
      'access_signin' => 'Sign in to read',
      'related_heading' => 'Related works',
      'news_heading' => 'News about this work',
      'withdrawn' => 'This work has been withdrawn. Metadata remains as a public record; the full text is unavailable.',
      'citation' => 'Bibliographic citation',
    ],
  ][$lang];

  $langNames = [
    'ru' => ['ru' => 'Русский', 'kk' => 'Казахский', 'en' => 'Английский'],
    'kk' => ['ru' => 'Орыс тілі', 'kk' => 'Қазақ тілі', 'en' => 'Ағылшын тілі'],
    'en' => ['ru' => 'Russian', 'kk' => 'Kazakh', 'en' => 'English'],
  ][$lang];

  $authors = collect($item->authors ?? [])->filter()->values();
  $keywords = collect($item->keywords ?? [])->filter()->values();
  $publishedDisplay = $item->published_at?->format('d.m.Y') ?? '—';
  $normalisedPolicy = \App\Models\Catalog\RepositoryItem::normaliseAccessPolicy($item->access_policy);
  $requiresAuthentication = $normalisedPolicy === 'metadata_public_file_authenticated';

  $fileSizeDisplay = null;
  if ($hasFile && $item->file_size) {
      $megabytes = $item->file_size / 1048576;
      $fileSizeDisplay = $megabytes >= 1
          ? number_format($megabytes, 1, ',', ' ') . ' MB'
          : number_format(max(1, (int) round($item->file_size / 1024)), 0, ',', ' ') . ' KB';
  }
@endphp

@section('title', $item->title.' — '.__('brand.library.name'))
@section('meta_description', filled($item->abstract) ? \Illuminate\Support\Str::limit(strip_tags($item->abstract), 200) : $item->title)

@section('content')
  <div class="repository-detail" data-section="repository-detail-page" data-work-id="{{ $item->getKey() }}">
    <a class="repository-detail__back" href="{{ $routeWithLang('/repository') }}" data-test-id="repository-detail-back">
      <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
      <span>{{ $copy['back'] }}</span>
    </a>

    <header class="repository-detail__header" data-section="repository-detail-header">
      <p class="repository-detail__eyebrow">
        {{ __('librarian.repository.work_types.'.$item->work_type) }}@if($item->year) · {{ $item->year }}@endif
      </p>
      <h1 class="repository-detail__display">{{ $item->title }}</h1>
      <p class="repository-detail__authors">{{ $authors->isNotEmpty() ? $authors->implode(' · ') : '—' }}</p>
    </header>

    <div class="repository-detail__columns">
      <div class="repository-detail__main">
        <section class="repository-detail__section" data-section="repository-detail-abstract">
          <h2 class="repository-detail__section-heading">{{ $copy['abstract_heading'] }}</h2>
          <p class="repository-detail__abstract">{{ filled($item->abstract) ? $item->abstract : $copy['abstract_missing'] }}</p>
        </section>

        @if($isWithdrawn)
          <section class="repository-detail__section repository-detail__access" data-section="repository-withdrawal-tombstone" role="status">
            <h2 class="repository-detail__section-heading"><span class="material-symbols-outlined" aria-hidden="true">block</span>{{ $copy['withdrawn'] }}</h2>
            @if($item->withdrawal_reason)<p class="repository-detail__access-body">{{ $item->withdrawal_reason }}</p>@endif
          </section>
        @else
        <section class="repository-detail__section repository-detail__access" data-section="repository-detail-access">
          <h2 class="repository-detail__section-heading">
            <span class="material-symbols-outlined" aria-hidden="true">{{ $canReadFull && $hasFile ? 'lock_open' : 'lock' }}</span>
            {{ $copy['access_heading'] }}
          </h2>
          @if($canReadFull)
            <p class="repository-detail__access-body">{{ $normalisedPolicy === 'full_public' ? $copy['access_body_open'] : $copy['access_body_authenticated'] }}</p>
            @if($hasFile)
              <a class="repository-detail__access-cta"
                 href="{{ $routeWithLang('/repository/' . $item->getKey() . '/view') }}"
                 data-test-id="repository-detail-view">
                <span class="material-symbols-outlined" aria-hidden="true">picture_as_pdf</span>
                {{ $copy['access_view'] }}
              </a>
              <a class="repository-detail__download-link"
                 href="{{ $routeWithLang('/repository/' . $item->getKey() . '/download') }}"
                 data-test-id="repository-detail-download">{{ $copy['access_download'] }}</a>
            @else
              <p class="repository-detail__access-note" data-test-id="repository-detail-no-file">{{ $copy['access_no_file'] }}</p>
            @endif
          @elseif($item->status === 'embargoed' || $item->embargoIsActive())
            <p class="repository-detail__access-body">{{ $copy['access_body_embargo'] }}</p>
          @elseif($isAuthenticated || ! $requiresAuthentication)
            <p class="repository-detail__access-body">{{ $copy['access_body_denied'] }}</p>
          @else
            <p class="repository-detail__access-body">{{ $copy['access_body_guest'] }}</p>
            <a class="repository-detail__access-cta" href="{{ $routeWithLang('/login') }}" data-test-id="repository-detail-access-cta">
              {{ $copy['access_signin'] }}
            </a>
          @endif
        </section>

        @if($canReadFull && $hasFile)
          <section class="repository-detail__section" data-section="repository-inline-pdf">
            <iframe
              class="repository-detail__pdf"
              src="{{ $routeWithLang('/repository/' . $item->getKey() . '/view') }}"
              title="{{ $copy['access_view'] }}"
              loading="lazy"
            ></iframe>
          </section>
        @endif
        @endif

        <section class="repository-detail__section" data-section="repository-detail-citation">
          <h2 class="repository-detail__section-heading">{{ $copy['citation'] }}</h2>
          <p class="repository-detail__abstract">{{ $citation }}</p>
        </section>

        @if(($linkedNews ?? collect())->isNotEmpty())
          <section class="repository-detail__section" data-section="repository-linked-news">
            <h2 class="repository-detail__section-heading">{{ $copy['news_heading'] }}</h2>
            <div class="repository-detail__related-grid">
              @foreach($linkedNews as $newsItem)
                <a class="repository-detail__related-card"
                   href="{{ $routeWithLang('/news/' . $newsItem->localizedSlug($lang)) }}"
                   data-test-id="repository-linked-news-{{ $newsItem->getKey() }}">
                  <span class="repository-detail__related-eyebrow">{{ $newsItem->published_at?->format('d.m.Y') }}</span>
                  <span class="repository-detail__related-title">{{ $newsItem->localized('title', $lang) }}</span>
                </a>
              @endforeach
            </div>
          </section>
        @endif

        @if($related->isNotEmpty())
          <section class="repository-detail__section" data-section="repository-detail-related">
            <h2 class="repository-detail__section-heading">{{ $copy['related_heading'] }}</h2>
            <div class="repository-detail__related-grid">
              @foreach($related as $relatedItem)
                @php $relatedAuthors = collect($relatedItem->authors ?? [])->filter()->values(); @endphp
                <a class="repository-detail__related-card"
                   href="{{ $routeWithLang('/repository/' . $relatedItem->getKey()) }}"
                   data-test-id="repository-detail-related-{{ $relatedItem->getKey() }}">
                  <span class="repository-detail__related-eyebrow">
                    {{ __('librarian.repository.work_types.'.$relatedItem->work_type) }}@if($relatedItem->year) · {{ $relatedItem->year }}@endif
                  </span>
                  <span class="repository-detail__related-title">{{ $relatedItem->title }}</span>
                  <span class="repository-detail__related-authors">{{ $relatedAuthors->isNotEmpty() ? $relatedAuthors->implode(' · ') : '—' }}</span>
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
            <dt>{{ $copy['meta_type'] }}</dt>
            <dd>{{ __('librarian.repository.work_types.'.$item->work_type) }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_year'] }}</dt>
            <dd>{{ $item->year ?? '—' }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_department'] }}</dt>
            <dd>{{ $item->department ?: '—' }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_udc'] }}</dt>
            <dd>
              @if(filled($item->udc_code))
                <span class="repository-detail__udc">{{ $item->udc_code }}</span>
              @else
                —
              @endif
            </dd>
          </div>
          <div>
            <dt>{{ $copy['meta_language'] }}</dt>
            <dd>{{ $langNames[$item->language] ?? ($item->language ?: '—') }}</dd>
          </div>
          <div>
            <dt>{{ $copy['meta_published'] }}</dt>
            <dd>
              @if($item->published_at)
                <time datetime="{{ $item->published_at->toDateString() }}">{{ $publishedDisplay }}</time>
              @else
                —
              @endif
            </dd>
          </div>
          @if(filled($item->supervisor))
            <div><dt>{{ $copy['meta_supervisor'] }}</dt><dd>{{ $item->supervisor }}</dd></div>
          @endif
          @if(filled($item->university))
            <div><dt>{{ $copy['meta_university'] }}</dt><dd>{{ $item->university }}</dd></div>
          @endif
          @if(filled($item->doi))
            <div><dt>{{ $copy['meta_doi'] }}</dt><dd>{{ $item->doi }}</dd></div>
          @endif
          <div><dt>{{ $copy['meta_source'] }}</dt><dd>{{ $item->source }}</dd></div>
          <div><dt>{{ $copy['meta_rights_holder'] }}</dt><dd>{{ $item->rights_holder }}</dd></div>
          @if(filled($item->licence_type))
            <div><dt>{{ $copy['meta_licence'] }}</dt><dd>{{ $item->licence_type }}</dd></div>
          @endif
          <div><dt>{{ $copy['meta_access'] }}</dt><dd>{{ __('repository.access.'.$normalisedPolicy) }}</dd></div>
          @if($canReadFull && $hasFile)
            <div>
              <dt>{{ $copy['meta_file'] }}</dt>
              <dd>
                {{ $item->file_name ?: '—' }}@if($fileSizeDisplay) <span class="repository-detail__file-size">({{ $fileSizeDisplay }})</span>@endif
              </dd>
            </div>
          @endif
          @if($keywords->isNotEmpty())
            <div>
              <dt>{{ $copy['meta_keywords'] }}</dt>
              <dd>
                <ul class="repository-detail__keywords" role="list">
                  @foreach($keywords as $keyword)
                    <li>{{ $keyword }}</li>
                  @endforeach
                </ul>
              </dd>
            </div>
          @endif
        </dl>
      </aside>
    </div>
  </div>
@endsection

@section('head')
<style>
  .repository-detail {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 80px var(--page-inset) 96px;
    color: #191c1d;
    font-family: 'Manrope', sans-serif;
  }

  @media (min-width: 768px) {
    .repository-detail {
      padding: 96px var(--page-inset);
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
    max-width: 940px;
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
    overflow-wrap: anywhere;
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
    overflow-wrap: anywhere;
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

  .repository-detail__access-note {
    font-size: 13.5px;
    line-height: 1.6;
    color: #43474e;
    margin: 0;
  }

  .repository-detail__access-cta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 28px;
    background: #006a6a;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    transition: background-color 0.2s ease;
  }

  .repository-detail__download-link {
    display: inline-block;
    margin-left: 16px;
    color: #006a6a;
    font-size: 14px;
    font-weight: 700;
  }

  .repository-detail__pdf {
    width: 100%;
    min-height: 680px;
    border: 1px solid #d9e3e3;
    border-radius: 8px;
    background: #fff;
  }

  .repository-detail__access-cta:hover {
    background: #00524f;
  }

  .repository-detail__access-cta .material-symbols-outlined {
    font-size: 18px;
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
    word-break: break-word;
  }

  .repository-detail__file-size {
    color: #43474e;
    font-size: 13px;
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
