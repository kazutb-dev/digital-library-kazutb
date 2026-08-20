<?php

// Homepage sections 2–6 (faculty picks, new arrivals, collections, statistics, FAQ).
//
// Copy only. Book counts are NOT stored here: they are resolved from the real
// catalog in the `/` route and rendered only when the catalog can answer, so a
// card never shows an invented number.
//
// Physical rooms, schedules and other operational claims are intentionally
// absent unless an authoritative source is available.

return [

    // UDC indices are real classification codes; each card links into
    // /catalog?udc={code}, so the tiles are navigation, not decoration.
    'faculties' => [
        ['udc' => '004',   'icon' => 'memory'],
        ['udc' => '33',    'icon' => 'trending_up'],
        ['udc' => '34',    'icon' => 'gavel'],
        ['udc' => '005',   'icon' => 'insights'],
        ['udc' => '72',    'icon' => 'architecture'],
        ['udc' => '61',    'icon' => 'medical_services'],
        ['udc' => '37',    'icon' => 'school'],
        ['udc' => '159.9', 'icon' => 'psychology'],
    ],

    'collections' => [
        ['udc' => '62',    'icon' => 'engineering'],
        ['udc' => '004',   'icon' => 'terminal'],
        ['udc' => '005',   'icon' => 'business_center'],
        ['udc' => '33',    'icon' => 'trending_up'],
        ['udc' => '34',    'icon' => 'gavel'],
        ['udc' => '61',    'icon' => 'medical_services'],
        ['udc' => '94',    'icon' => 'history_edu'],
        ['udc' => '159.9', 'icon' => 'psychology'],
        ['udc' => '80',    'icon' => 'translate'],
        ['udc' => '72',    'icon' => 'architecture'],
    ],

    // ════════════════════════════════════════════════════════════════
    'ru' => [
        'faculty' => [
            'kicker' => 'Подразделения фонда',
            'title' => 'Книги по библиотечным фондам',
            'lead' => 'Названия соответствуют подразделениям, зарегистрированным в каталоге.',
            'cta' => 'Показать книги',
            'count_label' => 'раздел фонда',
            'all' => 'Открыть каталог',
            'names' => [
                'econ' => 'Экономическая библиотека',
                'tech' => 'Технологическая библиотека',
                'engit' => 'Библиотека колледжа',
            ],
        ],

        'arrivals' => [
            'kicker' => 'Пополнение фонда',
            'title' => 'Новые поступления',
            'lead' => 'Издания, недавно добавленные в электронный каталог библиотеки.',
            'all' => 'Весь каталог',
            'details' => 'Подробнее',
            'available' => 'Доступно',
            'unavailable' => 'Все выданы',
            'no_holdings' => 'Экземпляры не зарегистрированы',
            'copies' => 'экз.',
            'prev' => 'Предыдущие поступления',
            'next' => 'Следующие поступления',
            'empty' => 'Каталог пополняется. Откройте поиск по фонду, чтобы посмотреть доступные материалы.',
            'no_publisher' => 'Издательство не указано',
        ],

        'collections' => [
            'kicker' => 'Тематические разделы',
            'title' => 'Коллекции библиотеки',
            'lead' => 'Основные разделы фонда по универсальной десятичной классификации.',
            'all' => 'Вся классификация',
            'count_label' => 'изданий',
            'names' => [
                '62' => 'Инженерия',
                '004' => 'Информационные технологии',
                '005' => 'Бизнес и менеджмент',
                '33' => 'Экономика',
                '34' => 'Право',
                '61' => 'Медицина',
                '94' => 'История',
                '159.9' => 'Психология',
                '80' => 'Филология',
                '72' => 'Архитектура',
            ],
            'descriptions' => [
                '62' => 'Технические дисциплины, машиностроение, энергетика и промышленные технологии.',
                '004' => 'Программирование, обработка данных, сети и информационная безопасность.',
                '005' => 'Управление организацией, проектный менеджмент и деловое администрирование.',
                '33' => 'Экономическая теория, финансы, учёт и региональная экономика.',
                '34' => 'Правовые дисциплины, законодательство и юридическая практика.',
                '61' => 'Медицинские науки, здравоохранение и санитарные дисциплины.',
                '94' => 'Всеобщая и отечественная история, источниковедение и археология.',
                '159.9' => 'Общая и прикладная психология, психодиагностика и педагогическая психология.',
                '80' => 'Языкознание, литературоведение и филологические исследования.',
                '72' => 'Архитектура, градостроительство и проектирование зданий.',
            ],
        ],

        'stats' => [
            'kicker' => 'Библиотека в цифрах',
            'title' => 'Статистика библиотеки',
            'lead' => 'Показатели рассчитываются по текущему состоянию системы.',
            'items' => [],
        ],

        'faq' => [
            'kicker' => 'Поддержка читателей',
            'title' => 'Часто задаваемые вопросы',
            'lead' => 'Короткие ответы на вопросы о доступе, выдаче и электронных сервисах библиотеки.',
            'more' => 'Правила пользования библиотекой',
            'items' => [
                [
                    'q' => 'Как получить читательский билет?',
                    'a' => 'Каталог и публичные описания доступны без отдельного билета. Для входа в личный кабинет используйте выданные университетом учётные данные; условия электронных материалов указаны в их карточках. Порядок получения печатных изданий уточните у библиотеки по контактам на сайте.',
                ],
                [
                    'q' => 'Как войти в личный кабинет?',
                    'a' => 'Нажмите «Войти» в шапке сайта и используйте выданные университетом учётные данные. Если доступ не предоставлен, свяжитесь с библиотекой. Гостям доступны каталог, описания ресурсов и публичные записи научного репозитория без входа.',
                ],
                [
                    'q' => 'Как продлить книгу?',
                    'a' => 'Откройте историю выдач в личном кабинете. Если продление разрешено для текущей выдачи, там будут показаны действие и новый срок.',
                ],
                [
                    'q' => 'Как забронировать литературу?',
                    'a' => 'Найдите издание в каталоге и откройте его карточку. Кнопка бронирования показывается только когда запрос доступен; текущие условия и сроки будут указаны в личном кабинете.',
                ],
                [
                    'q' => 'Как пользоваться электронной библиотекой?',
                    'a' => 'Для каждого опубликованного электронного материала интерфейс показывает доступные действия и условия: просмотр, скачивание (если разрешено) или необходимость входа.',
                ],
                [
                    'q' => 'Как получить доступ из дома?',
                    'a' => 'Каталог, публичные записи репозитория и описания ресурсов открыты из любой точки без входа. Доступ к личному кабинету и электронным материалам зависит от вашей учётной записи и условий конкретного материала. Условия внешних ресурсов указаны в их карточках на странице «Ресурсы».',
                ],
                [
                    'q' => 'Как связаться с библиотекарем?',
                    'a' => 'На странице контактов указаны подтверждённые электронная почта, телефон, часы работы и канал обращения для авторизованных читателей. Точное место обслуживания перед визитом уточните у библиотеки.',
                ],
            ],
        ],
    ],

    // ════════════════════════════════════════════════════════════════
    'kk' => [
        'faculty' => [
            'kicker' => 'Қор бөлімшелері',
            'title' => 'Кітапхана қорлары бойынша кітаптар',
            'lead' => 'Атаулар каталогта тіркелген бөлімшелерге сәйкес келеді.',
            'cta' => 'Кітаптарды көрсету',
            'count_label' => 'қор бөлімі',
            'all' => 'Каталогты ашу',
            'names' => [
                'econ' => 'Экономикалық кітапхана',
                'tech' => 'Технологиялық кітапхана',
                'engit' => 'Колледж кітапханасы',
            ],
        ],

        'arrivals' => [
            'kicker' => 'Қор толықтыру',
            'title' => 'Жаңа түсімдер',
            'lead' => 'Кітапхананың электрондық каталогына жақында қосылған басылымдар.',
            'all' => 'Толық каталог',
            'details' => 'Толығырақ',
            'available' => 'Қолжетімді',
            'unavailable' => 'Барлығы берілген',
            'no_holdings' => 'Даналар тіркелмеген',
            'copies' => 'дана',
            'prev' => 'Алдыңғы түсімдер',
            'next' => 'Келесі түсімдер',
            'empty' => 'Каталог толықтырылуда. Қолжетімді материалдарды көру үшін қор бойынша іздеуді ашыңыз.',
            'no_publisher' => 'Баспасы көрсетілмеген',
        ],

        'collections' => [
            'kicker' => 'Тақырыптық бөлімдер',
            'title' => 'Кітапхана жинақтары',
            'lead' => 'Әмбебап ондық жіктеу бойынша қордың негізгі бөлімдері.',
            'all' => 'Толық жіктеу',
            'count_label' => 'басылым',
            'names' => [
                '62' => 'Инженерия',
                '004' => 'Ақпараттық технологиялар',
                '005' => 'Бизнес және менеджмент',
                '33' => 'Экономика',
                '34' => 'Құқық',
                '61' => 'Медицина',
                '94' => 'Тарих',
                '159.9' => 'Психология',
                '80' => 'Филология',
                '72' => 'Сәулет',
            ],
            'descriptions' => [
                '62' => 'Техникалық пәндер, машина жасау, энергетика және өнеркәсіптік технологиялар.',
                '004' => 'Бағдарламалау, деректерді өңдеу, желілер және ақпараттық қауіпсіздік.',
                '005' => 'Ұйымды басқару, жобалық менеджмент және іскерлік әкімшілендіру.',
                '33' => 'Экономикалық теория, қаржы, есеп және өңірлік экономика.',
                '34' => 'Құқықтық пәндер, заңнама және заң практикасы.',
                '61' => 'Медицина ғылымдары, денсаулық сақтау және санитарлық пәндер.',
                '94' => 'Дүниежүзілік және отандық тарих, деректану мен археология.',
                '159.9' => 'Жалпы және қолданбалы психология, психодиагностика, педагогикалық психология.',
                '80' => 'Тіл білімі, әдебиеттану және филологиялық зерттеулер.',
                '72' => 'Сәулет, қала құрылысы және ғимараттарды жобалау.',
            ],
        ],

        'stats' => [
            'kicker' => 'Кітапхана сандармен',
            'title' => 'Кітапхана статистикасы',
            'lead' => 'Көрсеткіштер жүйенің ағымдағы күйі бойынша есептеледі.',
            'items' => [],
        ],

        'faq' => [
            'kicker' => 'Оқырмандарды қолдау',
            'title' => 'Жиі қойылатын сұрақтар',
            'lead' => 'Қолжетімділік, беру және кітапхананың электрондық сервистері туралы қысқа жауаптар.',
            'more' => 'Кітапхананы пайдалану ережелері',
            'items' => [
                [
                    'q' => 'Оқырман билетін қалай алуға болады?',
                    'a' => 'Каталог пен ашық сипаттамалар бөлек билетсіз қолжетімді. Жеке кабинетке кіру үшін университет берген есептік жазбаны пайдаланыңыз; электрондық материалдың шарттары оның карточкасында көрсетіледі. Баспа басылымдарын алу тәртібін сайттағы байланыстар арқылы кітапханадан анықтаңыз.',
                ],
                [
                    'q' => 'Жеке кабинетке қалай кіруге болады?',
                    'a' => 'Сайттың жоғарғы жағындағы «Кіру» түймесін басып, университет берген есептік жазбаны пайдаланыңыз. Қолжетімділік берілмесе, кітапханаға хабарласыңыз. Қонақтарға каталог, ресурс сипаттамалары және ғылыми репозиторийдің ашық жазбалары кірусіз қолжетімді.',
                ],
                [
                    'q' => 'Кітапты қалай ұзартуға болады?',
                    'a' => 'Жеке кабинеттегі беру тарихын ашыңыз. Егер ағымдағы беруді ұзартуға рұқсат етілсе, сол жерде әрекет пен жаңа мерзім көрсетіледі.',
                ],
                [
                    'q' => 'Әдебиетті қалай брондауға болады?',
                    'a' => 'Каталогтан басылымды тауып, оның карточкасын ашыңыз. Брондау түймесі сұрау қолжетімді болғанда ғана көрсетіледі; ағымдағы шарттар мен мерзімдер жеке кабинетте беріледі.',
                ],
                [
                    'q' => 'Электрондық кітапхананы қалай пайдалану керек?',
                    'a' => 'Әр жарияланған электрондық материал үшін интерфейс қолжетімді әрекеттер мен шарттарды көрсетеді: қарау, рұқсат етілсе жүктеу немесе жүйеге кіру қажеттілігі.',
                ],
                [
                    'q' => 'Үйден қалай қол жеткізуге болады?',
                    'a' => 'Каталог, репозиторийдің ашық жазбалары және ресурс сипаттамалары кез келген жерден кірусіз қолжетімді. Жеке кабинет пен электрондық материалдарға қолжетімділік есептік жазбаңызға және нақты материалдың шарттарына байланысты. Сыртқы ресурстардың шарттары «Ресурстар» бетіндегі карточкаларда көрсетілген.',
                ],
                [
                    'q' => 'Кітапханашымен қалай байланысуға болады?',
                    'a' => 'Байланыс бетінде расталған электрондық пошта, телефон, жұмыс уақыты және авторизацияланған оқырмандарға арналған өтініш арнасы көрсетілген. Келмес бұрын нақты қызмет көрсету орнын кітапханадан анықтаңыз.',
                ],
            ],
        ],
    ],

    // ════════════════════════════════════════════════════════════════
    'en' => [
        'faculty' => [
            'kicker' => 'Library collections',
            'title' => 'Books by library collection',
            'lead' => 'Names match the collection units registered in the catalog.',
            'cta' => 'Show books',
            'count_label' => 'service unit',
            'all' => 'Open catalog',
            'names' => [
                'econ' => 'Economics Library',
                'tech' => 'Technology Library',
                'engit' => 'College Library',
            ],
        ],

        'arrivals' => [
            'kicker' => 'Collection growth',
            'title' => 'New additions',
            'lead' => 'Editions recently added to the electronic catalog of the library.',
            'all' => 'Full catalog',
            'details' => 'Details',
            'available' => 'Available',
            'unavailable' => 'All on loan',
            'no_holdings' => 'No copies are registered',
            'copies' => 'copies',
            'prev' => 'Previous additions',
            'next' => 'Next additions',
            'empty' => 'The catalog is being populated. Open the collection search to see available materials.',
            'no_publisher' => 'Publisher not recorded',
        ],

        'collections' => [
            'kicker' => 'Subject sections',
            'title' => 'Library collections',
            'lead' => 'The principal sections of the collection under Universal Decimal Classification.',
            'all' => 'Full classification',
            'count_label' => 'items',
            'names' => [
                '62' => 'Engineering',
                '004' => 'Information technology',
                '005' => 'Business and management',
                '33' => 'Economics',
                '34' => 'Law',
                '61' => 'Medicine',
                '94' => 'History',
                '159.9' => 'Psychology',
                '80' => 'Philology',
                '72' => 'Architecture',
            ],
            'descriptions' => [
                '62' => 'Technical disciplines, mechanical engineering, energy and industrial technology.',
                '004' => 'Programming, data processing, networks and information security.',
                '005' => 'Organisational management, project management and business administration.',
                '33' => 'Economic theory, finance, accounting and regional economics.',
                '34' => 'Legal disciplines, legislation and juridical practice.',
                '61' => 'Medical sciences, public health and sanitary disciplines.',
                '94' => 'General and national history, source studies and archaeology.',
                '159.9' => 'General and applied psychology, psychodiagnostics and educational psychology.',
                '80' => 'Linguistics, literary studies and philological research.',
                '72' => 'Architecture, urban planning and building design.',
            ],
        ],

        'stats' => [
            'kicker' => 'The library in numbers',
            'title' => 'Library statistics',
            'lead' => 'Figures are calculated from the current system state.',
            'items' => [],
        ],

        'faq' => [
            'kicker' => 'Reader support',
            'title' => 'Frequently asked questions',
            'lead' => 'Short answers about access, borrowing and the digital services of the library.',
            'more' => 'Library usage rules',
            'items' => [
                [
                    'q' => 'How do I get a library card?',
                    'a' => 'The catalog and public descriptions are available without a separate card. Use the account issued by the university to sign in; each digital-material record states its own access conditions. Confirm the procedure for borrowing print editions with the library through the contacts listed on this site.',
                ],
                [
                    'q' => 'How do I sign in to the member dashboard?',
                    'a' => 'Press "Sign in" in the site header and use the account issued by the university. If you have not been granted access, contact the library. Guests can browse the catalog, resource descriptions and public scholarly-repository records without signing in.',
                ],
                [
                    'q' => 'How do I renew a book?',
                    'a' => 'Open borrowing history in your dashboard. If renewal is allowed for the current loan, the action and the new due date will be shown there.',
                ],
                [
                    'q' => 'How do I reserve an item?',
                    'a' => 'Find the edition in the catalog and open its record. The reservation action is shown only when a request is available; current terms and dates are stated in the reader dashboard.',
                ],
                [
                    'q' => 'How does the digital library work?',
                    'a' => 'For each published digital material, the interface shows the available actions and conditions: viewing, downloading when permitted, or a sign-in requirement.',
                ],
                [
                    'q' => 'How do I get access from home?',
                    'a' => 'The catalog, public repository records and resource descriptions are available from anywhere without signing in. Access to the dashboard and digital materials depends on your account and the conditions of each material. Conditions for external resources are stated on their cards on the Resources page.',
                ],
                [
                    'q' => 'How do I contact a librarian?',
                    'a' => 'The contacts page lists the verified email, phone number, opening-hours information and inquiry route for signed-in readers. Confirm the exact service point with the library before visiting.',
                ],
            ],
        ],
    ],
];
