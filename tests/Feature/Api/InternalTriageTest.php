<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalTriageTest extends TestCase
{
    private bool $transactionStarted = false;

    /**
     * @return array{library.user: array<string, string>}
     */
    private function staffSession(string $role = 'librarian'): array
    {
        return [
            'library.user' => [
                'id' => (string) Str::uuid(),
                'name' => 'Internal Staff',
                'email' => 'staff@example.test',
                'login' => 'staff',
                'ad_login' => 'staff',
                'role' => $role,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');

        if ($this->canUseLivePgsql()) {
            DB::connection('pgsql')->beginTransaction();
            $this->transactionStarted = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::connection('pgsql')->rollBack();
        }

        parent::tearDown();
    }

    public function test_triage_summary_returns_aggregated_counts(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage summary test.');

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totalUnresolved',
                    'totalEntities',
                    'byEntity' => [
                        'copies' => ['total', 'needsReviewCount', 'resolvedCount'],
                        'documents' => ['total', 'needsReviewCount', 'resolvedCount'],
                        'readers' => ['total', 'needsReviewCount', 'resolvedCount'],
                    ],
                    'qualityIssues',
                    'topReasonCodes',
                ],
                'source',
            ])
            ->assertJsonPath('source', 'internal_triage_aggregation');

        $data = $response->json('data');
        $this->assertIsInt($data['totalUnresolved']);
        $this->assertIsInt($data['totalEntities']);
        $this->assertGreaterThanOrEqual(0, $data['totalUnresolved']);
        $this->assertGreaterThanOrEqual(0, $data['totalEntities']);
    }

    public function test_triage_summary_aggregated_total_equals_per_entity_sum(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage summary aggregation test.');

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-summary');

        $response->assertOk();

        $data = $response->json('data');
        $byEntity = $data['byEntity'];

        $expectedTotal = $byEntity['copies']['needsReviewCount']
            + $byEntity['documents']['needsReviewCount']
            + $byEntity['readers']['needsReviewCount'];

        $this->assertSame($expectedTotal, $data['totalUnresolved']);

        $expectedEntities = $byEntity['copies']['total']
            + $byEntity['documents']['total']
            + $byEntity['readers']['total'];

        $this->assertSame($expectedEntities, $data['totalEntities']);
    }

    public function test_triage_summary_per_entity_counts_are_consistent(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage per-entity consistency test.');

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-summary');

        $response->assertOk();

        $byEntity = $response->json('data.byEntity');

        foreach (['copies', 'documents', 'readers'] as $entity) {
            $total = $byEntity[$entity]['total'];
            $needsReview = $byEntity[$entity]['needsReviewCount'];
            $resolved = $byEntity[$entity]['resolvedCount'];

            $this->assertGreaterThanOrEqual(0, $needsReview, "{$entity} needsReviewCount should be >= 0");
            $this->assertGreaterThanOrEqual(0, $resolved, "{$entity} resolvedCount should be >= 0");
            $this->assertSame($total - $needsReview, $resolved, "{$entity} resolved should equal total minus needsReview");
        }
    }

    public function test_triage_summary_reflects_seeded_review_flags(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage flag seeding test.');

        $copyId = $this->pickEntityId('app.book_copies');
        $documentId = $this->pickEntityId('app.documents');
        $readerId = $this->pickEntityId('app.readers');

        if ($copyId === null || $documentId === null || $readerId === null) {
            $this->markTestSkipped('At least one entity of each type is required for triage seeding test.');
        }

        $reasonCode = 'TRIAGE_SEED_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.book_copies')->where('id', $copyId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);
        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);
        DB::connection('pgsql')->table('app.readers')->where('id', $readerId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-summary?top_limit=20');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, $data['totalUnresolved']);

        $this->assertGreaterThanOrEqual(1, $data['byEntity']['copies']['needsReviewCount']);
        $this->assertGreaterThanOrEqual(1, $data['byEntity']['documents']['needsReviewCount']);
        $this->assertGreaterThanOrEqual(1, $data['byEntity']['readers']['needsReviewCount']);

        $topCodes = array_column($data['topReasonCodes'], 'reasonCode');
        $this->assertContains($reasonCode, $topCodes);
    }

    public function test_triage_reason_codes_returns_aggregated_codes(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage reason codes test.');

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-reason-codes?top_limit=10');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'topReasonCodes',
                ],
                'source',
            ])
            ->assertJsonPath('source', 'internal_triage_aggregation');

        $topCodes = $response->json('data.topReasonCodes');
        $this->assertIsArray($topCodes);

        foreach ($topCodes as $code) {
            $this->assertArrayHasKey('reasonCode', $code);
            $this->assertArrayHasKey('count', $code);
            $this->assertArrayHasKey('entities', $code);
            $this->assertIsString($code['reasonCode']);
            $this->assertIsInt($code['count']);
            $this->assertIsArray($code['entities']);
        }
    }

    public function test_triage_reason_codes_include_per_entity_when_requested(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage per-entity reason codes test.');

        $response = $this->withSession($this->staffSession())->getJson(
            '/api/v1/internal/review/triage-reason-codes?top_limit=5&include_per_entity=true'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'topReasonCodes',
                    'perEntity' => [
                        'copies',
                        'documents',
                        'readers',
                    ],
                ],
            ]);

        foreach (['copies', 'documents', 'readers'] as $entity) {
            $entityCodes = $response->json("data.perEntity.{$entity}");
            $this->assertIsArray($entityCodes);

            foreach ($entityCodes as $code) {
                $this->assertArrayHasKey('reasonCode', $code);
                $this->assertArrayHasKey('count', $code);
            }
        }
    }

    public function test_triage_reason_codes_omit_per_entity_by_default(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage per-entity omission test.');

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-reason-codes');

        $response->assertOk();

        $this->assertArrayNotHasKey('perEntity', $response->json('data'));
    }

    public function test_triage_summary_requires_staff_session(): void
    {
        $response = $this->getJson('/api/v1/internal/review/triage-summary');

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_triage_reason_codes_requires_staff_session(): void
    {
        $response = $this->getJson('/api/v1/internal/review/triage-reason-codes');

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_triage_summary_respects_top_limit_parameter(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for triage top_limit test.');

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/triage-summary?top_limit=2');

        $response->assertOk();

        $topCodes = $response->json('data.topReasonCodes');
        $this->assertIsArray($topCodes);
        $this->assertLessThanOrEqual(2, count($topCodes));
    }

    private function pickEntityId(string $table): ?string
    {
        $row = DB::connection('pgsql')->table($table)->limit(1)->first();

        return $row !== null ? (string) $row->id : null;
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

    private function requireLivePgsql(string $message): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped($message);
        }
    }
}
