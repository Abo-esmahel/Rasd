<?php

namespace App\Services\Localization;

final class SourceLanguage
{
    public const ARABIC = 'ar';

    public const ENGLISH = 'en';

    public const NEUTRAL = 'neutral';

    public static function detect(?string $text): string
    {
        $t = trim((string) $text);
        if ($t === '') {
            return self::NEUTRAL;
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $t)) {
            return self::ARABIC;
        }
        if (preg_match('/[A-Za-z]/', $t)) {
            return self::ENGLISH;
        }

        return self::NEUTRAL;
    }

    public static function isArabic(?string $text): bool
    {
        return self::detect($text) === self::ARABIC;
    }

    public static function normalizeLocale(?string $locale): string
    {
        return strtolower(trim((string) $locale)) === 'en' ? 'en' : 'ar';
    }
}
