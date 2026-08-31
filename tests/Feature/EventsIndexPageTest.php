<?php

namespace Tests\Feature;

use App\Models\News;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\Concerns\SeedsPublicEvents;
use Tests\TestCase;

class EventsIndexPageTest extends TestCase
{
    use BuildsAdminControlPlane, SeedsPublicEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->seedPublicEvents();
    }

    public function test_guest_sees_published_managed_events_in_chronological_order(): void
    {
        $this->get('/events?lang=en')->assertOk()
            ->assertSeeInOrder([
                'Digital Preservation of Collections in Academic Libraries',
                'Open Access and Academic Publishing',
                'Rare Editions from the Scientific Library',
                'Citations in Academic Research',
            ], false);
    }

    public function test_index_has_canonical_structure_and_working_detail_links(): void
    {
        $this->get('/events?lang=en')->assertOk()
            ->assertSeeInOrder(['data-section="events-canonical-header"', 'data-section="events-canonical-list"'], false)
            ->assertSee('data-event-featured="true"', false)
            ->assertSee('href="/events/digital-preservation-symposium-2026?lang=en"', false)
            ->assertDontSee('data-test-id="events-canonical-load-more"', false);
    }

    public function test_index_is_localized_in_all_supported_languages(): void
    {
        $this->get('/events?lang=ru')->assertOk()
            ->assertSee('Календарь мероприятий', false)
            ->assertSee('Цифровое сохранение фондов в академических библиотеках', false)
            ->assertSee('Главный читальный зал, корпус 1', false);

        $this->get('/events?lang=kk')->assertOk()
            ->assertSee('Іс-шаралар күнтізбесі', false)
            ->assertSee('Академиялық кітапханалардағы қорларды цифрлық сақтау', false)
            ->assertSee('Басты оқу залы, 1-корпус', false);
    }

    public function test_index_hides_drafts_and_expired_events(): void
    {
        News::query()->where('slug', 'rare-collections-exhibit-2026')->update(['status' => 'draft']);
        News::query()->where('slug', 'research-workshop-thesis-citations-2026')->update(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

        $this->get('/events?lang=en')->assertOk()
            ->assertDontSee('Rare Editions from the Scientific Library', false)
            ->assertDontSee('Citations in Academic Research', false);
    }

    public function test_index_does_not_render_legacy_branding_or_news_markers(): void
    {
        $this->get('/events?lang=en')->assertOk()
            ->assertDontSee('Athenaeum', false)
            ->assertDontSee('KazUTB Digital Library', false)
            ->assertDontSee('data-section="news-canonical-page"', false);
    }
}
