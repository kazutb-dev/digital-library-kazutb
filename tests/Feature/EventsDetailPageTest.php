<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsAdminControlPlane;
use Tests\Concerns\SeedsPublicEvents;
use Tests\TestCase;

class EventsDetailPageTest extends TestCase
{
    use BuildsAdminControlPlane, SeedsPublicEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->seedPublicEvents();
    }

    public function test_each_published_event_opens_and_unknown_slugs_are_not_found(): void
    {
        foreach (['digital-preservation-symposium-2026', 'open-access-publishing-seminar-2026', 'rare-collections-exhibit-2026', 'research-workshop-thesis-citations-2026'] as $slug) {
            $this->get('/events/'.$slug.'?lang=en')->assertOk();
        }

        $this->get('/events/this-event-does-not-exist-2026?lang=en')->assertNotFound();
        $this->get('/events/NotASlug')->assertNotFound();
    }

    public function test_detail_uses_the_canonical_article_layout_and_event_metadata(): void
    {
        $this->get('/events/digital-preservation-symposium-2026?lang=en')->assertOk()
            ->assertSee('data-section="news-detail-canonical-page"', false)
            ->assertSee('data-section="news-detail-canonical-article"', false)
            ->assertSee('data-test-id="news-detail-canonical-back"', false)
            ->assertSee('data-test-id="news-detail-canonical-body"', false)
            ->assertSee('Digital Preservation of Collections in Academic Libraries', false)
            ->assertSee('Main Reading Room, Building 1', false)
            ->assertSee('datetime=', false)
            ->assertSee('href="/events?lang=en"', false);
    }

    public function test_detail_is_localized_and_lists_related_managed_events(): void
    {
        $this->get('/events/digital-preservation-symposium-2026?lang=ru')->assertOk()
            ->assertSee('Цифровое сохранение фондов в академических библиотеках', false)
            ->assertSee('Главный читальный зал, корпус 1', false)
            ->assertSee('Открытый доступ и академические публикации', false);

        $this->get('/events/digital-preservation-symposium-2026?lang=kk')->assertOk()
            ->assertSee('Академиялық кітапханалардағы қорларды цифрлық сақтау', false)
            ->assertSee('Басты оқу залы, 1-корпус', false);
    }

    public function test_detail_uses_only_current_institutional_branding(): void
    {
        $this->get('/events/digital-preservation-symposium-2026?lang=en')->assertOk()
            ->assertSee('Kazakh University of Technology and Business named after K. Kulazhanov', false)
            ->assertDontSee('Athenaeum', false)
            ->assertDontSee('KazUTB Digital Library', false)
            ->assertDontSee('KazTBU Digital Library', false);
    }
}
