<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ReportPublishedNotification;
use App\Services\Localization\LocalizedPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, LocalizedPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));
        $filter = $request->query('filter');

        $query = $this->visibleNotifications($user)->latest();
        if ($filter === 'unread') $query->whereNull('read_at');
        if ($filter === 'read') $query->whereNotNull('read_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $unreadCount = $this->visibleNotifications($user)->whereNull('read_at')->count();

        $normalized = $paginator->getCollection()->map(fn($n) => $this->normalize($n, $presenter))->values();
        $paginator->setCollection($normalized);

        return response()->json([
            'success' => true,
            'data' => $paginator,
            'unread_count' => $unreadCount,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->visibleNotifications($user)->whereNull('read_at')->count();
        return response()->json([
            'success' => true,
            'data' => ['count' => $count, 'unread_count' => $count],
            'count' => $count,
            'unread_count' => $count,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function feed(Request $request, LocalizedPresenter $presenter): JsonResponse
    {
        $user = $request->user();
        $after = $request->query('after');
        $lastId = $request->query('last_id');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $unreadOnly = $request->boolean('unread_only');

        $cursor = null;
        if ($after) {
            try { $cursor = \Carbon\Carbon::parse($after); } catch (\Throwable $e) { $cursor = null; }
        }
        if ($lastId && !$cursor) {
            $ref = $user->notifications()->where('id', $lastId)->first();
            if ($ref) $cursor = $ref->created_at;
        }

        $query = $this->visibleNotifications($user)->latest();
        if ($cursor) {
            $query->where('created_at', '>', $cursor);
        }
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit($limit)->get()->map(fn($n) => $this->normalize($n, $presenter))->values();
        $unreadCount = $this->visibleNotifications($user)->whereNull('read_at')->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function markAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'ids' => ['sometimes', 'array', 'max:100'],
            'ids.*' => ['string', 'max:50'],
        ]);
        $ids = $validated['ids'] ?? [];
        if (empty($ids)) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        } else {
            $user->notifications()->whereIn('id', $ids)->whereNull('read_at')->update(['read_at' => now()]);
        }
        $unread = $this->visibleNotifications($user)->whereNull('read_at')->count();
        return response()->json(['success' => true, 'unread_count' => $unread, 'data' => ['unread_count' => $unread]]);
    }

    public function markOneAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();
        $unread = $this->visibleNotifications($user)->whereNull('read_at')->count();
        return response()->json(['success' => true, 'unread_count' => $unread, 'data' => ['unread_count' => $unread]]);
    }

    private function normalize($n, ?LocalizedPresenter $presenter = null): array
    {
        $data = $n->data;
        if (is_string($data)) $data = json_decode($data, true) ?: [];
        $rawType = $data['type'] ?? $n->type ?? 'generic';
        $safeData = $presenter?->safeNotificationData($data) ?? [
            'type' => $rawType,
            'category' => $this->inferCategory($rawType),
            'priority' => $this->inferPriority($rawType),
            'title' => __('notifications.generic_title'),
            'message' => '',
            'body' => '',
        ];
        $priority = $safeData['priority'];
        $category = $safeData['category'];
        return [
            'id' => $n->id,
            'type' => 'generic',
            'data' => $safeData,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'created_at_human' => $n->created_at->diffForHumans(),
            'created_at_full' => $n->created_at->format('Y-m-d H:i'),
            'title' => $safeData['title'],
            'body' => '',
            'message' => '',
            'priority' => $priority,
            'category' => $category,
        ];
    }

    private function visibleNotifications(User $user)
    {
        $query = $user->notifications();
        if ($user->isMonitor()) {
            $query->where(function ($q) {
                $q->where(function ($other) {
                    $other->where('type', '!=', ReportPublishedNotification::class)
                        ->where(function ($category) {
                            $category->whereNull('data->category')
                                ->orWhere('data->category', '!=', 'report');
                        });
                })->orWhere(function ($alert) {
                    $alert->where('type', ReportPublishedNotification::class)
                        ->where('data->monitor_alert', true);
                });
            });
        }
        return $query;
    }

    private function inferPriority(string $type): string
    {
        $t = strtolower($type);
        if (str_contains($t, 'reject')) return 'high';
        if (str_contains($t, 'accept')) return 'normal';
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
