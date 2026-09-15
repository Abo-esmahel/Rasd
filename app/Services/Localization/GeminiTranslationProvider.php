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

        // If quota was exhausted recently, skip the API call entirely (avoids wasted 1-2s latency per request)
        // نفس الشيء للأعطال المتكررة: حظر الموقع/المفتاح (30 دقيقة) أو تعطل الخدمة (5 دقائق).
        // بدون هذا القاطع كل صفحة تعيد ضرب Gemini وتعلق اللودر 10-30 ثانية.
        $quotaKey = 'gemini:quota:exhausted';
        $blockedKey = 'gemini:blocked';
        $unavailableKey = 'gemini:unavailable';
        try {
            $cache = \Illuminate\Support\Facades\Cache::store(config('cache.default'));
            if ($cache->get($quotaKey) || $cache->get($blockedKey) || $cache->get($unavailableKey)) {
                return [];
            }
        } catch (\Throwable) {
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
        } catch (\App\Exceptions\Ai\AiRateLimitException $e) {
            $msg = strtolower($e->getMessage());
            $isDaily = str_contains($msg, 'exceeded') || str_contains($msg, 'daily') || str_contains($msg, 'quota');
            Log::warning('[L10N] provider rate limited', [
                'provider' => $this->name(),
                'model' => (string) config('ai.gemini.model', 'gemini-3.5-flash'),
                'dir' => "{$sourceLang}->{$targetLang}",
                'count' => count($payloadItems),
                'is_daily' => $isDaily,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            if ($isDaily) {
                // Cache the quota exhaustion for 6 hours — don't retry until quota resets
                try {
                    \Illuminate\Support\Facades\Cache::put('gemini:quota:exhausted', true, now()->addHours(6));
                } catch (\Throwable) {
                }
                return [];
            }
            // حد لحظي (وليس يومياً): أوقف المحاولات 5 دقائق بدل ضرب الـ API مع كل صفحة.
            try {
                \Illuminate\Support\Facades\Cache::put('gemini:unavailable', true, now()->addMinutes(5));
            } catch (\Throwable) {
            }
            throw $e;
        } catch (\App\Exceptions\Ai\AiAuthenticationException $e) {
            // مفتاح مرفوض أو موقع محظور ("User location is not supported") — لا فائدة من إعادة
            // المحاولة مع كل طلب. أوقف الترجمة 30 دقيقة واعرض النص الأصلي فوراً.
            try {
                \Illuminate\Support\Facades\Cache::put('gemini:blocked', true, now()->addMinutes(30));
            } catch (\Throwable) {
            }
            Log::warning('[L10N] provider blocked (auth/location), circuit open 30m', [
                'dir' => "{$sourceLang}->{$targetLang}",
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        } catch (\App\Exceptions\Ai\AiInvalidResponseException $e) {
            // يشمل "The service rejected the request" وحظر الموقع من جهة Google (400).
            // لا تعيد الضرب مباشرة: قاطع قصير 10 دقائق.
            try {
                \Illuminate\Support\Facades\Cache::put('gemini:unavailable', true, now()->addMinutes(10));
            } catch (\Throwable) {
            }
            Log::warning('[L10N] provider batch failed', [
                'provider' => $this->name(),
                'model' => (string) config('ai.gemini.model', 'gemini-3.5-flash'),
                'dir' => "{$sourceLang}->{$targetLang}",
                'count' => count($payloadItems),
                'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 300),
            ]);
            throw $e;
        } catch (\App\Exceptions\Ai\AiUnavailableException $e) {
            // timeout / 503: قاطع 5 دقائق بدل تعليق كل صفحة تالية.
            try {
                \Illuminate\Support\Facades\Cache::put('gemini:unavailable', true, now()->addMinutes(5));
            } catch (\Throwable) {
            }
            Log::warning('[L10N] provider unavailable, circuit open 5m', [
                'dir' => "{$sourceLang}->{$targetLang}",
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('[L10N] provider batch failed', [
                'provider' => $this->name(),
                'model' => (string) config('ai.gemini.model', 'gemini-3.5-flash'),
                'dir' => "{$sourceLang}->{$targetLang}",
                'count' => count($payloadItems),
                'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 300),
            ]);
            throw $e;
        }

        $raw = $result->raw !== '' ? $result->raw : $result->body;
        $decoded = $this->extractJson($raw);
        if (!is_array($decoded) || !isset($decoded['translations']) || !is_array($decoded['translations'])) {
            Log::warning('[L10N] provider invalid response', ['dir' => "{$sourceLang}->{$targetLang}", 'raw' => mb_substr($raw, 0, 300)]);
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
