<?php

namespace Tests\Feature;

use App\Integrations\IntegrationHubService;
use App\Integrations\Support\SafeEndpoint;
use App\Integrations\Support\SecretRedactor;
use App\Integrations\Support\WebhookSignatureVerifier;
use App\Integrations\Transport\SftpTransport;
use App\Models\Integration;
use App\Models\IntegrationOutboxMessage;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class IntegrationHubFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_registry_has_honest_provider_states_and_admin_dashboard_hides_secrets(): void
    {
        $this->assertDatabaseCount('integrations', 12);
        $this->assertDatabaseHas('integrations', ['code' => 'crm', 'enabled' => false, 'status' => 'credentials_required']);
        $this->assertDatabaseHas('integrations', ['code' => 'finance', 'enabled' => false, 'status' => 'credentials_required']);

        $response = $this->signInToLibraryAs($this->adminUser)->get('/admin/integrations');
        $response->assertOk()->assertSee('data-integration-hub', false)->assertSee('CRM')->assertSee('Active Directory')
            ->assertDontSee('AD_BIND_PASSWORD')->assertDontSee('bind_password')->assertDontSee('access_token');
    }

    public function test_inbox_and_outbox_are_idempotent_encrypted_and_retry_to_dlq(): void
    {
        $hub = app(IntegrationHubService::class);
        $crm = Integration::query()->where('code', 'crm')->firstOrFail();
        $first = $hub->receive($crm, 'event-1', 'user.updated', ['email' => 'reader@example.test'], '11111111-1111-1111-1111-111111111111');
        $second = $hub->receive($crm, 'event-1', 'user.updated', ['email' => 'reader@example.test'], '11111111-1111-1111-1111-111111111111');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('integration_inbox_messages', 1);
        $rawInbox = (string) DB::table('integration_inbox_messages')->value('payload_safe');
        $this->assertStringNotContainsString('reader@example.test', $rawInbox);

        $message = $hub->queue($crm, 'user', '42', 'user.sync', ['name' => 'Reader'], 'sync-user-42', 'crm');
        $duplicate = $hub->queue($crm, 'user', '42', 'user.sync', ['name' => 'Changed'], 'sync-user-42', 'crm');
        $this->assertSame($message->id, $duplicate->id);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            IntegrationOutboxMessage::query()->whereKey($message)->update([
                'status' => 'pending',
                'next_attempt_at' => now()->subSecond(),
            ]);
            $hub->deliver(IntegrationOutboxMessage::query()->findOrFail($message->id));
        }
        $this->assertDatabaseHas('integration_outbox_messages', ['id' => $message->id, 'status' => 'dead_letter', 'attempts' => 5, 'error_code' => 'delivery_failed']);
    }

    public function test_dry_run_mapping_allowlist_health_and_least_privilege(): void
    {
        $crm = Integration::query()->where('code', 'crm')->firstOrFail();
        $this->signInToLibraryAs($this->adminUser)->post(route('admin.integrations.dry-run', $crm))->assertRedirect();
        $this->assertDatabaseHas('integration_sync_runs', ['integration_id' => $crm->id, 'type' => 'dry_run', 'status' => 'configuration_required']);
        $this->post(route('admin.integrations.mappings.store', $crm), ['external_field' => 'personId', 'local_field' => 'university_id'])->assertRedirect();
        $this->assertDatabaseHas('integration_mappings', ['integration_id' => $crm->id, 'external_field' => 'personId', 'local_field' => 'university_id']);
        $this->post(route('admin.integrations.mappings.store', $crm), ['external_field' => 'password', 'local_field' => 'password'])->assertSessionHasErrors('local_field');
        $this->post(route('admin.integrations.toggle', $crm), ['enabled' => 1, 'confirmation' => 'CONFIRM'])->assertSessionHasErrors('enabled');
        $this->assertDatabaseHas('integrations', ['id' => $crm->id, 'enabled' => false, 'status' => 'credentials_required']);
        $this->post(route('admin.integrations.sync', $crm), ['confirmation' => 'CONFIRM'])->assertRedirect();
        $this->assertDatabaseHas('integration_sync_runs', ['integration_id' => $crm->id, 'type' => 'full', 'status' => 'configuration_required', 'error_code' => 'provider_not_configured']);

        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member)->get(route('admin.integrations.index'))->assertForbidden();
        $librarian = $this->makeControlPlaneUser('librarian');
        IntegrationOutboxMessage::query()->create(['integration_id' => $crm->id, 'aggregate_type' => 'user', 'aggregate_id' => 'sensitive-reference', 'event_type' => 'sync', 'payload_safe' => [], 'idempotency_key' => 'not-for-librarian', 'destination' => 'crm', 'status' => 'dead_letter', 'attempts' => 5, 'correlation_id' => '33333333-3333-3333-3333-333333333333']);
        $this->signInToLibraryAs($librarian)->get(route('admin.integrations.show', $crm))->assertOk()->assertDontSee('not-for-librarian')->assertDontSee('sensitive-reference');
        $this->post(route('admin.integrations.toggle', $crm), ['enabled' => 1, 'confirmation' => 'CONFIRM'])->assertForbidden();
    }

    public function test_secret_redaction_and_ssrf_validation_fail_closed(): void
    {
        $redacted = app(SecretRedactor::class)->redact(['Authorization' => 'Bearer abc', 'nested' => ['password' => 'secret'], 'safe' => 'ok']);
        $this->assertSame('[REDACTED]', $redacted['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['nested']['password']);
        $this->assertSame('ok', $redacted['safe']);
        $this->assertSame('https://api.example.test/v1', app(SafeEndpoint::class)->validate('https://api.example.test/v1'));
        foreach (['http://example.test', 'file:///etc/passwd', 'https://127.0.0.1/', 'https://user:pass@example.test/'] as $unsafe) {
            try {
                app(SafeEndpoint::class)->validate($unsafe);
                $this->fail('Unsafe endpoint accepted: '.$unsafe);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $timestamp = time();
        $body = '{"event":"reader.updated"}';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret');
        app(WebhookSignatureVerifier::class)->verify($body, $signature, $timestamp, 'test-secret');
        $this->addToAssertionCount(1);
        $this->expectException(\InvalidArgumentException::class);
        app(WebhookSignatureVerifier::class)->verify($body, $signature, $timestamp - 901, 'test-secret');
    }

    public function test_manual_retry_is_scoped_to_parent_integration(): void
    {
        $crm = Integration::query()->where('code', 'crm')->firstOrFail();
        $finance = Integration::query()->where('code', 'finance')->firstOrFail();
        $message = IntegrationOutboxMessage::query()->create(['integration_id' => $crm->id, 'aggregate_type' => 'user', 'aggregate_id' => '1', 'event_type' => 'sync', 'payload_safe' => [], 'idempotency_key' => 'scope-test', 'destination' => 'crm', 'status' => 'dead_letter', 'attempts' => 5, 'correlation_id' => '22222222-2222-2222-2222-222222222222']);
        $this->signInToLibraryAs($this->adminUser)->post(route('admin.integrations.outbox.retry', [$finance, $message]))->assertNotFound();
        $this->post(route('admin.integrations.outbox.retry', [$crm, $message]))->assertRedirect();
        $this->assertDatabaseHas('integration_outbox_messages', ['id' => $message->id, 'status' => 'pending', 'attempts' => 0]);
    }

    public function test_reconciliation_is_honest_and_sftp_configuration_is_fail_closed(): void
    {
        $crm = Integration::query()->where('code', 'crm')->firstOrFail();
        $this->signInToLibraryAs($this->adminUser)->post(route('admin.integrations.reconcile', $crm))->assertRedirect();
        $this->assertDatabaseHas('integration_sync_runs', ['integration_id' => $crm->id, 'type' => 'reconciliation', 'status' => 'configuration_required', 'conflicts' => 0]);
        $validated = app(SftpTransport::class)->validateConfiguration('sftp.example.test', 22, 'SHA256:abcdefghijklmnopqrstuvwxyz0123456789=');
        $this->assertSame('sftp.example.test', $validated['host']);
        $this->expectException(\InvalidArgumentException::class);
        app(SftpTransport::class)->validateConfiguration('127.0.0.1', 22, 'SHA256:abcdefghijklmnopqrstuvwxyz0123456789=');
    }
}
