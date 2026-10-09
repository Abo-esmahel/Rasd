<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Notifications\Notification;

class ReportPublishedNotification extends Notification
{
    public function __construct(public Report $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $data = [
            'type' => 'report_published',
            'category' => 'report',
            'priority' => 'normal',
        ];

        if ($notifiable instanceof \App\Models\User && $notifiable->isMonitor()) {
            $data['monitor_alert'] = true;
            $data['monitor_alert_day'] = $this->report->report_date?->toDateString() ?? now()->toDateString();
            $data['category'] = 'generic';

            return $data;
        }

        return $data + [
            'report_id' => $this->report->id,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
