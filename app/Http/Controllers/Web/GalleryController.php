<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Attachment::with(['note.owner'])
            ->whereHas('note', function ($q) use ($user) {
                $q->whereNull('notes.general_submission_id')
                    ->where(function ($qq) use ($user) {
                        $qq->where('notes.user_id', $user->id)
                            ->orWhere('notes.status', '!=', Note::STATUS_DRAFT);
                    });
            });

        if ($request->filled('camera_number') && is_numeric($request->camera_number)) {
            $cam = (int) $request->camera_number;
            $query->whereHas('note', fn ($q) => $q->where('notes.camera_number', $cam));
        }
        if ($request->filled('floor_number') && is_numeric($request->floor_number)) {
            $fl = (int) $request->floor_number;
            $query->whereHas('note', fn ($q) => $q->where('notes.floor_number', $fl));
        }
        if ($request->filled('type') && in_array($request->type, ['image', 'video', 'audio'], true)) {
            $query->where('attachments.mime_type', 'like', $request->type . '/%');
        }
        if ($request->filled('observer') && is_numeric($request->observer)) {
            $obsId = (int) $request->observer;
            $query->whereHas('note', fn ($q) => $q->where('notes.user_id', $obsId));
        }
        if ($request->filled('date') && strtotime($request->date) !== false) {
            $query->whereDate('attachments.created_at', $request->date);
        }

        if ($request->input('sort') === 'oldest') {
            $query->orderBy('attachments.created_at');
        } else {
            $query->orderByDesc('attachments.created_at');
        }
        $attachments = $query->paginate(24)->withQueryString();

        $base = Note::query()->whereNull('notes.general_submission_id')
            ->where(function ($q) use ($user) {
                $q->where('notes.user_id', $user->id)->orWhere('notes.status', '!=', Note::STATUS_DRAFT);
            });
        $cameras = (clone $base)->distinct()->orderBy('notes.camera_number')->pluck('notes.camera_number');
        $floors = (clone $base)->distinct()->orderBy('notes.floor_number')->pluck('notes.floor_number');

        // المراقبون الذين لديهم مرفقات ظاهرة فعلًا (نفس سكوب الرؤية أعلاه)
        $visibleUserIds = (clone $base)->distinct()->pluck('notes.user_id');
        $observers = User::whereIn('id', $visibleUserIds)->orderBy('name')->get(['id', 'name']);
        if ($observers->isEmpty()) {
            $observers = User::where('role', 'monitor')->orderBy('name')->get(['id', 'name']);
        }

        return view('gallery.index', compact('attachments', 'cameras', 'floors', 'observers'));
    }
}
