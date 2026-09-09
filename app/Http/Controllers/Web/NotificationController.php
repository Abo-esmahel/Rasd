<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->limit(20)->get()->map(function ($n) {
            return [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->diffForHumans(),
                'created_at_full' => $n->created_at->format('Y-m-d H:i'),
            ];
        });
        $unreadCount = $user->unreadNotifications()->count();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        }

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function unreadCount()
    {
        $count = Auth::user()->unreadNotifications()->count();
        return response()->json(['unread_count' => $count]);
    }

    public function markAsRead(Request $request)
    {
        $user = Auth::user();
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            $user->unreadNotifications->markAsRead();
        } else {
            $user->notifications()->whereIn('id', $ids)->get()->markAsRead();
        }
        return response()->json(['success' => true]);
    }

    public function markOneAsRead(string $id)
    {
        $notification = Auth::user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();
        return response()->json(['success' => true]);
    }
}
