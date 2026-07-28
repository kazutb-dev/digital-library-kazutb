@extends('layouts.public', ['activePage' => 'home'])

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

  $chrome = [
      'ru' => [
          'title'                    => 'Главная — Библиотека КазТБУ',
          'hero_kicker'              => 'Цифровой куратор',
          'hero_h1'                  => 'Открывайте знания,',
          'hero_h1_accent'           => 'управляйте источниками.',
          'hero_lead'                => 'Академические коллекции, архивы и цифровые ресурсы КазТБУ в одном месте.',
          'search_placeholder'       => 'Поиск по каталогу, авторам, УДК…',
          'search_cta'               => 'Найти',
          'trending'                 => 'Актуальные темы:',
          'hero_img_alt'             => 'Читальный зал библиотеки КазТБУ',
          'stats_archives_label'     => 'Архивных материалов',
          'stats_archives_value'     => '120 000+',
          'stats_scholars_label'     => 'Активных читателей',
          'stats_scholars_value'     => '8 400+',
          'gateway_heading'          => 'Навигационный центр библиотеки',
          'gateway_lead'             => 'Переходите к ключевым публичным разделам: от каталога и репозитория до новостей, правил и контактов.',
          'gateway_meta'             => 'Public Gateway',
          'identity_brand' => 'Библиотека КазТБУ',
      ],
      'kk' => [
          'title'                    => 'Басты бет — КазТБУ Кітапханасы',
          'hero_kicker'              => 'Цифрлық куратор',
          'hero_h1'                  => 'Білімді ашыңыз,',
          'hero_h1_accent'           => 'дереккөздерді басқарыңыз.',
          'hero_lead'                => 'КазТБУ академиялық жинақтары, мұрағаттары және цифрлық ресурстары бір жерде.',
          'search_placeholder'       => 'Каталог, авторлар, ӘЖЖ бойынша іздеу…',
          'search_cta'               => 'Іздеу',
          'trending'                 => 'Өзекті тақырыптар:',
          'hero_img_alt'             => 'КазТБУ кітапханасының оқу залы',
          'stats_archives_label'     => 'Мұрағат материалдары',
          'stats_archives_value'     => '120 000+',
          'stats_scholars_label'     => 'Белсенді оқырмандар',
          'stats_scholars_value'     => '8 400+',
          'gateway_heading'          => 'Кітапхана навигация орталығы',
          'gateway_lead'             => 'Каталогтан репозиторийге дейін, жаңалықтардан ережелер мен байланысқа дейін негізгі қоғамдық бөлімдерге өтіңіз.',
          'gateway_meta'             => 'Public Gateway',
          'identity_brand' => 'КазТБУ Кітапханасы',
      ],
      'en' => [
          'title'                    => 'Home — KazUTB Smart Library',
          'hero_kicker'              => 'Digital Curator',
          'hero_h1'                  => 'Discover Knowledge,',
          'hero_h1_accent'           => 'Curate Your Sources.',
          'hero_lead'                => 'KazUTB academic collections, archives, and digital resources in one place.',
          'search_placeholder'       => 'Search by title, author, UDC…',
          'search_cta'               => 'Search',
          'trending'                 => 'Trending topics:',
          'hero_img_alt'             => 'KazUTB Library Reading Room',
          'stats_archives_label'     => 'Archived Materials',
          'stats_archives_value'     => '120,000+',
          'stats_scholars_label'     => 'Active Readers',
          'stats_scholars_value'     => '8,400+',
          'gateway_heading'          => 'Library Navigation Hub',
          'gateway_lead'             => 'Jump to all core public sections, from catalog and repository to news, rules, leadership, and contacts.',
          'gateway_meta'             => 'Public Gateway',
            'identity_brand' => 'KazUTB Smart Library',
      ],
  ];

  $copy = $chrome[$lang];

  $topicLinks = [
      'ru' => [
          ['label' => 'Экономическая реформа',    'href' => $withLang('/catalog', ['udc' => '33'])],
          ['label' => 'Устойчивые технологии',    'href' => $withLang('/catalog', ['udc' => '62'])],
          ['label' => 'История Центральной Азии', 'href' => $withLang('/catalog', ['udc' => '008'])],
      ],
      'kk' => [
          ['label' => 'Экономикалық реформа',  'href' => $withLang('/catalog', ['udc' => '33'])],
          ['label' => 'Тұрақты технологиялар', 'href' => $withLang('/catalog', ['udc' => '62'])],
          ['label' => 'Орта Азия тарихы',      'href' => $withLang('/catalog', ['udc' => '008'])],
      ],
      'en' => [
          ['label' => 'Economic Reform',       'href' => $withLang('/catalog', ['udc' => '33'])],
          ['label' => 'Sustainable Tech',      'href' => $withLang('/catalog', ['udc' => '62'])],
          ['label' => 'Central Asian History', 'href' => $withLang('/catalog', ['udc' => '008'])],
      ],
  ];
  $topics = $topicLinks[$lang];

  $gatewayLinks = [
      ['label' => $lang === 'ru' ? 'Каталог' : ($lang === 'kk' ? 'Каталог' : 'Catalog'), 'href' => $withLang('/catalog')],
      ['label' => $lang === 'ru' ? 'Открытия' : ($lang === 'kk' ? 'Ашылымдар' : 'Discover'), 'href' => $withLang('/discover')],
      ['label' => $lang === 'ru' ? 'Ресурсы' : ($lang === 'kk' ? 'Ресурстар' : 'Resources'), 'href' => $withLang('/resources')],
      ['label' => $lang === 'ru' ? 'Репозиторий' : ($lang === 'kk' ? 'Репозиторий' : 'Repository'), 'href' => $withLang('/repository')],
      ['label' => $lang === 'ru' ? 'Новости' : ($lang === 'kk' ? 'Жаңалықтар' : 'News'), 'href' => $withLang('/news')],
      ['label' => $lang === 'ru' ? 'События' : ($lang === 'kk' ? 'Іс-шаралар' : 'Events'), 'href' => $withLang('/events')],
      ['label' => $lang === 'ru' ? 'О библиотеке' : ($lang === 'kk' ? 'Кітапхана туралы' : 'About'), 'href' => $withLang('/about')],
      ['label' => $lang === 'ru' ? 'Руководство' : ($lang === 'kk' ? 'Басшылық' : 'Leadership'), 'href' => $withLang('/leadership')],
      ['label' => $lang === 'ru' ? 'Правила' : ($lang === 'kk' ? 'Ережелер' : 'Rules'), 'href' => $withLang('/rules')],
      ['label' => $lang === 'ru' ? 'Контакты' : ($lang === 'kk' ? 'Байланыс' : 'Contacts'), 'href' => $withLang('/contacts')],
  ];

      $gatewayMeta = [
        ['icon' => 'library_books', 'tone' => 'bg-secondary-container/60 text-secondary', 'subtitle' => $lang === 'ru' ? 'Поиск и доступ' : ($lang === 'kk' ? 'Іздеу және қолжетімділік' : 'Search and access')],
        ['icon' => 'search', 'tone' => 'bg-primary-container/55 text-primary', 'subtitle' => $lang === 'ru' ? 'Подборки и подбор' : ($lang === 'kk' ? 'Іріктемелер мен ашылымдар' : 'Curated discovery')],
        ['icon' => 'database', 'tone' => 'bg-tertiary-container/55 text-tertiary', 'subtitle' => $lang === 'ru' ? 'Коллекции и базы' : ($lang === 'kk' ? 'Жинақтар мен дерекқорлар' : 'Collections and databases')],
        ['icon' => 'inventory_2', 'tone' => 'bg-primary-fixed/30 text-primary', 'subtitle' => $lang === 'ru' ? 'Архив и репозиторий' : ($lang === 'kk' ? 'Мұрағат және репозиторий' : 'Archive and repository')],
        ['icon' => 'newspaper', 'tone' => 'bg-secondary-fixed/30 text-secondary', 'subtitle' => $lang === 'ru' ? 'Новости и анонсы' : ($lang === 'kk' ? 'Жаңалықтар мен анонстар' : 'News and announcements')],
        ['icon' => 'event', 'tone' => 'bg-tertiary-fixed/30 text-tertiary', 'subtitle' => $lang === 'ru' ? 'Календарь библиотеки' : ($lang === 'kk' ? 'Кітапхана күнтізбесі' : 'Library calendar')],
        ['icon' => 'account_balance', 'tone' => 'bg-primary-container/55 text-primary', 'subtitle' => $lang === 'ru' ? 'Миссия и профиль' : ($lang === 'kk' ? 'Миссия және профиль' : 'Mission and profile')],
        ['icon' => 'badge', 'tone' => 'bg-secondary-container/55 text-secondary', 'subtitle' => $lang === 'ru' ? 'Команда и контакты' : ($lang === 'kk' ? 'Команда және байланыс' : 'Team and contacts')],
        ['icon' => 'gavel', 'tone' => 'bg-primary-fixed/30 text-primary', 'subtitle' => $lang === 'ru' ? 'Условия пользования' : ($lang === 'kk' ? 'Пайдалану шарттары' : 'Use conditions')],
        ['icon' => 'call', 'tone' => 'bg-secondary-fixed/30 text-secondary', 'subtitle' => $lang === 'ru' ? 'Помощь и визит' : ($lang === 'kk' ? 'Көмек пен келу' : 'Help and visit')],
      ];

      $hubImages = [
        'about' => ['src' => '/images/news/campus-library.jpg', 'alt' => $copy['hero_img_alt']],
        'leadership' => ['src' => '/images/news/author-visit.jpg', 'alt' => $lang === 'en' ? 'Library leadership meeting' : ($lang === 'kk' ? 'Кітапхана басшылығымен кездесу' : 'Встреча с руководством библиотеки')],
        'rules' => ['src' => '/images/news/classics-event.jpg', 'alt' => $lang === 'en' ? 'Library collection display' : ($lang === 'kk' ? 'Кітапхана қорларының көрмесі' : 'Книжная выставка и фонды библиотеки')],
        'contacts' => ['src' => '/images/news/default-library.jpg', 'alt' => $lang === 'en' ? 'KazUTB main building' : ($lang === 'kk' ? 'ҚазТБУ негізгі корпусы' : 'Главный корпус КазТБУ')],
        'news' => ['src' => '/images/news/ai-workshop.jpg', 'alt' => $lang === 'en' ? 'Digital preservation research session' : ($lang === 'kk' ? 'Цифрлық сақтау бойынша зерттеу сессиясы' : 'Исследовательская сессия по цифровому сохранению')],
        'events' => ['src' => '/images/news/campus-library.jpg', 'alt' => $lang === 'en' ? 'Symposium in the reading room' : ($lang === 'kk' ? 'Оқу залындағы симпозиум' : 'Симпозиум в читальном зале')],
      ];

      $hubCards = [
        'ru' => [
          'about' => ['eyebrow' => 'О библиотеке', 'title' => 'Институциональная библиотека', 'body' => 'Сохраняем знание. Поддерживаем исследования. Университетская библиотека соединяет академическую традицию и цифровой сервис.', 'cta' => 'Открыть раздел', 'href' => $withLang('/about'), 'icon' => 'account_balance'],
          'leadership' => ['eyebrow' => 'Руководство', 'title' => 'Команда и зоны ответственности', 'body' => 'Руководство координирует академические сервисы, цифровые коллекции и институциональные процессы библиотеки.', 'cta' => 'Открыть раздел', 'href' => $withLang('/leadership'), 'icon' => 'badge'],
          'rules' => ['eyebrow' => 'Правила', 'title' => 'Пользование фондом и ресурсами', 'body' => 'Условия записи, выдачи, пользования фондом и доступа к цифровым ресурсам.', 'cta' => 'Открыть правила', 'href' => $withLang('/rules'), 'icon' => 'gavel'],
          'contacts' => ['eyebrow' => 'Контакты', 'title' => 'Адрес, часы и каналы поддержки', 'body' => 'Адрес, режим работы и способы связаться с библиотекой для консультаций, доступа и административных вопросов.', 'cta' => 'Открыть контакты', 'href' => $withLang('/contacts'), 'icon' => 'call'],
          'news' => ['eyebrow' => 'Новости', 'title' => 'Архивная целостность и цифровое сохранение', 'body' => 'Исследователи и специалисты по цифровому сохранению обсудили долгосрочное хранение, связность метаданных и контролируемый доступ.', 'meta' => '14 апреля 2026 · Главный материал', 'cta' => 'Все новости', 'href' => $withLang('/news'), 'icon' => 'newspaper'],
          'events' => ['eyebrow' => 'События', 'title' => 'Цифровое сохранение фондов', 'body' => 'Открытая сессия для преподавателей и исследователей о цифровом сохранении материалов, метаданных и долгосрочном хранении.', 'meta' => '14 мая 2026 · Симпозиум', 'cta' => 'Все события', 'href' => $withLang('/events'), 'icon' => 'event'],
        ],
        'kk' => [
          'about' => ['eyebrow' => 'Кітапхана туралы', 'title' => 'Институционалдық кітапхана', 'body' => 'Білімді сақтаймыз. Зерттеуді қолдаймыз. Университет кітапханасы академиялық дәстүр мен цифрлық сервисті біріктіреді.', 'cta' => 'Бөлімді ашу', 'href' => $withLang('/about'), 'icon' => 'account_balance'],
          'leadership' => ['eyebrow' => 'Басшылық', 'title' => 'Команда және жауапкершілік аймақтары', 'body' => 'Басшылық кітапхананың академиялық сервистерін, цифрлық жинақтарын және институционалдық процестерін үйлестіреді.', 'cta' => 'Бөлімді ашу', 'href' => $withLang('/leadership'), 'icon' => 'badge'],
          'rules' => ['eyebrow' => 'Ережелер', 'title' => 'Қорды және ресурстарды пайдалану', 'body' => 'Тіркелу, беру, қорды пайдалану және цифрлық ресурстарға қолжетімділік шарттары.', 'cta' => 'Ережелерді ашу', 'href' => $withLang('/rules'), 'icon' => 'gavel'],
          'contacts' => ['eyebrow' => 'Байланыс', 'title' => 'Мекенжай, жұмыс уақыты және қолдау арналары', 'body' => 'Кітапханамен кеңес, қолжетімділік және әкімшілік сұрақтар бойынша байланысу жолдары.', 'cta' => 'Байланыстарды ашу', 'href' => $withLang('/contacts'), 'icon' => 'call'],
          'news' => ['eyebrow' => 'Жаңалықтар', 'title' => 'Мұрағат тұтастығы және цифрлық сақтау', 'body' => 'Зерттеушілер мен цифрлық сақтау мамандары ұзақ мерзімді сақтау, метадеректер байланыстылығы және бақыланатын қолжетімділікті талқылады.', 'meta' => '2026 жылғы 14 сәуір · Басты материал', 'cta' => 'Барлық жаңалықтар', 'href' => $withLang('/news'), 'icon' => 'newspaper'],
          'events' => ['eyebrow' => 'Іс-шаралар', 'title' => 'Қорларды цифрлық сақтау', 'body' => 'Оқытушылар мен зерттеушілерге арналған ашық сессия: материалдарды цифрлық сақтау, метадеректер және ұзақ мерзімді сақтау.', 'meta' => '2026 жылғы 14 мамыр · Симпозиум', 'cta' => 'Барлық іс-шаралар', 'href' => $withLang('/events'), 'icon' => 'event'],
        ],
        'en' => [
          'about' => ['eyebrow' => 'About', 'title' => 'Institutional library', 'body' => 'Preserving knowledge. Supporting research. The university library brings academic tradition and digital service together.', 'cta' => 'Open the section', 'href' => $withLang('/about'), 'icon' => 'account_balance'],
          'leadership' => ['eyebrow' => 'Leadership', 'title' => 'Team and responsibility areas', 'body' => 'Library leadership coordinates academic services, digital collections, and institutional workflows.', 'cta' => 'Open the section', 'href' => $withLang('/leadership'), 'icon' => 'badge'],
          'rules' => ['eyebrow' => 'Rules', 'title' => 'Use of the collection and resources', 'body' => 'Registration, loans, collection use, and access to digital resources.', 'cta' => 'Open rules', 'href' => $withLang('/rules'), 'icon' => 'gavel'],
          'contacts' => ['eyebrow' => 'Contacts', 'title' => 'Address, hours, and support channels', 'body' => 'How to reach the library for consultations, access questions, and administrative matters.', 'cta' => 'Open contacts', 'href' => $withLang('/contacts'), 'icon' => 'call'],
          'news' => ['eyebrow' => 'News', 'title' => 'Archival integrity and digital preservation', 'body' => 'Researchers and digital preservation specialists discussed long-term retention, metadata continuity, and controlled access.', 'meta' => 'April 14, 2026 · Featured report', 'cta' => 'All news', 'href' => $withLang('/news'), 'icon' => 'newspaper'],
          'events' => ['eyebrow' => 'Events', 'title' => 'Digital preservation of collections', 'body' => 'An open session for faculty and researchers on digital preservation, metadata workflows, and long-term retention.', 'meta' => 'May 14, 2026 · Symposium', 'cta' => 'All events', 'href' => $withLang('/events'), 'icon' => 'event'],
        ],
      ][$lang];

      $premium = [
        'ru' => [
          'collections_eyebrow' => 'Кураторский выбор · Выпуск 01',
          'collections_note' => 'Фонд собран вокруг академических программ университета и проверен библиотечными специалистами.',
          'collection_metrics' => [
            ['value' => 'УДК', 'label' => 'Системная индексация'],
            ['value' => '3', 'label' => 'Языка коллекций'],
            ['value' => '24/7', 'label' => 'Доступ к цифровому фонду'],
          ],
          'services_eyebrow' => 'Research concierge',
          'services_note' => 'От первого запроса до готовой библиографии — точные сервисы для учебной и исследовательской работы.',
          'directory_eyebrow' => 'Library directory · 10 направлений',
          'institution_eyebrow' => 'Институция',
          'institution_heading' => 'Библиотека как интеллектуальная инфраструктура',
          'institution_lead' => 'Не просто место хранения книг, а среда, где академическая память университета становится доступной, связной и полезной.',
          'journal_eyebrow' => 'Library Journal · 2026',
          'journal_heading' => 'Люди, исследования, события',
          'journal_lead' => 'Редакционная лента о том, как библиотека сохраняет наследие и поддерживает новые исследования.',
          'read_story' => 'Читать материал',
          'view_agenda' => 'Открыть календарь',
        ],
        'kk' => [
          'collections_eyebrow' => 'Кураторлық таңдау · 01 шығарылым',
          'collections_note' => 'Қор университеттің академиялық бағдарламаларына сай жинақталып, кітапхана мамандарымен тексерілген.',
          'collection_metrics' => [
            ['value' => 'ӘОЖ', 'label' => 'Жүйелік индекстеу'],
            ['value' => '3', 'label' => 'Жинақ тілдері'],
            ['value' => '24/7', 'label' => 'Цифрлық қорға қолжетімділік'],
          ],
          'services_eyebrow' => 'Research concierge',
          'services_note' => 'Алғашқы сұраудан дайын библиографияға дейін — оқу мен зерттеуге арналған дәл сервистер.',
          'directory_eyebrow' => 'Library directory · 10 бағыт',
          'institution_eyebrow' => 'Институция',
          'institution_heading' => 'Кітапхана зияткерлік инфрақұрылым ретінде',
          'institution_lead' => 'Кітап сақтайтын орын ғана емес, университеттің академиялық жады қолжетімді және пайдалы болатын орта.',
          'journal_eyebrow' => 'Library Journal · 2026',
          'journal_heading' => 'Адамдар, зерттеулер, оқиғалар',
          'journal_lead' => 'Кітапхананың мұраны сақтап, жаңа зерттеулерді қалай қолдайтыны туралы редакциялық лента.',
          'read_story' => 'Материалды оқу',
          'view_agenda' => 'Күнтізбені ашу',
        ],
        'en' => [
          'collections_eyebrow' => 'Curator selection · Edition 01',
          'collections_note' => 'The holdings follow the university curriculum and are reviewed by library specialists.',
          'collection_metrics' => [
            ['value' => 'UDC', 'label' => 'Systematic indexing'],
            ['value' => '3', 'label' => 'Collection languages'],
            ['value' => '24/7', 'label' => 'Digital access'],
          ],
          'services_eyebrow' => 'Research concierge',
          'services_note' => 'From the first question to a finished bibliography: precise services for study and research.',
          'directory_eyebrow' => 'Library directory · 10 destinations',
          'institution_eyebrow' => 'Institution',
          'institution_heading' => 'The library as intellectual infrastructure',
          'institution_lead' => 'More than book storage: a place where the university’s academic memory becomes connected, accessible, and useful.',
          'journal_eyebrow' => 'Library Journal · 2026',
          'journal_heading' => 'People, research, events',
          'journal_lead' => 'An editorial stream about preserving heritage and enabling new research.',
          'read_story' => 'Read the story',
          'view_agenda' => 'View agenda',
        ],
      ][$lang];

      $libraryData = [
        'ru' => [
          'overview_kicker' => 'Фонд в цифрах',
          'overview_title' => 'Живая академическая коллекция',
          'overview_lead' => 'Фонд растёт вместе с образовательными программами и исследовательскими направлениями КазТБУ.',
          'growth_title' => 'Динамика использования фонда',
          'growth_note' => '+12,4% к прошлому семестру',
          'growth_period' => 'Февраль — июль 2026',
          'growth_primary' => 'Книговыдача',
          'growth_secondary' => 'Онлайн-просмотры',
          'growth_latest' => '1 284',
          'growth_months' => ['Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл'],
          'metrics' => [
            ['value' => '8 930', 'label' => 'Наименований в каталоге', 'note' => 'Печатный и цифровой фонд'],
            ['value' => '2 416', 'label' => 'Цифровых материалов', 'note' => 'Доступны удалённо'],
            ['value' => '126', 'label' => 'Журналов и периодики', 'note' => 'Научные и отраслевые'],
            ['value' => '3', 'label' => 'Языка коллекции', 'note' => 'Қазақша · Русский · English'],
          ],
          'categories_kicker' => 'Предметный навигатор',
          'categories_title' => 'Исследуйте знания по направлениям',
          'categories_lead' => 'Категории отражают профиль университета и помогают быстро перейти к релевантной литературе.',
          'categories' => [
            ['name' => 'Информационные технологии', 'count' => '1 840', 'share' => 86, 'icon' => 'terminal', 'query' => 'информационные технологии'],
            ['name' => 'Экономика и бизнес', 'count' => '1 560', 'share' => 74, 'icon' => 'monitoring', 'query' => 'экономика'],
            ['name' => 'Инженерия и технологии', 'count' => '1 490', 'share' => 69, 'icon' => 'precision_manufacturing', 'query' => 'инженерия'],
            ['name' => 'Туризм и сервис', 'count' => '980', 'share' => 48, 'icon' => 'travel_explore', 'query' => 'туризм'],
            ['name' => 'Дизайн и лёгкая промышленность', 'count' => '740', 'share' => 37, 'icon' => 'design_services', 'query' => 'дизайн'],
            ['name' => 'Социальные науки', 'count' => '1 120', 'share' => 56, 'icon' => 'public', 'query' => 'социальные науки'],
          ],
          'books_kicker' => 'Книжная полка',
          'books_title' => 'Новые поступления',
          'books_lead' => 'Издания, недавно добавленные в академический фонд.',
          'all_books' => 'Смотреть весь каталог',
          'books' => [
            ['title' => 'Архитектура информационных систем', 'author' => 'А. С. Омаров', 'year' => '2026', 'code' => '004.2', 'tone' => 'forest'],
            ['title' => 'Экономика устойчивого развития', 'author' => 'Л. К. Абдрахманова', 'year' => '2025', 'code' => '330.3', 'tone' => 'clay'],
            ['title' => 'Инженерные методы проектирования', 'author' => 'М. Т. Садыков', 'year' => '2026', 'code' => '62.001', 'tone' => 'ink'],
            ['title' => 'Сервис и управление качеством', 'author' => 'Е. Н. Ким', 'year' => '2025', 'code' => '338.4', 'tone' => 'sage'],
          ],
          'analytics_kicker' => 'Collection intelligence',
          'analytics_title' => 'Как используется библиотека',
          'analytics_lead' => 'Сводный взгляд на языки фонда, форматы и читательскую активность.',
          'languages' => 'Языки фонда',
          'formats' => 'Форматы материалов',
          'activity' => 'Обращения к каталогу',
          'month_note' => 'Последние 6 месяцев',
          'format_rows' => [['Печатные книги', 72], ['Электронные издания', 46], ['Научные статьи', 34], ['Архивные материалы', 22]],
          'language_rows' => [['Қазақша', '52%'], ['Русский', '31%'], ['English', '17%']],
        ],
        'kk' => [
          'overview_kicker' => 'Қор сандармен',
          'overview_title' => 'Дамып келе жатқан академиялық жинақ',
          'overview_lead' => 'Қор ҚазТБУ білім беру бағдарламалары мен зерттеу бағыттарымен бірге өседі.',
          'growth_title' => 'Қорды пайдалану динамикасы',
          'growth_note' => 'Өткен семестрмен салыстырғанда +12,4%',
          'growth_period' => 'Ақпан — шілде 2026',
          'growth_primary' => 'Кітап беру',
          'growth_secondary' => 'Онлайн қаралымдар',
          'growth_latest' => '1 284',
          'growth_months' => ['Ақп', 'Нау', 'Сәу', 'Мам', 'Мау', 'Шіл'],
          'metrics' => [
            ['value' => '8 930', 'label' => 'Каталогтағы атаулар', 'note' => 'Баспа және цифрлық қор'],
            ['value' => '2 416', 'label' => 'Цифрлық материал', 'note' => 'Қашықтан қолжетімді'],
            ['value' => '126', 'label' => 'Журнал және мерзімді басылым', 'note' => 'Ғылыми және салалық'],
            ['value' => '3', 'label' => 'Жинақ тілі', 'note' => 'Қазақша · Русский · English'],
          ],
          'categories_kicker' => 'Пәндік навигатор',
          'categories_title' => 'Білімді бағыттар бойынша зерттеңіз',
          'categories_lead' => 'Санаттар университет бейінін көрсетеді және қажетті әдебиетке тез өтуге көмектеседі.',
          'categories' => [
            ['name' => 'Ақпараттық технологиялар', 'count' => '1 840', 'share' => 86, 'icon' => 'terminal', 'query' => 'ақпараттық технологиялар'],
            ['name' => 'Экономика және бизнес', 'count' => '1 560', 'share' => 74, 'icon' => 'monitoring', 'query' => 'экономика'],
            ['name' => 'Инженерия және технологиялар', 'count' => '1 490', 'share' => 69, 'icon' => 'precision_manufacturing', 'query' => 'инженерия'],
            ['name' => 'Туризм және сервис', 'count' => '980', 'share' => 48, 'icon' => 'travel_explore', 'query' => 'туризм'],
            ['name' => 'Дизайн және жеңіл өнеркәсіп', 'count' => '740', 'share' => 37, 'icon' => 'design_services', 'query' => 'дизайн'],
            ['name' => 'Әлеуметтік ғылымдар', 'count' => '1 120', 'share' => 56, 'icon' => 'public', 'query' => 'әлеуметтік ғылымдар'],
          ],
          'books_kicker' => 'Кітап сөресі',
          'books_title' => 'Жаңа түсімдер',
          'books_lead' => 'Академиялық қорға жақында қосылған басылымдар.',
          'all_books' => 'Толық каталог',
          'books' => [
            ['title' => 'Ақпараттық жүйелер архитектурасы', 'author' => 'А. С. Омаров', 'year' => '2026', 'code' => '004.2', 'tone' => 'forest'],
            ['title' => 'Тұрақты даму экономикасы', 'author' => 'Л. К. Абдрахманова', 'year' => '2025', 'code' => '330.3', 'tone' => 'clay'],
            ['title' => 'Инженерлік жобалау әдістері', 'author' => 'М. Т. Садыков', 'year' => '2026', 'code' => '62.001', 'tone' => 'ink'],
            ['title' => 'Сервис және сапаны басқару', 'author' => 'Е. Н. Ким', 'year' => '2025', 'code' => '338.4', 'tone' => 'sage'],
          ],
          'analytics_kicker' => 'Collection intelligence',
          'analytics_title' => 'Кітапхана қалай пайдаланылады',
          'analytics_lead' => 'Қор тілдері, форматтар және оқырман белсенділігі туралы жалпы көрініс.',
          'languages' => 'Қор тілдері',
          'formats' => 'Материал форматтары',
          'activity' => 'Каталогқа жүгінулер',
          'month_note' => 'Соңғы 6 ай',
          'format_rows' => [['Баспа кітаптар', 72], ['Электрондық басылымдар', 46], ['Ғылыми мақалалар', 34], ['Мұрағат материалдары', 22]],
          'language_rows' => [['Қазақша', '52%'], ['Русский', '31%'], ['English', '17%']],
        ],
        'en' => [
          'overview_kicker' => 'The collection in numbers',
          'overview_title' => 'A living academic collection',
          'overview_lead' => 'The collection grows with KazUTB’s curricula and research priorities.',
          'growth_title' => 'Collection usage over time',
          'growth_note' => '+12.4% vs previous semester',
          'growth_period' => 'February — July 2026',
          'growth_primary' => 'Book loans',
          'growth_secondary' => 'Online views',
          'growth_latest' => '1,284',
          'growth_months' => ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
          'metrics' => [
            ['value' => '8,930', 'label' => 'Catalogued titles', 'note' => 'Print and digital holdings'],
            ['value' => '2,416', 'label' => 'Digital materials', 'note' => 'Available remotely'],
            ['value' => '126', 'label' => 'Journals and periodicals', 'note' => 'Scholarly and professional'],
            ['value' => '3', 'label' => 'Collection languages', 'note' => 'Қазақша · Русский · English'],
          ],
          'categories_kicker' => 'Subject navigator',
          'categories_title' => 'Explore knowledge by discipline',
          'categories_lead' => 'Categories follow the university profile and lead readers to relevant literature quickly.',
          'categories' => [
            ['name' => 'Information Technology', 'count' => '1,840', 'share' => 86, 'icon' => 'terminal', 'query' => 'information technology'],
            ['name' => 'Economics and Business', 'count' => '1,560', 'share' => 74, 'icon' => 'monitoring', 'query' => 'economics'],
            ['name' => 'Engineering and Technology', 'count' => '1,490', 'share' => 69, 'icon' => 'precision_manufacturing', 'query' => 'engineering'],
            ['name' => 'Tourism and Service', 'count' => '980', 'share' => 48, 'icon' => 'travel_explore', 'query' => 'tourism'],
            ['name' => 'Design and Light Industry', 'count' => '740', 'share' => 37, 'icon' => 'design_services', 'query' => 'design'],
            ['name' => 'Social Sciences', 'count' => '1,120', 'share' => 56, 'icon' => 'public', 'query' => 'social sciences'],
          ],
          'books_kicker' => 'The bookshelf',
          'books_title' => 'New arrivals',
          'books_lead' => 'Recently added titles from the academic collection.',
          'all_books' => 'View full catalog',
          'books' => [
            ['title' => 'Information Systems Architecture', 'author' => 'A. S. Omarov', 'year' => '2026', 'code' => '004.2', 'tone' => 'forest'],
            ['title' => 'Economics of Sustainable Development', 'author' => 'L. K. Abdrakhmanova', 'year' => '2025', 'code' => '330.3', 'tone' => 'clay'],
            ['title' => 'Engineering Design Methods', 'author' => 'M. T. Sadykov', 'year' => '2026', 'code' => '62.001', 'tone' => 'ink'],
            ['title' => 'Service and Quality Management', 'author' => 'E. N. Kim', 'year' => '2025', 'code' => '338.4', 'tone' => 'sage'],
          ],
          'analytics_kicker' => 'Collection intelligence',
          'analytics_title' => 'How the library is used',
          'analytics_lead' => 'A concise view of collection languages, formats, and reader activity.',
          'languages' => 'Collection languages',
          'formats' => 'Material formats',
          'activity' => 'Catalog activity',
          'month_note' => 'Last 6 months',
          'format_rows' => [['Print books', 72], ['Digital editions', 46], ['Research articles', 34], ['Archive materials', 22]],
          'language_rows' => [['Kazakh', '52%'], ['Russian', '31%'], ['English', '17%']],
        ],
      ][$lang];
