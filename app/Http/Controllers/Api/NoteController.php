<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RejectNoteRequest;
use App\Http\Requests\Api\StoreAttachmentRequest;
use App\Http\Requests\Api\StoreNoteRequest;
use App\Http\Requests\Api\UpdateNoteRequest;
use App\Models\Attachment;
use App\Models\Note;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    private NoteService $noteService;
    private \App\Services\AttachmentStorageService $storage;

    public function __construct(NoteService $noteService, \App\Services\AttachmentStorageService $storage)
    {
        $this->noteService = $noteService;
        $this->storage = $storage;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->noteService->getVisibleNotesQuery($user);

        $allowedStatuses = ['draft', 'pending', 'accepted', 'rejected'];
        if ($request->filled('status') && in_array($request->status, $allowedStatuses, true)) {
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

        $notes = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function myNotes(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Note::with(['owner', 'attachments'])->where('user_id', $user->id);

        $allowedStatuses = ['draft', 'pending', 'accepted', 'rejected'];
        if ($request->filled('status') && in_array($request->status, $allowedStatuses, true)) {
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

        $notes = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('create', Note::class)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنشاء الملاحظات',
            ], 403);
        }

        try {
            $note = $this->noteService->createDraft($user, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المسودة بنجاح',
            'data' => $note->load(['owner', 'attachments']),
        ], 201);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('view', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض هذه الملاحظة',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $note->load(['owner', 'processor', 'attachments']),
        ]);
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتعديل هذه الملاحظة',
            ], 403);
        }

        try {
            $note = $this->noteService->updateNote($user, $note, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الملاحظة بنجاح',
                'data' => $note->load(['owner', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('delete', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بحذف هذه الملاحظة',
            ], 403);
        }

        try {
            $this->noteService->deleteDraft($user, $note);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الملاحظة بنجاح',
            ], 204);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function send(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('send', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإرسال هذه الملاحظة',
            ], 403);
        }

        try {
            $note = $this->noteService->sendNote($user, $note);
            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الملاحظة بنجاح',
                'data' => $note->load(['owner', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function accept(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('accept', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بقبول هذه الملاحظة',
            ], 403);
        }

        try {
            $note = $this->noteService->acceptNote($user, $note);
            return response()->json([
                'success' => true,
                'message' => 'تم قبول الملاحظة بنجاح',
                'data' => $note->load(['owner', 'processor', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function reject(RejectNoteRequest $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('reject', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك برفض هذه الملاحظة',
            ], 403);
        }

        try {
            $note = $this->noteService->rejectNote($user, $note, $request->rejection_reason);
            return response()->json([
                'success' => true,
                'message' => 'تم رفض الملاحظة بنجاح',
                'data' => $note->load(['owner', 'processor', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function resend(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('resend', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإعادة إرسال هذه الملاحظة',
            ], 403);
        }

        try {
            $note = $this->noteService->resendRejectedNote($user, $note);
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة إرسال الملاحظة بنجاح',
                'data' => $note->load(['owner', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeAttachment(StoreAttachmentRequest $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('addAttachment', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإضافة مرفقات لهذه الملاحظة',
            ], 403);
        }

        try {
            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                $code = $file ? (int) $file->getError() : UPLOAD_ERR_NO_FILE;
                $name = $file ? $file->getClientOriginalName() : 'الملف';
                $msg = match ($code) {
                    UPLOAD_ERR_INI_SIZE => "الملف {$name} يتجاوز حد الخادم upload_max_filesize.",
                    UPLOAD_ERR_FORM_SIZE => "الملف {$name} يتجاوز الحد المسموح في النموذج.",
                    UPLOAD_ERR_PARTIAL => "وصل الملف {$name} ناقصاً. يرجى إعادة المحاولة.",
                    UPLOAD_ERR_NO_FILE => "لم يتم استلام الملف {$name}.",
                    default => "تعذّر استلام الملف {$name} (خطأ رفع {$code}).",
                };

                return response()->json([
                    'success' => false,
                    'message' => $msg,
                    'files_received' => $file ? 1 : 0,
                    'attachments_saved' => 0,
                ], 422);
            }
            $attachment = $this->noteService->addAttachment($user, $note, $file);
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المرفق بنجاح',
                'files_received' => 1,
                'attachments_saved' => 1,
                'data' => $attachment,
            ], 201);
        } catch (\App\Exceptions\AttachmentUploadException $e) {
            \Illuminate\Support\Facades\Log::error('[ATTACHMENT] api single upload failed', [
                'userId' => $user->id,
                'noteId' => $note->id,
                'stage' => $e->stage,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل رفع المرفق: '.$e->getMessage(),
                'files_received' => 1,
                'attachments_saved' => 0,
                'attachment_errors' => $e->attachmentErrors ?: [$e->getMessage()],
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'files_received' => 1,
                'attachments_saved' => 0,
            ], 400);
        } catch (\Throwable $e) {
            // فشل تخزين محلي — التفاصيل في السجلات فقط (لا Cloudinary في المسار الجديد)
            \Illuminate\Support\Facades\Log::error('[ATTACHMENT] local storage upload failed', [
                'userId' => $user->id,
                'noteId' => $note->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'تعذّر تخزين الملف على القرص المحلي. حاول مجدداً.',
                'files_received' => 1,
                'attachments_saved' => 0,
            ], 500);
        }
    }

    public function destroyAttachment(Request $request, Note $note, Attachment $attachment): JsonResponse
    {
        $user = $request->user();

        if ($attachment->note_id !== $note->id) {
            return response()->json([
                'success' => false,
                'message' => 'المرفق لا ينتمي لهذه الملاحظة',
            ], 404);
        }

        if (!$user->can('removeAttachment', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بحذف هذا المرفق',
            ], 403);
        }

        try {
            $this->noteService->removeAttachment($user, $attachment);
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المرفق بنجاح',
            ], 204);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function downloadAttachment(Request $request, Attachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $note = $attachment->note;

        if (!$user->can('view', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتحميل هذا المرفق',
            ], 403);
        }

        // التنزيل لكاتب التقرير فقط — حتى صاحب الملاحظة لا يمكنه التنزيل (حسب سياسة المشروع)
        if (!$user->isReportWriter()) {
            return response()->json([
                'success' => false,
                'message' => 'التنزيل مسموح لكاتب التقرير فقط',
            ], 403);
        }

        // Local first, legacy Cloudinary (secure_url) second, else controlled 404.
        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, true);
        }

        if (!empty($attachment->secure_url)) {
            return redirect()->away($attachment->secure_url);
        }

        return response()->json([
            'success' => false,
            'message' => 'الملف غير موجود',
        ], 404);
    }

    public function viewAttachment(Request $request, Attachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $note = $attachment->note;

        if (!$user->can('view', $note)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض هذا المرفق',
            ], 403);
        }

        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, false);
        }

        if (!empty($attachment->secure_url)) {
            return redirect()->away($attachment->secure_url);
        }

        return response()->json([
            'success' => false,
            'message' => 'الملف غير موجود',
        ], 404);
    }
}
