<?php

namespace App\Services;

use App\Exceptions\AttachmentUploadException;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Notifications\NoteAcceptedNotification;
use App\Notifications\NoteRejectedNotification;
use App\Notifications\NoteSentNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class NoteService
{
    
    public function __construct(
        private AttachmentStorageService $storage,
        private ?WebPushService $push = null,
    ) {}

    /**
     * الحد الأقصى لحجم الملف الواحد بالكيلوبايت — مصدر واحد لكل الكنترولرز
     * وطلبات الـ API (صور/فيديو/صوت)، مع احترام حد PHP.
     */
    public static function uploadFileMaxKb(): int
    {
        $phpMaxKb = (int) (self::parseBytesStatic((string) ini_get('upload_max_filesize')) / 1024);
        $appMaxKb = max(
            (int) config('attachments.max_image_size', 20480),
            (int) config('attachments.max_video_size', 102400),
            (int) (config('attachments.max_audio_size', 100 * 1024 * 1024) / 1024)
        );
        $cap = min($appMaxKb, 512000);

        return $phpMaxKb > 0 ? min($phpMaxKb, $cap) : $cap;
    }

    private static function parseBytesStatic(string $value): int
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

    /**
     * فالديشن ذكية للنطاق الزمني — نقطة واحدة لكل الملاحظات والإرساليات:
     * - عبور منتصف الليل مسموح (نهاية أصغر من البداية = اليوم التالي) بدل الرفض.
     * - المدة القصوى 12 ساعة لمنع أخطاء الإدخال.
     * - لا تواريخ مستقبلية (سماح ساعة واحدة لانحراف الساعات).
     */
    public static function normalizeObservedRange(array $data): array
    {
        if (empty($data['observed_at'])) {
            return $data;
        }

        try {
            $start = \Carbon\Carbon::parse($data['observed_at']);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(__('api.time_invalid'));
        }

        if ($start->gt(now()->addHour())) {
            throw new InvalidArgumentException(__('api.time_future'));
        }

        if (! empty($data['observed_end_at'])) {
            try {
                $end = \Carbon\Carbon::parse($data['observed_end_at']);
            } catch (\Throwable $e) {
                throw new InvalidArgumentException(__('api.time_end_invalid'));
            }

            if ($end->lt($start)) {
                // عبور منتصف الليل — نفس سلوك واجهة الملاحظات سابقاً.
                $end->addDay();
            }

            if ($start->diffInMinutes($end) > 12 * 60) {
                throw new InvalidArgumentException(__('api.time_range_exceeded'));
            }

            if ($end->gt(now()->addHour())) {
                throw new InvalidArgumentException(__('api.time_end_future'));
            }

            $data['observed_end_at'] = $end->toDateTimeString();
        }

        $data['observed_at'] = $start->toDateTimeString();

        return $data;
    }

    /**
     * إبطال مستهدف للكاش بعد أي تغيير حالة — العدادات كانت تبقى قديمة 30–300 ثانية.
     */
    public static function flushNoteCaches(?int $userId = null): void
    {
        try {
            $cache = \Illuminate\Support\Facades\Cache::store(config('cache.default'));
            if ($userId) {
                $cache->forget('notes-counts:'.$userId.':my');
                $cache->forget('notes-counts:'.$userId.':all');
                $cache->forget('profile-stats:'.$userId);
            }
            $cache->forget('ranking-global-stats');
        } catch (\Throwable $e) {
        }
    }

    public function createDraft(User $user, array $data): Note
    {
        if (!$user->isMonitor() && !$user->isReportWriter()) {
            throw new InvalidArgumentException(__('api.note_unauthorized_create'));
        }
        $allowed = array_intersect_key($data, array_flip(['floor_number', 'camera_number', 'observed_at', 'observed_end_at', 'description']));
        $allowed = self::normalizeObservedRange($allowed);
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

    
    public function createNoteWithAttachments(User $user, array $data, array $files, int $clientFilesCount = 0): array
    {
        $received = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $filesReceived = count($received);
        $clientFilesCount = max(0, $clientFilesCount);

        
        if ($clientFilesCount > 0 && $filesReceived < $clientFilesCount) {
            $msg = $filesReceived === 0
                ? __('api.note_files_announced_none', ['count' => $clientFilesCount])
                : __('api.note_files_partial', ['received' => $filesReceived, 'count' => $clientFilesCount]);
            Log::warning('[ATTACHMENT] transport loss before create', array_merge([
                'user_id' => $user->id,
                'client_files_count' => $clientFilesCount,
                'files_received' => $filesReceived,
            ], $this->transportForensics()));

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
                        __('api.note_attach_batch_failed_op'),
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
                        __('api.note_attach_batch_failed_op'),
                        stage: 'validation',
                        originalName: $file->getClientOriginalName(),
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: $attachmentErrors,
                        previous: $e,
                    );
                }
            }

            
            $attachmentsSaved = count($attachments);
            if ($filesReceived !== $attachmentsSaved) {
                throw new AttachmentUploadException(
                    __('api.note_attach_batch_failed_op'),
                    stage: 'verification',
                    filesReceived: $filesReceived,
                    attachmentsSaved: $attachmentsSaved,
                    attachmentErrors: $attachmentErrors ?: [__('api.note_attach_count_mismatch')],
                );
            }

            
            foreach ($attachments as $attachment) {
                $fresh = Attachment::where('id', $attachment->id)->where('note_id', $note->id)->first();
                if (!$fresh || !$this->storage->exists($fresh->file_path)) {
                    throw new AttachmentUploadException(
                        __('api.note_attach_verify_before_save'),
                        stage: 'verification',
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: [__('api.note_attach_files_missing_verify')],
                    );
                }
            }

            DB::commit();
            self::flushNoteCaches($user->id);

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
                __('api.note_create_with_attach_failed'),
                stage: 'db',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: [__('api.note_create_failed', ['error' => $e->getMessage()])],
                previous: $e,
            );
        }
    }

    
    public function updateNoteWithAttachments(User $user, Note $note, array $data, array $files, int $clientFilesCount = 0): array
    {
        $received = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $filesReceived = count($received);
        $clientFilesCount = max(0, $clientFilesCount);
        $attachmentsBefore = $note->attachments()->count();
        $maxAttachments = (int) config('attachments.max_per_note', 10);

        // كان بالإمكان تجاوز الحد عبر التعديل المتكرر — $attachmentsBefore حُسب ولم يُفحص أبداً.
        if ($attachmentsBefore + $filesReceived > $maxAttachments) {
            throw new AttachmentUploadException(
                __('api.note_attach_max_detail', ['max' => $maxAttachments, 'before' => $attachmentsBefore, 'new' => $filesReceived]),
                stage: 'validation',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: [__('api.note_attach_max', ['max' => $maxAttachments])],
            );
        }

        if ($clientFilesCount > 0 && $filesReceived < $clientFilesCount) {
            $msg = $filesReceived === 0
                ? __('api.note_files_announced_none_short', ['count' => $clientFilesCount])
                : __('api.note_files_partial_short', ['received' => $filesReceived, 'count' => $clientFilesCount]);
            Log::warning('[ATTACHMENT] transport loss before update', array_merge([
                'user_id' => $user->id,
                'note_id' => $note->id,
                'client_files_count' => $clientFilesCount,
                'files_received' => $filesReceived,
            ], $this->transportForensics()));
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
                $allowed = self::normalizeObservedRange($allowed);
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
                        __('api.note_attach_batch_failed_edit'),
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
                        __('api.note_attach_batch_failed_edit'),
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
                    __('api.note_attach_batch_failed_edit'),
                    stage: 'verification',
                    filesReceived: $filesReceived,
                    attachmentsSaved: count($newAttachments),
                    attachmentErrors: $attachmentErrors ?: [__('api.note_attach_count_mismatch_short')],
                );
            }

            foreach ($newAttachments as $attachment) {
                if (!$this->storage->exists($attachment->file_path)) {
                    throw new AttachmentUploadException(
                        __('api.note_attach_verify_before_save'),
                        stage: 'verification',
                        filesReceived: $filesReceived,
                        attachmentsSaved: 0,
                        attachmentErrors: [__('api.note_attach_files_missing_verify_short')],
                    );
                }
            }

            DB::commit();
            self::flushNoteCaches($user->id);

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
                __('api.note_update_with_attach_failed'),
                stage: 'db',
                filesReceived: $filesReceived,
                attachmentsSaved: 0,
                attachmentErrors: [__('api.note_update_failed', ['error' => $e->getMessage()])],
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
        $allowed = self::normalizeObservedRange($allowed);
        $note->update($allowed);
        self::flushNoteCaches($note->user_id);
        if ($note->processed_by) {
            self::flushNoteCaches($note->processed_by);
        }

        return $note->fresh();
    }

    public function deleteDraft(User $user, Note $note): void
    {
        if (!$note->isDraft() || $note->user_id !== $user->id) {
            throw new InvalidArgumentException(__('api.note_delete_rejected'));
        }
        $attachments = $note->attachments()->get();
        $ownerId = $note->user_id;
        DB::transaction(function () use ($note, $attachments) {
            foreach ($attachments as $attachment) {
                if ($attachment->file_path) {
                    $this->storage->delete($attachment->file_path);
                }
                $attachment->delete();
            }
            $note->delete();
        });
        self::flushNoteCaches($ownerId);
    }

    public function sendNote(User $user, Note $note): Note
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException(__('api.note_unauthorized_send'));
        }
        if (!$note->isDraft()) {
            throw new InvalidArgumentException(__('api.note_send_draft_only'));
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

            // fan-out آلي في الخلفية: الريكويست يعود فوراً بلا انتظار N مستخدم.
            // الإشعارات نفسها queued (ShouldQueue) + الـ Push عبر Job منفصل.
            try {
                \App\Jobs\FanoutNoteNotifications::dispatch($fresh->id, $user->id, $user->name)->afterResponse();
            } catch (\Throwable $e) {
                Log::warning('فشل جدولة إشعار ملاحظة جديدة: '.$e->getMessage());
            }
        }
        self::flushNoteCaches($user->id);

        return $fresh ?? $note->fresh();
    }

    public function acceptNote(User $user, Note $note): Note
    {
        if (!$user->isReportWriter()) {
            throw new InvalidArgumentException(__('api.note_unauthorized_accept'));
        }
        if (!$note->isPending()) {
            throw new InvalidArgumentException(__('api.note_accept_pending_only'));
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
        self::flushNoteCaches($fresh->user_id);
        self::flushNoteCaches($user->id);

        return $fresh;
    }

    public function rejectNote(User $user, Note $note, string $reason): Note
    {
        if (!$user->isReportWriter()) {
            throw new InvalidArgumentException(__('api.note_unauthorized_reject'));
        }
        if (!$note->isPending()) {
            throw new InvalidArgumentException(__('api.note_reject_pending_only'));
        }
        if (empty(trim($reason))) {
            throw new InvalidArgumentException(__('api.note_reject_reason_required'));
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
        self::flushNoteCaches($fresh->user_id);
        self::flushNoteCaches($user->id);

        return $fresh;
    }

    public function resendRejectedNote(User $user, Note $note): Note
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException(__('api.note_unauthorized_resend'));
        }
        if (!$note->isRejected()) {
            throw new InvalidArgumentException(__('api.note_resend_rejected_only'));
        }
        $note->update([
            'status' => Note::STATUS_PENDING,
            'sent_at' => now(),
            'rejection_reason' => null,
            'processed_by' => null,
            'processed_at' => null,
        ]);
        $fresh = $note->fresh();

        try {
            \App\Jobs\FanoutNoteNotifications::dispatch($fresh->id, $user->id, $user->name, true)->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('فشل جدولة إشعار إعادة الإرسال: '.$e->getMessage());
        }
        self::flushNoteCaches($user->id);

        return $fresh;
    }

    
    public function addAttachment(User $user, Note $note, UploadedFile $file): Attachment
    {
        return $this->storeSingleAttachment($user, $note, $file);
    }

    
    private function storeSingleAttachment(User $user, Note $note, UploadedFile $file): Attachment
    {
        if ($note->user_id !== $user->id) {
            throw new InvalidArgumentException(__('api.note_unauthorized_attach'));
        }
        if ($note->isRejected()) {
            throw new InvalidArgumentException(__('api.note_attach_rejected_locked'));
        }
        if ($note->isAccepted() && $note->processed_by !== null && $note->processed_by !== $user->id) {
            throw new InvalidArgumentException(__('api.note_attach_accepted_locked'));
        }
        $maxAttachments = (int) config('attachments.max_per_note', 5);
        if ($note->attachments()->count() >= $maxAttachments) {
            throw new InvalidArgumentException(__('api.note_attach_max', ['max' => $maxAttachments]));
        }

        
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

        
        $relativePath = $this->storage->store($file, $note->id);

        try {
            $attachment = Attachment::create([
                'note_id' => $note->id,
                'file_path' => $relativePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize() ?? 0,
            ]);
        } catch (\Throwable $e) {
            
            $this->storage->delete($relativePath);
            Log::error('[ATTACHMENT] DB create failed, local file cleaned', [
                'note_id' => $note->id,
                'path' => $relativePath,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        
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
                __('api.note_attach_verify_db'),
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
            throw new InvalidArgumentException(__('api.note_unauthorized_detach'));
        }
        if ($note->isRejected()) {
            throw new InvalidArgumentException(__('api.note_attach_detach_rejected'));
        }
        if ($note->isAccepted() && $note->processed_by !== null && $note->processed_by !== $user->id) {
            throw new InvalidArgumentException(__('api.note_attach_detach_accepted'));
        }
        $isLocal = $this->storage->isLocal($attachment);
        $path = (string) $attachment->file_path;

        DB::transaction(function () use ($attachment, $isLocal, $path) {
            if ($isLocal) {
                $deleted = $this->storage->delete($path);
                if (!$deleted) {
                    Log::warning('[ATTACHMENT] local file delete reported failure', ['path' => $path]);
                }
            }
            $attachment->delete();
        });
    }

    public function getVisibleNotesQuery(User $user)
    {

        // owner محدود الأعمدة + processor لمنع N+1 + عدّاد مرفقات بلا تحميل كل الصفوف.
        $query = Note::with(['owner:id,name,avatar_path', 'processor:id,name'])
            ->withCount('attachments')
            ->whereNull('notes.general_submission_id');
        
        $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('status', '!=', Note::STATUS_DRAFT);
        });
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

    
    private function transportForensics(): array
    {
        try {
            $req = request();
            return [
                'content_length' => $req->server('CONTENT_LENGTH') ?? $req->header('Content-Length'),
                'content_type_head' => substr((string) $req->header('Content-Type'), 0, 60),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'files_keys' => array_keys($req->allFiles()),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasNotification(User $user, string $type, int $id, string $key = 'note_id', mixed $since = null): bool
    {
        try {
            // فحص مباشر في DB عبر JSON — بلا get() لكل الإشعارات.
            $query = $user->notifications()->where('type', $type);
            if ($since) {
                $query->where('created_at', '>', $since);
            }

            // SQLite/MySQL يدعمان -> للـ JSON في where. نجرّب المفتاح المطلوب أولاً.
            if ($query->clone()->where('data->'.$key, $id)->exists()) {
                return true;
            }
            // توافق خلفي: بعض الإشعارات القديمة تخزن note_id/submission_id/general_submission_id.
            foreach (['note_id', 'submission_id', 'general_submission_id'] as $alt) {
                if ($alt === $key) {
                    continue;
                }
                if ($query->clone()->where('data->'.$alt, $id)->exists()) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            // fallback آمن: اعتبره غير موجود لتفادي كتم إشعار مهم، مع منع التكرار عبر unique لاحقاً.
            return false;
        }
    }

    public function validateFile(UploadedFile $file): void
    {
        $allowedMimes = ['jpg','jpeg','png','webp','heic','heif','tiff','tif','bmp','avif','gif','svg','mp4','webm','mov','avi','3gp','3gpp','mkv','m4v','mpg','mpeg','wmv','flv','ogv','ts','mts','m2ts','vob','asf','m2v','3g2','f4v','m4p','mp3','wav','ogg','oga','m4a','aac','wma','flac','opus','aiff','aif','amr','3ga','awb','mid','midi','au','ra','weba','ac3','dts','alac'];
        $allowedMimeTypes = ['image/jpeg','image/png','image/webp','image/heic','image/heif','image/tiff','image/bmp','image/avif','image/gif','image/svg+xml','video/mp4','video/webm','video/quicktime','video/x-msvideo','video/3gpp','video/3gpp2','video/x-matroska','video/mpeg','video/x-ms-wmv','video/x-flv','video/ogg','video/mp2t','video/MP2T','video/x-mts','video/mpeg2','video/x-m4v','video/3gpp-tts','audio/mpeg','audio/wav','audio/x-wav','audio/wave','audio/ogg','audio/opus','audio/webm','audio/mp4','audio/x-m4a','audio/aac','audio/x-aac','audio/aacp','audio/x-aacp','audio/x-hx-aac-adts','audio/vnd.dlna.adts','audio/flac','audio/x-flac','audio/wma','audio/x-ms-wma','audio/aiff','audio/x-aiff','audio/amr','audio/3gpp','audio/midi','audio/x-midi','audio/basic','audio/vnd.wave','audio/mp4a-latm','application/octet-stream'];
        $dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pht','html', 'htm', 'js', 'exe', 'sh', 'bat', 'cmd', 'cgi', 'shtml'];
        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = strtolower($file->getClientOriginalName());
        if (in_array($extension, $dangerousExtensions)) {
            throw new InvalidArgumentException(__('api.file_type_not_allowed'));
        }
        foreach ($dangerousExtensions as $dangerous) {
            if (str_contains($originalName, '.' . $dangerous . '.') || str_ends_with($originalName, '.' . $dangerous)) {
                throw new InvalidArgumentException(__('api.file_type_not_allowed'));
            }
        }
        if (!in_array($extension, $allowedMimes)) {
            throw new InvalidArgumentException(__('api.file_ext_not_supported', ['list' => implode(', ', $allowedMimes)]));
        }
        $realPath = $file->getRealPath();
        
        
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
                    throw new InvalidArgumentException(__('api.file_real_type_not_supported'));
                }
            }
        }
        
        $maxImageSize = (int) config('attachments.max_image_size', 5120) * 1024;
        $maxVideoSize = (int) config('attachments.max_video_size', 30720) * 1024;
        $maxAudioSize = (int) config('attachments.max_audio_size', 100 * 1024 * 1024);
        
        $phpMax = $this->parseBytes((string) ini_get('upload_max_filesize'));
        if ($phpMax > 0) {
            $maxImageSize = min($maxImageSize, $phpMax);
            $maxVideoSize = min($maxVideoSize, $phpMax);
            $maxAudioSize = min($maxAudioSize, $phpMax);
        }
        $realMimeStr = (string) $realMime;
        if (str_starts_with($realMimeStr, 'image/') && $file->getSize() > $maxImageSize) {
            $mb = round($maxImageSize / 1024 / 1024, 1);
            throw new InvalidArgumentException(__('api.file_image_too_large', ['mb' => $mb]));
        }
        if (str_starts_with($realMimeStr, 'video/') && $file->getSize() > $maxVideoSize) {
            $mb = round($maxVideoSize / 1024 / 1024, 1);
            throw new InvalidArgumentException(__('api.file_video_too_large', ['mb' => $mb]));
        }
        if (str_starts_with($realMimeStr, 'audio/') && $file->getSize() > $maxAudioSize) {
            $mb = round($maxAudioSize / 1024 / 1024, 1);
            throw new InvalidArgumentException(__('api.file_audio_too_large', ['mb' => $mb]));
        }
    }

    private function parseBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') return 0;
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
