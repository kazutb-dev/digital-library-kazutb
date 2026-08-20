<?php

namespace App\Services\DataQuality;

use App\Models\Catalog\BibliographicRecord;
use App\Models\DuplicateGroup;
use App\Models\DuplicateGroupMember;
use App\Models\Setting;
use App\Services\Library\IsbnService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DuplicateDetectionService
{
    public function __construct(private readonly IsbnService $isbn) {}

    /**
     * @return Collection<int,array{record:BibliographicRecord,score:float,level:string,details:array<string,mixed>}>
     */
    public function candidates(BibliographicRecord|array $subject, ?int $ignoreId = null): Collection
    {
        $attributes = $subject instanceof BibliographicRecord ? $subject->toArray() : $subject;
        $normalizedIsbn = $this->isbn->normalize((string) ($attributes['isbn'] ?? ''));
        $normalizedTitle = $this->normalizeText((string) ($attributes['title'] ?? ''));
        $author = $this->normalizeText((string) ($attributes['primary_author'] ?? ''));
        if ($normalizedIsbn === '' && $normalizedTitle === '') {
            return collect();
        }

        return BibliographicRecord::query()
            ->where('merge_status', 'active')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(function ($query) use ($normalizedIsbn, $normalizedTitle): void {
                if ($normalizedIsbn !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(UPPER(COALESCE(isbn, '')), '-', ''), ' ', '') = ?", [$normalizedIsbn]);
                }
                foreach (array_slice(explode(' ', $normalizedTitle), 0, 3) as $token) {
                    if (mb_strlen($token) >= 3) {
                        $query->orWhereRaw('LOWER(title) LIKE ?', ['%'.$token.'%']);
                    }
                }
            })
            ->limit(100)
            ->get()
            ->map(function (BibliographicRecord $candidate) use ($attributes, $normalizedIsbn, $normalizedTitle, $author): array {
                $details = $this->compare($attributes, $candidate, $normalizedIsbn, $normalizedTitle, $author);

                return [
                    'record' => $candidate,
                    'score' => $details['score'],
                    'level' => $details['score'] >= $this->exactThreshold()
                        ? 'exact'
                        : ($details['score'] >= $this->probableThreshold() ? 'probable' : 'possible'),
                    'details' => $details,
                ];
            })
            ->filter(fn (array $match): bool => $match['score'] >= $this->possibleThreshold())
            ->sortByDesc('score')
            ->values();
    }

    public function detectAndStore(BibliographicRecord $record): Collection
    {
        return $this->storeMatches($record, $this->candidates($record, $record->getKey()));
    }

    /**
     * Build the candidate map for a full scan in two bounded passes. This
     * avoids issuing one broad LIKE query per MARC record while preserving the
     * detailed fuzzy detector for an individual record after editing.
     *
     * @return array<int,Collection<int,array{record:BibliographicRecord,score:float,level:string,details:array<string,mixed>}>>
     */
    public function bulkCandidates(): array
    {
        $records = BibliographicRecord::query()->where('merge_status', 'active')
            ->get(['id', 'title', 'primary_author', 'publication_year', 'publisher', 'language', 'isbn', 'udc_code', 'resource_type'])
            ->keyBy('id');
        $buckets = [];
        foreach ($records as $record) {
            $isbn = $this->isbn->normalize((string) $record->isbn);
            $title = $this->normalizeText((string) $record->title);
            $author = $this->normalizeText((string) $record->primary_author);
            if ($isbn !== '') {
                $buckets['isbn:'.$isbn][] = $record->getKey();
            }
            if ($title !== '' && $author !== '') {
                $buckets['work:'.$title.'|'.$author][] = $record->getKey();
            }
        }

        $candidateIds = [];
        foreach ($buckets as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }
            foreach ($ids as $id) {
                $candidateIds[$id] = array_values(array_unique([...($candidateIds[$id] ?? []), ...array_diff($ids, [$id])]));
            }
        }

        $result = [];
        foreach ($candidateIds as $id => $ids) {
            $subject = $records->get($id);
            $isbn = $this->isbn->normalize((string) $subject->isbn);
            $title = $this->normalizeText((string) $subject->title);
            $author = $this->normalizeText((string) $subject->primary_author);
            $matches = collect($ids)->map(function (int $candidateId) use ($records, $subject, $isbn, $title, $author): array {
                $candidate = $records->get($candidateId);
                $details = $this->compare($subject->toArray(), $candidate, $isbn, $title, $author);

                return [
                    'record' => $candidate,
                    'score' => $details['score'],
                    'level' => $details['score'] >= $this->exactThreshold()
                        ? 'exact'
                        : ($details['score'] >= $this->probableThreshold() ? 'probable' : 'possible'),
                    'details' => $details,
                ];
            })->filter(fn (array $match): bool => $match['score'] >= $this->possibleThreshold())
                ->sortByDesc('score')->values();
            if ($matches->isNotEmpty()) {
                $result[(int) $id] = $matches;
            }
        }

        return $result;
    }

    /**
     * Persist an already calculated candidate set so a full scan does not run
     * the expensive comparison query twice.
     *
     * @param  Collection<int,array{record:BibliographicRecord,score:float,level:string,details:array<string,mixed>}>  $matches
     * @return Collection<int,DuplicateGroup>
     */
    public function storeMatches(BibliographicRecord $record, Collection $matches): Collection
    {
        return $matches->map(function (array $match) use ($record): DuplicateGroup {
            $ids = collect([$record->getKey(), $match['record']->getKey()])->sort()->values();
            $fingerprint = hash('sha256', $ids->implode('|'));
            $group = DuplicateGroup::query()->firstOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'group_number' => 'DUP-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                    'score' => $match['score'],
                    'match_level' => $match['level'],
                    'status' => 'open',
                ],
            );
            foreach ([$record, $match['record']] as $member) {
                DuplicateGroupMember::query()->updateOrCreate(
                    ['duplicate_group_id' => $group->getKey(), 'bibliographic_record_id' => $member->getKey()],
                    ['match_details' => $match['details']],
                );
            }

            return $group;
        });
    }

    /** @return array<string,mixed> */
    private function compare(array $subject, BibliographicRecord $candidate, string $isbn, string $title, string $author): array
    {
        $candidateIsbn = $this->isbn->normalize((string) $candidate->isbn);
        $isbnExact = $isbn !== '' && $isbn === $candidateIsbn;
        $titleSimilarity = $this->similarity($title, $this->normalizeText($candidate->title));
        $authorSimilarity = $this->similarity($author, $this->normalizeText((string) $candidate->primary_author));
        $sameYear = ($subject['publication_year'] ?? null) !== null && (int) $subject['publication_year'] === (int) $candidate->publication_year;
        $closeYear = ($subject['publication_year'] ?? null) !== null && abs((int) $subject['publication_year'] - (int) $candidate->publication_year) <= 2;
        $samePublisher = $this->normalizeText((string) ($subject['publisher'] ?? '')) !== ''
            && $this->normalizeText((string) ($subject['publisher'] ?? '')) === $this->normalizeText((string) $candidate->publisher);
        $sameLanguage = ($subject['language'] ?? null) === $candidate->language;
        $sameUdc = ($subject['udc_code'] ?? null) !== null && ($subject['udc_code'] ?? null) === $candidate->udc_code;
        $distinctEditionSignal = $this->distinctEditionSignal((string) ($subject['title'] ?? ''), $candidate->title)
            || (($subject['language'] ?? null) && ! $sameLanguage);

        $score = ($isbnExact ? 55 : 0)
            + ($titleSimilarity * 25)
            + ($authorSimilarity * 10)
            + ($sameYear ? 5 : ($closeYear ? 2 : 0))
            + ($samePublisher ? 2 : 0)
            + ($sameLanguage ? 2 : 0)
            + ($sameUdc ? 1 : 0);
        if ($distinctEditionSignal) {
            $score = min($score, $this->exactThreshold() - 1);
        }

        return [
            'score' => round(min(100, $score), 2),
            'isbn_exact' => $isbnExact,
            'title_similarity' => round($titleSimilarity * 100, 2),
            'author_similarity' => round($authorSimilarity * 100, 2),
            'same_year' => $sameYear,
            'close_year' => $closeYear,
            'same_publisher' => $samePublisher,
            'same_language' => $sameLanguage,
            'same_udc' => $sameUdc,
            'distinct_edition_signal' => $distinctEditionSignal,
        ];
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value));

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function similarity(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0;
        }
        if ($left === $right) {
            return 1;
        }
        similar_text($left, $right, $percent);

        return $percent / 100;
    }

    private function distinctEditionSignal(string $left, string $right): bool
    {
        $patterns = '/\b(?:том|т\.|volume|vol\.|часть|part|выпуск|edition|издание)\s*[0-9ivx]+\b/iu';
        preg_match_all($patterns, $left, $leftMatches);
        preg_match_all($patterns, $right, $rightMatches);

        return ($leftMatches[0] ?? []) !== ($rightMatches[0] ?? []);
    }

    private function exactThreshold(): float
    {
        return (float) Setting::valueFor('data_quality_duplicate_exact_threshold', 90);
    }

    private function probableThreshold(): float
    {
        return (float) Setting::valueFor('data_quality_duplicate_probable_threshold', 65);
    }

    private function possibleThreshold(): float
    {
        return (float) Setting::valueFor('data_quality_duplicate_possible_threshold', 50);
    }
}
