<?php

namespace Tests\Feature;

use App\Models\Catalog\RepositoryItem;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class LibrarianRepositoryPageTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/librarian/repository');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_publication_process_is_exactly_four_localised_steps(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            $copy = require base_path("lang/{$locale}/librarian.php");
            $this->assertCount(4, $copy['repository']['process_steps']);
        }

        $view = file_get_contents(resource_path('views/librarian/repository/index.blade.php'));
        $this->assertSame(1, substr_count((string) $view, "@foreach(__('librarian.repository.process_steps') as \$step)"));
    }

    public function test_all_seven_required_work_types_have_localised_labels(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            $copy = require base_path("lang/{$locale}/librarian.php");
            foreach (RepositoryItem::WORK_TYPES as $type) {
                $this->assertArrayHasKey($type, $copy['repository']['work_types']);
            }
        }
    }

    public function test_role_matrix_keeps_business_approval_with_director_not_admin(): void
    {
        $this->assertContains('repository.approve', RoleSeeder::DIRECTOR);
        $this->assertContains('repository.publish', RoleSeeder::DIRECTOR);
        $this->assertContains('repository.request_changes', RoleSeeder::DIRECTOR);
        $this->assertNotContains('repository.approve', RoleSeeder::ADMIN);
        $this->assertNotContains('repository.publish', RoleSeeder::ADMIN);
        $this->assertContains('repository.upload', RoleSeeder::LIBRARIAN_EXTRA);
    }
}
