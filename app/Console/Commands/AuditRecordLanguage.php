<?php

namespace App\Console\Commands;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Language-tag quality audit (ДИР §6.3 "подсветка аномалий").
 *
 * Two different defects hide Kazakh books behind the KK language filter and
 * they need different remedies:
 *
 *  - Cyrillic "Каз" in MARC 041$a was not recognised by the importer, so those
 *    records landed as `other`. The source says Kazakh, so re-deriving the tag
 *    is authoritative and safe — that is what --fix-from-source does.
 *  - ~2200 records carry a Kazakh title but 041$a genuinely reads "rus". The
 *    legacy cataloguing is wrong at the source; no re-import can fix it, so
 *    these are only ever *flagged* for a librarian, never rewritten silently.
 */
class AuditRecordLanguage extends Command
{
    protected $signature = 'catalog:audit-language
        {--retag-other : Retag "other" records whose title is Kazakh script (the Cyrillic-"Каз" import defect)}
        {--flag : Mark script/tag mismatches with needs_manual_review so they surface in Data Cleanup}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Report and repair bibliographic records whose language tag disagrees with the title script';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $mismatched = $this->mismatchedQuery()->count();
        $byLanguage = $this->mismatchedQuery()
            ->select('language', DB::raw('count(*) as n'))
            ->groupBy('language')
            ->pluck('n', 'language');

        $this->info("Records with Kazakh-script titles tagged as non-Kazakh: {$mismatched}");
        foreach ($byLanguage as $language => $count) {
            $this->line("  {$language}: {$count}");
        }

        if ($this->option('retag-other')) {
            $this->retagOther($dryRun);
        }

        if ($this->option('flag')) {
            $flagged = $dryRun
                ? $mismatched
                : $this->mismatchedQuery()->update(['needs_manual_review' => true]);
            $this->info(($dryRun ? '[dry-run] would flag ' : 'Flagged ').$flagged.' record(s) for manual review.');
        }

        return self::SUCCESS;
    }

    /**
     * Records whose title uses Kazakh-only letters while the tag says otherwise.
     */
    private function mismatchedQuery()
    {
        return BibliographicRecord::query()
            ->where('language', '!=', 'kk')
            ->titleHasKazakhLetters();
    }

    /**
     * The importer used to drop Cyrillic "Каз" into `other` (verified against
     * marc_current_2026: 041$a = "Каз" on 49 documents). Selecting on
     * language='other' plus a Kazakh-script title reproduces that set without
     * needing a live SQL Server connection, and the pairing is unambiguous —
     * a Kazakh-script title tagged "other" has no other plausible language.
     */
    private function retagOther(bool $dryRun): void
    {
        $candidates = BibliographicRecord::query()
            ->where('language', 'other')
            ->titleHasKazakhLetters()
            ->get(['id', 'title']);

        $this->info(($dryRun ? '[dry-run] would retag ' : 'Retagged ')
            .$candidates->count().' record(s) from "other" to "kk".');

        foreach ($candidates->take(10) as $record) {
            $this->line('  #'.$record->getKey().' '.mb_substr((string) $record->title, 0, 60));
        }

        if (! $dryRun && $candidates->isNotEmpty()) {
            BibliographicRecord::query()
                ->whereIn('id', $candidates->pluck('id'))
                ->update(['language' => 'kk']);
        }
    }
}
