<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ExternalResource;
use App\Models\Fund;
use Database\Seeders\ExternalResourceSeeder;
use Database\Seeders\LibraryStructureSeeder;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminSeederDeletionSafetyTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_repeated_seed_does_not_restore_soft_deleted_admin_records(): void
    {
        $branch = Branch::query()->where('code', 'READING-ROOM')->firstOrFail();
        $fund = Fund::query()->where('code', 'COLLEGE')->firstOrFail();
        $resource = ExternalResource::query()->orderBy('id')->firstOrFail();

        $branch->delete();
        $fund->delete();
        $resource->delete();

        app(LibraryStructureSeeder::class)->run();
        app(ExternalResourceSeeder::class)->run();

        $this->assertSoftDeleted('branches', ['id' => $branch->getKey()]);
        $this->assertSoftDeleted('funds', ['id' => $fund->getKey()]);
        $this->assertSoftDeleted('external_resources', ['id' => $resource->getKey()]);
    }
}
