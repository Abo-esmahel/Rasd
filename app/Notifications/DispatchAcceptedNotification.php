<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Notifications\Notification;

class DispatchAcceptedNotification extends Notification
{
    public GeneralSubmission $submission;
    public string $processorName;

    public function __construct(GeneralSubmission $submission, string $processorName)
    {
        $this->submission = $submission;
        $this->processorName = $processorName;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'submission_id' => $this->submission->id,
            'type' => 'dispatch_accepted',
            'category' => 'dispatch',
            'priority' => 'normal',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
