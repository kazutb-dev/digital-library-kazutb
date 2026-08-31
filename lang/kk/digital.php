<?php

return [
    'ui' => ['eyebrow' => 'Цифрлық кітапхана', 'subtitle' => 'Өңдеу барысы, құқықтар және қорғалған қолжетімділік саясаты', 'search' => 'Материалдарды іздеу', 'all_statuses' => 'Барлық күйлер', 'workflow' => 'Жұмыс барысы', 'versions' => 'Нұсқалар', 'reason' => 'Әрекет себебі', 'no_versions' => 'Файл нұсқалары әлі жоқ'],
    'fields' => ['type' => 'Түрі', 'language' => 'Тілі', 'title' => 'Атауы', 'description' => 'Сипаттамасы', 'source' => 'Дереккөз', 'rights_holder' => 'Құқық иесі', 'copyright' => 'Авторлық құқық күйі', 'licence' => 'Лицензия', 'access' => 'Қолжетімділік', 'preview_policy' => 'Қарау', 'download_policy' => 'Жүктеу', 'print_policy' => 'Басып шығару', 'copy_policy' => 'Көшіру', 'campus_only' => 'Тек кампуста', 'embargo_until' => 'Эмбарго мерзімі'],
    'statuses' => ['uploaded' => 'Жүктелді', 'quarantined' => 'Карантинде', 'metadata_review' => 'Метадеректерді тексеру', 'rights_review' => 'Құқықтарды тексеру', 'processing' => 'Өңделуде', 'ready_for_review' => 'Тексеруге дайын', 'approved' => 'Мақұлданды', 'published' => 'Жарияланды', 'restricted' => 'Шектелді', 'rejected' => 'Қабылданбады', 'withdrawn' => 'Кері қайтарылды', 'archived' => 'Мұрағатта', 'processing_failed' => 'Өңдеу қатесі'],
    'types' => ['book_pdf' => 'Кітап PDF-і', 'image_collection' => 'Суреттер жинағы', 'presentation' => 'Презентация', 'scientific_work' => 'Ғылыми жұмыс', 'methodological_material' => 'Әдістемелік материал', 'supplementary_file' => 'Қосымша файл'],
    'file_types' => ['pdf' => 'PDF', 'image' => 'Сурет', 'presentation' => 'Презентация', 'document' => 'Құжат'],
    'copyright' => ['public_domain' => 'Қоғамдық игілік', 'permission_granted' => 'Рұқсат берілген', 'university_owned' => 'Университет меншігі', 'licensed' => 'Лицензияланған', 'restricted' => 'Шектелген', 'unknown' => 'Белгісіз'],
    'access' => ['public' => 'Ашық', 'authenticated' => 'Кірген пайдаланушыларға', 'student' => 'Студенттерге', 'faculty' => 'Оқытушыларға', 'staff' => 'Қызметкерлерге', 'librarian' => 'Кітапханашыларға', 'campus_only' => 'Тек кампуста', 'restricted_roles' => 'Таңдалған рөлдерге', 'embargoed' => 'Эмбаргода', 'metadata_only' => 'Тек метадеректер', 'restricted' => 'Шектелген'],
    'options' => ['none' => 'Жоқ', 'metadata' => 'Тек метадеректер', 'sample' => 'Үзінді', 'full' => 'Толық', 'disabled' => 'Тыйым салынған', 'allowed' => 'Рұқсат етілген'],
    'version' => ['initial_upload' => 'Бастапқы жүктеу', 'replacement' => 'Файлдың жаңа нұсқасы'],
    'validation' => ['duplicate_checksum' => 'Бұл файл жүйеде бұрыннан бар.', 'storage_failed' => 'Файлды қорғалған қоймаға сақтау мүмкін болмады.', 'invalid_transition' => 'Бұл күйге өтуге болмайды.', 'rights_required' => 'Жариялау үшін құқық иесі, дереккөз және лицензия расталуы тиіс.'],
    'external' => [
        'fields' => ['access_method' => 'Қолжетімділік тәсілі', 'publication_status' => 'Жариялау күйі', 'guest_access' => 'Қонақтарға қолжетімді', 'campus_only' => 'Тек кампус желісінде', 'login_required' => 'Кіру қажет', 'contract_number' => 'Шарт нөмірі', 'contract_starts_at' => 'Шарттың басталуы', 'contract_ends_at' => 'Шарттың аяқталуы', 'renewal_at' => 'Ұзарту күні', 'licence_file' => 'Лицензиялық құжат (қорғалған сақтау)', 'contract_change_reason' => 'Шартты өзгерту себебі', 'internal_notes' => 'Ішкі ескертпелер'],
        'access_methods' => ['public_url' => 'Ашық URL', 'institutional_sso' => 'Университет SSO', 'ip_based' => 'IP бойынша', 'campus_only' => 'Тек кампуста', 'personal_account' => 'Жеке тіркелгі', 'librarian_mediated' => 'Кітапханашы арқылы', 'manual_instructions' => 'Нұсқаулық бойынша'],
        'publication_statuses' => ['draft' => 'Жоба', 'review' => 'Тексеруде', 'published' => 'Жарияланған', 'archived' => 'Мұрағатта'],
        'licence_notice_title' => 'Сыртқы ресурс лицензиясы', 'licence_notice_body' => '«:title» ресурсының лицензия мерзімі: :days күн.',
        'health_outage_title' => 'Сыртқы ресурс қолжетімсіз', 'health_outage_body' => 'Анонимді автоматты тексеру «:title» ресурсын аша алмады. Мекенжайы мен қолжетімділігін тексеріңіз.',
    ],
];
