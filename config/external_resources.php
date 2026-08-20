<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External Licensed Resources
    |--------------------------------------------------------------------------
    |
    | Structured data source for external licensed electronic resources
    | available to university students, faculty, and staff.
    |
    | Each resource includes: slug, title, provider, description, access_type,
    | status, expiry_date, url, category, and notes.
    |
    | Categories: electronic_library, research_database, open_access, analytics
    | Access types: campus, remote_auth, open
    | Status: active, expiring_soon, inactive
    |
    */

    'resources' => [

        [
            'slug' => 'ipr-smart',
            'title' => 'IPR SMART',
            'provider' => 'IPR Media',
            'description' => 'Цифровая образовательная платформа с учебниками, монографиями и научными журналами по экономике, технологиям, праву и другим направлениям подготовки.',
            'resource_type' => 'licensed',
            'access_type' => 'remote_auth',
            'status' => env('EXTERNAL_RESOURCE_IPR_EXPIRES_AT') ? 'active' : 'inactive',
            'publication_status' => env('EXTERNAL_RESOURCE_IPR_EXPIRES_AT') ? 'published' : 'draft',
            // Supplied from the verified production contract, never guessed.
            'expiry_date' => env('EXTERNAL_RESOURCE_IPR_EXPIRES_AT'),
            'url' => 'https://www.iprbookshop.ru/',
            'logo' => '/images/resources/ipr-smart.ico',
            'category' => 'electronic_library',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'scientific_articles', 'journals', 'educational_materials', 'multimedia'],
            'access_method' => 'personal_account',
            'login_required' => true,
            'notes' => 'Доступ для зарегистрированных читателей через личный аккаунт. Лицензия обычно оформляется на один год; точную текущую дату указывает ответственный сотрудник в административной панели.',
        ],

        [
            'slug' => 'rmeb',
            'title' => 'Республиканская межвузовская электронная библиотека (РМЭБ)',
            'provider' => 'РМЭБ',
            'description' => 'Электронные версии диссертаций, монографий и научных трудов казахстанских учёных. Национальная база академических работ.',
            'resource_type' => 'licensed',
            'access_type' => 'campus',
            'status' => env('EXTERNAL_RESOURCE_RMEB_EXPIRES_AT') ? 'active' : 'inactive',
            'publication_status' => env('EXTERNAL_RESOURCE_RMEB_EXPIRES_AT') ? 'published' : 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_RMEB_EXPIRES_AT'),
            'url' => 'https://rmebrk.kz/',
            'logo' => '/images/resources/rmeb.ico',
            'category' => 'research_database',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'scientific_articles', 'dissertations', 'faculty_works'],
            'access_method' => 'ip_based',
            'campus_only' => true,
            'notes' => 'Откройте ресурс из сети кампуса. Актуальную дату годового договора указывает ответственный сотрудник.',
        ],

        [
            'slug' => 'elibrary',
            'title' => 'Электронная научная библиотека eLIBRARY.RU',
            'provider' => 'eLIBRARY.RU',
            'description' => 'Крупнейшая научная электронная библиотека с индексами цитирования РИНЦ, полнотекстовыми статьями и реферативными базами.',
            'resource_type' => 'licensed',
            'access_type' => 'remote_auth',
            'status' => env('EXTERNAL_RESOURCE_ELIBRARY_EXPIRES_AT') ? 'active' : 'inactive',
            'publication_status' => env('EXTERNAL_RESOURCE_ELIBRARY_EXPIRES_AT') ? 'published' : 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_ELIBRARY_EXPIRES_AT'),
            'url' => 'https://elibrary.ru/',
            'logo' => '/images/resources/elibrary.ico',
            'category' => 'research_database',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['scientific_articles', 'journals', 'research_reports'],
            'access_method' => 'personal_account',
            'login_required' => true,
            'notes' => 'Включает РИНЦ. Важна для публикационной активности и подготовки научных статей.',
        ],

        [
            'slug' => 'polpred',
            'title' => 'Polpred.com',
            'provider' => 'Polpred.com',
            'description' => 'Обзоры прессы и аналитика по экономике, бизнесу, праву и другим направлениям. Архив публикаций СМИ.',
            'resource_type' => 'licensed',
            'access_type' => 'campus',
            'status' => env('EXTERNAL_RESOURCE_POLPRED_EXPIRES_AT') ? 'active' : 'inactive',
            'publication_status' => env('EXTERNAL_RESOURCE_POLPRED_EXPIRES_AT') ? 'published' : 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_POLPRED_EXPIRES_AT'),
            'url' => 'https://polpred.com/',
            'logo' => '/images/resources/polpred.ico',
            'category' => 'analytics',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['scientific_articles', 'research_reports', 'journals'],
            'access_method' => 'ip_based',
            'campus_only' => true,
            'notes' => 'Полезен для анализа СМИ, мониторинга бизнес-среды и подготовки аналитических работ.',
        ],

        [
            'slug' => 'cyberleninka',
            'title' => 'КиберЛенинка',
            'provider' => 'КиберЛенинка',
            'description' => 'Открытая научная электронная библиотека. Полнотекстовые статьи из российских научных журналов с открытым доступом.',
            'resource_type' => 'open_access',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://cyberleninka.ru/',
            'logo' => '/images/resources/cyberleninka.ico',
            'category' => 'open_access',
            'available_roles' => ['guest', 'student', 'teacher', 'library_staff'],
            'content_types' => ['scientific_articles', 'journals'],
            'access_method' => 'public_url',
            'notes' => 'Свободный доступ без ограничений. Хороший источник для студенческих работ и обзоров литературы.',
        ],

        [
            'slug' => 'doaj',
            'title' => 'Directory of Open Access Journals (DOAJ)',
            'provider' => 'DOAJ',
            'description' => 'Международный каталог рецензируемых журналов открытого доступа. Охватывает все научные направления.',
            'resource_type' => 'open_access',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://doaj.org/',
            'logo' => '/images/resources/doaj.png',
            'category' => 'open_access',
            'available_roles' => ['guest', 'student', 'teacher', 'library_staff'],
            'content_types' => ['scientific_articles', 'journals'],
            'access_method' => 'public_url',
            'notes' => 'Свободный доступ. Полезен для поиска рецензируемых OA-журналов для публикации.',
        ],

        [
            'slug' => 'oapen',
            'title' => 'OAPEN Library',
            'provider' => 'OAPEN Foundation',
            'description' => 'Открытая библиотека академических монографий и книг. Полнотекстовый доступ к рецензируемым научным изданиям.',
            'resource_type' => 'open_access',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://library.oapen.org/',
            'logo' => '/images/resources/oapen.png',
            'category' => 'open_access',
            'available_roles' => ['guest', 'student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'scientific_articles'],
            'access_method' => 'public_url',
            'notes' => 'Свободный доступ к академическим книгам. Дополняет подписные ресурсы.',
        ],

        [
            'slug' => 'kaznu-repository',
            'title' => 'Репозиторий КазНУ',
            'provider' => 'КазНУ им. аль-Фараби',
            'description' => 'Институциональный репозиторий Казахского национального университета. Диссертации, научные труды и учебные материалы.',
            'resource_type' => 'open_access',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://repository.kaznu.kz/',
            'logo' => '/images/resources/kaznu-repository.svg',
            'category' => 'open_access',
            'available_roles' => ['guest', 'student', 'teacher', 'library_staff'],
            'content_types' => ['dissertations', 'scientific_articles', 'faculty_works', 'educational_materials'],
            'access_method' => 'public_url',
            'notes' => 'Открытый казахстанский академический ресурс.',
        ],

        [
            'slug' => 'atu-library',
            'title' => 'Научная библиотека АТУ',
            'name_translations' => ['ru' => 'Научная библиотека АТУ', 'kk' => 'АТУ ғылыми кітапханасы', 'en' => 'ATU Scientific Library'],
            'provider' => 'Алматинский технологический университет',
            'description' => 'Партнёрская университетская библиотека: электронный каталог, научные и учебные публикации, труды преподавателей.',
            'description_translations' => [
                'ru' => 'Партнёрская университетская библиотека: электронный каталог, научные и учебные публикации, труды преподавателей.',
                'kk' => 'Серіктес университет кітапханасы: электрондық каталог, ғылыми және оқу жарияланымдары, оқытушылар еңбектері.',
                'en' => 'Partner university library with an electronic catalogue, research and educational publications, and faculty works.',
            ],
            'resource_type' => 'partner',
            'access_type' => 'remote_auth',
            // Named by the specification, but URL and agreement details were
            // not supplied. Staff can complete and submit this draft.
            'status' => 'inactive',
            'publication_status' => 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_ATU_EXPIRES_AT'),
            'url' => null,
            'logo' => '/images/resources/partner-library.svg',
            'category' => 'electronic_library',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'scientific_articles', 'faculty_works', 'educational_materials'],
            'access_method' => 'personal_account',
            'login_required' => true,
            'notes' => 'Войдите с учётными данными, предоставленными библиотекой. Партнёрский договор рассчитан на 3–5 лет; точную дату указывает ответственный сотрудник.',
        ],

        [
            'slug' => 'rntb-kazakhstan',
            'title' => 'Республиканская научно-техническая библиотека (РНТБ)',
            'name_translations' => ['ru' => 'Республиканская научно-техническая библиотека (РНТБ)', 'kk' => 'Республикалық ғылыми-техникалық кітапхана (РҒТК)', 'en' => 'Republican Scientific and Technical Library (RSTL)'],
            'provider' => 'РНТБ Казахстана',
            'description' => 'Научно-техническая информация, электронный каталог, статьи, отчёты и отраслевые документы Казахстана.',
            'description_translations' => [
                'ru' => 'Научно-техническая информация, электронный каталог, статьи, отчёты и отраслевые документы Казахстана.',
                'kk' => 'Қазақстанның ғылыми-техникалық ақпараты, электрондық каталогы, мақалалары, есептері және салалық құжаттары.',
                'en' => 'Kazakhstan scientific and technical information, electronic catalogue, articles, reports, and sector documents.',
            ],
            'resource_type' => 'partner',
            'access_type' => 'remote_auth',
            'status' => 'inactive',
            'publication_status' => 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_RNTB_EXPIRES_AT'),
            'url' => null,
            'logo' => '/images/resources/partner-library.svg',
            'category' => 'research_database',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['scientific_articles', 'research_reports', 'journals', 'catalogues'],
            'access_method' => 'personal_account',
            'login_required' => true,
            'notes' => 'Для полного доступа используйте регистрацию или данные, выданные библиотекой. Срок партнёрского договора 3–5 лет; актуальная дата ведётся администратором.',
        ],

        [
            'slug' => 'kozybayev-library',
            'title' => 'Научная библиотека Kozybayev University',
            'name_translations' => ['ru' => 'Научная библиотека Kozybayev University', 'kk' => 'Kozybayev University ғылыми кітапханасы', 'en' => 'Kozybayev University Scientific Library'],
            'provider' => 'Kozybayev University',
            'description' => 'Партнёрский фонд университета: электронные книги, периодика, учебные материалы и научные работы.',
            'description_translations' => [
                'ru' => 'Партнёрский фонд университета: электронные книги, периодика, учебные материалы и научные работы.',
                'kk' => 'Университеттің серіктестік қоры: электрондық кітаптар, мерзімді басылымдар, оқу материалдары және ғылыми еңбектер.',
                'en' => 'Partner university collection of e-books, periodicals, educational materials, and research works.',
            ],
            'resource_type' => 'partner',
            'access_type' => 'remote_auth',
            'status' => 'inactive',
            'publication_status' => 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_KOZYBAYEV_EXPIRES_AT'),
            'url' => null,
            'logo' => '/images/resources/partner-library.svg',
            'category' => 'electronic_library',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'journals', 'faculty_works', 'educational_materials'],
            'access_method' => 'personal_account',
            'login_required' => true,
            'notes' => 'Перейдите на страницу научной библиотеки и используйте выданную партнёрскую учётную запись. Точный срок договора на 3–5 лет заполняется ответственным сотрудником.',
        ],

        [
            'slug' => 'astana-cbs',
            'title' => 'Централизованная библиотечная система Астаны',
            'name_translations' => ['ru' => 'Централизованная библиотечная система Астаны', 'kk' => 'Астана қаласының орталықтандырылған кітапхана жүйесі', 'en' => 'Astana Centralized Library System'],
            'provider' => 'Астана қаласының орталықтандырылған кітапхана жүйесі',
            'description' => 'Единый фонд городских библиотек, электронный каталог и интернет-библиотека произведений казахстанских авторов.',
            'description_translations' => [
                'ru' => 'Единый фонд городских библиотек, электронный каталог и интернет-библиотека произведений казахстанских авторов.',
                'kk' => 'Қалалық кітапханалардың бірыңғай қоры, электрондық каталог және қазақстандық авторлар шығармаларының интернет-кітапханасы.',
                'en' => 'Shared city-library collection, electronic catalogue, and online library of works by Kazakhstani authors.',
            ],
            'resource_type' => 'partner',
            'access_type' => 'remote_auth',
            'status' => 'inactive',
            'publication_status' => 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_ASTANA_CBS_EXPIRES_AT'),
            'url' => null,
            'logo' => '/images/resources/partner-library.svg',
            'category' => 'electronic_library',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'educational_materials', 'catalogues'],
            'access_method' => 'personal_account',
            'login_required' => true,
            'notes' => 'Для электронных услуг может потребоваться регистрация в городской библиотечной системе. Актуальную дату партнёрства указывает ответственный сотрудник.',
        ],

        [
            'slug' => 'arnaiy-kitaphana',
            'title' => '«Арнайы кітапхана»',
            'name_translations' => ['ru' => '«Арнайы кітапхана» — специализированная библиотека', 'kk' => '«Арнайы кітапхана»', 'en' => 'Arnaiy Kitaphana — Specialized Library'],
            'provider' => null,
            'description' => 'Доступные форматы для незрячих и слабовидящих читателей: книги по Брайлю, аудиокниги и адаптированные электронные материалы.',
            'description_translations' => [
                'ru' => 'Доступные форматы для незрячих и слабовидящих читателей: книги по Брайлю, аудиокниги и адаптированные электронные материалы.',
                'kk' => 'Зағип және нашар көретін оқырмандарға арналған қолжетімді форматтар: Брайль кітаптары, аудиокітаптар және бейімделген электрондық материалдар.',
                'en' => 'Accessible formats for blind and low-vision readers: Braille books, audiobooks, and adapted digital materials.',
            ],
            'resource_type' => 'partner',
            'access_type' => 'remote_auth',
            'status' => 'inactive',
            'publication_status' => 'draft',
            'expiry_date' => env('EXTERNAL_RESOURCE_ARNAIY_EXPIRES_AT'),
            'url' => null,
            'logo' => '/images/resources/accessible-library.svg',
            'category' => 'electronic_library',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'braille_books', 'audiobooks', 'multimedia'],
            'access_method' => 'librarian_mediated',
            'login_required' => true,
            'notes' => 'Обратитесь к сотруднику библиотеки для регистрации и получения адаптированного материала. Договорный срок 3–5 лет; фактическая дата ведётся в административной панели.',
        ],

        [
            'slug' => 'kazutb-catalogue',
            'title' => 'Электронный каталог библиотеки КазУТБ',
            'name_translations' => ['ru' => 'Электронный каталог библиотеки КазУТБ', 'kk' => 'ҚазТБУ кітапханасының электрондық каталогы', 'en' => 'KazUTB Library Electronic Catalogue'],
            'provider' => 'Научная библиотека КазУТБ',
            'description' => 'Внутренний ресурс библиотеки для поиска печатных и электронных изданий, проверки наличия и формирования подборки.',
            'resource_type' => 'internal',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => '/catalog',
            'logo' => '/logo.png',
            'category' => 'electronic_library',
            'available_roles' => ['guest', 'student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'journals', 'educational_materials', 'catalogues'],
            'access_method' => 'public_url',
            'notes' => 'Введите название, автора, ISBN или индекс УДК. Описание фонда открыто всем; личные операции доступны после входа.',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Category labels
    |--------------------------------------------------------------------------
    */

    'categories' => [
        'electronic_library' => [
            'label' => 'Электронная библиотека',
            'icon' => '📚',
            'color' => 'blue',
        ],
        'research_database' => [
            'label' => 'Научная база данных',
            'icon' => '🔬',
            'color' => 'violet',
        ],
        'open_access' => [
            'label' => 'Открытый доступ',
            'icon' => '🔓',
            'color' => 'green',
        ],
        'analytics' => [
            'label' => 'Аналитика и СМИ',
            'icon' => '📊',
            'color' => 'pink',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Access type labels
    |--------------------------------------------------------------------------
    */

    'access_types' => [
        'campus' => [
            'label' => 'Из кампуса',
            'badge' => 'access-badge--campus',
            'description' => 'Доступ с компьютеров читальных залов и Wi-Fi сети университета.',
        ],
        'remote_auth' => [
            'label' => 'По авторизации',
            'badge' => 'access-badge--remote',
            'description' => 'Вход через личный кабинет библиотеки из любой точки.',
        ],
        'open' => [
            'label' => 'Свободный доступ',
            'badge' => 'access-badge--open',
            'description' => 'Без ограничений, доступно всем.',
        ],
    ],

];
