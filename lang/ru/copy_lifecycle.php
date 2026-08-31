<?php

return [
    'inventory' => 'Состояние фонда',
    'circulation' => 'Доступность',
    'inventory_statuses' => ['active'=>'В фонде','damaged'=>'Повреждён','repair'=>'В ремонте','lost'=>'Утерян','written_off'=>'Списан'],
    'circulation_statuses' => ['available'=>'Доступен','reserved'=>'Забронирован','on_hold'=>'На бронеполке','on_loan'=>'На руках','in_transfer'=>'В перемещении','unavailable'=>'Недоступен'],
    'fields' => ['writeoff_date'=>'Дата списания','writeoff_act'=>'Акт списания','writeoff_reason'=>'Причина списания'],
    'writeoff_warning' => 'Экземпляр останется в базе, но станет недоступен для выдачи. Назначенные брони будут отменены, действие попадёт в КСУ-2 и аудит.',
    'reservation_cancel_reason' => 'Экземпляр :copy выведен из оборота.',
    'validation' => ['written_off_immutable'=>'Списанный экземпляр нельзя вернуть в фонд обычным действием.','no_state_change'=>'Экземпляр уже находится в этом состоянии.'],
    'legacy' => [
        'title'=>'Техническая история MARC-SQL','description'=>'Исходные значения для сверки; они не изменяются вместе с рабочей карточкой.',
        'fields'=>['legacy_inv_id'=>'Source INV ID','legacy_doc_id'=>'Source DOC ID','legacy_inventory_number'=>'Исходный инвентарный №','sigla_code'=>'Код сиглы','legacy_sigla_id'=>'Source SIGLA_ID','local_library_code'=>'Код библиотеки','fund_raw'=>'T090w / fund_raw','price_raw'=>'Исходная цена','accounting_mode_raw'=>'Исходный вид учёта','legacy_state_raw'=>'INV.STATE','legacy_state_label'=>'Исходный статус','legacy_notes'=>'Исходные примечания'],
    ],
];
