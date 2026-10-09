<?php

namespace App\Services\Localization;

use App\Jobs\WarmTranslationProjection;
use Illuminate\Support\Facades\Log;

class LocalizedPresenter
{
    protected static array $requestCache = [];

    protected static array $warmScheduled = [];

    /** Keys "{$type}:{$id}:{$field}:{$locale}" known to hold a REAL stored translation. */
    protected static array $translatedKeys = [];

    public function __construct(private TranslationService $translations)
    {
    }

    public function locale(?string $locale = null): string
    {
        return SourceLanguage::normalizeLocale($locale ?? app()->getLocale());
    }

    public function dir(?string $locale = null): string
    {
        return $this->locale($locale) === 'ar' ? 'rtl' : 'ltr';
    }

    public function backArrow(?string $locale = null): string
    {
        return $this->locale($locale) === 'ar' ? '→' : '←';
    }

    public function text(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        $locale = $this->locale($locale);
        $source = (string) $source;
        if (trim($source) === '') {
            return '';
        }

        $ck = "{$type}:{$id}:{$field}:{$locale}";
        if (array_key_exists($ck, self::$requestCache)) {
            return self::$requestCache[$ck];
        }

        if ($type === 'report' && $field === 'title') {
            $t = trim($source);
            if ($locale === 'en' && preg_match('/^التقرير اليومي\s*[—\-]\s*(\d{4}-\d{2}-\d{2})$/u', $t, $m)) {
                self::$requestCache[$ck] = 'Daily Report — ' . $m[1];

                return self::$requestCache[$ck];
            }
            if ($locale === 'ar' && preg_match('/^Daily Report\s*[—\-]\s*(\d{4}-\d{2}-\d{2})$/', $t, $m)) {
                self::$requestCache[$ck] = 'التقرير اليومي — ' . $m[1];

                return self::$requestCache[$ck];
            }
        }

        try {
            $map = $this->translations->resolveStoredMany([
                ['type' => $type, 'id' => $id, 'field' => $field, 'text' => $source]
            ], $locale);
        } catch (\Throwable) {
            $map = [];
        }

        $ik = TranslationService::itemKey($type, $id, $field);
        if (isset($map[$ik])) {
            self::$requestCache[$ck] = $map[$ik];
            self::$translatedKeys[$ck] = true;

            return $map[$ik];
        }

        try {
            $state = $this->translations->translationState($type, $id, $field, $source, $locale);
        } catch (\Throwable) {
            $state = 'source';
        }

        if ($state === 'source') {
            self::$requestCache[$ck] = trim($source);

            return self::$requestCache[$ck];
        }

        try {
            if ($this->translations->shouldTranslateCached($source)) {
                $this->scheduleWarm($type, $id, $field, $source, $locale);
            }
        } catch (\Throwable $e) {
            Log::warning('[L10N] schedule warm failed', ['key' => "{$type}:{$id}:{$field}", 'error' => mb_substr($e->getMessage(), 0, 150)]);
        }

        self::$requestCache[$ck] = trim($source);
        return self::$requestCache[$ck];
    }

    public function translationState(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        try {
            $loc = $this->locale($locale);
            if (isset(self::$translatedKeys["{$type}:{$id}:{$field}:{$loc}"])) {
                return 'ready';
            }
            $t = trim((string) $source);
            if ($type === 'report' && $field === 'title') {
                if ($loc === 'en' && preg_match('/^التقرير اليومي\s*[—\-]\s*\d{4}-\d{2}-\d{2}$/u', $t)) {
                    return 'ready';
                }
                if ($loc === 'ar' && preg_match('/^Daily Report\s*[—\-]\s*\d{4}-\d{2}-\d{2}$/', $t)) {
                    return 'ready';
                }
            }
        } catch (\Throwable) {
        }
        try {
            return $this->translations->translationState($type, $id, $field, $source, $locale ?? $this->locale());
        } catch (\Throwable) {
            return 'source';
        }
    }

    public function preload(array $refs, ?string $locale = null): void
    {
        $locale = $this->locale($locale);
        if ($refs === []) {
            return;
        }
        try {
            $map = $this->translations->resolveStoredMany($refs, $locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N] preload failed', ['error' => get_class($e)]);
            return;
        }
        foreach ($map as $key => $text) {
            self::$requestCache["{$key}:{$locale}"] = $text;
            self::$translatedKeys["{$key}:{$locale}"] = true;
        }
    }

