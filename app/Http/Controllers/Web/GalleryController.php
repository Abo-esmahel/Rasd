<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\GeneralSubmissionAttachment;
use App\Models\GeneralSubmission;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $cam = $request->filled('camera_number') && is_numeric($request->camera_number) ? (int) $request->camera_number : null;
        $floor = $request->filled('floor_number') && is_numeric($request->floor_number) ? (int) $request->floor_number : null;
        $type = in_array($request->type, ['image', 'video', 'audio'], true) ? $request->type : null;
        $date = ($request->filled('date') && strtotime($request->date) !== false) ? $request->date : null;
        $sort = $request->input('sort') === 'oldest' ? 'oldest' : 'latest';


        $noteQ = Attachment::query()
            ->select([
                'attachments.id', 'attachments.original_name', 'attachments.mime_type',
                'attachments.file_size', 'attachments.file_path', 'attachments.created_at',
                'attachments.note_id as parent_id', DB::raw("'note' as kind"),
            ])
            ->whereHas('note', function ($q) use ($user, $cam, $floor) {
                $q->whereNull('notes.general_submission_id')
                    ->where(function ($qq) use ($user) {
                        $qq->where('notes.user_id', $user->id)->orWhere('notes.status', '!=', Note::STATUS_DRAFT);
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
            ->whereHas('submission', function ($q) use ($user, $cam, $floor) {
                $q->where(function ($qq) use ($user) {
                    $qq->whereHas('reportWriters', fn ($w) => $w->where('users.id', $user->id))
                        ->orWhere('general_submissions.user_id', $user->id);
                });
                if ($cam !== null) $q->where('general_submissions.camera_number', $cam);
                if ($floor !== null) $q->where('general_submissions.floor_number', $floor);
            });

        foreach ([$noteQ, $subQ] as $q) {
            if ($type !== null) $q->where('mime_type', 'like', $type . '/%');
            if ($date !== null) $q->whereDate('created_at', $date);
        }


        $page = $noteQ->toBase()->unionAll($subQ->toBase())->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc')->paginate(24)->withQueryString();


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
                    'kind' => 'note', 'id' => $r['id'], 'name' => $r['original_name'], 'mime' => $r['mime_type'],
                    'size' => $r['file_size'], 'created' => $created,
                    'viewUrl' => route('notes.attachments.view', $r['id']),
                    'downloadUrl' => route('notes.attachments.download', $r['id']),
                    'parentUrl' => route('notes.show', $p), 'parentKind' => __('ui.parent_note'),
                    'parentRef' => '#' . str_pad($p->id, 4, '0', STR_PAD_LEFT),
                    'camera' => $p->camera_number, 'floor' => $p->floor_number,
                    'ownerName' => $p->owner->name ?? '—',
                ];
            }
            $p = $subs->get($r['parent_id']);
            if (!$p) return null;
            return [
                'kind' => 'submission', 'id' => $r['id'], 'name' => $r['original_name'], 'mime' => $r['mime_type'],
                'size' => $r['file_size'], 'created' => $created,
                'viewUrl' => route('submission-attachments.view', $r['id']),
                'downloadUrl' => route('submission-attachments.download', $r['id']),
                'parentUrl' => route('general-submissions.show', $p), 'parentKind' => __('ui.parent_submission'),
                'parentRef' => '#' . str_pad($p->id, 4, '0', STR_PAD_LEFT),
                'camera' => $p->camera_number, 'floor' => $p->floor_number,
                'ownerName' => $p->owner->name ?? '—',
            ];
        })->filter()->values();
        $page->setCollection($items);


        $filtersKey = 'gallery-filters:v2:'.$user->id;
        $cameras = $floors = [];
        try {
            $cached = \Illuminate\Support\Facades\Cache::get($filtersKey);
            if (is_array($cached) && isset($cached[0], $cached[1]) && is_array($cached[0]) && is_array($cached[1])) {
                $cameras = self::galleryScalarList($cached[0]);
                $floors = self::galleryScalarList($cached[1]);
                if ((count($cached[0]) > 0 && count($cameras) === 0) || (count($cached[1]) > 0 && count($floors) === 0)) {
                    throw new \RuntimeException('gallery filters cache holds no scalars');
                }
            } else {
                if ($cached !== null) \Illuminate\Support\Facades\Cache::forget($filtersKey);
                [$cameras, $floors] = self::buildGalleryFilters($user);
                \Illuminate\Support\Facades\Cache::put($filtersKey, [$cameras, $floors], 300);
            }
        } catch (\Throwable $e) {
            report($e);
            try {
                \Illuminate\Support\Facades\Cache::forget($filtersKey);
                [$cameras, $floors] = self::buildGalleryFilters($user);
            } catch (\Throwable $e2) {
                report($e2);
                $cameras = $floors = [];
            }
        }

        return view('gallery.index', ['attachments' => $page, 'cameras' => $cameras, 'floors' => $floors]);
    }

    private static function buildGalleryFilters($user): array
    {
        $noteBase = Note::query()->whereNull('notes.general_submission_id')
            ->where(fn ($q) => $q->where('notes.user_id', $user->id)->orWhere('notes.status', '!=', Note::STATUS_DRAFT));
        $subBase = GeneralSubmission::query()
            ->where(fn ($q) => $q->whereHas('reportWriters', fn ($w) => $w->where('users.id', $user->id))->orWhere('general_submissions.user_id', $user->id));
        $cameras = $noteBase->clone()->distinct()->orderBy('notes.camera_number')->pluck('notes.camera_number')
            ->merge($subBase->clone()->distinct()->orderBy('general_submissions.camera_number')->pluck('general_submissions.camera_number'))
            ->all();
        $floors = $noteBase->clone()->distinct()->orderBy('notes.floor_number')->pluck('notes.floor_number')
            ->merge($subBase->clone()->distinct()->orderBy('general_submissions.floor_number')->pluck('general_submissions.floor_number'))
            ->all();

        return [self::galleryScalarList($cameras), self::galleryScalarList($floors)];
    }

    private static function galleryScalarList($values): array
    {
        $out = [];
        if (is_iterable($values)) {
            foreach ($values as $v) {
                if (is_scalar($v)) {
                    $s = trim((string) $v);
                    if ($s !== '') $out[] = $s;
                }
            }
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_NATURAL);
        return $out;
    }
}
