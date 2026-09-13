<?php

namespace App\Policies;

use App\Models\GeneralSubmission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GeneralSubmissionPolicy
{
    
    public function viewAny(User $user): bool
    {
        return $user->isMonitor() || $user->isReportWriter();
    }

    
    public function view(User $user, GeneralSubmission $generalSubmission): bool
    {
        
        if ($generalSubmission->user_id === $user->id) {
            return true;
        }

        
        if ($user->isReportWriter()) {
            
            if ($generalSubmission->relationLoaded('reportWriters')) {
                return $generalSubmission->reportWriters->contains('id', $user->id);
            }
            return $generalSubmission->reportWriters()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    
    public function create(User $user): bool
    {
        return $user->isMonitor();
    }

    
    public function update(User $user, GeneralSubmission $generalSubmission): bool
    {
        
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

    
    public function delete(User $user, GeneralSubmission $generalSubmission): bool
    {
        
        return $generalSubmission->user_id === $user->id;
    }

    
    public function restore(User $user, GeneralSubmission $generalSubmission): bool
    {
        return false;
    }

    
    public function forceDelete(User $user, GeneralSubmission $generalSubmission): bool
    {
        return false;
    }
}
