<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class LocalizedText
{
    /** @param array<string, mixed> $parameters */
    public static function parameters(array $parameters, ?string $locale = null): array
    {
        $locale = (new LocaleResolver)->normalize($locale ?? app()->getLocale());

        return array_map(static function (mixed $value) use ($locale): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (isset($value['_translation']) && is_string($value['_translation'])) {
                return __($value['_translation'], is_array($value['parameters'] ?? null) ? $value['parameters'] : [], $locale);
            }
            if (isset($value['_date']) && is_string($value['_date'])) {
                return self::date($value['_date'], $locale, ($value['format'] ?? 'date') === 'datetime');
            }

            return $value;
        }, $parameters);
    }

    public static function date(string $value, ?string $locale = null, bool $withTime = false): string
    {
        $locale = (new LocaleResolver)->normalize($locale ?? app()->getLocale());
        $date = Carbon::parse($value)->locale($locale);
        $formatted = match ($locale) {
            'en' => $date->isoFormat($withTime ? 'MMMM D, YYYY, h:mm A' : 'MMMM D, YYYY'),
            'ru' => $date->isoFormat($withTime ? 'D MMMM YYYY [г.], HH:mm' : 'D MMMM YYYY [г.]'),
            default => $date->isoFormat($withTime ? 'YYYY [жылғы] D MMMM, HH:mm' : 'YYYY [жылғы] D MMMM'),
        };

        return $formatted;
    }
}
