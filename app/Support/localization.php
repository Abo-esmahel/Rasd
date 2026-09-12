<?php

/**
 * Localization presentation helpers — سكر Blade فوق LocalizedPresenter.
 * لا منطق لغوي هنا إطلاقاً — كل القرارات داخل الـResolver المركزي.
 */

use App\Services\Localization\LocalizedPresenter;

if (!function_exists('l10n_text')) {
    /**
     * Localized presentation text لحقل كيان — STRICT: محفوظ فقط + placeholder
     * بلغة الواجهة عند الغياب (ZERO Gemini، ZERO Arabic في English).
     */
    function l10n_text(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        try {
            return app(LocalizedPresenter::class)->text($type, $id, $field, $source, $locale);
        } catch (\Throwable) {
            $locale = $locale ?? app()->getLocale();
            if ($locale === 'en' && preg_match('/[\x{0600}-\x{06FF}]/u', (string) $source)) {
                return 'Translation is being prepared…';
            }

            return (string) $source;
        }
    }
}

if (!function_exists('translation_state')) {
    /**
     * حالة ترجمة حقل — قراءة فقط (بلا Gemini): ready|pending|source.
     */
    function translation_state(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        try {
            return app(LocalizedPresenter::class)->translationState($type, $id, $field, $source, $locale);
        } catch (\Throwable) {
            return 'source';
        }
    }
}

if (!function_exists('status_label')) {
    function status_label(string $status, ?string $locale = null): string
    {
        try {
            return app(LocalizedPresenter::class)->statusLabel($status, $locale);
        } catch (\Throwable) {
            return $status;
        }
    }
}

if (!function_exists('back_arrow')) {
    function back_arrow(?string $locale = null): string
    {
        try {
            return app(LocalizedPresenter::class)->backArrow($locale);
        } catch (\Throwable) {
            return app()->getLocale() === 'ar' ? '→' : '←';
        }
    }
}

if (!function_exists('ui_dir')) {
    function ui_dir(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'ar' ? 'rtl' : 'ltr';
    }
}
