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
            'access_type' => 'remote_auth',
            'status' => 'active',
            'expiry_date' => '2026-09-30',
            'url' => 'https://www.iprbookshop.ru/',
            'category' => 'electronic_library',
            'notes' => 'Основная подписная электронная библиотека платформы. Доступ для читателей и преподавателей через авторизацию.',
        ],

        [
            'slug' => 'rmeb',
            'title' => 'Республиканская межвузовская электронная библиотека (РМЭБ)',
            'provider' => 'РМЭБ',
            'description' => 'Электронные версии диссертаций, монографий и научных трудов казахстанских учёных. Национальная база академических работ.',
            'access_type' => 'campus',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://rmebrk.kz/',
            'category' => 'research_database',
            'notes' => 'Доступ из кампуса. Содержит казахстанские диссертации и научные труды.',
        ],

        [
            'slug' => 'elibrary',
            'title' => 'Электронная научная библиотека eLIBRARY.RU',
            'provider' => 'eLIBRARY.RU',
            'description' => 'Крупнейшая научная электронная библиотека с индексами цитирования РИНЦ, полнотекстовыми статьями и реферативными базами.',
            'access_type' => 'remote_auth',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://elibrary.ru/',
            'category' => 'research_database',
            'notes' => 'Включает РИНЦ. Важна для публикационной активности и подготовки научных статей.',
        ],

        [
            'slug' => 'polpred',
            'title' => 'Polpred.com',
            'provider' => 'Polpred.com',
            'description' => 'Обзоры прессы и аналитика по экономике, бизнесу, праву и другим направлениям. Архив публикаций СМИ.',
            'access_type' => 'campus',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://polpred.com/',
            'category' => 'analytics',
            'notes' => 'Полезен для анализа СМИ, мониторинга бизнес-среды и подготовки аналитических работ.',
        ],

        [
            'slug' => 'cyberleninka',
            'title' => 'КиберЛенинка',
            'provider' => 'КиберЛенинка',
            'description' => 'Открытая научная электронная библиотека. Полнотекстовые статьи из российских научных журналов с открытым доступом.',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://cyberleninka.ru/',
            'category' => 'open_access',
            'notes' => 'Свободный доступ без ограничений. Хороший источник для студенческих работ и обзоров литературы.',
        ],

        [
            'slug' => 'doaj',
            'title' => 'Directory of Open Access Journals (DOAJ)',
            'provider' => 'DOAJ',
            'description' => 'Международный каталог рецензируемых журналов открытого доступа. Охватывает все научные направления.',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://doaj.org/',
            'category' => 'open_access',
            'notes' => 'Свободный доступ. Полезен для поиска рецензируемых OA-журналов для публикации.',
        ],

        [
            'slug' => 'oapen',
            'title' => 'OAPEN Library',
            'provider' => 'OAPEN Foundation',
            'description' => 'Открытая библиотека академических монографий и книг. Полнотекстовый доступ к рецензируемым научным изданиям.',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://library.oapen.org/',
            'category' => 'open_access',
            'notes' => 'Свободный доступ к академическим книгам. Дополняет подписные ресурсы.',
        ],

        [
            'slug' => 'kaznu-repository',
            'title' => 'Репозиторий КазНУ',
            'provider' => 'КазНУ им. аль-Фараби',
            'description' => 'Институциональный репозиторий Казахского национального университета. Диссертации, научные труды и учебные материалы.',
            'access_type' => 'open',
            'status' => 'active',
            'expiry_date' => null,
            'url' => 'https://repository.kaznu.kz/',
            'category' => 'open_access',
            'notes' => 'Открытый казахстанский академический ресурс.',
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
