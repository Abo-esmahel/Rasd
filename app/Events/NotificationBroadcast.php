<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time broadcast of a database notification.
 * 
 * Architecture: Business Event -> Notification (DB) -> NotificationBroadcast (Real-Time)
 * 
 * Frontend receives this via:
 *  - Laravel Reverb / Pusher (Echo private channel) when BROADCAST_CONNECTION=reverb/pusher
 *  - SSE fallback (NotificationStreamController polls DB and emits same payload)
 * 
 * Keep single source of truth: DatabaseNotifications table is authoritative.
 */
class NotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public array $notification;
    public int $unreadCount;

    /**
     * @param int $userId Recipient user id
     * @param array $notification Normalized notification array (id, type, data, read_at, created_at, etc)
     * @param int $unreadCount Current unread count (backend truth)
     */
    public function __construct(int $userId, array $notification, int $unreadCount)
    {
        $this->userId = $userId;
        $this->notification = $notification;
        $this->unreadCount = $unreadCount;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'unread_count' => $this->unreadCount,
        ];
    }
}
