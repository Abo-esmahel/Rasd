<?php

namespace App\Services\Localization;

class DynamicTranslationService extends TranslationService
{
    public const TARGET_LOCALE = 'en';

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
                'ai_used' => false,
            ],
        ];
    }

    public static function statusLabel(string $status, ?string $locale = null): string
    {
        return parent::statusLabel($status, $locale ?? 'en');
    }
}
