<?php

namespace App\Services\Localization;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BibliographicRecordTranslation;
use Illuminate\Support\Collection;

final class LocalizedContentResolver
{
    /**
     * @return array{locale:string,title:string,annotation:string,keywords:list<string>,original_title:string,original_annotation:string,original_keywords:list<string>,is_fallback:bool,has_translation:bool}
     */
    public function bibliographic(BibliographicRecord $record, ?string $locale = null): array
    {
        $requested = $this->locale($locale);
        $translations = $record->relationLoaded('translations')
            ? $record->translations
            : $record->translations()->get();
        $usable = $translations
            ->whereIn('translation_status', BibliographicRecordTranslation::PUBLIC_STATUSES)
            ->keyBy('locale');

        $selected = $usable->get($requested);
        $selectedLocale = $requested;

        if ($selected === null && ! $this->hasOriginal($record)) {
            [$selectedLocale, $selected] = $this->firstAvailable($usable, $requested);
        }

        return [
            'locale' => $selected?->locale ?? (string) $record->language,
            'title' => trim((string) ($selected?->title ?? $record->title)),
            'annotation' => trim((string) ($selected?->annotation ?? $record->annotation ?? '')),
            'keywords' => $this->keywords($selected?->keywords ?? $record->keywords),
            'original_title' => trim((string) $record->title),
            'original_annotation' => trim((string) ($record->annotation ?? '')),
            'original_keywords' => $this->keywords($record->keywords),
            'is_fallback' => $selected === null || $selectedLocale !== $requested,
            'has_translation' => $selected !== null,
        ];
    }

    private function locale(?string $locale): string
    {
        $candidate = mb_strtolower(trim((string) ($locale ?: app()->getLocale())));

        return in_array($candidate, BibliographicRecordTranslation::LOCALES, true) ? $candidate : 'ru';
    }

    private function hasOriginal(BibliographicRecord $record): bool
    {
        return trim((string) $record->title) !== '';
    }

    /** @return array{0:string,1:BibliographicRecordTranslation|null} */
    private function firstAvailable(Collection $translations, string $requested): array
    {
        foreach (array_values(array_unique([$requested, 'ru', 'kk', 'en'])) as $locale) {
            if (($translation = $translations->get($locale)) instanceof BibliographicRecordTranslation) {
                return [$locale, $translation];
            }
        }

        return [$requested, null];
    }

    /** @return list<string> */
    private function keywords(mixed $keywords): array
    {
        return collect(is_array($keywords) ? $keywords : [])
            ->map(static fn (mixed $keyword): string => trim(strip_tags((string) $keyword)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
