<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NoteRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Note $note;
    public string $reason;
    public string $processorName;

    public function __construct(Note $note, string $reason, string $processorName)
    {
        $this->note = $note;
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
            'note_id' => $this->note->id,
            'camera_number' => $this->note->camera_number,
            'floor_number' => $this->note->floor_number,
            'reason' => $this->reason,
            'processor_name' => $this->processorName,
            'observed_at' => $this->note->observed_at?->toIso8601String(),
            'message' => "تم رفض ملاحظتك #{$this->note->id} (كاميرا {$this->note->camera_number} - الطابق {$this->note->floor_number}) بواسطة {$this->processorName}",
            'url' => route('notes.show', $this->note->id),
            'type' => 'note_rejected',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
