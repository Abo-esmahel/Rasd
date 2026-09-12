<?php

namespace App\Services\ReportPreview;

/**
 * الاختيار الحتمي للقالب — Backend فقط، بناءً على عدد الملاحظات.
 * الـAI لا يختار القالب ولا يُرسل له إلا قالب واحد.
 */
final class ReportTemplateSelector
{
    /**
     * مفتاح القالب المعلن (template-1 .. template-100).
     * Dynamic engine: أي عدد 1..100 له قالب (multi-page طبيعي).
     * صور الأوراق القديمة (1-7) تبقى في config للتوافق فقط.
     */
    public function keyForCount(int $count): ?string
    {
        if ($count < 1 || $count > ReportRenderPayload::MAX_OBSERVATIONS) {
            return null;
        }

        return "template-{$count}";
    }

    /** @return array{n: int, key: string, sheet: array}|null */
    public function selectForPayload(ReportRenderPayload $payload): ?array
    {
        $count = $payload->observationCount();
        if ($count < 1 || $count > ReportRenderPayload::MAX_OBSERVATIONS) {
            return null;
        }
        $templates = config('report_sheets.templates', []);
        if (isset($templates[$count])) {
            return ['n' => $count, 'key' => "template-{$count}", 'sheet' => array_merge(['n' => $count], $templates[$count])];
        }

        // خارج 1-7: وثيقة ديناميكية بلا صورة ورقة (نفس المحرك، صفحات طبيعية).
        return ['n' => $count, 'key' => "template-{$count}", 'sheet' => ['n' => $count, 'screen' => null]];
    }
}
