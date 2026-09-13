<?php

namespace App\Services\ReportPreview;

final class ReportTemplateSelector
{
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

        return ['n' => $count, 'key' => "template-{$count}", 'sheet' => ['n' => $count, 'screen' => null]];
    }
}
