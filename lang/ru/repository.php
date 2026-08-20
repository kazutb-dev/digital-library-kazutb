<?php

return [
    'fields' => ['source' => 'Источник', 'rights_holder' => 'Правообладатель', 'copyright_status' => 'Статус авторских прав', 'licence_type' => 'Лицензия', 'licence_text' => 'Условия лицензии / разрешения', 'permission_date' => 'Дата разрешения', 'access_policy' => 'Политика доступа', 'embargo_until' => 'Эмбарго до', 'post_embargo_access_policy' => 'Доступ после эмбарго', 'primary_author_orcid' => 'ORCID основного автора (необязательно)', 'version_reason' => 'Причина новой версии', 'scheduled_for' => 'Опубликовать в'],
    'post_embargo_help' => 'Выберите явно утверждаемую политику, которая вступит в силу после окончания эмбарго.',
    'copyright' => ['unknown' => 'Неизвестно', 'public_domain' => 'Общественное достояние', 'permission_granted' => 'Разрешение получено', 'university_owned' => 'Права университета', 'licensed' => 'По лицензии', 'restricted' => 'Ограничено'],
    'access' => ['metadata_only' => 'Только метаданные', 'full_public' => 'Полный текст открыт', 'metadata_public_file_authenticated' => 'Метаданные открыты, файл после входа', 'campus_only' => 'Только в кампусе', 'restricted' => 'Ограниченный', 'embargoed' => 'Под эмбарго'],
    'validation' => ['invalid_transition' => 'Переход в этот статус недоступен.', 'rights_required' => 'До публикации необходимо подтвердить права.', 'reason_required' => 'Укажите причину решения.', 'schedule_required' => 'Выберите будущее время публикации.', 'approval_required' => 'Публикация возможна только после утверждения руководителем библиотеки.', 'pdf_required' => 'До публикации загрузите PDF-файл работы.', 'full_public_required' => 'Для публикации выберите полный открытый доступ.'],
];
