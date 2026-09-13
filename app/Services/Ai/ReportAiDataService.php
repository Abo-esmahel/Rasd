<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\AiDisabledException;
use App\Exceptions\Ai\AiInvalidResponseException;
use App\Models\Report;
use App\Models\User;
use InvalidArgumentException;

class ReportAiDataService
{
    public function __construct(private AiTextGeneratorInterface $generator)
    {
    }

    /**
     * Backend/System owns: locale, facts, dates, counts, ordering. AI owns: grouping/dedup/phrasing.
     *
     * @return array{observations: string[], recommendations: string, count: int, locale: string}
     */
    public function generateFor(User $user, Report $report, ?string $locale = null): array
    {
        if (!$user->isReportWriter() || (int) $report->author_id !== (int) $user->id) {
            throw new InvalidArgumentException(__('api.report_unauthorized_generate'));
        }
        if (!config('ai.enabled', false)) {
            throw new AiDisabledException(__('api.ai_disabled_short'));
        }
        if (trim((string) config('ai.gemini.api_key', '')) === '') {
            throw new \App\Exceptions\Ai\AiAuthenticationException(__('api.ai_not_configured'));
        }

        $locale = $locale ? strtolower(trim($locale)) : strtolower((string) app()->getLocale());
        if (!in_array($locale, ['ar', 'en'], true)) $locale = 'ar';
        $isEn = $locale === 'en';

        $report->loadMissing(['author', 'notes']);
        // System orders chronologically — AI must not reorder arbitrarily
        $notes = $report->notes->sortBy(fn ($n) => $n->observed_at?->timestamp ?? PHP_INT_MAX)->values();
        $count = $notes->count();
        if ($count < 1 || $count > 7) {
            throw new InvalidArgumentException(__('api.ai_data_range'));
        }

        $tz = (string) config('app.timezone', 'UTC');
        $lines = [];
        foreach ($notes as $i => $n) {
            $at = $n->observed_at ? $n->observed_at->setTimezone($tz)->format('H:i') : '—';
            $desc = trim((string) $n->description);
            if (mb_strlen($desc) > 700) $desc = mb_substr($desc, 0, 700) . '…';
            $lines[] = 'NOTE ' . ($i + 1) . " [camera {$n->camera_number} | floor {$n->floor_number} | time {$at}]: " . $desc;
        }

        $system = self::systemPrompt($locale);
        if ($isEn) {
            $userPrompt = "GENERATION_LANGUAGE: en\nReport: {$report->title} | Date: {$report->report_date->toDateString()} | Locale: en\n"
                . "FACTS from system: count={$count}, timezone={$tz}, notes already sorted chronologically.\n"
                . 'Notes (' . $count . "):\n" . implode("\n", $lines)
                . "\n\nINSTRUCTION: Understand notes as a WHOLE first — group similar incidents, remove duplicate facts, prioritize critical over routine, preserve times/cameras/floors. Then produce exactly {$count} observations (ONE per NOTE in given order, but each phrased after holistic understanding — not isolated rephrase), plus recommendations (or empty if routine).";
        } else {
            $userPrompt = "GENERATION_LANGUAGE: ar\nتقرير: {$report->title} | التاريخ: {$report->report_date->toDateString()} | اللغة: ar\n"
                . "حقائق من النظام: العدد={$count}، المنطقة الزمنية={$tz}، الملاحظات مرتبة زمنياً مسبقاً.\n"
                . 'الملاحظات (' . $count . "):\n" . implode("\n", $lines)
                . "\n\nتعليمات: افهم الملاحظات ككل أولاً — جمّع المتشابهة، أزل التكرار، ميّز العاجل عن الروتيني، حافظ على الأوقات/الكاميرات/الطوابق. ثم أنتج بالضبط {$count} ملاحظة (واحدة لكل NOTE بنفس الترتيب المعطى لكن بعد الفهم الشمولي — لا إعادة صياغة معزولة)، مع توصيات (أو فارغة إذا روتيني).";
        }

        $result = $this->generator->generate($system, $userPrompt);
        $data = $this->extractData($result->raw !== '' ? $result->raw : $result->body, $count);

        return ['observations' => $data['observations'], 'recommendations' => $data['recommendations'], 'count' => $count, 'locale' => $locale];
    }

