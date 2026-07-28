<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalReaderReviewWorkflowTest extends TestCase
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

    public function test_reader_review_queue_lists_readers_needing_review(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reader review queue test.');

        [$readerId1, $readerId2] = $this->pickTwoDistinctReaderIds();
        $reasonCode = 'READER_STEWARD_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.readers')->where('id', $readerId1)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        DB::connection('pgsql')->table('app.readers')->where('id', $readerId2)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        $response = $this->withSession($this->staffSession())->getJson(
            '/api/v1/internal/review/readers?reason_code=' . urlencode($reasonCode) . '&limit=100'
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'readerIdentity' => ['id', 'fullName', 'legacyCode'],
                    'lifecycle' => ['needsReview', 'reviewReasonCodes', 'registrationAt', 'reregistrationAt'],
                    'updatedAt',
                ],
            ],
            'meta' => ['page', 'per_page', 'total', 'total_pages', 'totalPages'],
            'filters' => ['reason_code'],
            'source',
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $ids = array_map(static fn (array $row): string => (string) ($row['readerIdentity']['id'] ?? ''), $data);
        $this->assertContains($readerId1, $ids);
        $this->assertNotContains($readerId2, $ids);

        foreach ($data as $row) {
            $this->assertTrue((bool) ($row['lifecycle']['needsReview'] ?? false));
        }
    }

    public function test_reader_review_summary_counts_review_states(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reader review summary test.');

        [$readerId] = $this->pickTwoDistinctReaderIds();
        $reasonCode = 'READER_SUMMARY_' . Str::upper(Str::random(6));

        $totalBefore = DB::connection('pgsql')->table('app.readers')->count();
        $needsReviewBefore = DB::connection('pgsql')->table('app.readers')->where('needs_review', true)->count();

        DB::connection('pgsql')->table('app.readers')->where('id', $readerId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/readers-summary?top_limit=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'entity',
                'totalReaders',
                'needsReviewCount',
                'resolvedCount',
                'topReasonCodes' => [
                    '*' => ['reasonCode', 'count'],
                ],
            ],
            'source',
        ]);

        $this->assertEquals('readers', $response->json('data.entity'));
        $this->assertEquals($totalBefore, $response->json('data.totalReaders'));
        $this->assertGreaterThanOrEqual($needsReviewBefore, $response->json('data.needsReviewCount'));

        $topReasonCodes = $response->json('data.topReasonCodes');
        $reasonCodes = array_map(static fn (array $row): string => (string) ($row['reasonCode'] ?? ''), $topReasonCodes ?? []);
        $this->assertContains($reasonCode, $reasonCodes);
    }

    public function test_reader_review_resolve_clears_review_flags_and_writes_audit(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reader resolve test.');

        [$readerId] = $this->pickTwoDistinctReaderIds();
        $reasonCode = 'READER_RESOLVE_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.readers')->where('id', $readerId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/readers/' . $readerId . '/resolve', [
                'resolution_note' => 'Reader profile review signal resolved by librarian.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.readerIdentity.id', $readerId)
            ->assertJsonPath('data.lifecycle.needsReview', false)
            ->assertJsonPath('data.lifecycle.reviewReasonCodes', []);

        $row = DB::connection('pgsql')
            ->table('app.readers')
            ->select(['needs_review', 'review_reason_codes'])
            ->where('id', $readerId)
            ->first();

        $this->assertNotNull($row);
        $this->assertFalse((bool) ($row->needs_review ?? false));
        $this->assertTrue($this->isPgArrayEmpty((string) ($row->review_reason_codes ?? '{}')));

        $audit = CirculationAuditEvent::query()
            ->where('action', 'internal_reader_review_resolved')
            ->where('entity_type', 'reader')
            ->where('entity_id', $readerId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($readerId, $audit->reader_id);
        $this->assertSame(true, $audit->previous_state['lifecycle']['needsReview'] ?? null);
        $this->assertSame(false, $audit->new_state['lifecycle']['needsReview'] ?? null);
    }

    public function test_reader_review_resolve_fails_with_invalid_reader_id(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for invalid reader ID test.');

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/readers/not-a-uuid/resolve', [
                'resolution_note' => 'Test',
            ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_reader_id')
            ->assertJsonPath('success', false);
    }

    public function test_reader_review_resolve_fails_when_reader_not_found(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reader-not-found test.');

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/readers/' . (string) Str::uuid() . '/resolve', [
                'resolution_note' => 'Test',
            ]);

        $response
            ->assertStatus(404)
            ->assertJsonPath('error', 'reader_not_found')
            ->assertJsonPath('success', false);
    }

    public function test_reader_review_resolve_fails_when_review_not_required(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for review-not-required test.');

        [$readerId] = $this->pickTwoDistinctReaderIds();

        DB::connection('pgsql')->table('app.readers')->where('id', $readerId)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/readers/' . $readerId . '/resolve', [
                'resolution_note' => 'Test',
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('error', 'review_not_required')
            ->assertJsonPath('success', false);
    }

    public function test_reader_review_resolve_rejects_non_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reader actor override authorization test.');

        [$readerId] = $this->pickTwoDistinctReaderIds();

        DB::connection('pgsql')->table('app.readers')->where('id', $readerId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{OVERRIDE_CHECK}',
        ]);

        $response = $this->withSession($this->staffSession('librarian'))->postJson(
            '/api/v1/internal/review/readers/' . $readerId . '/resolve',
            ['actor_user_id' => (string) Str::uuid()]
        );

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'insufficient_staff_role');
    }

    public function test_reader_review_queue_requires_staff_auth(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reader queue auth test.');

        $response = $this->getJson('/api/v1/internal/review/readers');

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    /**
     * @return array{string, string}
     */
    private function pickTwoDistinctReaderIds(): array
    {
        $ids = DB::connection('pgsql')
            ->table('app.readers')
            ->limit(2)
            ->pluck('id')
            ->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('At least two readers are required for internal reader review workflow tests.');
        }

        return [(string) $ids[0], (string) $ids[1]];
    }

    private function isPgArrayEmpty(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed === '' || $trimmed === '{}' || $trimmed === '{NULL}';
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
