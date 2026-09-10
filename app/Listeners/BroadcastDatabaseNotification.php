<?php

namespace App\Listeners;

use App\Events\NotificationBroadcast;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        
        if ($event->channel !== 'database') {
            return;
        }

        $notifiable = $event->notifiable;

        if (!$notifiable || !isset($notifiable->id)) {
            return;
        }

        
        if (!method_exists($notifiable, 'notifications')) {
            return;
        }

        try {
            
            $dbNotification = $notifiable->notifications()->latest()->first();

            
            if ($dbNotification) {
                $payload = $this->normalize($dbNotification);
                $unread = $notifiable->unreadNotifications()->count();
                
                
                
                try {
                    
                    if (function_exists('dispatch') && config('queue.default') !== 'sync') {
                        
                        \Illuminate\Support\Facades\Broadcast::queue(new NotificationBroadcast((int) $notifiable->id, $payload, $unread));
                    } else {
                        
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
        
        $data = $n->data;
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        
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
