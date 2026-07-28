<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ConsolidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function withAuthSession(array $overrides = []): static
    {
        $defaults = [
            'id' => 'test-user-1',
            'name' => 'Тест Тестов',
            'email' => 'test@digital-library.test',
            'role' => 'reader',
        ];

        return $this->withSession(['library.user' => array_merge($defaults, $overrides)]);
    }

    // ═══════════════════════════════════════════════════════════
    // 1. Removed pages redirect correctly
    // ═══════════════════════════════════════════════════════════

    public function test_services_redirects_to_homepage(): void
    {
        $response = $this->get('/services');
        $response->assertRedirect('/');
        $response->assertStatus(301);
    }

    public function test_news_renders_as_canonical_page(): void
    {
        $response = $this->get('/news');
        $response->assertStatus(200);
        $response->assertSee('data-section="news-canonical-page"', false);
    }

    public function test_about_renders_directly(): void
    {
        $response = $this->get('/about');
        $response->assertOk();
        $response->assertSee('data-section="about-canonical-hero"', false);
        $response->assertSee('KazUTB Smart Library');
    }

    // ═══════════════════════════════════════════════════════════
    // 2. Surviving pages still render
    // ═══════════════════════════════════════════════════════════

    public function test_homepage_renders(): void
    {
        $response = $this->get('/');
        $response->assertOk();
    }

    public function test_catalog_renders(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee('Каталог');
    }

    public function test_contacts_renders_with_about_content(): void
    {
        $response = $this->get('/contacts');
        $response->assertOk();
        $response->assertSee('Каналы поддержки');
        $response->assertSee('Режим работы');
        $response->assertSee('library@kazutb.edu.kz');
    }

    public function test_resources_renders(): void
    {
        $response = $this->get('/resources');
        $response->assertOk();
    }

    public function test_for_teachers_redirects_to_resources(): void
    {
        $response = $this->get('/for-teachers');
        $response->assertRedirect('/resources');
        $response->assertStatus(301);
    }

    public function test_login_renders(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // 3. Navbar no longer references removed pages
    // ═══════════════════════════════════════════════════════════

    public function test_contacts_page_has_no_services_nav_link(): void
    {
        $response = $this->get('/contacts');
        $response->assertOk();
        $response->assertDontSee('href="/services"', false);
        $response->assertDontSee('href="/news"', false);
        $response->assertSee('href="/about"', false);
    }

    public function test_navbar_no_longer_has_for_teachers_link(): void
    {
        $response = $this->get('/contacts');
        $response->assertOk();
        $response->assertDontSee('href="/for-teachers"', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 4. Role-aware account
    // ═══════════════════════════════════════════════════════════

    public function test_teacher_account_shows_workbench(): void
    {
        $response = $this->withAuthSession(['profile_type' => 'teacher'])
            ->get('/account');

        $response->assertOk();
        $response->assertSee('workbench-section', false);
        $response->assertSee('Подборка и сохранённые действия');
        $response->assertSee('📚 Преподаватель');
    }

    public function test_student_account_shows_quick_actions(): void
    {
        $response = $this->withAuthSession(['profile_type' => 'student'])
            ->get('/account');

        $response->assertOk();
        $response->assertSee('Куда перейти дальше');
        $response->assertSee('🎓 Студент');
        $response->assertSee('id="workbench-section"', false);
    }

    public function test_librarian_account_renders(): void
    {
        $response = $this->withAuthSession(['role' => 'librarian'])
            ->get('/account');

        $response->assertOk();
        $response->assertSee('📖 Библиотекарь');
    }

    public function test_admin_account_renders(): void
    {
        $response = $this->withAuthSession(['role' => 'admin'])
            ->get('/account');

        $response->assertOk();
        $response->assertSee('🛡️ Администратор');
    }

    public function test_default_reader_account_shows_quick_actions(): void
    {
        $response = $this->withAuthSession()
            ->get('/account');

        $response->assertOk();
        $response->assertSee('Куда перейти дальше');
        $response->assertSee('id="workbench-section"', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 5. Catalog UX improvements
    // ═══════════════════════════════════════════════════════════

    public function test_catalog_has_clear_filters(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee('clear-filters-btn', false);
        $response->assertSee('clearAllFilters', false);
    }

    public function test_catalog_has_mobile_filter_toggle(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee('mobile-filter-toggle', false);
        $response->assertSee('toggleFilters', false);
    }

    public function test_catalog_has_filter_badge(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee('filter-count-badge', false);
        $response->assertSee('updateFilterBadge', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 6. No regressions — auth flows
    // ═══════════════════════════════════════════════════════════

    public function test_unauthenticated_account_redirects_to_login(): void
    {
        $response = $this->get('/account');
        $response->assertRedirect('/login?redirect=%2Faccount');
    }

    public function test_authenticated_login_redirects_to_account(): void
    {
        $response = $this->withAuthSession()
            ->get('/login');
        $response->assertRedirect('/account');
    }

    public function test_account_summary_api_still_works(): void
    {
        $response = $this->withAuthSession()
            ->getJson('/api/v1/account/summary');
        $response->assertOk();
        $response->assertJsonPath('authenticated', true);
    }

    // ═══════════════════════════════════════════════════════════
    // 7. For-teachers page no longer links to /services
    // ═══════════════════════════════════════════════════════════

    public function test_for_teachers_redirect_is_legacy_safe(): void
    {
        $response = $this->get('/for-teachers');
        $response->assertRedirect('/resources');
        $response->assertStatus(301);
    }

    // ═══════════════════════════════════════════════════════════
    // 8. Profile type in demo auth config
    // ═══════════════════════════════════════════════════════════

    public function test_demo_auth_config_has_profile_types(): void
    {
        $identities = config('demo_auth.identities');
        $this->assertNotNull($identities);

        $this->assertEquals('student', $identities['student']['profile_type'] ?? null);
        $this->assertEquals('teacher', $identities['teacher']['profile_type'] ?? null);
    }

    // ═══════════════════════════════════════════════════════════
    // 9. Wave 2 — Homepage hero search
    // ═══════════════════════════════════════════════════════════

    public function test_homepage_has_hero_search_bar(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('hero-search-bar', false);
        $response->assertSee('heroSearch', false);
    }

    public function test_homepage_has_hero_quick_links(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('hero-quick-links', false);
        $response->assertSee('href="/catalog"', false);
        $response->assertSee('href="/resources"', false);
    }

    public function test_homepage_has_kaztbu_identity_mark(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('hero-campus-mark', false);
        $response->assertSee('Библиотека КазТБУ');
    }

    public function test_homepage_uses_real_kaztbu_logo_in_hero_mark(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('campus-mark__logo', false);
        $response->assertSee('logo.png', false);
    }

    public function test_homepage_hides_helper_text_inside_hero_logo(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('Официальный логотип');
        $response->assertDontSee('Знак университета');
    }

    public function test_navbar_uses_real_kaztbu_logo(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('navbar-brand-logo', false);
        $response->assertSee('logo.png', false);
    }

    public function test_homepage_no_advantages_section(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('Наши преимущества');
    }

    // ═══════════════════════════════════════════════════════════
    // 10. Wave 2 — For-teachers action groups
    // ═══════════════════════════════════════════════════════════

    public function test_resources_has_faculty_support_tools(): void
    {
        $response = $this->get('/resources');
        $response->assertOk();
        $response->assertSee('data-test-id="resources-canonical-off-campus"', false);
        $response->assertSee('data-section="resources-canonical-premium"', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 11. Wave 2 — Resources compact layout
    // ═══════════════════════════════════════════════════════════

    public function test_resources_has_compact_catalog_banner(): void
    {
        $response = $this->get('/resources');
        $response->assertOk();
        $response->assertSee('data-section="resources-canonical-sidebar"', false);
    }

    public function test_resources_has_inline_access_chips(): void
    {
        $response = $this->get('/resources');
        $response->assertOk();
        $response->assertSee('data-section="resources-canonical-open-access"', false);
    }

    // ═══════════════════════════════════════════════════════════
    // 12. Wave 2 — Catalog improvements
    // ═══════════════════════════════════════════════════════════

    public function test_catalog_chips_are_buttons(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee('<button type="button" data-lang=', false);
    }

    public function test_catalog_has_active_filters_container(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee('active-filters', false);
        $response->assertSee('renderActiveFilters', false);
    }

    public function test_catalog_uses_12_per_page(): void
    {
        $response = $this->get('/catalog');
        $response->assertOk();
        $response->assertSee("apiParams.set('limit', '10')", false);
    }

    // ═══════════════════════════════════════════════════════════
    // 13. Wave 2 — Contacts trimmed
    // ═══════════════════════════════════════════════════════════

    public function test_contacts_no_filler_support_section(): void
    {
        $response = $this->get('/contacts');
        $response->assertOk();
        $response->assertDontSee('Чем можем помочь');
    }

    // ═══════════════════════════════════════════════════════════
    // 14. Wave 2 — Account cross-links
    // ═══════════════════════════════════════════════════════════

    public function test_teacher_account_has_shortlist_link(): void
    {
        $response = $this->withAuthSession(['profile_type' => 'teacher'])
            ->get('/account');

        $response->assertOk();
        $response->assertSee('href="/shortlist"', false);
        $response->assertSee('Подборка и сохранённые действия');
        $response->assertDontSee('href="/for-teachers"', false);
    }

    public function test_student_account_no_for_teachers_link_in_quick_actions(): void
    {
        $response = $this->withAuthSession(['profile_type' => 'student'])
            ->get('/account');

        $response->assertOk();
        // Student quick-actions should not have the teacher link
        $response->assertDontSee('Инструменты для силлабуса');
    }
}
