<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminOverviewPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_admin_overview_redirects_guests_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_all_control_plane_pages_render_in_russian_for_admin(): void
    {
        $pages = [
            '/admin' => 'Обзор управления',
            '/admin/users' => 'Управление пользователями',
            '/admin/roles' => 'Управление ролями',
            '/admin/logs' => 'Управление, журналы и мониторинг',
            '/admin/news' => 'Новости и объявления',
            '/admin/feedback' => 'Входящие обращения',
            '/admin/reports' => 'Отчёты и аналитика',
            '/admin/settings' => 'Системные настройки',
            '/admin/integrations' => 'Мониторинг интеграций',
            '/admin/branches' => 'Филиалы и пункты обслуживания',
            '/admin/external-resources' => 'Внешние ресурсы',
        ];

        foreach ($pages as $uri => $title) {
            $this->signInToLibraryAs($this->adminUser)
                ->get($uri.'?lang=ru')
                ->assertOk()
                ->assertSee('lang="ru"', false)
                ->assertSee($title, false);
        }
    }

    public function test_dashboard_uses_real_aggregates_and_not_legacy_mock_numbers(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin?lang=ru')
            ->assertOk()
            ->assertSee('Активные пользователи', false)
            ->assertSee('Активные выдачи', false)
            ->assertSee('Все показатели рассчитаны по актуальным записям базы данных.', false)
            ->assertDontSee('12,450', false)
            ->assertDontSee('8,291', false)
            ->assertDontSee('142', false);
    }

    public function test_real_configuration_and_seeded_structure_are_visible(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/settings?lang=ru')
            ->assertOk()
            ->assertSee('Максимум активных бронирований', false)
            ->assertSee('Управляется APP_DEMO_LOGIN_ENABLED в серверном окружении и не может быть включён через базу данных.', false);

        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/branches?lang=ru')
            ->assertOk()
            ->assertSee('Научная библиотека', false)
            ->assertSee('Основной фонд', false);
    }

    public function test_csv_and_pdf_report_exports_are_real_downloads(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/reports/roles/export/csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $pdf = $this->signInToLibraryAs($this->adminUser)
            ->get('/admin/reports/user-activity/export/pdf');

        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());
    }
}
