<?php

return [
    'ui' => ['eyebrow' => 'Цифровая библиотека', 'subtitle' => 'Маршрут обработки, права и политики защищённого доступа', 'search' => 'Поиск материалов', 'all_statuses' => 'Все статусы', 'workflow' => 'Рабочий процесс', 'versions' => 'Версии', 'reason' => 'Причина действия', 'no_versions' => 'Версий файла пока нет'],
    'fields' => ['type' => 'Тип', 'language' => 'Язык', 'title' => 'Название', 'description' => 'Описание', 'source' => 'Источник', 'rights_holder' => 'Правообладатель', 'copyright' => 'Статус авторских прав', 'licence' => 'Лицензия', 'access' => 'Доступ', 'preview_policy' => 'Просмотр', 'download_policy' => 'Скачивание', 'print_policy' => 'Печать', 'copy_policy' => 'Копирование', 'campus_only' => 'Только в кампусе', 'embargo_until' => 'Эмбарго до'],
    'statuses' => ['uploaded' => 'Загружен', 'quarantined' => 'На карантине', 'metadata_review' => 'Проверка метаданных', 'rights_review' => 'Проверка прав', 'processing' => 'Обработка', 'ready_for_review' => 'Готов к проверке', 'approved' => 'Одобрен', 'published' => 'Опубликован', 'restricted' => 'Ограничен', 'rejected' => 'Отклонён', 'withdrawn' => 'Отозван', 'archived' => 'В архиве', 'processing_failed' => 'Ошибка обработки'],
    'types' => ['book_pdf' => 'PDF книги', 'image_collection' => 'Коллекция изображений', 'presentation' => 'Презентация', 'scientific_work' => 'Научная работа', 'methodological_material' => 'Методический материал', 'supplementary_file' => 'Дополнительный файл'],
    'file_types' => ['pdf' => 'PDF', 'image' => 'Изображение', 'presentation' => 'Презентация', 'document' => 'Документ'],
    'copyright' => ['public_domain' => 'Общественное достояние', 'permission_granted' => 'Разрешение получено', 'university_owned' => 'Права университета', 'licensed' => 'По лицензии', 'restricted' => 'Ограничено', 'unknown' => 'Неизвестно'],
    'access' => ['public' => 'Открытый', 'authenticated' => 'После входа', 'student' => 'Студентам', 'faculty' => 'Преподавателям', 'staff' => 'Сотрудникам', 'librarian' => 'Библиотекарям', 'campus_only' => 'Только в кампусе', 'restricted_roles' => 'Выбранным ролям', 'embargoed' => 'Под эмбарго', 'metadata_only' => 'Только метаданные', 'restricted' => 'Ограниченный'],
    'options' => ['none' => 'Нет', 'metadata' => 'Только метаданные', 'sample' => 'Фрагмент', 'full' => 'Полностью', 'disabled' => 'Запрещено', 'allowed' => 'Разрешено'],
    'version' => ['initial_upload' => 'Первичная загрузка', 'replacement' => 'Новая версия файла'],
    'validation' => ['duplicate_checksum' => 'Этот файл уже зарегистрирован в системе.', 'storage_failed' => 'Не удалось сохранить файл в защищённом хранилище.', 'invalid_transition' => 'Переход в этот статус недоступен.', 'rights_required' => 'До публикации необходимо подтвердить правообладателя, источник и лицензию.'],
    'external' => [
        'fields' => ['access_method' => 'Способ доступа', 'publication_status' => 'Статус публикации', 'guest_access' => 'Доступ гостям', 'campus_only' => 'Только в сети кампуса', 'login_required' => 'Требуется вход', 'contract_number' => 'Номер договора', 'contract_starts_at' => 'Начало договора', 'contract_ends_at' => 'Окончание договора', 'renewal_at' => 'Дата продления', 'licence_file' => 'Лицензионный документ (защищённое хранение)', 'contract_change_reason' => 'Причина изменения договора', 'internal_notes' => 'Внутренние заметки'],
        'access_methods' => ['public_url' => 'Открытый URL', 'institutional_sso' => 'Университетский SSO', 'ip_based' => 'По IP', 'campus_only' => 'Только в кампусе', 'personal_account' => 'Личный аккаунт', 'librarian_mediated' => 'Через библиотекаря', 'manual_instructions' => 'По инструкции'],
        'publication_statuses' => ['draft' => 'Черновик', 'review' => 'На проверке', 'published' => 'Опубликован', 'archived' => 'В архиве'],
        'licence_notice_title' => 'Лицензия внешнего ресурса', 'licence_notice_body' => 'До окончания лицензии «:title» осталось дней: :days.',
        'health_outage_title' => 'Внешний ресурс недоступен', 'health_outage_body' => 'Автоматическая анонимная проверка не смогла открыть «:title». Проверьте адрес и доступность ресурса.',
    ],
];
