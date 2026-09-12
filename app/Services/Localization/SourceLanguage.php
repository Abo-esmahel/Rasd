<?php

namespace App\Services\Localization;

/**
 * Source Language — لغة المصدر الأصلية.
 *
 * فصل صريح (Rule 1):
 * - UI Locale ............ لغة العرض الحالية (ar|en) — Presentation فقط.
 * - Source Language ...... لغة النص الأصلي المخزن (ar|en|neutral) — تُكتشف من المحتوى.
 * - Original Business Data  النص الأصلي في جداوله — Source of Truth، لا يتغير أبداً.
 * - Localized Presentation  إسقاط عرضي (Translation Projection) — مشتق، يُعرض حسب UI Locale.
 *
 * لا أعمدة لغة على الـBusiness Entities ولا نسخ عربية/إنجليزية —
 * اللغة تُحسم لكل حقل عند العرض من النص نفسه.
 */
final class SourceLanguage
{
    public const ARABIC = 'ar';

    public const ENGLISH = 'en';

    /** لا لغة بشرية (أرقام/رموز/تواريخ صرفة) — لا يُترجم أبداً. */
    public const NEUTRAL = 'neutral';

    /**
     * اكتشاف لغة النص من محتواه (حتمي، بلا AI، بلا IO).
     * عربي عند وجود أي حرف عربي، وإلا إنجليزي عند وجود حرف لاتيني، وإلا محايد.
     */
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
