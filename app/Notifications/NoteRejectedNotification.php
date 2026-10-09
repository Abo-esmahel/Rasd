<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Notifications\Notification;

class NoteRejectedNotification extends Notification
{
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
            'type' => 'note_rejected',
            'category' => 'note',
            'priority' => 'high',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
