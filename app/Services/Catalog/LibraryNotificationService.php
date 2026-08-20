<?php

namespace App\Services\Catalog;

use App\Models\Catalog\ReaderNotification;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Support\LocaleResolver;
use App\Support\LocalizedText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivery layer for reader notifications (Master.md 15.6). Consults the
 * admin-managed notification_settings matrix per event type. In-app is the
 * primary channel; email goes through Laravel Mail only when a real mailer
 * is configured — with the log driver the email branch is skipped entirely
 * so nothing pretends to have been sent.
 */
class LibraryNotificationService
{
    private const CRITICAL_EVENTS = ['loan_overdue', 'reservation_expired', 'reservation_expiry_reminder', 'incident_opened', 'reader_blocked'];

    /**
     * Persist a locale-neutral notification and render delivery text in the
     * recipient's language. The i18n metadata remains in payload so the
     * in-app feed can be re-rendered after a later language change.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $payload
     */
    public function sendLocalized(
        User $user,
        string $eventType,
        string $titleKey,
        ?string $bodyKey = null,
        array $parameters = [],
        array $payload = [],
    ): ?ReaderNotification {
        $locale = (new LocaleResolver)->normalize($user->locale);
        $payload['_i18n'] = [
            'title_key' => $titleKey,
            'body_key' => $bodyKey,
            'parameters' => $parameters,
        ];

        $workerLocale = app()->getLocale();
        app()->setLocale($locale);
        try {
            $renderedParameters = LocalizedText::parameters($parameters);

            return $this->send(
                $user,
                $eventType,
                __($titleKey, $renderedParameters),
                $bodyKey === null ? null : __($bodyKey, $renderedParameters),
                $payload,
            );
        } finally {
            app()->setLocale($workerLocale);
        }
    }

    public function send(User $user, string $eventType, string $title, ?string $body = null, array $payload = []): ?ReaderNotification
    {
        $notification = null;
        $idempotencyKey = 'notify:'.$user->getKey().':'.$eventType.':'.sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        if (NotificationSetting::channelEnabled($eventType, 'in_app') && $this->readerAllows($user, $eventType, 'in_app')) {
            $notification = ReaderNotification::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey.':in_app'],
                [
                    'user_id' => $user->getKey(), 'event_type' => $eventType, 'title' => $title,
                    'body' => $body, 'payload' => $payload ?: null, 'channel' => 'in_app',
                    'delivery_status' => 'sent', 'attempts' => 1, 'sent_at' => now(),
                ],
            );
        }

        if (NotificationSetting::channelEnabled($eventType, 'email') && $this->readerAllows($user, $eventType, 'email') && $this->mailerConfigured()) {
            $emailLog = ReaderNotification::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey.':email'],
                [
                    'user_id' => $user->getKey(), 'event_type' => $eventType, 'title' => $title,
                    'body' => $body, 'payload' => $payload ?: null, 'channel' => 'email',
                    'delivery_status' => 'pending', 'attempts' => 0,
                ],
            );
            if (! $emailLog->wasRecentlyCreated) {
                return $notification;
            }
            try {
                Mail::raw(trim($title."\n\n".($body ?? '')), function ($message) use ($user, $title): void {
                    $message->to($user->email, $user->name)->subject($title);
                });
                $emailLog->update(['delivery_status' => 'sent', 'attempts' => 1, 'sent_at' => now()]);
            } catch (\Throwable $exception) {
                $emailLog->update(['delivery_status' => 'failed', 'attempts' => 1, 'last_error' => $exception->getMessage()]);
                Log::warning('Reader notification email failed', [
                    'user_id' => $user->getKey(),
                    'event_type' => $eventType,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    public function unreadCountFor(User $user): int
    {
        return ReaderNotification::query()
            ->where('user_id', $user->getKey())
            ->unread()
            ->count();
    }

    /**
     * Real delivery requires a transport that actually leaves the machine.
     */
    private function mailerConfigured(): bool
    {
        return ! in_array((string) config('mail.default'), ['log', 'array', ''], true);
    }

    private function readerAllows(User $user, string $eventType, string $channel): bool
    {
        if (in_array($eventType, self::CRITICAL_EVENTS, true)) {
            return true;
        }
        $preferences = $user->readerProfile?->notification_preferences ?? [];
        if ($channel === 'email' && array_key_exists('email', $preferences) && ! $preferences['email']) {
            return false;
        }
        $family = match (true) {
            str_starts_with($eventType, 'reservation_') => 'reservations',
            str_starts_with($eventType, 'message_') => 'messages',
            str_starts_with($eventType, 'digital_'), str_starts_with($eventType, 'repository_') => 'digital',
            str_starts_with($eventType, 'news_') => 'news',
            default => null,
        };

        return $family === null || ! array_key_exists($family, $preferences) || (bool) $preferences[$family];
    }
}
