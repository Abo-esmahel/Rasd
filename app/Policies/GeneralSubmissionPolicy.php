<?php

namespace App\Policies;

use App\Models\GeneralSubmission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GeneralSubmissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isMonitor();
    }

    /**
     * Determine whether the user can view the model.
     * الإرسالية خاصة: المرسل فقط + الكتّاب المحددون فقط
     */
    public function view(User $user, GeneralSubmission $generalSubmission): bool
    {
        // المرسل (المالك) يرى إرساليته دائماً
        if ($generalSubmission->user_id === $user->id) {
            return true;
        }

        // كاتب التقرير يرى الإرساليات الموجهة إليه فقط (تم تحميل العلاقة أو فحص مباشر)
        if ($user->isReportWriter()) {
            // استخدم exists لتجنب مشاكل عدم تحميل العلاقة
            if ($generalSubmission->relationLoaded('reportWriters')) {
                return $generalSubmission->reportWriters->contains('id', $user->id);
            }
            return $generalSubmission->reportWriters()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isMonitor();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GeneralSubmission $generalSubmission): bool
    {
        // فقط المالك في حالة المسودة يمكنه التعديل
        return $generalSubmission->user_id === $user->id && $generalSubmission->isDraft();
    }

    public function submit(User $user, GeneralSubmission $generalSubmission): bool
    {
        return $generalSubmission->user_id === $user->id && $generalSubmission->isDraft();
    }

    public function accept(User $user, GeneralSubmission $generalSubmission): bool
    {
        if (!$user->isReportWriter() || !$generalSubmission->isPending()) {
            return false;
        }
        if ($generalSubmission->relationLoaded('reportWriters')) {
            return $generalSubmission->reportWriters->contains('id', $user->id);
        }
        return $generalSubmission->reportWriters()->where('users.id', $user->id)->exists();
    }

    public function reject(User $user, GeneralSubmission $generalSubmission): bool
    {
        if (!$user->isReportWriter() || !$generalSubmission->isPending()) {
            return false;
        }
        if ($generalSubmission->relationLoaded('reportWriters')) {
            return $generalSubmission->reportWriters->contains('id', $user->id);
        }
        return $generalSubmission->reportWriters()->where('users.id', $user->id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GeneralSubmission $generalSubmission): bool
    {
        // المالك فقط يمكنه الحذف (وهو monitor)
        return $generalSubmission->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GeneralSubmission $generalSubmission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GeneralSubmission $generalSubmission): bool
    {
        return false;
    }
}
