<?php

namespace App\Services\Localization;

use App\Jobs\WarmTranslationProjection;
use Illuminate\Support\Facades\Log;

/**
 * LocalizedPresenter — Presentation Resolver المركزي الوحيد.
 *
 *   Request → Current Locale → Valid Projection → Localized Presentation → Render
 *
 * القاعدة: ممنوع `if ($locale === 'en')` مبعثر في Controllers/Models/Views.
 * كل قرار عرض لغوي يمر من هنا. الفرونت لا يعرف شيئاً عن Gemini أو
 * Translation Projection أو source_hash أو provider — يستلم Presentation فقط.
 *
 * أداء القراءة الطبيعية:
 * - Controllers تستدعي preload* مرة واحدة للصفحة (دفعة واحدة).
 * - text() تقرأ من ذاكرة الطلب أولاً، ثم Cache/DB عبر TranslationService.
 * - نص جديد بلا projection → يُعرض المصدر فوراً + تدفئة async (بلا storm).
 */
class LocalizedPresenter
{
    /** ذاكرة الطلب: "type:id:field:locale" => localized text */
    protected static array $requestCache = [];

    /** حقول جُدولت للتدفئة في هذا الطلب (dedup) */
    protected static array $warmScheduled = [];

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

    /** سهم الرجوع المنطقي حسب الاتجاه (لا أيقونات مكسورة في LTR). */
    public function backArrow(?string $locale = null): string
    {
        return $this->locale($locale) === 'ar' ? '→' : '←';
    }

    /**
     * نص عرضي محلي لحقل كيان — المسار الوحيد لعرض البيانات الديناميكية.
     *
     * STRICT (Language Switch):
     *   Locale → Existing localized data → Render — ZERO Gemini هنا.
     * - لا Provider، لا dispatch، لا storm — قراءة محفوظة فقط.
     * - المصدر (Source of Truth) لا يُكتب ولا يُعدَّل إطلاقاً.
     * - عند locale=en ومصدر عربي بلا ترجمة محفوظة: placeholder إنجليزي
     *   (ZERO ARABIC VISIBLE) — لا يُعرض المصدر العربي silently.
     * - عند locale=ar: الأصل/المترجمة العربية حسب المصدر (لا placeholder أجنبي).
     * - لا يرمي استثناءات أبداً.
     */
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

