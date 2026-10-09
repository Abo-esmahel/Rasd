<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isReportWriter() || $user->isGeneralManager();
    }

    public function viewInsights(User $user): bool
    {
        return $user->isReportWriter() || $user->isGeneralManager();
    }

    public function view(User $user, Report $report): bool
    {
        if ($report->isPublished()) {
            return $user->isReportWriter() || $user->isGeneralManager();
        }

        return $user->isReportWriter() && (int) $report->author_id === (int) $user->id;
    }

    /**
     * التفاعل (تعليق/إعجاب) لا معنى له قبل النشر: المسودة وثيقة عمل
     * خاصة بالكاتب، لا أحد غيره يقرأها فلن يعلّق عليها.
     */
    public function comment(User $user, Report $report): bool
    {
        return $report->isPublished()
            && ($user->isReportWriter() || $user->isGeneralManager())
            && $this->view($user, $report);
    }

    /** الإعجاب على التقرير — يُحجب عن المسودة كال تعليق. */
    public function reactReport(User $user, Report $report): bool
    {
        return $report->isPublished() && $this->view($user, $report);
    }

    /**
     * الإعجاب على ملاحظة داخل تقرير: يُحجب إن كان التقرير مسودة
     * (لا تفاعل قبل النشر)، ويُسمح لكل قارئ للمنشور.
     * التقرير أولاً لأن Gate يستنتج السياسة من أول وسيط.
     */
    public function reactNote(User $user, Report $report, Note $note): bool
    {
        return $report->isPublished() && $this->view($user, $report);
    }

    public function preview(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }

    public function export(User $user, Report $report): bool
    {
        return ($user->isReportWriter() || $user->isGeneralManager()) && $this->view($user, $report);
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
        && ($report->isDraft() || !$report->isLocked());    }

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
        return $user->isReportWriter()
            && (int) $report->author_id === (int) $user->id
            && $report->isDraft();
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
