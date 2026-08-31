<?php

/*
|--------------------------------------------------------------------------
| Demo Login Identities (RBAC)
|--------------------------------------------------------------------------
|
| Backs the "Быстрый вход" cards on /login and the POST /login/demo/{role}
| route. These are real `users` rows created by DemoUserSeeder and carrying
| real Spatie roles — unlike config/demo_auth.php, which drives the older
| session-array login path and holds no database records.
|
| Gated by APP_DEMO_LOGIN_ENABLED. With the flag off the route returns 404 and
| the cards are not rendered. Never enable on a production deployment: the
| passwords below are fixed and public.
|
| `legacy_role` maps the Spatie role onto the role string the existing
| session-based middleware (EnsureMemberReader, EnsureLibrarianStaff,
| EnsureAdminStaff) expects, so both authorization stacks agree.
|
*/

return [

    'enabled' => (bool) env('APP_DEMO_LOGIN_ENABLED', false),

    'password' => env('APP_DEMO_LOGIN_PASSWORD', 'DemoAccess2026!'),

    'identities' => [

        'student' => [
            'email' => 'demo-student@kazutb.local',
            'name' => 'Демо-студент',
            'role' => 'member',
            'legacy_role' => 'reader',
            'profile_type' => 'student',
            'ad_login' => 'demo_student',
            'label' => 'Студент',
            'description' => 'Поиск, каталог, бронирование, личный кабинет',
            'icon' => 'school',
            'landing' => '/dashboard',
        ],

        'librarian' => [
            'email' => 'demo-librarian@kazutb.local',
            'name' => 'Демо-библиотекарь',
            'role' => 'librarian',
            'legacy_role' => 'librarian',
            'profile_type' => 'staff',
            'ad_login' => 'demo_librarian',
            'label' => 'Библиотекарь',
            'description' => 'Выдача и возврат, каталогизация, очереди, отчёты',
            'icon' => 'menu_book',
            'landing' => '/librarian',
        ],

        'director' => [
            'email' => 'demo-director@kazutb.local',
            'name' => 'Демо-директор библиотеки',
            'role' => 'director',
            'legacy_role' => 'librarian',
            'profile_type' => 'staff',
            'ad_login' => 'demo_director',
            'label' => 'Директор библиотеки',
            'description' => 'Полная аналитика, контроль качества и публикации',
            'icon' => 'monitoring',
            'landing' => '/librarian',
        ],

        'senior_librarian' => [
            'email' => 'demo-senior-librarian@kazutb.local',
            'name' => 'Демо-ведущий библиотекарь',
            'role' => 'senior_librarian',
            'legacy_role' => 'librarian',
            'profile_type' => 'staff',
            'ad_login' => 'demo_senior_librarian',
            'label' => 'Ведущий библиотекарь',
            'description' => 'Координация операций и контроль качества данных',
            'icon' => 'explore',
            'landing' => '/librarian',
        ],

        'acquisitions' => [
            'email' => 'demo-acquisitions@kazutb.local',
            'name' => 'Демо-комплектатор',
            'role' => 'acquisitions',
            'legacy_role' => 'librarian',
            'profile_type' => 'staff',
            'ad_login' => 'demo_acquisitions',
            'label' => 'Комплектатор',
            'description' => 'Заказы, поступления и регистрация экземпляров',
            'icon' => 'inventory_2',
            'landing' => '/librarian',
        ],

        'cataloguer' => [
            'email' => 'demo-cataloguer@kazutb.local',
            'name' => 'Демо-каталогизатор',
            'role' => 'cataloguer',
            'legacy_role' => 'librarian',
            'profile_type' => 'staff',
            'ad_login' => 'demo_cataloguer',
            'label' => 'Каталогизатор',
            'description' => 'Библиографические записи, УДК и классификация',
            'icon' => 'sell',
            'landing' => '/librarian',
        ],

        'bibliographer' => [
            'email' => 'demo-bibliographer@kazutb.local',
            'name' => 'Демо-библиограф',
            'role' => 'bibliographer',
            'legacy_role' => 'librarian',
            'profile_type' => 'staff',
            'ad_login' => 'demo_bibliographer',
            'label' => 'Библиограф',
            'description' => 'Поиск, библиографические списки и консультации',
            'icon' => 'search',
            'landing' => '/librarian',
        ],

        'admin' => [
            'email' => 'demo-admin@kazutb.local',
            'name' => 'Демо-администратор',
            'role' => 'admin',
            'legacy_role' => 'admin',
            'profile_type' => 'staff',
            'ad_login' => 'demo_admin',
            'label' => 'Администратор',
            'description' => 'Пользователи, роли, настройки системы, логи',
            'icon' => 'admin_panel_settings',
            'landing' => '/admin',
        ],

    ],

];
