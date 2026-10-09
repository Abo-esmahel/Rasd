<?php

namespace App\Notifications;

use App\Models\Note;
use Illuminate\Notifications\Notification;

class NoteSentNotification extends Notification
{
    public Note $note;

    public function __construct(Note $note)
    {
        $this->note = $note;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'note_id' => $this->note->id,
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
