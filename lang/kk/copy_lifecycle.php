<?php

return [
    'inventory'=>'Қор күйі','circulation'=>'Қолжетімділік',
    'inventory_statuses'=>['active'=>'Қорда','damaged'=>'Зақымдалған','repair'=>'Жөндеуде','lost'=>'Жоғалған','written_off'=>'Есептен шығарылған'],
    'circulation_statuses'=>['available'=>'Қолжетімді','reserved'=>'Броньдалған','on_hold'=>'Бронь сөресінде','on_loan'=>'Оқырманда','in_transfer'=>'Тасымалдауда','unavailable'=>'Қолжетімсіз'],
    'fields'=>['writeoff_date'=>'Есептен шығару күні','writeoff_act'=>'Есептен шығару актісі','writeoff_reason'=>'Есептен шығару себебі'],
    'writeoff_warning'=>'Дана дерекқорда қалады, бірақ беруге қолжетімсіз болады. Тағайындалған броньдар жойылып, әрекет КСУ-2 мен аудитте тіркеледі.',
    'reservation_cancel_reason'=>':copy данасы айналымнан шығарылды.',
    'validation'=>['written_off_immutable'=>'Есептен шығарылған дананы қалыпты күй әрекетімен қорға қайтаруға болмайды.','no_state_change'=>'Дана сұралған күйде тұр.'],
    'legacy'=>[
        'title'=>'MARC-SQL техникалық тарихы','description'=>'Салыстыруға арналған өзгермейтін бастапқы мәндер; жұмыс карточкасын өңдеу оларды қайта жазбайды.',
        'fields'=>['legacy_inv_id'=>'Source INV ID','legacy_doc_id'=>'Source DOC ID','legacy_inventory_number'=>'Бастапқы түгендеу №','sigla_code'=>'Сигла коды','legacy_sigla_id'=>'Source SIGLA_ID','local_library_code'=>'Кітапхана коды','fund_raw'=>'T090w / fund_raw','price_raw'=>'Бастапқы баға','accounting_mode_raw'=>'Бастапқы есеп түрі','legacy_state_raw'=>'INV.STATE','legacy_state_label'=>'Бастапқы күй','legacy_notes'=>'Бастапқы ескертпелер'],
    ],
];
