<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\Localization\LocalizedPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request, LocalizedPresenter $presenter)
    {
        $user = Auth::user();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $filter = $request->query('filter');

        $query = $user->notifications()->latest();
        if ($filter === 'unread') $query->whereNull('read_at');
        if ($filter === 'read') $query->whereNotNull('read_at');

        $paginator = $query->paginate($perPage);
        $notifications = $paginator->getCollection()->map(fn($n) => $this->normalize($n, $presenter))->values();
        $unreadCount = $user->unreadNotifications()->count();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'server_time' => now()->toIso8601String(),
            ]);
        }

        $viewNotifications = $paginator->getCollection()->map(function ($n) use ($presenter) {
            $data = is_string($n->data) ? json_decode($n->data, true) : (array) $n->data;
            $localized = $presenter->notification($data);
            $data = array_merge($data, ['title' => $localized['title'], 'message' => $localized['message']]);
            return (object)[
                'id' => $n->id,
                'type' => $n->type,
                'data' => $data,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        return view('notifications.index', [
            'notifications' => $viewNotifications,
            'unreadCount' => $unreadCount,
            'paginator' => $paginator,
        ]);
    }

    public function unreadCount()
    {
        $count = Auth::user()->unreadNotifications()->count();
        return response()->json(['unread_count' => $count, 'server_time' => now()->toIso8601String()]);
    }

    public function markAsRead(Request $request)
    {
        $user = Auth::user();
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
        $unread = $user->unreadNotifications()->count();
        return response()->json(['success' => true, 'unread_count' => $unread]);
    }

    public function markOneAsRead(string $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();
        $unread = Auth::user()->unreadNotifications()->count();
        return response()->json(['success' => true, 'unread_count' => $unread]);
    }

    public function preferences(Request $request)
    {
        $pref = NotificationPreference::forUser(Auth::id());
        if (! $request->expectsJson() && ! $request->ajax() && ! $request->wantsJson()) {
            return redirect()->route('notifications.index');
        }
        return response()->json($pref);
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'sound_enabled' => ['sometimes', 'boolean'],
            'desktop_enabled' => ['sometimes', 'boolean'],
            'toast_enabled' => ['sometimes', 'boolean'],
            'volume' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'sound_theme' => ['sometimes', 'string', 'in:default,subtle,urgent'],
            'muted_types' => ['sometimes', 'array', 'max:50'],
            'muted_types.*' => ['string', 'max:100'],
        ]);

        $pref = NotificationPreference::forUser(Auth::id());
        $pref->update($validated);

        return response()->json(['success' => true, 'preferences' => $pref->fresh()]);
    }

    private function normalize($n, ?\App\Services\Localization\LocalizedPresenter $presenter = null): array
    {
        $data = $n->data;
        if (is_string($data)) $data = json_decode($data, true) ?: [];
        $rawType = $data['type'] ?? $n->type ?? 'generic';
        $priority = $data['priority'] ?? $this->inferPriority($rawType);
        $category = $data['category'] ?? $this->inferCategory($rawType);
        try {
            if ($presenter) {
                $localized = $presenter->notification($data);
                $data['title'] = $localized['title'];
                $data['message'] = $localized['message'];
            }
        } catch (\Throwable) {}
        return [
            'id' => $n->id,
            'type' => $n->type,
            'data' => array_merge($data, ['priority' => $priority, 'category' => $category]),
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'created_at_human' => $n->created_at->diffForHumans(),
            'created_at_full' => $n->created_at->format('Y-m-d H:i'),
        ];
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
