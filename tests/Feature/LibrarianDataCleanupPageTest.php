<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LibrarianDataCleanupPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->adminUser->forceFill(['locale' => 'ru'])->save();
        $this->librarian = $this->makeControlPlaneUser('librarian', ['locale' => 'ru']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/librarian/data-cleanup')->assertRedirectContains('/login');
    }

    public function test_librarian_and_admin_can_view_localized_data_cleanup(): void
    {
        foreach ([$this->librarian, $this->adminUser] as $staff) {
            $this->signInToLibraryAs($staff)->get('/librarian/data-cleanup?lang=ru')->assertOk()
                ->assertSee('Контроль качества каталога', false)
                ->assertSee('Прогресс за сегодня', false)
                ->assertSee('Незавершённые записи', false)
                ->assertSee('Экземпляры без места хранения', false)
                ->assertDontSee('Data Stewardship', false)
                ->assertDontSee('Critical Anomalies', false);
        }
    }

    public function test_member_is_forbidden(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $this->signInToLibraryAs($member)->get('/librarian/data-cleanup')->assertForbidden();
    }
}
