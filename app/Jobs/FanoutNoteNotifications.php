<?php

namespace App\Jobs;

use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteSentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanoutNoteNotifications implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public int $noteId,
        public int $senderId,
        public string $senderName,
        public bool $writersOnly = false,
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->noteId;
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
                            $recipient->notify(new NoteSentNotification($note));
                            $loc = in_array($recipient->locale ?? null, ['ar', 'en'], true) ? $recipient->locale : 'ar';
                            SendPushToUser::dispatch($recipient->id, [
                                'title' => __('notifications.note_sent_title', ['id' => $note->id], $loc),
                                'body' => __('notifications.note_sent_message', ['id' => $note->id], $loc),
                                'url' => '/notes/'.$note->id,
                                'tag' => 'rasd-note-'.$note->id,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[FANOUT] note notify failed', ['user_id' => $recipient->id, 'error' => $e->getMessage()]);
                    }
                }
            });
    }
}
