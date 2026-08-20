<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LandingLocalizationTest extends TestCase
{
    #[DataProvider('localizedLandingCopy')]
    public function test_public_landing_api_localizes_all_editorial_copy(
        string $locale,
        string $title,
        string $description,
        array $expectedStatLabels,
    ): void {
        Cache::put('public.portal.statistics.v3', [
            'catalog_titles' => 9562,
            'physical_copies' => 50907,
            'electronic_materials' => null,
            'published_resources' => 6,
            'published_repository' => 0,
            'published_news' => 0,
            'published_events' => 0,
        ], now()->addMinute());

        $response = $this->getJson('/api/v1/landing?lang='.$locale);

        $response
            ->assertOk()
            ->assertJsonPath('hero.title', $title)
            ->assertJsonPath('hero.description', $description);

        $payload = $response->json();
        $stats = collect(data_get($payload, 'hero.stats', []))->keyBy('source');

        $this->assertSame(array_keys($expectedStatLabels), $stats->keys()->all());
        foreach ($expectedStatLabels as $source => $label) {
            $this->assertSame($label, data_get($stats->get($source), 'label'), $source.' label for '.$locale);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:common|public_landing)\.[a-z][a-z0-9_.-]*\b/iu',
            $json,
            'The public response must never expose a raw translation key.',
        );
    }

    public static function localizedLandingCopy(): array
    {
        return [
            'ru' => [
                'ru',
                'Научная библиотека КазУТБ',
                'Публичный каталог, электронные ресурсы и сервисы библиотеки.',
                [
                    'catalog_titles' => 'наименований в электронном каталоге',
                    'physical_copies' => 'экземпляров библиотечного фонда',
                    'published_resources' => 'ресурсов с опубликованными условиями доступа',
                    'public_catalog_availability' => 'онлайн-доступ к публичному каталогу',
                ],
            ],
            'kk' => [
                'kk',
                'ҚазТБУ ғылыми кітапханасы',
                'Кітапхананың ашық каталогы, электрондық ресурстары мен қызметтері.',
                [
                    'catalog_titles' => 'электрондық каталогтағы атау',
                    'physical_copies' => 'кітапхана қорындағы дана',
                    'published_resources' => 'қолжетімділік шарттары жарияланған ресурс',
                    'public_catalog_availability' => 'ашық каталогқа тәулік бойы онлайн-қолжетім',
                ],
            ],
            'en' => [
                'en',
                'KazUTB Scientific Library',
                "The library's public catalogue, electronic resources, and services.",
                [
                    'catalog_titles' => 'titles in the electronic catalogue',
                    'physical_copies' => 'copies in the library collection',
                    'published_resources' => 'resources with published access terms',
                    'public_catalog_availability' => 'round-the-clock online access to the public catalogue',
                ],
            ],
        ];
    }
}
