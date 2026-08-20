<?php

namespace App\Services\Repository;

use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryUsageDaily;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stores the minimum dimensions needed for operational usage reports.
 * Deliberately does not record IP addresses, user agents, referrers or search
 * queries, so this is not a behavioural clickstream.
 */
final class RepositoryUsageRecorder
{
    public function record(RepositoryItem $item, string $eventType, ?User $user, string $locale): void
    {
        if (! in_array($eventType, ['metadata_view', 'pdf_view', 'download'], true)) {
            throw new \InvalidArgumentException("Unknown repository usage event: {$eventType}");
        }

        $role = $user?->getRoleNames()->first();

        $dimensions = [
            'repository_item_id' => $item->getKey(),
            'event_type' => $eventType,
            'role_name' => $user === null ? 'guest' : ($role ?: 'authenticated'),
            'locale' => in_array($locale, ['kk', 'ru', 'en'], true) ? $locale : 'ru',
            'occurred_on' => today('UTC')->toDateString(),
        ];

        // One anonymous counter row per day/dimension: no user IDs, exact
        // timestamps, IPs, agents, referrers or queries are retained.
        RepositoryUsageDaily::query()->upsert(
            [[...$dimensions, 'event_count' => 1]],
            ['repository_item_id', 'occurred_on', 'event_type', 'role_name', 'locale'],
            ['event_count' => DB::raw('repository_usage_daily.event_count + 1')],
        );
    }
}
