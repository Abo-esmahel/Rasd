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

    public function viewInsights(User $user): bool
    {
        return $user->isReportWriter();
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->isReportWriter()) {
            return true;
        }

        return $report->isPublished() && (bool) $report->visible_to_monitors;
    }

    public function preview(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }

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
