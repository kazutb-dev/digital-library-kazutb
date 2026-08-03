<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $settings = [
            [
                'key' => 'max_active_reservations',
                'value' => 3,
                'type' => 'integer',
                'group' => 'reservations',
                'description' => 'Максимальное количество активных бронирований у одного читателя.',
            ],
            [
                'key' => 'reservation_lifespan_days',
                'value' => 1,
                'type' => 'integer',
                'group' => 'reservations',
                'description' => 'Срок хранения подтверждённого бронирования в днях.',
            ],
            ['key' => 'reservation_queue_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'reservations', 'description' => 'Разрешить очередь на недоступные издания.'],
            ['key' => 'reservation_hold_days', 'value' => 1, 'type' => 'integer', 'group' => 'reservations', 'description' => 'Срок хранения готовой брони в днях.'],
            ['key' => 'reservation_max_extensions', 'value' => 1, 'type' => 'integer', 'group' => 'reservations', 'description' => 'Максимум продлений хранения брони.'],
            ['key' => 'reservation_extension_hours', 'value' => 24, 'type' => 'integer', 'group' => 'reservations', 'description' => 'Часы одного продления хранения.'],
            ['key' => 'reservation_manual_confirmation_required', 'value' => false, 'type' => 'boolean', 'group' => 'reservations', 'description' => 'Требовать ручное подтверждение доступной брони.'],
            ['key' => 'reservation_interbranch_transfer_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'reservations', 'description' => 'Разрешить межфилиальные перемещения.'],
            ['key' => 'reservation_expiry_reminder_hours', 'value' => 24, 'type' => 'integer', 'group' => 'reservations', 'description' => 'За сколько часов напоминать об истечении.'],
            ['key' => 'reservation_queue_override_enabled', 'value' => true, 'type' => 'boolean', 'group' => 'reservations', 'description' => 'Разрешить аудируемый override очереди.'],
            ['key' => 'reservation_blocking_on_fines', 'value' => true, 'type' => 'boolean', 'group' => 'reservations', 'description' => 'Блокировать бронь при задолженности.'],
            [
                'key' => 'max_active_loans',
                'value' => 5,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Максимальное количество одновременно выданных экземпляров.',
            ],
            [
                'key' => 'standard_loan_period_days',
                'value' => 14,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Стандартный срок выдачи литературы в днях.',
            ],
            [
                'key' => 'reference_loan_period_days',
                'value' => 1,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Срок выдачи справочных материалов в днях.',
            ],
            // ДИР §9.3 — срок выдачи зависит от количества экземпляров записи.
            // Значения ступеней — предложенная эвристика внутри диапазона 3–7,
            // библиотека корректирует их через /admin/settings.
            [
                'key' => 'loan_period_scarce_max_copies',
                'value' => 2,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'До какого числа экземпляров издание считается дефицитным.',
            ],
            [
                'key' => 'loan_period_scarce_days',
                'value' => 3,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Срок выдачи дефицитного издания в днях.',
            ],
            [
                'key' => 'loan_period_standard_max_copies',
                'value' => 5,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'До какого числа экземпляров действует обычный срок выдачи.',
            ],
            [
                'key' => 'loan_period_standard_days',
                'value' => 5,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Обычный срок выдачи в днях.',
            ],
            [
                'key' => 'loan_period_abundant_days',
                'value' => 7,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Срок выдачи издания, представленного большим числом экземпляров.',
            ],
            [
                'key' => 'renewal_allowed',
                'value' => true,
                'type' => 'boolean',
                'group' => 'circulation',
                'description' => 'Разрешено ли продление выдачи при отсутствии активного бронирования.',
            ],
            [
                'key' => 'renewal_period_days',
                'value' => 14,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Срок продления в днях; по умолчанию совпадает со стандартным сроком выдачи.',
            ],
            ['key' => 'max_renewals', 'value' => 1, 'type' => 'integer', 'group' => 'circulation', 'description' => 'Максимум продлений одной выдачи.'],
            ['key' => 'manual_due_date_max_days', 'value' => 30, 'type' => 'integer', 'group' => 'circulation', 'description' => 'Максимальная ручная дата возврата от текущей даты.'],
            ['key' => 'inventory_batch_scan_limit', 'value' => 5000, 'type' => 'integer', 'group' => 'inventory', 'description' => 'Предельное число сканов в сессии.'],
            ['key' => 'barcode_format', 'value' => 'code128', 'type' => 'string', 'group' => 'barcodes', 'description' => 'Основной формат линейного кода.'],
            ['key' => 'barcode_label_size', 'value' => '62x40', 'type' => 'string', 'group' => 'barcodes', 'description' => 'Размер печатной этикетки в миллиметрах.'],
            [
                'key' => 'overdue_blocking_enabled',
                'value' => true,
                'type' => 'boolean',
                'group' => 'circulation',
                'description' => 'Блокировать новые бронирования при наличии просроченных выдач.',
            ],
            [
                'key' => 'fine_per_overdue_day',
                'value' => 100,
                'type' => 'integer',
                'group' => 'circulation',
                'description' => 'Штраф за каждый день просрочки, тенге. 0 — штрафы за просрочку отключены.',
            ],
            ['key' => 'incident_resolution_days', 'value' => 30, 'type' => 'integer', 'group' => 'incidents', 'description' => 'Предлагаемый изменяемый срок урегулирования дела.'],
            ['key' => 'replacement_year_tolerance', 'value' => 5, 'type' => 'integer', 'group' => 'incidents', 'description' => 'Предлагаемое изменяемое допустимое расхождение года издания.'],
            ['key' => 'replacement_requires_senior_approval', 'value' => true, 'type' => 'boolean', 'group' => 'incidents', 'description' => 'Обычная замена требует решения ведущего библиотекаря.'],
            ['key' => 'replacement_exception_requires_director', 'value' => true, 'type' => 'boolean', 'group' => 'incidents', 'description' => 'Исключение по обязательным критериям требует директора.'],
            ['key' => 'monetary_compensation_allowed', 'value' => false, 'type' => 'boolean', 'group' => 'incidents', 'description' => 'Разрешена денежная компенсация по решению уполномоченного сотрудника.'],
            ['key' => 'incident_blocks_issues', 'value' => true, 'type' => 'boolean', 'group' => 'incidents', 'description' => 'Открытое дело блокирует новые выдачи.'],
            ['key' => 'replacement_required_severe', 'value' => true, 'type' => 'boolean', 'group' => 'incidents', 'description' => 'Предлагать обязательство по замене при серьёзном повреждении.'],
            ['key' => 'replacement_required_irreparable', 'value' => true, 'type' => 'boolean', 'group' => 'incidents', 'description' => 'Предлагать обязательство по замене при невосстановимом повреждении.'],
            ['key' => 'incident_resolution_types', 'value' => ['replacement', 'fine', 'fine_and_replacement', 'repair', 'write_off'], 'type' => 'array', 'group' => 'incidents', 'description' => 'Доступные способы урегулирования дела.'],
            [
                'key' => 'notification_channels',
                'value' => ['in_app', 'email'],
                'type' => 'array',
                'group' => 'notifications',
                'description' => 'Каналы доставки системных уведомлений.',
            ],
            [
                'key' => 'news_categories',
                'value' => ['event', 'announcement', 'update', 'schedule'],
                'type' => 'array',
                'group' => 'content',
                'description' => 'Доступные категории новостей и объявлений.',
            ],
            [
                'key' => 'message_categories',
                'value' => ['request', 'complaint', 'suggestion', 'question', 'other'],
                'type' => 'array',
                'group' => 'content',
                'description' => 'Доступные категории обращений пользователей.',
            ],
            [
                'key' => 'default_ui_language',
                'value' => 'kk',
                'type' => 'string',
                'group' => 'localization',
                'description' => 'Язык интерфейса по умолчанию.',
            ],
            [
                'key' => 'catalog_page_size',
                'value' => 12,
                'type' => 'integer',
                'group' => 'localization',
                'description' => 'Число карточек на странице публичного каталога. Отдельно от результатов административных таблиц.',
            ],
            [
                'key' => 'results_per_page',
                'value' => 20,
                'type' => 'integer',
                'group' => 'localization',
                'description' => 'Количество строк на одной странице списков.',
            ],
            ['key' => 'data_quality_scan_chunk_size', 'value' => 500, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый размер порции сканирования.'],
            ['key' => 'data_quality_bulk_batch_limit', 'value' => 1000, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый безопасный предел массовой операции.'],
            ['key' => 'data_quality_duplicate_exact_threshold', 'value' => 90, 'type' => 'decimal', 'group' => 'data_quality', 'description' => 'Подсказочный порог точного дубля; не запускает слияние автоматически.'],
            ['key' => 'data_quality_duplicate_probable_threshold', 'value' => 65, 'type' => 'decimal', 'group' => 'data_quality', 'description' => 'Подсказочный порог вероятного дубля.'],
            ['key' => 'data_quality_min_publication_year', 'value' => 1450, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемая изменяемая нижняя граница года издания.'],
            ['key' => 'data_quality_max_future_years', 'value' => 1, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Допустимое отклонение года издания в будущее.'],
            ['key' => 'data_quality_rescan_days', 'value' => 7, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый интервал полного повторного сканирования.'],
            ['key' => 'data_quality_staging_retention_days', 'value' => 90, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый срок хранения staging-данных.'],
            ['key' => 'data_quality_sla_critical_hours', 'value' => 24, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый SLA critical issue.'],
            ['key' => 'data_quality_sla_high_hours', 'value' => 72, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый SLA high issue.'],
            ['key' => 'data_quality_sla_medium_hours', 'value' => 168, 'type' => 'integer', 'group' => 'data_quality', 'description' => 'Предлагаемый SLA medium issue.'],
            ['key' => 'data_quality_bulk_approval_required', 'value' => true, 'type' => 'boolean', 'group' => 'data_quality', 'description' => 'Требовать независимое подтверждение массовых исправлений.'],
            ['key' => 'data_quality_merge_approval_required', 'value' => true, 'type' => 'boolean', 'group' => 'data_quality', 'description' => 'Требовать независимое подтверждение слияния.'],
            ['key' => 'data_quality_import_encodings', 'value' => ['UTF-8', 'Windows-1251'], 'type' => 'array', 'group' => 'data_quality', 'description' => 'Разрешённые кодировки импорта; legacy-кодировки добавляются только по образцу.'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        // APP_DEMO_LOGIN_ENABLED intentionally remains environment-only.
        // Persisting a second database switch would allow production policy
        // to drift from the deployment's explicit security configuration.
    }
}
