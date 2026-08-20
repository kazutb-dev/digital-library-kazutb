<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonical, deliberately small vocabulary for aggregate usage analytics.
 *
 * Analytics answers "how often was a feature used?".  It is not an audit log
 * and must not become a shadow reading-history database.  Domain writers may
 * retain their existing compact event names; normalize() gives reports one
 * stable vocabulary while legacy rows remain readable.
 */
final class AnalyticsEvent
{
    public const CATALOGUE_SEARCH = 'catalogue.search';

    public const BOOK_VIEW = 'book.view';

    public const LOAN_ISSUE = 'loan.issue';

    public const LOAN_RETURN = 'loan.return';

    public const RESERVATION_CREATE = 'reservation.create';

    public const DIGITAL_VIEW = 'digital.view';

    public const DIGITAL_DOWNLOAD = 'digital.download';

    public const REPOSITORY_VIEW = 'repository.view';

    public const REPOSITORY_FILE_VIEW = 'repository.file_view';

    public const EXTERNAL_RESOURCE_VIEW = 'external_resource.view';

    public const EXTERNAL_RESOURCE_OPEN = 'external_resource.open';

    public const EXTERNAL_RESOURCE_ACCESS_DENIED = 'external_resource.access_denied';

    public const NEWS_VIEW = 'news.view';

    public const EVENT_REGISTRATION = 'event.registration';

    public const LIBRARY_VISIT = 'library.visit';

    /** @var array<string, string> */
    private const LEGACY_ALIASES = [
        'card_view' => self::EXTERNAL_RESOURCE_VIEW,
        'outbound_click' => self::EXTERNAL_RESOURCE_OPEN,
        'expired_click' => self::EXTERNAL_RESOURCE_ACCESS_DENIED,
        'access_denied' => self::EXTERNAL_RESOURCE_ACCESS_DENIED,
        'unsafe_destination' => self::EXTERNAL_RESOURCE_ACCESS_DENIED,
        'view' => self::DIGITAL_VIEW,
        'preview' => self::DIGITAL_VIEW,
        'read' => self::DIGITAL_VIEW,
        'download' => self::DIGITAL_DOWNLOAD,
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::CATALOGUE_SEARCH,
            self::BOOK_VIEW,
            self::LOAN_ISSUE,
            self::LOAN_RETURN,
            self::RESERVATION_CREATE,
            self::DIGITAL_VIEW,
            self::DIGITAL_DOWNLOAD,
            self::REPOSITORY_VIEW,
            self::REPOSITORY_FILE_VIEW,
            self::EXTERNAL_RESOURCE_VIEW,
            self::EXTERNAL_RESOURCE_OPEN,
            self::EXTERNAL_RESOURCE_ACCESS_DENIED,
            self::NEWS_VIEW,
            self::EVENT_REGISTRATION,
            self::LIBRARY_VISIT,
        ];
    }

    public static function normalize(string $event): string
    {
        $event = mb_strtolower(trim($event));

        return self::LEGACY_ALIASES[$event] ?? $event;
    }

    public static function isAllowed(string $event): bool
    {
        return in_array(self::normalize($event), self::all(), true);
    }

    public static function assertAllowed(string $event): string
    {
        $normalized = self::normalize($event);

        if (! in_array($normalized, self::all(), true)) {
            throw new InvalidArgumentException("Unknown analytics event [{$event}].");
        }

        return $normalized;
    }
}
