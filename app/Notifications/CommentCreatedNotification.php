<?php

namespace App\Notifications;

use App\Models\ReportComment;
use Illuminate\Notifications\Notification;

class CommentCreatedNotification extends Notification
{
    public function __construct(
        public ReportComment $comment,
        public string $senderName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->comment->parent_id ? 'reply_created' : 'comment_created',
            'category' => 'report',
            'priority' => 'normal',
            'report_id' => $this->comment->report_id,
            'comment_id' => $this->comment->id,
            'parent_id' => $this->comment->parent_id,
            'sender_name' => $this->senderName,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}