<?php

namespace App\Support;

final class PublicCatalogLanguage
{
    /** @var array<string, list<string>> */
    private const STORAGE_ALIASES = [
        'ru' => ['ru', 'rus'],
        'kk' => ['kk', 'kaz', 'kz'],
        'en' => ['en', 'eng'],
    ];

    public static function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        foreach (self::STORAGE_ALIASES as $canonical => $aliases) {
            if (in_array($value, $aliases, true)) {
                return $canonical;
            }
        }

        return 'other';
    }

    /** @return list<string> */
    public static function storageAliases(string $value): array
    {
        return self::STORAGE_ALIASES[self::normalize($value)] ?? [];
    }

    /** @return list<string> */
    public static function allKnownStorageAliases(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::STORAGE_ALIASES))));
    }

    public static function label(?string $value): string
    {
        return (string) __('common.languages.'.self::normalize($value));
    }
}
