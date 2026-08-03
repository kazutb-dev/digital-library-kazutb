<?php

// Homepage sections 2–6 (faculty picks, new arrivals, collections, statistics, FAQ).
//
// Copy only. Book counts are NOT stored here: they are resolved from the real
// catalog in the `/` route and rendered only when the catalog can answer, so a
// card never shows an invented number.
//
// The institutional figures in `stats` are supplied by the library as public
// claims about the physical service (reading rooms, fund availability); the
// platform cannot compute them today.

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
            'kicker' => 'Абонементы библиотеки',
            'title' => 'Три основных абонемента',
            'lead' => 'Вместо рекомендаций кафедр здесь показаны реальные абонементы по факультетам.',
            'cta' => 'Открыть абонемент',
            'count_label' => 'раздел фонда',
            'all' => 'Все абонементы',
            'names' => [
                'econ' => 'Абонемент экономического факультета',
                'tech' => 'Абонемент технологического факультета',
                'engit' => 'Абонемент факультета инжиниринга и информационных технологий',
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
            'no_holdings' => 'Данные о наличии уточняются',
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
            'lead' => 'Фонд и пространства библиотеки поддерживают ежедневную учёбу, самостоятельную работу и исследования студентов.',
            'items' => [
                ['value' => '46 000+',  'label' => 'Уникальных книг в библиотеке', 'icon' => 'auto_stories'],
                ['value' => '100 000+', 'label' => 'Печатных экземпляров', 'icon' => 'library_books'],
                ['value' => '3',        'label' => 'Читальных зала', 'icon' => 'meeting_room'],
            ],
        ],

        'faq' => [
            'kicker' => 'Поддержка читателей',
            'title' => 'Часто задаваемые вопросы',
            'lead' => 'Короткие ответы на вопросы о доступе, выдаче и электронных сервисах библиотеки.',
            'more' => 'Правила пользования библиотекой',
            'items' => [
                [
                    'q' => 'Как получить читательский билет?',
                    'a' => 'Отдельный билет для цифровых сервисов не нужен: доступ к каталогу, электронным материалам и личному кабинету открывается по корпоративной учётной записи университета. Для получения печатных изданий обратитесь на пункт выдачи с удостоверением университета — сотрудник зарегистрирует вас в системе обслуживания.',
                ],
                [
                    'q' => 'Как войти в личный кабинет?',
                    'a' => 'Нажмите «Войти» в шапке сайта и введите корпоративный логин и пароль университета. Отдельная регистрация не требуется — учётные записи создаются централизованно. Гостям доступны каталог, описания ресурсов и научный репозиторий без входа.',
                ],
                [
                    'q' => 'Как продлить книгу?',
                    'a' => 'Продление доступно в личном кабинете в разделе истории выдач. Издание можно продлить один раз на тот же срок, если на него нет активной брони другого читателя. При наличии просроченных выдач продление и новые брони недоступны до возврата.',
                ],
                [
                    'q' => 'Как забронировать литературу?',
                    'a' => 'Найдите издание в каталоге, откройте карточку и нажмите «Забронировать». Бронировать можно только доступные экземпляры, одновременно — до трёх. Бронь подтверждает библиотекарь, после чего экземпляр хранится на пункте выдачи 3 дня; при неявке бронь автоматически снимается.',
                ],
                [
                    'q' => 'Как пользоваться электронной библиотекой?',
                    'a' => 'Электронные материалы открываются в контролируемом просмотрщике прямо в браузере. Скачивание файлов не предусмотрено — это условие соглашений с правообладателями. Обложки и ознакомительные фрагменты видны всем, полный текст — авторизованным читателям при наличии соответствующего уровня доступа.',
                ],
                [
                    'q' => 'Как получить доступ из дома?',
                    'a' => 'Каталог, репозиторий и описания ресурсов открыты из любой точки без входа. Для электронных материалов и личного кабинета достаточно войти по корпоративной учётной записи. Лицензионные внешние базы имеют собственные условия доступа — они указаны в карточке каждого ресурса на странице «Ресурсы».',
                ],
                [
                    'q' => 'Как пользоваться AI-помощником?',
                    'a' => 'Интеллектуальный подбор литературы находится в разработке и пока недоступен читателям. Сегодня эту задачу выполняет библиограф: отправьте тему исследования или курса через страницу контактов, и специалист подготовит подборку источников и черновик списка литературы.',
                ],
                [
                    'q' => 'Как связаться с библиотекарем?',
                    'a' => 'Через страницу контактов: там указаны электронная почта, телефоны и часы работы, а также форма обращения для авторизованных читателей. Очно — на пунктах выдачи: технологический фонд (1/200), фонд колледжа (1/202) и экономический фонд (1/203).',
                ],
            ],
        ],
    ],

    // ════════════════════════════════════════════════════════════════
    'kk' => [
        'faculty' => [
            'kicker' => 'Кітапхана абонементтері',
            'title' => 'Үш негізгі абонемент',
            'lead' => 'Кафедра ұсыныстарының орнына мұнда факультеттер бойынша қызмет көрсететін нақты абонементтер берілген.',
            'cta' => 'Абонементті ашу',
            'count_label' => 'қор бөлімі',
            'all' => 'Барлық абонементтер',
            'names' => [
                'econ' => 'Экономикалық факультет абонементі',
                'tech' => 'Технологиялық факультет абонементі',
                'engit' => 'Инжиниринг және ақпараттық технологиялар факультетінің абонементі',
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
            'no_holdings' => 'Қор туралы дерек нақтылануда',
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
            'lead' => 'Кітапхана қоры мен кеңістіктері студенттердің күнделікті оқуын, өзіндік жұмысын және зерттеулерін қолдайды.',
            'items' => [
                ['value' => '46 000+',  'label' => 'Кітапханадағы бірегей кітап', 'icon' => 'auto_stories'],
                ['value' => '100 000+', 'label' => 'Баспа данасы', 'icon' => 'library_books'],
                ['value' => '3',        'label' => 'Оқу залы', 'icon' => 'meeting_room'],
            ],
        ],

        'faq' => [
            'kicker' => 'Оқырмандарды қолдау',
            'title' => 'Жиі қойылатын сұрақтар',
            'lead' => 'Қолжетімділік, беру және кітапхананың электрондық сервистері туралы қысқа жауаптар.',
            'more' => 'Кітапхананы пайдалану ережелері',
            'items' => [
                [
                    'q' => 'Оқырман билетін қалай алуға болады?',
                    'a' => 'Цифрлық сервистер үшін бөлек билет қажет емес: каталогқа, электрондық материалдарға және жеке кабинетке қолжетімділік университеттің корпоративтік есептік жазбасы арқылы ашылады. Баспа басылымдарын алу үшін университет куәлігімен беру пунктіне жүгініңіз — қызметкер сізді қызмет көрсету жүйесіне тіркейді.',
                ],
                [
                    'q' => 'Жеке кабинетке қалай кіруге болады?',
                    'a' => 'Сайттың жоғарғы жағындағы «Кіру» түймесін басып, университеттің корпоративтік логині мен құпиясөзін енгізіңіз. Бөлек тіркелу қажет емес — есептік жазбалар орталықтандырылып жасалады. Қонақтарға каталог, ресурс сипаттамалары және ғылыми репозиторий кірусіз қолжетімді.',
                ],
                [
                    'q' => 'Кітапты қалай ұзартуға болады?',
                    'a' => 'Ұзарту жеке кабинеттегі беру тарихы бөлімінде қолжетімді. Басылымды басқа оқырманның белсенді броны болмаса, сол мерзімге бір рет ұзартуға болады. Мерзімі өткен берулер болса, ұзарту мен жаңа брондау қайтарылғанға дейін мүмкін емес.',
                ],
                [
                    'q' => 'Әдебиетті қалай брондауға болады?',
                    'a' => 'Каталогтан басылымды тауып, карточкасын ашыңыз да «Брондау» түймесін басыңыз. Тек қолжетімді даналарды брондауға болады, бір мезгілде — үшеуге дейін. Бронды кітапханашы растайды, содан кейін дана беру пунктінде 3 күн сақталады; келмеген жағдайда бронь автоматты түрде алынады.',
                ],
                [
                    'q' => 'Электрондық кітапхананы қалай пайдалану керек?',
                    'a' => 'Электрондық материалдар браузерде бақыланатын қарау құралында ашылады. Файлдарды жүктеп алу қарастырылмаған — бұл құқық иеленушілермен келісім шарты. Мұқабалар мен таныстыру үзінділері барлығына көрінеді, толық мәтін — тиісті қолжетімділік деңгейі бар авторизацияланған оқырмандарға.',
                ],
                [
                    'q' => 'Үйден қалай қол жеткізуге болады?',
                    'a' => 'Каталог, репозиторий және ресурс сипаттамалары кез келген жерден кірусіз ашық. Электрондық материалдар мен жеке кабинет үшін корпоративтік есептік жазбамен кіру жеткілікті. Лицензияланған сыртқы дерекқорлардың өз шарттары бар — олар «Ресурстар» бетіндегі әр ресурс карточкасында көрсетілген.',
                ],
                [
                    'q' => 'AI-көмекшіні қалай пайдалану керек?',
                    'a' => 'Әдебиетті интеллектуалды іріктеу әзірленуде және оқырмандарға әзірге қолжетімді емес. Бүгінде бұл міндетті библиограф орындайды: зерттеу немесе курс тақырыбын байланыс беті арқылы жіберіңіз, маман дереккөздер іріктемесін және әдебиеттер тізімінің жобасын дайындайды.',
                ],
                [
                    'q' => 'Кітапханашымен қалай байланысуға болады?',
                    'a' => 'Байланыс беті арқылы: онда электрондық пошта, телефондар мен жұмыс уақыты, сондай-ақ авторизацияланған оқырмандарға арналған өтініш нысаны көрсетілген. Тікелей — беру пункттерінде: технологиялық қор (1/200), колледж қоры (1/202) және экономикалық қор (1/203).',
                ],
            ],
        ],
    ],

    // ════════════════════════════════════════════════════════════════
    'en' => [
        'faculty' => [
            'kicker' => 'Library lending desks',
            'title' => 'Three main lending desks',
            'lead' => 'Instead of department recommendations, this section highlights the real reader service points by faculty.',
            'cta' => 'Open desk',
            'count_label' => 'service unit',
            'all' => 'All desks',
            'names' => [
                'econ' => 'Economics faculty desk',
                'tech' => 'Technology faculty desk',
                'engit' => 'Engineering and information technology faculty desk',
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
            'no_holdings' => 'Holdings data pending',
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
            'lead' => 'The library collection and its spaces support students’ daily learning, independent work, and research.',
            'items' => [
                ['value' => '46,000+',  'label' => 'Unique books in the library', 'icon' => 'auto_stories'],
                ['value' => '100,000+', 'label' => 'Printed copies', 'icon' => 'library_books'],
                ['value' => '3',        'label' => 'Reading rooms', 'icon' => 'meeting_room'],
            ],
        ],

        'faq' => [
            'kicker' => 'Reader support',
            'title' => 'Frequently asked questions',
            'lead' => 'Short answers about access, borrowing and the digital services of the library.',
            'more' => 'Library usage rules',
            'items' => [
                [
                    'q' => 'How do I get a library card?',
                    'a' => 'No separate card is needed for digital services: the catalog, digital materials and member dashboard open with your corporate university account. To borrow print editions, visit a service desk with your university ID and a member of staff will register you in the circulation system.',
                ],
                [
                    'q' => 'How do I sign in to the member dashboard?',
                    'a' => 'Press "Sign in" in the site header and enter your corporate university login and password. No separate registration is required — accounts are created centrally. Guests can browse the catalog, resource descriptions and the scholarly repository without signing in.',
                ],
                [
                    'q' => 'How do I renew a book?',
                    'a' => 'Renewal is available in your dashboard under borrowing history. An item can be renewed once for the same period, provided no other reader holds an active reservation on it. While you have overdue loans, renewals and new reservations are unavailable until the items are returned.',
                ],
                [
                    'q' => 'How do I reserve an item?',
                    'a' => 'Find the edition in the catalog, open its record and press "Reserve". Only available copies can be reserved, up to three at a time. A librarian confirms the reservation, after which the copy is held at the service desk for 3 days; if it is not collected, the reservation expires automatically.',
                ],
                [
                    'q' => 'How does the digital library work?',
                    'a' => 'Digital materials open in a controlled viewer directly in the browser. File downloads are not provided — this is a condition of our agreements with rights holders. Covers and limited previews are visible to everyone; full text is available to signed-in readers with the appropriate access level.',
                ],
                [
                    'q' => 'How do I get access from home?',
                    'a' => 'The catalog, repository and resource descriptions are open from anywhere without signing in. Digital materials and the dashboard only require your corporate account. Licensed external databases have their own access conditions, stated on each resource card on the Resources page.',
                ],
                [
                    'q' => 'How do I use the AI assistant?',
                    'a' => 'Intelligent literature selection is under development and not yet available to readers. Today a bibliographer does this work: send your research or course topic through the contacts page and a specialist will prepare a set of sources and a draft reading list.',
                ],
                [
                    'q' => 'How do I contact a librarian?',
                    'a' => 'Through the contacts page, which lists email, phone numbers and opening hours, along with an inquiry form for signed-in readers. In person, visit a service desk: technology fund (1/200), college fund (1/202) or economics fund (1/203).',
                ],
            ],
        ],
    ],
];
