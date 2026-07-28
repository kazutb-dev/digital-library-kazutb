<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster C.1 — standalone public events index at /events.
 *
 * Mirrors docs/design-exports/events_index_canonical — header + vertical
 * event card list (1/4 date rail + 3/4 content with venue + details link)
 * + Load More button. Content is driven by $eventsSeedProvider (tri-
 * lingual). Event detail (/events/{slug}) is NOT in scope for this slice.
 */
class EventsIndexPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo_auth.enabled', true);
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    private function loginAs(string $identitySlug): void
    {
        $identity = config("demo_auth.identities.{$identitySlug}");

        $this->get('/login');
        $this->post('/login', [
            '_token' => csrf_token(),
            'login' => $identity['login'],
            'password' => $identity['password'],
            'device_name' => 'phpunit',
        ]);
    }

    public function test_guest_can_view_events_index(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Public Events Index', false);
    }

    public function test_events_index_renders_canonical_section_markers(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="events-canonical-header"', false);
        $response->assertSee('data-section="events-canonical-list"', false);
        $response->assertSee('data-section="events-canonical-load-more"', false);
    }

    public function test_events_index_renders_canonical_sections_in_order(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-section="events-canonical-header"',
            'data-section="events-canonical-list"',
            'data-section="events-canonical-load-more"',
        ], false);
    }

    public function test_events_index_renders_all_seeded_event_cards(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('data-event-slug="digital-preservation-symposium-2026"', false);
        $response->assertSee('data-event-slug="open-access-publishing-seminar-2026"', false);
        $response->assertSee('data-event-slug="rare-collections-exhibit-2026"', false);
        $response->assertSee('data-event-slug="research-workshop-thesis-citations-2026"', false);
        $response->assertSeeInOrder([
            'data-event-slug="digital-preservation-symposium-2026"',
            'data-event-slug="open-access-publishing-seminar-2026"',
            'data-event-slug="rare-collections-exhibit-2026"',
            'data-event-slug="research-workshop-thesis-citations-2026"',
        ], false);
    }

    public function test_events_index_flags_first_event_as_featured(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('events-canonical__card--featured', false);
        $response->assertSee('data-event-featured="true"', false);
    }

    public function test_events_index_renders_date_title_description_and_venue(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('Symposium', false);
        $response->assertSee('May 14', false);
        $response->assertSee('Digital Preservation of Collections in Academic Libraries', false);
        $response->assertSee('Main Reading Room, Building 1', false);
        $response->assertSee('Open Access and Academic Publishing', false);
        $response->assertSee('Seminar Hall B, Building 1', false);
        $response->assertSee('Rare Editions and the Scholarly Heritage of the Collection', false);
        $response->assertSee('Room 1/200, Technology Fund', false);
        $response->assertSee('Room 1/202, College Fund', false);
    }

    public function test_events_index_detail_links_are_well_formed(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="events-canonical-details-digital-preservation-symposium-2026"', false);
        $response->assertSee('href="/events/digital-preservation-symposium-2026?lang=en"', false);
        $response->assertSee('href="/events/open-access-publishing-seminar-2026?lang=en"', false);
        $response->assertSee('href="/events/rare-collections-exhibit-2026?lang=en"', false);
        $response->assertSee('href="/events/research-workshop-thesis-citations-2026?lang=en"', false);
    }

    public function test_events_index_renders_load_more_control(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="events-canonical-load-more"', false);
        $response->assertSee('Load more events', false);
    }

    public function test_events_index_ru_variant_renders_localized_copy(): void
    {
        $response = $this->get('/events?lang=ru');

        $response->assertOk();
        $response->assertSee('Календарь событий', false);
        $response->assertSee('Симпозиум', false);
        $response->assertSee('14 мая', false);
        $response->assertSee('Цифровое сохранение фондов в академических библиотеках', false);
        $response->assertSee('Главный читальный зал, корпус 1', false);
        $response->assertSee('Показать больше событий', false);
        $response->assertSee('href="/events/digital-preservation-symposium-2026"', false);
    }

    public function test_events_index_kk_variant_renders_localized_copy_and_preserves_lang(): void
    {
        $response = $this->get('/events?lang=kk');

        $response->assertOk();
        $response->assertSee('Іс-шаралар күнтізбесі', false);
        $response->assertSee('Академиялық кітапханалардағы қорларды цифрлық сақтау', false);
        $response->assertSee('Басты оқу залы, 1-корпус', false);
        $response->assertSee('Қосымша іс-шараларды көрсету', false);
        $response->assertSee('href="/events/digital-preservation-symposium-2026?lang=kk"', false);
    }

    public function test_events_index_does_not_render_legacy_brand(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('KazUTB Digital Library', false);
        $response->assertDontSee('KazTBU Digital Library', false);
    }

    public function test_events_index_does_not_render_news_index_markers(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertDontSee('data-section="news-canonical-hero"', false);
        $response->assertDontSee('data-section="news-index-featured"', false);
        $response->assertDontSee('data-section="news-index-grid"', false);
    }

    public function test_events_index_guest_navbar_shows_sign_in(): void
    {
        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('Sign in', false);
    }

    public function test_events_index_authenticated_reader_navbar_shows_sign_out(): void
    {
        $this->loginAs('student');

        $response = $this->get('/events?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Sign out', false);
    }
}
