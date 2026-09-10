<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\AttachmentUploadException;
use App\Http\Controllers\Controller;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\User;
use App\Services\AttachmentStorageService;
use App\Services\GeneralSubmissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeneralSubmissionController extends Controller
{
    use AuthorizesRequests;
    public function __construct(private GeneralSubmissionService $service, private AttachmentStorageService $storage) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->service->getVisibleSubmissions($user)->with(['owner','reportWriters','attachments']);

        if ($request->filled('status') && in_array($request->status, ['draft','pending','accepted','rejected'], true)) {
            $query->where('status', $request->status);
        }

        // Quick time presets on submission date.
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

        $maxFiles = max(1, (int) ini_get('max_file_uploads') ?: 20);
        // Simplified form (description + writers only): auto-fill the rest.
        if (!$request->filled('floor_number')) $request->merge(['floor_number' => 0]);
        if (!$request->filled('camera_number')) $request->merge(['camera_number' => 1]);
        if (!$request->filled('observed_at')) $request->merge(['observed_at' => now()->format('Y-m-d\TH:i')]);
        $validated = $request->validate([
            'floor_number' => ['required','integer','min:0'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            'observed_end_at' => ['nullable','date','after_or_equal:observed_at'],
            'description' => ['required','string','min:10','max:5000'],
            'report_writer_ids' => ['required','array','min:1'],
            'report_writer_ids.*' => ['integer','distinct','exists:users,id'],
            'files' => ['nullable','array','max:'.$maxFiles],
            'files.*' => ['file','max:512000'],
            'client_files_count' => ['nullable','integer','min:0','max:100'],
        ], [
            'files.max' => 'عدد الملفات يتجاوز الحد المسموح به من السيرفر (:max). أرسل على دفعات.',
        ], [
            'report_writer_ids' => 'كتّاب التقارير',
        ]);

        
        $writers = User::whereIn('id', $validated['report_writer_ids'])->get();
        $invalid = $writers->filter(fn($u) => !$u->isReportWriter());
        if ($invalid->count() > 0 || $writers->count() !== count($validated['report_writer_ids'])) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => 'يجب أن يكون جميع المختارين كتّاب تقارير'], 422);
            }

            return back()->withErrors(['report_writer_ids' => 'يجب أن يكون جميع المختارين كتّاب تقارير'])->withInput();
        }

        $rawFiles = $request->file('files');
        $receivedFiles = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : ($rawFiles ? [$rawFiles] : []);
        $clientFilesCount = max(0, (int) $request->input('client_files_count', 0));

        foreach ($receivedFiles as $f) {
            if (!$f->isValid()) {
                $msg = "تعذّر استلام الملف {$f->getClientOriginalName()} (خطأ رفع {$f->getError()}).";
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
                    'message' => 'فشل رفع أحد المرفقات، ولم يتم حفظ الإرسالية.',
                ]), 422);
            }

            return back()->withInput()->withErrors(['files' => 'فشل رفع أحد المرفقات، ولم يتم حفظ الإرسالية.']);
        } catch (\InvalidArgumentException $e) {
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['report_writer_ids' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('[SUBMISSION] store unexpected', ['message' => $e->getMessage()]);
            if ($this->wantsJson($request)) {
                return response()->json(['success' => false, 'message' => 'فشل إنشاء الإرسالية.'], 500);
            }

            return back()->withInput()->withErrors(['general' => 'فشل إنشاء الإرسالية.']);
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

        return redirect()->route('general-submissions.index')->with('success','تم إنشاء الإرسال العام وإرساله إلى الكتّاب المختارين');
    }

    public function show(GeneralSubmission $generalSubmission)
    {
        $this->authorize('view', $generalSubmission);
        $generalSubmission->load(['owner','reportWriters','attachments']);
        return view('general-submissions.show', compact('generalSubmission'));
    }

    public function accept(GeneralSubmission $generalSubmission)
    {
        $this->authorize('accept', $generalSubmission);
        try {
            $this->service->accept($generalSubmission, auth()->user());
        } catch (AttachmentUploadException $e) {
            return redirect()->route('general-submissions.show', $generalSubmission)->withErrors(['general' => 'تعذّر القبول: '.$e->getMessage()]);
        }

        return redirect()->route('general-submissions.show', $generalSubmission)->with('success', 'تم قبول الإرسالية');
    }

    public function reject(Request $request, GeneralSubmission $generalSubmission)
    {
        $this->authorize('reject', $generalSubmission);
        $request->validate(['rejection_reason' => ['required','string','min:5','max:1000']]);
        $this->service->reject($generalSubmission, auth()->user(), $request->rejection_reason);
        return redirect()->route('general-submissions.show', $generalSubmission)->with('success', 'تم رفض الإرسالية');
    }

    
    public function viewAttachment(GeneralSubmissionAttachment $attachment)
    {
        $submission = $attachment->submission;
        if (!auth()->user()->can('view', $submission)) {
            abort(403, 'غير مصرح لك بعرض هذا المرفق');
        }

        if (!$this->storage->isLocalSubmission($attachment)) {
            abort(404, 'الملف غير موجود');
        }

        return $this->storage->fileResponseSubmission($attachment, false);
    }

    public function downloadAttachment(GeneralSubmissionAttachment $attachment)
    {
        $submission = $attachment->submission;
        if (!auth()->user()->can('view', $submission)) {
            abort(403, 'غير مصرح لك بتحميل هذا المرفق');
        }
        if (!auth()->user()->isReportWriter()) {
            abort(403, 'التنزيل مسموح لكاتب التقرير فقط');
        }

        if (!$this->storage->isLocalSubmission($attachment)) {
            abort(404, 'الملف غير موجود');
        }

        return $this->storage->fileResponseSubmission($attachment, true);
    }

    public function sharedViewAttachment(int $attachment)
    {
        // Guests always go to login first (even for unknown ids) — never a bare 404.
        if (!auth()->check()) {
            return redirect()->guest(route('login'));
        }
        $model = GeneralSubmissionAttachment::findOrFail($attachment);
        if (!$this->storage->isLocalSubmission($model)) {
            abort(404, 'الملف غير موجود');
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
        $model = GeneralSubmissionAttachment::findOrFail($attachment);
        if (!$this->storage->isLocalSubmission($model)) {
            abort(404, 'الملف غير موجود');
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
            $msg = 'حجم الطلب يتجاوز حد الخادم post_max_size. قلل حجم/عدد المرفقات ثم أعد المحاولة.';
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
