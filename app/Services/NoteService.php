<?php

namespace App\Services;

use App\Exceptions\AttachmentUploadException;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteAcceptedNotification;
use App\Notifications\NoteRejectedNotification;
use App\Notifications\NoteSentNotification;
use App\Services\Media\CloudinaryMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class NoteService
{
    /**
     * Local disk storage is currently active.
     * Cloudinary storage temporarily disabled — kept only as LEGACY READ-ONLY fallback.
     */
    public function __construct(
        private AttachmentStorageService $storage,
        private ?CloudinaryMediaService $legacyMedia = null,
    ) {}

    public function createDraft(User $user, array $data): Note
    {
        if (!$user->isMonitor() && !$user->isReportWriter()) {
            throw new InvalidArgumentException('غير مصرح لك بإنشاء الملاحظات');
        }
        $allowed = array_intersect_key($data, array_flip(['floor_number', 'camera_number', 'observed_at', 'observed_end_at', 'description']));
        $allowed['user_id'] = $user->id;
        if ($user->isReportWriter()) {
            $allowed['status'] = Note::STATUS_ACCEPTED;
            $allowed['processed_by'] = $user->id;
            $allowed['processed_at'] = now();
        } else {
            $allowed['status'] = Note::STATUS_DRAFT;
        }
        return Note::create($allowed);
    }

    /**
     * Atomic creation: Note + every uploaded file must persist, else NOTHING persists.
     *
     * @param  UploadedFile[]  $files
     * @return array{note: Note, files_received: int, attachments_saved: int, attachment_errors: array}
     *
     * @throws AttachmentUploadException on any attachment failure (rollback + cleanup already done)
     */
    public function createNoteWithAttachments(User $user, array $data, array $files, int $clientFilesCount = 0): array
    {
        $received = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $filesReceived = count($received);
        $clientFilesCount = max(0, $clientFilesCount);

        // Transport-loss detection BEFORE creating the note: declared > received = failure, no note.
        if ($clientFilesCount > 0 && $filesReceived < $clientFilesCount) {
            $msg = $filesReceived === 0
                ? "تم إعلان {$clientFilesCount} ملف لكن لم يصل أي مرفق إلى الخادم. يرجى إعادة رفع الملف."
                : "وصل {$filesReceived} من أصل {$clientFilesCount} ملف معلن فقط. أعد إرسال الملفات الناقصة.";
            Log::warning('[ATTACHMENT] transport loss before create', [
                'user_id' => $user->id,
                'client_files_count' => $clientFilesCount,
                'files_received' => $filesReceived,
            ]);

            throw new AttachmentUploadException(
                $msg,
                stage: 'transport_loss',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: [$msg],
            );
        }

        $note = null;
        $storedPaths = [];
        $attachments = [];
        $attachmentErrors = [];

        try {
            DB::beginTransaction();

            $note = $this->createDraft($user, $data);

            foreach ($received as $file) {
                try {
                    $attachment = $this->storeSingleAttachment($user, $note, $file);
                    $attachments[] = $attachment;
                    $storedPaths[] = $attachment->file_path;
                } catch (AttachmentUploadException $e) {
                    $attachmentErrors[] = $this->formatAttachmentError($file, $e);
                    throw new AttachmentUploadException(
                        'فشل رفع أحد المرفقات، ولم يتم حفظ العملية.',
                        stage: $e->stage,
                        originalName: $file->getClientOriginalName(),
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: $attachmentErrors,
                        previous: $e,
                    );
                } catch (InvalidArgumentException $e) {
                    $attachmentErrors[] = $file->getClientOriginalName().': '.$e->getMessage();
                    throw new AttachmentUploadException(
                        'فشل رفع أحد المرفقات، ولم يتم حفظ العملية.',
                        stage: 'validation',
                        originalName: $file->getClientOriginalName(),
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: $attachmentErrors,
                        previous: $e,
                    );
                }
            }

            // Business-level invariant: received MUST equal saved, else fail loud.
            $attachmentsSaved = count($attachments);
            if ($filesReceived !== $attachmentsSaved) {
                throw new AttachmentUploadException(
                    'فشل رفع أحد المرفقات، ولم يتم حفظ العملية.',
                    stage: 'verification',
                    filesReceived: $filesReceived,
                    attachmentsSaved: $attachmentsSaved,
                    attachmentErrors: $attachmentErrors ?: ['عدد الملفات المحفوظة لا يطابق عدد الملفات المرسلة.'],
                );
            }

            // Physical + DB verification before commit.
            foreach ($attachments as $attachment) {
                $fresh = Attachment::where('id', $attachment->id)->where('note_id', $note->id)->first();
                if (!$fresh || !$this->storage->exists($fresh->file_path)) {
                    throw new AttachmentUploadException(
                        'فشل التحقق من المرفقات قبل الحفظ.',
                        stage: 'verification',
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: ['تعذّر التحقق من وجود الملفات فعلياً قبل الحفظ.'],
                    );
                }
            }

            DB::commit();

            return [
                'note' => $note->fresh(['owner', 'attachments']),
                'files_received' => $filesReceived,
                'attachments_saved' => $attachmentsSaved,
                'attachment_errors' => [],
            ];
        } catch (AttachmentUploadException $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $re) {
            }
            $this->cleanupStoredFiles($storedPaths);
            Log::error('[ATTACHMENT] createNoteWithAttachments failed', [
                'user_id' => $user->id,
                'stage' => $e->stage,
                'files_received' => $filesReceived,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $re) {
            }
            $this->cleanupStoredFiles($storedPaths);
            Log::error('[ATTACHMENT] createNoteWithAttachments unexpected failure', [
                'user_id' => $user->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw new AttachmentUploadException(
                'فشل إنشاء الملاحظة مع المرفقات.',
                stage: 'db',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: ['فشل إنشاء الملاحظة: '.$e->getMessage()],
                previous: $e,
            );
        }
    }

    /**
     * Atomic update: note fields + new files must all persist, else rollback to original.
     *
     * @param  UploadedFile[]  $files
     */
    public function updateNoteWithAttachments(User $user, Note $note, array $data, array $files, int $clientFilesCount = 0): array
    {
        $received = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $filesReceived = count($received);
        $clientFilesCount = max(0, $clientFilesCount);
        $attachmentsBefore = $note->attachments()->count();

        if ($clientFilesCount > 0 && $filesReceived < $clientFilesCount) {
            $msg = $filesReceived === 0
                ? "تم إعلان {$clientFilesCount} ملف لكن لم يصل أي مرفق إلى الخادم."
                : "وصل {$filesReceived} من أصل {$clientFilesCount} ملف معلن فقط.";
            throw new AttachmentUploadException(
                $msg,
                stage: 'transport_loss',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: [$msg],
            );
        }

        $storedPaths = [];
        $newAttachments = [];
        $attachmentErrors = [];

        try {
            DB::beginTransaction();

            $allowed = array_intersect_key($data, array_flip(['floor_number', 'camera_number', 'observed_at', 'observed_end_at', 'description']));
            if (!empty($allowed)) {
                $note->update($allowed);
            }

            foreach ($received as $file) {
                try {
                    $attachment = $this->storeSingleAttachment($user, $note->fresh(), $file);
                    $newAttachments[] = $attachment;
                    $storedPaths[] = $attachment->file_path;
                } catch (AttachmentUploadException $e) {
                    $attachmentErrors[] = $this->formatAttachmentError($file, $e);
                    throw new AttachmentUploadException(
                        'فشل رفع أحد المرفقات، ولم يتم حفظ التعديلات.',
                        stage: $e->stage,
                        originalName: $file->getClientOriginalName(),
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: $attachmentErrors,
                        previous: $e,
                    );
                } catch (InvalidArgumentException $e) {
                    $attachmentErrors[] = $file->getClientOriginalName().': '.$e->getMessage();
                    throw new AttachmentUploadException(
                        'فشل رفع أحد المرفقات، ولم يتم حفظ التعديلات.',
                        stage: 'validation',
                        originalName: $file->getClientOriginalName(),
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: $attachmentErrors,
                        previous: $e,
                    );
                }
            }

            if ($filesReceived !== count($newAttachments)) {
                throw new AttachmentUploadException(
                    'فشل رفع أحد المرفقات، ولم يتم حفظ التعديلات.',
                    stage: 'verification',
                    filesReceived: $filesReceived,
                    attachmentsSaved: count($newAttachments),
                    attachmentErrors: $attachmentErrors ?: ['عدد الملفات المحفوظة لا يطابق المرسلة.'],
                );
            }

            foreach ($newAttachments as $attachment) {
                if (!$this->storage->exists($attachment->file_path)) {
                    throw new AttachmentUploadException(
                        'فشل التحقق من المرفقات قبل الحفظ.',
                        stage: 'verification',
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: ['تعذّر التحقق من وجود الملفات فعلياً.'],
                    );
                }
            }

            DB::commit();

            $fresh = $note->fresh(['owner', 'attachments']);
            $totalSaved = $fresh->attachments->count();

            return [
                'note' => $fresh,
                'files_received' => $filesReceived,
                'attachments_saved' => $totalSaved,
                'new_saved' => count($newAttachments),
                'attachment_errors' => [],
            ];
        } catch (AttachmentUploadException $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $re) {
            }
            $this->cleanupStoredFiles($storedPaths);
            Log::error('[ATTACHMENT] updateNoteWithAttachments failed', [
                'note_id' => $note->id,
                'stage' => $e->stage,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $re) {
            }
            $this->cleanupStoredFiles($storedPaths);
            throw new AttachmentUploadException(
                'فشل تحديث الملاحظة مع المرفقات.',
                stage: 'db',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: ['فشل تحديث الملاحظة: '.$e->getMessage()],
                previous: $e,
            );
        }
    }

    public function updateNote(User $user, Note $note, array $data): Note
    {
        $allowed = array_intersect_key($data, array_flip(['floor_number', 'camera_number', 'observed_at', 'observed_end_at', 'description']));
        if (empty($allowed)) {
            return $note->fresh();
        }
        $note->update($allowed);
        return $note->fresh();
    }

    public function deleteDraft(User $user, Note $note): void
    {
        if (!$note->isDraft() || $note->user_id !== $user->id) {
            throw new InvalidArgumentException('لا يمكن حذف هذه الملاحظة');
        }
        $attachments = $note->attachments()->get();
        DB::transaction(function () use ($note, $attachments) {
            foreach ($attachments as $attachment) {
                // Local files are deleted; legacy Cloudinary assets are RETAINED (read-only).
                if ($this->storage->isLocal($attachment)) {
                    $this->storage->delete($attachment->file_path);
                } else {
                    Log::info('[ATTACHMENT] legacy cloudinary asset retained on draft delete', [
                        'attachment_id' => $attachment->id,
                        'file_path' => $attachment->file_path,
                    ]);
                }
                $attachment->delete();
            }
            $note->delete();
        });
    }

    public function sendNote(User $user, Note $note): Note
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإرسال هذه الملاحظة');
        }
        if (!$note->isDraft()) {
            throw new InvalidArgumentException('يمكن إرسال المسودات فقط');
        }
        if ($user->isReportWriter()) {
            $note->update([
                'status' => Note::STATUS_ACCEPTED,
                'sent_at' => now(),
                'rejection_reason' => null,
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);
            $fresh = $note->fresh();
        } else {
            $note->update([
                'status' => Note::STATUS_PENDING,
                'sent_at' => now(),
                'rejection_reason' => null,
                'processed_by' => null,
                'processed_at' => null,
            ]);
            $fresh = $note->fresh();
            // Notify all report writers of new pending note (real-time)
            try {
                $writers = User::where('role', 'report_writer')->get();
                foreach ($writers as $writer) {
                    if ($writer->id === $user->id) continue;
                    if (!$this->hasNotification($writer, NoteSentNotification::class, $fresh->id, 'note_id', $fresh->sent_at)) {
                        $writer->notify(new NoteSentNotification($fresh, $user->name));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('فشل إرسال إشعار ملاحظة جديدة: '.$e->getMessage());
            }
        }
        return $fresh ?? $note->fresh();
    }

    public function acceptNote(User $user, Note $note): Note
    {
        if (!$user->isReportWriter()) {
            throw new InvalidArgumentException('غير مصرح لك بقبول الملاحظات');
        }
        if (!$note->isPending()) {
            throw new InvalidArgumentException('يمكن قبول الملاحظات قيد المراجعة فقط');
        }
        $note->update([
            'status' => Note::STATUS_ACCEPTED,
            'processed_by' => $user->id,
            'processed_at' => now(),
        ]);
        $fresh = $note->fresh();
        try {
            $owner = $fresh->owner;
            if ($owner && $owner->id !== $user->id && !$this->hasNotification($owner, NoteAcceptedNotification::class, $fresh->id, 'note_id', $fresh->sent_at)) {
                $owner->notify(new NoteAcceptedNotification($fresh, $user->name));
            }
        } catch (\Throwable $e) {
            Log::warning('فشل إرسال إشعار القبول: '.$e->getMessage());
        }
        return $fresh;
    }

    public function rejectNote(User $user, Note $note, string $reason): Note
    {
        if (!$user->isReportWriter()) {
            throw new InvalidArgumentException('غير مصرح لك برفض الملاحظات');
        }
        if (!$note->isPending()) {
            throw new InvalidArgumentException('يمكن رفض الملاحظات قيد المراجعة فقط');
        }
        if (empty(trim($reason))) {
            throw new InvalidArgumentException('سبب الرفض مطلوب');
        }
        $note->update([
            'status' => Note::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_by' => $user->id,
            'processed_at' => now(),
        ]);
        $fresh = $note->fresh();
        try {
            $owner = $fresh->owner;
            if ($owner && $owner->id !== $user->id && !$this->hasNotification($owner, NoteRejectedNotification::class, $fresh->id, 'note_id', $fresh->sent_at)) {
                $owner->notify(new NoteRejectedNotification($fresh, $reason, $user->name));
            }
        } catch (\Throwable $e) {
            Log::warning('فشل إرسال إشعار الرفض: '.$e->getMessage());
        }
        return $fresh;
    }

    public function resendRejectedNote(User $user, Note $note): Note
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإعادة إرسال هذه الملاحظة');
        }
        if (!$note->isRejected()) {
            throw new InvalidArgumentException('يمكن إعادة إرسال الملاحظات المرفوضة فقط');
        }
        $note->update([
            'status' => Note::STATUS_PENDING,
            'sent_at' => now(),
            'rejection_reason' => null,
            'processed_by' => null,
            'processed_at' => null,
        ]);
        $fresh = $note->fresh();
        // Notify report writers on resend as well
        try {
            $writers = User::where('role', 'report_writer')->get();
            foreach ($writers as $writer) {
                if ($writer->id === $user->id) continue;
                if (!$this->hasNotification($writer, NoteSentNotification::class, $fresh->id, 'note_id', $fresh->sent_at)) {
                    $writer->notify(new NoteSentNotification($fresh, $user->name));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('فشل إرسال إشعار إعادة الإرسال: '.$e->getMessage());
        }
        return $fresh;
    }

    /**
     * Single attachment upload → Local Disk ONLY.
     * Cloudinary upload is DISABLED for new files (legacy read-only fallback only).
     */
    public function addAttachment(User $user, Note $note, UploadedFile $file): Attachment
    {
        return $this->storeSingleAttachment($user, $note, $file);
    }

    /**
     * Core single-file pipeline: auth → limit → php_upload → validation → store → DB → verify.
     * Any failure throws (AttachmentUploadException or InvalidArgumentException) — never silent.
     */
    private function storeSingleAttachment(User $user, Note $note, UploadedFile $file): Attachment
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإضافة مرفقات لهذه الملاحظة');
        }
        if ($note->isAccepted() && $note->processed_by !== null && $note->processed_by !== $user->id) {
            throw new InvalidArgumentException('لا يمكن تعديل مرفقات ملاحظة تمت مراجعتها من مستخدم آخر');
        }
        $maxAttachments = (int) config('attachments.max_per_note', 5);
        if ($note->attachments()->count() >= $maxAttachments) {
            throw new InvalidArgumentException("الحد الأقصى للمرفقات هو $maxAttachments");
        }

        // PHP upload stage first (UPLOAD_ERR_* must fail loud).
        if (!$file->isValid()) {
            $code = (int) $file->getError();
            Log::warning('[ATTACHMENT] php upload invalid in addAttachment', [
                'note_id' => $note->id,
                'original_name' => $file->getClientOriginalName(),
                'error_code' => $code,
            ]);
            throw new AttachmentUploadException(
                $this->uploadErrorMessage($code, $file->getClientOriginalName()),
                stage: 'php_upload',
                originalName: $file->getClientOriginalName(),
                filesReceived: 1,
                attachmentsSaved: 0,
            );
        }

        $this->validateFile($file);

        // Cloudinary storage temporarily disabled.
        // Local disk storage is currently active.
        $relativePath = $this->storage->store($file, $note->id);

        try {
            $attachment = Attachment::create([
                'note_id' => $note->id,
                'file_path' => $relativePath,
                'cloudinary_resource_type' => null,
                'cloudinary_format' => null,
                'secure_url' => null,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize() ?? 0,
            ]);
        } catch (\Throwable $e) {
            // Compensation: cleanup orphan local file if DB fails.
            $this->storage->delete($relativePath);
            Log::error('[ATTACHMENT] DB create failed, local file cleaned', [
                'note_id' => $note->id,
                'path' => $relativePath,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        // DB + physical verification: record must exist AND file must exist.
        $existsDb = Attachment::where('id', $attachment->id)->where('note_id', $note->id)->exists();
        $existsFile = $this->storage->exists($relativePath);
        if (!$existsDb || !$existsFile) {
            $this->storage->delete($relativePath);
            try {
                $attachment->delete();
            } catch (\Throwable $e) {
            }
            Log::error('[ATTACHMENT] verification failed after create', [
                'note_id' => $note->id,
                'attachment_id' => $attachment->id,
                'db_exists' => $existsDb,
                'file_exists' => $existsFile,
            ]);
            throw new AttachmentUploadException(
                'فشل التحقق من حفظ المرفق (قاعدة البيانات أو الملف).',
                stage: 'verification',
                originalName: $file->getClientOriginalName(),
                filesReceived: 1,
                attachmentsSaved: 0,
            );
        }

        return $attachment;
    }

    public function removeAttachment(User $user, Attachment $attachment): void
    {
        $note = $attachment->note;
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بحذف هذا المرفق');
        }
        if ($note->isAccepted() && $note->processed_by !== null && $note->processed_by !== $user->id) {
            throw new InvalidArgumentException('لا يمكن حذف مرفقات ملاحظة تمت مراجعتها من مستخدم آخر');
        }
        $isLocal = $this->storage->isLocal($attachment);
        $path = (string) $attachment->file_path;

        DB::transaction(function () use ($attachment, $isLocal, $path) {
            if ($isLocal) {
                $deleted = $this->storage->delete($path);
                if (!$deleted) {
                    Log::warning('[ATTACHMENT] local file delete reported failure', ['path' => $path]);
                }
            } else {
                // Legacy Cloudinary asset: delete DB record only, retain remote asset (non-destructive).
                Log::info('[ATTACHMENT] legacy cloudinary asset retained on attachment delete', [
                    'attachment_id' => $attachment->id,
                    'file_path' => $path,
                ]);
            }
            $attachment->delete();
        });
    }

    public function getVisibleNotesQuery(User $user)
    {
        $query = Note::with(['owner', 'attachments']);
        // الخصوصية: الملاحظ يرى ملاحظاته فقط، كاتب التقرير يرى ملاحظاته + جميع غير المسودة
        if ($user->isReportWriter()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('status', '!=', Note::STATUS_DRAFT);
            });
        } else {
            $query->where('user_id', $user->id);
        }
        return $query;
    }

    private function cleanupStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $this->storage->delete($path);
            } catch (\Throwable $e) {
            }
        }
    }

    private function formatAttachmentError(UploadedFile $file, AttachmentUploadException $e): array
    {
        return [
            'file' => $file->getClientOriginalName(),
            'stage' => $e->stage,
            'message' => $e->getMessage(),
        ];
    }

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

    private function hasNotification(User $user, string $type, int $id, string $key = 'note_id', mixed $since = null): bool
    {
        try {
            // فحص idempotent ضمن الدورة الحالية فقط: إشعارات الدورات السابقة
            // (created_at <= بداية الدورة الحالية sent_at) لا تُحتسب، حتى يصل
            // إشعار جديد عند كل رفض/قبول بعد إعادة الإرسال.
            $query = $user->notifications()->where('type', $type);
            if ($since) {
                $query->where('created_at', '>', $since);
            }
            $existing = $query->get();
            foreach ($existing as $n) {
                $data = $n->data;
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }
                if (($data[$key] ?? null) == $id) {
                    return true;
                }
                // دعم مفاتيح بديلة
                if (($data['note_id'] ?? null) == $id || ($data['submission_id'] ?? null) == $id || ($data['general_submission_id'] ?? null) == $id) {
                    // تحقق إضافي للنوع
                    if ($n->type === $type) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }

    public function validateFile(UploadedFile $file): void
    {
        $allowedMimes = ['jpg','jpeg','png','webp','heic','heif','tiff','tif','bmp','avif','gif','svg','mp4','webm','mov','avi','3gp','3gpp','mkv','m4v','mpg','mpeg','wmv','flv','ogv','ts','mts','m2ts','vob','asf','m2v','3g2','f4v','m4p','mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','ra','weba','aac','ac3','dts','alac','aiff'];
        $allowedMimeTypes = ['image/jpeg','image/png','image/webp','image/heic','image/heif','image/tiff','image/bmp','image/avif','image/gif','image/svg+xml','video/mp4','video/webm','video/quicktime','video/x-msvideo','video/3gpp','video/3gpp2','video/x-matroska','video/mpeg','video/x-ms-wmv','video/x-flv','video/ogg','video/mp2t','video/MP2T','video/x-mts','video/mpeg2','video/x-m4v','video/3gpp-tts','audio/mpeg','audio/wav','audio/x-wav','audio/wave','audio/ogg','audio/opus','audio/webm','audio/mp4','audio/x-m4a','audio/aac','audio/aacp','audio/flac','audio/x-flac','audio/wma','audio/x-ms-wma','audio/aiff','audio/x-aiff','audio/amr','audio/3gpp','audio/midi','audio/x-midi','audio/basic','audio/vnd.wave','application/octet-stream'];
        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pht','html', 'htm', 'js', 'exe', 'sh', 'bat', 'cmd', 'cgi', 'shtml'];
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = strtolower($file->getClientOriginalName());
        if (in_array($extension, $dangerousExtensions)) {
            throw new InvalidArgumentException('نوع الملف غير مسموح به');
        }
        foreach ($dangerousExtensions as $dangerous) {
            if (str_contains($originalName, '.' . $dangerous . '.') || str_ends_with($originalName, '.' . $dangerous)) {
                throw new InvalidArgumentException('نوع الملف غير مسموح به');
            }
        }
        if (!in_array($extension, $allowedMimes)) {
            throw new InvalidArgumentException('امتداد الملف غير مدعوم. الامتدادات المدعومة: ' . implode(', ', $allowedMimes));
        }
        $realPath = $file->getRealPath();
        // ROOT CAUSE FIX: تهيئة افتراضية من Symfony guesser حتى لا يُستخدم متغير غير معرّف
        // (TypeError) لو تعذّر finfo — مع بقاء فحص الامتداد هو الحارس الأول
        $realMime = $file->getMimeType();
        if ($realPath && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $realPath);
                finfo_close($finfo);
                if ($detected) {
                    $realMime = $detected;
                }
                if ($realMime && $realMime !== 'application/octet-stream' && !in_array($realMime, $allowedMimeTypes)) {
                    throw new InvalidArgumentException('نوع الملف الفعلي غير مدعوم');
                }
            }
        }
        $maxImageSize = (int) config('attachments.max_image_size', 5120) * 1024;
        $maxVideoSize = (int) config('attachments.max_video_size', 30720) * 1024;
        $maxAudioSize = (int) config('attachments.max_audio_size', 100 * 1024 * 1024);
        $realMimeStr = (string) $realMime;
        if (str_starts_with($realMimeStr, 'image/') && $file->getSize() > $maxImageSize) {
            throw new InvalidArgumentException('حجم الصورة يتجاوز الحد الأقصى المسموح (20MB)');
        }
        if (str_starts_with($realMimeStr, 'video/') && $file->getSize() > $maxVideoSize) {
            throw new InvalidArgumentException('حجم الفيديو يتجاوز الحد الأقصى المسموح (500MB)');
        }
        if (str_starts_with($realMimeStr, 'audio/') && $file->getSize() > $maxAudioSize) {
            throw new InvalidArgumentException('حجم الصوت يتجاوز الحد الأقصى المسموح (100MB)');
        }
    }
}
