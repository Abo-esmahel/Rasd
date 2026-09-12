<?php

namespace App\Services\Localization;

/**
 * TranslationProviderInterface — عقد مزود الترجمة الوحيد.
 *
 * TranslationService → TranslationProviderInterface → GeminiTranslationProvider
 *
 * لا يعرف Domain/Models/Controllers شيئاً عن Gemini — يعتمدون على
 * TranslationService فقط، واستبدال المزود لاحقاً = bind آخر فقط.
 */
interface TranslationProviderInterface
{
    /** اسم المزود للسجلات (مثال: gemini) — لا يُعرض للمستخدم أبداً. */
    public function name(): string;

    /**
     * ترجمة دفعة نصوص من لغة المصدر إلى اللغة الهدف — Structured Data فقط.
     *
     * @param  array<string,string>  $texts  key => source text (نص خام، ليس HTML)
     * @param  string  $sourceLang  'ar'|'en' (دائماً من المصدر مباشرة — ممنوع ar→en→ar)
     * @param  string  $targetLang  'ar'|'en'
     * @return array<string,string>  key => translated text (فقط الناجح؛ الفاشل يُحذف)
     *
     * @throws \Throwable عند فشل الدفعة (الخدمة العليا تلتقط وتسقط للمصدر)
     */
    public function translateBatch(array $texts, string $sourceLang, string $targetLang): array;
}
