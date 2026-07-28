<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /resources — canonical-exact rebuild per docs/design-exports/institutional_resources_canonical.
 *
 * Legacy shell markers (pathways, filter-bar, grid, support-section, featured/small cards,
 * core-databases-label, tailored-pathway cards) must be absent. The new layout mirrors
 * the canonical export: hero (8/12 + 4/12 off-campus) → 1/4 sidebar + 3/4 categorized
 * directory split by access type into Premium Databases (remote_auth + campus) and
 * Open Access Tools (open).
 */
class ResourcesPageTest extends TestCase
{
    public function test_resources_page_returns_200(): void
    {
        $this->get('/resources')->assertOk();
        $this->get('/resources?lang=en')->assertOk();
        $this->get('/resources?lang=kk')->assertOk();
    }

    public function test_resources_page_renders_canonical_section_markers(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('data-section="resources-canonical-hero"', false)
            ->assertSee('data-section="resources-canonical-main"', false)
            ->assertSee('data-section="resources-canonical-sidebar"', false)
            ->assertSee('data-section="resources-canonical-premium"', false)
            ->assertSee('data-section="resources-canonical-open-access"', false)
            ->assertSee('data-test-id="resources-canonical-off-campus"', false);
    }

    public function test_resources_page_preserves_canonical_section_order(): void
    {
        $content = $this->get('/resources')->assertOk()->getContent();

        $heroPos    = strpos($content, 'data-section="resources-canonical-hero"');
        $mainPos    = strpos($content, 'data-section="resources-canonical-main"');
        $sidebarPos = strpos($content, 'data-section="resources-canonical-sidebar"');
        $premiumPos = strpos($content, 'data-section="resources-canonical-premium"');
        $openPos    = strpos($content, 'data-section="resources-canonical-open-access"');

        $this->assertNotFalse($heroPos);
        $this->assertLessThan($mainPos, $heroPos, 'Hero must precede main layout');
        $this->assertLessThan($sidebarPos, $mainPos, 'Main must precede sidebar');
        $this->assertLessThan($premiumPos, $sidebarPos, 'Sidebar must precede premium section');
        $this->assertLessThan($openPos, $premiumPos, 'Premium must precede open-access section');
    }

    public function test_resources_page_does_not_reintroduce_legacy_shell_markers(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertDontSee('id="resources-page"', false)
            ->assertDontSee('id="resources-pathways"', false)
            ->assertDontSee('id="resources-filter-bar"', false)
            ->assertDontSee('data-resource-grid', false)
            ->assertDontSee('id="resource-support-section"', false)
            ->assertDontSee('data-test-id="resources-pathways"', false)
            ->assertDontSee('data-test-id="pathway-students"', false)
            ->assertDontSee('data-test-id="pathway-faculty"', false)
            ->assertDontSee('data-test-id="pathway-researchers"', false)
            ->assertDontSee('resource-card--featured', false)
            ->assertDontSee('resource-card--small', false)
            ->assertDontSee('data-test-id="core-databases-label"', false);
    }

    public function test_resources_page_does_not_reintroduce_legacy_brand(): void
    {
        $response = $this->get('/resources?lang=en');

        $response
            ->assertOk()
            ->assertDontSee('KazTBU Digital Library')
            ->assertDontSee('Athenaeum')
            ->assertSee('KazUTB Smart Library');
    }

    public function test_resources_page_renders_real_premium_resources(): void
    {
        $response = $this->get('/resources');

        // Premium databases sourced from config/external_resources.php — remote_auth + campus.
        $response
            ->assertOk()
            ->assertSee('IPR SMART', false)
            ->assertSee('eLIBRARY.RU', false)
            ->assertSee('Polpred.com', false)
            ->assertSee('Республиканская межвузовская электронная библиотека', false)
            ->assertSee('data-test-id="resources-canonical-premium-card-ipr-smart"', false)
            ->assertSee('data-test-id="resources-canonical-premium-card-elibrary"', false)
            ->assertSee('data-test-id="resources-canonical-premium-link-ipr-smart"', false);
    }

    public function test_resources_page_renders_real_open_access_resources(): void
    {
        $response = $this->get('/resources');

        // Open access tools sourced from config — access_type=open.
        $response
            ->assertOk()
            ->assertSee('Directory of Open Access Journals (DOAJ)', false)
            ->assertSee('КиберЛенинка', false)
            ->assertSee('OAPEN Library', false)
            ->assertSee('data-test-id="resources-canonical-open-row-doaj"', false)
            ->assertSee('data-test-id="resources-canonical-open-row-cyberleninka"', false)
            ->assertSee('data-test-id="resources-canonical-open-link-doaj"', false);
    }