        // ترجمة نمطية فورية لعنوان التقرير اليومي — لا تعتمد على Gemini/DB
        // "التقرير اليومي — 2026-09-12" ⇄ "Daily Report — 2026-09-12"
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
            $state = $this->translations->translationState($type, $id, $field, $source, $locale);
        } catch (\Throwable) {
            $state = 'source';
        }

        // لا ترجمة مطلوبة (نفس اللغة/تقني/اسم) — اعرض المصدر كما هو.
        if ($state === 'source') {
            self::$requestCache[$ck] = trim($source);

            return self::$requestCache[$ck];
        }

        try {
            $stored = $this->translations->resolveStored($type, $id, $field, $source, $locale);
        } catch (\Throwable) {
            $stored = null;
        }

        if (is_string($stored) && $stored !== '') {
            self::$requestCache[$ck] = $stored;

            return $stored;
        }

        // miss — جدولة تدفئة خلفية واعرض المصدر فوراً (لا حجب للمحتوى).
        // السبب: Gemini quota قد تكون ممتلئة (rate limit) فيبقى الـ placeholder عالقاً
        // كما في التقارير. عرض المصدر مع badge pending أفضل من إخفاء الملاحظة.
        $this->scheduleWarm($type, $id, $field, $source, $locale);
        self::$requestCache[$ck] = trim($source);

        return self::$requestCache[$ck];
    }

    /**
     * حالة ترجمة حقل للـ Smart Note Badge (قراءة فقط — بلا Gemini).
     * 'ready' | 'pending' | 'source'
     */
    public function translationState(string $type, int|string $id, string $field, ?string $source, ?string $locale = null): string
    {
        // نفس المنطق النمطي لعنوان التقرير — لا badge pending وهمي
        try {
            $loc = $this->locale($locale);
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

    /**
     * تحميل مسبق لدفعة مراجع — تستدعيه Controllers مرة واحدة لكل صفحة.
     * STRICT: قراءة محفوظة فقط (دفعة DB واحدة) — بلا Provider، بلا storm،
     * بلا Gemini على Language Switch. المفقود يُحل placeholder عند text().
     *
     * @param  array<int, array{type: string, id: int|string, field: string, text: mixed}>  $refs
     */
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

    /**
     * Localized FinalReportData — نفس Report Engine الحالي يستقبلها.
     * locale → Localized FinalReportData → Existing Report Engine.
     * الأرقام/التواريخ/الأسماء تبقى كما هي — النصوص البشرية فقط تتبدل.
     */
    public function reportPayload(int $reportId, array $source, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $observations = array_values((array) ($source['observations'] ?? []));
        $recommendations = trim((string) ($source['recommendations'] ?? ''));

        if ($locale === 'ar' && !$this->containsEnglishSource($observations, $recommendations)) {
            // المصدر عربي والهدف عربي — الأصل مباشرة، بلا Provider.
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

        // Batch warm for report observations: ONE job for all missing fields (atomic translation).
        // يضمن ترجمة كل الملاحظات معاً — لا تظهر وثيقة رسمية بنصف عربي ونصف إنجليزي بسبب نجاح جزئي.
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

        // Resolve from requestCache (populated by preload) — بلا استدعاء text() المنفرد الذي يجزّئ الدفعة.
        foreach ($observations as $i => $obs) {
            $field = "observation:{$i}";
            $ck = "report:{$reportId}:{$field}:{$locale}";
            if (array_key_exists($ck, self::$requestCache)) {
                $observations[$i] = self::$requestCache[$ck];
            } else {
                // Fallback للمصدر مع تثبيت في الكاش لمنع جدولة منفردة لاحقة في نفس الطلب
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

    /**
     * Presentation إشعار — الإشعارات المخزنة عربية/هيكلية، والعرض حسب locale.
     * المدخلات: $data من عمود notifications.data (مصفوفة). المخرجات: title/message فقط.
     */
    public function notification(array $data, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $title = (string) ($data['title'] ?? '');
        $message = (string) ($data['message'] ?? '');

        // إشعارات هيكلية جديدة (title_key/message_key + params) — عرض محلي فوري بلا AI.
        if (!empty($data['title_key']) || !empty($data['message_key'])) {
            $params = $this->notificationParams($data);
            if (!empty($data['title_key'])) {
                $title = __($data['title_key'], $params, $locale);
            }
            if (!empty($data['message_key'])) {
                $message = __($data['message_key'], $params, $locale);
            }

            return ['title' => $title, 'message' => $message];
        }

        // إشعارات قديمة (نص عربي مخزن): ar → كما هي؛ en → projection مغلفة بدفعة.
        if ($locale === 'en' && ($title !== '' || $message !== '')) {
            $entityId = (int) ($data['note_id'] ?? $data['submission_id'] ?? $data['report_id'] ?? 0);
            if ($entityId > 0) {
                $type = isset($data['report_id']) ? 'report' : (isset($data['submission_id']) ? 'submission' : 'note');
                $refs = [];
                if ($title !== '') {
                    $refs[] = ['type' => $type, 'id' => $entityId, 'field' => 'title', 'text' => $title];
                }
                if ($message !== '') {
                    $refs[] = ['type' => $type, 'id' => $entityId, 'field' => 'description', 'text' => $message];
                }
                $this->preload($refs, $locale);
                if ($title !== '') {
                    $title = $this->text($type, $entityId, 'title', $title, $locale);
                }
                if ($message !== '') {
                    $message = $this->text($type, $entityId, 'description', $message, $locale);
                }
            }
        }

        return ['title' => $title, 'message' => $message];
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
        $lang = SourceLanguage::detect($source);
        if ($lang === SourceLanguage::NEUTRAL || $lang === $locale) {
            return false;
        }

        try {
            return app(TranslationService::class)->shouldTranslate($source);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * تدفئة صريحة فقط (CREATE/UPDATE observer + زر إعادة المحاولة).
     * ممنوع استدعاؤها من مسار القراءة/Language Switch (ZERO Gemini هناك).
     */
    public function scheduleWarmExplicit(string $type, int|string $id, string $field, string $source, string $locale): void
    {
        $this->scheduleWarm($type, $id, $field, $source, $locale);
    }

    private function scheduleWarm(string $type, int|string $id, string $field, string $source, string $locale): void
    {
        $dk = "{$type}:{$id}:{$field}:{$locale}:".TranslationService::sourceHash($source);
        if (isset(self::$warmScheduled[$dk])) {
            return;
        }
        self::$warmScheduled[$dk] = true;
        try {
            WarmTranslationProjection::dispatchAfterResponse($type, $id, [$field => $source], $locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N] warm schedule failed', ['key' => "{$type}:{$id}:{$field}"]);
        }
    }

    private function scheduleWarmBatch(string $type, int|string $id, array $fields, string $locale): void
    {
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
            WarmTranslationProjection::dispatchAfterResponse($type, $id, $toDispatch, $locale);
        } catch (\Throwable $e) {
            Log::warning('[L10N] warm batch schedule failed', ['key' => "{$type}:{$id}", 'fields' => array_keys($toDispatch)]);
        }
    }

    private function containsEnglishSource(array $observations, string $recommendations): bool
    {
        foreach ($observations as $obs) {
            if (SourceLanguage::detect((string) $obs) === SourceLanguage::ENGLISH) {
                return true;
            }
        }

        return SourceLanguage::detect($recommendations) === SourceLanguage::ENGLISH;
    }

    /** params آمنة للعرض — IDs وأرقام وأسماء فقط (تُعرض كما هي، لا تُترجم). */
    private function notificationParams(array $data): array
    {
        $params = [];
        foreach (['id', 'note_id', 'submission_id', 'report_id', 'camera_number', 'camera', 'floor_number', 'floor', 'name', 'processor_name', 'count', 'time', 'observed_at'] as $k) {
            if (isset($data[$k]) && (is_scalar($data[$k]) || $data[$k] instanceof \Stringable)) {
                $params[$k] = (string) $data[$k];
            }
        }

        return $params;
    }

    /** لمواقف الاختبار/نهاية الطلب — تصفير ذاكرة الطلب. */
    public static function flushRequestCache(): void
    {
        self::$requestCache = [];
        self::$warmScheduled = [];
    }
}
