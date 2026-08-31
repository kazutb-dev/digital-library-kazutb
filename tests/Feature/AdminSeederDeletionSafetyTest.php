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

    public function test_repeated_seed_applies_only_safe_official_url_updates(): void
    {
        $ipr = ExternalResource::query()->where('slug', 'ipr-smart')->firstOrFail();
        $atu = ExternalResource::query()->where('slug', 'atu-library')->firstOrFail();
        $rntb = ExternalResource::query()->where('slug', 'rntb-kazakhstan')->firstOrFail();

        $ipr->update(['url' => 'https://www.iprbookshop.ru/']);
        $atu->update(['url' => null]);
        $rntb->update(['url' => 'https://curated.example.test/rntb']);

        app(ExternalResourceSeeder::class)->run();

        $this->assertSame('https://ipr-smart.ru/', $ipr->refresh()->url);
        $this->assertSame('https://library.atu.edu.kz/', $atu->refresh()->url);
        $this->assertSame('https://curated.example.test/rntb', $rntb->refresh()->url);
    }
}
