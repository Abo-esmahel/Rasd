<?php

namespace App\Services\Localization;

use App\Services\Ai\AiTextGeneratorInterface;
use Illuminate\Support\Facades\Log;

class GeminiTranslationProvider implements TranslationProviderInterface
{
    public function __construct(private AiTextGeneratorInterface $generator)
    {
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function translateBatch(array $texts, string $sourceLang, string $targetLang): array
    {
        if ($texts === []) {
            return [];
        }
        if (!config('ai.enabled', false)) {
            throw new \RuntimeException('AI translation is disabled.');
        }
        if (trim((string) config('ai.gemini.api_key', '')) === '') {
            throw new \RuntimeException('AI translation is not configured.');
        }
        if (!in_array($sourceLang, ['ar', 'en'], true) || !in_array($targetLang, ['ar', 'en'], true)) {
            throw new \InvalidArgumentException('Unsupported translation direction.');
        }
        if ($sourceLang === $targetLang) {
            throw new \InvalidArgumentException('Source and target languages are identical.');
        }

        $payloadItems = [];
        foreach ($texts as $key => $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }
            $payloadItems[] = ['key' => (string) $key, 'text' => mb_substr($text, 0, 5000)];
        }
        if ($payloadItems === []) {
            return [];
        }

        $system = $sourceLang === 'ar'
            ? 'You are a professional Arabic→English translator for an official Syrian government field-surveillance monitoring system. '
            . 'Context: field monitoring notes (floor number, camera number, observation time window) reviewed by report writers and compiled into official daily reports. '
            . 'Translate ONLY the human-readable Arabic content into natural, clear, formal English (fluent report style — never a dumb literal word-for-word translation). Preserve meaning, context, and surveillance/report terminology. '
            . 'Output JSON ONLY, no markdown, no explanations. '
            . 'RULES: (1) Natural formal English for official surveillance reports; keep the full meaning of every human-readable field (description, rejection reason, title, summary, recommendations — not just one field). '
            . '(2) NEVER translate or alter inside the text: person names, place names, camera numbers (e.g. camera 12), floor numbers, IDs, numeric codes (e.g. CAM-001), URLs, file names, email addresses, phone numbers, timestamps, dates, times, measurements, system terms/statuses — copy them exactly as they appear. '
            . '(3) NEVER invent a Latin transliteration for person/place names: if a name appears in Arabic script, keep the Arabic script exactly as-is inside the English sentence. Do not translate a proper name as if it were a common noun. '
            . '(4) Keep the same number of items, same keys, same order. Each translated text max 2000 chars. '
            . 'Schema: {"translations": [{"key": "...", "text": "..."}]}.'
            : 'You are a professional English→Arabic translator for an official Syrian government field-surveillance monitoring system. '
            . 'Context: field monitoring notes (floor number, camera number, observation time window) reviewed by report writers and compiled into official daily reports. '
            . 'Translate ONLY the human-readable English content into natural, clear, formal Modern Standard Arabic suitable for official reports (fluent — never a dumb literal translation). Preserve meaning, context, and surveillance/report terminology. '
            . 'Output JSON ONLY, no markdown, no explanations. '
            . 'RULES: (1) Natural formal Modern Standard Arabic; complete translation of every human-readable field (description, rejection reason, title, summary, recommendations). '
            . '(2) NEVER translate or alter inside the text: person names, place names, camera numbers, floor numbers, IDs, numeric codes, URLs, file names, email addresses, phone numbers, timestamps, dates, times, measurements, system terms/statuses — copy them exactly as they appear. '
            . '(3) NEVER invent an Arabic transliteration for person/place names written in Latin script: keep the Latin-script name exactly as-is inside the Arabic sentence. Do not translate a proper name as if it were a common noun. '
            . '(4) Keep the same number of items, same keys, same order. Each translated text max 2000 chars. '
            . 'Schema: {"translations": [{"key": "...", "text": "..."}]}.';

        $user = 'SOURCE_LANG: '.$sourceLang."\n"
            . 'TARGET_LANG: '.$targetLang."\n"
            . 'ITEMS_JSON: '.json_encode(['items' => $payloadItems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $result = $this->generator->generate($system, $user);
        } catch (\Throwable $e) {
            Log::warning('[L10N] provider batch failed', [
                'provider' => $this->name(),
                'dir' => "{$sourceLang}->{$targetLang}",
                'count' => count($payloadItems),
                'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        }

        $raw = $result->raw !== '' ? $result->raw : $result->body;
        $decoded = $this->extractJson($raw);
        if (!is_array($decoded) || !isset($decoded['translations']) || !is_array($decoded['translations'])) {
            throw new \RuntimeException('Invalid translation provider response.');
        }

        $map = [];
        foreach ($decoded['translations'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $k = isset($row['key']) ? (string) $row['key'] : '';
            $t = isset($row['text']) ? (string) $row['text'] : '';
            if ($k !== '' && array_key_exists($k, $texts) && trim($t) !== '') {
                $map[$k] = trim($t);
            }
        }

        return $map;
    }

    private function extractJson(string $text): ?array
    {
        $clean = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        if (str_starts_with($clean, '{')) {
            $d = json_decode($clean, true);
            if (is_array($d)) {
                return $d;
            }
        }
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $d = json_decode($m[0], true);
            if (is_array($d)) {
                return $d;
            }
        }

        return null;
    }
}