    public function preloadNotes(iterable $notes, ?string $locale = null): void
    {
        $refs = [];
        foreach ($notes as $note) {
            $id = $note->id ?? null;
            if (!is_numeric($id)) {
                continue;
            }
            if (isset($note->description)) {
                $refs[] = ['type' => 'note', 'id' => (int) $id, 'field' => 'description', 'text' => $note->description];
            }
            if (!empty($note->rejection_reason)) {
                $refs[] = ['type' => 'note', 'id' => (int) $id, 'field' => 'rejection_reason', 'text' => $note->rejection_reason];
            }
        }
        $this->preload($refs, $locale);
    }

    public function preloadSubmissions(iterable $submissions, ?string $locale = null): void
    {
        $refs = [];
        foreach ($submissions as $s) {
            $id = $s->id ?? null;
            if (!is_numeric($id)) {
                continue;
            }
            if (isset($s->description)) {
                $refs[] = ['type' => 'submission', 'id' => (int) $id, 'field' => 'description', 'text' => $s->description];
            }
            if (!empty($s->rejection_reason)) {
                $refs[] = ['type' => 'submission', 'id' => (int) $id, 'field' => 'rejection_reason', 'text' => $s->rejection_reason];
            }
        }
        $this->preload($refs, $locale);
    }

    public function preloadReports(iterable $reports, ?string $locale = null): void
    {
        $refs = [];
        foreach ($reports as $r) {
            $id = $r->id ?? null;
            if (!is_numeric($id)) {
                continue;
            }
            foreach (['title', 'summary', 'content', 'recommendations'] as $f) {
                if (!empty($r->{$f})) {
                    $refs[] = ['type' => 'report', 'id' => (int) $id, 'field' => $f, 'text' => $r->{$f}];
                }
            }
        }
        $this->preload($refs, $locale);
    }

    public function reportPayload(int $reportId, array $source, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $observations = array_values((array) ($source['observations'] ?? []));
        $recommendations = trim((string) ($source['recommendations'] ?? ''));

        if ($locale === 'ar' && !$this->containsEnglishSource($observations, $recommendations)) {
            return ['observations' => $observations, 'recommendations' => $recommendations];
        }

        $refs = [];
        foreach ($observations as $i => $obs) {
            if (trim((string) $obs) !== '') {
                $refs[] = ['type' => 'report', 'id' => $reportId, 'field' => "observation:{$i}", 'text' => (string) $obs];
            }
        }
        if ($recommendations !== '') {
            $refs[] = ['type' => 'report', 'id' => $reportId, 'field' => 'recommendations', 'text' => $recommendations];
        }
        $this->preload($refs, $locale);

        $missing = [];
        foreach ($observations as $i => $obs) {
            $field = "observation:{$i}";
            $ck = "report:{$reportId}:{$field}:{$locale}";
            if (array_key_exists($ck, self::$requestCache)) {
                continue;
            }
            $src = trim((string) $obs);
            if ($src === '' || !$this->needsTranslation($src, $locale)) {
                continue;
            }
            $missing[$field] = $src;
        }
        if ($recommendations !== '' && $this->needsTranslation($recommendations, $locale)) {
            $field = 'recommendations';
            $ck = "report:{$reportId}:{$field}:{$locale}";
            if (!array_key_exists($ck, self::$requestCache)) {
                $missing[$field] = $recommendations;
            }
        }
        if ($missing !== []) {
            $this->scheduleWarmBatch('report', $reportId, $missing, $locale);
        }

        foreach ($observations as $i => $obs) {
            $field = "observation:{$i}";
            $ck = "report:{$reportId}:{$field}:{$locale}";
            if (array_key_exists($ck, self::$requestCache)) {
                $observations[$i] = self::$requestCache[$ck];
            } else {
                $observations[$i] = (string) $obs;
                self::$requestCache[$ck] = (string) $obs;
            }
        }
        if ($recommendations !== '') {
            $field = 'recommendations';
            $ck = "report:{$reportId}:{$field}:{$locale}";
            if (array_key_exists($ck, self::$requestCache)) {
                $recommendations = self::$requestCache[$ck];
            } else {
                self::$requestCache[$ck] = $recommendations;
            }
        }

        return ['observations' => $observations, 'recommendations' => $recommendations];
    }

