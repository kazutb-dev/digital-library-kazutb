<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\MessageCategory;
use App\Services\Messages\MessageSlaService;
use App\Services\Messages\MessageSubmissionService;
use App\Services\Messages\MessageWorkflowService;
use Database\Seeders\MessageCategorySeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MessageAppealsWorkflowTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        (require base_path('database/migrations/2026_08_06_000000_build_message_appeals_workflow.php'))->up();
        app(MessageCategorySeeder::class)->run();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_authenticated_reader_submission_gets_ticket_route_sla_and_public_thread(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $this->makeControlPlaneUser('librarian');
        $message = $this->submit($reader);

        $this->assertMatchesRegularExpression('/^LIB-\d{4}-\d{6}$/', $message->ticket_number);
        $this->assertNotNull($message->public_id);
        $this->assertNotNull($message->due_at);
        $this->assertSame($reader->getKey(), $message->user_id);
        $this->assertSame('public', $message->threadEntries()->firstOrFail()->visibility);
        $this->assertNotNull($message->assigned_to);
    }

    public function test_submission_token_is_idempotent(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $token = (string) Str::uuid();
        $first = $this->submit($reader, token: $token);
        $second = $this->submit($reader, token: $token);
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, ContactMessage::query()->where('user_id', $reader->getKey())->count());
    }

    public function test_reader_only_sees_public_thread_entries(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $staff = $this->makeControlPlaneUser('librarian');
        $message = $this->submit($reader);
        $message->update(['assigned_to' => $staff->getKey()]);
        app(MessageWorkflowService::class)->addInternalNote($message, $staff, 'Internal evidence that must stay private.');

        $this->signInToLibraryAs($reader)->get(route('member.messages.show', $message))
            ->assertOk()->assertDontSee('Internal evidence that must stay private.');
    }

    public function test_other_reader_cannot_view_ticket(): void
    {
        $owner = $this->makeControlPlaneUser('member');
        $stranger = $this->makeControlPlaneUser('member');
        $message = $this->submit($owner);
        $this->signInToLibraryAs($stranger)->get(route('member.messages.show', $message))->assertNotFound();
    }

    public function test_complaint_response_requires_director_approval(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $librarian = $this->makeControlPlaneUser('librarian');
        $director = $this->makeControlPlaneUser('director');
        $message = $this->submit($reader, 'complaint');
        $message->update(['assigned_to' => $librarian->getKey()]);
        $workflow = app(MessageWorkflowService::class);
        $workflow->takeInReview($message, $librarian);
        $workflow->prepareOfficialResponse($message->refresh(), $librarian, 'We investigated the complaint and prepared this official response.');
        $this->assertSame('response_prepared', $message->refresh()->status);
        $this->assertFalse($message->threadEntries()->where('is_official_response', true)->where('visibility', 'public')->exists());
        $workflow->approveOfficialResponse($message->refresh(), $director);
        $this->assertSame('resolved', $message->refresh()->status);
        $this->assertTrue($message->threadEntries()->where('is_official_response', true)->where('visibility', 'public')->exists());
    }

    public function test_admin_cannot_approve_official_response(): void
    {
        $this->assertFalse($this->adminUser->can('messages.approve_response'));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $librarian = $this->makeControlPlaneUser('librarian');
        $message = $this->submit($reader);
        $message->update(['assigned_to' => $librarian->getKey(), 'status' => 'closed']);
        $this->expectException(ValidationException::class);
        app(MessageWorkflowService::class)->takeInReview($message, $librarian);
    }

    public function test_waiting_for_user_pauses_and_reply_resumes_sla(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $librarian = $this->makeControlPlaneUser('librarian');
        $message = $this->submit($reader);
        $message->update(['assigned_to' => $librarian->getKey()]);
        $workflow = app(MessageWorkflowService::class);
        $workflow->takeInReview($message, $librarian);
        $workflow->requestClarification($message->refresh(), $librarian, 'Please provide the catalogue number.');
        $this->assertSame('waiting_for_user', $message->refresh()->status);
        $this->assertNotNull($message->sla_paused_at);
        $workflow->userReply($message->refresh(), $reader, 'The catalogue number is 12345.');
        $this->assertSame('in_review', $message->refresh()->status);
        $this->assertNull($message->sla_paused_at);
    }

    public function test_sla_escalation_is_idempotent(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $message = $this->submit($reader);
        $message->update(['due_at' => now('UTC')->subMinute()]);
        $first = app(MessageSlaService::class)->sweep();
        $second = app(MessageSlaService::class)->sweep();
        $this->assertSame(1, $first['escalated']);
        $this->assertSame(0, $second['escalated']);
    }

    public function test_member_and_staff_surfaces_render_in_all_locales(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        foreach (['kk', 'ru', 'en'] as $locale) {
            $this->signInToLibraryAs($reader)->withSession(['locale' => $locale])->get('/dashboard/messages')->assertOk()->assertDontSee('messages.');
        }
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)->get('/librarian/messages')->assertOk()->assertDontSee('messages.summary.');
        $this->signInToLibraryAs($this->adminUser)->get('/admin/messages')->assertOk()->assertDontSee('messages.technical_');
    }

    private function submit($reader, string $type = 'request', ?string $token = null): ContactMessage
    {
        $category = MessageCategory::query()->where('message_type', $type)->firstOrFail();
        $request = Request::create('/dashboard/messages', 'POST');
        $request->setUserResolver(fn () => $reader);

        return app(MessageSubmissionService::class)->submit($reader, [
            'type' => $type, 'category_id' => $category->getKey(), 'subject' => 'Test library appeal',
            'body' => 'A sufficiently detailed message for workflow testing.', 'preferred_contact_channel' => 'in_app',
            'submission_token' => $token ?: (string) Str::uuid(),
        ], $request);
    }
}
