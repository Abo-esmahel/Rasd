<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NoteAcceptedNotification extends Notification
{
    use Queueable;

    public Note $note;
    public string $processorName;

    public function __construct(Note $note, string $processorName)
    {
        $this->note = $note;
        $this->processorName = $processorName;
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
            'processor_name' => $this->processorName,
            'observed_at' => $this->note->observed_at?->toIso8601String(),
            'message' => "تم قبول ملاحظتك #{$this->note->id} (كاميرا {$this->note->camera_number} - الطابق {$this->note->floor_number}) بواسطة {$this->processorName}",
            'url' => '/notes/'.$this->note->id,
            'type' => 'note_accepted',
            'category' => 'note',
            'priority' => 'normal',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