    /**
     * Locale-aware drafting: backend decides language, AI obeys generation_language.
     * Intelligence: holistic understanding (group/dedup/prioritize) before 1-per-note wording.
     */
    private static function systemPrompt(string $locale = 'ar'): string
    {
        $isEn = $locale === 'en';
        if ($isEn) {
            return 'You are the drafting officer for an official Syrian government daily surveillance report. '
                . 'Write formal, natural, professional English (consistent official terminology, not literal translation). Output JSON ONLY: no markdown, no explanations, no code fences. '
                . 'Locale/generation_language: en — ALL output (observations + recommendations) MUST be in English even if NOTES are Arabic. '
                . 'INPUT: NOTEs already sorted chronologically by system, each with camera/floor/time + raw description (may hold typos or Arabic text — understand meaning, never copy typos). '
                . 'HOLISTIC INTELLIGENCE FIRST: Before wording, understand the whole incident set as ONE story. Group similar events (same location/type/window), remove duplicate facts (same incident captured by two cameras = mention once in wording logic), distinguish critical/urgent from routine, capture cause→effect relations. Preserve all factual anchors (times/cameras/floors). Then produce ONE coherent per-NOTE phrasing reflecting that understanding (not isolated rephrasing). '
                . 'TASK A — OBSERVATIONS: exactly one per NOTE, observation i from NOTE i only, same order. '
                . 'Each observation is ONE concise past-tense fact (max 300 characters): describe what was observed. '
                . 'Do NOT mention camera/floor/time in the sentence — metadata row shows it. '
                . 'If note is vague: describe only what is certain. If empty: say "No details were recorded." '
                . 'NEVER invent names/numbers/causes. Never inflate certainty; use "observed/noted". '
                . 'FORBIDDEN in observations: orders, instructions, must/should, the word recommendation, numbered sub-lists, introductions, filler openers. Do NOT number observations. Do NOT repeat the same fact across two observations verbatim (deduplicate). '
                . 'TASK B — RECOMMENDATIONS: decide FIRST if follow-up is truly needed. Routine uneventful notes → "" (empty, no disclaimer, no filler). '
                . 'If needed: 1–5 concrete forward actions, prioritized, each starting with a verb. Number as "1." "2." … one per line. Max 800 chars. Never restate an observation verbatim. '
                . 'SELF-CHECK: (1) observations.length == NOTEs, (2) no recommendation language inside observation, (3) zero invented details, (4) recommendations empty unless truly needed. '
                . 'Schema: {"observations": [{"text": "..."}], "recommendations": "..."}.';
        }

        return 'You are the drafting officer for an official Syrian government daily surveillance report. '
            . 'Write formal Modern Standard Arabic — عربية رسمية طبيعية (مصطلحات ثابتة، ليست ترجمة حرفية). Output JSON ONLY: no markdown, no explanations, no code fences. '
            . 'Locale/generation_language: ar — كل المخرجات (observations + recommendations) يجب أن تكون عربية حتى لو كانت الملاحظات إنجليزية. '
            . 'INPUT: NOTEs مرتبة زمنياً مسبقاً من النظام، كل واحدة مع camera/floor/time + description الخام (قد يحوي أخطاء إملائية أو نص إنجليزي — افهم المعنى، لا تنسخ الأخطاء). '
            . 'الذكاء الشمولي أولاً: قبل الصياغة افهم الحادثة ككل كقصة واحدة. جمّع المتشابهة (نفس الموقع/النوع/النافذة)، أزل التكرار الحرفي (نفس الواقعة بكاميرتين = صِغها بلا تكرار لفظي)، ميّز العاجل عن الروتيني، أوضح علاقة السبب→النتيجة. حافظ على كل المرساة (الأوقات/الكاميرات/الطوابق). ثم أنتج صياغة واحدة متماسكة لكل NOTE تعكس ذلك الفهم (ليست إعادة صياغة معزولة). '
            . 'TASK A — OBSERVATIONS: بالضبط واحدة لكل NOTE، الملاحظة i من NOTE i فقط، بنفس الترتيب. '
            . 'كل ملاحظة جملة واحدة مركزة ماضوية (حد 300 محرف): صف ما روصد. '
            . 'لا تذكر camera/floor/time داخل الجملة — الصف يعرضها. '
            . 'إذا كانت الملاحظة غامضة: صف المؤكد فقط. إذا فارغة: قل "لم تُسجّل تفاصيل". '
            . 'ممنوع اختراع أسماء/أرقام/أسباب. لا تضخم اليقين: استخدم رُصد/لوحظ. '
            . 'ممنوع داخل observations: أوامر، يجب/ينبغي، كلمة توصية، قوائم مرقمة، مقدمات، حشو. لا ترقّم. لا تكرر نفس الواقعة حرفياً عبر ملاحظتين. '
            . 'TASK B — RECOMMENDATIONS: قرر أولاً هل المتابعة مطلوبة فعلاً. روتين بلا شذوذ → "" (فارغ بلا جملة تفسيرية). '
            . 'إذا مطلوب: 1–5 إجراءات عملية مرتبة حسب الأولوية، كل واحدة تبدأ بفعل. رقّم "1." "2." … سطر لكل واحدة. حد 800 محرف. لا تعِد نص الملاحظة حرفياً. '
            . 'SELF-CHECK: (1) observations.length == NOTEs، (2) بلا لغة توصية داخل الملاحظة، (3) صفر اختراع، (4) التوصيات فارغة إلا إذا لزم فعلاً. '
            . 'Schema: {"observations": [{"text": "..."}], "recommendations": "..."}.';
    }

