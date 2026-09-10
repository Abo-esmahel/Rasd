<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\AttachmentUploadException;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\AttachmentStorageService;
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

    public function index(Request $request)
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

        $notes = $query->paginate(15)->withQueryString();
        // إصلاح جمود الموقع: استعلام مباشر بدون file cache (file cache + SQLite + file session = تـنافس على قفل الملف)
        // 4 صفوف فقط — الاستعلام أسرع من قراءة/كتابة cache
        $observers = User::where('role', 'monitor')->orderBy('name')->get(['id', 'name']);

        $observerUser = null;
        if ($request->filled('observer')) {
            $observerUser = User::find($request->observer);
        }

        return view('notes.index', compact('notes', 'observers', 'observerUser'));
    }

    public function myNotes(Request $request)
    {
        $user = $request->user();
        $query = Note::with(['owner','processor','attachments'])->where('user_id', $user->id);

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

        $notes = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

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
        // حد الملف الواحد = أصغر بين حد PHP وحد التطبيق — أسرع فشل مبكر ورسالة واضحة
        $phpMaxKb = (int) ($this->parseBytes((string) ini_get('upload_max_filesize')) / 1024);
        $appMaxKb = max(
            (int) config('attachments.max_image_size', 20480),
            (int) config('attachments.max_video_size', 102400),
            (int) (config('attachments.max_audio_size', 100*1024*1024) / 1024)
        );
        $fileMaxKb = $phpMaxKb > 0 ? min($phpMaxKb, $appMaxKb, 512000) : min($appMaxKb, 512000);
        $validated = $request->validate([
            'floor_number' => ['required','integer','min:0'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            'observed_end_at' => ['nullable','date','after_or_equal:observed_at'],
            'description' => ['required','string','min:10','max:5000'],
            'files' => ['nullable','array','max:'.$maxFiles],
            'files.*' => ['file','max:'.$fileMaxKb],
            'client_files_count' => ['nullable','integer','min:0','max:100'],
        ], [
            'files.max' => 'عدد الملفات يتجاوز الحد المسموح به من السيرفر (:max). أرسل على دفعات.',
            'files.*.max' => 'حجم الملف يتجاوز الحد الأقصى (:max كيلوبايت).',
        ], [
            'floor_number' => 'رقم الطابق',
            'camera_number' => 'رقم الكاميرا',
            'observed_at' => 'وقت الملاحظة',
            'observed_end_at' => 'وقت انتهاء الملاحظة',
            'description' => 'الوصف',
        ]);

        $rawFiles = $request->file('files');
        $receivedFiles = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : ($rawFiles ? [$rawFiles] : []);
        $clientFilesCount = max(0, (int) $request->input('client_files_count', 0));

        // Explicit per-file PHP upload check BEFORE any DB write (fail fast, no silent success).
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
                    ['message' => 'فشل رفع أحد المرفقات، ولم يتم حفظ الملاحظة.']
                ), 422);
            }

            return redirect()->back()->withInput()->withErrors([
                'files' => 'فشل رفع أحد المرفقات، ولم يتم حفظ الملاحظة: '.implode(' | ', $this->flattenErrors($e->attachmentErrors)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Note creation failed', ['request_id' => $reqId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(['success' => false, 'note_id' => null, 'files_received' => count($receivedFiles), 'attachments_saved' => 0, 'attachment_errors' => ['فشل إنشاء الملاحظة: '.$e->getMessage()]], 500);
            }

            return redirect()->back()->withInput()->withErrors(['general' => 'فشل إنشاء الملاحظة: '.$e->getMessage()]);
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

        return redirect()->route('notes.index')->with('success', 'تم إنشاء الملاحظة بنجاح');
    }

    public function show(Note $note)
    {
        $this->authorize('view', $note);
        $note->load(['owner','processor','attachments']);
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
        $phpMaxKb = (int) ($this->parseBytes((string) ini_get('upload_max_filesize')) / 1024);
        $appMaxKb = max(
            (int) config('attachments.max_image_size', 20480),
            (int) config('attachments.max_video_size', 102400),
            (int) (config('attachments.max_audio_size', 100*1024*1024) / 1024)
        );
        $fileMaxKb = $phpMaxKb > 0 ? min($phpMaxKb, $appMaxKb, 512000) : min($appMaxKb, 512000);
        $validated = $request->validate([
            'floor_number' => ['required','integer','min:0'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            'observed_end_at' => ['nullable','date','after_or_equal:observed_at'],
            'description' => ['required','string','min:10','max:5000'],
            'files' => ['nullable','array','max:'.$maxFiles],
            'files.*' => ['file','max:'.$fileMaxKb],
            'client_files_count' => ['nullable','integer','min:0','max:100'],
        ], [
            'files.max' => 'عدد الملفات يتجاوز الحد المسموح به من السيرفر (:max). أرسل على دفعات.',
            'files.*.max' => 'حجم الملف يتجاوز الحد الأقصى (:max كيلوبايت).',
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
                    ['message' => 'فشل رفع أحد المرفقات، ولم يتم حفظ التعديلات.']
                ), 422);
            }

            return redirect()->back()->withInput()->withErrors([
                'files' => 'فشل رفع أحد المرفقات، ولم يتم حفظ التعديلات: '.implode(' | ', $this->flattenErrors($e->attachmentErrors)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Note update failed', ['note_id' => $note->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            if ($this->wantsJsonResponse($request)) {
                return response()->json(['success' => false, 'note_id' => $note->id, 'files_received' => count($receivedFiles), 'attachments_saved' => $note->attachments()->count(), 'attachment_errors' => ['فشل تحديث الملاحظة: '.$e->getMessage()]], 500);
            }

            return redirect()->back()->withInput()->withErrors(['general' => 'فشل تحديث الملاحظة: '.$e->getMessage()]);
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

        return redirect()->route('notes.index')->with('success', 'تم تحديث الملاحظة بنجاح');
    }

    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);
        $this->noteService->deleteDraft(auth()->user(), $note);

        if (request()->expectsJson()) {
            return response()->json(['success' => true], 204);
        }
        return redirect()->route('notes.index')->with('success', 'تم حذف الملاحظة');
    }

    public function send(Note $note)
    {
        $this->authorize('send', $note);
        $note = $this->noteService->sendNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', 'تم إرسال الملاحظة للمراجعة');
    }

    public function accept(Note $note)
    {
        $this->authorize('accept', $note);
        $note = $this->noteService->acceptNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', 'تم قبول الملاحظة');
    }

    public function reject(Request $request, Note $note)
    {
        $this->authorize('reject', $note);
        $request->validate(['rejection_reason' => ['required','string','min:5','max:1000']]);
        $note = $this->noteService->rejectNote(auth()->user(), $note, $request->rejection_reason);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', 'تم رفض الملاحظة');
    }

    public function resend(Note $note)
    {
        $this->authorize('resend', $note);
        $note = $this->noteService->resendRejectedNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success' => true,'status' => $note->status,'data' => $note]);
        return redirect()->route('notes.index')->with('success', 'تمت إعادة إرسال الملاحظة');
    }

    /**
     * رفع مرفق واحد بعد إنشاء الملاحظة — يستخدم للرفع المتدرج (Progressive Upload)
     * يسمح برفع ملفات كبيرة واحدة تلو الأخرى بدلاً من طلب واحد ضخم، أسرع وأكثر استقراراً
     */
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
            $msg = $this->uploadErrorMessage($code, $file?->getClientOriginalName() ?? 'الملف');
            return response()->json(['success'=>false,'message'=>$msg], 422);
        }

        try {
            $attachment = $this->noteService->addAttachment($request->user(), $note, $file);
            return response()->json(['success'=>true,'data'=>$attachment,'message'=>'تم رفع المرفق'], 201);
        } catch (\App\Exceptions\AttachmentUploadException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage(),'stage'=>$e->stage], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[ATTACHMENT] web single upload failed', ['note_id'=>$note->id,'message'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>'تعذر حفظ المرفق: '.$e->getMessage()], 500);
        }
    }

    public function destroyAttachment(Note $note, Attachment $attachment)
    {
        if ($attachment->note_id !== $note->id) abort(404);
        $this->authorize('removeAttachment', $note);
        $this->noteService->removeAttachment(auth()->user(), $attachment);
        if (request()->expectsJson()) return response()->json(['success' => true], 204);
        return back()->with('success', 'تم حذف المرفق');
    }

    /**
     * Secure view route:
     *   local exists → stream from local disk (auth already checked)
     *   else legacy secure_url → redirect (read-only fallback)
     *   else → controlled 404 (never silent broken image)
     */
    public function viewAttachment(Attachment $attachment)
    {
        $user = auth()->user();
        $note = $attachment->note;
        if (!$user->can('view', $note)) abort(403, 'غير مصرح لك بعرض هذا المرفق');

        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, false);
        }

        // Legacy Cloudinary fallback: secure_url only (new local files have null secure_url).
        if (!empty($attachment->secure_url)) {
            return redirect()->away($attachment->secure_url);
        }

        // Fallback for very old records without secure_url but with a resolvable Cloudinary URL.
        $url = $attachment->url;
        if ($url && $this->storage->storageType($attachment) === 'cloudinary_legacy') {
            return redirect()->away($url);
        }

        abort(404, 'الملف غير موجود');
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $user = auth()->user();
        $note = $attachment->note;
        if (!$user->can('view', $note)) abort(403, 'غير مصرح لك بتحميل هذا المرفق');
        // التنزيل لكاتب التقرير فقط — حتى صاحب الملاحظة لا يمكنه التنزيل (حسب سياسة المشروع)
        if (!$user->isReportWriter()) abort(403, 'التنزيل مسموح لكاتب التقرير فقط');

        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, true);
        }

        if (!empty($attachment->secure_url)) {
            return redirect()->away($attachment->secure_url);
        }

        $url = $attachment->url;
        if ($url && $this->storage->storageType($attachment) === 'cloudinary_legacy') {
            return redirect()->away($url);
        }

        abort(404, 'الملف غير موجود');
    }

    private function wantsJsonResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax() || $request->wantsJson();
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

    /**
     * UPLOAD INTEGRITY — رسالة عربية مفهومة لكل رمز خطأ رفع من PHP.
     */
    private function uploadErrorMessage(int $code, string $name): string
    {
        $safe = trim($name) !== '' ? $name : 'الملف';
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => "الملف {$safe} يتجاوز حد الخادم upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE => "الملف {$safe} يتجاوز الحد المسموح في النموذج.",
            UPLOAD_ERR_PARTIAL => "وصل الملف {$safe} ناقصاً. يرجى إعادة المحاولة.",
            UPLOAD_ERR_NO_FILE => "لم يتم استلام الملف {$safe}.",
            UPLOAD_ERR_NO_TMP_DIR => "تعذّر حفظ الملف {$safe} مؤقتاً (إعداد الخادم).",
            UPLOAD_ERR_CANT_WRITE => "تعذّر كتابة الملف {$safe} على الخادم.",
            UPLOAD_ERR_EXTENSION => "رفض الخادم الملف {$safe} (إضافة PHP).",
            default => "تعذّر استلام الملف {$safe} (خطأ رفع {$code}).",
        };
    }

    /**
     * UPLOAD INTEGRITY — تجاوز post_max_size يفرّغ POST وFILES معاً بصمت.
     * نكتشفه عبر Content-Length قبل أي معالجة. يعيد Response أو null.
     */
    private function postOverflowResponse(Request $request)
    {
        $postMax = $this->parseBytes((string) ini_get('post_max_size'));
        $length = (int) $request->server('CONTENT_LENGTH', 0);
        if ($postMax > 0 && $length > $postMax) {
            $msg = 'حجم الطلب يتجاوز حد الخادم post_max_size. قلل حجم/عدد المرفقات ثم أعد المحاولة.';
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
