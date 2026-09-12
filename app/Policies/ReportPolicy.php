<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isMonitor() || $user->isReportWriter();
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->isReportWriter()) {
            return true;
        }

        return $report->isPublished() && (bool) $report->visible_to_monitors;
    }

    /**
     * رؤية المعاينة الرسمية (Inline فقط): منفصلة عن التصدير.
     * - المراقب: منشور + ظاهر للمراقبين فقط (الاعتماد يُفحص في الكنترولر: approved+published).
     * - الكاتب: أي تقرير يراه (الاعتماد يُفحص في الكنترولر).
     */
    public function preview(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }

    /**
     * صلاحية التصدير/التنزيل — منفصلة تماماً عن View.
     * ممنوعة على المراقب دائماً، حتى مع View.
     * فقط كاتب التقارير يملكها (الاعتماد+النشر يُفحصان في الكنترولر).
     */
    public function export(User $user, Report $report): bool
    {
        return $user->isReportWriter();
    }

    public function create(User $user): bool
    {
        return $user->isReportWriter();
    }

    public function update(User $user, Report $report): bool
    {
        return $user->isReportWriter()
            && (int) $report->author_id === (int) $user->id
            && ($report->isDraft() || !$report->isLocked());
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->isReportWriter()
            && (int) $report->author_id === (int) $user->id
            && ($report->isDraft() || !$report->isLocked());
    }

    public function publish(User $user, Report $report): bool
    {
        return $user->isReportWriter() && (int) $report->author_id === (int) $user->id && $report->isDraft();
    }

    public function unpublish(User $user, Report $report): bool
    {
        return $user->isReportWriter()
            && (int) $report->author_id === (int) $user->id
            && $report->isPublished()
            && !$report->isLocked();
    }

    public function attachNotes(User $user, Report $report): bool
    {
        // التقارير تُبنى تلقائياً من ملاحظات اليوم — ممنوع الإضافة/الحذف/إعادة الترتيب يدوياً
        return false;
    }

    public function generateAi(User $user, Report $report): bool
    {
        return $this->update($user, $report);
    }

    public function fillSheet(User $user, Report $report): bool
    {
        return $this->update($user, $report);
    }
}
