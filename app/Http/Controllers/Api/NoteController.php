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
        if ($user->isGeneralManager() || !$user->can('viewAny', Note::class)) {
            return response()->json(['success' => false, 'message' => __('api.forbidden')], 403);
        }
        $query = $this->noteService->getVisibleNotesQuery($user);

        $allowedStatuses = ['draft', 'pending', 'accepted', 'rejected'];
        if ($request->filled('status') && in_array($request->status, $allowedStatuses, true)) {
            $query->where('notes.status', $request->status);
        } else {
            $query->where('notes.status', '!=', Note::STATUS_DRAFT);
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
        if ($request->filled('search') && mb_strlen(trim($request->search)) >= 2) {
            $query->where('notes.description', 'like', '%'.trim($request->search).'%');
        }
        if ($request->filled('observer') && is_numeric($request->observer)) {
            $query->where('notes.user_id', (int) $request->observer);
        }

        $sort = (string) $request->query('sort', 'latest');
        if ($sort === 'oldest') {
            $query->orderBy('notes.created_at');
        } elseif ($sort === 'observer') {
            $query->join('users', 'users.id', '=', 'notes.user_id')
                ->orderBy('users.name')
                ->select('notes.*');
        } else {
            $query->orderByDesc('notes.created_at');
        }

        $notes = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function myNotes(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isGeneralManager()) {
            return response()->json(['success' => false, 'message' => __('api.forbidden')], 403);
        }
        $query = Note::with(['owner', 'attachments'])->where('user_id', $user->id)->whereNull('general_submission_id');

        $allowedStatuses = ['draft', 'pending', 'accepted', 'rejected'];
        if ($request->filled('status') && in_array($request->status, $allowedStatuses, true)) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', Note::STATUS_DRAFT);
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
        if ($request->filled('search') && mb_strlen(trim($request->search)) >= 2) {
            $query->where('description', 'like', '%'.trim($request->search).'%');
        }

        $sort = (string) $request->query('sort', 'latest');
        if ($sort === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $notes = $query->paginate(15);

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
                'message' => __('api.note_unauthorized_create'),
            ], 403);
        }

        try {
            $note = $this->noteService->createDraft($user, $request->validated());
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = str_contains($msg, 'انتهاء') ? 'observed_end_at' : 'observed_at';
            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors' => [$field => [$msg]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('api.note_draft_created'),
            'data' => $note->load(['owner', 'attachments']),
        ], 201);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if ($note->general_submission_id !== null) {
            return response()->json([
                'success' => false,
                'message' => __('api.note_not_found'),
            ], 404);
        }

        if (!$user->can('view', $note)) {
            return response()->json([
                'success' => false,
                'message' => __('api.note_unauthorized_view'),
            ], 403);
        }

        $note->load(['owner', 'processor', 'attachments', 'reactions']);
        $data = $note->toArray();
        unset($data['reactions']);
        $data['reaction_counts'] = $note->reactions->groupBy('reaction')->map->count()->all();
        $data['my_reaction'] = $note->reactions->firstWhere('user_id', $user->id)?->reaction;

        $data['can_react'] = $user->can('react', $note);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function update(UpdateNoteRequest $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $note)) {
            return response()->json([
                'success' => false,
                'message' => __('api.note_unauthorized_update'),
            ], 403);
        }

        try {
            $note = $this->noteService->updateNote($user, $note, $request->validated());
            return response()->json([
                'success' => true,
                'message' => __('api.note_updated'),
                'data' => $note->load(['owner', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $field = str_contains($msg, 'انتهاء') ? 'observed_end_at' : 'observed_at';
            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors' => [$field => [$msg]],
            ], 422);
        }
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('delete', $note)) {
            return response()->json([
                'success' => false,
                'message' => __('api.note_unauthorized_delete'),
            ], 403);
        }

        try {
            $this->noteService->deleteDraft($user, $note);
            return response()->json([
                'success' => true,
                'message' => __('api.note_api_deleted'),
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
                'message' => __('api.note_unauthorized_send'),
            ], 403);
        }

        try {
            $note = $this->noteService->sendNote($user, $note);
            return response()->json([
                'success' => true,
                'message' => __('api.note_sent'),
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
                'message' => __('api.note_unauthorized_accept'),
            ], 403);
        }

        try {
            $note = $this->noteService->acceptNote($user, $note);
            return response()->json([
                'success' => true,
                'message' => __('api.note_api_accepted'),
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
                'message' => __('api.note_unauthorized_reject'),
            ], 403);
        }

        try {
            $note = $this->noteService->rejectNote($user, $note, $request->rejection_reason);
            return response()->json([
                'success' => true,
                'message' => __('api.note_api_rejected'),
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
                'message' => __('api.note_unauthorized_resend'),
            ], 403);
        }

        try {
            $note = $this->noteService->resendRejectedNote($user, $note);
            return response()->json([
                'success' => true,
                'message' => __('api.note_api_resent'),
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
                'message' => __('api.note_unauthorized_attach'),
            ], 403);
        }

        try {
            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                $code = $file ? (int) $file->getError() : UPLOAD_ERR_NO_FILE;
                $name = $file ? $file->getClientOriginalName() : __('api.upload_file_default');
                $msg = match ($code) {
                    UPLOAD_ERR_INI_SIZE => __('api.upload_ini', ['name' => $name]),
                    UPLOAD_ERR_FORM_SIZE => __('api.upload_form', ['name' => $name]),
                    UPLOAD_ERR_PARTIAL => __('api.upload_partial', ['name' => $name]),
                    UPLOAD_ERR_NO_FILE => __('api.upload_no_file', ['name' => $name]),
                    default => __('api.upload_generic', ['name' => $name, 'code' => $code]),
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
                'message' => __('api.note_attach_added'),
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
                'message' => __('api.attach_failed_prefix', ['error' => $e->getMessage()]),
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
            
            \Illuminate\Support\Facades\Log::error('[ATTACHMENT] local storage upload failed', [
                'userId' => $user->id,
                'noteId' => $note->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('api.storage_failed'),
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
                'message' => __('api.note_attach_not_belong'),
            ], 404);
        }

        if (!$user->can('removeAttachment', $note)) {
            return response()->json([
                'success' => false,
                'message' => __('api.note_unauthorized_detach'),
            ], 403);
        }

        try {
            $this->noteService->removeAttachment($user, $attachment);
            return response()->json([
                'success' => true,
                'message' => __('api.note_attach_deleted'),
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

        if (!$user->isGeneralManager() && !$user->can('view', $note)) {
            return response()->json([
                'success' => false,
                'message' => __('api.forbidden_attach_download'),
            ], 403);
        }

        
        if (!$user->isReportWriter() && !$user->isGeneralManager()) {
            return response()->json([
                'success' => false,
                'message' => __('api.download_writer_only'),
            ], 403);
        }

        
        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, true);
        }

        return response()->json([
            'success' => false,
            'message' => __('api.file_not_found'),
        ], 404);
    }

    public function viewAttachment(Request $request, Attachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $note = $attachment->note;

        if (!$user->isGeneralManager() && !$user->can('view', $note)) {
            return response()->json([
                'success' => false,
                'message' => __('api.forbidden_attach_view'),
            ], 403);
        }

        if ($this->storage->isLocal($attachment)) {
            return $this->storage->fileResponse($attachment, false);
        }

        return response()->json([
            'success' => false,
            'message' => __('api.file_not_found'),
        ], 404);
    }

    /**
     * عرض مرفق برابط موقّع (من داخل PDF) — بلا جلسة ولا Bearer.
     *
     * التوقيع نفسه هو التفويض: محدّد بملف واحد وبوقت انتهاء. بلا توقيع صالح
     * (أو منتهٍ) نرد 403 لا 401 — فلا يظن المستخدم أن الملف محمي بحساب.
     */
    public function signedAttachmentView(Request $request, Attachment $attachment)
    {
        $ok = \App\Services\Pdf\AttachmentSignedUrl::verify(
            (int) $attachment->getKey(),
            $request->query('e'),
            $request->query('s'),
            false,
        );

        if (! $ok) {
            return response()->json([
                'success' => false,
                'message' => __('api.forbidden_attach_view'),
            ], 403);
        }

        if (! $this->storage->isLocal($attachment)) {
            return response()->json([
                'success' => false,
                'message' => __('api.file_not_found'),
            ], 404);
        }

        return $this->storage->fileResponse($attachment, false);
    }
}
