<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduledNews extends Command
{
    protected $signature = 'news:publish-scheduled';

    protected $description = 'Publish news items whose scheduled publication time has arrived';

    public function handle(AuditLogger $audit): int
    {
        $published = 0;

        News::query()->dueForPublication()->orderBy('id')->chunkById(100, function ($items) use ($audit, &$published): void {
            foreach ($items as $news) {
                $didPublish = DB::transaction(function () use ($news, $audit): bool {
                    $lockedNews = News::query()
                        ->dueForPublication()
                        ->whereKey($news->getKey())
                        ->lockForUpdate()
                        ->first();

                    if ($lockedNews === null) {
                        return false;
                    }

                    $old = [
                        'status' => $lockedNews->status,
                        'publish_at' => $lockedNews->publish_at?->utc()->toIso8601String(),
                    ];
                    $lockedNews->update(['status' => 'published']);

                    $audit->logRequired(
                        actionType: 'publish',
                        entityType: 'news',
                        entityId: $lockedNews->getKey(),
                        oldValues: $old,
                        newValues: [
                            'status' => 'published',
                            'publish_at' => $lockedNews->publish_at?->utc()->toIso8601String(),
                        ],
                        scope: 'operational',
                        actor: ['name' => 'Scheduler', 'role' => 'system'],
                    );

                    return true;
                });

                if ($didPublish) {
                    $published++;
                }
            }
        });

        $this->info("Published scheduled news: {$published}");

        return self::SUCCESS;
    }
}
