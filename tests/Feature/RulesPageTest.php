<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster B.2 — Public Library Rules page (/rules).
 *
 * /rules renders resources/views/rules.blade.php extending layouts.public.
 * Content is driven by the $rulesPublicProvider closure in routes/web.php.
 * It publishes general guidance while omitting dates, fixed limits,
 * penalties, and other terms that have no approved policy source.
 *
 * Section order and anchor IDs (#general, #borrowing, #digital, #conduct,
 * #penalties) are frozen per Cluster B Content Contract §2 and are a
 * public contract — they MUST stay stable.
 *
 * Per contract §8 the route is NOT added to the primary navbar; the
 * footer exposes a "Правила библиотеки / Кітапхана ережелері / Library
 * Rules" link.
 */
class RulesPageTest extends TestCase
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

    public function test_guest_can_access_rules_page(): void
    {
        $response = $this->get('/rules?lang=kk');

        $response->assertOk();
        $response->assertSee('Кітапхананы пайдалану ережелері', false);
    }

    public function test_rules_page_renders_all_frozen_section_markers(): void
    {
        $response = $this->get('/rules?lang=ru');

        $response->assertOk();
        // Frozen section markers per Cluster B Content Contract §2.
        $response->assertSee('data-section="rules-header"', false);
        $response->assertSee('data-section="rules-toc"', false);
        $response->assertSee('data-section="rules-general"', false);
        $response->assertSee('data-section="rules-borrowing"', false);
        $response->assertSee('data-section="rules-digital-access"', false);
        $response->assertSee('data-section="rules-conduct"', false);
        $response->assertSee('data-section="rules-penalties"', false);
        $response->assertSee('data-section="rules-footer-meta"', false);
    }

    public function test_rules_page_exposes_stable_anchor_ids(): void
    {
        $response = $this->get('/rules');

        $response->assertOk();
        // Anchor IDs are a public contract.
        $response->assertSee('id="general"', false);
        $response->assertSee('id="borrowing"', false);
        $response->assertSee('id="digital"', false);
        $response->assertSee('id="conduct"', false);
        $response->assertSee('id="penalties"', false);
        // TOC must link to those anchors.
        $response->assertSee('href="#general"', false);
        $response->assertSee('href="#borrowing"', false);
        $response->assertSee('href="#digital"', false);
        $response->assertSee('href="#conduct"', false);
        $response->assertSee('href="#penalties"', false);
    }

    public function test_rules_header_does_not_invent_effective_or_review_dates(): void
    {
        $response = $this->get('/rules');

        $response->assertOk();
        $response->assertDontSee('data-test-id="rules-effective-date"', false);
        $response->assertDontSee('data-test-id="rules-last-reviewed"', false);
        $response->assertDontSee('2026-04-01', false);
        $response->assertDontSee('2026-04-22', false);
    }

    public function test_rules_page_renders_russian_locale_variant(): void
    {
        $response = $this->get('/rules?lang=ru');

        $response->assertOk();
        $response->assertSee('Правила пользования библиотекой', false);
        // Section headlines
        $response->assertSee('Общие положения', false);
        $response->assertSee('Выдача и возврат', false);
        $response->assertSee('Электронный доступ', false);
        $response->assertSee('Правила поведения', false);
        $response->assertSee('Вопросы и поддержка', false);
        $response->assertSee('Срок возврата', false);
        $response->assertSee('В личном кабинете', false);
        $response->assertSee('Если действие доступно', false);
        $response->assertDontSee('Нарушения и взыскания', false);
        $response->assertDontSee('Шкала приостановки доступа', false);
    }

    public function test_rules_page_renders_kazakh_locale_variant(): void
    {
        $response = $this->get('/rules?lang=kk');

        $response->assertOk();
        $response->assertSee('Кітапхананы пайдалану ережелері', false);
        $response->assertSee('Жалпы ережелер', false);
        $response->assertSee('Беру және қайтару', false);
        $response->assertSee('Электрондық қолжетімділік', false);
        $response->assertSee('Мінез-құлық ережелері', false);
        $response->assertSee('Сұрақтар мен қолдау', false);
        $response->assertSee('Қайтару мерзімі', false);
        $response->assertSee('Жеке кабинетте', false);
        $response->assertDontSee('Бұзушылықтар мен шаралар', false);
    }

    public function test_rules_page_renders_english_locale_variant(): void
    {
        $response = $this->get('/rules?lang=en');

        $response->assertOk();
        $response->assertSee('Library Usage Rules', false);
        $response->assertSee('General provisions', false);
        $response->assertSee('Borrowing and returns', false);
        $response->assertSee('Digital access', false);
        $response->assertSee('Code of conduct', false);
        $response->assertSee('Questions and support', false);
        $response->assertSee('Current loan', false);
        $response->assertSee('In the reader account', false);
        $response->assertSee('When the action is available', false);
        $response->assertSee('Based on current availability', false);
        $response->assertSee('Feedback', false);
        $response->assertDontSee('Violations and penalties', false);
        $response->assertDontSee('controlled viewer with no download path', false);
        $response->assertDontSee('Access suspension ladder', false);
        $response->assertDontSee('Right of appeal', false);
    }

    public function test_borrowing_section_shows_only_current_loan_facts_without_fixed_audience_limits(): void
    {
        $response = $this->get('/rules?lang=en');

        $response->assertOk();
        $response->assertSee('Current loan', false);
        $response->assertSee('Due date', false);
        $response->assertDontSee('Undergraduate students', false);
        $response->assertDontSee('doctoral students', false);
        $response->assertDontSee('Faculty and research staff', false);
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'data-audience-slot'),
            'Only the source-backed current-loan card should be published.'
        );
    }

    public function test_rules_footer_meta_renders_related_links(): void
    {
        $enResponse = $this->get('/rules?lang=en');
        $enResponse->assertOk();
        // Both related links must be present.
        $enResponse->assertSee('data-test-id="rules-contacts-link"', false);
        $enResponse->assertSee('data-test-id="rules-leadership-link"', false);
        $enResponse->assertSee('href="/contacts?lang=en"', false);
        $enResponse->assertSee('href="/leadership?lang=en"', false);

        // Russian default must keep bare paths (no ?lang=ru suffix).
        $ruResponse = $this->get('/rules?lang=ru');
        $ruResponse->assertOk();
        $ruResponse->assertSee('href="/contacts?lang=ru"', false);
        $ruResponse->assertSee('href="/leadership?lang=ru"', false);
    }

    public function test_footer_exposes_rules_link_in_all_locales(): void
    {
        $this->get('/rules?lang=ru')
            ->assertOk()
            ->assertSee('Правила библиотеки', false);

        $this->get('/rules?lang=kk')
            ->assertOk()
            ->assertSee('Кітапхана ережелері', false);

        $this->get('/rules?lang=en')
            ->assertOk()
            ->assertSee('>Library Rules<', false);
    }

    public function test_rules_page_does_not_reintroduce_legacy_brand(): void
    {
        $response = $this->get('/rules?lang=en');

        $response->assertOk();
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('Curator Archive', false);
        $response->assertDontSee('KazTBU Digital Library', false);
        $response->assertDontSee('KazUTB Digital Library', false);
    }

    public function test_primary_navbar_does_not_gain_rules_item(): void
    {
        // Per Cluster B Content Contract §8: primary navbar stays flat.
        // /rules is surfaced via the footer only.
        $response = $this->get('/rules?lang=en');

        $response->assertOk();
        $response->assertDontSee(
            '<a href="/rules?lang=en" class="px-3 py-2',
            false
        );
    }

    public function test_authenticated_reader_can_view_rules_page(): void
    {
        $response = $this->withSession(['library.user' => [
            'id' => 'qa-reader-001',
            'name' => 'QA Reader',
            'role' => 'reader',
        ]])->get('/rules?lang=en');

        $response->assertOk();
        $response->assertSee('Library Usage Rules', false);
        $response->assertSee('Sign out', false);
    }

    public function test_librarian_can_view_rules_page(): void
    {
        $this->loginAs('librarian');

        $this->get('/rules?lang=en')
            ->assertOk()
            ->assertSee('Library Usage Rules', false);
    }

    public function test_admin_can_view_rules_page(): void
    {
        $this->loginAs('admin');

        $this->get('/rules?lang=en')
            ->assertOk()
            ->assertSee('Library Usage Rules', false);
    }
}
