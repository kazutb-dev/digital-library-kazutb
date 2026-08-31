<?php

namespace Tests\Concerns;

use App\Models\News;

trait SeedsPublicNews
{
    protected function seedPublicNews(): void
    {
        $authorId = $this->adminUser->getKey();
        $published = now('UTC')->subDay();

        foreach ([
            ['slug' => 'global-symposium-archival-integrity', 'title_ru' => 'Международный симпозиум по целостности архивов прошёл в Астане', 'title_kk' => 'Астанада мұрағат тұтастығы бойынша халықаралық симпозиум өтті', 'title_en' => 'Global Symposium on Archival Integrity Concludes in Astana', 'cover_image' => 'images/news/campus-library.jpg', 'featured' => true],
            ['slug' => 'eurasian-manuscripts-integration', 'title_ru' => 'Интеграция евразийских рукописей XIX века', 'title_kk' => 'XIX ғасырдағы еуразиялық қолжазбаларды біріктіру', 'title_en' => 'Integration of the 19th-Century Eurasian Manuscripts', 'cover_image' => 'images/news/classics-event.jpg', 'featured' => false],
            ['slug' => 'digital-access-partner-institutions', 'title_ru' => 'Расширен цифровой доступ для академических партнёров', 'title_kk' => 'Академиялық серіктестер үшін цифрлық қолжетімділік кеңейтілді', 'title_en' => 'Expanded Digital Access for External Academic Partners', 'cover_image' => 'images/news/author-visit.jpg', 'featured' => false],
        ] as $index => $item) {
            News::query()->create([
                'slug' => $item['slug'], 'slug_ru' => $item['slug'], 'slug_kk' => $item['slug'], 'slug_en' => $item['slug'],
                'title' => $item['title_ru'], 'title_ru' => $item['title_ru'], 'title_kk' => $item['title_kk'], 'title_en' => $item['title_en'],
                'excerpt' => 'Опубликованный материал научной библиотеки.', 'excerpt_ru' => 'Опубликованный материал научной библиотеки.',
                'excerpt_kk' => 'Ғылыми кітапхананың жарияланған материалы.', 'excerpt_en' => 'A published update from the scientific library.',
                'body' => "Основной текст публикации.\n\nДополнительные сведения для читателей.",
                'content_ru' => "Основной текст публикации.\n\nДополнительные сведения для читателей.",
                'content_kk' => "Жарияланымның негізгі мәтіні.\n\nОқырмандарға арналған қосымша мәліметтер.",
                'content_en' => "Main publication text.\n\nAdditional information for readers.",
                'category' => 'announcement', 'type' => 'announcement', 'language' => 'ru', 'status' => 'published', 'visibility' => 'public',
                'cover_image' => $item['cover_image'], 'image_alt_ru' => $item['title_ru'], 'image_alt_kk' => $item['title_kk'], 'image_alt_en' => $item['title_en'],
                'show_on_homepage' => $item['featured'], 'is_featured' => $item['featured'], 'created_by' => $authorId, 'published_by' => $authorId,
                'approved_by' => $authorId, 'approved_at' => $published, 'published_at' => $published->copy()->subMinutes($index), 'publish_at' => $published->copy()->subMinutes($index),
            ]);
        }
    }
}
