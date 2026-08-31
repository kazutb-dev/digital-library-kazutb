<?php

namespace Tests\Concerns;

use App\Models\News;

trait SeedsPublicEvents
{
    protected function seedPublicEvents(): void
    {
        $authorId = $this->adminUser->getKey();
        $published = now('UTC')->subDay();
        $start = now('UTC')->addWeeks(2)->startOfHour();

        foreach ([
            ['slug' => 'digital-preservation-symposium-2026', 'ru' => 'Цифровое сохранение фондов в академических библиотеках', 'kk' => 'Академиялық кітапханалардағы қорларды цифрлық сақтау', 'en' => 'Digital Preservation of Collections in Academic Libraries', 'venue_ru' => 'Главный читальный зал, корпус 1', 'venue_kk' => 'Басты оқу залы, 1-корпус', 'venue_en' => 'Main Reading Room, Building 1', 'featured' => true],
            ['slug' => 'open-access-publishing-seminar-2026', 'ru' => 'Открытый доступ и академические публикации', 'kk' => 'Ашық қолжетімділік және академиялық жарияланымдар', 'en' => 'Open Access and Academic Publishing', 'venue_ru' => 'Семинарский зал', 'venue_kk' => 'Семинар залы', 'venue_en' => 'Seminar Hall', 'featured' => false],
            ['slug' => 'rare-collections-exhibit-2026', 'ru' => 'Редкие издания научной библиотеки', 'kk' => 'Ғылыми кітапхананың сирек басылымдары', 'en' => 'Rare Editions from the Scientific Library', 'venue_ru' => 'Выставочный зал', 'venue_kk' => 'Көрме залы', 'venue_en' => 'Exhibition Hall', 'featured' => false],
            ['slug' => 'research-workshop-thesis-citations-2026', 'ru' => 'Оформление ссылок в научной работе', 'kk' => 'Ғылыми жұмыстағы сілтемелерді рәсімдеу', 'en' => 'Citations in Academic Research', 'venue_ru' => 'Учебный зал', 'venue_kk' => 'Оқу залы', 'venue_en' => 'Training Room', 'featured' => false],
        ] as $index => $event) {
            News::query()->create([
                'slug' => $event['slug'], 'slug_ru' => $event['slug'], 'slug_kk' => $event['slug'], 'slug_en' => $event['slug'],
                'title' => $event['ru'], 'title_ru' => $event['ru'], 'title_kk' => $event['kk'], 'title_en' => $event['en'],
                'excerpt' => 'Открытое мероприятие научной библиотеки.', 'excerpt_ru' => 'Открытое мероприятие научной библиотеки.',
                'excerpt_kk' => 'Ғылыми кітапхананың ашық іс-шарасы.', 'excerpt_en' => 'A public event hosted by the scientific library.',
                'body' => "Описание мероприятия.\n\nПрактическая информация для участников.",
                'content_ru' => "Описание мероприятия.\n\nПрактическая информация для участников.",
                'content_kk' => "Іс-шараның сипаттамасы.\n\nҚатысушыларға арналған практикалық ақпарат.",
                'content_en' => "Event description.\n\nPractical information for attendees.",
                'category' => 'event', 'type' => 'event', 'language' => 'ru', 'status' => 'published', 'visibility' => 'public',
                'venue' => $event['venue_ru'], 'venue_ru' => $event['venue_ru'], 'venue_kk' => $event['venue_kk'], 'venue_en' => $event['venue_en'],
                'starts_at' => $start->copy()->addDays($index * 7), 'ends_at' => $start->copy()->addDays($index * 7)->addHours(2),
                'timezone' => 'Asia/Almaty', 'capacity' => 120, 'organizer' => 'Научная библиотека',
                'is_featured' => $event['featured'], 'show_on_homepage' => $event['featured'],
                'created_by' => $authorId, 'published_by' => $authorId, 'approved_by' => $authorId,
                'approved_at' => $published, 'published_at' => $published->copy()->subMinutes($index), 'publish_at' => $published->copy()->subMinutes($index),
            ]);
        }
    }
}
