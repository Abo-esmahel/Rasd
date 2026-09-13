<?php

namespace App\Jobs;

use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteSentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanoutNoteNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $noteId,
        public int $senderId,
        public string $senderName,
        public bool $writersOnly = false,
    ) {
    }

    public function handle(): void
    {
        $note = Note::find($this->noteId);
        if (! $note) {
            return;
        }

        $query = User::where('id', '!=', $this->senderId);
        if ($this->writersOnly) {
            $query->where('role', 'report_writer');
        }
        $query->select(['id', 'name', 'locale'])
            ->chunkById(100, function ($recipients) use ($note) {
                foreach ($recipients as $recipient) {
                    try {
                        $exists = $recipient->notifications()
                            ->where('type', NoteSentNotification::class)
                            ->where('data->note_id', $note->id)
                            ->exists();
                        if (! $exists) {
                            $recipient->notify(new NoteSentNotification($note, $this->senderName));
                        }

                        $loc = in_array($recipient->locale ?? null, ['ar', 'en'], true) ? $recipient->locale : 'ar';
                        SendPushToUser::dispatch($recipient->id, [
                            'title' => __('ui.kind_note', [], $loc).' #'.$note->id,
                            'body' => __('ui.camera', [], $loc).' '.$note->camera_number,
                            'url' => '/notes/'.$note->id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('[FANOUT] note notify failed', ['user_id' => $recipient->id, 'error' => $e->getMessage()]);
                    }
                }
            });
    }
}
