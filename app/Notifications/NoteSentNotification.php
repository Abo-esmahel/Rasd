<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NoteSentNotification extends Notification
{
    use Queueable;

    public Note $note;
    public string $senderName;

    public function __construct(Note $note, string $senderName)
    {
        $this->note = $note;
        $this->senderName = $senderName;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'note_id' => $this->note->id,
            'camera_number' => $this->note->camera_number,
            'floor_number' => $this->note->floor_number,
            'sender_name' => $this->senderName,
            'sender_id' => $this->note->user_id,
            'observed_at' => $this->note->observed_at?->toIso8601String(),
            'message' => "ملاحظة جديدة #{$this->note->id} من {$this->senderName} (كاميرا {$this->note->camera_number} - الطابق {$this->note->floor_number}) بانتظار المراجعة",
            'url' => route('notes.show', $this->note->id),
            'type' => 'note_sent',
            'category' => 'note',
            'priority' => 'normal',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
