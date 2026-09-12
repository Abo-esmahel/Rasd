<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReportPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'title_key' => 'notifications.report_published_title',
            'message_key' => 'notifications.report_published_message',
            'url' => '/reports/' . $this->report->id,
            'type' => 'report_published',
            'category' => 'report',
            'priority' => 'normal',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}