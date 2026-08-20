<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderNotification;
use App\Services\Catalog\LibraryNotificationService;
use App\Support\LocaleResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LocalizationArchitectureTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        config(['app.locale' => 'kk', 'app.fallback_locale' => 'kk']);
        app()->setLocale('kk');
    }

    public function test_new_guest_uses_kazakh_locale_brand_and_validation(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<html lang="kk"', false)
            ->assertSee(__('brand.university.full'))
            ->assertSee(__('brand.library.name'))
            ->assertSee('data-locale-switcher', false)
            ->assertDontSee('KazUTB Smart'.' Library', false);

        $this->post('/locale', [])->assertSessionHasErrors('locale');
        $this->assertSame('kk', app()->getLocale());
    }

    public function test_every_primary_public_page_uses_kazakh_without_a_language_parameter(): void
    {
        foreach (['/', '/login', '/catalog', '/resources', '/news', '/events', '/about', '/leadership', '/rules', '/contacts'] as $path) {
            $this->flushSession();

            $this->get($path)
                ->assertOk()
                ->assertSee('<html lang="kk"', false);
        }
    }

    public function test_guest_locale_switch_persists_and_rejects_open_redirects(): void
    {
        $this->from('/catalog?q=қазақ')->post('/locale', [
            'locale' => 'ru',
            'return_to' => '/catalog?q=қазақ',
        ])->assertRedirect('/catalog?q='.rawurlencode('қазақ'))->assertSessionHas('locale', 'ru');

        $this->get('/catalog?q=қазақ')
            ->assertOk()
            ->assertSee('<html lang="ru"', false)
            ->assertSee('data-locale-switcher', false);

        $this->post('/locale', [
            'locale' => 'en',
            'return_to' => 'https://attacker.example/phishing',
        ])->assertRedirect('/');

        $this->post('/locale', ['locale' => 'de'])
            ->assertSessionHasErrors('locale');
    }

    public function test_authenticated_user_preference_has_priority_and_is_saved(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $this->signInToLibraryAs($member)
            ->post('/locale', ['locale' => 'en', 'return_to' => '/dashboard'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'en');

        $this->assertSame('en', $member->refresh()->locale);
        $this->signInToLibraryAs($member)->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Scientific Library')
            ->assertSee('data-locale-switcher', false);
    }

    public function test_account_without_a_preference_uses_kazakh_without_persisting_a_fake_choice(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => null]);

        $this->signInToLibraryAs($member)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="kk"', false);

        $this->assertNull($member->refresh()->locale);
    }

    public function test_guest_locale_cookie_is_respected_on_active_directory_login_page(): void
    {
        $this->withCookie(LocaleResolver::COOKIE, 'en')
            ->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Welcome back')
            ->assertSee('Corporate login')
            ->assertDontSee('data-demo-slug', false);
    }

    public function test_canonical_shells_render_their_locale_without_prohibited_brand(): void
    {
        $matrix = [
            ['member', '/dashboard'],
            ['librarian', '/librarian'],
            ['admin', '/admin'],
        ];

        foreach (LocaleResolver::SUPPORTED as $locale) {
            foreach ($matrix as [$role, $path]) {
                $user = $this->makeControlPlaneUser($role, ['locale' => $locale]);
                $response = $this->signInToLibraryAs($user)->get($path);
                $response->assertOk()
                    ->assertSee('<html lang="'.$locale.'"', false)
                    ->assertSee(__('brand.library.name', locale: $locale))
                    ->assertSee('data-locale-switcher', false)
                    ->assertDontSee('KazUTB Smart'.' Library', false);
            }
        }
    }

    public function test_error_pages_are_localized_for_all_supported_locales(): void
    {
        foreach (LocaleResolver::SUPPORTED as $locale) {
            $this->app['session']->flush();
            $response = $this->withSession(['locale' => $locale])->get('/missing-i18n-page-'.$locale);
            $response->assertNotFound()
                ->assertSee('<html lang="'.$locale.'"', false)
                ->assertSee(__('errors.pages.404.title', locale: $locale))
                ->assertSee('data-locale-switcher', false);
        }
    }

    public function test_i18n_audit_has_full_parity_and_no_critical_problem(): void
    {
        $this->assertSame(0, Artisan::call('library:i18n:audit'));
        $this->assertStringContainsString('critical=0', Artisan::output());
    }

    public function test_notifications_are_locale_neutral_and_legacy_rows_remain_readable(): void
    {
        $reader = $this->makeControlPlaneUser('member', ['locale' => 'en']);
        $service = app(LibraryNotificationService::class);
        $parameters = [
            'title' => 'Clean Code',
            'due' => ['_date' => '2026-08-03T10:00:00+00:00'],
        ];

        app()->setLocale('kk');
        $notification = $service->sendLocalized(
            $reader,
            'loan_due_soon',
            'librarian.notifications.loan_due_soon_title',
            'librarian.notifications.loan_due_soon_body',
            $parameters,
            ['loan_id' => 42],
        );

        $this->assertNotNull($notification);
        $this->assertSame('kk', app()->getLocale(), 'Recipient rendering must not leak into the worker locale.');
        $this->assertSame(__('librarian.notifications.loan_due_soon_title', locale: 'en'), $notification->title);
        $this->assertSame('librarian.notifications.loan_due_soon_title', data_get($notification->payload, '_i18n.title_key'));

        app()->setLocale('ru');
        $this->assertSame(__('librarian.notifications.loan_due_soon_title'), $notification->localizedTitle());
        $this->assertStringContainsString('3 августа 2026 г.', (string) $notification->localizedBody());

        $legacy = ReaderNotification::query()->create([
            'user_id' => $reader->getKey(),
            'event_type' => 'legacy_notice',
            'title' => 'Старое уведомление',
            'body' => 'Исторический текст сохранён.',
            'channel' => 'in_app',
            'delivery_status' => 'sent',
            'attempts' => 1,
        ]);
        $this->assertSame('Старое уведомление', $legacy->localizedTitle());
        $this->assertSame('Исторический текст сохранён.', $legacy->localizedBody());
    }

    public function test_email_delivery_log_uses_recipient_locale(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $reader = $this->makeControlPlaneUser('member', ['locale' => 'en']);

        app(LibraryNotificationService::class)->sendLocalized(
            $reader,
            'loan_renewed',
            'librarian.notifications.loan_renewed_title',
            'librarian.notifications.loan_renewed_body',
            ['title' => 'Clean Code', 'due' => ['_date' => '2026-08-21T10:00:00+00:00']],
            ['loan_id' => 43],
        );

        $email = ReaderNotification::query()->where('user_id', $reader->getKey())->where('channel', 'email')->firstOrFail();
        $this->assertSame(__('librarian.notifications.loan_renewed_title', locale: 'en'), $email->title);
        $this->assertSame('sent', $email->delivery_status);
    }
}
