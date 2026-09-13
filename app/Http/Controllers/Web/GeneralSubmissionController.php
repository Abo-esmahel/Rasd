<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\AttachmentUploadException;
use App\Http\Controllers\Controller;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\User;
use App\Services\AttachmentStorageService;
use App\Services\GeneralSubmissionService;
use App\Services\Localization\LocalizedPresenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeneralSubmissionController extends Controller
{
    use AuthorizesRequests;
    public function __construct(private GeneralSubmissionService $service, private AttachmentStorageService $storage) {}

    public function index(Request $request, LocalizedPresenter $presenter)
    {
        $user = $request->user();
        $query = $this->service->getVisibleSubmissions($user)->with(['owner','reportWriters','attachments']);

        if ($request->filled('status') && in_array($request->status, ['draft','pending','accepted','rejected'], true)) {
            $query->where('status', $request->status);
        }


        $period = $request->input('period');
        $today = \Carbon\Carbon::today();
        if ($period === 'today') {
            $query->whereDate('created_at', $today);
        } elseif ($period === 'yesterday') {
            $query->whereDate('created_at', $today->copy()->subDay());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', $today->copy()->subDays(6)->startOfDay());
        } elseif ($period === 'month') {
            $query->where('created_at', '>=', $today->copy()->subDays(29)->startOfDay());
        } elseif (!in_array($period, [null, '', 'all'], true)) {
            $period = null;
        }

        $submissions = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        try {
            $presenter->preloadSubmissions($submissions->items());
        } catch (\Throwable) {
        }

        return view('general-submissions.index', compact('submissions', 'period'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', GeneralSubmission::class);
        $writers = User::where('role','report_writer')->orderBy('name')->get();
        return view('general-submissions.create', compact('writers'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', GeneralSubmission::class);

        if ($overflow = $this->postOverflowResponse($request)) {
            return $overflow;
        }

        $maxFiles = min(max(1, (int) ini_get('max_file_uploads') ?: 20), (int) config('attachments.max_per_submission', 5));
        $fileMaxKb = \App\Services\NoteService::uploadFileMaxKb();

        if (!$request->filled('floor_number')) $request->merge(['floor_number' => 0]);
        if (!$request->filled('camera_number')) $request->merge(['camera_number' => 1]);
        if (!$request->filled('observed_at')) $request->merge(['observed_at' => now()->format('Y-m-d\TH:i')]);
        $validated = $request->validate([
            'floor_number' => ['required','integer','min:0'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            'observed_end_at' => ['nullable','date'],
            'description' => ['required','string','min:10','max:5000'],
            'report_writer_ids' => ['required','array','min:1'],
            'report_writer_ids.*' => ['integer','distinct','exists:users,id'],
            'files' => ['nullable','array','max:'.$maxFiles],
            'files.*' => ['file','max:'.$fileMaxKb,'mimes:jpg,jpeg,png,webp,heic,heif,tiff,tif,bmp,avif,gif,svg,mp4,webm,mov,avi,3gp,3gpp,mkv,m4v,mpg,mpeg,wmv,flv,ogv,ts,mts,m2ts,vob,asf,m2v,3g2,f4v,m4p,mp3,wav,ogg,oga,m4a,aac,wma,flac,opus,aiff,aif,amr,3ga,awb,mid,midi,au,ra,weba,ac3,dts,alac'],
            'client_files_count' => ['nullable','integer','min:0','max:100'],
        ], [
            'files.max' => __('api.files_max'),
        ], [
            'report_writer_ids' => __('validation.attributes.report_writer_ids'),
        ]);

        
        $writers = User::whereIn('id', $validated['report_writer_ids'])->get();
        $invalid = $writers->filter(fn($u) => !$u->isReportWriter());
        if ($invalid->count() > 0 || $writers->count() !== count($validated['report_writer_ids'])) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => __('api.sub_recipients_must_writers_choice')], 422);
            }

            return back()->withErrors(['report_writer_ids' => __('api.sub_recipients_must_writers_choice')])->withInput();
        }

        $rawFiles = $request->file('files');
        $receivedFiles = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : ($rawFiles ? [$rawFiles] : []);
        $clientFilesCount = max(0, (int) $request->input('client_files_count', 0));

        foreach ($receivedFiles as $f) {
            if (!$f->isValid()) {
                $msg = __('api.upload_generic', ['name' => $f->getClientOriginalName(), 'code' => $f->getError()]);
                if ($this->wantsJson($request)) {
                    return response()->json(['success' => false, 'submission_id' => null, 'files_received' => count($receivedFiles), 'attachments_saved' => 0, 'attachment_errors' => [$msg]], 422);
                }

                return back()->withInput()->withErrors(['files' => $msg]);
            }
        }

        try {
            $result = $this->service->createSubmissionWithAttachments(
                $request->user(),
                $validated,
                $receivedFiles,
                $clientFilesCount,
                $validated['report_writer_ids']
            );
        } catch (AttachmentUploadException $e) {
            Log::error('[SUBMISSION] store failed', ['stage' => $e->stage, 'message' => $e->getMessage()]);
            if ($this->wantsJson($request)) {
                return response()->json(array_merge($e->toResponseArray(null), [
                    'submission_id' => null,
                    'message' => __('api.sub_attach_failed'),
                ]), 422);
            }

            return back()->withInput()->withErrors(['files' => __('api.sub_attach_failed')]);
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['report_writer_ids' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('[SUBMISSION] store unexpected', ['message' => $e->getMessage()]);
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => __('api.sub_create_failed')], 500);
            }

            return back()->withInput()->withErrors(['general' => __('api.sub_create_failed')]);
        }

        $submission = $result['submission'];

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'submission_id' => $submission->id,
                'note_id' => null,
                'files_received' => $result['files_received'],
                'attachments_saved' => $result['attachments_saved'],
                'data' => $submission->load(['reportWriters', 'attachments']),
                'attachment_errors' => [],
            ], 201);
        }

        return redirect()->route('general-submissions.index')->with('success', __('api.sub_web_created'));
    }

    public function show(GeneralSubmission $generalSubmission, LocalizedPresenter $presenter)
    {
        $this->authorize('view', $generalSubmission);
        $generalSubmission->load(['owner','reportWriters','attachments']);
        try {
            $presenter->preloadSubmissions([$generalSubmission]);
        } catch (\Throwable) {
        }
        return view('general-submissions.show', compact('generalSubmission'));
    }

    public function accept(GeneralSubmission $generalSubmission)
    {
        $this->authorize('accept', $generalSubmission);
        try {
            $this->service->accept($generalSubmission, auth()->user());
        } catch (AttachmentUploadException $e) {
            return redirect()->route('general-submissions.show', $generalSubmission)->withErrors(['general' => __('api.sub_accept_failed', ['error' => $e->getMessage()])]);
        }

        return redirect()->route('general-submissions.show', $generalSubmission)->with('success', __('api.sub_web_accepted'));
    }

    public function reject(Request $request, GeneralSubmission $generalSubmission)
    {
        $this->authorize('reject', $generalSubmission);
        $request->validate(['rejection_reason' => ['required','string','min:5','max:1000']]);
        $this->service->reject($generalSubmission, auth()->user(), $request->rejection_reason);
        return redirect()->route('general-submissions.show', $generalSubmission)->with('success', __('api.sub_web_rejected'));
    }

    
    public function viewAttachment(GeneralSubmissionAttachment $attachment)
    {
        $submission = $attachment->submission;
        if (!auth()->user()->can('view', $submission)) {
            abort(403, __('api.forbidden_attach_view'));
        }

        if (!$this->storage->isLocalSubmission($attachment)) {
            abort(404, __('api.file_not_found'));
        }

        return $this->storage->fileResponseSubmission($attachment, false);
    }

    public function downloadAttachment(GeneralSubmissionAttachment $attachment)
    {
        $submission = $attachment->submission;
        if (!auth()->user()->can('view', $submission)) {
            abort(403, __('api.forbidden_attach_download'));
        }
        if (!auth()->user()->isReportWriter()) {
            abort(403, __('api.download_writer_only'));
        }

        if (!$this->storage->isLocalSubmission($attachment)) {
            abort(404, __('api.file_not_found'));
        }

        return $this->storage->fileResponseSubmission($attachment, true);
    }

    public function sharedViewAttachment(int $attachment)
    {
        if (!auth()->check()) {
            return redirect()->guest(route('login'));
        }
        $model = GeneralSubmissionAttachment::with('submission')->findOrFail($attachment);
        $submission = $model->submission;
        if (!$submission || !auth()->user()->can('view', $submission)) {
            abort(403, __('api.forbidden_attach_view'));
        }
        if (!$this->storage->isLocalSubmission($model)) {
            abort(404, __('api.file_not_found'));
        }

        return view('shared.attachment', [
            'name' => $model->original_name,
            'mime' => $model->mime_type,
            'size' => $model->file_size,
            'fileUrl' => route('shared.submission-attachments.file', $model),
            'downloadUrl' => route('submission-attachments.download', $model),
            'canDownload' => auth()->user()->isReportWriter(),
        ]);
    }

    public function sharedFileAttachment(int $attachment)
    {
        if (!auth()->check()) {
            return redirect()->guest(route('login'));
        }
        $model = GeneralSubmissionAttachment::with('submission')->findOrFail($attachment);
        $submission = $model->submission;
        if (!$submission || !auth()->user()->can('view', $submission)) {
            abort(403, __('api.forbidden_attach_view'));
        }
        if (!$this->storage->isLocalSubmission($model)) {
            abort(404, __('api.file_not_found'));
        }

        return $this->storage->fileResponseSubmission($model, false);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->ajax() || $request->wantsJson();
    }

    private function postOverflowResponse(Request $request)
    {
        $postMax = $this->parseBytes((string) ini_get('post_max_size'));
        $length = (int) $request->server('CONTENT_LENGTH', 0);
        if ($postMax > 0 && $length > $postMax) {
            $msg = __('api.post_too_large');
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'submission_id' => null, 'files_received' => 0, 'attachments_saved' => 0, 'attachment_errors' => [$msg]], 413);
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
