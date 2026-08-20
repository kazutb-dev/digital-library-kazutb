<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\MessageAttachment;
use App\Models\Setting;
use App\Services\Messages\MessageAttachmentService;
use App\Services\Messages\MessageSubmissionService;
use App\Services\Messages\MessageWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageSubmissionController extends Controller
{
    public function store(Request $request, MessageSubmissionService $submission): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ContactMessage::TYPES)],
            'category_id' => ['required', 'integer', Rule::exists('message_categories', 'id')->where('active', true)],
            'subject' => ['required', 'string', 'min:5', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:20000'],
            'requested_priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'preferred_contact_channel' => ['required', Rule::in(['in_app', 'email'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'related_entity_type' => ['nullable', Rule::in(['book', 'copy', 'loan', 'reservation', 'fine', 'incident', 'electronic_material', 'news', 'branch'])],
            'related_entity_id' => ['nullable', 'required_with:related_entity_type', 'integer'],
            'complaint_against_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'submission_token' => ['required', 'uuid'],
            'contact_confirmed' => ['accepted'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:51200'],
        ]);
        if (($validated['complaint_against_user_id'] ?? null) && $validated['type'] !== 'complaint') {
            return back()->withErrors(['complaint_against_user_id' => __('messages.validation.complaint_staff_only')])->withInput();
        }

        $message = $submission->submit($request->user(), $validated, $request);

        return redirect()->route('member.messages.show', $message)->with('success', __('messages.messages.registered', ['ticket' => $message->ticket_number]));
    }

    public function reply(Request $request, ContactMessage $message, MessageWorkflowService $workflow, MessageAttachmentService $attachments): RedirectResponse
    {
        $this->owner($request, $message);
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:20000'], 'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', 'max:51200']]);
        $entry = $workflow->userReply($message, $request->user(), $validated['body']);
        $attachments->store($message, $entry, $request->file('attachments', []), $request->user());

        return back()->with('success', __('messages.messages.reply_sent'));
    }

    public function feedback(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->owner($request, $message);
        $validated = $request->validate(['score' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $workflow->feedback($message, $request->user(), (int) $validated['score'], $validated['comment'] ?? null);

        return back()->with('success', __('messages.messages.feedback_saved'));
    }

    public function reopen(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->owner($request, $message);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        abort_unless($message->status === 'resolved' && $message->resolved_at?->gte(now('UTC')->subDays(max(1, (int) Setting::valueFor('library_feedback_reopen_days', 14)))), 409);
        $workflow->readerReopen($message, $request->user(), $validated['reason']);

        return back()->with('success', __('messages.messages.reopened'));
    }

    public function attachment(Request $request, ContactMessage $message, MessageAttachment $attachment): StreamedResponse
    {
        $this->owner($request, $message);
        abort_unless((int) $attachment->contact_message_id === (int) $message->getKey() && $attachment->visibility === 'public', 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function owner(Request $request, ContactMessage $message): void
    {
        abort_unless((int) ($message->user_id ?: $message->sender_id) === (int) $request->user()->getKey(), 404);
    }
}
