<?php

namespace App\Jobs;

use App\Models\GeneralSubmission;
use App\Models\User;
use App\Notifications\DispatchSentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanoutDispatchNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $submissionId, public string $senderName, public array $writerIds = [])
    {
    }

    public function handle(): void
    {
        $submission = GeneralSubmission::find($this->submissionId);
        if (! $submission) {
            return;
        }

        $query = User::where('role', 'report_writer')->select(['id', 'name']);
        if (! empty($this->writerIds)) {
            $query->whereIn('id', $this->writerIds);
        }

        $query->chunkById(100, function ($writers) use ($submission) {
            foreach ($writers as $writer) {
                try {
                    $exists = $writer->notifications()
                        ->where('type', DispatchSentNotification::class)
                        ->where(function ($q) use ($submission) {
                            $q->where('data->submission_id', $submission->id)
                                ->orWhere('data->general_submission_id', $submission->id);
                        })
                        ->exists();
                    if (! $exists) {
                        $writer->notify(new DispatchSentNotification($submission, $this->senderName));
                    }
                } catch (\Throwable $e) {
                    Log::warning('[FANOUT] dispatch notify failed', ['user_id' => $writer->id, 'error' => $e->getMessage()]);
                }
            }
        });
    }
}
