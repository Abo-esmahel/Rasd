<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Notifications\Notification;

class NoteAcceptedNotification extends Notification
{
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
