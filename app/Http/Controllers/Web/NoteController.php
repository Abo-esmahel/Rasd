<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\AttachmentUploadException;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\AttachmentStorageService;
use App\Services\Localization\LocalizedPresenter;
use App\Services\NoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NoteController extends Controller
{
    use AuthorizesRequests;

    private NoteService $noteService;
    private AttachmentStorageService $storage;

    public function __construct(NoteService $noteService, AttachmentStorageService $storage)
    {
        $this->noteService = $noteService;
        $this->storage = $storage;
    }

    public function index(Request $request, LocalizedPresenter $presenter)
    {
        $user = $request->user();
        $this->authorize('viewAny', Note::class);

        $query = $this->noteService->getVisibleNotesQuery($user);

        if ($request->filled('status') && in_array($request->status, ['draft','pending','accepted','rejected'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date') && strtotime($request->date) !== false) {
            $query->whereDate('observed_at', $request->date);
        }
        if ($request->filled('floor_number') && is_numeric($request->floor_number)) {
            $query->where('floor_number', (int) $request->floor_number);
        }
        if ($request->filled('camera_number') && is_numeric($request->camera_number)) {
            $query->where('camera_number', (int) $request->camera_number);
        }
        if ($request->filled('observer') && is_numeric($request->observer)) {
            $query->where('user_id', (int) $request->observer);
        }
        if ($request->filled('sort') && $request->sort === 'observer') {
            $query->join('users', 'users.id', '=', 'notes.user_id')->orderBy('users.name')->select('notes.*');
        } else {
            $query->orderByDesc('created_at');
        }


        if ($request->boolean('live')) {
            return response()->json($this->notesSignature($query));
        }

        $notes = $query->paginate(15)->withQueryString();

        // Preload عرضي واحد (محفوظ فقط — ZERO Gemini على Language Switch).
        try {
            $presenter->preloadNotes($notes->items());
        } catch (\Throwable) {
        }

        // كاش ساعة — كانت تُجلب في كل فتح لصفحة الملاحظات.
        // arrays فقط: مخزن الكاش (database) يعيد الأجسام ناقصة (serializable_classes=false).
        $observers = \Illuminate\Support\Facades\Cache::remember('observers_list', 3600, fn () => User::where('role', 'monitor')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all());

        $observerUser = null;
        if ($request->filled('observer')) {
            $observerUser = User::select(['id', 'name'])->find($request->observer);
        }

        return view('notes.index', compact('notes', 'observers', 'observerUser'));
    }

    public function myNotes(Request $request, LocalizedPresenter $presenter)
    {
        $user = $request->user();
        $query = Note::with(['owner:id,name,avatar_path', 'processor:id,name'])->withCount('attachments')->where('user_id', $user->id)->whereNull('notes.general_submission_id');

        if ($request->filled('status') && in_array($request->status, ['draft','pending','accepted','rejected'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date') && strtotime($request->date) !== false) {
            $query->whereDate('observed_at', $request->date);
        }
        if ($request->filled('floor_number') && is_numeric($request->floor_number)) {
            $query->where('floor_number', (int) $request->floor_number);
        }
        if ($request->filled('camera_number') && is_numeric($request->camera_number)) {
            $query->where('camera_number', (int) $request->camera_number);
        }


        if ($request->boolean('live')) {
            return response()->json($this->notesSignature($query));
        }

        $notes = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        try {
            $presenter->preloadNotes($notes->items());
        } catch (\Throwable) {
        }

        return view('notes.my', compact('notes'));
    }

    public function create()
    {
        $this->authorize('create', Note::class);
        return view('notes.create');
    }

    public function store(Request $request)
    {
        $reqId = (string) Str::uuid();
        if ($overflow = $this->postOverflowResponse($request)) {
            return $overflow;
        }
        $this->authorize('create', Note::class);

        $maxFiles = max(1, (int) ini_get('max_file_uploads') ?: 20);
        // مصدر واحد للحد — كان محسوباً يدوياً هنا ومختلفاً في مسار الإرساليات (500MB).
        $fileMaxKb = \App\Services\NoteService::uploadFileMaxKb();
        $validated = $request->validate([
            'floor_number' => ['required','integer','min:0'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            // عبور منتصف الليل والمدى الزمني يعالجهما NoteService::normalizeObservedRange بذكاء.
            'observed_end_at' => ['nullable','date'],
            'description' => ['required','string','min:10','max:5000'],
            'files' => ['nullable','array','max:'.$maxFiles],
            'files.*' => ['file','max:'.$fileMaxKb,'mimes:jpg,jpeg,png,webp,heic,heif,tiff,tif,bmp,avif,gif,svg,mp4,webm,mov,avi,3gp,3gpp,mkv,m4v,mpg,mpeg,wmv,flv,ogv,ts,mts,m2ts,vob,asf,m2v,3g2,f4v,m4p,mp3,wav,ogg,oga,m4a,aac,wma,flac,opus,aiff,aif,amr,3ga,awb,mid,midi,au,ra,weba'],
            'client_files_count' => ['nullable','integer','min:0','max:100'],
        ], [
            'files.max' => __('api.files_max'),
            'files.*.max' => __('api.file_max'),
        ], [
            'floor_number' => __('validation.attributes.floor_number'),
            'camera_number' => __('validation.attributes.camera_number'),
            'observed_at' => __('validation.attributes.observed_at'),
            'observed_end_at' => __('validation.attributes.observed_end_at'),
            'description' => __('validation.attributes.description'),
        ]);

        $rawFiles = $request->file('files');
        $receivedFiles = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : ($rawFiles ? [$rawFiles] : []);
        $clientFilesCount = max(0, (int) $request->input('client_files_count', 0));

        
        foreach ($receivedFiles as $f) {
            if (!$f->isValid()) {
                $msg = $this->uploadErrorMessage($f->getError(), $f->getClientOriginalName());
                Log::warning('[ATTACHMENT] invalid upload in store', ['request_id' => $reqId, 'error' => $msg]);
                if ($this->wantsJsonResponse($request)) {
                    return response()->json([
                        'success' => false,
                        'note_id' => null,
                        'files_received' => count($receivedFiles),
                        'attachments_saved' => 0,
                        'attachment_errors' => [$msg],
                    ], 422);
                }

                return redirect()->back()->withInput()->withErrors(['files' => $msg]);
            }
        }

        try {
            $result = $this->noteService->createNoteWithAttachments(
                $request->user(),
                $validated,
                $receivedFiles,
                $clientFilesCount
            );
        } catch (AttachmentUploadException $e) {
            Log::error('[ATTACHMENT] note creation failed (attachment)', [
                'request_id' => $reqId,
                'stage' => $e->stage,
                'files_received' => $e->filesReceived,
                'message' => $e->getMessage(),
            ]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(array_merge(
                    $e->toResponseArray(null),
                    ['message' => __('api.note_attach_batch_failed')]
                ), 422);
            }

            return redirect()->back()->withInput()->withErrors([
                'files' => __('api.note_attach_batch_failed').': '.implode(' | ', $this->flattenErrors($e->attachmentErrors)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Note creation failed', ['request_id' => $reqId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(['success' => false, 'note_id' => null, 'files_received' => count($receivedFiles), 'attachments_saved' => 0, 'attachment_errors' => [__('api.note_create_failed', ['error' => $e->getMessage()])]], 500);
            }

            return redirect()->back()->withInput()->withErrors(['general' => __('api.note_create_failed', ['error' => $e->getMessage()])]);
        }

        $note = $result['note'];
        $filesReceived = $result['files_received'];
        $attachmentsSaved = $result['attachments_saved'];

        if ($request->input('action') === 'send') {
            try {
                $this->noteService->sendNote($request->user(), $note);
                $note = $note->fresh(['owner', 'attachments']);
            } catch (\Throwable $e) {
                Log::warning('[NOTE] auto-send after create failed', ['note_id' => $note->id, 'message' => $e->getMessage()]);
            }
        }

        if ($this->wantsJsonResponse($request)) {
            return response()->json([
                'success' => true,
                'note_id' => $note->id,
                'files_received' => $filesReceived,
                'attachments_saved' => $attachmentsSaved,
                'data' => $note->load(['owner', 'attachments']),
                'attachment_errors' => [],
            ], 201);
        }

        return redirect()->route('notes.index')->with('success', __('api.note_web_created'));
    }

    public function show(Note $note, LocalizedPresenter $presenter)
    {
        $this->authorize('view', $note);
        $note->load(['owner','processor','attachments']);
        try {
            $presenter->preloadNotes([$note]);
        } catch (\Throwable) {
        }
        return view('notes.show', compact('note'));
    }

    public function edit(Note $note)
    {
        $this->authorize('update', $note);
        $note->load(['attachments']);
        return view('notes.edit', compact('note'));
    }

    public function update(Request $request, Note $note)
    {
        $this->authorize('update', $note);

        if ($overflow = $this->postOverflowResponse($request)) {
            return $overflow;
        }

        $maxFiles = max(1, (int) ini_get('max_file_uploads') ?: 20);
        $fileMaxKb = \App\Services\NoteService::uploadFileMaxKb();
        $validated = $request->validate([
            'floor_number' => ['required','integer','min:0'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            // عبور منتصف الليل والمدى الزمني يعالجهما NoteService::normalizeObservedRange بذكاء.
            'observed_end_at' => ['nullable','date'],
            'description' => ['required','string','min:10','max:5000'],
            'files' => ['nullable','array','max:'.$maxFiles],
            'files.*' => ['file','max:'.$fileMaxKb,'mimes:jpg,jpeg,png,webp,heic,heif,tiff,tif,bmp,avif,gif,svg,mp4,webm,mov,avi,3gp,3gpp,mkv,m4v,mpg,mpeg,wmv,flv,ogv,ts,mts,m2ts,vob,asf,m2v,3g2,f4v,m4p,mp3,wav,ogg,oga,m4a,aac,wma,flac,opus,aiff,aif,amr,3ga,awb,mid,midi,au,ra,weba'],
            'client_files_count' => ['nullable','integer','min:0','max:100'],
        ], [
            'files.max' => __('api.files_max'),
            'files.*.max' => __('api.file_max'),
        ]);

        $rawFiles = $request->file('files');
        $receivedFiles = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : ($rawFiles ? [$rawFiles] : []);
        $clientFilesCount = max(0, (int) $request->input('client_files_count', 0));

        foreach ($receivedFiles as $f) {
            if (!$f->isValid()) {
                $msg = $this->uploadErrorMessage($f->getError(), $f->getClientOriginalName());
                if ($this->wantsJsonResponse($request)) {
                    return response()->json([
                        'success' => false,
                        'note_id' => $note->id,
                        'files_received' => count($receivedFiles),
                        'attachments_saved' => $note->attachments()->count(),
                        'attachment_errors' => [$msg],
                    ], 422);
                }

                return redirect()->back()->withInput()->withErrors(['files' => $msg]);
            }
        }

        try {
            $result = $this->noteService->updateNoteWithAttachments(
                $request->user(),
                $note,
                $validated,
                $receivedFiles,
                $clientFilesCount
            );
        } catch (AttachmentUploadException $e) {
            Log::error('[ATTACHMENT] note update failed (attachment)', [
                'note_id' => $note->id,
                'stage' => $e->stage,
                'message' => $e->getMessage(),
            ]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(array_merge(
                    $e->toResponseArray($note->id),
                    ['message' => __('api.note_attach_batch_failed_edit')]
                ), 422);
            }

            return redirect()->back()->withInput()->withErrors([
                'files' => __('api.note_attach_batch_failed_edit').': '.implode(' | ', $this->flattenErrors($e->attachmentErrors)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Note update failed', ['note_id' => $note->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(['success' => false, 'note_id' => $note->id, 'files_received' => count($receivedFiles), 'attachments_saved' => $note->attachments()->count(), 'attachment_errors' => [__('api.note_update_failed', ['error' => $e->getMessage()])]], 500);
            }

            return redirect()->back()->withInput()->withErrors(['general' => __('api.note_update_failed', ['error' => $e->getMessage()])]);
        }

        if ($this->wantsJsonResponse($request)) {
            return response()->json([
                'success' => true,
                'note_id' => $result['note']->id,
                'files_received' => $result['files_received'],
                'attachments_saved' => $result['new_saved'],
                'data' => $result['note']->load(['owner', 'attachments']),
                'attachment_errors' => [],
            ]);
        }

        return redirect()->route('notes.index')->with('success', __('api.note_updated'));
    }

    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);
        $this->noteService->deleteDraft(auth()->user(), $note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true], 204);
        }
        return redirect()->route('notes.index')->with('success', __('api.note_deleted'));
    }

    public function send(Note $note)
    {
        $this->authorize('send', $note);
        $note = $this->noteService->sendNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', __('api.note_web_sent'));
    }

    public function accept(Note $note)
    {
        $this->authorize('accept', $note);
        $note = $this->noteService->acceptNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', __('api.note_accepted'));
    }

    public function reject(Request $request, Note $note)
    {
        $this->authorize('reject', $note);
        $request->validate(['rejection_reason' => ['required','string','min:5','max:1000']]);
        $note = $this->noteService->rejectNote(auth()->user(), $note, $request->rejection_reason);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', __('api.note_rejected'));
    }

    public function resend(Note $note)
    {
        $this->authorize('resend', $note);
        $note = $this->noteService->resendRejectedNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', __('api.note_resent'));
    }

    
    public function storeAttachment(Request $request, Note $note)
    {
        $this->authorize('update', $note);
        if ($overflow = $this->postOverflowResponse($request)) return $overflow;

        $phpMaxKb = (int) ($this->parseBytes((string) ini_get('upload_max_filesize')) / 1024);
        $appMaxKb = max(
            (int) config('attachments.max_image_size', 20480),
            (int) config('attachments.max_video_size', 102400),
            (int) (config('attachments.max_audio_size', 100*1024*1024) / 1024)
        );
        $fileMaxKb = $phpMaxKb > 0 ? min($phpMaxKb, $appMaxKb, 512000) : min($appMaxKb, 512000);

        $request->validate([
            'file' => ['required','file','max:'.$fileMaxKb,'mimes:jpg,jpeg,png,webp,heic,heif,tiff,tif,bmp,avif,gif,svg,mp4,webm,mov,avi,3gp,3gpp,mkv,m4v,mpg,mpeg,wmv,flv,ogv,ts,mts,m2ts,vob,asf,m2v,3g2,f4v,m4p,mp3,wav,ogg,oga,m4a,aac,wma,flac,opus,aiff,aif,amr,3ga,awb,mid,midi,au,ra,weba'],
        ]);

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            $code = $file ? (int) $file->getError() : UPLOAD_ERR_NO_FILE;
            $msg = $this->uploadErrorMessage($code, $file?->getClientOriginalName() ?? __('api.upload_file_default'));
            return response()->json(['success'=>false,'message'=>$msg], 422);
        }

        try {
            $attachment = $this->noteService->addAttachment($request->user(), $note, $file);
            return response()->json(['success'=>true,'data'=>$attachment,'message'=>__('api.note_attach_uploaded')], 201);
        } catch (\App\Exceptions\AttachmentUploadException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage(),'stage'=>$e->stage], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[ATTACHMENT] web single upload failed', ['note_id'=>$note->id,'message'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>__('api.note_attach_save_failed', ['error' => $e->getMessage()])], 500);
        }
    }

    public function destroyAttachment(Note $note, Attachment $attachment)
    {
        if ($attachment->note_id !== $note->id) abort(404);
        $this->authorize('removeAttachment', $note);
        $this->noteService->removeAttachment(auth()->user(), $attachment);
        if (request()->expectsJson()) return response()->json(['success' => true], 204);
        return back()->with('success', __('api.note_attach_deleted'));
    }

    
    public function viewAttachment(Attachment $attachment)
    {
        $user = auth()->user();
        $note = $attachment->note;
        if (!$user->can('view', $note)) abort(403, __('api.forbidden_attach_view'));

        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, false);
        }

        abort(404, __('api.file_not_found'));
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $user = auth()->user();
        $note = $attachment->note;
        if (!$user->can('view', $note)) abort(403, __('api.forbidden_attach_download'));

        if (!$user->isReportWriter()) abort(403, __('api.download_writer_only'));

        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, true);
        }

        abort(404, __('api.file_not_found'));
    }


    public function sharedViewAttachment(int $attachment)
    {
        if (!auth()->check()) {
            return redirect()->guest(route('login'));
        }
        $model = Attachment::with('note')->findOrFail($attachment);
        $note = $model->note;
        if (!$note || !auth()->user()->can('view', $note)) {
            abort(403, __('api.forbidden_attach_view'));
        }
        if (!$this->storage->isLocal($model)) {
            abort(404, __('api.file_not_found'));
        }

        return view('shared.attachment', [
            'name' => $model->original_name,
            'mime' => $model->mime_type,
            'size' => $model->file_size,
            'fileUrl' => route('shared.attachments.file', $model),
            'downloadUrl' => route('notes.attachments.download', $model),
            'canDownload' => auth()->user()->isReportWriter(),
        ]);
    }


    public function sharedFileAttachment(int $attachment)
    {
        if (!auth()->check()) {
            return redirect()->guest(route('login'));
        }
        $model = Attachment::with('note')->findOrFail($attachment);
        $note = $model->note;
        if (!$note || !auth()->user()->can('view', $note)) {
            abort(403, __('api.forbidden_attach_view'));
        }
        if (!$this->storage->isLocal($model)) {
            abort(404, __('api.file_not_found'));
        }

        return $this->storage->fileResponse($model, false);
    }

    private function wantsJsonResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax() || $request->wantsJson();
    }


    private function notesSignature($query): array
    {
        $fp = (clone $query)->reorder()->orderByDesc('notes.updated_at')->limit(60)
            ->pluck('notes.updated_at', 'notes.id');
        $total = (clone $query)->reorder()->count();
        $sig = sha1(
            $fp->map(fn ($t, $id) => $id . '@' . ($t instanceof \DateTimeInterface ? $t->getTimestamp() : strtotime((string) $t)))->implode('|')
            . '#' . $total
        );

        return ['sig' => $sig, 'total' => $total];
    }

    private function flattenErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $e) {
            if (is_array($e)) {
                $out[] = ($e['file'] ?? '').': '.($e['message'] ?? json_encode($e, JSON_UNESCAPED_UNICODE));
            } else {
                $out[] = (string) $e;
            }
        }

        return $out;
    }

    
    private function uploadErrorMessage(int $code, string $name): string
    {
        $safe = trim($name) !== '' ? $name : __('api.upload_file_default');
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => __('api.upload_ini', ['name' => $safe]),
            UPLOAD_ERR_FORM_SIZE => __('api.upload_form', ['name' => $safe]),
            UPLOAD_ERR_PARTIAL => __('api.upload_partial', ['name' => $safe]),
            UPLOAD_ERR_NO_FILE => __('api.upload_no_file', ['name' => $safe]),
            UPLOAD_ERR_NO_TMP_DIR => __('api.upload_no_tmp', ['name' => $safe]),
            UPLOAD_ERR_CANT_WRITE => __('api.upload_cant_write', ['name' => $safe]),
            UPLOAD_ERR_EXTENSION => __('api.upload_extension', ['name' => $safe]),
            default => __('api.upload_generic', ['name' => $safe, 'code' => $code]),
        };
    }

    
    private function postOverflowResponse(Request $request)
    {
        $postMax = $this->parseBytes((string) ini_get('post_max_size'));
        $length = (int) $request->server('CONTENT_LENGTH', 0);
        if ($postMax > 0 && $length > $postMax) {
            $msg = __('api.post_too_large');
            Log::warning('[UPLOAD INTEGRITY] post_max_size overflow', [
                'content_length' => $length,
                'post_max_size' => ini_get('post_max_size'),
                'content_type' => $request->server('CONTENT_TYPE'),
            ]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(['success' => false, 'note_id' => null, 'files_received' => 0, 'attachments_saved' => 0, 'attachment_errors' => [$msg]], 413);
            }

            return redirect()->back()->withInput()->withErrors(['files' => $msg]);
        }

        return null;
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
