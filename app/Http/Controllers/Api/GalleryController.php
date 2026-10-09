<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\GeneralSubmissionAttachment;
use App\Models\GeneralSubmission;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cam = $request->filled('camera_number') && is_numeric($request->camera_number) ? (int) $request->camera_number : null;
        $floor = $request->filled('floor_number') && is_numeric($request->floor_number) ? (int) $request->floor_number : null;
        $type = in_array($request->type, ['image', 'video', 'audio'], true) ? $request->type : null;
        $date = ($request->filled('date') && strtotime($request->date) !== false) ? $request->date : null;
        $sort = $request->input('sort') === 'oldest' ? 'oldest' : 'latest';

        $isGM = $user->isGeneralManager();
        $noteQ = Attachment::query()
            ->select([
                'attachments.id', 'attachments.original_name', 'attachments.mime_type',
                'attachments.file_size', 'attachments.file_path', 'attachments.created_at',
                'attachments.note_id as parent_id', DB::raw("'note' as kind"),
            ])
            ->whereHas('note', function ($q) use ($user, $cam, $floor, $isGM) {
                $q->whereNull('notes.general_submission_id');
                $q->where(function ($qq) use ($user, $isGM) {
                    if ($isGM) {
                        $qq->where('notes.status', '!=', Note::STATUS_DRAFT);
                    } else {
                        $qq->where('notes.user_id', $user->id)->orWhere('notes.status', '!=', Note::STATUS_DRAFT);
                    }
                });
                if ($cam !== null) $q->where('notes.camera_number', $cam);
                if ($floor !== null) $q->where('notes.floor_number', $floor);
            });

        $subQ = GeneralSubmissionAttachment::query()
            ->select([
                'general_submission_attachments.id', 'general_submission_attachments.original_name',
                'general_submission_attachments.mime_type', 'general_submission_attachments.file_size',
                'general_submission_attachments.file_path', 'general_submission_attachments.created_at',
                'general_submission_attachments.general_submission_id as parent_id', DB::raw("'submission' as kind"),
            ])
            ->whereHas('submission', function ($q) use ($user, $cam, $floor, $isGM) {
                if (!$isGM) {
                    $q->where(function ($qq) use ($user) {
                        $qq->whereHas('reportWriters', fn ($w) => $w->where('users.id', $user->id))
                            ->orWhere('general_submissions.user_id', $user->id);
                    });
                }
                if ($cam !== null) $q->where('general_submissions.camera_number', $cam);
                if ($floor !== null) $q->where('general_submissions.floor_number', $floor);
            });

        $dayStart = $dayEnd = null;
        if ($date !== null) {
            try {
                $day = \Carbon\Carbon::parse($date)->startOfDay();
                $dayStart = $day->toDateTimeString();
                $dayEnd = $day->copy()->endOfDay()->toDateTimeString();
            } catch (\Throwable) {
                $dayStart = $dayEnd = null;
            }
        }

        if ($type !== null) {
            $noteQ->where('attachments.mime_type', 'like', $type . '/%');
            $subQ->where('general_submission_attachments.mime_type', 'like', $type . '/%');
        }
        if ($dayStart !== null) {
            $noteQ->whereBetween('attachments.created_at', [$dayStart, $dayEnd]);
            $subQ->whereBetween('general_submission_attachments.created_at', [$dayStart, $dayEnd]);
        }

        $perPage = min(50, max(1, (int) $request->query('per_page', 24)));
        $page = $noteQ->toBase()->unionAll($subQ->toBase())->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc')->paginate($perPage)->withQueryString();

        $rows = collect($page->items());
        $noteIds = $rows->where('kind', 'note')->pluck('parent_id')->unique()->all();
        $subIds = $rows->where('kind', 'submission')->pluck('parent_id')->unique()->all();
        $notes = Note::with('owner')->whereIn('id', $noteIds)->get()->keyBy('id');
        $subs = GeneralSubmission::with('owner')->whereIn('id', $subIds)->get()->keyBy('id');

        $items = $rows->map(function ($r) use ($notes, $subs) {
            $r = $r instanceof \Illuminate\Database\Eloquent\Model ? $r->getAttributes() : (array) $r;
            if (!isset($r['created_at'], $r['kind'], $r['parent_id'])) {
                return null;
            }
            $created = \Carbon\Carbon::parse($r['created_at']);
            if ($r['kind'] === 'note') {
                $p = $notes->get($r['parent_id']);
                if (!$p) return null;
                return [
                    'kind' => 'note',
                    'id' => $r['id'],
                    'name' => $r['original_name'],
                    'mime' => $r['mime_type'],
                    'mime_type' => $r['mime_type'],
                    'size' => $r['file_size'],
                    'file_size' => $r['file_size'],
                    'file_path' => $r['file_path'],
                    'original_name' => $r['original_name'],
                    'created_at' => $created->toIso8601String(),
                    'created' => $created->toIso8601String(),
                    'view_url' => '/api/attachments/' . $r['id'] . '/view',
                    'thumb_url' => '/api/attachments/' . $r['id'] . '/view',
                    'download_url' => '/api/attachments/' . $r['id'],
                    'viewUrl' => '/api/attachments/' . $r['id'] . '/view',
                    'thumbUrl' => '/api/attachments/' . $r['id'] . '/view',
                    'downloadUrl' => '/api/attachments/' . $r['id'],
                    'parent_id' => $p->id,
                    'parentUrl' => '/api/notes/' . $p->id,
                    'camera' => $p->camera_number,
                    'camera_number' => $p->camera_number,
                    'floor' => $p->floor_number,
                    'floor_number' => $p->floor_number,
                    'owner_name' => $p->owner->localized_name ?? '—',
                    'owner_id' => $p->user_id,
                ];
            }
            $p = $subs->get($r['parent_id']);
            if (!$p) return null;
            return [
                'kind' => 'submission',
                'id' => $r['id'],
                'name' => $r['original_name'],
                'mime' => $r['mime_type'],
                'mime_type' => $r['mime_type'],
                'size' => $r['file_size'],
                'file_size' => $r['file_size'],
                'file_path' => $r['file_path'],
                'original_name' => $r['original_name'],
                'created_at' => $created->toIso8601String(),
                'created' => $created->toIso8601String(),
                'view_url' => '/api/submission-attachments/' . $r['id'] . '/view',
                'thumb_url' => '/api/submission-attachments/' . $r['id'] . '/view',
                'download_url' => '/api/submission-attachments/' . $r['id'] . '/download',
                'viewUrl' => '/api/submission-attachments/' . $r['id'] . '/view',
                'thumbUrl' => '/api/submission-attachments/' . $r['id'] . '/view',
                'downloadUrl' => '/api/submission-attachments/' . $r['id'] . '/download',
                'parent_id' => $p->id,
                'parentUrl' => '/api/general-submissions/' . $p->id,
                'camera' => $p->camera_number,
                'camera_number' => $p->camera_number,
                'floor' => $p->floor_number,
                'floor_number' => $p->floor_number,
                    'owner_name' => $p->owner->localized_name ?? '—',
                    'owner_id' => $p->user_id,
                ];
        })->filter()->values();

        $page->setCollection($items);

        return response()->json([
            'success' => true,
            'data' => $page,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
