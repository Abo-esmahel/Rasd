<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Notifications\Notification;

class DispatchRejectedNotification extends Notification
{
    public GeneralSubmission $submission;
    public string $reason;
    public string $processorName;

    public function __construct(GeneralSubmission $submission, string $reason, string $processorName)
    {
        $this->submission = $submission;
        $this->reason = $reason;
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
            'type' => 'dispatch_rejected',
            'category' => 'dispatch',
            'priority' => 'high',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
