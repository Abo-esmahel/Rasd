<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportPublishedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanoutReportPublished implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $reportId)
    {
    }

    public function handle(): void
    {
        $report = Report::find($this->reportId);
        if (! $report || ! $report->visible_to_monitors) {
            return;
        }

        User::where('role', 'monitor')->select(['id', 'name'])->chunkById(100, function ($monitors) use ($report) {
            foreach ($monitors as $m) {
                try {
                    $exists = $m->notifications()
                        ->where('type', ReportPublishedNotification::class)
                        ->where('data->report_id', $report->id)
                        ->exists();
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
