@extends('layouts.public', ['activePage' => 'catalog'])

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';

  $withLang = function (string $path, array $query = []) use ($lang): string {
      $normalizedPath = '/' . ltrim($path, '/');
      if ($normalizedPath === '//') {
          $normalizedPath = '/';
      }

      if ($lang !== 'ru' && ! array_key_exists('lang', $query)) {
          $query['lang'] = $lang;
      }

      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

      return $normalizedPath . ($query ? ('?' . http_build_query($query)) : '');
  };

  $q = (string) request()->query('q', '');
  $language = (string) request()->query('language', 'all');
  $yearFrom = (string) request()->query('year_from', '');
  $yearTo = (string) request()->query('year_to', '');
  $availableOnly = request()->boolean('available_only');
  $physicalOnly = request()->boolean('physical_only');
  $institution = (string) request()->query('institution', '');
  $sort = (string) request()->query('sort', 'relevance');
  $materialType = (string) request()->query('material_type', '');
  $titleFilter = (string) request()->query('title', '');
  $authorFilter = (string) request()->query('author', '');
  $publisherFilter = (string) request()->query('publisher', '');
  $isbnFilter = (string) request()->query('isbn', '');
  $udcFilter = (string) request()->query('udc', '');

  $copy = [
      'ru' => [
          'title' => 'Каталог книг — Digital Library',
          'heading' => 'Каталог книг',
          'search_placeholder' => 'Поиск по названию, автору, ISBN или теме...',
          'advanced' => 'Расширенный',
          'seed_summary' => 'Просмотр академических материалов университета.',
          'filters' => 'Фильтры',
          'material_type' => 'Тип материала',
          'publication_date' => 'Год издания',
          'language' => 'Язык',
          'collection' => 'Коллекция',
          'available_only' => 'Только доступные экземпляры',
          'physical_only' => 'Только с физическим фондом',
          'clear_filters' => 'Сбросить фильтры',
          'showing' => 'Показаны',
          'of' => 'из',
          'results_for' => 'результатов по запросу',
          'sort_by' => 'Сортировка',
          'sort_options' => [
              'relevance' => 'Релевантность',
              'title' => 'По названию',
              'year_desc' => 'Сначала новые',
              'year_asc' => 'Сначала старые',
          ],
          'advanced_search' => 'Расширенный поиск',
          'advanced_apply' => 'Применить',
          'advanced_reset' => 'Очистить поля',
          'field_title' => 'Название',
          'field_author' => 'Автор',
          'field_publisher' => 'Издатель',
          'field_isbn' => 'ISBN',
          'field_udc' => 'УДК',
          'field_subject' => 'Тема / аннотация',
          'field_subject_help' => 'Поле готово для будущей библиографической аннотации.',
          'types' => ['Книги и e-books', 'Научные журналы', 'Диссертации и работы'],
          'collections' => [
              ['label' => 'Инженерия и технологии', 'value' => 'technology_library'],
              ['label' => 'Бизнес и экономика', 'value' => 'economic_library'],
              ['label' => 'Гуманитарный фонд', 'value' => 'college_library'],
              ['label' => '+ Показать ещё 12', 'value' => 'ktslib'],
          ],
          'institution_options' => [
              '' => 'Все подразделения',
              'technology_library' => 'Технологическая библиотека',
              'economic_library' => 'Экономическая библиотека',
              'college_library' => 'Библиотека колледжа',
          ],
          'sample_items' => [
              [
                  'badge' => 'Электронный ресурс',
                  'badge_style' => 'bg-secondary-container text-on-secondary-container',
                  'title' => 'Advances in Computational Fluid Dynamics: A Multidisciplinary Approach',
                  'meta' => 'Dr. Almas Kurmanbayev · 2023 · Cambridge University Press',
                  'body' => 'Междисциплинарное исследование вычислительной гидродинамики и её инженерных применений.',
                  'primary_cta' => 'Читать онлайн',
                  'status' => 'Мгновенный доступ',
                  'status_style' => 'text-secondary',
                  'icon' => 'visibility',
                  'image' => '/images/news/default-library.jpg',
              ],
              [
                  'badge' => 'Печатный экземпляр',
                  'badge_style' => 'bg-surface-container-highest text-on-surface-variant',
                  'title' => 'Economic Transformations in Post-Soviet Kazakhstan',
                  'meta' => 'Saule Tleubayeva · 2021 · KazUTB Press',
                  'body' => 'Аналитический обзор структурных реформ и технологической адаптации в экономике Казахстана.',
                  'primary_cta' => 'Найти на полке',
                  'status' => 'Этаж 3, стеллаж B-12',
                  'status_style' => 'text-on-surface-variant',
                  'icon' => 'library_books',
                  'image' => '/images/news/classics-event.jpg',
              ],
              [
                  'badge' => 'Архив',
                  'badge_style' => 'bg-secondary-container text-on-secondary-container',
                  'title' => 'Historical Archives of Industrial Design in Central Asia',
                  'meta' => 'Various Contributors · 1985–1995 · Institutional Archive',
                  'body' => 'Цифровая коллекция чертежей и проектной документации по промышленному дизайну региона.',
                  'primary_cta' => 'Запросить просмотр',
                  'status' => 'Требуется специальный доступ',
                  'status_style' => 'text-error',
                  'icon' => 'history_edu',
                  'image' => '/images/news/campus-library.jpg',
              ],
          ],
          'ui' => [
              'electronic' => 'Электронный ресурс',
              'physical' => 'Печатный экземпляр',
              'archive' => 'Архив',
              'read' => 'Читать онлайн',
              'locate' => 'Найти на полке',
              'request' => 'Запросить просмотр',
              'cite' => 'Цитировать',
              'isbn' => 'ISBN',
              'udc' => 'УДК',
              'available' => 'Мгновенный доступ',
              'permission' => 'Требуется специальный доступ',
              'empty' => 'По выбранным фильтрам материалы не найдены.',
              'results_for' => 'результатов по запросу',
              'copies' => 'Экземпляры',
              'fallback_loaded' => 'Каталог загружен с сохранённой выдачей.',
              'author_unknown' => 'Автор не указан',
              'description_placeholder' => 'Аннотация будет добавлена после библиографической доработки записи.',
              'subjects' => 'Темы',
              'language_label' => 'Язык',
              'institution_label' => 'Фонд',
              'no_location' => 'Локация уточняется',
              'page_prev' => 'Назад',
              'page_next' => 'Вперёд',
              'central_library' => 'Центральная библиотека КазТБУ',
              'technology_library' => 'Технологическая библиотека',
              'economic_library' => 'Экономическая библиотека',
              'college_library' => 'Библиотека колледжа',
              'cabinet_short' => 'каб.',
              'main_cabinet' => 'главный кабинет выдачи',
          ],
      ],
      'kk' => [
          'title' => 'Кітаптар каталогы — Digital Library',
          'heading' => 'Институционалдық каталог',
          'search_placeholder' => 'Атауы, авторы, ISBN немесе тақырып бойынша іздеу...',
          'advanced' => 'Кеңейтілген',
          'seed_summary' => 'Университеттің академиялық материалдарын шолу.',
          'filters' => 'Сүзгілер',
          'material_type' => 'Материал түрі',
          'publication_date' => 'Жарияланған жылы',
          'language' => 'Тіл',
          'collection' => 'Коллекция',
          'available_only' => 'Тек қолжетімді даналар',
          'physical_only' => 'Тек физикалық қоры бар',
          'clear_filters' => 'Сүзгілерді тазарту',
          'showing' => 'Көрсетіліп жатыр',
          'of' => 'барлығы',
          'results_for' => 'нәтиже',
          'sort_by' => 'Сұрыптау',
          'sort_options' => [
              'relevance' => 'Өзектілік',
              'title' => 'Атауы бойынша',
              'year_desc' => 'Жаңа алдымен',
              'year_asc' => 'Ескі алдымен',
          ],
          'advanced_search' => 'Кеңейтілген іздеу',
          'advanced_apply' => 'Қолдану',
          'advanced_reset' => 'Өрістерді тазарту',
          'field_title' => 'Атауы',
          'field_author' => 'Автор',
          'field_publisher' => 'Баспасы',
          'field_isbn' => 'ISBN',
          'field_udc' => 'ӘОЖ',
          'field_subject' => 'Тақырып / аннотация',
          'field_subject_help' => 'Өріс болашақ библиографиялық аннотация үшін дайын тұр.',
          'types' => ['Кітаптар мен e-books', 'Ғылыми журналдар', 'Диссертациялар мен жұмыстар'],
          'collections' => [
              ['label' => 'Инженерия және технология', 'value' => 'technology_library'],
              ['label' => 'Бизнес және экономика', 'value' => 'economic_library'],
              ['label' => 'Гуманитарлық қор', 'value' => 'college_library'],
              ['label' => '+ Тағы 12 бөлім', 'value' => 'ktslib'],
          ],
          'institution_options' => [
              '' => 'Барлық бөлімдер',
              'technology_library' => 'Технологиялық кітапхана',
              'economic_library' => 'Экономикалық кітапхана',
              'college_library' => 'Колледж кітапханасы',
          ],
          'sample_items' => [
              [
                  'badge' => 'Электрондық ресурс',
                  'badge_style' => 'bg-secondary-container text-on-secondary-container',
                  'title' => 'Advances in Computational Fluid Dynamics: A Multidisciplinary Approach',
                  'meta' => 'Dr. Almas Kurmanbayev · 2023 · Cambridge University Press',
                  'body' => 'Есептеу гидродинамикасы мен оның инженерлік қолданылуы туралы пәнаралық зерттеу.',
                  'primary_cta' => 'Онлайн оқу',
                  'status' => 'Бірден қолжетімді',
                  'status_style' => 'text-secondary',
                  'icon' => 'visibility',
                  'image' => '/images/news/default-library.jpg',
              ],
              [
                  'badge' => 'Баспа данасы',
                  'badge_style' => 'bg-surface-container-highest text-on-surface-variant',
                  'title' => 'Economic Transformations in Post-Soviet Kazakhstan',
                  'meta' => 'Saule Tleubayeva · 2021 · KazUTB Press',
                  'body' => 'Қазақстан экономикасындағы құрылымдық реформалар мен технологиялық бейімделуге арналған шолу.',
                  'primary_cta' => 'Сөреден табу',
                  'status' => '3-қабат, B-12 сөресі',
                  'status_style' => 'text-on-surface-variant',
                  'icon' => 'library_books',
                  'image' => '/images/news/classics-event.jpg',
              ],
              [
                  'badge' => 'Архив',
                  'badge_style' => 'bg-secondary-container text-on-secondary-container',
                  'title' => 'Historical Archives of Industrial Design in Central Asia',
                  'meta' => 'Various Contributors · 1985–1995 · Institutional Archive',
                  'body' => 'Өнеркәсіптік дизайн бойынша сызбалар мен жобалық құжаттаманың цифрлық коллекциясы.',
                  'primary_cta' => 'Қарауды сұрау',
                  'status' => 'Арнайы рұқсат қажет',
                  'status_style' => 'text-error',
                  'icon' => 'history_edu',
                  'image' => '/images/news/campus-library.jpg',
              ],
          ],
          'ui' => [
              'electronic' => 'Электрондық ресурс',
              'physical' => 'Баспа данасы',
              'archive' => 'Архив',
              'read' => 'Онлайн оқу',
              'locate' => 'Сөреден табу',
              'request' => 'Қарауды сұрау',
              'cite' => 'Дәйексөз',
              'isbn' => 'ISBN',
              'udc' => 'ӘОЖ',
              'available' => 'Бірден қолжетімді',
              'permission' => 'Арнайы рұқсат қажет',
              'empty' => 'Таңдалған сүзгілер бойынша материал табылмады.',
              'results_for' => 'нәтиже',
              'copies' => 'Даналар',
              'fallback_loaded' => 'Каталог сақталған нәтижелермен жүктелді.',
              'author_unknown' => 'Автор көрсетілмеген',
              'description_placeholder' => 'Аннотация библиографиялық толықтырудан кейін қосылады.',
              'subjects' => 'Тақырыптар',
              'language_label' => 'Тіл',
              'institution_label' => 'Қор',
              'no_location' => 'Орналасуы нақтылануда',
              'page_prev' => 'Артқа',
              'page_next' => 'Алға',
              'central_library' => 'ҚазТБУ орталық кітапханасы',
              'technology_library' => 'Технологиялық кітапхана',
              'economic_library' => 'Экономикалық кітапхана',
              'college_library' => 'Колледж кітапханасы',
              'cabinet_short' => 'каб.',
              'main_cabinet' => 'негізгі беру кабинеті',
          ],
      ],
      'en' => [
          'title' => 'Catalog — Digital Library',
          'heading' => 'Institutional Catalog',
          'search_placeholder' => 'Search by title, author, ISBN, or subject...',
          'advanced' => 'Advanced',
          'seed_summary' => 'Viewing scholarly items across university collections.',
          'filters' => 'Filters',
          'material_type' => 'Material Type',
          'publication_date' => 'Publication Date',
          'language' => 'Language',
          'collection' => 'Collection',
          'available_only' => 'Available in library',
          'physical_only' => 'Physical holdings only',
          'clear_filters' => 'Clear filters',
          'showing' => 'Showing',
          'of' => 'of',
          'results_for' => 'results for',
          'sort_by' => 'Sort by',
          'sort_options' => [
              'relevance' => 'Relevance',
              'title' => 'Title',
              'year_desc' => 'Newest First',
              'year_asc' => 'Oldest First',
          ],
          'advanced_search' => 'Advanced search',
          'advanced_apply' => 'Apply',
          'advanced_reset' => 'Clear fields',
          'field_title' => 'Title',
          'field_author' => 'Author',
          'field_publisher' => 'Publisher',
          'field_isbn' => 'ISBN',
          'field_udc' => 'UDC',
          'field_subject' => 'Subject / annotation',
          'field_subject_help' => 'This field is prepared for future bibliographic annotations.',
          'types' => ['Books & E-books', 'Academic Journals', 'Theses & Dissertations'],
          'collections' => [
              ['label' => 'Engineering & Tech', 'value' => 'technology_library'],
              ['label' => 'Business & Economics', 'value' => 'economic_library'],
              ['label' => 'Humanities', 'value' => 'college_library'],
              ['label' => '+ View 12 more', 'value' => 'ktslib'],
          ],
          'institution_options' => [
              '' => 'All divisions',
              'technology_library' => 'Technology Library',
              'economic_library' => 'Economics Library',
              'college_library' => 'College Library',
          ],
          'sample_items' => [
              [
                  'badge' => 'Electronic Resource',
                  'badge_style' => 'bg-secondary-container text-on-secondary-container',
                  'title' => 'Advances in Computational Fluid Dynamics: A Multidisciplinary Approach',
                  'meta' => 'Dr. Almas Kurmanbayev · 2023 · Cambridge University Press',
                  'body' => 'A multidisciplinary study of computational fluid dynamics and its engineering applications.',
                  'primary_cta' => 'Read Online',
                  'status' => 'Immediate Access',
                  'status_style' => 'text-secondary',
                  'icon' => 'visibility',
                  'image' => '/images/news/default-library.jpg',
              ],
              [
                  'badge' => 'Physical Copy',
                  'badge_style' => 'bg-surface-container-highest text-on-surface-variant',
                  'title' => 'Economic Transformations in Post-Soviet Kazakhstan',
                  'meta' => 'Saule Tleubayeva · 2021 · KazUTB Press',
                  'body' => 'An analytical review of structural reforms and technological adaptation in Kazakhstan’s economy.',
                  'primary_cta' => 'Locate on Shelf',
                  'status' => 'Floor 3, Stack B-12',
                  'status_style' => 'text-on-surface-variant',
                  'icon' => 'library_books',
                  'image' => '/images/news/classics-event.jpg',
              ],
              [
                  'badge' => 'Archive',
                  'badge_style' => 'bg-secondary-container text-on-secondary-container',
                  'title' => 'Historical Archives of Industrial Design in Central Asia',
                  'meta' => 'Various Contributors · 1985–1995 · Institutional Archive',
                  'body' => 'A digitized collection of industrial design drawings and project documentation.',
                  'primary_cta' => 'Request Viewing',
                  'status' => 'Special Permission Required',
                  'status_style' => 'text-error',
                  'icon' => 'history_edu',
                  'image' => '/images/news/campus-library.jpg',
              ],
          ],
          'ui' => [
              'electronic' => 'Electronic Resource',
              'physical' => 'Physical Copy',
              'archive' => 'Archive',
              'read' => 'Read Online',
              'locate' => 'Locate on Shelf',
              'request' => 'Request Viewing',
              'cite' => 'Cite Item',
              'isbn' => 'ISBN',
              'udc' => 'UDC',
              'available' => 'Immediate Access',
              'permission' => 'Special Permission Required',
              'empty' => 'No items match the selected filters.',
              'results_for' => 'results for',
              'copies' => 'Copies',
              'fallback_loaded' => 'Catalog loaded using the preserved result set.',
              'author_unknown' => 'Author not specified',
              'description_placeholder' => 'Description will appear after bibliographic enrichment.',
              'subjects' => 'Subjects',
              'language_label' => 'Language',
              'institution_label' => 'Collection',
              'no_location' => 'Location pending',
              'page_prev' => 'Previous',
              'page_next' => 'Next',
              'central_library' => 'KazTBU Central Library',
              'technology_library' => 'Technology Library',
              'economic_library' => 'Economics Library',
              'college_library' => 'College Library',
              'cabinet_short' => 'room',
              'main_cabinet' => 'main circulation desk',
          ],
      ],
  ][$lang];

  $langSuffix = $lang === 'ru' ? '' : ('?lang=' . $lang);
  $yearMin = max((int) ($catalogYearBounds['min'] ?? 1950), 1900);
  $yearMax = max((int) ($catalogYearBounds['max'] ?? (int) date('Y')), $yearMin);
  $yearFrom = (string) max($yearMin, min((int) ($yearFrom !== '' ? $yearFrom : $yearMin), $yearMax));
  $yearTo = (string) max((int) $yearFrom, min((int) ($yearTo !== '' ? $yearTo : $yearMax), $yearMax));
  $initialCatalog = is_array($catalogBootstrap ?? null) ? $catalogBootstrap : ['data' => [], 'meta' => []];
  $initialResults = is_array($initialCatalog['data'] ?? null) ? $initialCatalog['data'] : [];
  $initialMeta = is_array($initialCatalog['meta'] ?? null) ? $initialCatalog['meta'] : [];
  $initialTotal = (int) ($initialMeta['total'] ?? count($initialResults));
  $initialPage = max((int) ($initialMeta['page'] ?? 1), 1);
  $initialPerPage = max((int) ($initialMeta['per_page'] ?? max(count($initialResults), 10)), 1);
  $initialFrom = $initialTotal > 0 ? (($initialPage - 1) * $initialPerPage) + 1 : 0;
  $initialTo = $initialTotal > 0 ? min($initialPage * $initialPerPage, $initialTotal) : 0;
  $initialQueryLabel = $q !== '' ? $q : strtoupper($language === '' ? 'all' : $language);
  $hasAdvancedFilters = $titleFilter !== '' || $authorFilter !== '' || $publisherFilter !== '' || $isbnFilter !== '' || $udcFilter !== '';
  $formatLocationLabel = static function (array $location) use ($copy): string {
      $serviceCode = strtolower(trim((string) data_get($location, 'servicePoint.code', '')));
      $serviceName = trim((string) data_get($location, 'servicePoint.name', ''));
      $campusCode = strtolower(trim((string) data_get($location, 'campus.code', '')));
      $unitCode = strtolower(trim((string) data_get($location, 'institutionUnit.code', '')));

      $libraryLabel = match (true) {
          $serviceCode === '1', $campusCode === 'university_economic' => $copy['ui']['economic_library'],
          $serviceCode === '2', $campusCode === 'university_technological' => $copy['ui']['technology_library'],
          $serviceCode === '3', $campusCode === 'college_main', $unitCode === 'college' => $copy['ui']['college_library'],
          $serviceCode === 'kstlib', $campusCode === 'university_central' => $copy['ui']['central_library'],
          default => '',
      };

      if (in_array($serviceCode, ['1', '2', '3'], true)) {
          return trim($libraryLabel . ' · ' . $copy['ui']['cabinet_short'] . ' ' . $serviceCode);
      }

      if ($serviceCode === 'kstlib') {
          return trim($libraryLabel . ' · ' . $copy['ui']['main_cabinet']);
      }

      if ($libraryLabel !== '') {
          return $libraryLabel;
      }

      return $serviceName !== '' && ! str_starts_with(strtolower($serviceName), 'app.')
          ? $serviceName
          : $copy['ui']['no_location'];
  };