    public function test_resources_page_premium_resources_split_from_open_access(): void
    {
        $content = $this->get('/resources')->assertOk()->getContent();

        $premiumPos = strpos($content, 'data-section="resources-canonical-premium"');
        $openPos    = strpos($content, 'data-section="resources-canonical-open-access"');
        $doajPos    = strpos($content, 'data-test-id="resources-canonical-open-row-doaj"');
        $iprPos     = strpos($content, 'data-test-id="resources-canonical-premium-card-ipr-smart"');

        $this->assertNotFalse($doajPos);
        $this->assertNotFalse($iprPos);
        // IPR (premium) appears inside premium section (before open-access section starts).
        $this->assertGreaterThan($premiumPos, $iprPos);
        $this->assertLessThan($openPos, $iprPos);
        // DOAJ (open access) appears inside open-access section.
        $this->assertGreaterThan($openPos, $doajPos);
    }

    public function test_resources_page_resource_links_are_real_and_external(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            // Real external URLs from config/external_resources.php.
            ->assertSee('href="https://www.iprbookshop.ru/"', false)
            ->assertSee('href="https://doaj.org/"', false)
            ->assertSee('href="https://cyberleninka.ru/"', false)
            // All resource links must open in a new tab with safe rel attributes.
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            // No dead '#' hrefs from canonical placeholder.
            ->assertDontSee('<a class="resources-canonical__card-link" href="#"', false)
            ->assertDontSee('<a class="resources-canonical__row-link" href="#"', false);
    }

    public function test_resources_page_renders_russian_locale_chrome(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('Институциональные', false)
            ->assertSee('Справочник', false)
            ->assertSee('Премиальные базы данных', false)
            ->assertSee('Инструменты открытого доступа', false)
            ->assertSee('Фильтр поиска', false)
            ->assertSee('Доступ вне кампуса', false);
    }

    public function test_resources_page_renders_english_locale_chrome(): void
    {
        $response = $this->get('/resources?lang=en');

        $response
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Institutional')
            ->assertSee('Resources')
            ->assertSee('Directory')
            ->assertSee('Premium Databases')
            ->assertSee('Open Access Tools')
            ->assertSee('Refine Search')
            ->assertSee('Off-Campus Access')
            ->assertSee('Engineering &amp; Tech', false)
            ->assertSee('Business &amp; Economics', false);
    }

    public function test_resources_page_renders_kazakh_locale_chrome(): void
    {
        $response = $this->get('/resources?lang=kk');

        $response
            ->assertOk()
            ->assertSee('<html lang="kk">', false)
            ->assertSee('Институционалдық', false)
            ->assertSee('Премиум дерекқорлар', false)
            ->assertSee('Ашық қол жеткізу құралдары', false)
            ->assertSee('Іздеуді нақтылау', false)
            ->assertSee('Кампустан тыс қол жеткізу', false);
    }

    public function test_resources_page_sidebar_facets_are_ui_only_checkboxes(): void
    {
        $response = $this->get('/resources?lang=en');

        $response
            ->assertOk()
            ->assertSee('data-facet-type="discipline"', false)
            ->assertSee('data-facet-slug="engineering"', false)
            ->assertSee('data-facet-type="resource-type"', false)
            ->assertSee('data-facet-slug="journals"', false)
            ->assertSee('name="discipline[]"', false)
            ->assertSee('name="resource_type[]"', false);
    }

    public function test_resources_page_off_campus_cta_targets_contacts(): void
    {
        // The canonical off-campus card points to a real institutional help path.
        $response = $this->get('/resources?lang=en');

        $response
            ->assertOk()
            ->assertSee('data-test-id="resources-canonical-off-campus-cta"', false)
            // En variant keeps ?lang=en on the internal link.
            ->assertSee('href="/contacts?lang=en"', false);

        $this->get('/resources')
            ->assertOk()
            ->assertSee('href="/contacts"', false);
    }

    public function test_for_teachers_legacy_path_still_redirects_to_resources(): void
    {
        // Legacy /for-teachers → /resources 301 redirect preserved; the canonical rebuild
        // drops tailored pathway cards but the redirect target remains valid.
        $this->get('/for-teachers')
            ->assertStatus(301)
            ->assertRedirect('/resources');
    }
}