    public function notification(array $data, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $type = strtolower((string) ($data['type'] ?? ''));
        $key = match (true) {
            str_contains($type, 'note_accepted'), str_contains($type, 'noteacceptednotification') => 'notifications.note_accepted_title',
            str_contains($type, 'note_rejected'), str_contains($type, 'noterejectednotification') => 'notifications.note_rejected_title',
            str_contains($type, 'note_sent'), str_contains($type, 'notesentnotification') => 'notifications.note_sent_title',
            str_contains($type, 'dispatch_accepted'), str_contains($type, 'dispatchacceptednotification') => 'notifications.dispatch_accepted_title',
            str_contains($type, 'dispatch_rejected'), str_contains($type, 'dispatchrejectednotification') => 'notifications.dispatch_rejected_title',
            str_contains($type, 'dispatch_sent'), str_contains($type, 'dispatchsentnotification'), str_contains($type, 'dispatch') => 'notifications.dispatch_sent_title',
            str_contains($type, 'report_published'), str_contains($type, 'reportpublishednotification') => 'notifications.report_published_title',
            str_contains($type, 'reply_created') => 'notifications.reply_created_title',
            str_contains($type, 'comment_created') => 'notifications.comment_created_title',
            str_contains($type, 'commentcreatednotification') => ! empty($data['parent_id'])
                ? 'notifications.reply_created_title'
                : 'notifications.comment_created_title',
            default => 'notifications.generic_title',
        };
        $params = $this->notificationParams($data);
        if (! isset($params['id'])) {
            foreach (['note_id', 'submission_id', 'general_submission_id', 'report_id', 'comment_id'] as $k) {
                if (isset($data[$k]) && is_scalar($data[$k]) && (string) $data[$k] !== '') {
                    $params['id'] = (string) $data[$k];
                    break;
                }
            }
        }

        $messageKey = preg_replace('/_title$/', '_message', $key);

        return [
            'title' => __($key, $params, $locale),
            'message' => __($messageKey, $params, $locale),
        ];
    }

    /** Keep only the fields needed to identify and open a notification target. */
    public function safeNotificationData(array $data, ?string $locale = null): array
    {
        $rawType = (string) ($data['type'] ?? 'generic');
        $type = strtolower($rawType);
        $monitorAlert = !empty($data['monitor_alert']);
        $category = match (true) {
            str_contains($type, 'note_'), str_contains($type, 'notesentnotification'), str_contains($type, 'noteacceptednotification'), str_contains($type, 'noterejectednotification') => 'note',
            str_contains($type, 'dispatch') => 'dispatch',
            str_contains($type, 'report_published'), str_contains($type, 'reportpublishednotification') => 'report',
            str_contains($type, 'comment_created'), str_contains($type, 'reply_created'), str_contains($type, 'commentcreatednotification') => 'report',
            default => 'generic',
        };
        if ($monitorAlert) {
            $category = 'generic';
        }
        $localized = $this->notification($data, $locale);
        $safe = [
            'type' => 'generic',
            'category' => $category,
            'priority' => 'normal',
            'title' => $localized['title'],
            'message' => $localized['message'],
            'body' => $localized['message'],
        ];
        foreach (['note_id', 'submission_id', 'general_submission_id', 'report_id', 'comment_id', 'parent_id'] as $key) {
            $id = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $safe[$key] = $id;
            }
        }
        if (! empty($data['sender_name']) && is_scalar($data['sender_name'])) {
            $safe['sender_name'] = mb_substr((string) $data['sender_name'], 0, 120);
        }

        $noteId = $safe['note_id'] ?? null;
        $submissionId = $safe['submission_id'] ?? $safe['general_submission_id'] ?? null;
        $reportId = $safe['report_id'] ?? null;
        if ($noteId) {
            $safe['url'] = '/notes/'.$noteId;
        } elseif ($submissionId) {
            $safe['url'] = '/general-submissions/'.$submissionId;
        } elseif ($reportId) {
            $safe['url'] = '/reports/'.$reportId.(isset($safe['comment_id']) ? '#report-discussion' : '');
        }

