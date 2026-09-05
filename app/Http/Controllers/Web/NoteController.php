<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NoteController extends Controller
{
    use AuthorizesRequests;

    private NoteService $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
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
        $observers = User::where('role', 'monitor')->orderBy('name')->get();

        $observerUser = null;
        if ($request->filled('observer')) {
            $observerUser = User::find($request->observer);
        }

        return view('notes.index', compact('notes', 'observers', 'observerUser'));
    }

    public function myNotes(Request $request)
    {
        $user = $request->user();
        $query = Note::with(['owner','attachments'])->where('user_id', $user->id);

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
        $this->authorize('create', Note::class);

        $validated = $request->validate([
            'floor_number' => ['required','integer','min:1'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            'observed_end_at' => ['nullable','date','after_or_equal:observed_at'],
            'description' => ['required','string','min:10','max:5000'],
            'files' => ['nullable','array','max:50'],
            'files.*' => ['file','max:102400'],
        ], [], [
            'floor_number' => 'رقم الطابق',
            'camera_number' => 'رقم الكاميرا',
            'observed_at' => 'وقت الرصد',
            'observed_end_at' => 'وقت انتهاء الرصد',
            'description' => 'الوصف',
        ]);

        $note = $this->noteService->createDraft($request->user(), $validated);

        $attachmentErrors = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                try { $this->noteService->addAttachment($request->user(), $note, $file); } catch (\Throwable $e) { $attachmentErrors[] = $file->getClientOriginalName().': '.$e->getMessage(); \Illuminate\Support\Facades\Log::warning('Attachment failed in store: '.$e->getMessage()); }
            }
        }

        if ($request->input('action') === 'send') {
            try { $this->noteService->sendNote($request->user(), $note); } catch (\Throwable $e) {}
        }

        if ($request->expectsJson()) {
            return response()->json(['success'=>true,'data'=>$note->load(['owner','attachments']),'attachment_errors'=>$attachmentErrors], 201);
        }

        if (!empty($attachmentErrors)) {
            return redirect()->route('notes.index')->with('success', 'تم إنشاء الملاحظة بنجاح')->with('warning', 'بعض المرفقات لم تُرفع: '.implode(' | ', $attachmentErrors));
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

        $validated = $request->validate([
            'floor_number' => ['required','integer','min:1'],
            'camera_number' => ['required','integer','min:1'],
            'observed_at' => ['required','date'],
            'observed_end_at' => ['nullable','date','after_or_equal:observed_at'],
            'description' => ['required','string','min:10','max:5000'],
            'files' => ['nullable','array','max:50'],
            'files.*' => ['file','max:102400'],
        ]);

        $note = $this->noteService->updateNote($request->user(), $note, $validated);

        $attachmentErrors = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                try { $this->noteService->addAttachment($request->user(), $note, $file); } catch (\Throwable $e) { $attachmentErrors[] = $file->getClientOriginalName().': '.$e->getMessage(); \Illuminate\Support\Facades\Log::warning('Attachment failed in update: '.$e->getMessage()); }
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success'=>true,'data'=>$note->load(['owner','attachments']),'attachment_errors'=>$attachmentErrors]);
        }

        if (!empty($attachmentErrors)) {
            return redirect()->route('notes.index')->with('success', 'تم تحديث الملاحظة بنجاح')->with('warning', 'بعض المرفقات لم تُرفع: '.implode(' | ', $attachmentErrors));
        }
        return redirect()->route('notes.index')->with('success', 'تم تحديث الملاحظة بنجاح');
    }

    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);
        $this->noteService->deleteDraft(auth()->user(), $note);

        if (request()->expectsJson()) {
            return response()->json(['success'=>true], 204);
        }
        return redirect()->route('notes.index')->with('success', 'تم حذف الملاحظة');
    }

    public function send(Note $note)
    {
        $this->authorize('send', $note);
        $note = $this->noteService->sendNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success'=>true,'data'=>$note]);
        return redirect()->route('notes.index')->with('success', 'تم إرسال الملاحظة للمراجعة');
    }

    public function accept(Note $note)
    {
        $this->authorize('accept', $note);
        $note = $this->noteService->acceptNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success'=>true,'data'=>$note]);
        return redirect()->route('notes.index')->with('success', 'تم قبول الملاحظة');
    }

    public function reject(Request $request, Note $note)
    {
        $this->authorize('reject', $note);
        $request->validate(['rejection_reason'=>['required','string','min:5','max:1000']]);
        $note = $this->noteService->rejectNote(auth()->user(), $note, $request->rejection_reason);
        if (request()->expectsJson()) return response()->json(['success'=>true,'data'=>$note]);
        return redirect()->route('notes.index')->with('success', 'تم رفض الملاحظة');
    }

    public function resend(Note $note)
    {
        $this->authorize('resend', $note);
        $note = $this->noteService->resendRejectedNote(auth()->user(), $note);
        if (request()->expectsJson()) return response()->json(['success'=>true,'data'=>$note]);
        return redirect()->route('notes.index')->with('success', 'تمت إعادة إرسال الملاحظة');
    }

    public function destroyAttachment(Note $note, Attachment $attachment)
    {
        if ($attachment->note_id !== $note->id) abort(404);
        $this->authorize('removeAttachment', $note);
        $this->noteService->removeAttachment(auth()->user(), $attachment);
        if (request()->expectsJson()) return response()->json(['success'=>true], 204);
        return back()->with('success', 'تم حذف المرفق');
    }

    public function viewAttachment(Attachment $attachment)
    {
        $user = auth()->user();
        $note = $attachment->note;
        if (!$user->can('view', $note)) abort(403, 'غير مصرح لك بعرض هذا المرفق');
        if (!Storage::disk('private')->exists($attachment->file_path)) abort(404, 'الملف غير موجود');

        $mime = $attachment->mime_type;
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        return Storage::disk('private')->response($attachment->file_path, $attachment->original_name, $headers);
    }

    public function downloadAttachment(Attachment $attachment)
    {
        $user = auth()->user();
        $note = $attachment->note;
        if (!$user->can('view', $note)) abort(403, 'غير مصرح لك بتحميل هذا المرفق');
        if (!$user->isReportWriter()) abort(403, 'التنزيل مسموح لكاتب التقرير فقط');
        if (!Storage::disk('private')->exists($attachment->file_path)) abort(404, 'الملف غير موجود');

        return Storage::disk('private')->download($attachment->file_path, $attachment->original_name);
    }
}
