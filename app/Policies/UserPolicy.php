<?php

namespace App\Policies;

use App\Models\User;

/**
 * إدارة المستخدمين — للمدير العام وحده.
 *
 * قيود مقصودة: المدير العام لا يحظر نفسه ولا يغيّر دوره، ولا يدير مديراً
 * عاملا آخر. لولا ذلك لأمكن خفض دوره إلى مراقب فيفقد النظام آخر مدير.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGeneralManager() && ! $user->isBanned();
    }

    public function create(User $user): bool
    {
        return $user->isGeneralManager() && ! $user->isBanned();
    }

    /** GM لا يدير نفسه: لا تعديل ولا حظر (انظر manageable). */
    public function update(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    public function ban(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    public function unban(User $user, User $target): bool
    {
        return $this->manageable($user, $target);
    }

    /**
     * قاعدة واحدة تجمع شروط التحديث والحظر:
     * GM حيّ، والهدف ليس GM (ولا هو نفسه).
     */
    private function manageable(User $user, User $target): bool
    {
        return $user->isGeneralManager()
            && ! $user->isBanned()
            && (int) $target->id !== (int) $user->id
            && ! $target->isGeneralManager();
    }
}
