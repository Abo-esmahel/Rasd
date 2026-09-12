<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GeneralSubmission\StoreGeneralSubmissionRequest;
use App\Http\Requests\Api\GeneralSubmission\UpdateGeneralSubmissionRequest;
use App\Models\GeneralSubmission;
use App\Models\User;
use App\Services\GeneralSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GeneralSubmissionController extends Controller
{
    private GeneralSubmissionService $generalSubmissionService;
    private \App\Services\AttachmentStorageService $storage;

    public function __construct(GeneralSubmissionService $generalSubmissionService, \App\Services\AttachmentStorageService $storage)
    {
        $this->generalSubmissionService = $generalSubmissionService;
        $this->storage = $storage;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->generalSubmissionService->getVisibleSubmissions($user);

        if ($request->filled('status') && in_array($request->status, ['draft', 'pending', 'accepted', 'rejected'], true)) {
            $query->where('status', $request->status);
        }

        $submissions = $query->orderByDesc('created_at')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $submissions,
        ]);
    }

    public function store(StoreGeneralSubmissionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('create', GeneralSubmission::class)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنشاء الإرسالات العامة',
            ], 403);
        }

        $validated = $request->validated();
        $rawFiles = $request->file('files');
        $receivedFiles = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : ($rawFiles ? [$rawFiles] : []);
        $clientFilesCount = max(0, (int) $request->input('client_files_count', 0));

        foreach ($receivedFiles as $f) {
            if (!$f->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => "تعذّر استلام الملف {$f->getClientOriginalName()}",
                    'files_received' => count($receivedFiles),
                    'attachments_saved' => 0,
                ], 422);
            }
        }

        try {
            
            if (!empty($validated['report_writer_ids'])) {
                $result = $this->generalSubmissionService->createSubmissionWithAttachments(
                    $user, $validated, $receivedFiles, $clientFilesCount, $validated['report_writer_ids']
                );
                $submission = $result['submission'];

                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء الإرسال وإرساله بنجاح',
                    'submission_id' => $submission->id,
                    'files_received' => $result['files_received'],
                    'attachments_saved' => $result['attachments_saved'],
                    'data' => $submission->load(['reportWriters', 'writer', 'attachments']),
                ], 201);
            }

            $submission = $this->generalSubmissionService->createDraft($user, $validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الإرسال المسودة بنجاح',
                'data' => $submission->load(['reportWriters', 'writer']),
            ], 201);
        } catch (\App\Exceptions\AttachmentUploadException $e) {
            return response()->json(array_merge($e->toResponseArray(null), [
                'message' => 'فشل رفع أحد المرفقات، ولم يتم حفظ الإرسالية.',
            ]), 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function show(Request $request, GeneralSubmission $generalSubmission): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('view', $generalSubmission)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بعرض هذه الإرسال',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $generalSubmission->load(['reportWriters', 'writer', 'owner', 'attachments']),
        ]);
    }

    public function update(UpdateGeneralSubmissionRequest $request, GeneralSubmission $generalSubmission): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $generalSubmission)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتعديل هذه الإرسال',
            ], 403);
        }

        try {
            $generalSubmission = $this->generalSubmissionService->addReportWriter(
                $generalSubmission,
                User::find($request->report_writer_id)
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الكاتب بنجاح',
                'data' => $generalSubmission->load(['reportWriters']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function submit(Request $request, GeneralSubmission $generalSubmission): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('submit', $generalSubmission)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإرسال الإرسال',
            ], 403);
        }

        $validated = $request->validate([
            'report_writer_ids' => ['required','array','min:1'],
            'report_writer_ids.*' => ['integer','distinct','exists:users,id'],
        ]);

        try {
            $generalSubmission = $this->generalSubmissionService->submit(
                $generalSubmission,
                $user,
                $validated['report_writer_ids']
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الإرسال بنجاح',
                'data' => $generalSubmission->load(['reportWriters']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function accept(Request $request, GeneralSubmission $generalSubmission): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('accept', $generalSubmission)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بقبول هذه الإرسالية',
            ], 403);
        }

        try {
            $submission = $this->generalSubmissionService->accept($generalSubmission, $user);

            return response()->json([
                'success' => true,
                'message' => 'تم قبول الإرسال بنجاح',
                'data' => $submission->fresh()->load(['owner', 'reportWriters', 'attachments']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function reject(Request $request, GeneralSubmission $generalSubmission): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('reject', $generalSubmission)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك برفض هذه الإرسالية',
            ], 403);
        }

        $request->validate(['rejection_reason' => ['required', 'string', 'min:5', 'max:1000']]);

        try {
            $submission = $this->generalSubmissionService->reject($generalSubmission, $user, $request->rejection_reason);

            return response()->json([
                'success' => true,
                'message' => 'تم رفض الإرسال بنجاح',
                'data' => $submission->load(['reportWriters']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function viewAttachment(Request $request, \App\Models\GeneralSubmissionAttachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $submission = $attachment->submission;

        if (!$user->can('view', $submission)) {
            return response()->json(['success' => false, 'message' => __('api.forbidden_attach_view')], 403);
        }

        if (!$this->storage->isLocalSubmission($attachment)) {
            return response()->json(['success' => false, 'message' => 'الملف غير موجود'], 404);
        }

        return $this->storage->fileResponseSubmission($attachment, false);
    }

    public function downloadAttachment(Request $request, \App\Models\GeneralSubmissionAttachment $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();
        $submission = $attachment->submission;

        if (!$user->can('view', $submission)) {
            return response()->json(['success' => false, 'message' => __('api.forbidden_attach_download')], 403);
        }

        if (!$user->isReportWriter()) {
            return response()->json(['success' => false, 'message' => __('api.download_writer_only')], 403);
        }

        if (!$this->storage->isLocalSubmission($attachment)) {
            return response()->json(['success' => false, 'message' => 'الملف غير موجود'], 404);
        }

        return $this->storage->fileResponseSubmission($attachment, true);
    }
}