@endphp

@section('title', $copy['title'])
@section('body_class', 'bg-surface text-on-background min-h-screen flex flex-col')

@section('head')
<style>
  .catalog-export .font-newsreader { font-family: 'Newsreader', serif; }
  .catalog-export .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
  }
  .catalog-export .line-clamp-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }
  .catalog-export .catalog-item {
    align-items: stretch;
  }
  .catalog-export .catalog-card-media {
    width: 100%;
    max-width: 10rem;
    min-height: 17rem;
    align-self: stretch;
    margin-inline: auto;
    overflow: visible;
    background: transparent;
    box-shadow: none;
  }
  .catalog-export .catalog-card-book {
    position: relative;
    width: 100%;
    min-height: 17rem;
    height: 100%;
    perspective: 1800px;
  }
  .catalog-export .catalog-card-book__stack {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 17rem;
    transform-style: preserve-3d;
  }
  .catalog-export .catalog-card-book__pages {
    position: absolute;
    inset: 0.3rem 0.15rem 0.3rem 0.4rem;
    border-radius: 0 0.75rem 0.75rem 0;
    overflow: hidden;
    background: linear-gradient(90deg, #f3ead7 0%, #fffdfa 18%, #f3ede2 100%);
    box-shadow: inset 0 0 0 1px rgba(120, 96, 58, 0.12), 0 14px 30px rgba(15, 23, 42, 0.16);
    opacity: 0;
    transform: translateX(0.35rem) scaleX(0.985);
    transition: opacity 0.25s ease, transform 0.4s ease;
  }
  .catalog-export .catalog-card-book:hover .catalog-card-book__pages {
    opacity: 1;
    transform: translateX(0) scaleX(1);
  }
  .catalog-export .catalog-card-book__pages::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(90deg, rgba(120,96,58,0.04) 0, rgba(120,96,58,0.04) 2px, transparent 2px, transparent 6px);
    opacity: 0.9;
    pointer-events: none;
  }
  .catalog-export .catalog-card-book__pages::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 2;
    background: linear-gradient(90deg, rgba(244,238,227,0.98) 0%, rgba(244,238,227,0.94) 42%, rgba(244,238,227,0.3) 72%, rgba(244,238,227,0) 100%);
    transition: opacity 0.25s ease;
    pointer-events: none;
  }
  .catalog-export .catalog-card-book:hover .catalog-card-book__pages::after {
    opacity: 0;
  }
  .catalog-export .catalog-card-book__page-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.65rem;
    height: 100%;
    padding: 0.75rem;
  }
  .catalog-export .catalog-card-book__page-label {
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #8b6b3f;
  }
  .catalog-export .catalog-card-book__page-text {
    margin: 0;
    color: #3b3428;
    font-size: 0.68rem;
    line-height: 1.45;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 6;
    overflow: hidden;
  }
  .catalog-export .catalog-card-book__page-meta {
    display: grid;
    gap: 0.3rem;
  }
  .catalog-export .catalog-card-book__page-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.4rem;
    padding-top: 0.28rem;
    border-top: 1px solid rgba(120, 96, 58, 0.14);
  }
  .catalog-export .catalog-card-book__page-row span {
    font-size: 0.53rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #8b6b3f;
  }
  .catalog-export .catalog-card-book__page-row strong {
    font-size: 0.6rem;
    color: #2f2b25;
    text-align: right;
    word-break: break-word;
  }
  .catalog-export .catalog-card-book__cover {
    position: absolute;
    inset: 0;
    border-radius: 0.35rem 0.75rem 0.75rem 0.35rem;
    overflow: hidden;
    transform-origin: left center;
    transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.22);
    border-left: 2px solid rgba(0, 0, 0, 0.16);
    cursor: pointer;
    isolation: isolate;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
  }
  .catalog-export .catalog-card-book__cover::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 2px;
    background: rgba(255,255,255,0.18);
    z-index: 3;
    pointer-events: none;
  }
  .catalog-export .catalog-card-book:hover .catalog-card-book__cover {
    transform: rotateY(-90deg) translateX(-1px);
  }
  .catalog-export .catalog-card-book__cover::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.16) 0%, rgba(255,255,255,0.02) 45%, rgba(0,0,0,0.12) 100%);
    pointer-events: none;
    z-index: 2;
  }
  .catalog-export .catalog-card-book__cover-art {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    opacity: 0.28;
    z-index: 0;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
  }
  .catalog-export .catalog-card-book__cover-shell {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.75rem;
    height: 100%;
    padding: 0.8rem 0.8rem 0.9rem;
  }
  .catalog-export .catalog-card-book__eyebrow {
    display: inline-flex;
    max-width: 100%;
    padding: 0.25rem 0.45rem;
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.88);
    font-size: 0.52rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .catalog-export .catalog-card-book__title {
    margin: 0.5rem 0 0;
    color: #f4dda2;
    font-family: 'Newsreader', serif;
    font-size: 1.3rem;
    line-height: 1;
    letter-spacing: -0.02em;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
  }
  .catalog-export .catalog-card-book__author {
    margin: 0.35rem 0 0;
    color: rgba(255,255,255,0.82);
    font-size: 0.72rem;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
  }
  .catalog-export .catalog-card-book__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }
  .catalog-export .catalog-card-book__meta span {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.42rem;
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.9);
    font-size: 0.52rem;
    font-weight: 700;
    line-height: 1.1;
  }
  .catalog-export .catalog-card-book--navy .catalog-card-book__cover {
    background: linear-gradient(135deg, #163450 0%, #0c2138 100%);
  }
  .catalog-export .catalog-card-book--wine .catalog-card-book__cover {
    background: linear-gradient(135deg, #6f1d2d 0%, #441019 100%);
  }
  .catalog-export .catalog-card-book--forest .catalog-card-book__cover {
    background: linear-gradient(135deg, #1e5a46 0%, #12372a 100%);
  }
  .catalog-export .catalog-card-book--wood .catalog-card-book__cover {
    background: linear-gradient(135deg, #6c4428 0%, #3d2416 100%);
  }
  .catalog-export .catalog-card-book--plum .catalog-card-book__cover {
    background: linear-gradient(135deg, #54406d 0%, #2e2240 100%);
  }
  @media (prefers-reduced-motion: reduce) {
    .catalog-export .catalog-card-book__cover {
      transition: none;
    }
  }
  .catalog-export .catalog-range {
    position: absolute;
    inset-inline: 0;
    top: -0.55rem;
    width: 100%;
    appearance: none;
    background: transparent;
    pointer-events: none;
  }
  .catalog-export .catalog-range::-webkit-slider-thumb {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    background: #ffffff;
    border: 2px solid rgb(13 148 136);
    box-shadow: 0 1px 4px rgba(0,0,0,.16);
    pointer-events: auto;
    cursor: pointer;
  }
  .catalog-export .catalog-range::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    background: #ffffff;
    border: 2px solid rgb(13 148 136);
    box-shadow: 0 1px 4px rgba(0,0,0,.16);
    pointer-events: auto;
    cursor: pointer;
  }
  .catalog-export .catalog-range::-webkit-slider-runnable-track,
  .catalog-export .catalog-range::-moz-range-track {
    height: 0.25rem;
    background: transparent;
  }
  .catalog-export .sort-menu-panel[hidden],
  .catalog-export .advanced-search-panel[hidden] {
    display: none !important;
  }
  .catalog-export .advanced-field[disabled] {
    opacity: 0.62;
    cursor: not-allowed;
    background: rgba(245,245,245,0.85);
  }
  @media (max-width: 767px) {
    #catalog-filters {
      display: none;
    }
    #catalog-filters.open {
      display: block;
    }
  }
</style>
@endsection

@section('content')
<div class="catalog-export flex-grow w-full max-w-screen-2xl mx-auto px-4 md:px-8 pt-12 pb-24">
  <section class="mb-16">
    <div class="max-w-4xl">
      <h1 class="text-[3.5rem] font-newsreader font-medium text-primary mb-6 tracking-tight">{{ $copy['heading'] }}</h1>
      <div class="relative group">
        <div class="absolute inset-y-0 left-5 flex items-center pointer-events-none">
          <span class="material-symbols-outlined text-outline" data-icon="search">search</span>
        </div>
        <input id="catalog-search-input" value="{{ $q }}" class="w-full pl-14 pr-6 py-5 bg-surface-container-highest border-b border-outline-variant/20 focus:border-secondary focus:ring-0 text-lg font-body placeholder:text-on-surface-variant/50 transition-all" placeholder="{{ $copy['search_placeholder'] }}" type="text" />
        <div class="absolute inset-y-0 right-4 flex items-center gap-2">
          <button type="button" onclick="toggleAdvancedSearch()" class="px-4 py-2 text-secondary font-semibold hover:bg-secondary-container rounded-lg transition-colors">{{ $copy['advanced'] }}</button>
        </div>
      </div>
      <p id="catalog-summary-text" class="mt-4 text-on-surface-variant text-sm font-label italic">{{ $copy['seed_summary'] }}</p>

      <div id="advanced-search-panel" class="advanced-search-panel mt-6 rounded-2xl border border-outline-variant/20 bg-white/90 p-4 md:p-5" @if(! $hasAdvancedFilters) hidden @endif>
        <div class="flex items-center justify-between gap-3 mb-4">
          <h2 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">{{ $copy['advanced_search'] }}</h2>
          <button type="button" onclick="resetAdvancedFields()" class="text-xs font-semibold text-secondary">{{ $copy['advanced_reset'] }}</button>
        </div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          <label class="block">
            <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_title'] }}</span>
            <input id="advanced-title-input" value="{{ $titleFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_title'] }}" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_author'] }}</span>
            <input id="advanced-author-input" value="{{ $authorFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_author'] }}" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_publisher'] }}</span>
            <input id="advanced-publisher-input" value="{{ $publisherFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_publisher'] }}" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_isbn'] }}</span>
            <input id="advanced-isbn-input" value="{{ $isbnFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="978..." />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_udc'] }}</span>
            <input id="advanced-udc-input" value="{{ $udcFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_udc'] }}" />
          </label>
          <label class="block">
            <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_subject'] }}</span>
            <input class="advanced-field w-full px-3 py-2 rounded-lg border border-outline-variant/20 text-sm" type="text" placeholder="{{ $copy['field_subject_help'] }}" disabled />
          </label>
        </div>
        <div class="mt-4 flex justify-end">
          <button type="button" onclick="applyAdvancedSearch()" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold">{{ $copy['advanced_apply'] }}</button>
        </div>
      </div>
    </div>
  </section>

  <button id="mobile-filter-toggle" type="button" onclick="toggleFilters()" class="md:hidden mb-6 inline-flex items-center gap-2 px-4 py-2 border border-outline-variant/30 rounded-lg text-sm font-semibold text-primary bg-white">
    <span class="material-symbols-outlined text-base">tune</span>
    <span>{{ $copy['filters'] }}</span>
    <span id="filter-count-badge" class="text-secondary">0</span>
  </button>

  <div class="flex flex-col md:flex-row gap-12">
    <aside id="catalog-filters" class="w-full md:w-64 flex-shrink-0 space-y-8">
      <div>
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['material_type'] }}</h3>
        <ul class="space-y-3">
          @foreach($copy['types'] as $index => $type)
            @php
              $typeValue = [0 => 'all', 1 => 'digital', 2 => 'archive'][$index] ?? 'all';
              $typeActive = $materialType === $typeValue || ($materialType === '' && $typeValue === 'all');
            @endphp
            <li>
              <button type="button" data-material-type="{{ $typeValue }}" class="w-full flex items-center gap-3 cursor-pointer group text-left">
                @if($typeActive)
                  <span class="w-4 h-4 border-2 border-secondary bg-secondary rounded-sm flex items-center justify-center">
                    <span class="material-symbols-outlined text-[10px] text-white font-bold">check</span>
                  </span>
                  <span class="text-sm text-on-surface font-bold">{{ $type }}</span>
                @else
                  <span class="w-4 h-4 border-2 border-outline-variant rounded-sm group-hover:border-secondary transition-colors"></span>
                  <span class="text-sm text-on-surface font-medium">{{ $type }}</span>
                @endif
              </button>
            </li>
          @endforeach
        </ul>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['publication_date'] }}</h3>
        <div class="space-y-4">
          <div class="relative pt-3">
            <div class="h-1 bg-surface-container-high relative rounded-full"></div>
            <div id="year-range-fill" class="absolute top-3 h-1 bg-secondary rounded-full" style="left: 0%; right: 0%;"></div>
            <input id="year-from-range" class="catalog-range" type="range" min="{{ $yearMin }}" max="{{ $yearMax }}" value="{{ $yearFrom }}" />
            <input id="year-to-range" class="catalog-range" type="range" min="{{ $yearMin }}" max="{{ $yearMax }}" value="{{ $yearTo }}" />
          </div>
          <div class="flex justify-between text-xs font-label text-on-surface-variant">
            <span id="year-min-label">{{ $yearMin }}</span>
            <span id="year-max-label" class="font-bold text-on-surface">{{ $yearMax }}</span>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <input id="year-from-input" class="w-full bg-white border border-outline-variant/30 px-3 py-2 text-sm rounded-lg" value="{{ $yearFrom }}" placeholder="{{ $yearMin }}" type="number" min="{{ $yearMin }}" max="{{ $yearMax }}" />
            <input id="year-to-input" class="w-full bg-white border border-outline-variant/30 px-3 py-2 text-sm rounded-lg" value="{{ $yearTo }}" placeholder="{{ $yearMax }}" type="number" min="{{ $yearMin }}" max="{{ $yearMax }}" />
          </div>
        </div>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['language'] }}</h3>
        <div id="language-chips" class="flex flex-wrap gap-2">
          <button type="button" data-lang="all" class="px-3 py-2 rounded-lg border text-sm font-medium {{ $language === 'all' ? 'bg-primary text-white border-primary' : 'border-outline-variant/30 bg-white text-on-surface' }}">ALL</button>
          <button type="button" data-lang="ru" class="px-3 py-2 rounded-lg border text-sm font-medium {{ $language === 'ru' ? 'bg-primary text-white border-primary' : 'border-outline-variant/30 bg-white text-on-surface' }}">RU</button>
          <button type="button" data-lang="kk" class="px-3 py-2 rounded-lg border text-sm font-medium {{ $language === 'kk' ? 'bg-primary text-white border-primary' : 'border-outline-variant/30 bg-white text-on-surface' }}">KK</button>
          <button type="button" data-lang="en" class="px-3 py-2 rounded-lg border text-sm font-medium {{ $language === 'en' ? 'bg-primary text-white border-primary' : 'border-outline-variant/30 bg-white text-on-surface' }}">EN</button>
        </div>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['collection'] }}</h3>
        <select id="institution-select" class="w-full bg-white border border-outline-variant/30 px-3 py-2 text-sm rounded-lg">
          @foreach($copy['institution_options'] as $value => $label)
            <option value="{{ $value }}" @selected($institution === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <ul class="space-y-3 mt-4">
          @foreach($copy['collections'] as $collection)
            <li>
              <button type="button" data-collection-filter="{{ $collection['value'] }}" class="w-full text-left text-sm text-on-surface-variant hover:text-secondary cursor-pointer {{ str_contains($collection['label'], '+') ? 'font-bold' : '' }} {{ $institution === $collection['value'] ? 'text-secondary font-bold' : '' }}">{{ $collection['label'] }}</button>
            </li>
          @endforeach
        </ul>
      </div>

      <div class="pt-6 border-t border-outline-variant/10 space-y-4">
        <label class="flex items-center gap-3 cursor-pointer">
          <input id="filter-available-only" @checked($availableOnly) class="w-5 h-5 rounded-md border-outline-variant text-secondary focus:ring-secondary/20" type="checkbox" />
          <span class="text-sm font-medium">{{ $copy['available_only'] }}</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer">
          <input id="filter-physical-only" @checked($physicalOnly) class="w-5 h-5 rounded-md border-outline-variant text-secondary focus:ring-secondary/20" type="checkbox" />
          <span class="text-sm font-medium">{{ $copy['physical_only'] }}</span>
        </label>
        <button id="clear-filters-btn" type="button" onclick="clearAllFilters()" class="w-full px-4 py-2 text-sm font-semibold border border-outline-variant/30 rounded-lg hover:bg-surface-container-low transition-colors">
          {{ $copy['clear_filters'] }}
        </button>
      </div>
    </aside>

    <div class="flex-grow">
      <div class="flex flex-col md:flex-row justify-between md:items-baseline gap-4 mb-12 border-b border-outline-variant/10 pb-4">
        <div id="catalog-results-count" class="text-on-surface-variant text-sm font-label">{{ $copy['showing'] }} <span class="text-on-surface font-bold">{{ $initialFrom }}-{{ $initialTo }}</span> {{ $copy['of'] }} <span class="font-bold">{{ $initialTotal }}</span> {{ $copy['results_for'] }} <span class="font-medium">“{{ $initialQueryLabel }}”</span></div>
        <div class="flex items-center gap-6">
          <span class="text-xs font-bold uppercase tracking-tighter text-on-surface-variant">{{ $copy['sort_by'] }}</span>
          <div class="relative" data-sort-menu>
            <button id="sort-menu-button" type="button" onclick="toggleSortMenu()" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-white px-4 py-2 text-sm font-bold text-on-surface shadow-sm hover:border-secondary/40">
              <span id="sort-menu-current">{{ $copy['sort_options'][$sort] ?? reset($copy['sort_options']) }}</span>
              <span class="material-symbols-outlined text-base">expand_more</span>
            </button>
            <div id="sort-menu-panel" class="sort-menu-panel absolute right-0 mt-2 min-w-52 rounded-xl border border-outline-variant/20 bg-white p-1 shadow-lg z-20" hidden>
              @foreach($copy['sort_options'] as $value => $label)
                <button type="button" data-sort-option="{{ $value }}" class="sort-option w-full text-left px-3 py-2 rounded-lg text-sm {{ $sort === $value ? 'bg-surface-container-high text-primary font-bold' : 'text-on-surface hover:bg-surface-container-low' }}">{{ $label }}</button>
              @endforeach
            </div>
            <select id="sort-select" class="sr-only">
              @foreach($copy['sort_options'] as $value => $label)
                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div id="catalog-active-filters" class="mb-8 rounded-xl border border-outline-variant/20 bg-surface-container-low/60 p-3 md:p-4" hidden>
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ $copy['filters'] }}</span>
          <button id="catalog-active-filters-clear" type="button" onclick="clearAllFilters()" class="text-xs font-semibold text-secondary hover:underline">{{ $copy['clear_filters'] }}</button>
        </div>
        <div id="catalog-active-filters-list" class="flex flex-wrap gap-2"></div>
      </div>

      <div id="catalog-results-list" class="space-y-16">
        @forelse($initialResults as $index => $record)
          @php
            $title = trim((string) ($record['title']['display'] ?? '')) ?: 'Untitled';
            $author = trim((string) ($record['primaryAuthor'] ?? '')) ?: $copy['ui']['author_unknown'];
            $year = $record['publicationYear'] ?? '—';
            $publisher = trim((string) ($record['publisher']['name'] ?? ''));
            $isbn = trim((string) ($record['isbn']['raw'] ?? '')) ?: '—';
            $udc = trim((string) ($record['udc']['raw'] ?? '')) ?: '—';
            $subtitleRaw = trim((string) ($record['title']['subtitle'] ?? ''));
            $subjectLabels = [];
            foreach (($record['classification'] ?? []) as $subject) {
                $label = trim((string) ($subject['label'] ?? ''));
                if ($label !== '') {
                    $subjectLabels[] = $label;
                }
            }
            $description = $subtitleRaw !== '' && ! str_starts_with($subtitleRaw, 'app.')
                ? $subtitleRaw
                : (! empty($subjectLabels) ? implode(' · ', array_slice($subjectLabels, 0, 3)) : $copy['ui']['description_placeholder']);
            $availableCopies = (int) ($record['copies']['available'] ?? 0);
            $totalCopies = (int) ($record['copies']['total'] ?? 0);
            $kind = $totalCopies > 0 ? ($availableCopies > 0 ? 'physical' : 'archive') : 'electronic';
            $badgeStyle = $kind === 'physical' ? 'bg-surface-container-highest text-on-surface-variant' : 'bg-secondary-container text-on-secondary-container';
            $badgeLabel = $kind === 'physical' ? $copy['ui']['physical'] : ($kind === 'archive' ? $copy['ui']['archive'] : $copy['ui']['electronic']);
            $primaryLabel = $kind === 'physical' ? $copy['ui']['locate'] : ($kind === 'archive' ? $copy['ui']['request'] : $copy['ui']['read']);
            $icon = $kind === 'physical' ? 'library_books' : ($kind === 'archive' ? 'history_edu' : 'visibility');
            $primaryLocation = is_array($record['availability']['locations'][0] ?? null) ? $record['availability']['locations'][0] : [];
            $locationLabel = $formatLocationLabel($primaryLocation);
            $languageLabel = strtoupper(trim((string) ($record['language']['code'] ?? '')));
            $statusStyle = $kind === 'archive' ? 'text-error' : ($kind === 'electronic' ? 'text-secondary' : 'text-on-surface-variant');
            $status = $kind === 'archive' ? $copy['ui']['permission'] : ($locationLabel ?: $copy['ui']['available']);
            $detailHref = $isbn !== '—' ? $withLang('/book/' . rawurlencode($isbn)) : $withLang('/catalog');
            $metaParts = array_values(array_filter([$author, $year, $publisher], static fn ($value) => (string) $value !== '' && (string) $value !== '—'));
          @endphp
          <article class="flex flex-col sm:flex-row gap-8 group catalog-item">
            @php
              $coverTones = ['catalog-card-book--navy', 'catalog-card-book--wine', 'catalog-card-book--forest', 'catalog-card-book--wood', 'catalog-card-book--plum'];
              $coverTone = $coverTones[$index % count($coverTones)];
              $coverUrl = trim((string) ($record['coverUrl'] ?? data_get($record, 'cover.medium') ?? data_get($record, 'cover.small') ?? ''));
              $coverDescription = trim((string) $description) !== '' ? $description : $copy['ui']['description_placeholder'];
              $coverCode = $udc !== '—' ? $udc : $isbn;
            @endphp
            <div class="catalog-card-media w-full sm:w-36 flex-shrink-0">
              <div class="catalog-card-book {{ $coverTone }} {{ $coverUrl !== '' ? 'has-art' : '' }}">
                <div class="catalog-card-book__stack">
                  <div class="catalog-card-book__pages" aria-hidden="true">
                    <div class="catalog-card-book__page-content">
                      <div>
                        <div class="catalog-card-book__page-label">{{ $publisher !== '' ? $publisher : $badgeLabel }}</div>
                        <p class="catalog-card-book__page-text">{{ $coverDescription }}</p>
                      </div>
                      <div class="catalog-card-book__page-meta">
                        <div class="catalog-card-book__page-row"><span>{{ $copy['ui']['isbn'] }}</span><strong>{{ $isbn }}</strong></div>
                        <div class="catalog-card-book__page-row"><span>{{ $copy['ui']['udc'] }}</span><strong>{{ $udc }}</strong></div>
                        <div class="catalog-card-book__page-row"><span>{{ $copy['ui']['language_label'] }}</span><strong>{{ $languageLabel !== '' ? $languageLabel : '—' }}</strong></div>
                      </div>
                    </div>
                  </div>
                  <div class="catalog-card-book__cover">
                    @if ($coverUrl !== '')
                      <div class="catalog-card-book__cover-art" style="background-image: url('{{ e($coverUrl) }}');"></div>
                    @endif
                    <div class="catalog-card-book__cover-shell">
                      <div>
                        <span class="catalog-card-book__eyebrow">{{ $badgeLabel }}</span>
                        <h3 class="catalog-card-book__title">{{ $title }}</h3>
                        <p class="catalog-card-book__author">{{ $author }}</p>
                      </div>
                      <div class="catalog-card-book__meta">
                        @if ($year !== '—')
                          <span>{{ $year }}</span>
                        @endif
                        <span>{{ $languageLabel !== '' ? $languageLabel : '—' }}</span>
                        @if ($coverCode !== '—')
                          <span>{{ $coverCode }}</span>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex-grow">
              <div class="flex justify-between items-start gap-4">
                <div>
                  <span class="inline-block px-2 py-0.5 {{ $badgeStyle }} text-[10px] font-bold uppercase tracking-wider rounded mb-2">{{ $badgeLabel }}</span>
                  <a href="{{ $detailHref }}" class="block text-2xl font-newsreader font-semibold text-primary mb-1 group-hover:text-secondary transition-colors cursor-pointer">{{ $title }}</a>
                  <p class="text-on-surface-variant font-medium mb-3">{{ implode(' · ', $metaParts) }}</p>
                </div>
                <button type="button" class="material-symbols-outlined text-on-surface-variant hover:text-secondary cursor-pointer">bookmark</button>
              </div>
              <p data-catalog-description class="text-on-surface-variant text-sm line-clamp-2 mb-4 max-w-2xl leading-relaxed">{{ $description }}</p>
              @if(! empty($subjectLabels))
                <div class="flex flex-wrap gap-2 mb-4 text-[11px] text-on-surface-variant">
                  @foreach(array_slice($subjectLabels, 0, 3) as $subjectLabel)
                    <span class="px-2 py-1 rounded-full bg-surface-container-high">{{ $subjectLabel }}</span>
                  @endforeach
                </div>
              @endif
              <div class="flex flex-wrap gap-3 text-xs text-on-surface-variant mb-6">
                <span><strong>{{ $copy['ui']['isbn'] }}:</strong> {{ $isbn }}</span>
                <span><strong>{{ $copy['ui']['udc'] }}:</strong> {{ $udc }}</span>
                <span><strong>{{ $copy['ui']['copies'] }}:</strong> {{ $availableCopies }} / {{ $totalCopies }}</span>
                <span><strong>{{ $copy['ui']['language_label'] }}:</strong> {{ $languageLabel !== '' ? $languageLabel : '—' }}</span>
                <span><strong>{{ $copy['ui']['institution_label'] }}:</strong> {{ $locationLabel }}</span>
              </div>
              <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                <a href="{{ $detailHref }}" class="text-sm font-bold text-secondary flex items-center gap-2 group/btn">
                  <span class="material-symbols-outlined text-lg">{{ $icon }}</span>
                  <span>{{ $primaryLabel }}</span>
                </a>
                <button type="button" class="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors flex items-center gap-2" onclick="copyCitation(@js($title))">
                  <span class="material-symbols-outlined text-lg">description</span>
                  <span>{{ $copy['ui']['cite'] }}</span>
                </button>
                <div class="sm:ml-auto text-xs {{ $statusStyle }} font-bold flex items-center gap-1">
                  <span class="w-2 h-2 rounded-full {{ $statusStyle === 'text-secondary' ? 'bg-secondary' : ($statusStyle === 'text-error' ? 'bg-error' : 'bg-outline') }}"></span>
                  {{ $status }}
                </div>
              </div>
            </div>
          </article>
        @empty
          <p class="text-on-surface-variant text-sm">{{ $copy['ui']['empty'] }}</p>
        @endforelse
      </div>

      <div class="mt-24 pt-12 border-t border-outline-variant/10 flex justify-center">
        <nav id="catalog-pagination" class="flex items-center gap-2" aria-label="Catalog pagination">
          <button type="button" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-colors">
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <button type="button" class="w-10 h-10 flex items-center justify-center rounded-lg bg-primary text-white font-bold">1</button>
          <button type="button" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface font-medium hover:bg-surface-container-high">2</button>
          <button type="button" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface font-medium hover:bg-surface-container-high">3</button>
          <span class="px-2 text-on-surface-variant">...</span>
          <button type="button" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface font-medium hover:bg-surface-container-high">42</button>
          <button type="button" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-colors">
            <span class="material-symbols-outlined">chevron_right</span>
          </button>
        </nav>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
  const API_ENDPOINT = '/api/v1/catalog-db';
  const LANG_SUFFIX = @json($langSuffix);
  const uiCopy = @json($copy['ui']);
  const SORT_API_MAP = {
    relevance: 'popular',
    title: 'title',
    year_desc: 'newest',
    year_asc: 'newest',
    popular: 'popular',
    newest: 'newest',
    author: 'author'
  };
  const SORT_LABELS = @json($copy['sort_options']);
  const FILTER_LABELS = {
    material_type: @json($copy['material_type']),
    publication_date: @json($copy['publication_date']),
    language: @json($copy['language']),
    collection: @json($copy['collection']),
    available_only: @json($copy['available_only']),
    physical_only: @json($copy['physical_only']),
    field_title: @json($copy['field_title']),
    field_author: @json($copy['field_author']),
    field_publisher: @json($copy['field_publisher']),
    field_isbn: @json($copy['field_isbn']),
    field_udc: @json($copy['field_udc']),
  };
  const MATERIAL_TYPE_LABELS = {
    all: '',
    digital: @json($copy['types'][1] ?? 'Digital'),
    archive: @json($copy['types'][2] ?? 'Archive'),
  };
  const YEAR_BOUNDS = { min: @json($yearMin), max: @json($yearMax) };

  let searchDebounceId = null;

  function toggleFilters() {
    const panel = document.getElementById('catalog-filters');
    panel?.classList.toggle('open');
  }

  function isMeaningfulText(value) {
    const normalized = String(value ?? '').trim();
    if (!normalized) return false;
    const lowered = normalized.toLowerCase();
    if (['null', 'undefined', '[object object]', 'n/a'].includes(lowered)) return false;
    if (lowered.startsWith('app.')) return false;
    return true;
  }

  function updateFilterBadge() {
    let active = 0;
    if ((window.catalogState?.q || '') !== '') active++;
    if ((window.catalogState?.materialType || 'all') !== 'all') active++;
    if ((window.catalogState?.title || '') !== '') active++;
    if ((window.catalogState?.author || '') !== '') active++;
    if ((window.catalogState?.publisher || '') !== '') active++;
    if ((window.catalogState?.isbn || '') !== '') active++;
    if ((window.catalogState?.udc || '') !== '') active++;
    if (document.getElementById('filter-available-only')?.checked) active++;
    if (document.getElementById('filter-physical-only')?.checked) active++;
    if ((document.getElementById('institution-select')?.value || '') !== '') active++;
    if ((document.getElementById('year-from-input')?.value || '') !== String(YEAR_BOUNDS.min)) active++;
    if ((document.getElementById('year-to-input')?.value || '') !== String(YEAR_BOUNDS.max)) active++;
    if ((window.catalogState?.language || 'all') !== 'all') active++;

    const badge = document.getElementById('filter-count-badge');
    if (badge) badge.textContent = String(active);
  }

  function resetFilterByKey(key) {
    if (key === 'q') window.catalogState.q = '';
    if (key === 'title') window.catalogState.title = '';
    if (key === 'author') window.catalogState.author = '';
    if (key === 'publisher') window.catalogState.publisher = '';
    if (key === 'isbn') window.catalogState.isbn = '';
    if (key === 'udc') window.catalogState.udc = '';
    if (key === 'materialType') window.catalogState.materialType = 'all';
    if (key === 'language') window.catalogState.language = 'all';
    if (key === 'yearRange') {
      window.catalogState.yearFrom = String(YEAR_BOUNDS.min);
      window.catalogState.yearTo = String(YEAR_BOUNDS.max);
    }
    if (key === 'institution') window.catalogState.institution = '';
    if (key === 'availableOnly') window.catalogState.availableOnly = false;
    if (key === 'physicalOnly') window.catalogState.physicalOnly = false;
  }

  function collectActiveFilters() {
    const active = [];
    const pushChip = (key, label, value) => {
      active.push({ key, label, value });
    };

    if ((window.catalogState.q || '') !== '') pushChip('q', '', window.catalogState.q);
    if ((window.catalogState.title || '') !== '') pushChip('title', FILTER_LABELS.field_title, window.catalogState.title);
    if ((window.catalogState.author || '') !== '') pushChip('author', FILTER_LABELS.field_author, window.catalogState.author);
    if ((window.catalogState.publisher || '') !== '') pushChip('publisher', FILTER_LABELS.field_publisher, window.catalogState.publisher);
    if ((window.catalogState.isbn || '') !== '') pushChip('isbn', FILTER_LABELS.field_isbn, window.catalogState.isbn);
    if ((window.catalogState.udc || '') !== '') pushChip('udc', FILTER_LABELS.field_udc, window.catalogState.udc);

    if ((window.catalogState.materialType || 'all') !== 'all') {
      pushChip('materialType', FILTER_LABELS.material_type, MATERIAL_TYPE_LABELS[window.catalogState.materialType] || window.catalogState.materialType);
    }

    if ((window.catalogState.language || 'all') !== 'all') {
      pushChip('language', FILTER_LABELS.language, String(window.catalogState.language || '').toUpperCase());
    }

    const from = Number(window.catalogState.yearFrom || YEAR_BOUNDS.min);
    const to = Number(window.catalogState.yearTo || YEAR_BOUNDS.max);
    if (from !== YEAR_BOUNDS.min || to !== YEAR_BOUNDS.max) {
      pushChip('yearRange', FILTER_LABELS.publication_date, `${from}–${to}`);
    }

    if ((window.catalogState.institution || '') !== '') {
      const selectedOption = document.querySelector(`#institution-select option[value="${window.catalogState.institution}"]`);
      const institutionLabel = selectedOption ? selectedOption.textContent.trim() : window.catalogState.institution;
      pushChip('institution', FILTER_LABELS.collection, institutionLabel);
    }

    if (window.catalogState.availableOnly) pushChip('availableOnly', '', FILTER_LABELS.available_only);
    if (window.catalogState.physicalOnly) pushChip('physicalOnly', '', FILTER_LABELS.physical_only);

    return active;
  }

  function renderActiveFilters() {
    const wrapper = document.getElementById('catalog-active-filters');
    const list = document.getElementById('catalog-active-filters-list');
    if (!wrapper || !list) return;

    const activeFilters = collectActiveFilters();
    if (!activeFilters.length) {
      wrapper.hidden = true;
      list.innerHTML = '';
      return;
    }

    wrapper.hidden = false;
    list.innerHTML = activeFilters.map((item) => {
      const label = item.label ? `<span class="text-on-surface-variant">${escapeHtml(item.label)}:</span>` : '';
      return `
        <button type="button" data-remove-filter="${escapeHtml(item.key)}" class="inline-flex items-center gap-2 rounded-full bg-white border border-outline-variant/30 px-3 py-1.5 text-xs text-on-surface hover:border-secondary/40">
          ${label}
          <span class="font-semibold">${escapeHtml(item.value)}</span>
          <span class="material-symbols-outlined text-sm text-on-surface-variant">close</span>
        </button>
      `;
    }).join('');

    list.querySelectorAll('[data-remove-filter]').forEach((button) => {
      button.addEventListener('click', () => {
        resetFilterByKey(button.dataset.removeFilter || '');

        const searchInput = document.getElementById('catalog-search-input');
        if (searchInput) searchInput.value = window.catalogState.q || '';
        document.getElementById('advanced-title-input').value = window.catalogState.title || '';
        document.getElementById('advanced-author-input').value = window.catalogState.author || '';
        document.getElementById('advanced-publisher-input').value = window.catalogState.publisher || '';
        document.getElementById('advanced-isbn-input').value = window.catalogState.isbn || '';
        document.getElementById('advanced-udc-input').value = window.catalogState.udc || '';
        document.getElementById('filter-available-only').checked = !!window.catalogState.availableOnly;
        document.getElementById('filter-physical-only').checked = !!window.catalogState.physicalOnly;
        document.getElementById('institution-select').value = window.catalogState.institution || '';

        updateYearRangeVisual();
        window.catalogState.page = 1;
        loadCatalog();
      });
    });
  }

  function clearAllFilters() {
    window.catalogState = {
      q: '',
      title: '',
      author: '',
      publisher: '',
      isbn: '',
      udc: '',
      language: 'all',
      sort: 'relevance',
      yearFrom: String(YEAR_BOUNDS.min),
      yearTo: String(YEAR_BOUNDS.max),
      availableOnly: false,
      physicalOnly: false,
      institution: '',
      materialType: 'all',
      page: 1,
      advancedOpen: false,
    };

    document.getElementById('catalog-search-input').value = '';
    document.getElementById('year-from-input').value = String(YEAR_BOUNDS.min);
    document.getElementById('year-to-input').value = String(YEAR_BOUNDS.max);
    document.getElementById('year-from-range').value = String(YEAR_BOUNDS.min);
    document.getElementById('year-to-range').value = String(YEAR_BOUNDS.max);
    document.getElementById('filter-available-only').checked = false;
    document.getElementById('filter-physical-only').checked = false;
    document.getElementById('institution-select').value = '';
    document.getElementById('sort-select').value = 'relevance';
    document.getElementById('advanced-title-input').value = '';
    document.getElementById('advanced-author-input').value = '';
    document.getElementById('advanced-publisher-input').value = '';
    document.getElementById('advanced-isbn-input').value = '';
    document.getElementById('advanced-udc-input').value = '';
    document.getElementById('advanced-search-panel')?.setAttribute('hidden', 'hidden');

    updateYearRangeVisual();
    updateFilterBadge();
    syncLanguageButtons();
    syncMaterialButtons();
    syncCollectionButtons();
    syncSortMenu();
    loadCatalog();
  }

  function copyCitation(title) {
    navigator.clipboard?.writeText(title).catch(() => {});
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatValue(value, fallback = '—') {
    return isMeaningfulText(value) ? String(value).trim() : fallback;
  }

  function formatLocationLabel(location) {
    const serviceCode = String(location?.servicePoint?.code || '').trim().toLowerCase();
    const serviceName = String(location?.servicePoint?.name || '').trim();
    const campusCode = String(location?.campus?.code || '').trim().toLowerCase();
    const unitCode = String(location?.institutionUnit?.code || '').trim().toLowerCase();

    let libraryLabel = '';
    if (serviceCode === '1' || campusCode === 'university_economic') {
      libraryLabel = uiCopy.economic_library;
    } else if (serviceCode === '2' || campusCode === 'university_technological') {
      libraryLabel = uiCopy.technology_library;
    } else if (serviceCode === '3' || campusCode === 'college_main' || unitCode === 'college') {
      libraryLabel = uiCopy.college_library;
    } else if (serviceCode === 'kstlib' || campusCode === 'university_central') {
      libraryLabel = uiCopy.central_library;
    }

    if (['1', '2', '3'].includes(serviceCode)) {
      return `${libraryLabel} · ${uiCopy.cabinet_short} ${serviceCode}`;
    }

    if (serviceCode === 'kstlib') {
      return `${libraryLabel} · ${uiCopy.main_cabinet}`;
    }

    if (libraryLabel) {
      return libraryLabel;
    }

    return formatValue(serviceName, uiCopy.no_location);
  }

  function toggleAdvancedSearch() {
    const panel = document.getElementById('advanced-search-panel');
    if (!panel) return;
    const isHidden = panel.hasAttribute('hidden');
    if (isHidden) {
      panel.removeAttribute('hidden');
      document.getElementById('advanced-title-input')?.focus();
    } else {
      panel.setAttribute('hidden', 'hidden');
    }
    window.catalogState.advancedOpen = isHidden;
  }

  function resetAdvancedFields() {
    document.getElementById('advanced-title-input').value = '';
    document.getElementById('advanced-author-input').value = '';
    document.getElementById('advanced-publisher-input').value = '';
    document.getElementById('advanced-isbn-input').value = '';
    document.getElementById('advanced-udc-input').value = '';
    window.catalogState.title = '';
    window.catalogState.author = '';
    window.catalogState.publisher = '';
    window.catalogState.isbn = '';
    window.catalogState.udc = '';
    updateFilterBadge();
  }

  function applyAdvancedSearch() {
    window.catalogState.title = document.getElementById('advanced-title-input')?.value.trim() || '';
    window.catalogState.author = document.getElementById('advanced-author-input')?.value.trim() || '';
    window.catalogState.publisher = document.getElementById('advanced-publisher-input')?.value.trim() || '';
    window.catalogState.isbn = document.getElementById('advanced-isbn-input')?.value.trim() || '';
    window.catalogState.udc = document.getElementById('advanced-udc-input')?.value.trim() || '';
    window.catalogState.page = 1;
    loadCatalog();
  }

  function clampYearState() {
    let from = Number(window.catalogState.yearFrom || YEAR_BOUNDS.min);
    let to = Number(window.catalogState.yearTo || YEAR_BOUNDS.max);

    from = Math.max(YEAR_BOUNDS.min, Math.min(from, YEAR_BOUNDS.max));
    to = Math.max(YEAR_BOUNDS.min, Math.min(to, YEAR_BOUNDS.max));

    if (from > to) {
      if (document.activeElement?.id === 'year-from-range' || document.activeElement?.id === 'year-from-input') {
        to = from;
      } else {
        from = to;
      }
    }

    window.catalogState.yearFrom = String(from);
    window.catalogState.yearTo = String(to);
  }

  function updateYearRangeVisual() {
    clampYearState();
    const from = Number(window.catalogState.yearFrom);
    const to = Number(window.catalogState.yearTo);
    const span = Math.max(YEAR_BOUNDS.max - YEAR_BOUNDS.min, 1);
    const startPct = ((from - YEAR_BOUNDS.min) / span) * 100;
    const endPct = ((to - YEAR_BOUNDS.min) / span) * 100;
    const fill = document.getElementById('year-range-fill');

    document.getElementById('year-from-input').value = String(from);
    document.getElementById('year-to-input').value = String(to);
    document.getElementById('year-from-range').value = String(from);
    document.getElementById('year-to-range').value = String(to);

    if (fill) {
      fill.style.left = `${startPct}%`;
      fill.style.right = `${100 - endPct}%`;
    }
  }

  function toggleSortMenu() {
    const panel = document.getElementById('sort-menu-panel');
    if (!panel) return;
    panel.hidden = !panel.hidden;
  }

  function syncSortMenu() {
    const current = document.getElementById('sort-menu-current');
    const currentValue = window.catalogState.sort || 'relevance';
    if (current) {
      current.textContent = SORT_LABELS[currentValue] || SORT_LABELS.relevance || 'Relevance';
    }

    document.querySelectorAll('[data-sort-option]').forEach((button) => {
      const active = button.dataset.sortOption === currentValue;
      button.className = `sort-option w-full text-left px-3 py-2 rounded-lg text-sm ${active ? 'bg-surface-container-high text-primary font-bold' : 'text-on-surface hover:bg-surface-container-low'}`;
    });
  }

  function syncCollectionButtons() {
    document.querySelectorAll('[data-collection-filter]').forEach((button) => {
      const active = button.dataset.collectionFilter === (window.catalogState.institution || '');
      button.className = `w-full text-left text-sm hover:text-secondary cursor-pointer ${button.textContent.includes('+') ? 'font-bold' : ''} ${active ? 'text-secondary font-bold' : 'text-on-surface-variant'}`;
    });
  }

  function detailHref(isbn) {
    if (!isbn || isbn === '—') {
      return '/catalog' + LANG_SUFFIX;
    }
    return '/book/' + encodeURIComponent(isbn) + LANG_SUFFIX;
  }

  function deriveMaterialKind(item) {
    const subjectText = Array.isArray(item?.classification)
      ? item.classification.map((subject) => String(subject?.label || '').toLowerCase()).join(' ')
      : '';
    const total = Number(item?.copies?.total ?? 0);
    const available = Number(item?.copies?.available ?? 0);

    if (/dissert|thesis|диссер|archive|архив/.test(subjectText)) return 'archive';
    if (total > 0 && available === 0) return 'archive';
    if (total > 0) return 'physical';
    return 'digital';
  }

  function getMaterialPresentation(kind) {
    if (kind === 'physical') {
      return {
        badgeClass: 'bg-surface-container-highest text-on-surface-variant',
        badgeLabel: uiCopy.physical,
        primaryLabel: uiCopy.locate,
        primaryIcon: 'library_books',
        statusClass: 'text-on-surface-variant',
        statusDot: 'bg-outline',
      };
    }

    if (kind === 'archive') {
      return {
        badgeClass: 'bg-secondary-container text-on-secondary-container',
        badgeLabel: uiCopy.archive,
        primaryLabel: uiCopy.request,
        primaryIcon: 'history_edu',
        statusClass: 'text-error',
        statusDot: 'bg-error',
      };
    }

    return {
      badgeClass: 'bg-secondary-container text-on-secondary-container',
      badgeLabel: uiCopy.electronic,
      primaryLabel: uiCopy.read,
      primaryIcon: 'visibility',
      statusClass: 'text-secondary',
      statusDot: 'bg-secondary',
    };
  }

  function normalizeRecord(item) {
    const kind = deriveMaterialKind(item);
    const title = formatValue(item?.title?.display || item?.title?.raw, 'Untitled');
    const author = formatValue(item?.primaryAuthor, uiCopy.author_unknown);
    const publicationYear = isMeaningfulText(item?.publicationYear) ? String(item.publicationYear) : '';
    const publisher = formatValue(item?.publisher?.name, '');
    const isbn = formatValue(item?.isbn?.raw);
    const udc = formatValue(item?.udc?.raw);
    const location = formatLocationLabel(item?.availability?.locations?.[0] || {});
    const language = formatValue((item?.language?.code || item?.language?.raw || '').toUpperCase(), '—');
    const copies = Number(item?.copies?.available ?? 0);
    const total = Number(item?.copies?.total ?? 0);
    const subjects = Array.isArray(item?.classification)
      ? item.classification.map((subject) => String(subject?.label || '').trim()).filter(Boolean).slice(0, 3)
      : [];
    const subtitle = isMeaningfulText(item?.title?.subtitle) ? String(item.title.subtitle).trim() : '';
    const description = subtitle || (subjects.length ? subjects.join(' · ') : uiCopy.description_placeholder);
    const metaLine = [author, publicationYear, publisher].filter((part) => isMeaningfulText(part));
    const statusLabel = kind === 'archive'
      ? uiCopy.permission
      : (location !== uiCopy.no_location ? location : uiCopy.available);

    return {
      title,
      author,
      publicationYear: publicationYear || '—',
      publisher,
      metaLine: metaLine.join(' · '),
      description,
      subjects,
      isbn,
      udc,
      language,
      copies,
      total,
      location,
      coverUrl: normalizeText(item?.coverUrl || item?.cover?.medium || item?.cover?.small, ''),
      detailUrl: detailHref(isbn),
      statusLabel,
      ...getMaterialPresentation(kind),
    };
  }

  function coverToneClass(index) {
    const tones = [
      'catalog-card-book--navy',
      'catalog-card-book--wine',
      'catalog-card-book--forest',
      'catalog-card-book--wood',
      'catalog-card-book--plum'
    ];

    return tones[index % tones.length];
  }

  function buildBookMedia(record, index) {
    const descriptionText = record.description || uiCopy.description_placeholder;
    const coverCode = record.udc !== '—' ? record.udc : record.isbn;
    const coverArt = record.coverUrl
      ? `<div class="catalog-card-book__cover-art" style="background-image: url('${encodeURI(record.coverUrl)}');"></div>`
      : '';

    return `
      <div class="catalog-card-media w-full sm:w-36 flex-shrink-0">
        <div class="catalog-card-book ${coverToneClass(index)} ${record.coverUrl ? 'has-art' : ''}">
          <div class="catalog-card-book__stack">
            <div class="catalog-card-book__pages" aria-hidden="true">
              <div class="catalog-card-book__page-content">
                <div>
                  <div class="catalog-card-book__page-label">${escapeHtml(record.publisher || record.badgeLabel)}</div>
                  <p class="catalog-card-book__page-text">${escapeHtml(descriptionText)}</p>
                </div>
                <div class="catalog-card-book__page-meta">
                  <div class="catalog-card-book__page-row"><span>${escapeHtml(uiCopy.isbn)}</span><strong>${escapeHtml(record.isbn)}</strong></div>
                  <div class="catalog-card-book__page-row"><span>${escapeHtml(uiCopy.udc)}</span><strong>${escapeHtml(record.udc)}</strong></div>
                  <div class="catalog-card-book__page-row"><span>${escapeHtml(uiCopy.language_label)}</span><strong>${escapeHtml(record.language)}</strong></div>
                </div>
              </div>
            </div>
            <div class="catalog-card-book__cover">
              ${coverArt}
              <div class="catalog-card-book__cover-shell">
                <div>
                  <span class="catalog-card-book__eyebrow">${escapeHtml(record.badgeLabel)}</span>
                  <h3 class="catalog-card-book__title">${escapeHtml(record.title)}</h3>
                  <p class="catalog-card-book__author">${escapeHtml(record.author)}</p>
                </div>
                <div class="catalog-card-book__meta">
                  ${record.publicationYear !== '—' ? `<span>${escapeHtml(record.publicationYear)}</span>` : ''}
                  <span>${escapeHtml(record.language)}</span>
                  ${coverCode !== '—' ? `<span>${escapeHtml(coverCode)}</span>` : ''}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function buildCard(item, index) {
    const record = normalizeRecord(item);
    const subjectsHtml = record.subjects.length
      ? `<div class="flex flex-wrap gap-2 mb-4 text-[11px] text-on-surface-variant">${record.subjects.map((subject) => `<span class="px-2 py-1 rounded-full bg-surface-container-high">${escapeHtml(subject)}</span>`).join('')}</div>`
      : '';

    return `
      <article class="flex flex-col sm:flex-row gap-8 group catalog-item">
        ${buildBookMedia(record, index)}
        <div class="flex-grow">
          <div class="flex justify-between items-start gap-4">
            <div>
              <span class="inline-block px-2 py-0.5 ${record.badgeClass} text-[10px] font-bold uppercase tracking-wider rounded mb-2">${escapeHtml(record.badgeLabel)}</span>
              <a href="${record.detailUrl}" class="block text-2xl font-newsreader font-semibold text-primary mb-1 group-hover:text-secondary transition-colors cursor-pointer">${escapeHtml(record.title)}</a>
              <p class="text-on-surface-variant font-medium mb-3">${escapeHtml(record.metaLine)}</p>
            </div>
            <button type="button" class="material-symbols-outlined text-on-surface-variant hover:text-secondary cursor-pointer" aria-label="Bookmark">bookmark</button>
          </div>
          <p data-catalog-description class="text-on-surface-variant text-sm line-clamp-2 mb-4 max-w-2xl leading-relaxed">${escapeHtml(record.description)}</p>
          ${subjectsHtml}
          <div class="flex flex-wrap gap-3 text-xs text-on-surface-variant mb-6">
            <span><strong>${uiCopy.isbn}:</strong> ${escapeHtml(record.isbn)}</span>
            <span><strong>${uiCopy.udc}:</strong> ${escapeHtml(record.udc)}</span>
            <span><strong>${escapeHtml(uiCopy.copies)}:</strong> ${escapeHtml(record.copies)} / ${escapeHtml(record.total)}</span>
            <span><strong>${escapeHtml(uiCopy.language_label)}:</strong> ${escapeHtml(record.language)}</span>
            <span><strong>${escapeHtml(uiCopy.institution_label)}:</strong> ${escapeHtml(record.location)}</span>
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
            <a href="${record.detailUrl}" class="text-sm font-bold text-secondary flex items-center gap-2 group/btn">
              <span class="material-symbols-outlined text-lg">${record.primaryIcon}</span>
              <span>${escapeHtml(record.primaryLabel)}</span>
            </a>
            <button type="button" class="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors flex items-center gap-2" onclick="copyCitation(${JSON.stringify(record.title)})">
              <span class="material-symbols-outlined text-lg">description</span>
              <span>${escapeHtml(uiCopy.cite)}</span>
            </button>
            <div class="sm:ml-auto text-xs ${record.statusClass} font-bold flex items-center gap-1">
              <span class="w-2 h-2 rounded-full ${record.statusDot}"></span>
              ${escapeHtml(record.statusLabel)}
            </div>
          </div>
        </div>
      </article>
    `;
  }

  function syncLanguageButtons() {
    document.querySelectorAll('#language-chips button').forEach((button) => {
      const isActive = button.dataset.lang === window.catalogState.language;
      button.className = isActive
        ? 'px-3 py-2 rounded-lg border text-sm font-medium bg-primary text-white border-primary'
        : 'px-3 py-2 rounded-lg border text-sm font-medium border-outline-variant/30 bg-white text-on-surface';
    });
  }

  function syncMaterialButtons() {
    document.querySelectorAll('[data-material-type]').forEach((button) => {
      const active = (button.dataset.materialType || 'all') === (window.catalogState.materialType || 'all');
      const box = button.querySelector('span');
      const label = button.querySelectorAll('span')[1];
      if (!box || !label) return;

      box.className = active
        ? 'w-4 h-4 border-2 border-secondary bg-secondary rounded-sm flex items-center justify-center'
        : 'w-4 h-4 border-2 border-outline-variant rounded-sm group-hover:border-secondary transition-colors';
      box.innerHTML = active ? '<span class="material-symbols-outlined text-[10px] text-white font-bold">check</span>' : '';
      label.className = active ? 'text-sm text-on-surface font-bold' : 'text-sm text-on-surface font-medium';
    });
  }

  function buildUiParams() {
    const params = new URLSearchParams();
    if (window.catalogState.q) params.set('q', window.catalogState.q);
    if (window.catalogState.title) params.set('title', window.catalogState.title);
    if (window.catalogState.author) params.set('author', window.catalogState.author);
    if (window.catalogState.publisher) params.set('publisher', window.catalogState.publisher);
    if (window.catalogState.isbn) params.set('isbn', window.catalogState.isbn);
    if (window.catalogState.udc) params.set('udc', window.catalogState.udc);
    if (window.catalogState.language && window.catalogState.language !== 'all') params.set('language', window.catalogState.language);
    if (window.catalogState.sort && window.catalogState.sort !== 'relevance') params.set('sort', window.catalogState.sort);
    if (window.catalogState.yearFrom && Number(window.catalogState.yearFrom) !== YEAR_BOUNDS.min) params.set('year_from', window.catalogState.yearFrom);
    if (window.catalogState.yearTo && Number(window.catalogState.yearTo) !== YEAR_BOUNDS.max) params.set('year_to', window.catalogState.yearTo);
    if (window.catalogState.availableOnly) params.set('available_only', '1');
    if (window.catalogState.physicalOnly) params.set('physical_only', '1');
    if (window.catalogState.institution) params.set('institution', window.catalogState.institution);
    if (window.catalogState.materialType && window.catalogState.materialType !== 'all') params.set('material_type', window.catalogState.materialType);
    if (window.catalogState.page && window.catalogState.page > 1) params.set('page', String(window.catalogState.page));
    if (@json($lang) !== 'ru') params.set('lang', @json($lang));
    return params;
  }

  function syncUrl() {
    const base = new URL(window.location.href);
    base.search = buildUiParams().toString();
    window.history.replaceState({}, '', base.toString());
  }

  function renderPagination(meta = {}) {
    const nav = document.getElementById('catalog-pagination');
    if (!nav) return;

    const totalPages = Math.max(1, Number(meta.total_pages || meta.totalPages || 1));
    const currentPage = Math.max(1, Number(meta.page || 1));
    const pages = [];

    if (totalPages <= 5) {
      for (let page = 1; page <= totalPages; page += 1) pages.push(page);
    } else {
      pages.push(1, 2, 3, '…', totalPages);
    }

    const previousDisabled = currentPage <= 1;
    const nextDisabled = currentPage >= totalPages;

    nav.innerHTML = `
      <button type="button" ${previousDisabled ? 'disabled' : ''} data-page="${Math.max(1, currentPage - 1)}" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-colors ${previousDisabled ? 'opacity-40 cursor-not-allowed' : ''}" aria-label="${escapeHtml(uiCopy.page_prev)}">
        <span class="material-symbols-outlined">chevron_left</span>
      </button>
      ${pages.map((page) => {
        if (page === '…') {
          return '<span class="px-2 text-on-surface-variant">...</span>';
        }
        const active = page === currentPage;
        return `<button type="button" data-page="${page}" class="w-10 h-10 flex items-center justify-center rounded-lg ${active ? 'bg-primary text-white font-bold' : 'text-on-surface font-medium hover:bg-surface-container-high'}">${page}</button>`;
      }).join('')}
      <button type="button" ${nextDisabled ? 'disabled' : ''} data-page="${Math.min(totalPages, currentPage + 1)}" class="w-10 h-10 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-colors ${nextDisabled ? 'opacity-40 cursor-not-allowed' : ''}" aria-label="${escapeHtml(uiCopy.page_next)}">
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
    `;

    nav.querySelectorAll('[data-page]').forEach((button) => {
      if (button.hasAttribute('disabled')) return;
      button.addEventListener('click', () => {
        window.catalogState.page = Number(button.dataset.page || '1');
        loadCatalog();
      });
    });
  }

  async function loadCatalog() {
    clampYearState();
    const apiParams = new URLSearchParams();
    if (window.catalogState.q) apiParams.set('q', window.catalogState.q);
    if (window.catalogState.title) apiParams.set('title', window.catalogState.title);
    if (window.catalogState.author) apiParams.set('author', window.catalogState.author);
    if (window.catalogState.publisher) apiParams.set('publisher', window.catalogState.publisher);
    if (window.catalogState.isbn) apiParams.set('isbn', window.catalogState.isbn);
    if (window.catalogState.udc) apiParams.set('udc', window.catalogState.udc);
    if (window.catalogState.language && window.catalogState.language !== 'all') apiParams.set('language', window.catalogState.language);
    if (window.catalogState.yearFrom) apiParams.set('year_from', window.catalogState.yearFrom);
    if (window.catalogState.yearTo) apiParams.set('year_to', window.catalogState.yearTo);
    if (window.catalogState.availableOnly) apiParams.set('available_only', '1');
    if (window.catalogState.physicalOnly) apiParams.set('physical_only', '1');
    if (window.catalogState.institution) apiParams.set('institution', window.catalogState.institution);
    if (window.catalogState.materialType && window.catalogState.materialType !== 'all') apiParams.set('material_type', window.catalogState.materialType);
    apiParams.set('page', String(window.catalogState.page || 1));
    apiParams.set('sort', SORT_API_MAP[window.catalogState.sort] || 'popular');
    apiParams.set('limit', '10');

    const container = document.getElementById('catalog-results-list');
    const count = document.getElementById('catalog-results-count');
    const summary = document.getElementById('catalog-summary-text');

    try {
      const response = await fetch(`${API_ENDPOINT}?${apiParams.toString()}`, { headers: { Accept: 'application/json' } });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload?.message || 'Catalog request failed');
      }

      let data = Array.isArray(payload?.data) ? payload.data : [];
      const meta = payload?.meta || {};

      if (window.catalogState.sort === 'year_asc') {
        data = [...data].sort((left, right) => Number(left?.publicationYear || 0) - Number(right?.publicationYear || 0));
      }

      data = applyMaterialTypeFilter(data);

      if (container) {
        container.innerHTML = data.length
          ? data.map((item, index) => buildCard(item, index)).join('')
          : `<p class="text-on-surface-variant text-sm">${escapeHtml(uiCopy.empty)}</p>`;
      }

      const total = Number(meta.total || data.length || 0);
      const perPage = Number(meta.per_page || data.length || 0);
      const currentPage = Number(meta.page || 1);
      const fromValue = total > 0 ? ((currentPage - 1) * Math.max(perPage, 1)) + 1 : 0;
      const toValue = total > 0 ? Math.min(((currentPage - 1) * Math.max(perPage, 1)) + data.length, total) : 0;
      const queryLabel = window.catalogState.q || (document.querySelector('#language-chips button.bg-primary')?.textContent || 'Catalog');

      if (count) {
        count.innerHTML = `${@json($copy['showing'])} <span class="text-on-surface font-bold">${fromValue}-${toValue}</span> ${@json($copy['of'])} <span class="font-bold">${total}</span> ${uiCopy.results_for} <span class="font-medium">“${escapeHtml(queryLabel)}”</span>`;
      }

      if (summary) {
        summary.textContent = `${total.toLocaleString()} ${uiCopy.results_for}.`;
      }

      renderPagination(meta);
    } catch (error) {
      console.error('Catalog load failed:', error);
      if (summary) {
        summary.textContent = uiCopy.fallback_loaded;
      }
      renderPagination({ page: 1, total_pages: 1 });
    }

    updateFilterBadge();
    renderActiveFilters();
    updateYearRangeVisual();
    syncLanguageButtons();
    syncMaterialButtons();
    syncSortMenu();
    syncCollectionButtons();
    syncUrl();
  }

  window.catalogState = {
    q: @json($q),
    title: @json($titleFilter),
    author: @json($authorFilter),
    publisher: @json($publisherFilter),
    isbn: @json($isbnFilter),
    udc: @json($udcFilter),
    language: @json($language),
    sort: @json($sort),
    yearFrom: @json($yearFrom),
    yearTo: @json($yearTo),
    availableOnly: @json($availableOnly),
    physicalOnly: @json($physicalOnly),
    institution: @json($institution),
    materialType: @json($materialType !== '' ? $materialType : 'all'),
    page: @json($initialPage),
    advancedOpen: @json($hasAdvancedFilters),
  };

  document.getElementById('catalog-search-input')?.addEventListener('input', (event) => {
    const value = event.target.value.trim();
    window.catalogState.q = value;
    window.catalogState.page = 1;
    clearTimeout(searchDebounceId);
    searchDebounceId = window.setTimeout(() => loadCatalog(), 250);
  });

  document.getElementById('catalog-search-input')?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      clearTimeout(searchDebounceId);
      window.catalogState.q = event.target.value.trim();
      window.catalogState.page = 1;
      loadCatalog();
    }
  });

  document.getElementById('year-from-input')?.addEventListener('change', (event) => {
    window.catalogState.yearFrom = event.target.value.trim() || String(YEAR_BOUNDS.min);
    window.catalogState.page = 1;
    updateYearRangeVisual();
    loadCatalog();
  });

  document.getElementById('year-to-input')?.addEventListener('change', (event) => {
    window.catalogState.yearTo = event.target.value.trim() || String(YEAR_BOUNDS.max);
    window.catalogState.page = 1;
    updateYearRangeVisual();
    loadCatalog();
  });

  document.getElementById('year-from-range')?.addEventListener('input', (event) => {
    window.catalogState.yearFrom = event.target.value;
    updateYearRangeVisual();
  });

  document.getElementById('year-to-range')?.addEventListener('input', (event) => {
    window.catalogState.yearTo = event.target.value;
    updateYearRangeVisual();
  });

  document.getElementById('year-from-range')?.addEventListener('change', () => {
    window.catalogState.page = 1;
    loadCatalog();
  });

  document.getElementById('year-to-range')?.addEventListener('change', () => {
    window.catalogState.page = 1;
    loadCatalog();
  });

  document.getElementById('filter-available-only')?.addEventListener('change', (event) => {
    window.catalogState.availableOnly = event.target.checked;
    window.catalogState.page = 1;
    loadCatalog();
  });

  document.getElementById('filter-physical-only')?.addEventListener('change', (event) => {
    window.catalogState.physicalOnly = event.target.checked;
    window.catalogState.page = 1;
    loadCatalog();
  });

  document.getElementById('institution-select')?.addEventListener('change', (event) => {
    window.catalogState.institution = event.target.value;
    window.catalogState.page = 1;
    loadCatalog();
  });

  document.getElementById('sort-select')?.addEventListener('change', (event) => {
    window.catalogState.sort = event.target.value;
    window.catalogState.page = 1;
    syncSortMenu();
    loadCatalog();
  });

  document.querySelectorAll('[data-sort-option]').forEach((button) => {
    button.addEventListener('click', () => {
      window.catalogState.sort = button.dataset.sortOption || 'relevance';
      const select = document.getElementById('sort-select');
      if (select) select.value = window.catalogState.sort;
      document.getElementById('sort-menu-panel')?.setAttribute('hidden', 'hidden');
      document.getElementById('sort-menu-panel').hidden = true;
      window.catalogState.page = 1;
      syncSortMenu();
      loadCatalog();
    });
  });

  document.querySelectorAll('#language-chips button').forEach((button) => {
    button.addEventListener('click', () => {
      window.catalogState.language = button.dataset.lang || 'all';
      window.catalogState.page = 1;
      loadCatalog();
    });
  });

  document.querySelectorAll('[data-material-type]').forEach((button) => {
    button.addEventListener('click', () => {
      window.catalogState.materialType = button.dataset.materialType || 'all';
      window.catalogState.page = 1;
      loadCatalog();
    });
  });

  document.querySelectorAll('[data-collection-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      window.catalogState.institution = button.dataset.collectionFilter || '';
      const select = document.getElementById('institution-select');
      if (select) select.value = window.catalogState.institution;
      window.catalogState.page = 1;
      syncCollectionButtons();
      loadCatalog();
    });
  });

  document.addEventListener('click', (event) => {
    const panel = document.getElementById('sort-menu-panel');
    const wrapper = event.target.closest('[data-sort-menu]');
    if (panel && !wrapper) {
      panel.hidden = true;
      panel.setAttribute('hidden', 'hidden');
    }
  });

  ['advanced-title-input', 'advanced-author-input', 'advanced-publisher-input', 'advanced-isbn-input', 'advanced-udc-input'].forEach((id) => {
    document.getElementById(id)?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyAdvancedSearch();
      }
    });
  });

  updateFilterBadge();
  renderActiveFilters();
  updateYearRangeVisual();
  syncLanguageButtons();
  syncMaterialButtons();
  syncSortMenu();
  syncCollectionButtons();
  renderPagination({ page: @json($initialPage), total_pages: @json((int) ($initialMeta['total_pages'] ?? $initialMeta['totalPages'] ?? 1)) });
  loadCatalog();
</script>
@endsection
