<?php

namespace App\Services;

use App\Models\Report;

/**
 * الاختيار الذكي للورقة الرسمية بحسب عدد الملاحظات (1-7).
 * خارج النطاق (0 أو أكثر من 7) → null لتُستخدم الطباعة العامة.
 */
class ReportSheetService
{
    public function resolve(Report $report): ?array
    {
        $count = $report->relationLoaded('notes') ? $report->notes->count() : $report->notes()->count();
        $max = (int) config('report_sheets.max_notes', 7);
        if ($count < 1 || $count > $max) {
            return null;
        }

        $templates = config('report_sheets.templates', []);
        if (!isset($templates[$count])) {
            return null;
        }

        return array_merge(['n' => $count, 'count' => $count], $templates[$count]);
    }

    public function imageUrl(int $n): string
    {
        return route('report-sheets.image', $n);
    }

    public function imagePath(int $n): string
    {
        $templates = config('report_sheets.templates', []);
        if (isset($templates[$n]['file'])) {
            return public_path('images/التقارير/' . $templates[$n]['file']);
        }
        // توافق قديم: مجلد لكل قالب (إن وُجد)
        $pattern = (string) config('report_sheets.folder_pattern', '');
        if ($pattern !== '') {
            $legacy = public_path('images/التقارير/' . sprintf($pattern, $n) . '/' . config('report_sheets.file', 'screen.png'));
            if (is_file($legacy)) {
                return $legacy;
            }
        }

        return public_path('images/التقارير/' . sprintf((string) config('report_sheets.file_pattern', 'screen-%d.png'), $n));
    }
}
