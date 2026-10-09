<?php

namespace App\Jobs;

use App\Models\ReportComment;
use App\Models\User;
use App\Notifications\CommentCreatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanoutCommentNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $commentId,
        public int $senderId,
        public string $senderName,
    ) {
    }

    public function handle(): void
    {
        $comment = ReportComment::with(['report', 'parent.user'])->find($this->commentId);
        if (! $comment) {
            return;
        }

        $recipientIds = [];

        if ($comment->parent_id && $comment->parent) {
            $recipientIds[(int) $comment->parent->user_id] = true;
        }
        if ($comment->report) {
            $recipientIds[(int) $comment->report->author_id] = true;
        }

        $participants = ReportComment::where('report_id', $comment->report_id)
            ->where('id', '!=', $comment->id)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        foreach ($participants as $id) {
            $recipientIds[$id] = true;
        }

        unset($recipientIds[$this->senderId]);
        $recipientIds = array_keys($recipientIds);

        if ($recipientIds === []) {
            return;
        }

        User::whereIn('id', $recipientIds)
            ->select(['id', 'name', 'locale', 'role'])
            ->chunkById(100, function ($recipients) use ($comment) {
                foreach ($recipients as $recipient) {
                    try {
                        $exists = $recipient->notifications()
                            ->where('type', CommentCreatedNotification::class)
                            ->where('data->comment_id', $comment->id)
                            ->exists();
                        if (! $exists) {
                            $recipient->notify(new CommentCreatedNotification($comment, $this->senderName));
                            $loc = in_array($recipient->locale ?? null, ['ar', 'en'], true) ? $recipient->locale : 'ar';
                            $titleKey = $comment->parent_id ? 'notifications.reply_created_title' : 'notifications.comment_created_title';
                            $params = ['name' => $this->senderName, 'id' => $comment->report_id];
                            SendPushToUser::dispatch($recipient->id, [
                                'title' => __($titleKey, $params, $loc),
                                'body' => __(
                                    preg_replace('/_title$/', '_message', $titleKey),
                                    $params,
                                    $loc
                                ),
                                'url' => '/reports/'.$comment->report_id.'#report-discussion',
                                'tag' => 'rasd-comment-'.$comment->id,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[FANOUT] comment notify failed', ['user_id' => $recipient->id, 'error' => $e->getMessage()]);
                    }
                }
            });
    }
}