    /**
     * @return array{observations: string[], recommendations: string}
     */
    private function extractData(string $text, int $expectedCount): array
    {
        $clean = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $decoded = null;
        if (str_starts_with($clean, '{')) {
            $decoded = json_decode($clean, true);
        }
        if (!is_array($decoded) && preg_match('/\{.*\}/s', $clean, $m)) {
            $decoded = json_decode($m[0], true);
        }
        if (!is_array($decoded)) {
            throw new AiInvalidResponseException(__('api.ai_data_invalid'));
        }

        $obs = $decoded['observations'] ?? null;
        if (!is_array($obs) || count($obs) !== $expectedCount) {
            throw new AiInvalidResponseException(__('api.ai_count_mismatch'));
        }
        $texts = [];
        foreach ($obs as $o) {
            $t = trim((string) (is_array($o) ? ($o['text'] ?? '') : $o));
            if ($t === '' || mb_strlen($t) > 2000) {
                throw new AiInvalidResponseException(__('api.ai_note_invalid'));
            }
            // The engine renders its own ordinal titles — drop any numbering
            // or bullets the model prepended, plus leading filler openers.
            $t = self::stripObservationFluff($t);
            if ($t === '') {
                throw new AiInvalidResponseException(__('api.ai_note_empty'));
            }
            self::assertObservationIsFact($t);
            $texts[] = $t;
        }
        // The same fact must not be repeated across observations.
        if (count(array_unique($texts)) !== count($texts)) {
            throw new AiInvalidResponseException(__('api.ai_notes_duplicate'));
        }
        $reco = trim((string) ($decoded['recommendations'] ?? ''));
        if (mb_strlen($reco) > 2000) {
            throw new AiInvalidResponseException(__('api.ai_reco_too_long'));
        }
        // A disclaimer instead of actions ("no recommendations") is not a
        // recommendation: normalize short ones to empty so the document
        // omits the section instead of displaying filler.
        $reco = self::normalizeRecommendations($reco);
        // Recommendations must be actions, not a verbatim restatement.
        if ($reco !== '' && in_array($reco, $texts, true)) {
            throw new AiInvalidResponseException(__('api.ai_reco_is_note'));
        }

        return ['observations' => $texts, 'recommendations' => $reco];
    }

