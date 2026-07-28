<?php

namespace Tests\Feature\Api;

use App\Services\Library\ReaderContactNormalizationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReaderContactNormalizationTest extends TestCase
{
    private bool $useLivePgsql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->canUseLivePgsql()) {
            $this->useLivePgsql = true;
            Config::set('database.default', 'pgsql');
            DB::purge('pgsql');
        }
    }

    private function canUseLivePgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function requireLivePgsql(): void
    {
        if (! $this->useLivePgsql) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }
    }

    private function staffSession(): array
    {
        return [
            'library.user' => [
                'id' => 'aaaaaaaa-0000-0000-0000-111111111111',
                'role' => 'librarian',
                'email' => 'staff@digital-library.test',
                'name' => 'Test Staff',
            ],
        ];
    }

    // ── Email validation unit tests ────────────────────────

    public function test_email_validation_valid(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('EMAIL', 'Test@Example.COM');
        $this->assertTrue($result['valid']);
        $this->assertEquals('test@example.com', $result['normalized']);
        $this->assertNull($result['error']);
    }

    public function test_email_validation_invalid(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('EMAIL', 'not-an-email');
        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
    }

    public function test_email_validation_empty(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('EMAIL', '');
        $this->assertFalse($result['valid']);
    }

    // ── Phone validation unit tests ────────────────────────

    public function test_phone_validation_kz_8_format(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('PHONE', '87762651773');
        $this->assertTrue($result['valid']);
        $this->assertEquals('+77762651773', $result['normalized']);
    }

    public function test_phone_validation_kz_7_format(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('PHONE', '77762651773');
        $this->assertTrue($result['valid']);
        $this->assertEquals('+77762651773', $result['normalized']);
    }

    public function test_phone_validation_with_plus(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('PHONE', '+7 776 265 17 73');
        $this->assertTrue($result['valid']);
        $this->assertEquals('+77762651773', $result['normalized']);
    }

    public function test_phone_validation_too_short(): void
    {
        $svc = new ReaderContactNormalizationService();
        $result = $svc->validateContact('PHONE', '12345');
        $this->assertFalse($result['valid']);
    }

    // ── API Route tests ────────────────────────────────────

    public function test_contact_stats_route_exists(): void
    {
        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/reader-contacts/stats');

        $this->assertNotEquals(404, $response->status());
    }

    public function test_contact_stats_returns_data(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/reader-contacts/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'totalContacts',
                'byType' => ['email', 'phone'],
                'placeholderCount',
                'validFormatCount',
                'invalidFormatCount',
                'totalReaders',
                'readersWithValidEmail',
                'readersWithoutEmail',
            ],
        ]);
        $this->assertGreaterThan(0, $response->json('data.totalContacts'));
    }

    public function test_reader_contacts_route_works(): void
    {
        $this->requireLivePgsql();

        $reader = DB::connection('pgsql')->table('app.readers')->first();
        if ($reader === null) {
            $this->markTestSkipped('No readers available.');
        }

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/reader-contacts/' . $reader->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['readerId', 'fullName', 'contacts'],
        ]);
    }

    public function test_reader_contacts_rejects_invalid_uuid(): void
    {
        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/reader-contacts/not-a-uuid');

        $response->assertStatus(422);
    }

    public function test_validate_contact_endpoint(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/reader-contacts/validate', [
                'contact_type' => 'EMAIL',
                'value' => 'test@example.com',
            ]);

        $response->assertOk();
        $response->assertJson(['data' => ['valid' => true, 'normalized' => 'test@example.com']]);
    }

    public function test_validate_contact_invalid_email(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/reader-contacts/validate', [
                'contact_type' => 'EMAIL',
                'value' => 'not-valid',
            ]);

        $response->assertOk();
        $response->assertJson(['data' => ['valid' => false]]);
    }

    public function test_validate_contact_phone(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/reader-contacts/validate', [
                'contact_type' => 'PHONE',
                'value' => '87001234567',
            ]);

        $response->assertOk();
        $response->assertJson(['data' => ['valid' => true, 'normalized' => '+77001234567']]);
    }

    public function test_update_contact_requires_value(): void
    {
        $response = $this->withSession($this->staffSession())
            ->putJson('/api/v1/internal/reader-contacts/00000000-0000-0000-0000-000000000001/update', []);

        $response->assertStatus(422);
    }

    public function test_add_contact_requires_type_and_value(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/reader-contacts/00000000-0000-0000-0000-000000000001/add', []);

        $response->assertStatus(422);
    }

    public function test_add_contact_rejects_invalid_type(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/reader-contacts/00000000-0000-0000-0000-000000000001/add', [
                'contact_type' => 'FAX',
                'value' => '12345',
            ]);

        $response->assertStatus(422);
    }

    public function test_bulk_normalize_route(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/reader-contacts/bulk-normalize', ['limit' => 10]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['processed', 'updated', 'valid', 'invalid'],
        ]);
    }

    public function test_stewardship_page_has_contact_normalization_section(): void
    {
        $response = $this->withSession($this->staffSession())
            ->get('/internal/stewardship');

        $response->assertOk();
        $response->assertSee('Нормализация контактов');
        $response->assertSee('reader-contacts/stats');
    }
}
