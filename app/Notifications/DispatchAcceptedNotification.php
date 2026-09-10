<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DispatchAcceptedNotification extends Notification
{
    use Queueable;

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
            'general_submission_id' => $this->submission->id,
            'camera_number' => $this->submission->camera_number,
            'floor_number' => $this->submission->floor_number,
            'processor_name' => $this->processorName,
            'observed_at' => $this->submission->observed_at?->toIso8601String(),
            'message' => "تم قبول إرساليتك #{$this->submission->id} (كاميرا {$this->submission->camera_number} - الطابق {$this->submission->floor_number}) بواسطة {$this->processorName}",
            'url' => '/general-submissions/'.$this->submission->id,
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
