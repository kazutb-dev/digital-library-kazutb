<?php

namespace Database\Seeders;

use App\Models\MessageCategory;
use App\Models\MessageRoutingRule;
use App\Models\NotificationSetting;
use Illuminate\Database\Seeder;

class MessageCategorySeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'request' => [
                ['book-search', 'Әдебиетті іздеу', 'Поиск литературы', 'Literature search', 'medium', 'librarian', false, 72],
                ['book-availability', 'Кітаптың болуы', 'Наличие книги', 'Book availability', 'medium', 'librarian', false, 48],
                ['renewal', 'Ұзарту', 'Продление', 'Renewal', 'medium', 'librarian', false, 48],
                ['reservation', 'Брондау', 'Бронирование', 'Reservation', 'medium', 'librarian', false, 48],
                ['electronic-access', 'Электрондық қолжетімділік', 'Электронный доступ', 'Electronic access', 'medium', 'librarian', false, 48],
                ['bibliographic-reference', 'Библиографиялық анықтама', 'Библиографическая справка', 'Bibliographic reference', 'medium', 'bibliographer', false, 120],
                ['opening-hours', 'Жұмыс тәртібі', 'Режим работы', 'Opening hours', 'low', 'librarian', false, 24],
                ['interbranch-service', 'Филиаларалық қызмет', 'Межфилиальная услуга', 'Interbranch service', 'medium', 'senior_librarian', false, 120],
                ['request-other', 'Басқа сұрау', 'Другой запрос', 'Other request', 'medium', 'librarian', false, 72],
            ],
            'complaint' => [
                ['service-quality', 'Қызмет көрсету', 'Обслуживание', 'Service quality', 'high', 'senior_librarian', true, 48],
                ['staff-conduct', 'Қызметкердің жұмысы', 'Работа сотрудника', 'Staff conduct', 'high', 'senior_librarian', true, 48],
                ['technical-error', 'Техникалық қате', 'Техническая ошибка', 'Technical error', 'high', 'admin', true, 24],
                ['order-violation', 'Тәртіптің бұзылуы', 'Нарушение порядка', 'Order violation', 'high', 'senior_librarian', true, 48],
                ['resource-unavailable', 'Ресурс қолжетімсіз', 'Недоступность ресурса', 'Resource unavailable', 'high', 'librarian', true, 48],
                ['incorrect-information', 'Қате ақпарат', 'Некорректная информация', 'Incorrect information', 'high', 'senior_librarian', true, 48],
                ['branch-problem', 'Филиал мәселесі', 'Проблема с филиалом', 'Branch problem', 'high', 'senior_librarian', true, 48],
                ['complaint-other', 'Басқа шағым', 'Другая жалоба', 'Other complaint', 'high', 'senior_librarian', true, 48],
            ],
            'suggestion' => [
                ['new-books', 'Жаңа кітаптар', 'Новые книги', 'New books', 'low', 'director', true, 120],
                ['electronic-resources', 'Электрондық ресурстар', 'Электронные ресурсы', 'Electronic resources', 'low', 'director', true, 120],
                ['events', 'Іс-шаралар', 'Мероприятия', 'Events', 'low', 'director', true, 120],
                ['interface', 'Интерфейс', 'Интерфейс', 'Interface', 'medium', 'admin', false, 120],
                ['new-services', 'Жаңа қызметтер', 'Новые услуги', 'New services', 'low', 'director', true, 120],
                ['process-improvement', 'Процесті жақсарту', 'Улучшение процесса', 'Process improvement', 'low', 'director', true, 120],
                ['suggestion-other', 'Басқа ұсыныс', 'Другое предложение', 'Other suggestion', 'low', 'director', true, 120],
            ],
            'question' => [
                ['rules', 'Ережелер', 'Правила', 'Rules', 'medium', 'director', true, 72],
                ['library-policy', 'Кітапхана саясаты', 'Политика библиотеки', 'Library policy', 'medium', 'director', true, 72],
                ['organizational-decision', 'Ұйымдастырушылық шешім', 'Организационное решение', 'Organizational decision', 'medium', 'director', true, 72],
                ['collection-development', 'Қорды дамыту', 'Развитие фонда', 'Collection development', 'medium', 'director', true, 96],
                ['partnership', 'Серіктестік', 'Партнёрство', 'Partnership', 'medium', 'director', true, 96],
                ['question-other', 'Басқа сұрақ', 'Другой вопрос', 'Other question', 'medium', 'director', true, 72],
            ],
        ];

        $order = 10;
        foreach ($definitions as $type => $categories) {
            foreach ($categories as [$slug, $kk, $ru, $en, $priority, $role, $director, $sla]) {
                MessageCategory::query()->updateOrCreate(['slug' => $slug], [
                    'message_type' => $type, 'name_kk' => $kk, 'name_ru' => $ru, 'name_en' => $en,
                    'active' => true, 'sort_order' => $order, 'default_priority' => $priority,
                    'default_assignee_role' => $role, 'requires_director_review' => $director,
                    'sla_hours' => $sla, 'allowed_attachment_types' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx'],
                ]);
                $order += 10;
            }
        }

        foreach (MessageCategory::query()->get() as $category) {
            MessageRoutingRule::query()->updateOrCreate(
                ['category_id' => $category->getKey(), 'target_role' => $category->default_assignee_role],
                ['name' => 'Category: '.$category->slug, 'message_type' => $category->message_type, 'director_visibility' => $category->requires_director_review, 'active' => true, 'sort_order' => $category->sort_order],
            );
        }

        foreach ([
            'message_registered', 'message_assigned', 'message_critical', 'message_priority_raised',
            'message_clarification_requested', 'message_staff_replied', 'message_internal_note',
            'message_response_prepared', 'message_response_returned', 'message_resolved', 'message_rejected',
            'message_reopened', 'message_user_replied', 'message_sla_reminder', 'message_sla_breached',
        ] as $event) {
            NotificationSetting::query()->firstOrCreate(['event_type' => $event], ['in_app_enabled' => true, 'email_enabled' => false]);
        }
    }
}
