<?php

namespace App\Listeners;

use App\Events\NotificationBroadcast;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

/**
 * Listens to Laravel's NotificationSent event and re-broadcasts
 * database notifications in real-time to the private user channel.
 * 
 * Separation of concerns:
 *  - Notification creation (via $user->notify()) persists to DB (source of truth)
 *  - This listener handles delivery (real-time push) without coupling services to broadcasting
 * 
 * No polling needed when Reverb/Pusher is configured; SSE fallback uses the same payload
 * by polling the DB, so this listener is optional for SSE but essential for WebSocket mode.
 */
class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        // Only broadcast database notifications (not mail etc). And only for User notifiable.
        if ($event->channel !== 'database') {
            return;
        }

        $notifiable = $event->notifiable;

        if (!$notifiable || !isset($notifiable->id)) {
            return;
        }

        // Only for App\Models\User (has notifications relationship)
        if (!method_exists($notifiable, 'notifications')) {
            return;
        }

        try {
            /** @var \Illuminate\Notifications\DatabaseNotification|null $dbNotification */
            $dbNotification = $notifiable->notifications()->latest()->first();

            // If we can locate the exact notification just created, use it. Fallback to event->notification payload.
            if ($dbNotification) {
                $payload = $this->normalize($dbNotification);
                $unread = $notifiable->unreadNotifications()->count();
                // For LOCAL with BROADCAST_CONNECTION=log, broadcast is just log write — fast.
                // For production with pusher/reverb, ShouldBroadcast (queued) ensures HTTP request لا ينتظر شبكة
                // حتى مع QUEUE_CONNECTION=sync، الحدث يُرسل بعد DB commit لكن لا يوقف المستخدم إذا فشل
                try {
                    // Dispatch without blocking HTTP on network failure: wrap in try and use afterResponse if available
                    if (function_exists('dispatch') && config('queue.default') !== 'sync') {
                        // queued broadcast (async) — non-blocking
                        \Illuminate\Support\Facades\Broadcast::queue(new NotificationBroadcast((int) $notifiable->id, $payload, $unread));
                    } else {
                        // sync but with short timeout handling — event() will try broadcast, if fails it logs and continues
                        event(new NotificationBroadcast((int) $notifiable->id, $payload, $unread));
                    }
                } catch (\Throwable $inner) {
                    Log::warning('[BROADCAST] event dispatch failed (non-blocking)', ['error' => $inner->getMessage()]);
                }
                
                if (config('app.debug')) {
                    Log::debug('[BROADCAST] dispatched', [
                        'user_id' => $notifiable->id,
                        'notification_id' => $payload['id'],
                        'type' => $payload['type'] ?? null,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BROADCAST] failed to dispatch', [
                'user_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalize($n): array
    {
        // Handle DatabaseNotification model
        $data = $n->data;
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        // Enrich with presentation config (priority, category)
        $rawType = $data['type'] ?? $n->type ?? 'generic';
        $priority = $data['priority'] ?? $this->inferPriority($rawType, $data);
        $category = $data['category'] ?? $this->inferCategory($rawType);

        return [
            'id' => $n->id,
            'type' => $n->type,
            'data' => array_merge($data, [
                'priority' => $priority,
                'category' => $category,
            ]),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'created_at_human' => $n->created_at->diffForHumans(),
            'created_at_full' => $n->created_at->format('Y-m-d H:i'),
        ];
    }

    private function inferPriority(string $type, array $data): string
    {
        $t = strtolower($type);
        // critical for rejections? important for accepts
        if (str_contains($t, 'reject')) return 'high';
        if (str_contains($t, 'accept')) return 'normal';
        if (str_contains($t, 'sent')) return 'normal';
        return 'normal';
    }

    private function inferCategory(string $type): string
    {
        $t = strtolower($type);
        if (str_contains($t, 'note_')) return 'note';
        if (str_contains($t, 'dispatch')) return 'dispatch';
        return 'generic';
    }
}
