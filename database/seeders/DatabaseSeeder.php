<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // RBAC must be seeded before any account, so role assignment has
        // something to attach to.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            DemoUserSeeder::class,
            SettingsSeeder::class,
            LibraryStructureSeeder::class,
            ExternalResourceSeeder::class,
            UdcCodeSeeder::class,
            LibraryCatalogSeeder::class,
        ]);
    }
}
