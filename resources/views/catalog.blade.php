@extends('layouts.public', ['activePage' => 'catalog'])

@php
  $lang = app()->getLocale();
  $lang = in_array($lang, ['kk', 'ru', 'en'], true) ? $lang : 'ru';

  $withLang = function (string $path, array $query = []) use ($lang): string {
      $normalizedPath = '/' . ltrim($path, '/');
      if ($normalizedPath === '//') {
          $normalizedPath = '/';
      }

      if ($lang !== 'kk' && ! array_key_exists('lang', $query)) {
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
  $titleFilter = (string) request()->query('title', '');
  $authorFilter = (string) request()->query('author', '');
  $publisherFilter = (string) request()->query('publisher', '');
  $isbnFilter = (string) request()->query('isbn', '');
  $subjectFilter = (string) request()->query('subject', '');
  $udcFilter = (string) request()->query('udc', '');

  $copy = [
      'ru' => [
          'title' => 'Каталог книг — Digital Library',
          'eyebrow' => 'Фонд библиотеки',
          'heading' => 'Каталог университетской библиотеки',
          'lead' => 'Просматривайте книги, электронные ресурсы и архивные издания с удобными фильтрами по фонду, году, языку и коллекции.',
          'search_placeholder' => 'Поиск по названию, автору, ISBN или теме...',
          'advanced' => 'Расширенный',
          'seed_summary' => 'Просмотр академических материалов университета.',
          'filters' => 'Фильтры',
          'resource_type' => 'Тип документа',
          'publication_date' => 'Год издания',
          'language' => 'Язык',
          'fund' => 'Фонд',
          'branch' => 'Филиал',
          'category' => 'Тематика',
          'udc_axis' => 'УДК',
          'availability' => 'Доступность',
          'format' => 'Формат',
          'institution' => 'Подразделение',
          'any_option' => 'Все',
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
          'field_subject_help' => 'Тема, ключевое слово или текст аннотации',
          'year_from' => 'Год от',
          'year_to' => 'Год до',
          // Label maps only. The sidebar never renders these as an option list —
          // every option comes from $catalogFacets. The maps stay complete so a
          // value that appears after the MARC import never shows as a raw slug.
          'resource_type_labels' => [
              'book' => 'Книга',
              'textbook' => 'Учебник',
              'study_guide' => 'Учебное пособие',
              'methodical' => 'Методическое пособие',
              'journal' => 'Журнал',
              'periodical' => 'Периодическое издание',
              'article' => 'Статья',
              'dissertation' => 'Диссертация',
              'abstract' => 'Автореферат',
              'publication' => 'Научная публикация',
              'ebook' => 'Электронная книга',
              'digital_document' => 'Электронный документ',
          ],
          'resource_type_groups' => [
              'books' => 'Книги',
              'study' => 'Учебные издания',
              'periodicals' => 'Периодика',
              'research' => 'Научные работы',
              'digital' => 'Электронные',
              'other' => 'Прочее',
          ],
          'category_labels' => [
              'technology' => 'Технологии',
              'economics' => 'Экономика',
              'education' => 'Образование',
              'library_science' => 'Библиотечное дело',
              'law' => 'Право',
              'psychology' => 'Психология',
              'mathematics' => 'Математика',
              'periodicals' => 'Периодика',
              'history' => 'История',
              'science' => 'Наука',
          ],
          'availability_labels' => [
              'available' => 'В наличии',
              'issued' => 'Выдана',
              'electronic_only' => 'Только электронная',
              'processing' => 'В обработке',
              'repair' => 'В ремонте',
          ],
          'format_labels' => [
              'print' => 'Печатный',
              'electronic' => 'Электронный',
              'hybrid' => 'Гибридный',
          ],
          'language_labels' => [
              'ru' => 'RU',
              'kk' => 'KK',
              'en' => 'EN',
              'other' => 'Другие',
          ],
          'institution_labels' => [
              'technology_library' => 'Технологическая библиотека',
              'economic_library' => 'Экономическая библиотека',
              'college_library' => 'Библиотека колледжа',
              'ktslib' => 'Центральная библиотека',
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
              'hybrid_badge' => 'Печатный + электронный',
              'archive' => 'Архив',
              'read' => 'Читать онлайн',
              'locate' => 'Найти на полке',
              'request' => 'Запросить просмотр',
              'cite' => 'Цитировать',
              'shortlist_add' => 'В подборку',
              'shortlist_saved' => 'В подборке',
              'shortlist_counter' => 'В подборке',
              'shortlist_open' => 'Открыть подборку',
              'shortlist_added_toast' => 'Сохранено в подборку',
              'shortlist_removed_toast' => 'Удалено из подборки',
              'shortlist_error_toast' => 'Не удалось обновить подборку',
              'isbn' => 'ISBN',
              'udc' => 'УДК',
              'author_mark' => 'Авторский знак',
              'available' => 'Мгновенный доступ',
              'permission' => 'Требуется специальный доступ',
              'empty' => 'По выбранным фильтрам материалы не найдены.',
              'empty_hint' => 'Проверьте написание запроса, попробуйте более общие слова или сбросьте фильтры.',
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
              'status_available' => 'Доступна',
              'status_issued' => 'Все экземпляры выданы',
              'status_processing' => 'В обработке',
              'status_repair' => 'В ремонте',
              'status_unknown' => 'Нет данных о наличии',
              'format_print' => 'Печатный',
              'format_electronic' => 'Электронный',
              'format_hybrid' => 'Гибрид',
              'last_copy' => 'Остался последний экземпляр',
              'in_stock' => 'Есть в наличии',
              'absent' => 'Свободных экземпляров нет',
              'popular' => 'Популярное',
              'new_arrival' => 'Новое поступление',
              'access_free' => 'Свободный доступ',
              'access_reading_room' => 'Только читальный зал',
              'access_limited' => 'Ограниченная выдача',
          ],
      ],
      'kk' => [
          'title' => 'Кітаптар каталогы — Digital Library',
          'eyebrow' => 'Кітапхана қоры',
          'heading' => 'Университет кітапханасының каталогы',
          'lead' => 'Қор, жарияланған жылы, тіл және коллекция бойынша ыңғайлы сүзгілермен кітаптарды, электрондық ресурстарды және архивтік басылымдарды қарап шығыңыз.',
          'search_placeholder' => 'Атауы, авторы, ISBN немесе тақырып бойынша іздеу...',
          'advanced' => 'Кеңейтілген',
          'seed_summary' => 'Университеттің академиялық материалдарын шолу.',
          'filters' => 'Сүзгілер',
          'resource_type' => 'Құжат түрі',
          'publication_date' => 'Жарияланған жылы',
          'language' => 'Тіл',
          'fund' => 'Қор',
          'branch' => 'Филиал',
          'category' => 'Тақырып',
          'udc_axis' => 'ӘОЖ',
          'availability' => 'Қолжетімділік',
          'format' => 'Формат',
          'institution' => 'Бөлімше',
          'any_option' => 'Барлығы',
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
          'field_subject_help' => 'Тақырып, кілт сөз немесе аннотация мәтіні',
          'year_from' => 'Жылдан бастап',
          'year_to' => 'Жылға дейін',
          // Тек белгі карталары. Бүйірлік панель бұларды тізім ретінде
          // көрсетпейді — барлық нұсқа $catalogFacets ішінен келеді.
          'resource_type_labels' => [
              'book' => 'Кітап',
              'textbook' => 'Оқулық',
              'study_guide' => 'Оқу құралы',
              'methodical' => 'Әдістемелік құрал',
              'journal' => 'Журнал',
              'periodical' => 'Мерзімді басылым',
              'article' => 'Мақала',
              'dissertation' => 'Диссертация',
              'abstract' => 'Автореферат',
              'publication' => 'Ғылыми жарияланым',
              'ebook' => 'Электрондық кітап',
              'digital_document' => 'Электрондық құжат',
          ],
          'resource_type_groups' => [
              'books' => 'Кітаптар',
              'study' => 'Оқу басылымдары',
              'periodicals' => 'Мерзімді басылымдар',
              'research' => 'Ғылыми жұмыстар',
              'digital' => 'Электрондық',
              'other' => 'Басқа',
          ],
          'category_labels' => [
              'technology' => 'Технологиялар',
              'economics' => 'Экономика',
              'education' => 'Білім беру',
              'library_science' => 'Кітапхана ісі',
              'law' => 'Құқық',
              'psychology' => 'Психология',
              'mathematics' => 'Математика',
              'periodicals' => 'Мерзімді басылымдар',
              'history' => 'Тарих',
              'science' => 'Ғылым',
          ],
          'availability_labels' => [
              'available' => 'Қолжетімді',
              'issued' => 'Берілген',
              'electronic_only' => 'Тек электрондық',
              'processing' => 'Өңделуде',
              'repair' => 'Жөндеуде',
          ],
          'format_labels' => [
              'print' => 'Баспа',
              'electronic' => 'Электрондық',
              'hybrid' => 'Аралас',
          ],
          'language_labels' => [
              'ru' => 'RU',
              'kk' => 'KK',
              'en' => 'EN',
              'other' => 'Басқа',
          ],
          'institution_labels' => [
              'technology_library' => 'Технологиялық кітапхана',
              'economic_library' => 'Экономикалық кітапхана',
              'college_library' => 'Колледж кітапханасы',
              'ktslib' => 'Орталық кітапхана',
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
              'hybrid_badge' => 'Баспа + электрондық',
              'archive' => 'Архив',
              'read' => 'Онлайн оқу',
              'locate' => 'Сөреден табу',
              'request' => 'Қарауды сұрау',
              'cite' => 'Дәйексөз',
              'shortlist_add' => 'Топтамаға',
              'shortlist_saved' => 'Топтамада',
              'shortlist_counter' => 'Топтамада',
              'shortlist_open' => 'Топтаманы ашу',
              'shortlist_added_toast' => 'Топтамаға сақталды',
              'shortlist_removed_toast' => 'Топтамадан жойылды',
              'shortlist_error_toast' => 'Топтаманы жаңарту мүмкін болмады',
              'isbn' => 'ISBN',
              'udc' => 'ӘОЖ',
              'author_mark' => 'Авторлық белгі',
              'available' => 'Бірден қолжетімді',
              'permission' => 'Арнайы рұқсат қажет',
              'empty' => 'Таңдалған сүзгілер бойынша материал табылмады.',
              'empty_hint' => 'Сұраныстың жазылуын тексеріңіз, жалпырақ сөздерді қолданып көріңіз немесе сүзгілерді тазартыңыз.',
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
              'status_available' => 'Қолжетімді',
              'status_issued' => 'Барлық дана берілген',
              'status_processing' => 'Өңдеуде',
              'status_repair' => 'Жөндеуде',
              'status_unknown' => 'Қор туралы дерек жоқ',
              'format_print' => 'Баспа',
              'format_electronic' => 'Электрондық',
              'format_hybrid' => 'Гибрид',
              'last_copy' => 'Соңғы дана қалды',
              'in_stock' => 'Қорда бар',
              'absent' => 'Бос дана жоқ',
              'popular' => 'Танымал',
              'new_arrival' => 'Жаңа түсім',
              'access_free' => 'Еркін қолжетімділік',
              'access_reading_room' => 'Тек оқу залында',
              'access_limited' => 'Шектеулі беру',
          ],
      ],
      'en' => [
          'title' => 'Catalog — Digital Library',
          'eyebrow' => 'Library holdings',
          'heading' => 'University Library Catalog',
          'lead' => 'Browse books, electronic resources, and archival materials with convenient filters by collection, year, language, and holdings.',
          'search_placeholder' => 'Search by title, author, ISBN, or subject...',
          'advanced' => 'Advanced',
          'seed_summary' => 'Viewing scholarly items across university collections.',
          'filters' => 'Filters',
          'resource_type' => 'Document type',
          'publication_date' => 'Publication Date',
          'language' => 'Language',
          'fund' => 'Fund',
          'branch' => 'Branch',
          'category' => 'Subject area',
          'udc_axis' => 'UDC',
          'availability' => 'Availability',
          'format' => 'Format',
          'institution' => 'Division',
          'any_option' => 'Any',
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
          'field_subject_help' => 'Subject, keyword, or abstract text',
          'year_from' => 'Year from',
          'year_to' => 'Year to',
          // Label maps only. The sidebar never renders these as an option list —
          // every option comes from $catalogFacets. The maps stay complete so a
          // value that appears after the MARC import never shows as a raw slug.
          'resource_type_labels' => [
              'book' => 'Book',
              'textbook' => 'Textbook',
              'study_guide' => 'Study guide',
              'methodical' => 'Methodical guide',
              'journal' => 'Journal',
              'periodical' => 'Periodical',
              'article' => 'Article',
              'dissertation' => 'Dissertation',
              'abstract' => 'Abstract',
              'publication' => 'Publication',
              'ebook' => 'E-book',
              'digital_document' => 'Digital document',
          ],
          'resource_type_groups' => [
              'books' => 'Books',
              'study' => 'Study materials',
              'periodicals' => 'Periodicals',
              'research' => 'Research output',
              'digital' => 'Digital',
              'other' => 'Other',
          ],
          'category_labels' => [
              'technology' => 'Technology',
              'economics' => 'Economics',
              'education' => 'Education',
              'library_science' => 'Library science',
              'law' => 'Law',
              'psychology' => 'Psychology',
              'mathematics' => 'Mathematics',
              'periodicals' => 'Periodicals',
              'history' => 'History',
              'science' => 'Science',
          ],
          'availability_labels' => [
              'available' => 'On the shelf',
              'issued' => 'On loan',
              'electronic_only' => 'Electronic only',
              'processing' => 'In processing',
              'repair' => 'In repair',
          ],
          'format_labels' => [
              'print' => 'Print',
              'electronic' => 'Electronic',
              'hybrid' => 'Hybrid',
          ],
          'language_labels' => [
              'ru' => 'RU',
              'kk' => 'KK',
              'en' => 'EN',
              'other' => 'Other',
          ],
          'institution_labels' => [
              'technology_library' => 'Technology Library',
              'economic_library' => 'Economics Library',
              'college_library' => 'College Library',
              'ktslib' => 'Central Library',
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
              'hybrid_badge' => 'Print + digital',
              'archive' => 'Archive',
              'read' => 'Read Online',
              'locate' => 'Locate on Shelf',
              'request' => 'Request Viewing',
              'cite' => 'Cite Item',
              'shortlist_add' => 'Add to shortlist',
              'shortlist_saved' => 'In shortlist',
              'shortlist_counter' => 'In shortlist',
              'shortlist_open' => 'Open shortlist',
              'shortlist_added_toast' => 'Saved to shortlist',
              'shortlist_removed_toast' => 'Removed from shortlist',
              'shortlist_error_toast' => 'Unable to update shortlist',
              'isbn' => 'ISBN',
              'udc' => 'UDC',
              'author_mark' => 'Author mark',
              'available' => 'Immediate Access',
              'permission' => 'Special Permission Required',
              'empty' => 'No items match the selected filters.',
              'empty_hint' => 'Check the spelling, try broader terms, or clear the filters.',
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
              'status_available' => 'Available',
              'status_issued' => 'All copies on loan',
              'status_processing' => 'In processing',
              'status_repair' => 'Under repair',
              'status_unknown' => 'Holdings data unavailable',
              'format_print' => 'Print',
              'format_electronic' => 'Electronic',
              'format_hybrid' => 'Hybrid',
              'last_copy' => 'Last available copy',
              'in_stock' => 'In stock',
              'absent' => 'No available copies',
              'popular' => 'Popular',
              'new_arrival' => 'New arrival',
              'access_free' => 'Open access',
              'access_reading_room' => 'Reading room only',
              'access_limited' => 'Limited circulation',
          ],
      ],
  ][$lang];

  $langSuffix = $lang === 'kk' ? '' : ('?lang=' . $lang);

  // ---------------------------------------------------------------------
  // Live facet axes. Everything the sidebar offers is derived from this
  // array (the same payload as GET /api/v1/catalog-facets), so the option
  // lists and their counts are always the real collection, never a guess.
  // ---------------------------------------------------------------------
  $facets = is_array($catalogFacets ?? null) ? $catalogFacets : [];
  $facetRows = static function (string $key) use ($facets): array {
      $rows = is_array($facets[$key] ?? null) ? $facets[$key] : [];

      return array_values(array_filter($rows, static fn ($row) => is_array($row) && ($row['value'] ?? '') !== ''));
  };

  $resourceTypeFacet = $facetRows('resource_types');
  $languageFacet = $facetRows('languages');
  $categoryFacet = $facetRows('categories');
  $fundFacet = $facetRows('funds');
  $branchFacet = $facetRows('branches');
  $udcFacet = $facetRows('udc');
  $availabilityFacet = $facetRows('availability');
  $formatFacet = $facetRows('formats');
  $facetTotal = (int) ($facets['total'] ?? 0);

  // Multi-select axes arrive as a comma separated list, exactly as the API
  // expects them back.
  $splitList = static function (string $value): array {
      $parts = array_map('trim', explode(',', $value));

      return array_values(array_unique(array_filter($parts, static fn (string $part): bool => $part !== '')));
  };

  $resourceTypeSelected = $splitList((string) request()->query('resource_type', ''));
  $fundSelected = $splitList((string) request()->query('fund', ''));
  $branchSelected = $splitList((string) request()->query('branch', ''));
  $categorySelected = $splitList((string) request()->query('category', ''));
  $availabilitySelected = trim((string) request()->query('availability', ''));
  $formatSelected = trim((string) request()->query('format', ''));

  // Visual grouping for the document-type axis. Values are bucketed, but the
  // buckets are only a heading — every present type stays individually
  // selectable, and anything unknown falls through to the "other" bucket.
  $resourceTypeGroupMap = [
      'book' => 'books',
      'textbook' => 'study',
      'study_guide' => 'study',
      'methodical' => 'study',
      'journal' => 'periodicals',
      'periodical' => 'periodicals',
      'article' => 'periodicals',
      'dissertation' => 'research',
      'abstract' => 'research',
      'publication' => 'research',
      'ebook' => 'digital',
      'digital_document' => 'digital',
  ];
  $resourceTypeGrouped = [];
  foreach (array_keys($copy['resource_type_groups']) as $groupKey) {
      $resourceTypeGrouped[$groupKey] = [];
  }
  foreach ($resourceTypeFacet as $row) {
      $groupKey = $resourceTypeGroupMap[$row['value']] ?? 'other';
      $resourceTypeGrouped[$groupKey][] = $row;
  }
  $resourceTypeGrouped = array_filter($resourceTypeGrouped, static fn (array $rows): bool => $rows !== []);

  // Year bounds are the real min/max publication year in the collection.
  $facetYears = is_array($facets['years'] ?? null) ? $facets['years'] : [];
  $yearMin = (int) ($facetYears['min'] ?? $catalogYearBounds['min'] ?? 1950);
  $yearMax = (int) ($facetYears['max'] ?? $catalogYearBounds['max'] ?? (int) date('Y'));
  if ($yearMin <= 0) {
      $yearMin = (int) ($catalogYearBounds['min'] ?? 1950);
  }
  if ($yearMax < $yearMin) {
      $yearMax = $yearMin;
  }
  $yearFrom = (string) max($yearMin, min((int) ($yearFrom !== '' ? $yearFrom : $yearMin), $yearMax));
  $yearTo = (string) max((int) $yearFrom, min((int) ($yearTo !== '' ? $yearTo : $yearMax), $yearMax));

  $pageSize = max(1, (int) ($catalogPageSize ?? 12));
  $initialCatalog = is_array($catalogBootstrap ?? null) ? $catalogBootstrap : ['data' => [], 'meta' => []];
  $initialResults = is_array($initialCatalog['data'] ?? null) ? $initialCatalog['data'] : [];
  $initialMeta = is_array($initialCatalog['meta'] ?? null) ? $initialCatalog['meta'] : [];
  $initialTotal = (int) ($initialMeta['total'] ?? count($initialResults));
  $initialPage = max((int) ($initialMeta['page'] ?? 1), 1);
  $initialPerPage = max((int) ($initialMeta['per_page'] ?? $pageSize), 1);
  $initialTotalPages = max((int) ($initialMeta['total_pages'] ?? $initialMeta['totalPages'] ?? (int) ceil(max($initialTotal, 1) / $initialPerPage)), 1);
  $initialFrom = ($initialTotal > 0 && $initialResults !== []) ? (($initialPage - 1) * $initialPerPage) + 1 : 0;
  $initialTo = $initialFrom > 0 ? min($initialFrom + count($initialResults) - 1, $initialTotal) : 0;
  // A hand-typed ?page=99 must not produce a "previous → 98" link.
  $paginationPage = min($initialPage, $initialTotalPages);

  // Numbered pages are real links: every one carries the whole active filter
  // set so a deep link survives a reload and a middle-click.
  $pageHref = function (int $page) use ($lang): string {
      $query = request()->query();
      unset($query['page']);
      if ($page > 1) {
          $query['page'] = $page;
      }
      if ($lang !== 'kk') {
          $query['lang'] = $lang;
      }
      $query = array_filter($query, static fn ($value) => $value !== null && $value !== '');

      return '/catalog' . ($query ? ('?' . http_build_query($query)) : '');
  };

  // first page, last page, current ±2, with ellipses between the runs.
  $pageWindow = static function (int $current, int $total): array {
      $wanted = [1, $total];
      for ($page = $current - 2; $page <= $current + 2; $page++) {
          if ($page >= 1 && $page <= $total) {
              $wanted[] = $page;
          }
      }
      $wanted = array_values(array_unique(array_filter($wanted, static fn (int $page): bool => $page >= 1 && $page <= $total)));
      sort($wanted);

      $window = [];
      $previous = 0;
      foreach ($wanted as $page) {
          if ($previous > 0 && $page - $previous > 1) {
              $window[] = '…';
          }
          $window[] = $page;
          $previous = $page;
      }

      return $window;
  };
  $initialQueryLabel = $q !== '' ? $q : strtoupper($language === '' ? 'all' : $language);
  $hasAdvancedFilters = $titleFilter !== '' || $authorFilter !== '' || $publisherFilter !== '' || $isbnFilter !== '' || $subjectFilter !== '';
  $formatLocationLabel = static function (array $location) use ($copy): string {
      $serviceCode = strtolower(trim((string) data_get($location, 'servicePoint.code', '')));
      $serviceName = trim((string) data_get($location, 'servicePoint.name', ''));
      $unitName = trim((string) data_get($location, 'institutionUnit.name', ''));
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

      if ($serviceName !== '' && ! str_starts_with(strtolower($serviceName), 'app.')) {
          return $serviceName;
      }

      return $unitName !== '' ? $unitName : $copy['ui']['no_location'];
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
    background:
      radial-gradient(circle at 18% 20%, rgba(159, 198, 255, 0.26), transparent 28%),
      linear-gradient(135deg, #163450 0%, #0c2138 100%);
  }
  .catalog-export .catalog-card-book--wine .catalog-card-book__cover {
    background:
      linear-gradient(145deg, rgba(255,255,255,0.08), transparent 34%),
      linear-gradient(135deg, #6f1d2d 0%, #441019 100%);
  }
  .catalog-export .catalog-card-book--forest .catalog-card-book__cover {
    background:
      radial-gradient(circle at 82% 18%, rgba(145, 239, 198, 0.22), transparent 26%),
      linear-gradient(135deg, #1e5a46 0%, #12372a 100%);
  }
  .catalog-export .catalog-card-book--wood .catalog-card-book__cover {
    background:
      linear-gradient(135deg, rgba(255,255,255,0.1), transparent 42%),
      repeating-linear-gradient(90deg, rgba(255, 228, 196, 0.08) 0 10px, transparent 10px 18px),
      linear-gradient(135deg, #6c4428 0%, #3d2416 100%);
  }
  .catalog-export .catalog-card-book--plum .catalog-card-book__cover {
    background:
      radial-gradient(circle at 20% 78%, rgba(255, 220, 245, 0.18), transparent 28%),
      linear-gradient(135deg, #54406d 0%, #2e2240 100%);
  }
  .catalog-export .catalog-card-book--navy .catalog-card-book__eyebrow,
  .catalog-export .catalog-card-book--forest .catalog-card-book__eyebrow {
    color: rgba(225, 245, 255, 0.9);
    background: rgba(255,255,255,0.1);
  }
  .catalog-export .catalog-card-book--wine .catalog-card-book__eyebrow,
  .catalog-export .catalog-card-book--plum .catalog-card-book__eyebrow {
    color: rgba(255, 232, 236, 0.92);
    background: rgba(255,255,255,0.12);
  }
  .catalog-export .catalog-card-book--wood .catalog-card-book__eyebrow {
    color: rgba(255, 241, 226, 0.92);
    background: rgba(255,255,255,0.14);
  }
  .catalog-export .catalog-card-book--navy .catalog-card-book__title,
  .catalog-export .catalog-card-book--forest .catalog-card-book__title {
    color: #f4f9ff;
  }
  .catalog-export .catalog-card-book--wine .catalog-card-book__title,
  .catalog-export .catalog-card-book--plum .catalog-card-book__title {
    color: #ffe7ec;
  }
  .catalog-export .catalog-card-book--wood .catalog-card-book__title {
    color: #fff1d8;
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
  .catalog-export .catalog-shortlist-toast {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 80;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    max-width: min(24rem, calc(100vw - 2rem));
    border: 1px solid rgba(0, 106, 106, 0.18);
    background: #001f3f;
    color: #fff;
    padding: 0.8rem 1rem;
    box-shadow: 0 24px 60px rgba(0, 31, 63, 0.26);
    opacity: 0;
    pointer-events: none;
    transform: translateY(0.75rem);
    transition: opacity 0.2s ease, transform 0.2s ease;
  }
  .catalog-export .catalog-shortlist-toast.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .catalog-export .catalog-shortlist-toast.is-error {
    background: #7f1d1d;
  }
  /* ---- facet sidebar ---------------------------------------------- */
  .catalog-export .catalog-facet-group {
    margin: 14px 0 8px;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--portal-muted, #6b6257);
  }
  .catalog-export .catalog-facet-group:first-of-type {
    margin-top: 0;
  }
  .catalog-export .catalog-facet-list {
    display: grid;
    gap: 2px;
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .catalog-export .catalog-facet {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.3rem 0;
    cursor: pointer;
    font-size: 0.82rem;
    line-height: 1.35;
  }
  .catalog-export .catalog-facet__input {
    flex: 0 0 auto;
    width: 1rem;
    height: 1rem;
    margin: 0;
    accent-color: rgb(13 148 136);
    cursor: pointer;
  }
  .catalog-export .catalog-facet__label {
    flex: 1 1 auto;
    min-width: 0;
    overflow-wrap: anywhere;
  }
  .catalog-export .catalog-facet__count {
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--portal-muted, #6b6257);
  }
  .catalog-export .catalog-facet:hover:not(.is-disabled) .catalog-facet__label {
    color: rgb(13 148 136);
  }
  .catalog-export .catalog-facet.is-disabled {
    cursor: not-allowed;
    opacity: 0.42;
  }
  .catalog-export .catalog-facet.is-disabled .catalog-facet__input {
    cursor: not-allowed;
  }
  .catalog-export .catalog-facet-empty {
    margin: 0;
    font-size: 0.78rem;
    color: var(--portal-muted, #6b6257);
  }
  .catalog-export .catalog-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--portal-line, rgba(0, 0, 0, 0.14));
    background: #fff;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
  }
  .catalog-export .catalog-chip.is-active {
    border-color: #001f3f;
    background: #001f3f;
    color: #fff;
  }
  .catalog-export .catalog-chip__count {
    font-size: 0.66rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    opacity: 0.7;
  }
  .catalog-export .catalog-chip[disabled] {
    cursor: not-allowed;
    opacity: 0.42;
  }
  /* ---- pagination -------------------------------------------------- */
  .catalog-export .catalog-page {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: inherit;
    text-decoration: none;
  }
  .catalog-export .catalog-page.is-current {
    background: #001f3f;
    border-color: #001f3f !important;
    color: #fff;
    font-weight: 800;
  }
  .catalog-export .catalog-page.is-disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }
  .catalog-export .catalog-page.is-gap {
    border-color: transparent !important;
    cursor: default;
  }
  .catalog-export #catalog-pagination:empty {
    display: none;
  }
  .catalog-export .catalog-chip-remove {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.7rem;
    border: 1px solid var(--portal-line, rgba(0, 0, 0, 0.14));
    background: #fff;
    font-size: 0.72rem;
    line-height: 1.2;
    cursor: pointer;
  }
  .catalog-export .catalog-chip-remove:hover {
    border-color: rgb(13 148 136);
  }
  .catalog-export .catalog-chip-remove__key {
    color: var(--portal-muted, #6b6257);
  }
  .catalog-export .catalog-chip-remove__value {
    font-weight: 700;
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
<div class="catalog-export public-v2 catalog-v2">
  <section class="public-v2__hero catalog-export__hero">
    <div class="public-v2__inset public-v2__hero-grid">
    <div>
      @isset($copy['eyebrow'])
        <p class="public-v2__kicker">{{ $copy['eyebrow'] }}</p>
      @endisset
      <h1 class="public-v2__title">{{ $copy['heading'] }}</h1>
      @isset($copy['lead'])
        <p class="public-v2__lead">{{ $copy['lead'] }}</p>
      @endisset
    </div>
    <aside class="public-v2__hero-note">
      <strong>{{ number_format($initialTotal, 0, '.', ' ') }}</strong>
      <span>{{ $copy['results_for'] }} {{ $initialQueryLabel }}</span>
    </aside>
    </div>
  </section>

  <div class="public-v2__body">
  <div class="public-v2__inset">
  <div class="catalog-v2__search-block">
    <div class="public-v2__search">
      <span class="material-symbols-outlined" aria-hidden="true">search</span>
      <input id="catalog-search-input" value="{{ $q }}" placeholder="{{ $copy['search_placeholder'] }}" type="search" />
      <button type="button" onclick="toggleAdvancedSearch()">{{ $copy['advanced'] }}</button>
    </div>
    <p id="catalog-summary-text" class="mt-4 text-on-surface-variant text-sm font-label italic">{{ $copy['seed_summary'] }}</p>

    <div id="advanced-search-panel" class="advanced-search-panel mt-6 rounded-2xl border border-outline-variant/20 bg-white/90 p-4 md:p-5" @if(! $hasAdvancedFilters) hidden @endif>
      <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant">{{ $copy['advanced_search'] }}</h2>
        <button type="button" onclick="resetAdvancedFields()" class="text-xs font-semibold text-secondary">{{ $copy['advanced_reset'] }}</button>
      </div>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_title'] }}</span><input id="advanced-title-input" value="{{ $titleFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_title'] }}" /></label>
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_author'] }}</span><input id="advanced-author-input" value="{{ $authorFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_author'] }}" /></label>
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_publisher'] }}</span><input id="advanced-publisher-input" value="{{ $publisherFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="{{ $copy['field_publisher'] }}" /></label>
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_isbn'] }}</span><input id="advanced-isbn-input" value="{{ $isbnFilter }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="text" placeholder="978..." /></label>
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['field_subject'] }}</span><input id="advanced-subject-input" value="{{ $subjectFilter }}" class="advanced-field w-full px-3 py-2 rounded-lg border border-outline-variant/20 text-sm" type="text" placeholder="{{ $copy['field_subject_help'] }}" /></label>
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['year_from'] }}</span><input id="advanced-year-from-input" value="{{ $yearFrom }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="number" min="{{ $yearMin }}" max="{{ $yearMax }}" /></label>
        <label class="block"><span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['year_to'] }}</span><input id="advanced-year-to-input" value="{{ $yearTo }}" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm" type="number" min="{{ $yearMin }}" max="{{ $yearMax }}" /></label>
        <label class="block">
          <span class="block text-xs font-semibold text-on-surface-variant mb-1">{{ $copy['resource_type'] }}</span>
          <select id="advanced-resource-type-input" class="w-full px-3 py-2 rounded-lg border border-outline-variant/30 bg-white text-sm">
            <option value="">{{ $copy['any_option'] }}</option>
            @foreach($resourceTypeFacet as $row)
              <option value="{{ $row['value'] }}" @selected(in_array($row['value'], $resourceTypeSelected, true))>{{ $copy['resource_type_labels'][$row['value']] ?? $row['value'] }}</option>
            @endforeach
          </select>
        </label>
      </div>
      <div class="mt-4 flex justify-end">
        <button type="button" onclick="applyAdvancedSearch()" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold">{{ $copy['advanced_apply'] }}</button>
      </div>
    </div>
  </div>
  <button id="mobile-filter-toggle" type="button" onclick="toggleFilters()" class="md:hidden mb-6 inline-flex items-center gap-2 px-4 py-2 border border-outline-variant/30 rounded-lg text-sm font-semibold text-primary bg-white">
    <span class="material-symbols-outlined text-base">tune</span>
    <span>{{ $copy['filters'] }}</span>
    <span id="filter-count-badge" class="text-secondary">0</span>
  </button>

  <div class="public-v2__workspace">
    {{-- The slot is the grid item and stretches to the results column, so the
         sticky panel inside it can never outrun the last card. --}}
    <div class="public-v2__sidebar-slot">
    <aside id="catalog-filters" class="public-v2__sidebar space-y-8">
      <div class="public-v2__sidebar-head">
        <h2>{{ $copy['filters'] }}</h2>
        <p>{{ $copy['clear_filters'] }}</p>
      </div>
      <div data-facet-axis="resource_type">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['resource_type'] }}</h3>
        @forelse($resourceTypeGrouped as $groupKey => $groupRows)
          <p class="catalog-facet-group">{{ $copy['resource_type_groups'][$groupKey] ?? $groupKey }}</p>
          <ul class="catalog-facet-list">
            @foreach($groupRows as $row)
              <li>
                <label class="catalog-facet{{ (int) $row['count'] === 0 ? ' is-disabled' : '' }}">
                  <input type="checkbox" class="catalog-facet__input" data-facet="resource_type" value="{{ $row['value'] }}" @checked(in_array($row['value'], $resourceTypeSelected, true)) @disabled((int) $row['count'] === 0) />
                  <span class="catalog-facet__label">{{ $copy['resource_type_labels'][$row['value']] ?? $row['value'] }}</span>
                  <span class="catalog-facet__count">{{ $row['count'] }}</span>
                </label>
              </li>
            @endforeach
          </ul>
        @empty
          <p class="catalog-facet-empty">{{ $copy['ui']['empty'] }}</p>
        @endforelse
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

      <div data-facet-axis="language">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['language'] }}</h3>
        <div id="language-chips" class="flex flex-wrap gap-2">
          <button type="button" data-lang="all" class="catalog-chip{{ $language === 'all' ? ' is-active' : '' }}">
            <span class="catalog-chip__label">{{ $copy['any_option'] }}</span>
            <span class="catalog-chip__count">{{ $facetTotal }}</span>
          </button>
          @foreach($languageFacet as $row)
            <button type="button" data-lang="{{ $row['value'] }}" class="catalog-chip{{ $language === $row['value'] ? ' is-active' : '' }}" @disabled((int) $row['count'] === 0)>
              <span class="catalog-chip__label">{{ $copy['language_labels'][$row['value']] ?? strtoupper($row['value']) }}</span>
              <span class="catalog-chip__count">{{ $row['count'] }}</span>
            </button>
          @endforeach
        </div>
      </div>

      <div data-facet-axis="fund">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['fund'] }}</h3>
        <ul class="catalog-facet-list">
          @forelse($fundFacet as $row)
            <li>
              <label class="catalog-facet{{ (int) $row['count'] === 0 ? ' is-disabled' : '' }}">
                <input type="checkbox" class="catalog-facet__input" data-facet="fund" value="{{ $row['value'] }}" @checked(in_array($row['value'], $fundSelected, true)) @disabled((int) $row['count'] === 0) />
                <span class="catalog-facet__label">{{ $row['label'] ?? $row['value'] }}</span>
                <span class="catalog-facet__count">{{ $row['count'] }}</span>
              </label>
            </li>
          @empty
            <li><p class="catalog-facet-empty">{{ $copy['ui']['empty'] }}</p></li>
          @endforelse
        </ul>
      </div>

      <div data-facet-axis="branch">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['branch'] }}</h3>
        <ul class="catalog-facet-list">
          @forelse($branchFacet as $row)
            <li>
              <label class="catalog-facet{{ (int) $row['count'] === 0 ? ' is-disabled' : '' }}">
                <input type="checkbox" class="catalog-facet__input" data-facet="branch" value="{{ $row['value'] }}" @checked(in_array($row['value'], $branchSelected, true)) @disabled((int) $row['count'] === 0) />
                <span class="catalog-facet__label">{{ $row['label'] ?? $row['value'] }}</span>
                <span class="catalog-facet__count">{{ $row['count'] }}</span>
              </label>
            </li>
          @empty
            <li><p class="catalog-facet-empty">{{ $copy['ui']['empty'] }}</p></li>
          @endforelse
        </ul>
      </div>

      <div data-facet-axis="category">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['category'] }}</h3>
        <ul class="catalog-facet-list">
          @forelse($categoryFacet as $row)
            <li>
              <label class="catalog-facet{{ (int) $row['count'] === 0 ? ' is-disabled' : '' }}">
                <input type="checkbox" class="catalog-facet__input" data-facet="category" value="{{ $row['value'] }}" @checked(in_array($row['value'], $categorySelected, true)) @disabled((int) $row['count'] === 0) />
                <span class="catalog-facet__label">{{ $copy['category_labels'][$row['value']] ?? $row['value'] }}</span>
                <span class="catalog-facet__count">{{ $row['count'] }}</span>
              </label>
            </li>
          @empty
            <li><p class="catalog-facet-empty">{{ $copy['ui']['empty'] }}</p></li>
          @endforelse
        </ul>
      </div>

      <div data-facet-axis="availability">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['availability'] }}</h3>
        <ul class="catalog-facet-list">
          <li>
            <label class="catalog-facet">
              <input type="radio" name="catalog-availability" class="catalog-facet__input" data-facet-single="availability" value="" @checked($availabilitySelected === '') />
              <span class="catalog-facet__label">{{ $copy['any_option'] }}</span>
            </label>
          </li>
          @foreach($availabilityFacet as $row)
            <li>
              <label class="catalog-facet{{ (int) $row['count'] === 0 ? ' is-disabled' : '' }}">
                <input type="radio" name="catalog-availability" class="catalog-facet__input" data-facet-single="availability" value="{{ $row['value'] }}" @checked($availabilitySelected === (string) $row['value']) @disabled((int) $row['count'] === 0) />
                <span class="catalog-facet__label">{{ $copy['availability_labels'][$row['value']] ?? $row['value'] }}</span>
                <span class="catalog-facet__count">{{ $row['count'] }}</span>
              </label>
            </li>
          @endforeach
        </ul>
      </div>

      <div data-facet-axis="format">
        <h3 class="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-6">{{ $copy['format'] }}</h3>
        <ul class="catalog-facet-list">
          <li>
            <label class="catalog-facet">
              <input type="radio" name="catalog-format" class="catalog-facet__input" data-facet-single="format" value="" @checked($formatSelected === '') />
              <span class="catalog-facet__label">{{ $copy['any_option'] }}</span>
            </label>
          </li>
          @foreach($formatFacet as $row)
            <li>
              <label class="catalog-facet{{ (int) $row['count'] === 0 ? ' is-disabled' : '' }}">
                <input type="radio" name="catalog-format" class="catalog-facet__input" data-facet-single="format" value="{{ $row['value'] }}" @checked($formatSelected === (string) $row['value']) @disabled((int) $row['count'] === 0) />
                <span class="catalog-facet__label">{{ $copy['format_labels'][$row['value']] ?? $row['value'] }}</span>
                <span class="catalog-facet__count">{{ $row['count'] }}</span>
              </label>
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
    </div>

    <div>
      <div class="public-v2__toolbar">
        <div id="catalog-results-count" class="text-on-surface-variant text-sm font-label">{{ $copy['showing'] }} <span class="text-on-surface font-bold">{{ $initialFrom }}-{{ $initialTo }}</span> {{ $copy['of'] }} <span class="font-bold">{{ $initialTotal }}</span> {{ $copy['results_for'] }} <span class="font-medium">“{{ $initialQueryLabel }}”</span></div>
        <div class="public-v2__toolbar-actions">
          <button type="button" class="public-v2__view is-active" data-catalog-view="grid" aria-label="Grid view"><span class="material-symbols-outlined">grid_view</span></button>
          <button type="button" class="public-v2__view" data-catalog-view="list" aria-label="List view"><span class="material-symbols-outlined">view_list</span></button>
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

      <div id="catalog-results-list">
        @forelse($initialResults as $index => $record)
          @php
            $title = trim((string) ($record['title']['display'] ?? '')) ?: 'Untitled';
            $author = trim((string) ($record['primaryAuthor'] ?? '')) ?: $copy['ui']['author_unknown'];
            $year = $record['publicationYear'] ?? '—';
            $publisher = trim((string) ($record['publisher']['name'] ?? ''));
            $isbn = trim((string) ($record['isbn']['raw'] ?? '')) ?: '—';
            $udc = trim((string) ($record['udc']['display'] ?? $record['udc']['description'] ?? $record['udc']['raw'] ?? '')) ?: '—';
            $authorMark = trim((string) ($record['authorMark'] ?? '')) ?: '—';
            $resourceType = trim((string) ($record['resourceType'] ?? 'book'));
            $resourceTypeLabel = $copy['resource_type_labels'][$resourceType] ?? $resourceType;
            $annotationRaw = trim((string) ($record['annotation'] ?? ''));
            $subtitleRaw = trim((string) ($record['title']['subtitle'] ?? ''));
            $subjectLabels = [];
            foreach (($record['classification'] ?? []) as $subject) {
                $label = trim((string) ($subject['label'] ?? ''));
                if ($label !== '') {
                    $subjectLabels[] = $label;
                }
            }
            $description = $annotationRaw !== '' && ! str_starts_with($annotationRaw, 'app.')
                ? $annotationRaw
                : ($subtitleRaw !== '' && ! str_starts_with($subtitleRaw, 'app.')
                    ? $subtitleRaw
                    : (! empty($subjectLabels) ? implode(' · ', array_slice($subjectLabels, 0, 3)) : $copy['ui']['description_placeholder']));
            $availableCopies = (int) ($record['copies']['available'] ?? 0);
            $totalCopies = (int) ($record['copies']['total'] ?? 0);
            // Mirrors deriveMaterialKind() in the client renderer. The declared
            // format wins over copy counts: a record with a full text attached
            // must not advertise itself as print-only just because it also sits
            // on a shelf.
            $declaredFormat = (string) ($record['indicators']['format'] ?? '');
            $kind = match ($declaredFormat) {
                'electronic' => 'electronic',
                'hybrid' => 'hybrid',
                default => $totalCopies > 0 ? ($availableCopies > 0 ? 'physical' : 'archive') : 'electronic',
            };
            $badgeStyle = $kind === 'physical' ? 'bg-surface-container-highest text-on-surface-variant' : 'bg-secondary-container text-on-secondary-container';
            $badgeLabel = match ($kind) {
                'physical' => $copy['ui']['physical'],
                'archive' => $copy['ui']['archive'],
                'hybrid' => $copy['ui']['hybrid_badge'],
                default => $copy['ui']['electronic'],
            };
            $primaryLabel = match ($kind) {
                'physical' => $copy['ui']['locate'],
                'archive' => $copy['ui']['request'],
                default => $copy['ui']['read'],
            };
            $icon = match ($kind) {
                'physical' => 'library_books',
                'archive' => 'history_edu',
                'hybrid' => 'auto_stories',
                default => 'visibility',
            };
            $primaryLocation = is_array($record['availability']['locations'][0] ?? null) ? $record['availability']['locations'][0] : [];
            $locationLabel = $formatLocationLabel($primaryLocation);
            $availabilityByPoint = [];
            foreach (($record['availability']['locations'] ?? []) as $location) {
                if (! is_array($location)) {
                    continue;
                }
                $pointLabel = $formatLocationLabel($location);
                $availabilityByPoint[$pointLabel] = ($availabilityByPoint[$pointLabel] ?? 0)
                    + (int) data_get($location, 'copies.available', 0);
            }
            $languageLabel = strtoupper(trim((string) ($record['language']['code'] ?? '')));
            $indicators = is_array($record['indicators'] ?? null) ? $record['indicators'] : [];
            $availabilityIndicator = (string) ($indicators['availability'] ?? 'no_holdings');
            $statusStyle = match ($availabilityIndicator) {
                'available' => 'text-secondary',
                'issued', 'under_repair' => 'text-error',
                default => 'text-on-surface-variant',
            };
            $status = match ($availabilityIndicator) {
                'available' => $copy['ui']['status_available'],
                'issued' => $copy['ui']['status_issued'],
                'in_processing' => $copy['ui']['status_processing'],
                'under_repair' => $copy['ui']['status_repair'],
                default => $copy['ui']['status_unknown'],
            };
            $indicatorLabels = [
                $copy['ui']['format_' . ($indicators['format'] ?? 'print')] ?? $copy['ui']['format_print'],
                $copy['ui'][$indicators['copySupply'] ?? 'absent'] ?? $copy['ui']['absent'],
                $copy['ui']['access_' . ($indicators['accessRestriction'] ?? 'free')] ?? $copy['ui']['access_free'],
            ];
            if (($indicators['popular'] ?? false) === true) {
                $indicatorLabels[] = $copy['ui']['popular'];
            }
            if (($indicators['newArrival'] ?? false) === true) {
                $indicatorLabels[] = $copy['ui']['new_arrival'];
            }
            $detailIdentifier = $isbn !== '—' ? $isbn : (string) ($record['id'] ?? '');
            $detailHref = $withLang('/book/' . rawurlencode($detailIdentifier));
            $metaParts = array_values(array_filter([$author, $year, $publisher], static fn ($value) => (string) $value !== '' && (string) $value !== '—'));

            // Mirrors buildCitation() in the client renderer so a card cites
            // identically before and after the first XHR.
            $citeClean = static function ($value): string {
                $text = trim((string) $value);

                return ($text === '' || $text === '—') ? '' : $text;
            };
            // Raw author, not $author: that one already fell back to the
            // "author not specified" label, which must not enter a reference.
            $citeSegments = [];
            if ($citeClean($record['primaryAuthor'] ?? '') !== '') {
                $citeSegments[] = $citeClean($record['primaryAuthor']);
            }
            $citeSegments[] = $citeClean($title) !== '' ? $citeClean($title) : 'Без названия';
            $citeImprint = implode(', ', array_filter([$citeClean($publisher), $citeClean($year)]));
            if ($citeImprint !== '') {
                $citeSegments[] = '— '.$citeImprint;
            }
            if ($citeClean($isbn) !== '') {
                $citeSegments[] = '— ISBN '.$citeClean($isbn);
            }
            $citation = implode('. ', $citeSegments).'.';
          @endphp
          <article class="flex flex-col sm:flex-row gap-8 group catalog-item" data-catalog-card>
            @php
              $coverTones = ['catalog-card-book--navy', 'catalog-card-book--wine', 'catalog-card-book--forest', 'catalog-card-book--wood', 'catalog-card-book--plum'];
              $coverTone = $coverTones[$index % count($coverTones)];
              $coverUrl = trim((string) ($record['coverPath'] ?? $record['coverUrl'] ?? data_get($record, 'cover.medium') ?? data_get($record, 'cover.small') ?? ''));
              $coverDescription = trim((string) $description) !== '' ? $description : $copy['ui']['description_placeholder'];
              $coverCode = $authorMark !== '—'
                ? $copy['ui']['author_mark'].': '.$authorMark
                : ($udc !== '—'
                    ? $copy['ui']['udc'].': '.$udc
                    : ($isbn !== '—' ? $copy['ui']['isbn'].': '.$isbn : '—'));
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
                  <span class="inline-block px-2 py-0.5 bg-surface-container-high text-primary text-[10px] font-bold uppercase tracking-wider rounded mb-2">{{ $resourceTypeLabel }}</span>
              <a href="{{ $detailHref }}" class="block text-2xl font-newsreader font-semibold text-primary group-hover:text-secondary transition-colors cursor-pointer">{{ $title }}</a>
                  <p data-catalog-description class="mt-3 text-on-surface-variant text-sm leading-relaxed">{{ $description }}</p>
                  <p class="mt-3 text-on-surface-variant font-medium">{{ implode(' · ', $metaParts) }}</p>
                </div>
                <button
                  type="button"
                  class="catalog-shortlist-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center border border-secondary bg-secondary text-white transition-colors hover:bg-secondary-container hover:text-secondary"
                  data-catalog-shortlist-button
                  onclick="toggleCatalogShortlist(this); return false;"
                  data-shortlist-identifier="{{ e($isbn !== '—' ? $isbn : $detailHref) }}"
                  data-shortlist-title="{{ e($title) }}"
                  data-shortlist-author="{{ e($author) }}"
                  data-shortlist-publisher="{{ e($publisher) }}"
                  data-shortlist-year="{{ e($year !== '—' ? $year : '') }}"
                  data-shortlist-language="{{ e($languageLabel !== '' ? $languageLabel : '') }}"
                  data-shortlist-isbn="{{ e($isbn !== '—' ? $isbn : '') }}"
                  data-shortlist-available="{{ $availableCopies }}"
                  data-shortlist-total="{{ $totalCopies }}"
                  data-shortlist-url="{{ e($detailHref) }}"
                  data-shortlist-provider="{{ e($publisher) }}"
                  data-shortlist-type="{{ $kind === 'electronic' ? 'external_resource' : 'book' }}"
                  aria-label="{{ e($copy['ui']['shortlist_add']) }}"
                  aria-pressed="false"
                  title="{{ e($copy['ui']['shortlist_add']) }}"
                >
                  <span class="material-symbols-outlined text-[18px]" aria-hidden="true">bookmark_add</span>
                </button>
              </div>
              <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="catalog-shortlist-state inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.14em] text-on-surface-variant" data-catalog-shortlist-state hidden>
                  <span class="material-symbols-outlined text-[13px]" aria-hidden="true">bookmark</span>
                  <span>{{ $copy['ui']['shortlist_saved'] }}</span>
                </span>
                @foreach($indicatorLabels as $indicatorLabel)
                  <span class="inline-flex items-center rounded-full bg-surface-container-high px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.1em] text-on-surface-variant" data-catalog-indicator>{{ $indicatorLabel }}</span>
                @endforeach
              </div>
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
                @if($authorMark !== '—')
                  <span><strong>{{ $copy['ui']['author_mark'] }}:</strong> {{ $authorMark }}</span>
                @endif
                @forelse($availabilityByPoint as $pointLabel => $pointAvailable)
                  <span><strong>{{ $copy['ui']['copies'] }}:</strong> {{ $pointAvailable }} экз. — {{ $pointLabel }}</span>
                @empty
                  <span><strong>{{ $copy['ui']['copies'] }}:</strong> {{ $availableCopies }} экз.</span>
                @endforelse
                <span><strong>{{ $copy['ui']['language_label'] }}:</strong> {{ $languageLabel !== '' ? $languageLabel : '—' }}</span>
                <span><strong>{{ $copy['ui']['institution_label'] }}:</strong> {{ $locationLabel }}</span>
              </div>
              <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                <a href="{{ $detailHref }}" class="text-sm font-bold text-secondary flex items-center gap-2 group/btn">
                  <span class="material-symbols-outlined text-lg">{{ $icon }}</span>
                  <span>{{ $primaryLabel }}</span>
                </a>
                <button type="button" class="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors flex items-center gap-2" onclick="copyCitation(@js($citation))">
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
          <div class="public-v2__empty">
            <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
            <h3>{{ $copy['ui']['empty'] }}</h3>
            <p>{{ $copy['ui']['empty_hint'] }}</p>
            <button type="button" onclick="clearAllFilters()" class="mt-4 inline-flex items-center gap-2 rounded-full bg-surface-container-high px-5 py-2.5 text-sm font-bold text-secondary hover:bg-surface-container-highest transition-colors">
              <span class="material-symbols-outlined text-lg" aria-hidden="true">restart_alt</span>
              {{ $copy['clear_filters'] }}
            </button>
          </div>
        @endforelse
      </div>
    </div>

    {{-- Its own grid row so the results column ends with the last card: that
         is what bounds the sticky sidebar, keeping both columns level. --}}
    <div class="catalog-v2__pagination border-t border-outline-variant/10 flex justify-center">
        {{-- Server-rendered so the first paint (and a JS-less client) already
             has working, filter-preserving page links. JS re-renders it from
             the API meta after every load. --}}
        <nav id="catalog-pagination" class="flex items-center gap-2" aria-label="Catalog pagination">
          @if($initialTotalPages > 1)
            @if($paginationPage > 1)
              <a href="{{ $pageHref($paginationPage - 1) }}" data-page="{{ $paginationPage - 1 }}" class="catalog-page" aria-label="{{ $copy['ui']['page_prev'] }}"><span class="material-symbols-outlined">chevron_left</span></a>
            @else
              <span class="catalog-page is-disabled" aria-disabled="true"><span class="material-symbols-outlined">chevron_left</span></span>
            @endif
            @foreach($pageWindow($paginationPage, $initialTotalPages) as $entry)
              @if($entry === '…')
                <span class="catalog-page is-gap" aria-hidden="true">…</span>
              @elseif($entry === $paginationPage)
                <a href="{{ $pageHref($entry) }}" data-page="{{ $entry }}" class="catalog-page is-current" aria-current="page">{{ $entry }}</a>
              @else
                <a href="{{ $pageHref($entry) }}" data-page="{{ $entry }}" class="catalog-page">{{ $entry }}</a>
              @endif
            @endforeach
            @if($paginationPage < $initialTotalPages)
              <a href="{{ $pageHref($paginationPage + 1) }}" data-page="{{ $paginationPage + 1 }}" class="catalog-page" aria-label="{{ $copy['ui']['page_next'] }}"><span class="material-symbols-outlined">chevron_right</span></a>
            @else
              <span class="catalog-page is-disabled" aria-disabled="true"><span class="material-symbols-outlined">chevron_right</span></span>
            @endif
          @endif
        </nav>
    </div>
  </div>
  </div>
  </div>
