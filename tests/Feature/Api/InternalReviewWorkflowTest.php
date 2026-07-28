<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalReviewWorkflowTest extends TestCase
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

    public function test_copy_review_queue_filters_by_reason_code_and_returns_only_needs_review(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for review queue test.');

        [$flaggedCopyId, $clearCopyId] = $this->pickTwoDistinctCopyIds();
        $reasonCode = 'MANUAL_STEWARD_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.book_copies')->where('id', $flaggedCopyId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        DB::connection('pgsql')->table('app.book_copies')->where('id', $clearCopyId)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        $response = $this->withSession($this->staffSession())->getJson(
            '/api/v1/internal/review/copies?reason_code=' . urlencode($reasonCode) . '&limit=20'
        );

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $ids = array_map(static fn (array $row): string => (string) ($row['copyIdentity']['id'] ?? ''), $data);

        $this->assertContains($flaggedCopyId, $ids);
        $this->assertNotContains($clearCopyId, $ids);

        foreach ($data as $row) {
            $this->assertTrue((bool) ($row['lifecycle']['needsReview'] ?? false));
        }
    }

    public function test_copy_review_summary_includes_needs_review_counts_and_top_reasons(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for review summary test.');

        [$copyId] = $this->pickTwoDistinctCopyIds();
        $reasonCode = 'SUMMARY_REASON_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.book_copies')->where('id', $copyId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        $expectedNeedsReview = (int) DB::connection('pgsql')
            ->table('app.book_copies')
            ->where('needs_review', true)
            ->count();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/copies-summary?top_limit=10');

        $response
            ->assertOk()
            ->assertJsonPath('data.entity', 'copies')
            ->assertJsonPath('data.needsReviewCount', $expectedNeedsReview);

        $topReasonCodes = $response->json('data.topReasonCodes');
        $reasonCodes = array_map(static fn (array $row): string => (string) ($row['reasonCode'] ?? ''), $topReasonCodes ?? []);
        $this->assertContains($reasonCode, $reasonCodes);
    }

    public function test_resolve_copy_review_clears_flags_and_writes_audit(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for resolve review test.');

        [$copyId] = $this->pickTwoDistinctCopyIds();
        $reasonCode = 'RESOLVE_REASON_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.book_copies')->where('id', $copyId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/copies/' . $copyId . '/resolve',
            ['resolution_note' => 'Issue verified and cleared by librarian.']
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.copyIdentity.id', $copyId)
            ->assertJsonPath('data.lifecycle.needsReview', false);

        $row = DB::connection('pgsql')->table('app.book_copies')
            ->select(['needs_review', 'review_reason_codes'])
            ->where('id', $copyId)
            ->first();

        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->needs_review);
        $this->assertTrue($this->isPgArrayEmpty((string) ($row->review_reason_codes ?? '{}')));

        $audit = CirculationAuditEvent::query()
            ->where('action', 'internal_copy_review_resolved')
            ->where('entity_type', 'copy')
            ->where('entity_id', $copyId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(true, $audit->previous_state['lifecycle']['needsReview'] ?? null);
        $this->assertSame(false, $audit->new_state['lifecycle']['needsReview'] ?? null);
    }

    public function test_resolve_requires_staff_session(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for review middleware test.');

        [$copyId] = $this->pickTwoDistinctCopyIds();

        $response = $this->postJson('/api/v1/internal/review/copies/' . $copyId . '/resolve');

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_resolve_rejects_non_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for review actor override authorization test.');

        [$copyId] = $this->pickTwoDistinctCopyIds();

        DB::connection('pgsql')->table('app.book_copies')->where('id', $copyId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{OVERRIDE_CHECK}',
        ]);

        $response = $this->withSession($this->staffSession('librarian'))->postJson(
            '/api/v1/internal/review/copies/' . $copyId . '/resolve',
            ['actor_user_id' => (string) Str::uuid()]
        );

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'insufficient_staff_role');
    }

    /**
     * @return array{string, string}
     */
    private function pickTwoDistinctCopyIds(): array
    {
        $ids = DB::connection('pgsql')->table('app.book_copies')->limit(2)->pluck('id')->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('At least two copies are required for internal review workflow tests.');
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
