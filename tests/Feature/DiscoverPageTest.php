<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Phase 3 Cluster E — /discover canonical-led rebuild.
 *
 * Validates the canonical structure adopted from
 * docs/design-exports/academic_discovery_hub_canonical/code.html:
 *   1. data-section="discover-canonical-hero"
 *   2. data-section="discover-canonical-faculties" (bento 2+1+1+2)
 *   3. data-section="discover-canonical-udc"       (primary UDC pathways)
 *
 * Also guards against regressions to the retired brochure-style shell and to
 * the deprecated "KazTBU" / "Athenaeum" / "Digital Library" branding.
 */
class DiscoverPageTest extends TestCase
{
    public function test_discover_page_renders_ok(): void
    {
        $this->get('/discover')
            ->assertOk()
            ->assertSee('Центр академического поиска')
            ->assertSee('академического');
    }

    public function test_discover_page_has_canonical_section_markers(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        $this->assertStringContainsString('data-section="discover-canonical-hero"', $html);
        $this->assertStringContainsString('data-section="discover-canonical-faculties"', $html);
        $this->assertStringContainsString('data-section="discover-canonical-udc"', $html);
        $this->assertStringContainsString('class="discover-canonical"', $html);
    }

    public function test_discover_page_canonical_section_order(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        $hero     = strpos($html, 'data-section="discover-canonical-hero"');
        $fac      = strpos($html, 'data-section="discover-canonical-faculties"');
        $udc      = strpos($html, 'data-section="discover-canonical-udc"');

        $this->assertNotFalse($hero);
        $this->assertNotFalse($fac);
        $this->assertNotFalse($udc);
        // Canonical order: hero → faculties → UDC pathways.
        $this->assertLessThan($fac, $hero);
        $this->assertLessThan($udc, $fac);
    }

    public function test_discover_page_renders_all_four_faculty_bento_cards_with_catalog_wiring(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        // All four real KazUTB faculties render with their UDC labels + slugs.
        $this->assertStringContainsString('Технологический', $html);
        $this->assertStringContainsString('Экономика и бизнес', $html);
        $this->assertStringContainsString('Инжиниринг и ИТ', $html);
        $this->assertStringContainsString('Военная кафедра', $html);

        $this->assertStringContainsString('UDC 66', $html);
        $this->assertStringContainsString('UDC 33', $html);
        $this->assertStringContainsString('UDC 004 / 62', $html);
        $this->assertStringContainsString('UDC 355', $html);

        // Faculty cards must link into /catalog with the real secondary axis query.
        $this->assertStringContainsString('/catalog?faculty=technology&amp;udc=66', $html);
        $this->assertStringContainsString('/catalog?faculty=economics&amp;udc=33', $html);
        $this->assertStringContainsString('/catalog?faculty=engineering&amp;udc=004', $html);
        $this->assertStringContainsString('/catalog?faculty=military&amp;udc=355', $html);

        // Stable test hooks for UI automation.
        $this->assertStringContainsString('data-test-id="discover-canonical-faculty-technology"', $html);
        $this->assertStringContainsString('data-test-id="discover-canonical-faculty-economics"', $html);
        $this->assertStringContainsString('data-test-id="discover-canonical-faculty-engineering"', $html);
        $this->assertStringContainsString('data-test-id="discover-canonical-faculty-military"', $html);
    }

    public function test_discover_page_renders_primary_udc_pathways_linking_to_catalog(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        // Primary KazUTB UDC coverage axes.
        foreach (['004', '33', '62', '50'] as $code) {
            $this->assertStringContainsString('UDC ' . $code, $html);
            $this->assertStringContainsString('/catalog?udc=' . $code, $html);
            $this->assertStringContainsString('data-test-id="discover-canonical-udc-' . $code . '"', $html);
        }

        // View-all CTA must exist and point at the catalog (no "#" placeholder).
        $this->assertStringContainsString('data-test-id="discover-canonical-udc-view-all"', $html);
        $this->assertStringContainsString('/catalog?sort=udc', $html);
    }

    public function test_discover_page_udc_is_the_primary_axis_and_faculties_carry_udc_chip(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        // Hero lead must name UDC explicitly — UDC is the primary discovery contract.
        $this->assertStringContainsString('Универсальную десятичную классификацию', $html);
        $this->assertStringContainsString('УДК', $html);

        // Every faculty bento card carries its own UDC chip so the UDC contract
        // remains visible even when users enter via the faculty secondary axis.
        $this->assertMatchesRegularExpression('/data-faculty-udc="66"/', $html);
        $this->assertMatchesRegularExpression('/data-faculty-udc="33"/', $html);
        $this->assertMatchesRegularExpression('/data-faculty-udc="004"/', $html);
        $this->assertMatchesRegularExpression('/data-faculty-udc="355"/', $html);
    }

    public function test_discover_page_has_no_bare_hash_links(): void
    {
        $this->get('/discover')
            ->assertOk()
            ->assertDontSee('href="#"', false);
    }

