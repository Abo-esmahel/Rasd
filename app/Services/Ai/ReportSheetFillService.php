<?php

namespace App\Services\Ai;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * تعبئة الورقة الرسمية بالرسم المحلي (Backend):
 * يرسم قالب screen-N المختار (بحسب عدد الملاحظات 1-7) مع بيانات
 * التقرير، ويحفظ الصورة المعبأة لعرضها في المعاينة والطباعة.
 * لا AI صور هنا — Gemini (نص فقط) يبقى في مسار generate-data.
 */
class ReportSheetFillService
{
    public const DISK = 'public';

    public function dataHash(Report $report): string
    {
        // بصمة بيانات النظام عبر نفس مسار الـrender (قالب حتمي + حقول السيرفر).
        return app(\App\Services\ReportPreview\ReportHtmlRenderingService::class)->systemHash($report);
    }

    public function hasFilled(Report $report): bool
    {
        // مسار HTML: الوجود = صف render في DB (image_path قد يكون null بعد الـmigration).
        // توافق: الصفوف القديمة (PNG) تُحتسب أيضاً إن كان ملفها موجوداً.
        try {
            if (\App\Models\ReportSheetRender::where('report_id', $report->id)->exists()) {
                return true;
            }
        } catch (\Throwable) {
        }

        return $report->ai_sheet_image_path
            && Storage::disk(self::DISK)->exists($report->ai_sheet_image_path);
    }

    public function isStale(Report $report): bool
    {
        if (!$this->hasFilled($report)) {
            return true;
        }

        return (string) $report->ai_sheet_data_hash !== $this->dataHash($report);
    }

    /**
     * توليد الورقة المعبأة وحفظها (زر يدوي — المالك فقط، والمسودة فقط).
     * يفوّض إلى ReportHtmlRenderingService ببيانات النظام الافتراضية (HTML محلي).
     */
    public function fill(User $user, Report $report): Report
    {
        app(\App\Services\ReportPreview\ReportHtmlRenderingService::class)->renderSystem($user, $report);

        return Report::with(['author', 'notes'])->findOrFail($report->id);
    }

    public function clear(User $user, Report $report): Report
    {
        if (!$user->isReportWriter() || (int) $report->author_id !== (int) $user->id) {
            throw new InvalidArgumentException(__('api.report_unauthorized_manage'));
        }
        if (!$report->fresh()->isDraft()) {
            throw new InvalidArgumentException(__('api.report_edit_draft_only'));
        }
        foreach (\App\Models\ReportSheetRender::where('report_id', $report->id)->get() as $row) {
            if ($row->image_path) {
                Storage::disk(self::DISK)->delete($row->image_path);
            }
            $row->delete();
        }
        if ($report->ai_sheet_image_path) {
            Storage::disk(self::DISK)->delete($report->ai_sheet_image_path);
        }
        $report->update([
            'ai_sheet_image_path' => null,
            'ai_sheet_generated_at' => null,
            'ai_sheet_data_hash' => null,
        ]);

        return $report->fresh(['author', 'notes']);
    }
}
