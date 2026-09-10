<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DispatchRejectedNotification extends Notification
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
            'general_submission_id' => $this->submission->id,
            'camera_number' => $this->submission->camera_number,
            'floor_number' => $this->submission->floor_number,
            'reason' => $this->reason,
            'processor_name' => $this->processorName,
            'observed_at' => $this->submission->observed_at?->toIso8601String(),
            'title' => 'مرفوضة · كاميرا '.$this->submission->camera_number,
            'message' => "رفضها {$this->processorName} — {$this->reason}",
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
