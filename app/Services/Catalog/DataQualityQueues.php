<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the Data Quality workbench (Master.md 11, ДИР 6).
 *
 * The queues used to be an ad-hoc match() in the controller. They are grouped
 * here by *the kind of work they demand*, because that is what decides the UI a
 * librarian is sent to:
 *
 *  - `completion` — data is simply absent; fixed in the ordinary record form.
 *  - `judgement`  — data is present and valid but ambiguous; needs a decision,
 *                   usually applied to many records at once.
 *  - `retyping`   — characters are wrong in ways no rule can resolve; needs a
 *                   human who reads Kazakh, one record at a time.
 */
class DataQualityQueues
{
    public const GROUP_COMPLETION = 'completion';

    public const GROUP_JUDGEMENT = 'judgement';

    public const GROUP_RETYPING = 'retyping';

    /**
     * Glyphs that legacy Kazakh-in-cp1251 data carries in place of real
     * letters, with the candidates a human should choose between.
     *
     * Never applied automatically: "Делµз" is Deleuze, where µ stands for ё,
     * not for the Kazakh ө it usually means.
     *
     * @var array<string, list<string>>
     */
    public const GLYPH_SUBSTITUTIONS = [
        'є' => ['ә', 'ғ', 'е'],
        'ѓ' => ['ғ'],
        'ќ' => ['қ'],
        '±' => ['ұ'],
        'µ' => ['ө', 'ё'],
        'ў' => ['ү'],
        'ғ' => ['ә', 'ғ'],
    ];

    /**
     * Glyphs whose presence always means corruption. `ғ` is excluded because it
     * is a perfectly normal Kazakh letter — it only *sometimes* stands in for
     * `ә`, so flagging every record containing it would bury the real cases.
     *
     * @var list<string>
     */
    public const CORRUPTION_GLYPHS = ['є', 'ѓ', 'ќ', '±', 'µ', 'ў'];

    /**
     * Queue definitions in display order, grouped by kind of work.
     *
     * @return array<string, array{group: string, icon: string, mode: string}>
     */
    public function definitions(): array
    {
        return [
            'drafts' => ['group' => self::GROUP_COMPLETION, 'icon' => 'edit_note', 'mode' => 'list'],
            'missing_udc' => ['group' => self::GROUP_COMPLETION, 'icon' => 'category', 'mode' => 'list'],
            'missing_author' => ['group' => self::GROUP_COMPLETION, 'icon' => 'person_off', 'mode' => 'list'],
            'missing_isbn' => ['group' => self::GROUP_COMPLETION, 'icon' => 'barcode', 'mode' => 'list'],
            'missing_year' => ['group' => self::GROUP_COMPLETION, 'icon' => 'event_busy', 'mode' => 'list'],
            'unplaced_copies' => ['group' => self::GROUP_COMPLETION, 'icon' => 'location_off', 'mode' => 'copies'],

            'language_mismatch' => ['group' => self::GROUP_JUDGEMENT, 'icon' => 'translate', 'mode' => 'tiers'],
            'duplicates' => ['group' => self::GROUP_JUDGEMENT, 'icon' => 'content_copy', 'mode' => 'duplicates'],
            'manual_review' => ['group' => self::GROUP_JUDGEMENT, 'icon' => 'flag', 'mode' => 'list'],

            'glyph_substitution' => ['group' => self::GROUP_RETYPING, 'icon' => 'spellcheck', 'mode' => 'retype'],
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * The record-level query behind a queue. `duplicates` and `unplaced_copies`
     * are not record queries and are handled separately by the controller.
     */
    public function query(string $queue): Builder
    {
        $query = BibliographicRecord::query();

        return match ($queue) {
            'drafts' => $query->where('is_draft', true),
            'missing_udc' => $query->whereNull('udc_code'),
            'missing_isbn' => $query->whereNull('isbn')->whereNotIn('resource_type', ['periodical', 'journal']),
            'missing_author' => $query->whereNull('primary_author')->whereNotIn('resource_type', ['periodical', 'journal']),
            'missing_year' => $query->whereNull('publication_year'),
            'manual_review' => $query->where('needs_manual_review', true)
                ->where(fn (Builder $inner) => $inner
                    ->whereNull('review_category')
                    ->orWhereNotIn('review_category', ['language_mismatch', 'glyph_substitution'])),
            'language_mismatch' => $query->where('language', '!=', 'kk')->titleHasKazakhLetters(),
            'glyph_substitution' => $query->hasCorruptedGlyphs(),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];
        foreach ($this->keys() as $queue) {
            $counts[$queue] = match ($queue) {
                'duplicates' => $this->duplicateGroups()->count(),
                'unplaced_copies' => BookCopy::query()
                    ->where(fn (Builder $b) => $b
                        ->whereNull('branch_id')
                        ->orWhereNull('shelf_location')
                        ->orWhereRaw("TRIM(COALESCE(shelf_location, '')) = ''"))
                    ->count(),
                default => $this->query($queue)->count(),
            };
        }

        return $counts;
    }

    /**
     * How many distinct records need attention at all.
     *
     * Summing the queue counters would badly overstate it — one record is
     * routinely a draft *and* missing a UDC *and* missing an author, so the
     * naive total ran to 71 000 against a 9 562-record catalogue.
     */
    public function openRecordTotal(?string $group = null): int
    {
        $definitions = $this->definitions();

        $recordQueues = array_filter(
            $this->keys(),
            static fn (string $queue): bool => ! in_array($queue, ['duplicates', 'unplaced_copies'], true)
                && ($group === null || $definitions[$queue]['group'] === $group),
        );

        if ($recordQueues === []) {
            return 0;
        }

        $query = BibliographicRecord::query()->where(function (Builder $builder) use ($recordQueues): void {
            foreach ($recordQueues as $queue) {
                $builder->orWhereIn('id', $this->query($queue)->select('id'));
            }
        });

        return $query->count();
    }

    /**
     * Titles that appear on more than one record — merge candidates (11.3).
     */
    public function duplicateGroups()
    {
        return BibliographicRecord::query()
            ->selectRaw('LOWER(title) as normalized_title, count(*) as total')
            ->groupByRaw('LOWER(title)')
            ->havingRaw('count(*) > 1')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit(50)
            ->get();
    }

    /**
     * Split a string into runs so a Blade template can mark the damaged glyphs
     * without doing string surgery itself.
     *
     * @return list<array{text: string, glyph: bool, options: list<string>}>
     */
    public function annotate(string $value): array
    {
        $segments = [];
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $isGlyph = in_array($char, self::CORRUPTION_GLYPHS, true);
            $last = array_key_last($segments);

            if ($last !== null && $segments[$last]['glyph'] === false && ! $isGlyph) {
                $segments[$last]['text'] .= $char;

                continue;
            }

            $segments[] = [
                'text' => $char,
                'glyph' => $isGlyph,
                'options' => $isGlyph ? (self::GLYPH_SUBSTITUTIONS[$char] ?? []) : [],
            ];
        }

        return $segments;
    }
}
