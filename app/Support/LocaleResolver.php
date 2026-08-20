<?php

namespace App\Support;

use Illuminate\Http\Request;

final class LocaleResolver
{
    public const DEFAULT = 'kk';

    public const COOKIE = 'library_locale';

    /** @var list<string> */
    public const SUPPORTED = ['kk', 'ru', 'en'];

    public function resolve(Request $request): string
    {
        $queryLocale = $request->query('lang');
        if ($this->isSupported($queryLocale)) {
            return $this->normalize($queryLocale);
        }

        $userLocale = $request->user()?->locale;
        if ($this->isSupported($userLocale)) {
            return $this->normalize($userLocale);
        }

        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        if ($this->isSupported($sessionLocale)) {
            return $this->normalize($sessionLocale);
        }

        $cookieLocale = $request->cookie(self::COOKIE);
        if ($this->isSupported($cookieLocale)) {
            return $this->normalize($cookieLocale);
        }

        return self::DEFAULT;
    }

    public function normalize(mixed $locale): string
    {
        $locale = mb_strtolower(trim((string) $locale));

        return match ($locale) {
            'kz', 'kaz', 'kk-kz' => 'kk',
            'ru-ru', 'rus' => 'ru',
            'en-us', 'eng' => 'en',
            default => in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT,
        };
    }

    public function isSupported(mixed $locale): bool
    {
        if (! is_string($locale) || trim($locale) === '') {
            return false;
        }

        return in_array(mb_strtolower(trim($locale)), [
            ...self::SUPPORTED,
            'kz', 'kaz', 'kk-kz', 'ru-ru', 'rus', 'en-us', 'eng',
        ], true);
    }
}
