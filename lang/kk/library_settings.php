<?php

$fields = [
    'max_active_reservations' => 'Белсенді бронь лимиті',
    'reservation_hold_days' => 'Алып кетуге сақтау мерзімі, күн',
    'max_active_loans' => 'Белсенді берілім лимиті',
    'standard_loan_period_days' => 'Стандартты беру мерзімі, күн',
    'renewal_allowed' => 'Рұқсат етілген ұзартуды қосу',
    'renewal_period_days' => 'Ұзарту мерзімі, күн',
    'max_renewals' => 'Ұзарту лимиті',
    'fine_per_overdue_day' => 'Кешіктірілген күнге айыппұл, ₸ (0 — өшіру)',
    'inventory_batch_scan_limit' => 'Түгендеу сессиясындағы скан саны',
    'inventory_numbering_enabled' => 'Мүлік нөмірін автоматты беру',
    'inventory_number_prefix' => 'Мүлік нөмірінің префиксі',
    'barcode_generation_enabled' => 'Штрихкодты автоматты жасау',
    'barcode_prefix' => 'Штрихкод префиксі',
    'ksu_numbering_enabled' => 'Жаңа КСУ нөмірлерін бөлу',
    'ksu_yearly_reset' => 'Әр жылға жеке КСУ реттілігі',
    'default_service_point' => 'Әдепкі қызмет көрсету пункті',
    'default_sigla' => 'Әдепкі сақтау сигласы',
];

return [
    'kicker' => 'Бақыланатын жұмыс саясаты',
    'title' => 'Кітапхана операцияларының баптаулары',
    'description' => 'Берілім, бронь, нөмірлеу және орналастыру баптаулары. Әр өзгеріс тексеріліп, аудит журналына жазылады.',
    'circulation' => 'Берілім және бронь',
    'numbering' => 'Жаңа түсімдерді нөмірлеу',
    'numbering_help' => 'Бұл ауыстырып-қосқыштар тек жаңа расталған түсімдерге әсер етеді. Legacy деректерге жоқ штрихкод немесе КСУ жасалмайды.',
    'inventory' => 'Түгендеу және орналастыру',
    'save' => 'Баптауларды сақтау',
    'saved' => 'Кітапхана операцияларының баптаулары сақталды.',
    'fields' => $fields,
    'descriptions' => $fields,
];