    /**
     * Collapse "no recommendations" disclaimers to an empty string.
     * Only short standalone disclaimers qualify — real nuanced text stays.
     */
    private static function normalizeRecommendations(string $reco): string
    {
        $t = trim($reco);
        if ($t === '' || mb_strlen($t) > 80) {
            return $reco;
        }
        $stripped = trim(preg_replace('/^(\d{1,2}\s*[.)\-–]\s*|[\-•*–—]+\s*)/u', '', $t) ?? '');
        if ($stripped === '') {
            return '';
        }
        // Explicit Arabic-aware boundary (\b is unreliable after Arabic
        if (preg_match('/^(لا\s+(توجد|يوجد|حاجة|داعي)|بدون|لاشيء|لا شيء)(?=\s|$|[.،,:؛!؟\-–—])/u', $stripped)
            || in_array($stripped, ['لا', 'لا يوجد', 'لا توجد'], true)) {
            return '';
        }

        return $reco;
    }

    /**
     * Remove model-added numbering/bullets and leading filler openers.
     * Only the start of the text is touched — the fact itself is preserved.
     */
    private static function stripObservationFluff(string $t): string
    {
        $t = trim(preg_replace('/^(\d{1,2}\s*[.)\-–]\s*|[\-•*–—]+\s*)/u', '', trim($t)) ?? '');
        $t = trim(preg_replace(
            '/^(تجدر الإشارة إلى أنّ?|من الجدير بالذكر أنّ?|يجدر الذكر أنّ?|وفي الختام|وختاماً|وباختصار|باختصار)\s*[,،:]?\s*/u',
            '',
            $t
        ) ?? '');

        return $t;
    }

    /**
     * Smart guard: an observation must read as an observed fact, never as
     * recommendations or procedural filler smuggled into the wrong slot.
     *
     * @throws AiInvalidResponseException
     */
    private static function assertObservationIsFact(string $t): void
    {
        // Dodge: "no observations" while notes exist.
        if (preg_match('/لا\s+(يوجد|توجد)\s+(ملاحظات|ملاحظة)/u', $t)) {
            throw new AiInvalidResponseException(__('api.ai_empty_denial'));
        }
        // Smuggled numbered action list inside a single observation.
        if (preg_match_all('/(?:^|\s)\d{1,2}\s*[.)]\s+/u', $t) >= 2) {
            throw new AiInvalidResponseException(__('api.ai_numbered_list'));
        }
        // Recommendation modal language or an explicit "recommendation" lead.
        if (preg_match('/(يوصى|ينبغي|يتوجب|يجب (أن|على|عليكم)|عليكم بـ|التوصية بـ?)\s*/u', $t)
            || preg_match('/^التوصية/u', trim($t))) {
            throw new AiInvalidResponseException(__('api.ai_reco_leak'));
        }
        // Leading imperative orders (review/coordinate/complete/…) belong in
        // recommendations, never in an observed fact.
        if (preg_match('/^(راجع(وا|ي)?|نسّق(وا)?|استكمل(وا)?|قم|قوموا|تواصل(وا)?|أبلغ(وا)?|عزّز(وا)?|كثّف(وا)?|احرص(وا)?|اتخذ(وا)?|بادر(وا)?)\b/u', trim($t))) {
            throw new AiInvalidResponseException(__('api.ai_imperative'));
        }
    }
}
