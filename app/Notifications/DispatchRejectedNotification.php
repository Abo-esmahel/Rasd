<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DispatchRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            'camera_number' => $this->submission->camera_number,
            'floor_number' => $this->submission->floor_number,
            'reason' => $this->reason,
            'processor_name' => $this->processorName,
            'observed_at' => $this->submission->observed_at?->toIso8601String(),
            'title_key' => 'notifications.dispatch_rejected_title',
            'message_key' => 'notifications.dispatch_rejected_message',
            'url' => '/general-submissions/'.$this->submission->id,
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