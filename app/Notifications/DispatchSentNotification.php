<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Notifications\Notification;

class DispatchSentNotification extends Notification
{
    public GeneralSubmission $submission;
    public string $senderName;

    public function __construct(GeneralSubmission $submission, string $senderName)
    {
        $this->submission = $submission;
        $this->senderName = $senderName;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'submission_id' => $this->submission->id,
            'general_submission_id' => $this->submission->id,
            'type' => 'dispatch_sent',
            'category' => 'dispatch',
            'priority' => 'normal',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
