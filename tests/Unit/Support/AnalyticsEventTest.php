<?php

namespace Tests\Unit\Support;

use App\Support\AnalyticsEvent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnalyticsEventTest extends TestCase
{
    #[DataProvider('aliases')]
    public function test_legacy_domain_events_normalize_to_the_canonical_dictionary(string $legacy, string $canonical): void
    {
        $this->assertSame($canonical, AnalyticsEvent::assertAllowed($legacy));
    }

    public function test_unknown_events_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AnalyticsEvent::assertAllowed('student.reading_history.exported');
    }

    /** @return iterable<string, array{string, string}> */
    public static function aliases(): iterable
    {
        yield 'external open' => ['outbound_click', AnalyticsEvent::EXTERNAL_RESOURCE_OPEN];
        yield 'external card' => ['card_view', AnalyticsEvent::EXTERNAL_RESOURCE_VIEW];
        yield 'external denied' => ['access_denied', AnalyticsEvent::EXTERNAL_RESOURCE_ACCESS_DENIED];
        yield 'digital view' => ['read', AnalyticsEvent::DIGITAL_VIEW];
        yield 'digital download' => ['download', AnalyticsEvent::DIGITAL_DOWNLOAD];
    }
}
