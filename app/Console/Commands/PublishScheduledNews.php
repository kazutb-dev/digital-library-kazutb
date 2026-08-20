<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Services\News\NewsWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PublishScheduledNews extends Command
{
    protected $signature = 'library:news-sweep';

    protected $description = 'Publish news items whose scheduled publication time has arrived';

    public function handle(NewsWorkflowService $workflow): int
    {
        $published = 0;

        News::query()->dueForPublication()->orderBy('id')->chunkById(100, function ($items) use ($workflow, &$published): void {
            foreach ($items as $news) {
                $didPublish = $workflow->publishDue($news);

                if ($didPublish) {
                    $published++;
                }
            }
        });

        $this->info("Published scheduled news: {$published}");

        $archived = 0;
        if (Schema::hasColumn('news', 'expires_at')) {
            News::query()->published()->whereNotNull('expires_at')->where('expires_at', '<=', now('UTC'))->orderBy('id')->chunkById(100, function ($items) use ($workflow, &$archived): void {
                foreach ($items as $news) {
                    if ($workflow->archiveExpired($news)) {
                        $archived++;
                    }
                }
            });
        }
        if (Schema::hasColumn('news', 'homepage_until')) {
            News::query()->published()->where('show_on_homepage', true)->whereNotNull('homepage_until')->where('homepage_until', '<=', now('UTC'))->orderBy('id')->chunkById(100, function ($items) use ($workflow): void {
                foreach ($items as $news) {
                    $workflow->removeExpiredHomepagePlacement($news);
                }
            });
        }
        $this->info("Archived expired announcements: {$archived}");

        return self::SUCCESS;
    }
}
