<?php

namespace App\Services\Localization;

/**
 * غلاف توافق خلفي (BC) — يُبقي توقيع translatePage القديم يعمل.
 *
 * الجديد: TranslationService + LocalizedPresenter + TranslationProviderInterface.
 * هذا الغلاف يفوّض للخدمة المركزية، بلا منطق مكرر.
 * meta صادقة ومشتقة: localized (اختلف عن المصدر) مقابل source (كما هو).
 *
 * @deprecated استخدم TranslationService / LocalizedPresenter مباشرة.
 */
class DynamicTranslationService extends TranslationService
{
    public const TARGET_LOCALE = 'en';

    /**
     * READ صِرف — محفوظ فقط (STRICT: ZERO Gemini في القراءة/Language Switch).
     *
     *   Locale → Cache/DB → Stored Translation / Source → Render
     *
     * لا يستدعي localizeMany() ولا translateBatch() إطلاقاً — أي ترجمة
     * جديدة تحدث فقط في مسار CREATE/UPDATE (Observer + WarmTranslationProjection).
     *
     * @param  array<int, array{type: string, id: int|string, fields: array<string,string>}>  $items
     * @return array{translations: array<string,string>, meta: array{localized: int, source: int, ai_used: bool}}
     */
    public function translatePage(array $items, string $locale = self::TARGET_LOCALE): array
    {
        $locale = SourceLanguage::normalizeLocale($locale);

        $refs = [];
        $sources = [];
        foreach (array_values($items) as $it) {
            if (!is_array($it)) {
                continue;
            }
            $type = strtolower(trim((string) ($it['type'] ?? '')));
            $id = TranslationService::validId($it['id'] ?? null);
            $fields = $it['fields'] ?? null;
            if (!is_array($fields) || $id === null) {
                continue;
            }
            foreach ($fields as $field => $text) {
                $field = (string) $field;
                if ($field === '') {
                    continue;
                }
                $refs[] = ['type' => $type, 'id' => $id, 'field' => $field, 'text' => $text];
                $sources[TranslationService::itemKey($type, $id, $field)] = trim((string) $text);
            }
        }

        // stored-only: النسخ المحفوظة مسبقاً فقط — المفقود يُعرض بمصدره
        // (الـ placeholder بلغة الواجهة يُحل سيرفرياً عند الـ Presenter).
        // لا Provider هنا — Gemini محصور في WarmTranslationProjection (خلفية).
        $map = $this->resolveStoredMany($refs, $locale);

        $localized = 0;
        $translations = [];
        foreach ($sources as $key => $source) {
            $out = isset($map[$key]) ? (string) $map[$key] : $source;
            $translations[$key] = $out !== '' ? $out : $source;
            if ($translations[$key] !== $source && $source !== '') {
                $localized++;
            }
        }

        return [
            'translations' => $translations,
            'meta' => [
                'localized' => $localized,
                'source' => count($translations) - $localized,
                // قراءة محفوظة فقط — لا AI متزامن هنا أبداً (التدفئة خلفية فقط).
                'ai_used' => false,
            ],
        ];
    }

    public static function statusLabel(string $status, ?string $locale = null): string
    {
        return parent::statusLabel($status, $locale ?? 'en');
    }
}
