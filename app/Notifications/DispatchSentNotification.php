<?php

namespace App\Notifications;

use App\Models\GeneralSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DispatchSentNotification extends Notification
{
    use Queueable;

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
            'camera_number' => $this->submission->camera_number,
            'floor_number' => $this->submission->floor_number,
            'sender_name' => $this->senderName,
            'sender_id' => $this->submission->user_id,
            'observed_at' => $this->submission->observed_at?->toIso8601String(),
            'message' => "تم إرسال إرسالية جديدة #{$this->submission->id} إليك من {$this->senderName} (كاميرا {$this->submission->camera_number} - الطابق {$this->submission->floor_number})",
            'url' => '/general-submissions/'.$this->submission->id,
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