    public function test_discover_page_retires_legacy_shell_markers(): void
    {
        $response = $this->get('/discover')->assertOk();

        // Legacy brochure shell ids/classes from the retired template.
        $response->assertDontSee('id="discover-page"', false);
        $response->assertDontSee('id="discover-hero"', false);
        $response->assertDontSee('id="discover-disciplines"', false);
        $response->assertDontSee('id="discover-pathways"', false);
        $response->assertDontSee('id="discover-workflow"', false);
        $response->assertDontSee('id="discover-metadata"', false);
        $response->assertDontSee('id="discover-bridge"', false);
        $response->assertDontSee('discover-export-page', false);
        $response->assertDontSee('discover-export-hero', false);
        $response->assertDontSee('discover-visual-chip', false);
        $response->assertDontSee('discover-quote-card', false);
        $response->assertDontSee('discover-volume-panel', false);
        $response->assertDontSee('discover-kicker', false);
        $response->assertDontSee('discover-metadata-section', false);
        $response->assertDontSee('discover-pathways-section', false);
        $response->assertDontSee('discover-shell', false);
        $response->assertDontSee('discover-hero-grid', false);
        $response->assertDontSee('discover-hero-visual', false);
        $response->assertDontSee('data-test-id="institutional-pathways"', false);
        // Retired brochure copy.
        $response->assertDontSee('Карта знаний');
        $response->assertDontSee('Research Workflow');
        $response->assertDontSee('Institutional Metadata');
    }

    public function test_discover_page_canonical_hero_is_single_column_no_decorative_chips(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        // Canonical hero is a single-column display + lead — no hero-actions row
        // of CTAs, no decorative chip orbit, no quote card.
        $this->assertStringContainsString('class="discover-canonical__hero"', $html);
        $this->assertStringContainsString('class="discover-canonical__display"', $html);
        $this->assertStringContainsString('class="discover-canonical__lead"', $html);
        $this->assertStringNotContainsString('discover-hero-actions', $html);
        $this->assertStringNotContainsString('discover-visual-orbit', $html);
        $this->assertStringNotContainsString('discover-quote-card', $html);
    }

    public function test_discover_page_faculty_bento_follows_canonical_2_1_1_2_layout(): void
    {
        $html = $this->get('/discover')->assertOk()->getContent();

        // Canonical export bento: large (span 2) + small + small + large (span 2).
        // The first and last cards must carry the span-2 modifier.
        $this->assertMatchesRegularExpression(
            '/data-test-id="discover-canonical-faculty-technology"[^>]*(?:[\s\S]*?)discover-canonical__bento-card--span-2|discover-canonical__bento-card--span-2[\s\S]*?data-test-id="discover-canonical-faculty-technology"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-test-id="discover-canonical-faculty-military"[^>]*(?:[\s\S]*?)discover-canonical__bento-card--span-2|discover-canonical__bento-card--span-2[\s\S]*?data-test-id="discover-canonical-faculty-military"/',
            $html
        );

        // The accent gradient (canonical decorative wash) is applied at minimum
        // to the span-2 cards. Must be present.
        $accentCount = substr_count($html, 'discover-canonical__bento-accent');
        $this->assertGreaterThanOrEqual(2, $accentCount, 'Canonical bento must have at least 2 accent washes.');
    }

    public function test_discover_page_retires_deprecated_branding(): void
    {
        $response = $this->get('/discover')->assertOk();

        // Forbidden legacy brand drift at the page-content level. Note: the bare
        // string "Digital Library" still appears inside shared layout chrome
        // (navbar aria-label, translation key `ui.brand.home_aria`) — that is
        // owned by `resources/views/partials/navbar.blade.php` + `lang/*/ui.php`
        // and is intentionally out of scope for the /discover rebuild. The page
        // body itself must not reintroduce the retired composite product names.
        $response->assertDontSee('KazTBU');
        $response->assertDontSee('КазТБУ');
        $response->assertDontSee('Athenaeum');
        $response->assertDontSee('KazTBU Digital Library');
        $response->assertDontSee('KazUTB Digital Library');
        // Canonical brand surfaces via layout chrome, not page copy.
    }

    public function test_discover_page_supports_kazakh_locale(): void
    {
        $response = $this->get('/discover?lang=kk')->assertOk();

        $response
            ->assertSee('Академиялық ізденіс орталығы')
            ->assertSee('Факультеттер мен кафедралар')
            ->assertSee('Білім маршруттары')
            ->assertSee('Технологиялық')
            ->assertSee('Экономика және бизнес')
            ->assertSee('Инжиниринг және АТ')
            ->assertSee('Әскери кафедра')
            ->assertSee('ӘОЖ');

        $html = $response->getContent();
        // Cross-locale links keep the lang param so navigation stays in Kazakh.
        $this->assertStringContainsString('/catalog?faculty=technology&amp;udc=66&amp;lang=kk', $html);
        $this->assertStringContainsString('/catalog?udc=004&amp;lang=kk', $html);
    }

    public function test_discover_page_supports_english_locale(): void
    {
        $response = $this->get('/discover?lang=en')->assertOk();

        $response
            ->assertSee('Academic Discovery Hub')
            ->assertSee('Faculties &amp; Departments', false)
            ->assertSee('Knowledge Pathways')
            ->assertSee('Faculty of Technology')
            ->assertSee('Faculty of Economics &amp; Business', false)
            ->assertSee('Faculty of Engineering &amp; IT', false)
            ->assertSee('Military Department')
            ->assertSee('Universal Decimal Classification')
            ->assertSee('View Full UDC Tree');

        $html = $response->getContent();
        $this->assertStringContainsString('/catalog?udc=50&amp;lang=en', $html);
    }

    public function test_discover_page_falls_back_to_russian_for_unknown_lang(): void
    {
        $this->get('/discover?lang=zz')
            ->assertOk()
            ->assertSee('Центр академического поиска')
            ->assertDontSee('Academic Discovery Hub');
    }
}
