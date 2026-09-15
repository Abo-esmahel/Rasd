<?php

namespace App\Jobs;

use App\Services\Localization\TranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class WarmTranslationProjection implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        public string $type,
        public int|string $id,
        public array $fields,
        public string $locale,
    ) {
    }

    /**
     * Per-request merge buffer: "{type}:{id}:{locale}" => [field => text].
     * Overlapping warm requests for the same entity+locale within one
     * request are merged so terminate phase runs ONE job (one Gemini
     * round-trip) instead of N sequential ones. Same timing
     * (afterResponse), same field union — no behavior change.
     */
    protected static array $afterResponseBuffer = [];

    /** Keys already queued via afterResponse in this request. */
    protected static array $afterResponseScheduled = [];

    public static function scheduleAfterResponse(string $type, int|string $id, array $fields, string $locale, ?string $connection = null): void
    {
        $clean = [];
        foreach ((array) $fields as $field => $text) {
            $field = trim((string) $field);
            $text = trim((string) $text);
            if ($field !== '' && $text !== '') {
                $clean[$field] = $text;
            }
        }
        if ($clean === [] || !is_numeric($id)) {
            return;
        }
        $id = (int) $id;
        try {
            $locale = \App\Services\Localization\SourceLanguage::normalizeLocale($locale);
        } catch (\Throwable) {
            $locale = trim((string) $locale) !== '' ? trim((string) $locale) : 'ar';
        }
        // Non-sync drivers keep legacy behavior (worker-side merge is not possible).
        if ((string) config('queue.default', 'sync') !== 'sync') {
            try {
                $pending = self::dispatchAfterResponse($type, $id, $clean, $locale);
                if ($connection !== null) {
                    try { $pending->onConnection($connection); } catch (\Throwable) {}
                }
            } catch (\Throwable) {
                try { self::dispatch($type, $id, $clean, $locale); } catch (\Throwable) {}
            }

            return;
        }
        $key = "{$type}:{$id}:{$locale}";
        self::$afterResponseBuffer[$key] = array_merge(self::$afterResponseBuffer[$key] ?? [], $clean);
        if (isset(self::$afterResponseScheduled[$key])) {
            return; // queued job drains the merged buffer at handle time
        }
        self::$afterResponseScheduled[$key] = true;
        try {
            $pending = self::dispatchAfterResponse($type, $id, $clean, $locale);
            if ($connection !== null) {
                try { $pending->onConnection($connection); } catch (\Throwable) {}
            }
        } catch (\Throwable) {
            unset(self::$afterResponseScheduled[$key]);
            try {
                $pending = self::dispatch($type, $id, $clean, $locale);
                if ($connection !== null) {
                    try { $pending->onConnection($connection); } catch (\Throwable) {}
                }
            } catch (\Throwable) {}
        }
    }

    public function handle(TranslationService $service): void
    {
        // فشل سريع: إذا كان الذكاء معطلاً أو القاطع مفتوحاً (حصة/حظر/تعطل) لا تضرب الـ API أبداً.
        // هذا يمنع تعليق السيرفر أحادي الخيط (php artisan serve) بعد كل حفظ.
        try {
            if (!config('ai.enabled', false) || trim((string) config('ai.gemini.api_key', '')) === '') {
                return;
            }
            $cache = \Illuminate\Support\Facades\Cache::store(config('cache.default'));
            if ($cache->get('gemini:quota:exhausted') || $cache->get('gemini:blocked') || $cache->get('gemini:unavailable')) {
                return;
            }
        } catch (\Throwable) {
        }
        $refs = [];
        foreach ($this->fields as $field => $text) {
            $text = trim((string) $text);
            if ($field !== '' && $text !== '') {
                $refs[] = ['type' => $this->type, 'id' => $this->id, 'field' => (string) $field, 'text' => $text];
            }
        }
        // Drain fields merged after this job was queued (same request/process).
        // Queued-worker path has no static buffer, so the snapshot above still applies there.
        try {
            $bufferKey = "{$this->type}:{$this->id}:{$this->locale}";
            if (!empty(self::$afterResponseBuffer[$bufferKey])) {
                $seen = [];
                foreach ($refs as $r) {
                    $seen[$r['field']] = true;
                }
                foreach (self::$afterResponseBuffer[$bufferKey] as $field => $text) {
                    $text = trim((string) $text);
                    if ($field !== '' && $text !== '' && !isset($seen[$field])) {
                        $refs[] = ['type' => $this->type, 'id' => $this->id, 'field' => (string) $field, 'text' => $text];
                        $seen[$field] = true;
                    }
                }
                unset(self::$afterResponseBuffer[$bufferKey]);
            }
        } catch (\Throwable) {
        }
        if ($refs === []) {
            return;
        }

        try {
            // Chunked so merged sets larger than MAX_ITEMS keep full coverage.
            $map = [];
            foreach (array_chunk($refs, TranslationService::MAX_ITEMS) as $chunk) {
                $map += $service->localizeMany(array_values($chunk), $this->locale);
            }
        } catch (\App\Exceptions\Ai\AiException $e) {
            // خطأ ذكاء متوقع (حصة/حظر موقع/timeout): لا ترمِ — الرمي يعني إعادة المحاولة
            // 3 مرات مع backoff ويضاعف التعليق. القاطع في الـ Provider سيمنع التكرار.
            // نكتفي بالسجل ونعرض النص الأصلي.
            Log::info('[L10N-WARM] skipped (ai unavailable, no retry)', [
                'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
                'error' => mb_substr($e->getMessage(), 0, 150),
            ]);
            return;
        } catch (\Throwable $e) {
            Log::warning('[L10N-WARM] failed', [
                'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
                'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        }

        $warmed = 0;
        foreach ($refs as $ref) {
            $k = TranslationService::itemKey($ref['type'], $ref['id'], $ref['field']);
            if (isset($map[$k]) && $map[$k] !== $ref['text']) {
                $warmed++;
            }
        }

        Log::info('[L10N-WARM] done', [
            'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
            'fields' => count($refs), 'warmed' => $warmed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[L10N-WARM] exhausted', [
            'type' => $this->type, 'id' => $this->id, 'locale' => $this->locale,
            'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 300),
        ]);
    }
}