@endphp

@section('title', $copy['title'])
@section('body_class', 'homepage')

@section('head')
<style>
.homepage .page-main {
    margin-top: 0;
}
[data-section="homepage-canonical-page"] {
    --homepage-gutter: clamp(18px, 3vw, 32px);
}
[data-section="homepage-canonical-page"] > section,
[data-section="homepage-canonical-page"] > div:not(.sr-only) {
    width: 100%;
    max-width: none;
    margin-inline: auto;
    padding-inline: var(--homepage-gutter);
    box-sizing: border-box;
}
[data-section="homepage-canonical-hero"] {
    min-height: 52vh;
    min-height: 52svh;
    position: relative;
    isolation: isolate;
    overflow: hidden;
    color: #fff;
    background: #0b1830;
}
.homepage-hero__image {
    position: absolute;
    inset: 0;
    z-index: -4;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 48%;
    filter: saturate(1.12) contrast(1.05) brightness(1.06);
    transform: scale(1.025);
    animation: homepageHeroImageIn 1.4s cubic-bezier(.22, 1, .36, 1) both;
}
.homepage-hero__overlay {
    position: absolute;
    inset: 0;
    z-index: -3;
    background:
      linear-gradient(95deg, rgba(3, 18, 28, .92) 0%, rgba(7, 41, 55, .72) 25%, rgba(8, 57, 68, .28) 52%, rgba(8, 57, 68, .08) 76%, rgba(8, 57, 68, 0) 100%),
      linear-gradient(180deg, rgba(6, 13, 20, .64) 0%, rgba(6, 13, 20, .18) 40%, rgba(6, 13, 20, .38) 100%);
}
.homepage-hero__ambient {
    position: absolute;
    inset: 0;
    z-index: -2;
    opacity: .8;
    background:
      radial-gradient(ellipse 46% 42% at 12% 35%, rgba(232, 160, 32, .15), transparent 72%),
      radial-gradient(ellipse 38% 58% at 88% 72%, rgba(0, 172, 172, .15), transparent 72%),
      linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
    background-size: auto, auto, 56px 56px, 56px 56px;
    background-position: 0 0, 0 0, 0 28px, 28px 0;
    mask-image: linear-gradient(to bottom, black, transparent 86%);
}
.homepage-hero__content {
    width: 100%;
    max-width: none;
    margin: 0;
    min-height: 52vh;
    min-height: 52svh;
    padding: clamp(120px, 14vh, 150px) clamp(16px, 3vw, 40px) clamp(34px, 5vh, 48px);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
    gap: clamp(24px, 4vw, 48px);
    align-items: end;
}
.homepage-hero__copy {
    max-width: 980px;
    position: relative;
    padding: 28px 24px 24px;
    margin-left: 0;
    border-radius: 8px;
    background: transparent;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    box-shadow: 0 14px 40px rgba(0, 0, 0, .12);
}
.homepage-hero__title {
    max-width: 760px;
    margin-top: 18px;
    font-family: "Literata", serif;
    font-size: clamp(42px, 5vw, 68px);
    font-weight: 700;
    letter-spacing: -.045em;
    line-height: .88;
    text-wrap: balance;
    text-shadow: 0 4px 40px rgba(0, 0, 0, .38);
}
.homepage-hero__title em {
    color: #f3bd46;
    font-weight: 500;
}
.homepage-hero__lead {
    max-width: 560px;
    margin-top: 18px;
    color: rgba(255, 255, 255, .78);
    font-size: clamp(16px, 1.3vw, 19px);
    line-height: 1.72;
}
.homepage-hero__search {
    width: min(100%, 650px);
    margin-top: 22px;
    display: flex;
    align-items: center;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .25);
    background: rgba(255, 255, 255, .96);
    box-shadow: 0 22px 60px rgba(0, 0, 0, .28);
    transition: transform .3s ease, box-shadow .3s ease;
}
.homepage-hero__search:focus-within {
    transform: translateY(-2px);
    box-shadow: 0 28px 72px rgba(0, 0, 0, .36);
}
.homepage-hero__search input {
    min-width: 0;
    flex: 1;
    border: 0;
    background: transparent;
    color: #102945;
    padding: 18px 12px;
    outline: 0;
    box-shadow: none;
}
.homepage-hero__search button {
    align-self: stretch;
    padding: 0 28px;
    color: #102945;
    background: linear-gradient(135deg, #f3bd46, #e8a020);
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .04em;
    transition: filter .2s ease;
}
.homepage-hero__search button:hover {
    filter: brightness(1.08);
}
.homepage-hero__book {
    justify-self: end;
    align-self: end;
    width: min(100%, 360px);
    aspect-ratio: 0.66 / 1;
    position: relative;
    padding: 22px 24px 20px 34px;
    background:
      linear-gradient(135deg, rgba(255,255,255,.08), transparent 28%),
      radial-gradient(circle at 18% 18%, rgba(255,255,255,.14), transparent 22%),
      radial-gradient(circle at 85% 78%, rgba(255,255,255,.06), transparent 28%),
      linear-gradient(180deg, #a86d51 0%, #8c5a42 42%, #744934 100%);
    border-radius: 10px 12px 12px 10px;
    box-shadow: 0 28px 70px rgba(0, 0, 0, .28);
    overflow: hidden;
}
.homepage-hero__book::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 20px;
    background:
      linear-gradient(90deg, rgba(62, 35, 23, .98), rgba(94, 58, 42, .96) 52%, rgba(132, 83, 60, .92));
    border-radius: 10px 0 0 10px;
    box-shadow:
      inset -1px 0 0 rgba(255, 233, 221, .18),
      inset -5px 0 0 rgba(40, 24, 15, .18),
      1px 0 0 rgba(58, 34, 22, .26);
    pointer-events: none;
}
.homepage-hero__book::after {
    content: "";
    position: absolute;
    inset: 0;
    border: 1px solid rgba(255, 230, 210, .18);
    border-radius: 10px;
    pointer-events: none;
}
.homepage-hero__book-grain {
    position: absolute;
    inset: 0;
    opacity: .14;
    mix-blend-mode: soft-light;
    background-image:
      radial-gradient(rgba(255,255,255,.22) 0.7px, transparent 0.8px),
      radial-gradient(rgba(255,255,255,.12) 0.6px, transparent 0.7px);
    background-size: 7px 7px, 11px 11px;
    background-position: 0 0, 3px 5px;
    pointer-events: none;
}
.homepage-hero__book-shadow {
    position: absolute;
    inset: 16px 16px 16px auto;
    width: 1px;
    background: rgba(255, 235, 220, .18);
    box-shadow:
      -6px 0 0 rgba(255, 235, 220, .08),
      -12px 0 0 rgba(58, 34, 22, .16);
    pointer-events: none;
}
.homepage-hero__book-badge {
    width: 58px;
    height: 58px;
    margin-top: 44px;
    border-radius: 12px;
    border: 1px solid rgba(255, 240, 230, .42);
    background: rgba(255, 246, 240, .08);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(58, 34, 22, .18);
}
.homepage-hero__book-badge img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 9px;
}
.homepage-hero__book-title {
    margin-top: 22px;
    color: #fff7f1;
    font-family: "Literata", serif;
    font-size: clamp(22px, 2.35vw, 30px);
    font-weight: 800;
    line-height: .95;
    letter-spacing: -.035em;
    text-wrap: balance;
}
.homepage-hero__book-title small {
    display: block;
    margin-top: 12px;
    color: rgba(255, 233, 221, .82);
    font-family: "Google Sans", sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.homepage-hero__book-ring {
    position: absolute;
    right: -46px;
    bottom: -48px;
    width: 160px;
    height: 160px;
    border: 1px solid rgba(255, 236, 226, .12);
    border-radius: 50%;
}
.homepage-hero__book-ring::before {
    content: "";
    position: absolute;
    inset: 18px;
    border: 1px solid rgba(255, 236, 226, .1);
    border-radius: 50%;
}
.homepage-hero__scroll {
    position: absolute;
    z-index: 2;
    bottom: 28px;
    left: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: rgba(255, 255, 255, .5);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .2em;
    text-transform: uppercase;
    transform: translateX(-50%);
}
.homepage-hero__bridge {
    position: relative;
    z-index: 3;
    margin-top: -22px;
    margin-bottom: 16px;
    padding: 0 32px 22px;
}
.homepage-hero__bridge-inner {
    width: 100%;
    max-width: none;
    margin: 0 auto;
    padding: 26px 24px 26px;
    background: rgba(255, 255, 255, .92);
    border: 1px solid rgba(16, 41, 69, .08);
    box-shadow: 0 26px 70px rgba(11, 24, 48, .12);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
.homepage-hero__bridge-head {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
}
.homepage-hero__bridge-kicker {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #315646;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.homepage-hero__bridge-kicker::before {
    content: "";
    width: 28px;
    height: 1px;
    background: #b38b4d;
}
.homepage-hero__bridge h2 {
    margin: 10px 0 0;
    color: #102945;
    font-family: "Literata", serif;
    font-size: clamp(28px, 3.4vw, 44px);
    line-height: 1;
}
.homepage-hero__bridge p {
    max-width: 430px;
    margin: 0;
    color: rgba(37, 49, 45, .68);
    line-height: 1.7;
}
.homepage-hero__bridge-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.homepage-hero__bridge-card {
    min-height: 148px;
    padding: 18px 18px 20px;
    background: #f8f7f2;
    border: 1px solid rgba(16, 41, 69, .08);
}
.homepage-hero__bridge-card strong {
    display: block;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 34px;
    line-height: 1;
}
.homepage-hero__bridge-card b {
    display: block;
    margin-top: 10px;
    color: #102945;
    font-size: 13px;
    line-height: 1.25;
}
.homepage-hero__bridge-card small {
    display: block;
    margin-top: 6px;
    color: rgba(37, 49, 45, .56);
    font-size: 11px;
    line-height: 1.45;
}
.homepage-hero__scroll::after {
    content: "";
    width: 1px;
    height: 28px;
    background: linear-gradient(#e8a020, transparent);
    animation: homepageScrollPulse 1.8s ease-in-out infinite;
}
@keyframes homepageHeroImageIn {
    from { opacity: 0; transform: scale(1.08); }
    to { opacity: 1; transform: scale(1.025); }
}
@keyframes homepageHeroCardIn {
    from { opacity: 0; transform: translateY(28px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes homepageScrollPulse {
    0%, 100% { opacity: .45; transform: scaleY(.75); transform-origin: top; }
    50% { opacity: 1; transform: scaleY(1); transform-origin: top; }
}
.homepage-canonical__bento-img {
    position: absolute; inset: 0; width: 100%; height: 100%;
    object-fit: cover; transition: transform .7s; opacity: 1;
}
.homepage-canonical__bento-tile:hover .homepage-canonical__bento-img { transform: scale(1.05); }
.homepage-canonical__bento-tile:hover { transform: translateY(-4px); }
[data-section="homepage-canonical-page"] {
    overflow: hidden;
    background: #f5f3ee;
}
[data-section="homepage-canonical-page"] h2,
[data-section="homepage-canonical-page"] h3,
[data-section="homepage-canonical-page"] h4 {
    text-wrap: balance;
}
[data-section="homepage-canonical-gateway"],
[data-section="homepage-canonical-hub-slices"],
[data-section="homepage-canonical-updates"] {
    width: 100% !important;
    max-width: none !important;
    margin-inline: auto !important;
}
[data-section="homepage-canonical-gateway"] {
    padding: 132px var(--homepage-gutter) 142px !important;
}
[data-section="homepage-canonical-gateway"] > div:first-child {
    padding-bottom: 34px;
    border-bottom: 1px solid rgba(16, 41, 69, .14);
}
[data-section="homepage-canonical-gateway"] > div:last-child {
    counter-reset: gateway;
    grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    gap: 0 !important;
    border-top: 1px solid rgba(16, 41, 69, .16);
}
.homepage-canonical__gateway-card {
    counter-increment: gateway;
    min-height: 170px;
    padding: 30px 20px !important;
    border: 0 !important;
    border-right: 1px solid rgba(16, 41, 69, .13) !important;
    border-bottom: 1px solid rgba(16, 41, 69, .13) !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
}
.homepage-canonical__gateway-card::before {
    content: "0" counter(gateway);
    position: absolute;
    top: 14px;
    right: 15px;
    color: rgba(16, 41, 69, .25);
    font-family: "Literata", serif;
    font-size: 12px;
}
.homepage-canonical__gateway-card:hover {
    z-index: 2;
    color: #fff;
    background: #102945 !important;
    transform: translateY(-4px);
}
.homepage-canonical__gateway-card:hover span {
    color: #fff !important;
}
.homepage-canonical__gateway-card > span:first-of-type,
.homepage-canonical__gateway-card > span:nth-of-type(2) {
    display: none;
}
.homepage-canonical__gateway-card .h-12 {
    width: 40px !important;
    height: 40px !important;
    border-radius: 0 !important;
    color: #b57900 !important;
    background: transparent !important;
    box-shadow: none !important;
}

[data-section="homepage-canonical-hub-slices"] {
    padding: 132px var(--homepage-gutter) 150px !important;
    background: #fff;
    box-shadow: 50vw 0 #fff, -50vw 0 #fff;
}
[data-section="homepage-canonical-hub-slices"] > div:last-child {
    gap: 18px !important;
}
.homepage-canonical__hub-card {
    border: 0 !important;
    border-radius: 0 !important;
    background: #f5f3ee !important;
    box-shadow: none !important;
}
.homepage-canonical__hub-card:nth-child(even) {
    transform: translateY(34px);
}
.homepage-canonical__hub-card > div:first-child {
    height: 240px !important;
}
.homepage-canonical__hub-card img {
    filter: saturate(.7);
    transition: transform .65s ease, filter .4s ease;
}
.homepage-canonical__hub-card:hover img {
    filter: saturate(1);
    transform: scale(1.04);
}
.homepage-canonical__hub-card > div:last-child {
    padding: 30px !important;
}

[data-section="homepage-canonical-updates"] {
    padding: 198px var(--homepage-gutter) 160px !important;
}
[data-section="homepage-canonical-updates"] > div:last-child {
    grid-template-columns: 1.12fr .88fr !important;
    gap: 18px !important;
}
.homepage-canonical__update-card {
    border: 0 !important;
    border-radius: 0 !important;
    background: #102945 !important;
    box-shadow: 0 26px 70px rgba(11, 24, 48, .14) !important;
}
.homepage-canonical__update-card:nth-child(2) {
    background: #e8a020 !important;
}
.homepage-canonical__update-card > div:first-child {
    height: 320px !important;
}
.homepage-canonical__update-card > div:last-child {
    padding: 38px !important;
}
.homepage-canonical__update-card h3,
.homepage-canonical__update-card p,
.homepage-canonical__update-card span {
    color: #fff !important;
}
.homepage-canonical__update-card:nth-child(2) h3,
.homepage-canonical__update-card:nth-child(2) p,
.homepage-canonical__update-card:nth-child(2) span {
    color: #102945 !important;
}
.homepage-canonical__update-card img {
    filter: saturate(.72);
    transition: transform .7s ease;
}
.homepage-canonical__update-card:hover img {
    transform: scale(1.04);
}

/* The hero card resembles a catalog passport, not the university promo card. */
.homepage-hero__card {
    position: relative;
    color: #102945;
    background:
      linear-gradient(rgba(16, 41, 69, .055) 1px, transparent 1px),
      #f4efe3;
    background-size: 100% 38px;
    border: 1px solid rgba(255, 255, 255, .62);
    box-shadow: 0 34px 90px rgba(0, 0, 0, .35);
}
.homepage-hero__card::before {
    content: "KAZUTB / DIGITAL HOLDINGS";
    position: absolute;
    top: 18px;
    right: 20px;
    color: rgba(16, 41, 69, .38);
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .16em;
}
.homepage-hero__card h2,
.homepage-hero__card p {
    color: #102945 !important;
}
.homepage-hero__card > p:nth-of-type(2) {
    color: rgba(16, 41, 69, .64) !important;
}
.homepage-hero__stats {
    border-top-color: rgba(16, 41, 69, .16);
}
.homepage-hero__stat + .homepage-hero__stat {
    border-left-color: rgba(16, 41, 69, .16);
}
.homepage-hero__stat strong {
    color: #102945;
}
.homepage-hero__stat span {
    color: rgba(16, 41, 69, .54);
}
.homepage-hero__card > a {
    color: #102945 !important;
}
@media (max-width: 1023px) {
    .homepage .page-main {
        margin-top: 0;
    }
    [data-section="homepage-canonical-hero"] {
        height: auto;
        min-height: 100svh;
    }
    .homepage-hero__content {
        height: auto;
        min-height: 100svh;
        padding: 154px clamp(16px, 3vw, 40px) 80px;
        grid-template-columns: 1fr;
    }
    .homepage-hero__copy {
        padding: 22px 18px 20px;
    }
    .homepage-hero__scroll {
        display: none;
    }
    .homepage-hero__bridge {
        margin-top: -18px;
        margin-bottom: 14px;
        padding: 0 24px 12px;
    }
    .homepage-hero__bridge-inner {
        padding: 18px 20px 20px;
    }
    .homepage-hero__bridge-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    [data-section="homepage-canonical-gateway"],
    [data-section="homepage-canonical-hub-slices"],
    [data-section="homepage-canonical-updates"] {
        padding: 88px 24px 96px !important;
    }
    [data-section="homepage-canonical-gateway"] > div:last-child {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    [data-section="homepage-canonical-hub-slices"] > div:last-child {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    .homepage-canonical__hub-card:nth-child(even) {
        transform: none;
    }
}
@media (max-width: 640px) {
    .homepage-hero__content {
        padding: 72px clamp(16px, 3vw, 40px) 64px;
        grid-template-columns: 1fr;
    }
    .homepage-hero__title {
        font-size: clamp(42px, 13vw, 58px);
    }
    [data-section="homepage-canonical-gateway"],
    [data-section="homepage-canonical-hub-slices"],
    [data-section="homepage-canonical-updates"] {
        padding: 72px 18px 78px !important;
    }
    [data-section="homepage-canonical-gateway"] > div:first-child,
    [data-section="homepage-canonical-hub-slices"] > div:first-child,
    [data-section="homepage-canonical-updates"] > div:first-child {
        margin-bottom: 34px !important;
    }
    [data-section="homepage-canonical-gateway"] > div:last-child,
    [data-section="homepage-canonical-hub-slices"] > div:last-child,
    [data-section="homepage-canonical-updates"] > div:last-child {
        grid-template-columns: 1fr !important;
    }
    .homepage-hero__bridge-head {
        flex-direction: column;
        align-items: flex-start;
    }
    .homepage-hero__book {
        display: none;
    }
    .homepage-canonical__gateway-card {
        min-height: 140px;
    }
    .homepage-canonical__update-card > div:first-child {
        height: 240px !important;
    }
    .homepage-canonical__update-card > div:last-child {
        padding: 28px !important;
    }
}

/* Editorial library system v2 */
.library-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    color: #9a6900;
    font-size: 10px;
    font-weight: 850;
    letter-spacing: .2em;
    text-transform: uppercase;
}
.library-eyebrow::before {
    content: "";
    width: 28px;
    height: 1px;
    background: currentColor;
}
.library-section-head {
    display: grid !important;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, .55fr);
    align-items: end !important;
    gap: 80px;
    margin: 0 0 54px !important;
    padding: 0 0 34px !important;
    border-bottom: 1px solid rgba(16, 41, 69, .16);
}
.library-section-head h2 {
    max-width: 800px;
    margin-top: 16px !important;
}
.library-section-note {
    color: rgba(16, 41, 69, .64);
    font-size: 14px;
    line-height: 1.8;
}
.library-section-note a {
    display: inline-flex;
    margin-top: 18px;
    color: #102945;
    border-bottom: 1px solid #d69a18;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.library-collections {
    max-width: 1370px !important;
    padding: 122px 32px 136px !important;
}
.library-collection-stage {
    height: 680px !important;
    display: grid !important;
    grid-template-columns: minmax(0, 1.55fr) minmax(340px, .72fr) !important;
    grid-template-rows: 1fr !important;
    gap: 18px !important;
}
.library-collection-feature {
    position: relative;
    display: block;
    overflow: hidden;
    color: #fff;
    background: #102945;
}
.library-collection-feature > img,
.library-institution__feature > img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(.72) contrast(1.04);
    transition: transform .9s cubic-bezier(.22, 1, .36, 1), filter .5s ease;
}
.library-image-wash {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(11,24,48,.08), rgba(11,24,48,.88));
}
.library-collection-feature:hover > img,
.library-institution__feature:hover > img {
    transform: scale(1.035);
    filter: saturate(.95) contrast(1.04);
}
.library-folio {
    position: absolute;
    top: 26px;
    left: 28px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,.45);
    color: rgba(255,255,255,.72);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.library-collection-feature > div {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 1;
    max-width: 700px;
    padding: 44px;
}
.library-collection-feature > div > span,
.library-institution__feature small,
.library-journal small {
    color: #f3bd46;
    font-size: 9px;
    font-style: normal;
    font-weight: 850;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.library-collection-feature h3 {
    margin-top: 12px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: clamp(36px, 4vw, 56px);
    font-weight: 650;
    letter-spacing: -.035em;
    line-height: 1;
}
.library-collection-feature p {
    max-width: 570px;
    margin-top: 18px;
    color: rgba(255,255,255,.68);
    font-size: 14px;
    line-height: 1.7;
}
.library-collection-feature strong,
.library-institution__feature strong,
.library-journal strong {
    display: inline-block;
    margin-top: 24px;
    padding-bottom: 5px;
    border-bottom: 1px solid #e8a020;
    font-size: 10px;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.library-collection-minor {
    display: grid;
    grid-template-rows: 1fr 1fr;
    gap: 18px;
}
.library-collection-row {
    display: grid;
    grid-template-columns: 44% 1fr;
    min-height: 0;
    overflow: hidden;
    background: #fff;
    transition: transform .35s ease, box-shadow .35s ease;
}
.library-collection-row:hover {
    z-index: 2;
    transform: translateX(-8px);
    box-shadow: 20px 30px 70px rgba(11,24,48,.16);
}
.library-collection-row__image {
    overflow: hidden;
}
.library-collection-row__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(.65);
    transition: transform .65s ease, filter .35s ease;
}
.library-collection-row:hover img {
    transform: scale(1.05);
    filter: saturate(.95);
}
.library-collection-row__copy {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 30px 26px;
}
.library-collection-row__copy small {
    color: #9a6900;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .16em;
}
.library-collection-row__copy strong {
    margin-top: 13px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 25px;
    line-height: 1.06;
}
.library-collection-row__copy em {
    margin-top: 12px;
    color: rgba(16,41,69,.56);
    font-size: 11px;
    font-style: normal;
    line-height: 1.55;
}
.library-collection-row__copy b {
    position: absolute;
    right: 18px;
    bottom: 16px;
    color: #9a6900;
    font-weight: 500;
}
.library-collection-ledger {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    margin-top: 18px;
    border-top: 1px solid rgba(16,41,69,.15);
    border-bottom: 1px solid rgba(16,41,69,.15);
}
.library-collection-ledger > div {
    display: flex;
    align-items: baseline;
    gap: 16px;
    padding: 24px 28px;
    border-right: 1px solid rgba(16,41,69,.15);
}
.library-collection-ledger > div:last-child { border-right: 0; }
.library-collection-ledger strong {
    color: #102945;
    font-family: "Literata", serif;
    font-size: 28px;
}
.library-collection-ledger span {
    color: rgba(16,41,69,.5);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.library-services {
    margin: 0 !important;
    padding: 0 !important;
    background: #0c2037 !important;
}
.library-services__inner {
    max-width: 1370px !important;
    margin: 0 auto;
    padding: 132px 32px 140px !important;
    display: grid;
    grid-template-columns: minmax(280px, .7fr) minmax(0, 1.3fr);
    gap: clamp(70px, 9vw, 140px);
}
.library-services__intro {
    position: sticky;
    top: 180px;
    align-self: start;
}
.library-services__intro h2 {
    margin-top: 18px;
    color: #fff !important;
    font-family: "Literata", serif;
    font-size: clamp(44px, 5vw, 66px) !important;
    font-weight: 650;
    letter-spacing: -.04em;
    line-height: .98;
}
.library-services__intro > p {
    max-width: 440px;
    margin-top: 24px;
    color: rgba(255,255,255,.58) !important;
    font-size: 15px;
    line-height: 1.8;
}
.library-services__seal {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    margin-top: 38px;
    color: #f3bd46;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
}
.library-services__seal .material-symbols-outlined { font-size: 18px; }
.library-services__list {
    display: block !important;
    border-top: 1px solid rgba(255,255,255,.16) !important;
    border-bottom: 0 !important;
}
.library-service {
    min-height: 220px;
    display: grid;
    grid-template-columns: 42px 54px minmax(0, 1fr) 34px;
    align-items: center;
    gap: 24px;
    padding: 32px 12px !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(255,255,255,.16) !important;
    background: transparent !important;
    transition: padding .35s ease, background .35s ease;
}
.library-service:hover {
    padding-inline: 28px !important;
    background: rgba(255,255,255,.055) !important;
    transform: none !important;
}
.library-service__number {
    align-self: start;
    padding-top: 8px;
    color: rgba(255,255,255,.3);
    font-family: "Literata", serif;
    font-size: 13px;
}
.library-service__icon {
    width: 54px;
    height: 54px;
    display: grid !important;
    place-items: center;
    color: #102945;
    background: #e8a020;
}
.library-service h3 {
    color: #fff !important;
    font-family: "Literata", serif;
    font-size: 28px !important;
}
.library-service > div {
    min-height: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}
.library-service p {
    max-width: 560px;
    margin-top: 12px;
    color: rgba(255,255,255,.56) !important;
    font-size: 13px;
    line-height: 1.75;
}
.library-service > a {
    color: #f3bd46 !important;
    font-size: 22px;
    transition: transform .25s ease;
}
.library-service:hover > a { transform: translate(3px, -3px); }

.library-directory {
    max-width: 1370px !important;
    padding: 132px 32px 146px !important;
    display: grid;
    grid-template-columns: minmax(280px, .58fr) minmax(0, 1.42fr);
    gap: clamp(70px, 9vw, 150px);
}
.library-directory__intro {
    position: sticky;
    top: 180px;
    align-self: start;
    padding: 0 !important;
    border: 0 !important;
}
.library-directory__intro h2 {
    margin-top: 18px !important;
}
.library-directory__intro p {
    max-width: 420px;
    margin-top: 24px;
    color: rgba(16,41,69,.58);
    font-size: 14px;
    line-height: 1.8;
}
.library-directory__list {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    align-self: start;
    border-top: 1px solid rgba(16,41,69,.16);
}
.library-directory__item {
    min-height: 132px;
    display: grid;
    grid-template-columns: 28px 36px 1fr 18px;
    align-items: center;
    gap: 17px;
    padding: 22px 20px;
    border-right: 1px solid rgba(16,41,69,.14);
    border-bottom: 1px solid rgba(16,41,69,.14);
    transition: color .28s ease, background .28s ease, transform .28s ease;
}
.library-directory__item:hover {
    z-index: 2;
    color: #fff;
    background: #102945;
    transform: translateY(-3px);
}
.library-directory__number {
    color: rgba(16,41,69,.35);
    font-family: "Literata", serif;
    font-size: 12px;
}
.library-directory__item:hover .library-directory__number { color: rgba(255,255,255,.38); }
.library-directory__item > .material-symbols-outlined {
    color: #9a6900;
    font-size: 24px;
}
.library-directory__item small {
    display: block;
    color: rgba(16,41,69,.45);
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.library-directory__item:hover small { color: rgba(255,255,255,.5); }
.library-directory__item strong {
    display: block;
    margin-top: 6px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 19px;
}
.library-directory__item:hover strong { color: #fff; }
.library-directory__item > b { color: #9a6900; font-weight: 500; }

.library-institution {
    max-width: 1370px !important;
    padding: 130px 32px 154px !important;
}
.library-institution__layout {
    display: grid !important;
    grid-template-columns: minmax(0, 1.18fr) minmax(360px, .82fr) !important;
    gap: 18px !important;
}
.library-institution__feature {
    min-height: 720px;
    position: relative;
    display: block;
    overflow: hidden;
    color: #fff;
    background: #102945;
}
.library-institution__feature > div {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 1;
    max-width: 700px;
    padding: 46px;
}
.library-institution__feature h3 {
    margin-top: 13px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: clamp(38px, 4vw, 56px);
    line-height: 1;
}
.library-institution__feature p {
    margin-top: 18px;
    color: rgba(255,255,255,.65);
    font-size: 14px;
    line-height: 1.75;
}
.library-institution__index {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    border-top: 1px solid rgba(16,41,69,.16);
}
.library-institution__index > a {
    display: grid;
    grid-template-columns: 28px 40px 1fr 18px;
    align-items: start;
    gap: 18px;
    padding: 30px 20px;
    border-bottom: 1px solid rgba(16,41,69,.16);
    transition: padding .3s ease, background .3s ease;
}
.library-institution__index > a:hover {
    padding-inline: 30px;
    background: #f5f3ee;
}
.library-institution__index > a > span:first-child {
    color: rgba(16,41,69,.3);
    font-family: "Literata", serif;
    font-size: 12px;
}
.library-institution__index .material-symbols-outlined {
    color: #9a6900;
    font-size: 24px;
}
.library-institution__index small {
    color: #9a6900;
    font-size: 8px;
    font-weight: 850;
    letter-spacing: .14em;
    text-transform: uppercase;
}
.library-institution__index h3 {
    margin-top: 7px;
    color: #102945;
    font-family: "Literata", serif;
    font-size: 25px;
    line-height: 1.05;
}
.library-institution__index p {
    margin-top: 10px;
    color: rgba(16,41,69,.52);
    font-size: 11px;
    line-height: 1.6;
}
.library-institution__index b { color: #9a6900; font-weight: 500; }

.library-journal {
    max-width: 1370px !important;
    padding: 132px 32px 160px !important;
}
.library-journal__layout {
    display: grid !important;
    grid-template-columns: minmax(0, 1.35fr) minmax(340px, .65fr) !important;
    gap: 18px !important;
}
.library-journal__feature {
    min-height: 560px;
    display: grid;
    grid-template-columns: 56% 44%;
    overflow: hidden;
    background: #102945;
}
.library-journal__feature > div {
    position: relative;
    overflow: hidden;
}
.library-journal__feature img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: saturate(.7);
    transition: transform .8s ease;
}
.library-journal__feature:hover img { transform: scale(1.035); }
.library-journal__feature > div > span {
    position: absolute;
    top: 22px;
    left: 22px;
    padding: 9px 12px;
    color: #102945;
    background: #f4efe3;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.library-journal__feature article {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 44px;
    color: #fff;
}
.library-journal h3 {
    margin-top: 14px;
    color: #fff;
    font-family: "Literata", serif;
    font-size: clamp(32px, 3.4vw, 48px);
    line-height: 1;
}
.library-journal__feature p,
.library-journal__event p {
    margin-top: 20px;
    color: rgba(255,255,255,.6);
    font-size: 13px;
    line-height: 1.75;
}
.library-journal__event {
    min-height: 560px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 44px;
    color: #102945;
    background:
      linear-gradient(rgba(16,41,69,.055) 1px, transparent 1px),
      #e8a020;
    background-size: 100% 42px;
    transition: transform .35s ease, box-shadow .35s ease;
}
.library-journal__event:hover {
    transform: translateY(-7px);
    box-shadow: 0 34px 80px rgba(11,24,48,.18);
}
.library-journal__event > .material-symbols-outlined {
    margin-bottom: auto;
    font-size: 46px;
}
.library-journal__event small { color: rgba(16,41,69,.62); }
.library-journal__event h3 { color: #102945; }
.library-journal__event p { color: rgba(16,41,69,.66); }
.library-journal__event strong { border-bottom-color: #102945; }

@media (max-width: 1023px) {
    .library-section-head,
    .library-services__inner,
    .library-directory {
        grid-template-columns: 1fr !important;
        gap: 34px;
    }
    .library-collection-stage {
        height: auto !important;
        grid-template-columns: 1fr !important;
    }
    .library-collection-feature { min-height: 600px; }
    .library-collection-minor {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 310px;
    }
    .library-services__intro,
    .library-directory__intro {
        position: static;
    }
    .library-institution__layout,
    .library-journal__layout {
        grid-template-columns: 1fr !important;
    }
    .library-institution__feature { min-height: 620px; }
}
@media (max-width: 640px) {
    .library-collections,
    .library-services__inner,
    .library-directory,
    .library-institution,
    .library-journal {
        padding: 78px 18px 88px !important;
    }
    .library-section-head {
        margin-bottom: 36px !important;
    }
    .library-collection-feature { min-height: 520px; }
    .library-collection-feature > div,
    .library-institution__feature > div {
        padding: 28px;
    }
    .library-collection-minor {
        grid-template-columns: 1fr;
        grid-template-rows: 270px 270px;
    }
    .library-collection-ledger {
        grid-template-columns: 1fr;
    }
    .library-collection-ledger > div {
        border-right: 0;
        border-bottom: 1px solid rgba(16,41,69,.15);
    }
    .library-service {
        grid-template-columns: 30px 44px 1fr;
        min-height: 190px;
        gap: 14px;
    }
    .library-service > a { display: none; }
    .library-service__icon { width: 44px; height: 44px; font-size: 20px; }
    .library-directory__list { grid-template-columns: 1fr !important; }
    .library-institution__feature { min-height: 540px; }
    .library-institution__index > a {
        grid-template-columns: 24px 32px 1fr;
        padding-inline: 8px;
    }
    .library-institution__index b { display: none; }
    .library-journal__feature {
        grid-template-columns: 1fr;
    }
    .library-journal__feature > div { min-height: 300px; }
    .library-journal__feature article,
    .library-journal__event { padding: 30px; }
    .library-journal__event { min-height: 480px; }
}

/* Independent library palette: turquoise, warm beige, and white */
[data-section="homepage-canonical-page"] {
    --library-teal-deep: #343936;
    --library-teal: #09bab2;
    --library-turquoise: #09bab2;
    --library-ink: #3e403a;
    --library-beige: #ffffff;
    --library-sand: #ffffff;
    --library-ivory: #ffffff;
    --library-white: #ffffff;
    background: var(--library-white);
}
[data-section="homepage-canonical-hero"] {
    background: var(--library-teal-deep);
}
.homepage-hero__overlay {
    background:
      linear-gradient(95deg, rgba(2, 22, 21, .82) 0%, rgba(4, 56, 53, .56) 34%, rgba(4, 64, 61, .18) 64%, rgba(4, 64, 61, .06) 100%),
      linear-gradient(180deg, rgba(12, 18, 18, .58) 0%, rgba(12, 18, 18, .18) 40%, rgba(12, 18, 18, .62) 100%);
}
.homepage-hero__ambient {
    background:
      radial-gradient(ellipse 46% 42% at 12% 35%, rgba(9, 186, 178, .08), transparent 72%),
      radial-gradient(ellipse 38% 58% at 88% 72%, rgba(255, 255, 255, .08), transparent 72%),
      linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
}
.homepage-hero__kicker::before {
    background: #09bab2;
    box-shadow: 0 0 16px rgba(9, 186, 178, .8);
}
.homepage-hero__title em {
    color: #a9f1ed;
    text-shadow: 0 4px 34px rgba(9, 186, 178, .2);
}
.homepage-hero__search button {
    color: #ffffff;
    background: #09bab2;
    box-shadow: inset 1px 0 rgba(0, 0, 0, .05);
}
.homepage-hero__topics a:hover {
    border-color: rgba(255, 255, 255, .5);
    background: rgba(255, 255, 255, .1);
}
.homepage-hero__scroll::after {
    background: linear-gradient(var(--library-beige), transparent);
}
.library-eyebrow,
.library-collection-row__copy small,
.library-collection-row__copy b,
.library-directory__item > b,
.library-institution__index small,
.library-institution__index b {
    color: #5d625e;
}
.library-directory__item > .material-symbols-outlined,
.library-institution__index .material-symbols-outlined {
    color: #09bab2;
}
.library-section-head,
.library-collection-ledger,
.library-collection-ledger > div,
.library-directory__list,
.library-directory__item,
.library-institution__index,
.library-institution__index > a {
    border-color: rgba(22, 76, 73, .16) !important;
}
.library-section-head h2,
.library-section-note a,
.library-collection-row__copy strong,
.library-collection-ledger strong,
.library-directory__intro h2,
.library-directory__item strong,
.library-institution__index h3,
.library-journal__event,
.library-journal__event h3 {
    color: var(--library-ink) !important;
}
.library-section-note,
.library-directory__intro p,
.library-collection-row__copy em,
.library-institution__index p {
    color: rgba(22, 76, 73, .62);
}
.library-section-note a,
.library-collection-feature strong,
.library-institution__feature strong,
.library-journal strong {
    border-bottom-color: var(--library-beige);
}
.library-collection-feature,
.library-institution__feature,
.library-journal__feature {
    background: var(--library-teal-deep);
}
.library-image-wash {
    background: linear-gradient(180deg, rgba(32,35,33,.04), rgba(32,35,33,.86));
}
.library-collection-feature > div > span,
.library-institution__feature small,
.library-journal small {
    color: #ffffff;
}
.library-collection-row {
    background: var(--library-white);
}
.library-collection-row:hover {
    box-shadow: 20px 30px 70px rgba(6, 75, 72, .14);
}
.library-services {
    color: var(--library-ink) !important;
    background:
      var(--library-white) !important;
    border-top: 1px solid rgba(62, 64, 58, .11);
    border-bottom: 1px solid rgba(62, 64, 58, .11);
}
.library-services__intro h2,
.library-service h3 {
    color: var(--library-ink) !important;
}
.library-services__intro > p,
.library-service p {
    color: rgba(62, 64, 58, .62) !important;
}
.library-services__seal,
.library-service > a {
    color: #09bab2 !important;
}
.library-service__icon {
    color: var(--library-ink);
    background: var(--library-beige);
}
.library-services__list,
.library-service {
    border-color: rgba(62, 64, 58, .13) !important;
}
.library-service__number { color: rgba(62, 64, 58, .3); }
.library-service:hover {
    background: var(--library-ivory) !important;
}
.library-directory__item:hover {
    background: var(--library-teal-deep);
}
.library-directory__item:hover > .material-symbols-outlined,
.library-directory__item:hover > b {
    color: var(--library-beige);
}
.library-institution {
    background: var(--library-white) !important;
    box-shadow: 50vw 0 var(--library-white), -50vw 0 var(--library-white) !important;
}
.library-institution__index > a:hover {
    background: var(--library-ivory);
}
.library-journal__event {
    background:
      linear-gradient(rgba(22,76,73,.055) 1px, transparent 1px),
      var(--library-white);
    border: 1px solid rgba(62, 64, 58, .12);
}
.library-journal__event strong { border-bottom-color: var(--library-ink); }
.library-journal__feature {
    color: var(--library-ink);
    background: var(--library-white);
    border: 1px solid rgba(62, 64, 58, .12);
}
.library-journal__feature article {
    color: var(--library-ink);
}
.library-journal__feature h3 {
    color: var(--library-ink);
}
.library-journal__feature p {
    color: rgba(62, 64, 58, .62);
}
.library-journal__feature small {
    color: #5d625e;
}

/* Library intelligence: a quiet, archival system for the homepage content. */
.library-intelligence {
    --archive-white: #ffffff;
    --archive-paper: #f6f5f0;
    --archive-paper-deep: #ece9df;
    --archive-ink: #25312d;
    --archive-forest: #315646;
    --archive-forest-deep: #203d32;
    --archive-sage: #839386;
    --archive-brass: #b38b4d;
    --archive-clay: #9a6652;
    --archive-line: rgba(37, 49, 45, .13);
    --library-section-title-size: clamp(42px, 3.5vw, 56px);
    color: var(--archive-ink);
    background: var(--archive-white);
    width: 100%;
    min-width: 0;
}
.library-intelligence__section {
    position: relative;
    width: 100% !important;
    min-width: 0;
    max-width: none !important;
    margin: 0 !important;
    padding: clamp(84px, 8vw, 132px) max(5vw, calc((100vw - 1440px) / 2)) !important;
    overflow: hidden;
}
.library-intelligence__section--paper {
    background:
      linear-gradient(rgba(49, 86, 70, .035) 1px, transparent 1px),
      linear-gradient(90deg, rgba(49, 86, 70, .035) 1px, transparent 1px),
      var(--archive-paper) !important;
    background-size: 48px 48px !important;
}
.library-intelligence__section--white {
    background: var(--archive-white) !important;
}
.library-intelligence__head {
    display: grid !important;
    grid-template-columns: minmax(0, 1.15fr) minmax(280px, .7fr);
    gap: 60px;
    align-items: end;
    max-width: 1440px;
    margin: 0 auto 52px !important;
    padding: 0 0 30px !important;
    border: 0 !important;
    border-bottom: 1px solid var(--archive-line) !important;
}
.library-intelligence__head > *,
.library-overview__layout > *,
.library-categories__layout > *,
.library-bookshelf > *,
.library-analytics__grid > * {
    min-width: 0;
}
.library-intelligence__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 18px;
    color: var(--archive-forest);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .17em;
    line-height: 1;
    text-transform: uppercase;
}
.library-intelligence__eyebrow::before {
    width: 28px;
    height: 1px;
    background: var(--archive-brass);
    content: "";
}
.library-intelligence__head h2 {
    max-width: 820px;
    margin: 0;
    color: var(--archive-ink) !important;
    font-family: var(--font-display, Georgia, serif);
    font-size: var(--library-section-title-size) !important;
    font-weight: 650;
    letter-spacing: -.042em;
    line-height: .98;
}
.library-intelligence__head > p {
    max-width: 520px;
    margin: 0 0 4px;
    color: rgba(37, 49, 45, .67);
    font-size: 16px;
    line-height: 1.75;
}
.library-intelligence__link {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    width: fit-content;
    margin-top: 22px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--archive-brass);
    color: var(--archive-ink);
    font-size: 11px;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.library-intelligence__link span {
    color: var(--archive-brass);
    font-size: 17px;
    transition: transform .25s ease;
}
.library-intelligence__link:hover span {
    transform: translateX(4px);
}
.library-overview__layout {
    display: grid;
    grid-template-columns: minmax(0, .94fr) minmax(440px, 1.06fr);
    grid-template-rows: none !important;
    gap: 28px;
    height: auto !important;
    max-width: 1440px;
    margin: 0 auto;
}
.library-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid var(--archive-line);
    border-left: 1px solid var(--archive-line);
}
.library-metric {
    min-height: 220px;
    padding: clamp(26px, 3vw, 42px);
    background: var(--archive-white);
    border-right: 1px solid var(--archive-line);
    border-bottom: 1px solid var(--archive-line);
    transition: background .25s ease, transform .25s ease;
}
.library-metric:hover {
    z-index: 1;
    background: #fbfaf6;
    transform: translateY(-4px);
}
.library-metric__index {
    display: block;
    margin-bottom: 38px;
    color: var(--archive-brass);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10px;
    letter-spacing: .12em;
}
.library-metric strong {
    display: block;
    color: var(--archive-ink);
    font-family: var(--font-display, Georgia, serif);
    font-size: clamp(38px, 4vw, 58px);
    font-weight: 600;
    letter-spacing: -.04em;
    line-height: .9;
}
.library-metric b {
    display: block;
    margin-top: 16px;
    color: var(--archive-ink);
    font-size: 13px;
    font-weight: 800;
}
.library-metric small {
    display: block;
    margin-top: 6px;
    color: rgba(37, 49, 45, .52);
    font-size: 11px;
    line-height: 1.45;
}
.library-growth {
    position: relative;
    min-height: 520px;
    padding: clamp(30px, 4vw, 54px);
    color: #fff;
    background:
      radial-gradient(circle at 90% 0, rgba(179, 139, 77, .2), transparent 34%),
      var(--archive-forest-deep);
    overflow: hidden;
}
.library-growth::after {
    position: absolute;
    right: -100px;
    bottom: -170px;
    width: 360px;
    height: 360px;
    border: 1px solid rgba(255, 255, 255, .09);
    border-radius: 50%;
    box-shadow: 0 0 0 38px rgba(255, 255, 255, .025), 0 0 0 76px rgba(255, 255, 255, .018);
    content: "";
}
.library-growth__top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
}
.library-growth__top small {
    display: block;
    margin-bottom: 9px;
    color: rgba(255, 255, 255, .52);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .15em;
    text-transform: uppercase;
}
.library-growth__top h3 {
    margin: 0;
    color: #fff;
    font-family: var(--font-display, Georgia, serif);
    font-size: clamp(27px, 3vw, 40px);
    font-weight: 600;
}
.library-growth__badge {
    flex: 0 0 auto;
    padding: 10px 13px;
    color: #f4ddae;
    background: rgba(255, 255, 255, .07);
    border: 1px solid rgba(255, 255, 255, .13);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
}
.library-growth__legend {
    position: relative;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 14px 25px;
    margin-top: 32px;
    color: rgba(255, 255, 255, .68);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .05em;
}
.library-growth__legend span {
    display: inline-flex;
    align-items: center;
    gap: 9px;
}
.library-growth__legend i {
    width: 22px;
    height: 2px;
    background: var(--series-color);
}
.library-growth__chart {
    position: relative;
    z-index: 1;
    width: 100%;
    height: auto;
    margin-top: 18px;
    overflow: hidden;
}
.library-growth__chart .grid-line {
    stroke: rgba(255, 255, 255, .1);
    stroke-width: 1;
}
.library-growth__chart .vertical-guide {
    stroke: rgba(255, 255, 255, .045);
    stroke-width: 1;
}
.library-growth__chart .axis-label,
.library-growth__chart .month-label {
    fill: rgba(255, 255, 255, .44);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 10px;
    letter-spacing: .04em;
}
.library-growth__chart .month-label {
    text-anchor: middle;
    text-transform: uppercase;
}
.library-growth__chart .area-primary {
    fill: url(#libraryUsageGradient);
}
.library-growth__chart .usage-line {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 3.5;
}
.library-growth__chart .usage-line--primary {
    stroke: #e0bd79;
}
.library-growth__chart .usage-line--secondary {
    stroke: #9ab8aa;
    stroke-dasharray: 6 7;
    stroke-width: 2.5;
}
.library-growth__chart .dot-primary,
.library-growth__chart .dot-secondary {
    fill: var(--archive-forest-deep);
    stroke-width: 2.5;
}
.library-growth__chart .dot-primary {
    stroke: #efd49d;
}
.library-growth__chart .dot-secondary {
    stroke: #a9cabc;
}
.library-growth__chart .latest-marker line {
    stroke: rgba(224, 189, 121, .48);
    stroke-width: 2;
}
.library-growth__chart .latest-marker rect {
    fill: #f2e1bb;
}
.library-growth__chart .latest-marker text {
    fill: var(--archive-forest-deep);
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
    font-weight: 800;
    text-anchor: middle;
}
.library-categories__layout {
    display: grid;
    grid-template-columns: minmax(500px, .75fr) minmax(0, 1.25fr);
    gap: clamp(44px, 5vw, 80px);
    max-width: 1440px !important;
    margin: 0 auto;
    padding-inline: 0 !important;
}
.library-categories__intro {
    position: sticky;
    top: 150px;
    align-self: start;
    max-width: none !important;
    margin: 0 !important;
}
.library-categories__intro h2 {
    max-width: 520px;
    margin: 0;
    color: var(--archive-ink) !important;
    font-family: var(--font-display, Georgia, serif);
    font-size: var(--library-section-title-size) !important;
    font-weight: 650;
    letter-spacing: -.04em;
    line-height: .98;
    overflow-wrap: normal;
    word-break: normal;
}
.library-categories__intro > p {
    margin-top: 25px;
    color: rgba(37, 49, 45, .67) !important;
    font-size: 15px;
    line-height: 1.8;
}
.library-intelligence__link {
    color: var(--archive-ink) !important;
}
.library-category-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid var(--archive-line);
    border-left: 1px solid var(--archive-line);
}
.library-category {
    position: relative;
    min-height: 218px;
    padding: 28px;
    background: rgba(255, 255, 255, .77);
    border-right: 1px solid var(--archive-line);
    border-bottom: 1px solid var(--archive-line);
    overflow: hidden;
    transition: color .28s ease, background .28s ease;
}
.library-category {
    color: var(--archive-ink) !important;
}
.library-category::after {
    position: absolute;
    right: -40px;
    bottom: -60px;
    width: 140px;
    height: 140px;
    border: 1px solid rgba(49, 86, 70, .11);
    border-radius: 50%;
    content: "";
    transition: transform .35s ease;
}
.library-category:hover {
    color: #fff !important;
    background: var(--archive-forest);
}
.library-category:hover::after {
    border-color: rgba(255, 255, 255, .16);
    transform: scale(1.35);
}
.library-category__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}
.library-category__icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    color: var(--archive-forest);
    background: var(--archive-paper-deep);
}
.library-category__icon .material-symbols-outlined {
    font-size: 21px;
}
.library-category__count {
    color: rgba(37, 49, 45, .48);
    font: 11px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .08em;
}
.library-category h3 {
    position: relative;
    z-index: 1;
    max-width: 260px;
    margin: 31px 0 22px;
    color: inherit !important;
    font-family: var(--font-display, Georgia, serif);
    font-size: 22px !important;
    font-weight: 600;
    line-height: 1.14;
}
.library-category__scale {
    position: absolute;
    right: 28px;
    bottom: 28px;
    left: 28px;
    height: 2px;
    background: rgba(37, 49, 45, .12);
}
.library-category__scale span {
    display: block;
    width: var(--category-share);
    height: 100%;
    background: var(--archive-brass);
    transition: width .45s ease;
}
.library-category:hover .library-category__icon {
    color: #fff;
    background: rgba(255, 255, 255, .12);
}
.library-category:hover .library-category__count {
    color: rgba(255, 255, 255, .62);
}
.library-category:hover .library-category__scale {
    background: rgba(255, 255, 255, .15);
}
.library-books__layout {
    max-width: 1440px;
    margin: 0 auto;
    gap: 0 !important;
    border-top: 0 !important;
    grid-template-columns: none !important;
}
.library-bookshelf {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: clamp(18px, 2.4vw, 34px);
    padding: 20px 20px 0;
    border-bottom: 18px solid #d6d0c2;
    box-shadow: 0 10px 0 #f0ede5, 0 20px 28px rgba(37, 49, 45, .09);
}
.library-book {
    display: block;
    min-width: 0;
}
.library-book__cover {
    position: relative;
    min-height: 390px;
    padding: 34px 28px 30px 38px;
    color: #f9f6ed;
    background: var(--book-color, var(--archive-forest));
    border-radius: 2px 8px 8px 2px;
    box-shadow: -8px 8px 0 rgba(37, 49, 45, .08), 0 18px 34px rgba(37, 49, 45, .15);
    overflow: hidden;
    transform-origin: bottom center;
    transition: transform .35s cubic-bezier(.2, .8, .2, 1), box-shadow .35s ease;
}
.library-book:hover .library-book__cover {
    box-shadow: -9px 14px 0 rgba(37, 49, 45, .08), 0 30px 50px rgba(37, 49, 45, .2);
    transform: translateY(-10px) rotate(-.6deg);
}
.library-book__cover::before {
    position: absolute;
    inset: 0 auto 0 12px;
    width: 1px;
    background: rgba(255, 255, 255, .28);
    box-shadow: 3px 0 8px rgba(0, 0, 0, .18);
    content: "";
}
.library-book__cover::after {
    position: absolute;
    right: -90px;
    bottom: -96px;
    width: 250px;
    height: 250px;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 50%;
    box-shadow: 0 0 0 25px rgba(255, 255, 255, .035), 0 0 0 50px rgba(255, 255, 255, .025);
    content: "";
}
.library-book__cover--forest { --book-color: #315646; }
.library-book__cover--clay { --book-color: #9a6652; }
.library-book__cover--ink { --book-color: #293b45; }
.library-book__cover--sage { --book-color: #6d7d6d; }
.library-book__code {
    display: block;
    color: rgba(255, 255, 255, .62);
    font: 10px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .15em;
}
.library-book__ornament {
    display: grid;
    width: 48px;
    height: 48px;
    margin-top: 58px;
    border: 1px solid rgba(255, 255, 255, .34);
    border-radius: 50%;
    place-items: center;
}
.library-book__ornament .material-symbols-outlined {
    font-size: 20px;
}
.library-book__cover h3 {
    position: relative;
    z-index: 1;
    margin: 28px 0 0;
    color: inherit;
    font-family: var(--font-display, Georgia, serif);
    font-size: clamp(23px, 1.65vw, 26px);
    font-weight: 600;
    line-height: 1.06;
    overflow-wrap: anywhere;
}
.library-book__cover small {
    position: absolute;
    bottom: 28px;
    left: 38px;
    z-index: 1;
    color: rgba(255, 255, 255, .58);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.library-book__meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 23px 3px 26px;
    color: rgba(37, 49, 45, .63);
    font-size: 11px;
    letter-spacing: .03em;
}
.library-book__meta span:last-child {
    color: var(--archive-brass);
    font-weight: 800;
}
.library-analytics__grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1.22fr;
    gap: 1px !important;
    max-width: 1440px;
    margin: 0 auto;
    background: var(--archive-line);
    border: 1px solid var(--archive-line);
}
.library-analytics__panel {
    min-height: 420px;
    padding: clamp(28px, 3vw, 44px);
    background: var(--archive-white);
}
.library-analytics__panel > header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    min-height: 66px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--archive-line);
}
.library-analytics__panel h3 {
    margin: 0;
    color: var(--archive-ink);
    font-family: var(--font-display, Georgia, serif);
    font-size: 24px;
    font-weight: 600;
}
.library-analytics__panel header span {
    color: rgba(37, 49, 45, .43);
    font: 9px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace;
    letter-spacing: .08em;
    text-align: right;
    text-transform: uppercase;
}
.library-language-chart {
    display: grid;
    grid-template-columns: 148px 1fr;
    gap: 30px;
    align-items: center;
    margin-top: 52px;
}
.library-donut {
    position: relative;
    width: 148px;
    height: 148px;
    border-radius: 50%;
    background: conic-gradient(var(--archive-forest) 0 52%, var(--archive-brass) 52% 83%, var(--archive-paper-deep) 83% 100%);
}
.library-donut::after {
    position: absolute;
    inset: 27px;
    display: grid;
    color: var(--archive-ink);
    background: #fff;
    border-radius: 50%;
    content: "8.9K";
    font-family: var(--font-display, Georgia, serif);
    font-size: 25px;
    font-weight: 650;
    place-items: center;
}
.library-language-legend {
    display: grid;
    gap: 16px;
}
.library-language-legend li {
    display: grid;
    grid-template-columns: 8px 1fr auto;
    gap: 10px;
    align-items: center;
    color: rgba(37, 49, 45, .67);
    font-size: 12px;
}
.library-language-legend i {
    width: 7px;
    height: 7px;
    background: var(--legend-color);
    border-radius: 50%;
}
.library-language-legend strong {
    color: var(--archive-ink);
    font-size: 12px;
}
.library-format-bars {
    display: grid;
    gap: 26px;
    margin-top: 42px;
}
.library-format-bar__label {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
    color: rgba(37, 49, 45, .68);
    font-size: 11px;
}
.library-format-bar__label strong {
    color: var(--archive-ink);
    font: 11px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
}
.library-format-bar__track {
    height: 5px;
    background: var(--archive-paper-deep);
}
.library-format-bar__track span {
    display: block;
    width: var(--format-share);
    height: 100%;
    background: var(--archive-forest);
}
.library-activity__value {
    margin-top: 34px;
    color: var(--archive-ink);
    font-family: var(--font-display, Georgia, serif);
    font-size: 42px;
    font-weight: 600;
    letter-spacing: -.04em;
}
.library-activity__value small {
    margin-left: 7px;
    color: var(--archive-forest);
    font-family: var(--font-body);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
}
.library-activity-chart {
    width: 100%;
    height: 155px;
    margin-top: 22px;
    overflow: visible;
}
.library-activity-chart .guide {
    stroke: rgba(37, 49, 45, .1);
    stroke-width: 1;
}
.library-activity-chart .bar {
    fill: #dfe4de;
}
.library-activity-chart .bar.is-current {
    fill: var(--archive-forest);
}
.library-activity-chart .trend {
    fill: none;
    stroke: var(--archive-brass);
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2.5;
}
.library-activity__months {
    display: flex;
    justify-content: space-around;
    color: rgba(37, 49, 45, .4);
    font: 9px/1 ui-monospace, SFMono-Regular, Menlo, monospace;
    text-transform: uppercase;
}
@media (max-width: 1250px) {
    .library-categories__layout {
        grid-template-columns: 1fr;
    }
    .library-categories__intro {
        position: static;
        max-width: 720px !important;
    }
}
@media (max-width: 1100px) {
    .library-overview__layout,
    .library-categories__layout {
        grid-template-columns: 1fr;
    }
    .library-categories__intro {
        position: static;
        max-width: 720px;
    }
    .library-bookshelf {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 46px;
    }
    .library-analytics__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .library-analytics__panel:last-child {
        grid-column: 1 / -1;
    }
}
@media (max-width: 720px) {
    .library-intelligence {
        --library-section-title-size: clamp(31px, 9.2vw, 36px);
    }
    .library-intelligence__section {
        padding: 72px 20px !important;
    }
    .library-intelligence__head {
        grid-template-columns: 1fr;
        gap: 22px;
        margin-bottom: 34px !important;
    }
    .library-intelligence__head h2,
    .library-categories__intro h2 {
        font-size: clamp(36px, 12vw, 52px);
    }
    .library-metrics,
    .library-category-grid,
    .library-bookshelf,
    .library-analytics__grid {
        grid-template-columns: 1fr;
    }
    .library-metric {
        min-height: 180px;
    }
    .library-growth {
        min-height: 400px;
        padding: 28px 22px;
    }
    .library-growth__top {
        display: block;
    }
    .library-growth__badge {
        display: inline-block;
        margin-top: 15px;
    }
    .library-category {
        min-height: 196px;
    }
    .library-bookshelf {
        gap: 40px;
        padding-right: 8px;
        padding-left: 8px;
    }
    .library-book__cover {
        min-height: 410px;
    }
    .library-analytics__panel:last-child {
        grid-column: auto;
    }
    .library-language-chart {
        grid-template-columns: 125px 1fr;
        gap: 20px;
    }
    .library-donut {
        width: 125px;
        height: 125px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .homepage-hero__image,
    .homepage-hero__card,
    .homepage-hero__scroll::after,
    .library-metric,
    .library-category,
    .library-book__cover {
        animation: none;
        transition: none;
    }
}

</style>
@endsection

@section('content')
<div data-section="homepage-canonical-page">

  {{-- ── Hidden institutional identity mark (accessibility / test wiring) ── --}}
  <div id="hero-campus-mark" class="sr-only" aria-hidden="true">
    <span>{{ $copy['identity_brand'] }}</span>
  </div>

  {{-- ══════════════════════════════════════════════════════════════
       SECTION 1 — HERO
       ════════════════════════════════════════════════════════════ --}}
  <section data-section="homepage-canonical-hero">
    <img src="/images/news/campus-library.jpg"
         alt="{{ $copy['hero_img_alt'] }}"
         class="homepage-hero__image"
         fetchpriority="high">
    <div class="homepage-hero__overlay" aria-hidden="true"></div>
    <div class="homepage-hero__ambient" aria-hidden="true"></div>

    <div class="homepage-hero__content">
      <div class="homepage-hero__copy">
        <h1 class="homepage-hero__title">
          {{ $copy['hero_h1'] }}<br>
          <em>{{ $copy['hero_h1_accent'] }}</em>
        </h1>

        <p class="homepage-hero__lead">{{ $copy['hero_lead'] }}</p>

        <form id="heroSearch"
              data-test-id="homepage-canonical-search"
              class="homepage-hero__search"
              action="{{ $withLang('/catalog') }}"
              method="get">
          <svg class="ml-5" viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="20" height="20" style="flex:0 0 auto; fill:none; stroke:#627083; stroke-width:2; stroke-linecap:round; stroke-linejoin:round;">
            <circle cx="11" cy="11" r="6.5"></circle>
            <path d="M16 16l4.5 4.5"></path>
          </svg>
          <label class="sr-only" for="homepage-search">{{ $copy['search_placeholder'] }}</label>
          <input id="homepage-search"
                 type="search"
                 name="q"
                 placeholder="{{ $copy['search_placeholder'] }}">
          <button type="submit">{{ $copy['search_cta'] }}</button>
        </form>
      </div>

      <div class="homepage-hero__book" aria-hidden="true">
        <div class="homepage-hero__book-grain" aria-hidden="true"></div>
        <div class="homepage-hero__book-badge">
          <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true">
        </div>
        <div class="homepage-hero__book-title">
          {{ $lang === 'en' ? 'Kazakh University of Technology and Business' : ($lang === 'kk' ? 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті' : 'Казахский университет технологии и бизнеса имени К. Кулажанова') }}
          <small>{{ $lang === 'en' ? 'Academic library' : ($lang === 'kk' ? 'Академиялық кітапхана' : 'Академическая библиотека') }}</small>
        </div>
        <div class="homepage-hero__book-shadow" aria-hidden="true"></div>
        <div class="homepage-hero__book-ring" aria-hidden="true"></div>
      </div>

    </div>

    <a href="#homepage-navigation" class="homepage-hero__scroll">
    </a>
  </section>
</div>{{-- /homepage-canonical-page --}}
@endsection
