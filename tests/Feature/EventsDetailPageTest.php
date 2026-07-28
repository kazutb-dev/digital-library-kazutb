<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster C.2 — standalone public event detail at /events/{slug}.
 *
 * Mirrors docs/design-exports/event_detail_canonical — breadcrumb + back
 * link + hero (title, lead, meta grid, placeholder visual) + main grid
 * (About + Agenda on the left; Speaker + Materials + Share on the right)
 * + Related Events bento grid. Unknown slug → 404.
 */
class EventsDetailPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo_auth.enabled', true);
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    public function test_known_slug_returns_200(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Digital Preservation of Collections in Academic Libraries', false);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $response = $this->get('/events/this-event-does-not-exist-2026?lang=en');

        $response->assertStatus(404);
    }

    public function test_invalid_slug_format_returns_404(): void
    {
        // Route pattern only matches lowercase/digits/hyphens.
        $response = $this->get('/events/NotASlug');

        $response->assertStatus(404);
    }

    public function test_detail_renders_all_canonical_section_markers(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="event-detail-breadcrumb"', false);
        $response->assertSee('data-section="event-detail-hero"', false);
        $response->assertSee('data-section="event-detail-about"', false);
        $response->assertSee('data-section="event-detail-agenda"', false);
        $response->assertSee('data-section="event-detail-speaker"', false);
        $response->assertSee('data-section="event-detail-materials"', false);
        $response->assertSee('data-section="event-detail-share"', false);
        $response->assertSee('data-section="event-detail-related"', false);
    }

    public function test_detail_renders_sections_in_canonical_order(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-section="event-detail-breadcrumb"',
            'data-section="event-detail-hero"',
            'data-section="event-detail-about"',
            'data-section="event-detail-agenda"',
            'data-section="event-detail-speaker"',
            'data-section="event-detail-materials"',
            'data-section="event-detail-share"',
            'data-section="event-detail-related"',
        ], false);
    }

    public function test_detail_hero_renders_date_venue_and_capacity(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSee('Date &amp; Time', false);
        $response->assertSee('Venue', false);
        $response->assertSee('Audience', false);
        $response->assertSee('May 14', false);
        $response->assertSee('Main Reading Room, Building 1', false);
        $response->assertSee('120 seats', false);
        $response->assertSee('10:00 – 13:30', false);
        $response->assertSee('datetime="2026-05-14"', false);
    }

    public function test_detail_back_link_points_to_events_index(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="event-detail-back"', false);
        $response->assertSee('Back to events', false);
        $response->assertSee('href="/events?lang=en"', false);
    }

    public function test_detail_back_link_preserves_ru_lang_with_clean_url(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026');

        $response->assertOk();
        $response->assertSee('Вернуться к событиям', false);
        $response->assertSee('href="/events"', false);
    }

    public function test_detail_renders_about_agenda_speaker_materials(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSee('About the Event', false);
        $response->assertSee('Agenda', false);
        $response->assertSee('Featured Speaker', false);
        $response->assertSee('Preparatory Materials', false);
        $response->assertSee('Opening remarks', false);
        $response->assertSee('Keynote: The fragility of the digital', false);
        $response->assertSee('Professor, Department of Information Science', false);
        $response->assertSee('Opening brief: digital preservation in the academic setting', false);
    }

    public function test_detail_renders_related_events_bento_excluding_current(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertSee('Related Events', false);
        $response->assertSee('data-related-slug="open-access-publishing-seminar-2026"', false);
        $response->assertSee('data-related-slug="rare-collections-exhibit-2026"', false);
        $response->assertSee('data-related-slug="research-workshop-thesis-citations-2026"', false);
        $response->assertDontSee('data-related-slug="digital-preservation-symposium-2026"', false);
        $response->assertSee('href="/events/open-access-publishing-seminar-2026?lang=en"', false);
        $response->assertSee('View all events', false);
    }

    public function test_detail_ru_variant_renders_localized_copy(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=ru');

        $response->assertOk();
        $response->assertSee('Цифровое сохранение фондов в академических библиотеках', false);
        $response->assertSee('Вернуться к событиям', false);
        $response->assertSee('Дата и время', false);
        $response->assertSee('Место проведения', false);
        $response->assertSee('О событии', false);
        $response->assertSee('Программа', false);
        $response->assertSee('Спикер', false);
        $response->assertSee('Связанные события', false);
        $response->assertSee('Главный читальный зал, корпус 1', false);
    }

    public function test_detail_kk_variant_renders_localized_copy_and_preserves_lang(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=kk');

        $response->assertOk();
        $response->assertSee('Академиялық кітапханалардағы қорларды цифрлық сақтау', false);
        $response->assertSee('Іс-шараларға оралу', false);
        $response->assertSee('Күні мен уақыты', false);
        $response->assertSee('Іс-шара туралы', false);
        $response->assertSee('Бағдарлама', false);
        $response->assertSee('Қатысты іс-шаралар', false);
        $response->assertSee('Басты оқу залы, 1-корпус', false);
        $response->assertSee('href="/events?lang=kk"', false);
        $response->assertSee('href="/events/open-access-publishing-seminar-2026?lang=kk"', false);
    }

    public function test_detail_does_not_render_legacy_brand(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('KazUTB Digital Library', false);
        $response->assertDontSee('KazTBU Digital Library', false);
    }

    public function test_detail_does_not_render_news_article_markers(): void
    {
        $response = $this->get('/events/digital-preservation-symposium-2026?lang=en');

        $response->assertOk();
        $response->assertDontSee('data-section="news-detail"', false);
        $response->assertDontSee('news-detail-article', false);
        $response->assertDontSee('news-body-lead', false);
    }

    public function test_detail_all_four_slugs_resolve(): void
    {
        foreach ([
            'digital-preservation-symposium-2026',
            'open-access-publishing-seminar-2026',
            'rare-collections-exhibit-2026',
            'research-workshop-thesis-citations-2026',
        ] as $slug) {
            $response = $this->get('/events/' . $slug . '?lang=en');

            $response->assertOk();
            $response->assertSee('data-event-slug="' . $slug . '"', false);
            $response->assertSee('data-section="event-detail-hero"', false);
        }
    }
}