        return $safe;
    }

    public function statusLabel(string $status, ?string $locale = null): string
    {
        return TranslationService::statusLabel($status, $this->locale($locale));
    }

    public function ratingLabel(float $rating, ?string $locale = null): string
    {
        $locale = $this->locale($locale);
        if ($rating >= 4.5) {
            return __('ui.rating_excellent', [], $locale);
        }
        if ($rating >= 3.5) {
            return __('ui.rating_very_good', [], $locale);
        }
        if ($rating >= 2.5) {
            return __('ui.rating_good', [], $locale);
        }
        if ($rating >= 1.5) {
            return __('ui.rating_fair', [], $locale);
        }
        if ($rating > 0) {
            return __('ui.rating_weak', [], $locale);
        }

        return __('ui.rating_none', [], $locale);
    }

    private function needsTranslation(string $source, string $locale): bool
    {
        $lang = TranslationService::detectCached($source);
        if ($lang === SourceLanguage::NEUTRAL || $lang === $locale) {
            return false;
        }

        try {
            return app(TranslationService::class)->shouldTranslateCached($source);
        } catch (\Throwable) {
            return false;
        }
    }

    public function scheduleWarmExplicit(string $type, int|string $id, string $field, string $source, string $locale): void
    {
        $this->scheduleWarm($type, $id, $field, $source, $locale);
    }

    private function scheduleWarm(string $type, int|string $id, string $field, string $source, string $locale): void
    {
        if ($this->warmCircuitOpen()) {
            return;
        }
        $dk = "{$type}:{$id}:{$field}:{$locale}:".TranslationService::sourceHash($source);
        if (isset(self::$warmScheduled[$dk])) {
            return;
        }
        self::$warmScheduled[$dk] = true;
        try {
            WarmTranslationProjection::scheduleAfterResponse($type, $id, [$field => $source], $locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N] warm schedule failed', ['key' => "{$type}:{$id}:{$field}"]);
        }
    }

    private function scheduleWarmBatch(string $type, int|string $id, array $fields, string $locale): void
    {
        if ($this->warmCircuitOpen()) {
            return;
        }
        $toDispatch = [];
        foreach ($fields as $field => $text) {
            $text = trim((string) $text);
            if ($field === '' || $text === '') {
                continue;
            }
            $dk = "{$type}:{$id}:{$field}:{$locale}:".TranslationService::sourceHash($text);
            if (isset(self::$warmScheduled[$dk])) {
                continue;
            }
            self::$warmScheduled[$dk] = true;
            $toDispatch[$field] = $text;
        }
        if ($toDispatch === []) {
            return;
        }
        try {
            WarmTranslationProjection::scheduleAfterResponse($type, $id, $toDispatch, $locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N] warm batch schedule failed', ['key' => "{$type}:{$id}", 'fields' => array_keys($toDispatch)]);
        }
    }

    /**
     * قاطع الدائرة للتدفئة الخلفية: لا تجدول مهام Gemini إذا كان الذكاء معطلاً
     * أو سبق وفشل (حصة/حظر/تعطل) — وإلا تراكمت مهام afterResponse وعلّقت السيرفر.
     */
    private function warmCircuitOpen(): bool
    {
        try {
            if (!config('ai.enabled', false) || trim((string) config('ai.gemini.api_key', '')) === '') {
                return true;
            }
            $cache = \Illuminate\Support\Facades\Cache::store(config('cache.default'));
            if ($cache->get('gemini:quota:exhausted') || $cache->get('gemini:blocked') || $cache->get('gemini:unavailable')) {
                return true;
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function containsEnglishSource(array $observations, string $recommendations): bool
    {
        foreach ($observations as $obs) {
            if (TranslationService::detectCached((string) $obs) === SourceLanguage::ENGLISH) {
                return true;
            }
        }

        return TranslationService::detectCached($recommendations) === SourceLanguage::ENGLISH;
    }

    private function notificationParams(array $data): array
    {
        $params = [];
        foreach (['id', 'note_id', 'submission_id', 'report_id', 'camera_number', 'camera', 'floor_number', 'floor', 'name', 'sender_name', 'processor_name', 'count', 'time', 'observed_at'] as $k) {
            if (isset($data[$k]) && (is_scalar($data[$k]) || $data[$k] instanceof \Stringable)) {
                $params[$k] = (string) $data[$k];
            }
        }

        if (isset($params['sender_name']) && !isset($params['name'])) {
            $params['name'] = $params['sender_name'];
        } elseif (isset($params['processor_name']) && !isset($params['name'])) {
            $params['name'] = $params['processor_name'];
        }

        return $params;
    }

    public static function flushRequestCache(): void
    {
        self::$requestCache = [];
        self::$warmScheduled = [];
        self::$translatedKeys = [];
        TranslationService::flushRequestCache();
    }
}
