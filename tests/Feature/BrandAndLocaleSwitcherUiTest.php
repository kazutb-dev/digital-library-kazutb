<?php

namespace Tests\Feature;

use App\Support\LocaleResolver;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class BrandAndLocaleSwitcherUiTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        config(['app.locale' => 'kk', 'app.fallback_locale' => 'kk']);
    }

    public function test_public_login_member_librarian_and_admin_share_the_same_locale_control(): void
    {
        $surfaces = [
            ['guest', '/'],
            ['guest', '/login'],
            ['member', '/dashboard'],
            ['librarian', '/librarian'],
            ['admin', '/admin'],
        ];

        foreach ($surfaces as [$role, $path]) {
            $this->app['session']->flush();
            if ($role !== 'guest') {
                $this->signInToLibraryAs($this->makeControlPlaneUser($role, ['locale' => 'kk']));
            }

            $response = $this->withSession(['locale' => 'kk'])->get($path);
            $response->assertOk()
                ->assertSee('data-locale-globe', false)
                ->assertSee('aria-expanded="false"', false)
                ->assertSee('aria-current="true"', false)
                ->assertSee('ҚАЗ')
                ->assertSee('РУС')
                ->assertSee('ENG')
                ->assertSee('Қазақша')
                ->assertSee('Русский')
                ->assertSee('English')
                ->assertDontSee('🌐');

            $this->assertSame(1, preg_match_all('/<details[^>]*\sdata-locale-switcher(?:\s|>)/', $response->getContent()));
            $this->assertSame(1, preg_match_all('/<svg[^>]*\sdata-locale-globe(?:\s|>)/', $response->getContent()));
        }
    }

    public function test_switcher_uses_localized_short_label_and_preserves_the_current_url(): void
    {
        foreach (LocaleResolver::SUPPORTED as $locale) {
            $this->app['session']->flush();
            $response = $this->withSession(['locale' => $locale])->get('/catalog?q=metadata&page=2');

            $response->assertOk()
                ->assertSee('<html lang="'.$locale.'"', false)
                ->assertSee(__('locale.labels.'.$locale, locale: $locale))
                ->assertSee('name="return_to"', false)
                ->assertSee('q=metadata', false)
                ->assertSee('page=2', false);
        }
    }

    public function test_every_primary_surface_renders_the_official_logo_and_localized_brand(): void
    {
        $surfaces = [
            ['guest', '/'],
            ['guest', '/login'],
            ['member', '/dashboard'],
            ['librarian', '/librarian'],
            ['admin', '/admin'],
        ];

        foreach (LocaleResolver::SUPPORTED as $locale) {
            foreach ($surfaces as [$role, $path]) {
                auth()->logout();
                $this->flushSession();
                if ($role !== 'guest') {
                    $this->signInToLibraryAs($this->makeControlPlaneUser($role, ['locale' => $locale]));
                }

                $response = $this->withSession(['locale' => $locale])->get($path);
                $this->assertSame(200, $response->getStatusCode(), $role.' '.$path.' '.$locale);
                $response
                    ->assertSee('/logo.png', false)
                    ->assertSee(__('brand.library.name', locale: $locale))
                    ->assertSee(__('brand.university.full', locale: $locale))
                    ->assertSee(__('brand.logo_alt', locale: $locale))
                    ->assertDontSee('KazUTB '.'кітапханасы')
                    ->assertDontSee('KazUTB Smart'.' Library')
                    ->assertDontSee('My '.'Library')
                    ->assertDontSee('Your personal reading '.'workspace');
            }
        }
    }

    public function test_staff_role_is_rendered_separately_from_the_unchanging_brand(): void
    {
        foreach (['librarian', 'director', 'senior_librarian', 'acquisitions', 'cataloguer', 'bibliographer'] as $role) {
            $this->app['session']->flush();
            $response = $this->signInToLibraryAs($this->makeControlPlaneUser($role, ['locale' => 'ru']))
                ->get('/librarian');

            $response->assertOk()
                ->assertSee('data-library-brand="sidebar"', false)
                ->assertSee('data-workspace-role', false)
                ->assertSee(__('brand.library.name', locale: 'ru'))
                ->assertSee(__('brand.workspace.'.$role, locale: 'ru'));
        }

        $admin = $this->signInToLibraryAs($this->makeControlPlaneUser('admin', ['locale' => 'ru']))
            ->get('/admin');
        $admin->assertOk()
            ->assertSee(__('brand.workspace.system', locale: 'ru'))
            ->assertSee(__('brand.workspace.admin', locale: 'ru'));
    }

    public function test_standard_error_pages_keep_the_shared_brand_and_locale_switcher(): void
    {
        foreach ([403, 404, 419, 500, 503] as $status) {
            Route::get('/__brand-locale-error-'.$status, static fn () => abort($status));
            $response = $this->withSession(['locale' => 'kk'])->get('/__brand-locale-error-'.$status);

            $response->assertStatus($status)
                ->assertSee('data-library-brand="public"', false)
                ->assertSee('data-locale-globe', false)
                ->assertSee(__('brand.library.name', locale: 'kk'));
        }
    }
}
