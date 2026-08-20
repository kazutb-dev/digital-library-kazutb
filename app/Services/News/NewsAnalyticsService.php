<?php

namespace App\Services\News;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NewsAnalyticsService
{
    public function recordView(News $news, Request $request): void
    {
        if (! Schema::hasTable('news_views')) {
            $news->increment('view_count');

            return;
        }

        $locale = in_array(app()->getLocale(), News::LANGUAGES, true) ? app()->getLocale() : 'ru';
        $visitorHash = hash_hmac('sha256', implode('|', [
            now('UTC')->toDateString(),
            (string) $request->ip(),
            mb_substr((string) $request->userAgent(), 0, 255),
        ]), (string) config('app.key'));

        DB::transaction(function () use ($news, $locale, $visitorHash, $request): void {
            $row = DB::table('news_views')->where([
                'news_id' => $news->getKey(),
                'viewed_on' => now('UTC')->toDateString(),
                'locale' => $locale,
                'visitor_hash' => $visitorHash,
            ])->lockForUpdate()->first();

            if ($row) {
                DB::table('news_views')->where('id', $row->id)->update(['views' => DB::raw('views + 1'), 'updated_at' => now('UTC')]);
            } else {
                DB::table('news_views')->insert([
                    'news_id' => $news->getKey(),
                    'viewed_on' => now('UTC')->toDateString(),
                    'locale' => $locale,
                    'visitor_hash' => $visitorHash,
                    'views' => 1,
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            }

            $increments = ['view_count' => 1];
            if ($this->cameFromHomepage($request)) {
                $increments['homepage_click_count'] = 1;
            }
            News::query()->whereKey($news)->incrementEach($increments);
        });
    }

    public function recordRegistrationClick(News $news): void
    {
        $news->increment('registration_click_count');
    }

    private function cameFromHomepage(Request $request): bool
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return false;
        }

        return in_array(parse_url($referer, PHP_URL_PATH), ['', '/'], true);
    }
}