</div>
<div id="catalog-shortlist-toast" class="catalog-shortlist-toast" role="status" aria-live="polite" aria-atomic="true" hidden>
  <span class="material-symbols-outlined" aria-hidden="true">bookmark</span>
  <span id="catalog-shortlist-toast-text">{{ $copy['ui']['shortlist_added_toast'] }}</span>
</div>
@endsection

@section('scripts')
<script>
  document.querySelectorAll('[data-catalog-view]').forEach((button) => {
    button.addEventListener('click', () => {
      const list = document.getElementById('catalog-results-list');
      const isList = button.dataset.catalogView === 'list';
      list?.classList.toggle('is-list', isList);
      document.querySelectorAll('[data-catalog-view]').forEach((item) => item.classList.toggle('is-active', item === button));
    });
  });

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
    resource_type: @json($copy['resource_type']),
    publication_date: @json($copy['publication_date']),
    language: @json($copy['language']),
    fund: @json($copy['fund']),
    branch: @json($copy['branch']),
    category: @json($copy['category']),
    udc_axis: @json($copy['udc_axis']),
    availability: @json($copy['availability']),
    format: @json($copy['format']),
    institution: @json($copy['institution']),
    available_only: @json($copy['available_only']),
    physical_only: @json($copy['physical_only']),
    field_title: @json($copy['field_title']),
    field_author: @json($copy['field_author']),
    field_publisher: @json($copy['field_publisher']),
    field_isbn: @json($copy['field_isbn']),
    field_udc: @json($copy['field_udc']),
  };
  // Value -> human label. Only used to write chip/label text; the option
  // lists themselves are always facet-driven.
  const VALUE_LABELS = {
    resourceType: @json($copy['resource_type_labels']),
    category: @json($copy['category_labels']),
    availability: @json($copy['availability_labels']),
    format: @json($copy['format_labels']),
    language: @json($copy['language_labels']),
    institution: @json($copy['institution_labels']),
    fund: @json(collect($fundFacet)->pluck('label', 'value')->all() ?: (object) []),
    branch: @json(collect($branchFacet)->pluck('label', 'value')->all() ?: (object) []),
    udc: @json(collect($udcFacet)->pluck('label', 'value')->all() ?: (object) []),
  };
  const YEAR_BOUNDS = { min: @json($yearMin), max: @json($yearMax) };
  // Real page size (Setting::catalogPageSize()), never a hardcoded 10.
  const PAGE_SIZE = @json($pageSize);
  const MULTI_AXES = {
    resourceType: 'resource_type',
    fund: 'fund',
    branch: 'branch',
    category: 'category',
  };
  const SINGLE_AXES = {
    availability: 'availability',
    format: 'format',
  };

  let searchDebounceId = null;

  function labelFor(axis, value) {
    const map = VALUE_LABELS[axis] || {};
    return map[value] || String(value);
  }

  function toggleInList(list, value) {
    const current = Array.isArray(list) ? list.slice() : [];
    const index = current.indexOf(value);
    if (index === -1) {
      current.push(value);
    } else {
      current.splice(index, 1);
    }
    return current;
  }

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
    const state = window.catalogState || {};
    let active = 0;
    if ((state.q || '') !== '') active++;
    if ((state.title || '') !== '') active++;
    if ((state.author || '') !== '') active++;
    if ((state.publisher || '') !== '') active++;
    if ((state.isbn || '') !== '') active++;
    if ((state.subject || '') !== '') active++;
    if ((state.udc || '') !== '') active++;
    if (state.availableOnly) active++;
    if (state.physicalOnly) active++;
    if ((state.institution || '') !== '') active++;
    if (Number(state.yearFrom || YEAR_BOUNDS.min) !== YEAR_BOUNDS.min) active++;
    if (Number(state.yearTo || YEAR_BOUNDS.max) !== YEAR_BOUNDS.max) active++;
    if ((state.language || 'all') !== 'all') active++;
    Object.keys(MULTI_AXES).forEach((axis) => { active += (state[axis] || []).length; });
    Object.keys(SINGLE_AXES).forEach((axis) => { if ((state[axis] || '') !== '') active++; });

    const badge = document.getElementById('filter-count-badge');
    if (badge) badge.textContent = String(active);
  }

  function resetFilterByKey(key, value = '') {
    if (Object.prototype.hasOwnProperty.call(MULTI_AXES, key)) {
      const current = window.catalogState[key] || [];
      window.catalogState[key] = value === ''
        ? []
        : current.filter((entry) => entry !== value);
      return;
    }
    if (Object.prototype.hasOwnProperty.call(SINGLE_AXES, key)) {
      window.catalogState[key] = '';
      return;
    }
    if (key === 'q') window.catalogState.q = '';
    if (key === 'title') window.catalogState.title = '';
    if (key === 'author') window.catalogState.author = '';
    if (key === 'publisher') window.catalogState.publisher = '';
    if (key === 'isbn') window.catalogState.isbn = '';
    if (key === 'subject') window.catalogState.subject = '';
    if (key === 'udc') window.catalogState.udc = '';
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
    const pushChip = (key, label, value, removeValue = '') => {
      active.push({ key, label, value, removeValue });
    };

    if ((window.catalogState.q || '') !== '') pushChip('q', '', window.catalogState.q);
    if ((window.catalogState.title || '') !== '') pushChip('title', FILTER_LABELS.field_title, window.catalogState.title);
    if ((window.catalogState.author || '') !== '') pushChip('author', FILTER_LABELS.field_author, window.catalogState.author);
    if ((window.catalogState.publisher || '') !== '') pushChip('publisher', FILTER_LABELS.field_publisher, window.catalogState.publisher);
    if ((window.catalogState.isbn || '') !== '') pushChip('isbn', FILTER_LABELS.field_isbn, window.catalogState.isbn);
    if ((window.catalogState.subject || '') !== '') pushChip('subject', FILTER_LABELS.field_subject, window.catalogState.subject);
    if ((window.catalogState.udc || '') !== '') {
      const known = VALUE_LABELS.udc[window.catalogState.udc];
      const udcValue = known && known !== window.catalogState.udc
        ? `${window.catalogState.udc} — ${known}`
        : window.catalogState.udc;
      pushChip('udc', FILTER_LABELS.udc_axis, udcValue);
    }
    // One chip per selected value on the multi-select axes, so each can be
    // removed on its own.
    (window.catalogState.resourceType || []).forEach((value) => {
      pushChip('resourceType', FILTER_LABELS.resource_type, labelFor('resourceType', value), value);
    });
    (window.catalogState.fund || []).forEach((value) => {
      pushChip('fund', FILTER_LABELS.fund, labelFor('fund', value), value);
    });
    (window.catalogState.branch || []).forEach((value) => {
      pushChip('branch', FILTER_LABELS.branch, labelFor('branch', value), value);
    });
    (window.catalogState.category || []).forEach((value) => {
      pushChip('category', FILTER_LABELS.category, labelFor('category', value), value);
    });

    if ((window.catalogState.availability || '') !== '') {
      pushChip('availability', FILTER_LABELS.availability, labelFor('availability', window.catalogState.availability));
    }
    if ((window.catalogState.format || '') !== '') {
      pushChip('format', FILTER_LABELS.format, labelFor('format', window.catalogState.format));
    }

    if ((window.catalogState.language || 'all') !== 'all') {
      pushChip('language', FILTER_LABELS.language, labelFor('language', window.catalogState.language));
    }

    const from = Number(window.catalogState.yearFrom || YEAR_BOUNDS.min);
    const to = Number(window.catalogState.yearTo || YEAR_BOUNDS.max);
    if (from !== YEAR_BOUNDS.min || to !== YEAR_BOUNDS.max) {
      pushChip('yearRange', FILTER_LABELS.publication_date, `${from}–${to}`);
    }

    if ((window.catalogState.institution || '') !== '') {
      pushChip('institution', FILTER_LABELS.institution, labelFor('institution', window.catalogState.institution));
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
    list.replaceChildren();

    if (!activeFilters.length) {
      wrapper.hidden = true;
      return;
    }

    wrapper.hidden = false;

    activeFilters.forEach((item) => {
      // Built with createElement/textContent — server values never travel
      // through innerHTML.
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'catalog-chip-remove';
      button.dataset.removeFilter = item.key;
      if (item.removeValue !== '') button.dataset.removeValue = item.removeValue;

      if (item.label) {
        const key = document.createElement('span');
        key.className = 'catalog-chip-remove__key';
        key.textContent = `${item.label}:`;
        button.appendChild(key);
      }

      const value = document.createElement('span');
      value.className = 'catalog-chip-remove__value';
      value.textContent = item.value;
      button.appendChild(value);

      const icon = document.createElement('span');
      icon.className = 'material-symbols-outlined text-sm text-on-surface-variant';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = 'close';
      button.appendChild(icon);

      button.addEventListener('click', () => {
        resetFilterByKey(button.dataset.removeFilter || '', button.dataset.removeValue || '');
        syncFilterControls();
        window.catalogState.page = 1;
        loadCatalog();
      });

      list.appendChild(button);
    });
  }

  /**
   * Reconcile the sidebar's tick-boxes and radios with the state. Runs after
   * every load, so a chip removal or deep link can never leave a control
   * lying.
   */
  function syncFacetControls() {
    const state = window.catalogState;

    const availableOnly = document.getElementById('filter-available-only');
    if (availableOnly) availableOnly.checked = !!state.availableOnly;
    const physicalOnly = document.getElementById('filter-physical-only');
    if (physicalOnly) physicalOnly.checked = !!state.physicalOnly;

    document.querySelectorAll('[data-facet]').forEach((input) => {
      const axis = Object.keys(MULTI_AXES).find((key) => MULTI_AXES[key] === input.dataset.facet);
      if (!axis) return;
      input.checked = (state[axis] || []).includes(input.value);
    });

    document.querySelectorAll('[data-facet-single]').forEach((input) => {
      const axis = input.dataset.facetSingle;
      const current = state[axis] || '';
      input.checked = input.value === current;
    });
  }

  /**
   * Full push of window.catalogState back onto every control, text inputs
   * included. Used by the entry points that replace the state wholesale
   * (clear-all, chip removal, first paint) — not on every load, so it can
   * never rewrite the search box while the reader is typing in it.
   */
  function syncFilterControls() {
    const state = window.catalogState;

    const searchInput = document.getElementById('catalog-search-input');
    if (searchInput) searchInput.value = state.q || '';
    const setValue = (id, value) => {
      const element = document.getElementById(id);
      if (element) element.value = value;
    };
    setValue('advanced-title-input', state.title || '');
    setValue('advanced-author-input', state.author || '');
    setValue('advanced-publisher-input', state.publisher || '');
    setValue('advanced-isbn-input', state.isbn || '');
    setValue('advanced-subject-input', state.subject || '');
    setValue('advanced-year-from-input', state.yearFrom || String(YEAR_BOUNDS.min));
    setValue('advanced-year-to-input', state.yearTo || String(YEAR_BOUNDS.max));
    setValue('advanced-resource-type-input', (state.resourceType || [])[0] || '');

    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) sortSelect.value = state.sort || 'relevance';

    syncFacetControls();
    updateYearRangeVisual();
    syncLanguageButtons();
    syncSortMenu();
    updateFilterBadge();
  }

  function clearAllFilters() {
    window.catalogState = {
      q: '',
      title: '',
      author: '',
      publisher: '',
      isbn: '',
      subject: '',
      language: 'all',
      sort: 'relevance',
      yearFrom: String(YEAR_BOUNDS.min),
      yearTo: String(YEAR_BOUNDS.max),
      availableOnly: false,
      physicalOnly: false,
      institution: '',
      resourceType: [],
      fund: [],
      branch: [],
      category: [],
      availability: '',
      format: '',
      page: 1,
      advancedOpen: false,
    };

    document.getElementById('advanced-search-panel')?.setAttribute('hidden', 'hidden');

    syncFilterControls();
    loadCatalog();
  }

  function copyCitation(citation) {
    navigator.clipboard?.writeText(citation).catch(() => {});
  }

  // "Author. Title. — Publisher, Year. — ISBN x." — the same shape
  // App\Services\BibliographyFormatter::formatBookEntry() emits for shortlist
  // exports, so a copied card matches an exported reading list character for
  // character. Parts the MARC source left empty are dropped rather than
  // printed as dashes.
  function buildCitation(parts) {
    const clean = (value) => {
      const text = String(value ?? '').trim();
      return (text === '' || text === '—') ? '' : text;
    };

    const segments = [];
    if (clean(parts.author)) {
      segments.push(clean(parts.author));
    }
    segments.push(clean(parts.title) || uiCopy.untitled || 'Без названия');

    const imprint = [clean(parts.publisher), clean(parts.year)].filter(Boolean).join(', ');
    if (imprint) {
      segments.push(`— ${imprint}`);
    }
    if (clean(parts.isbn)) {
      segments.push(`— ISBN ${clean(parts.isbn)}`);
    }

    return `${segments.join('. ')}.`;
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
    const unitName = String(location?.institutionUnit?.name || '').trim();
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

    return formatValue(serviceName || unitName, uiCopy.no_location);
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
    document.getElementById('advanced-subject-input').value = '';
    document.getElementById('advanced-year-from-input').value = String(YEAR_BOUNDS.min);
    document.getElementById('advanced-year-to-input').value = String(YEAR_BOUNDS.max);
    document.getElementById('advanced-resource-type-input').value = '';
    window.catalogState.title = '';
    window.catalogState.author = '';
    window.catalogState.publisher = '';
    window.catalogState.isbn = '';
    window.catalogState.subject = '';
    window.catalogState.yearFrom = String(YEAR_BOUNDS.min);
    window.catalogState.yearTo = String(YEAR_BOUNDS.max);
    window.catalogState.resourceType = [];
    updateFilterBadge();
  }

  function applyAdvancedSearch() {
    window.catalogState.title = document.getElementById('advanced-title-input')?.value.trim() || '';
    window.catalogState.author = document.getElementById('advanced-author-input')?.value.trim() || '';
    window.catalogState.publisher = document.getElementById('advanced-publisher-input')?.value.trim() || '';
    window.catalogState.isbn = document.getElementById('advanced-isbn-input')?.value.trim() || '';
    window.catalogState.subject = document.getElementById('advanced-subject-input')?.value.trim() || '';
    window.catalogState.yearFrom = document.getElementById('advanced-year-from-input')?.value || String(YEAR_BOUNDS.min);
    window.catalogState.yearTo = document.getElementById('advanced-year-to-input')?.value || String(YEAR_BOUNDS.max);
    const advancedResourceType = document.getElementById('advanced-resource-type-input')?.value || '';
    window.catalogState.resourceType = advancedResourceType ? [advancedResourceType] : [];
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

  function detailHref(identifier) {
    if (!identifier || identifier === '—') {
      return '/catalog' + LANG_SUFFIX;
    }
    return '/book/' + encodeURIComponent(identifier) + LANG_SUFFIX;
  }

  function deriveMaterialKind(item) {
    const declaredFormat = String(item?.indicators?.format || '');
    if (declaredFormat === 'electronic') return 'digital';
    // Hybrid used to collapse into 'physical', so a record with a full text
    // attached still advertised itself as "Печатный экземпляр" and the reader
    // had no hint that an online copy existed.
    if (declaredFormat === 'hybrid') return 'hybrid';
    if (declaredFormat === 'print') return 'physical';

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

    // Both formats exist: lead with the online copy, because that is the action
    // the reader can take immediately, while keeping the shelf route visible.
    if (kind === 'hybrid') {
      return {
        badgeClass: 'bg-secondary-container text-on-secondary-container',
        badgeLabel: uiCopy.hybrid_badge,
        primaryLabel: uiCopy.read,
        primaryIcon: 'auto_stories',
        statusClass: 'text-secondary',
        statusDot: 'bg-secondary',
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
    const udc = formatValue(item?.udc?.display || item?.udc?.description || item?.udc?.raw);
    const authorMark = formatValue(item?.authorMark);
    const resourceType = formatValue(item?.resourceType, 'book');
    const resourceTypeLabel = labelFor('resourceType', resourceType);
    const location = formatLocationLabel(item?.availability?.locations?.[0] || {});
    const availabilityByPoint = Array.from(
      (Array.isArray(item?.availability?.locations) ? item.availability.locations : [])
        .reduce((points, holding) => {
          const label = formatLocationLabel(holding);
          points.set(label, (points.get(label) || 0) + Number(holding?.copies?.available || 0));
          return points;
        }, new Map())
        .entries()
    ).map(([label, available]) => ({ label, available }));
    const language = formatValue((item?.language?.code || item?.language?.raw || '').toUpperCase(), '—');
    const copies = Number(item?.copies?.available ?? 0);
    const total = Number(item?.copies?.total ?? 0);
    const subjects = Array.isArray(item?.classification)
      ? item.classification.map((subject) => String(subject?.label || '').trim()).filter(Boolean).slice(0, 3)
      : [];
    const annotation = isMeaningfulText(item?.annotation) ? String(item.annotation).trim() : '';
    const subtitle = isMeaningfulText(item?.title?.subtitle) ? String(item.title.subtitle).trim() : '';
    const description = annotation || subtitle || (subjects.length ? subjects.join(' · ') : uiCopy.description_placeholder);
    const metaLine = [author, publicationYear, publisher].filter((part) => isMeaningfulText(part));
    const indicators = item?.indicators || {};
    const availabilityLabels = {
      available: uiCopy.status_available,
      issued: uiCopy.status_issued,
      in_processing: uiCopy.status_processing,
      under_repair: uiCopy.status_repair,
      no_holdings: uiCopy.status_unknown,
    };
    const statusLabel = availabilityLabels[indicators.availability] || uiCopy.status_unknown;
    const statusPresentation = {
      available: { statusClass: 'text-secondary', statusDot: 'bg-secondary' },
      issued: { statusClass: 'text-error', statusDot: 'bg-error' },
      under_repair: { statusClass: 'text-error', statusDot: 'bg-error' },
      in_processing: { statusClass: 'text-on-surface-variant', statusDot: 'bg-outline' },
      no_holdings: { statusClass: 'text-on-surface-variant', statusDot: 'bg-outline' },
    }[indicators.availability] || { statusClass: 'text-on-surface-variant', statusDot: 'bg-outline' };
    const indicatorLabels = [
      uiCopy[`format_${indicators.format || 'print'}`] || uiCopy.format_print,
      uiCopy[indicators.copySupply || 'absent'] || uiCopy.absent,
      uiCopy[`access_${indicators.accessRestriction || 'free'}`] || uiCopy.access_free,
      ...(indicators.popular ? [uiCopy.popular] : []),
      ...(indicators.newArrival ? [uiCopy.new_arrival] : []),
    ];

    return {
      title,
      author,
      publicationYear: publicationYear || '—',
      publisher,
      resourceTypeLabel,
      metaLine: metaLine.join(' · '),
      description,
      subjects,
      isbn,
      udc,
      authorMark,
      language,
      copies,
      total,
      location,
      availabilityByPoint,
      indicatorLabels,
      // Built from the raw values, not the display ones: a reference must not
      // carry the "author not specified" placeholder as if it were a name.
      citation: buildCitation({
        author: formatValue(item?.primaryAuthor, ''),
        title,
        publisher,
        year: publicationYear,
        isbn,
      }),
      // formatValue is this file's isMeaningfulText-backed helper; the old
      // call here was to normalizeText(), which this view never defined, so
      // every loadCatalog() threw before a single card was built.
      coverUrl: formatValue(item?.coverPath || item?.coverUrl || item?.cover?.medium || item?.cover?.small, ''),
      detailUrl: detailHref(isbn !== '—' ? isbn : item?.id),
      statusLabel,
      ...getMaterialPresentation(kind),
      ...statusPresentation,
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
    const coverCode = record.authorMark !== '—'
      ? `${uiCopy.author_mark}: ${record.authorMark}`
      : (record.udc !== '—'
        ? `${uiCopy.udc}: ${record.udc}`
        : (record.isbn !== '—' ? `${uiCopy.isbn}: ${record.isbn}` : '—'));
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
      <article class="flex flex-col sm:flex-row gap-8 group catalog-item" data-catalog-card>
        ${buildBookMedia(record, index)}
        <div class="flex-grow">
          <div class="flex justify-between items-start gap-4">
            <div>
              <span class="inline-block px-2 py-0.5 ${record.badgeClass} text-[10px] font-bold uppercase tracking-wider rounded mb-2">${escapeHtml(record.badgeLabel)}</span>
              <span class="inline-block px-2 py-0.5 bg-surface-container-high text-primary text-[10px] font-bold uppercase tracking-wider rounded mb-2">${escapeHtml(record.resourceTypeLabel)}</span>
              <a href="${record.detailUrl}" class="block text-2xl font-newsreader font-semibold text-primary group-hover:text-secondary transition-colors cursor-pointer">${escapeHtml(record.title)}</a>
              <p data-catalog-description class="mt-3 text-on-surface-variant text-sm leading-relaxed">${escapeHtml(record.description)}</p>
              <p class="mt-3 text-on-surface-variant font-medium">${escapeHtml(record.metaLine)}</p>
            </div>
            <button
              type="button"
              class="catalog-shortlist-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-secondary bg-secondary text-white shadow-[0_8px_22px_rgba(0,106,106,0.18)] transition-colors hover:bg-secondary-container hover:text-secondary"
              data-catalog-shortlist-button
              onclick="toggleCatalogShortlist(this); return false;"
              data-shortlist-identifier="${escapeHtml(record.isbn !== '—' ? record.isbn : record.detailUrl)}"
              data-shortlist-title="${escapeHtml(record.title)}"
              data-shortlist-author="${escapeHtml(record.author)}"
              data-shortlist-publisher="${escapeHtml(record.publisher)}"
              data-shortlist-year="${escapeHtml(record.publicationYear !== '—' ? record.publicationYear : '')}"
              data-shortlist-language="${escapeHtml(record.language !== '—' ? record.language : '')}"
              data-shortlist-isbn="${escapeHtml(record.isbn !== '—' ? record.isbn : '')}"
              data-shortlist-available="${escapeHtml(record.copies)}"
              data-shortlist-total="${escapeHtml(record.total)}"
              data-shortlist-url="${escapeHtml(record.detailUrl)}"
              data-shortlist-provider="${escapeHtml(record.publisher)}"
              data-shortlist-type="${record.badgeLabel === uiCopy.electronic ? 'external_resource' : 'book'}"
              aria-label="${escapeHtml(uiCopy.shortlist_add)}"
              aria-pressed="false"
              title="${escapeHtml(uiCopy.shortlist_add)}"
            >
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">bookmark_add</span>
            </button>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="catalog-shortlist-state inline-flex items-center gap-1 rounded-full bg-surface-container-high px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.14em] text-on-surface-variant" data-catalog-shortlist-state hidden>
              <span class="material-symbols-outlined text-[13px]" aria-hidden="true">bookmark</span>
              <span>${escapeHtml(uiCopy.shortlist_saved)}</span>
            </span>
            ${record.indicatorLabels.map((label) => `<span class="inline-flex items-center rounded-full bg-surface-container-high px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.1em] text-on-surface-variant" data-catalog-indicator>${escapeHtml(label)}</span>`).join('')}
          </div>
          ${subjectsHtml}
          <div class="flex flex-wrap gap-3 text-xs text-on-surface-variant mb-6">
            <span><strong>${uiCopy.isbn}:</strong> ${escapeHtml(record.isbn)}</span>
            <span><strong>${uiCopy.udc}:</strong> ${escapeHtml(record.udc)}</span>
            ${record.authorMark !== '—' ? `<span><strong>${escapeHtml(uiCopy.author_mark)}:</strong> ${escapeHtml(record.authorMark)}</span>` : ''}
            ${(record.availabilityByPoint.length
              ? record.availabilityByPoint
              : [{ label: record.location, available: record.copies }])
              .map((point) => `<span><strong>${escapeHtml(uiCopy.copies)}:</strong> ${escapeHtml(point.available)} экз. — ${escapeHtml(point.label)}</span>`)
              .join('')}
            <span><strong>${escapeHtml(uiCopy.language_label)}:</strong> ${escapeHtml(record.language)}</span>
            <span><strong>${escapeHtml(uiCopy.institution_label)}:</strong> ${escapeHtml(record.location)}</span>
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
            <a href="${record.detailUrl}" class="text-sm font-bold text-secondary flex items-center gap-2 group/btn">
              <span class="material-symbols-outlined text-lg">${record.primaryIcon}</span>
              <span>${escapeHtml(record.primaryLabel)}</span>
            </a>
            <button type="button" class="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors flex items-center gap-2" onclick="copyCitation(${escapeHtml(JSON.stringify(record.citation))})">
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

  function buildShortlistPayload(button) {
    const identifier = button.dataset.shortlistIdentifier || '';
    return {
      identifier,
      title: button.dataset.shortlistTitle || identifier || 'Untitled',
      type: button.dataset.shortlistType || 'book',
      author: button.dataset.shortlistAuthor || '',
      publisher: button.dataset.shortlistPublisher || '',
      year: button.dataset.shortlistYear || '',
      language: button.dataset.shortlistLanguage || '',
      isbn: button.dataset.shortlistIsbn || '',
      available: Number(button.dataset.shortlistAvailable || 0),
      total: Number(button.dataset.shortlistTotal || 0),
      url: button.dataset.shortlistUrl || '',
      provider: button.dataset.shortlistProvider || '',
      access_type: button.dataset.shortlistType === 'external_resource' ? 'open' : '',
    };
  }

  let catalogToastTimer = null;

  function setCatalogShortlistCount(total) {
    const badge = document.getElementById('header-shortlist-count');
    if (badge) {
      const count = Math.max(0, Number(total || 0));
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = count <= 0;
    }

    window.refreshHeaderShortlistCount?.();
  }

  function showCatalogShortlistToast(message, type = 'success') {
    const toast = document.getElementById('catalog-shortlist-toast');
    const text = document.getElementById('catalog-shortlist-toast-text');
    if (!toast || !text) return;

    clearTimeout(catalogToastTimer);
    text.textContent = message;
    toast.classList.toggle('is-error', type === 'error');
    toast.hidden = false;
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    catalogToastTimer = window.setTimeout(() => {
      toast.classList.remove('is-visible');
      window.setTimeout(() => { toast.hidden = true; }, 220);
    }, 2400);
  }

  async function refreshCatalogShortlistSummary() {
    try {
      const response = await fetch('/api/v1/shortlist/summary', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      if (!response.ok) return;

      const payload = await response.json();
      setCatalogShortlistCount(payload?.data?.total || 0);
    } catch (error) {
      console.error('Shortlist summary failed:', error);
    }
  }

  function setCatalogShortlistButtonState(button, saved) {
    if (!button) return;
    const card = button.closest('[data-catalog-card]');
    const state = card?.querySelector('[data-catalog-shortlist-state]');

    button.dataset.shortlistActive = saved ? '1' : '0';
    button.setAttribute('aria-pressed', saved ? 'true' : 'false');
    button.setAttribute('title', saved ? uiCopy.shortlist_saved : uiCopy.shortlist_add);
    button.setAttribute('aria-label', saved ? uiCopy.shortlist_saved : uiCopy.shortlist_add);
    button.className = saved
      ? 'catalog-shortlist-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-secondary bg-secondary-container text-secondary shadow-[0_8px_22px_rgba(0,106,106,0.18)] transition-colors hover:bg-secondary hover:text-white'
      : 'catalog-shortlist-toggle inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-secondary bg-secondary text-white shadow-[0_8px_22px_rgba(0,106,106,0.18)] transition-colors hover:bg-secondary-container hover:text-secondary';
    button.innerHTML = saved
      ? `<span class="material-symbols-outlined text-[18px]" aria-hidden="true">bookmark</span>`
      : `<span class="material-symbols-outlined text-[18px]" aria-hidden="true">bookmark_add</span>`;

    if (state) {
      state.hidden = !saved;
    }
  }

  async function syncCatalogShortlistButtons(scope = document) {
    const buttons = Array.from(scope.querySelectorAll('[data-catalog-shortlist-button]'));
    if (!buttons.length) return;

    const identifiers = [...new Set(buttons.map((button) => button.dataset.shortlistIdentifier || '').filter(Boolean))];
    if (!identifiers.length) return;

    try {
      const response = await fetch('/api/v1/shortlist/check', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ identifiers }),
      });

      if (!response.ok) return;

      const payload = await response.json();
      const map = payload?.data || {};

      buttons.forEach((button) => {
        const identifier = button.dataset.shortlistIdentifier || '';
        setCatalogShortlistButtonState(button, Boolean(map[identifier]));
      });

      setCatalogShortlistCount(payload?.meta?.total || 0);

      return map;
    } catch (error) {
      console.error('Shortlist sync failed:', error);
    }
  }

  async function toggleCatalogShortlist(button) {
    const identifier = button.dataset.shortlistIdentifier || '';
    if (!identifier) return;

    const active = button.dataset.shortlistActive === '1';
    const payload = buildShortlistPayload(button);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    button.disabled = true;

    try {
      const response = await fetch(active ? `/api/v1/shortlist/${encodeURIComponent(identifier)}` : '/api/v1/shortlist', {
        method: active ? 'DELETE' : 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
        body: active ? undefined : JSON.stringify(payload),
      });

      if (!response.ok && response.status !== 409) {
        throw new Error('Shortlist update failed');
      }

      const saved = !active || response.status === 201 || response.status === 409;
      setCatalogShortlistButtonState(button, saved);
      showCatalogShortlistToast(saved ? uiCopy.shortlist_added_toast : uiCopy.shortlist_removed_toast);
      await syncCatalogShortlistButtons(document);
      await refreshCatalogShortlistSummary();
    } catch (error) {
      console.error('Shortlist update failed:', error);
      showCatalogShortlistToast(uiCopy.shortlist_error_toast, 'error');
    } finally {
      button.disabled = false;
    }
  }

  function syncLanguageButtons() {
    document.querySelectorAll('#language-chips button').forEach((button) => {
      const isActive = button.dataset.lang === window.catalogState.language;
      button.classList.toggle('is-active', isActive);
    });
  }

  function buildUiParams() {
    const params = new URLSearchParams();
    if (window.catalogState.q) params.set('q', window.catalogState.q);
    if (window.catalogState.title) params.set('title', window.catalogState.title);
    if (window.catalogState.author) params.set('author', window.catalogState.author);
    if (window.catalogState.publisher) params.set('publisher', window.catalogState.publisher);
    if (window.catalogState.isbn) params.set('isbn', window.catalogState.isbn);
    if (window.catalogState.subject) params.set('subject', window.catalogState.subject);
    if (window.catalogState.udc) params.set('udc', window.catalogState.udc);
    if (window.catalogState.language && window.catalogState.language !== 'all') params.set('language', window.catalogState.language);
    if (window.catalogState.sort && window.catalogState.sort !== 'relevance') params.set('sort', window.catalogState.sort);
    if (window.catalogState.yearFrom && Number(window.catalogState.yearFrom) !== YEAR_BOUNDS.min) params.set('year_from', window.catalogState.yearFrom);
    if (window.catalogState.yearTo && Number(window.catalogState.yearTo) !== YEAR_BOUNDS.max) params.set('year_to', window.catalogState.yearTo);
    if (window.catalogState.availableOnly) params.set('available_only', '1');
    if (window.catalogState.physicalOnly) params.set('physical_only', '1');
    if (window.catalogState.institution) params.set('institution', window.catalogState.institution);
    Object.entries(MULTI_AXES).forEach(([axis, param]) => {
      const values = window.catalogState[axis] || [];
      if (values.length) params.set(param, values.join(','));
    });
    Object.entries(SINGLE_AXES).forEach(([axis, param]) => {
      if (window.catalogState[axis]) params.set(param, window.catalogState[axis]);
    });
    if (window.catalogState.page && window.catalogState.page > 1) params.set('page', String(window.catalogState.page));
    if (@json($lang) !== 'ru') params.set('lang', @json($lang));
    return params;
  }

  function syncUrl() {
    const base = new URL(window.location.href);
    base.search = buildUiParams().toString();
    window.history.replaceState({}, '', base.toString());
  }

  /** Href for a page that keeps every active filter — real, linkable URLs. */
  function pageHref(page) {
    const params = buildUiParams();
    params.delete('page');
    if (page > 1) params.set('page', String(page));
    const query = params.toString();
    return query ? `/catalog?${query}` : '/catalog';
  }

  /** first, last, current ±2, with ellipses — stays sane at ~800 pages. */
  function buildPageWindow(current, total) {
    const wanted = new Set([1, total]);
    for (let page = current - 2; page <= current + 2; page += 1) {
      if (page >= 1 && page <= total) wanted.add(page);
    }

    const sorted = [...wanted].filter((page) => page >= 1 && page <= total).sort((left, right) => left - right);
    const window_ = [];
    let previous = 0;
    sorted.forEach((page) => {
      if (previous > 0 && page - previous > 1) window_.push('…');
      window_.push(page);
      previous = page;
    });

    return window_;
  }

  function renderPagination(meta = {}) {
    const nav = document.getElementById('catalog-pagination');
    if (!nav) return;

    const totalPages = Math.max(1, Number(meta.total_pages || meta.totalPages || 1));
    const currentPage = Math.min(Math.max(1, Number(meta.page || 1)), totalPages);

    nav.replaceChildren();
    if (totalPages <= 1) return;

    const makeIcon = (name) => {
      const icon = document.createElement('span');
      icon.className = 'material-symbols-outlined';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = name;
      return icon;
    };

    const makeLink = (page, content, ariaLabel) => {
      const link = document.createElement('a');
      link.className = 'catalog-page';
      link.href = pageHref(page);
      link.dataset.page = String(page);
      if (ariaLabel) link.setAttribute('aria-label', ariaLabel);
      if (page === currentPage) {
        link.classList.add('is-current');
        link.setAttribute('aria-current', 'page');
      }
      if (typeof content === 'string') {
        link.textContent = content;
      } else {
        link.appendChild(content);
      }
      link.addEventListener('click', (event) => {
        event.preventDefault();
        window.catalogState.page = page;
        loadCatalog();
      });
      return link;
    };

    const makeDisabled = (iconName) => {
      const span = document.createElement('span');
      span.className = 'catalog-page is-disabled';
      span.setAttribute('aria-disabled', 'true');
      span.appendChild(makeIcon(iconName));
      return span;
    };

    nav.appendChild(currentPage > 1
      ? makeLink(currentPage - 1, makeIcon('chevron_left'), uiCopy.page_prev)
      : makeDisabled('chevron_left'));

    buildPageWindow(currentPage, totalPages).forEach((entry) => {
      if (entry === '…') {
        const gap = document.createElement('span');
        gap.className = 'catalog-page is-gap';
        gap.setAttribute('aria-hidden', 'true');
        gap.textContent = '…';
        nav.appendChild(gap);
        return;
      }
      nav.appendChild(makeLink(entry, String(entry)));
    });

    nav.appendChild(currentPage < totalPages
      ? makeLink(currentPage + 1, makeIcon('chevron_right'), uiCopy.page_next)
      : makeDisabled('chevron_right'));
  }

  async function loadCatalog() {
    clampYearState();
    const apiParams = new URLSearchParams();
    if (window.catalogState.q) apiParams.set('q', window.catalogState.q);
    if (window.catalogState.title) apiParams.set('title', window.catalogState.title);
    if (window.catalogState.author) apiParams.set('author', window.catalogState.author);
    if (window.catalogState.publisher) apiParams.set('publisher', window.catalogState.publisher);
    if (window.catalogState.isbn) apiParams.set('isbn', window.catalogState.isbn);
    if (window.catalogState.subject) apiParams.set('subject', window.catalogState.subject);
    if (window.catalogState.udc) apiParams.set('udc', window.catalogState.udc);
    if (window.catalogState.language && window.catalogState.language !== 'all') apiParams.set('language', window.catalogState.language);
    // The slider's full min/max span means "no year filter". Sending those
    // bounds would silently exclude records whose MARC year is missing.
    if (window.catalogState.yearFrom && Number(window.catalogState.yearFrom) !== YEAR_BOUNDS.min) {
      apiParams.set('year_from', window.catalogState.yearFrom);
    }
    if (window.catalogState.yearTo && Number(window.catalogState.yearTo) !== YEAR_BOUNDS.max) {
      apiParams.set('year_to', window.catalogState.yearTo);
    }
    if (window.catalogState.availableOnly) apiParams.set('available_only', '1');
    if (window.catalogState.physicalOnly) apiParams.set('physical_only', '1');
    if (window.catalogState.institution) apiParams.set('institution', window.catalogState.institution);
    // Canonical multi-select axes travel as a comma separated list.
    Object.entries(MULTI_AXES).forEach(([axis, param]) => {
      const values = window.catalogState[axis] || [];
      if (values.length) apiParams.set(param, values.join(','));
    });
    Object.entries(SINGLE_AXES).forEach(([axis, param]) => {
      if (window.catalogState[axis]) apiParams.set(param, window.catalogState[axis]);
    });
    apiParams.set('page', String(window.catalogState.page || 1));
    apiParams.set('sort', SORT_API_MAP[window.catalogState.sort] || 'popular');
    apiParams.set('limit', String(PAGE_SIZE));

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

      // A hand-typed page past the end snaps back to the last real page
      // instead of stranding the reader on an empty screen. The retry can
      // only run once: the clamped page is always <= total_pages.
      const metaTotalPages = Math.max(1, Number(meta.total_pages || meta.totalPages || 1));
      if (Number(meta.total || 0) > 0 && Number(window.catalogState.page || 1) > metaTotalPages) {
        window.catalogState.page = metaTotalPages;
        loadCatalog();
        return;
      }

      if (window.catalogState.sort === 'year_asc') {
        data = [...data].sort((left, right) => Number(left?.publicationYear || 0) - Number(right?.publicationYear || 0));
      }

      if (container) {
        // Mirrors the server-rendered no-results branch: a bare "0" with no
        // explanation reads as a broken page rather than an honest no-match.
        container.innerHTML = data.length
          ? data.map((item, index) => buildCard(item, index)).join('')
          : `<div class="public-v2__empty">
              <span class="material-symbols-outlined" aria-hidden="true">search_off</span>
              <h3>${escapeHtml(uiCopy.empty)}</h3>
              <p>${escapeHtml(uiCopy.empty_hint)}</p>
              <button type="button" onclick="clearAllFilters()" class="mt-4 inline-flex items-center gap-2 rounded-full bg-surface-container-high px-5 py-2.5 text-sm font-bold text-secondary hover:bg-surface-container-highest transition-colors">
                <span class="material-symbols-outlined text-lg" aria-hidden="true">restart_alt</span>
                ${escapeHtml(@json($copy['clear_filters']))}
              </button>
            </div>`;
        syncCatalogShortlistButtons(container);
      }

      // The range is the real slice the API returned, so the last page of a
      // 13-record collection reads "13-13 из 13", not "13-24".
      const total = Number(meta.total ?? data.length ?? 0);
      const perPage = Math.max(1, Number(meta.per_page || PAGE_SIZE));
      const currentPage = Math.max(1, Number(meta.page || 1));
      const fromValue = (total > 0 && data.length > 0) ? ((currentPage - 1) * perPage) + 1 : 0;
      const toValue = fromValue > 0 ? Math.min(fromValue + data.length - 1, total) : 0;
      // Mirrors the server's $initialQueryLabel exactly, so the counter does
      // not change wording between first paint and the first XHR.
      const queryLabel = window.catalogState.q || String(window.catalogState.language || 'all').toUpperCase();

      if (count) {
        count.replaceChildren();
        const range = document.createElement('span');
        range.className = 'text-on-surface font-bold';
        range.textContent = `${fromValue}-${toValue}`;
        const totalNode = document.createElement('span');
        totalNode.className = 'font-bold';
        totalNode.textContent = String(total);
        const label = document.createElement('span');
        label.className = 'font-medium';
        label.textContent = `“${queryLabel}”`;
        count.append(
          `${@json($copy['showing'])} `, range,
          ` ${@json($copy['of'])} `, totalNode,
          ` ${uiCopy.results_for} `, label,
        );
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
    syncFacetControls();
    updateYearRangeVisual();
    syncLanguageButtons();
    syncSortMenu();
    syncUrl();
  }

  window.catalogState = {
    q: @json($q),
    title: @json($titleFilter),
    author: @json($authorFilter),
    publisher: @json($publisherFilter),
    isbn: @json($isbnFilter),
    subject: @json($subjectFilter),
    udc: @json($udcFilter),
    language: @json($language),
    sort: @json($sort),
    yearFrom: @json($yearFrom),
    yearTo: @json($yearTo),
    availableOnly: @json($availableOnly),
    physicalOnly: @json($physicalOnly),
    // Legacy coarse axis: no sidebar control any more (Фонд/Филиал replace
    // it), but kept in state so an existing ?institution= deep link keeps
    // filtering and stays removable from the active-filter chip row.
    institution: @json($institution),
    resourceType: @json($resourceTypeSelected),
    fund: @json($fundSelected),
    branch: @json($branchSelected),
    category: @json($categorySelected),
    availability: @json($availabilitySelected),
    format: @json($formatSelected),
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

  // Multi-select facet checkboxes: resource_type / fund / branch / category.
  document.querySelectorAll('[data-facet]').forEach((input) => {
    input.addEventListener('change', () => {
      const axis = Object.keys(MULTI_AXES).find((key) => MULTI_AXES[key] === input.dataset.facet);
      if (!axis) return;
      window.catalogState[axis] = toggleInList(window.catalogState[axis], input.value);
      window.catalogState.page = 1;
      loadCatalog();
    });
  });

  // Single-select facet radios: availability / format.
  document.querySelectorAll('[data-facet-single]').forEach((input) => {
    input.addEventListener('change', () => {
      if (!input.checked) return;
      const axis = input.dataset.facetSingle;
      window.catalogState[axis] = input.value;
      window.catalogState.page = 1;
      loadCatalog();
    });
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
      if (button.disabled) return;
      window.catalogState.language = button.dataset.lang || 'all';
      window.catalogState.page = 1;
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

  ['advanced-title-input', 'advanced-author-input', 'advanced-publisher-input', 'advanced-isbn-input', 'advanced-subject-input'].forEach((id) => {
    document.getElementById(id)?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyAdvancedSearch();
      }
    });
  });

  syncFilterControls();
  renderActiveFilters();
  refreshCatalogShortlistSummary();
  syncCatalogShortlistButtons(document);
  loadCatalog();
</script>
@endsection
