<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function create(User $user): bool
    {
        return $user->isMonitor() || $user->isReportWriter();
    }

    public function viewAny(User $user): bool
    {
        return $user->isMonitor() || $user->isReportWriter();
    }

    public function view(User $user, Note $note): bool
    {
        // المالك يرى دائماً إرساليته
        if ($note->user_id === $user->id) {
            return true;
        }

        // كاتب التقرير يرى الملاحظات غير المسودة فقط (قيد المراجعة / مقبولة / مرفوضة)
        // لكن الملاحظون الآخرون لا يرونها — خصوصية الإرسالية
        if ($user->isReportWriter()) {
            return $note->status !== Note::STATUS_DRAFT;
        }

        return false;
    }

    public function update(User $user, Note $note): bool
    {
        if ($note->user_id !== $user->id) {
            return false;
        }
        if (!$note->isAccepted()) {
            return true;
        }
        return $note->processed_by === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        return $note->user_id === $user->id && $note->isDraft();
    }

    public function send(User $user, Note $note): bool
    {
        return $note->user_id === $user->id && $note->isDraft();
    }

    public function accept(User $user, Note $note): bool
    {
        return $user->isReportWriter() && $note->isPending();
    }

    public function reject(User $user, Note $note): bool
    {
        return $user->isReportWriter() && $note->isPending();
    }

    public function resend(User $user, Note $note): bool
    {
        return $note->user_id === $user->id && $note->isRejected();
    }

    public function addAttachment(User $user, Note $note): bool
    {
        if ($note->user_id !== $user->id) {
            return false;
        }
        if (!$note->isAccepted()) {
            return true;
        }
        return $note->processed_by === $user->id;
    }

    public function removeAttachment(User $user, Note $note): bool
    {
        if ($note->user_id !== $user->id) {
            return false;
        }
        if (!$note->isAccepted()) {
            return true;
        }
        return $note->processed_by === $user->id;
    }
}
