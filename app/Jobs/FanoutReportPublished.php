<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportPublishedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanoutReportPublished implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public int $reportId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    public function handle(): void
    {
        $report = Report::find($this->reportId);
        if (! $report || ! $report->isPublished()) {
            return;
        }

        $roles = ['report_writer', 'general_manager'];
        $roles[] = 'monitor';

        User::whereIn('role', $roles)
            ->where('id', '!=', $report->author_id)
            ->select(['id', 'name', 'locale', 'role'])
            ->chunkById(100, function ($users) use ($report) {
            foreach ($users as $m) {
                try {
                    $existing = $m->notifications()->where('type', ReportPublishedNotification::class);
                    if ($m->isMonitor()) {
                        $existing->where('data->monitor_alert', true)
                            ->where('data->monitor_alert_day', $report->report_date?->toDateString() ?? now()->toDateString());
                    } else {
                        $existing->where('data->report_id', $report->id);
                    }
                    $exists = $existing->exists();
                    if (! $exists) {
                        $m->notify(new ReportPublishedNotification($report));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[FANOUT] report notify failed', ['user_id' => $m->id, 'error' => $e->getMessage()]);
                }
            }
        });
    }
}
