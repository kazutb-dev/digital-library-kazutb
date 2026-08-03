<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\StoredUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ContactMessageSubmissionController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in($this->categories())],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            // Images remain disabled until the runtime has a trusted metadata
            // sanitizer; this prevents EXIF location/device data from entering storage.
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,txt'],
        ]);

        $sessionUser = $request->session()->get('library.user', []);
        $authUser = $request->user();
        $senderEmail = (string) ($authUser?->email ?? $sessionUser['email'] ?? '');

        if ($authUser === null && $senderEmail !== '') {
            $authUser = User::query()->where('email', $senderEmail)->first();
        }

        $attachments = [];
        try {
            foreach ($request->file('attachments', []) as $file) {
                $attachments[] = [
                    'path' => StoredUpload::put($file, 'contact-attachments', 'local'),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
            }

            DB::transaction(function () use (
                $validated,
                $authUser,
                $senderEmail,
                $attachments,
                $audit,
                $sessionUser,
            ): void {
                $message = ContactMessage::query()->create([
                    'category' => $validated['category'],
                    'subject' => $validated['subject'],
                    'body' => $validated['body'],
                    'sender_id' => $authUser?->getKey(),
                    'sender_email' => $senderEmail,
                    'status' => 'open',
                    'priority' => 'normal',
                    'attachments' => $attachments ?: null,
                ]);

                $audit->logRequired(
                    actionType: 'message.created',
                    entityType: 'contact_message',
                    entityId: $message->getKey(),
                    newValues: [
                        'category' => $message->category,
                        'status' => $message->status,
                        'attachment_count' => count($attachments),
                    ],
                    scope: 'operational',
                    actor: $authUser ?? (is_array($sessionUser) ? $sessionUser : null),
                );
            });
        } catch (Throwable $exception) {
            foreach ($attachments as $attachment) {
                StoredUpload::deleteOrReport($attachment['path'], 'local');
            }

            throw $exception;
        }

        return back()->with('success', __('messages.submitted'));
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        $configured = Setting::valueFor(
            'message_categories',
            ['request', 'complaint', 'suggestion', 'question', 'other'],
        );

        return collect(is_array($configured) ? $configured : [])
            ->map(fn (mixed $value): string => mb_strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '' && mb_strlen($value) <= 32)
            ->unique()
            ->values()
            ->all() ?: ['request', 'complaint', 'suggestion', 'question', 'other'];
    }
}
