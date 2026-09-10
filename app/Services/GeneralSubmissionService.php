<?php

namespace App\Services;

use App\Exceptions\AttachmentUploadException;
use App\Models\GeneralSubmission;
use App\Models\GeneralSubmissionAttachment;
use App\Models\User;
use App\Notifications\DispatchAcceptedNotification;
use App\Notifications\DispatchRejectedNotification;
use App\Notifications\DispatchSentNotification;
use App\Services\NoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class GeneralSubmissionService
{
    public function __construct(private AttachmentStorageService $storage) {}

    public function createDraft(User $user, array $data): GeneralSubmission
    {
        if (!$user->isMonitor()) {
            throw new InvalidArgumentException('غير مصرح لك بإنشاء الإرسالات العامة');
        }

        $allowed = array_intersect_key($data, array_flip([
            'floor_number',
            'camera_number',
            'observed_at',
            'observed_end_at',
            'description',
        ]));

        $allowed['user_id'] = $user->id;
        $allowed['status'] = GeneralSubmission::STATUS_DRAFT;

        return GeneralSubmission::create($allowed);
    }

    
    public function createSubmissionWithAttachments(User $user, array $data, array $files, int $clientFilesCount, array $writerIds): array
    {
        if (!$user->isMonitor()) {
            throw new InvalidArgumentException('غير مصرح لك بإنشاء الإرسالات العامة');
        }

        $received = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $filesReceived = count($received);
        $clientFilesCount = max(0, $clientFilesCount);

        if ($clientFilesCount > 0 && $filesReceived < $clientFilesCount) {
            $msg = $filesReceived === 0
                ? "تم إعلان {$clientFilesCount} ملف لكن لم يصل أي مرفق إلى الخادم. يرجى إعادة رفع الملف."
                : "وصل {$filesReceived} من أصل {$clientFilesCount} ملف معلن فقط.";
            throw new AttachmentUploadException($msg, stage: 'transport_loss', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: [$msg]);
        }

        
        $writers = User::whereIn('id', $writerIds)->where('role', 'report_writer')->get();
        if ($writers->count() !== count($writerIds) || count(array_unique($writerIds)) !== count($writerIds) || $writers->isEmpty()) {
            throw new InvalidArgumentException('يجب أن يكون جميع المستلمين من كتاب التقاريرWithout تكرار');
        }

        $max = (int) config('attachments.max_per_submission', config('attachments.max_per_note', 5));
        if ($filesReceived > $max) {
            $msg = "الحد الأقصى للمرفقات هو {$max}";
            throw new AttachmentUploadException($msg, stage: 'validation', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: [$msg]);
        }

        $storedPaths = [];
        $newAttachments = [];
        $attachmentErrors = [];

        try {
            DB::beginTransaction();

            $submission = $this->createDraft($user, $data);

            foreach ($received as $file) {
                try {
                    $att = $this->storeSingleAttachment($user, $submission->fresh(), $file);
                    $newAttachments[] = $att;
                    $storedPaths[] = $att->file_path;
                } catch (AttachmentUploadException $e) {
                    $attachmentErrors[] = ['file' => $file->getClientOriginalName(), 'stage' => $e->stage, 'message' => $e->getMessage()];
                    throw new AttachmentUploadException('فشل رفع أحد المرفقات، ولم يتم حفظ الإرسالية.', stage: $e->stage, originalName: $file->getClientOriginalName(), filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: $attachmentErrors, previous: $e);
                } catch (InvalidArgumentException $e) {
                    $attachmentErrors[] = $file->getClientOriginalName().': '.$e->getMessage();
                    throw new AttachmentUploadException('فشل رفع أحد المرفقات، ولم يتم حفظ الإرسالية.', stage: 'validation', originalName: $file->getClientOriginalName(), filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: $attachmentErrors, previous: $e);
                }
            }

            if ($filesReceived !== count($newAttachments)) {
                throw new AttachmentUploadException('فشل رفع أحد المرفقات، ولم يتم حفظ الإرسالية.', stage: 'verification', filesReceived: $filesReceived, attachmentsSaved: count($newAttachments), attachmentErrors: $attachmentErrors ?: ['عدد الملفات المحفوظة لا يطابق المرسلة.']);
            }

            foreach ($newAttachments as $att) {
                if (!$this->storage->exists($att->file_path)) {
                    throw new AttachmentUploadException('فشل التحقق من المرفقات قبل الحفظ.', stage: 'verification', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: ['تعذّر التحقق من وجود الملفات فعلياً.']);
                }
            }

            
            $submission->reportWriters()->detach();
            $submission->reportWriters()->attach($writers->pluck('id')->toArray());
            $submission->update([
                'status' => GeneralSubmission::STATUS_PENDING,
                'sent_at' => now(),
                'rejection_reason' => null,
                'processed_by' => null,
                'processed_at' => null,
            ]);

            DB::commit();

            $fresh = $submission->fresh(['reportWriters', 'owner', 'attachments']);

            
            try {
                foreach ($writers as $writer) {
                    if (!$this->hasNotification($writer, DispatchSentNotification::class, $fresh->id)) {
                        $writer->notify(new DispatchSentNotification($fresh, $user->name));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('فشل إرسال إشعارات الإرسال: '.$e->getMessage());
            }

            return [
                'submission' => $fresh,
                'files_received' => $filesReceived,
                'attachments_saved' => count($newAttachments),
                'attachment_errors' => [],
            ];
        } catch (AttachmentUploadException $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $re) {
            }
            foreach ($storedPaths as $p) {
                try {
                    $this->storage->delete($p);
                } catch (\Throwable $ce) {
                }
            }
            Log::error('[SUBMISSION ATTACHMENT] create failed', ['user_id' => $user->id, 'stage' => $e->stage, 'message' => $e->getMessage()]);
            throw $e;
        } catch (\Throwable $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $re) {
            }
            foreach ($storedPaths as $p) {
                try {
                    $this->storage->delete($p);
                } catch (\Throwable $ce) {
                }
            }
            throw new AttachmentUploadException('فشل إنشاء الإرسالية مع المرفقات.', stage: 'db', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: ['فشل إنشاء الإرسالية: '.$e->getMessage()], previous: $e);
        }
    }

    
    public function addAttachment(User $user, GeneralSubmission $submission, UploadedFile $file): GeneralSubmissionAttachment
    {
        return $this->storeSingleAttachment($user, $submission, $file);
    }

    private function storeSingleAttachment(User $user, GeneralSubmission $submission, UploadedFile $file): GeneralSubmissionAttachment
    {
        if ($submission->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بإضافة مرفقات لهذه الإرسالية');
        }
        if (!$submission->isDraft()) {
            throw new InvalidArgumentException('يمكن إضافة المرفقات للمسودات فقط');
        }
        $max = (int) config('attachments.max_per_submission', config('attachments.max_per_note', 5));
        if ($submission->attachments()->count() >= $max) {
            throw new InvalidArgumentException("الحد الأقصى للمرفقات هو {$max}");
        }

        if (!$file->isValid()) {
            throw new AttachmentUploadException('تعذّر استلام الملف.', stage: 'php_upload', originalName: $file->getClientOriginalName(), filesReceived: 1, attachmentsSaved: 0);
        }

        app(NoteService::class)->validateFile($file);

        $relativePath = $this->storage->storeSubmission($file, $submission->id);

        try {
            $attachment = GeneralSubmissionAttachment::create([
                'general_submission_id' => $submission->id,
                'file_path' => $relativePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize() ?? 0,
            ]);
        } catch (\Throwable $e) {
            $this->storage->delete($relativePath);
            throw $e;
        }

        if (!GeneralSubmissionAttachment::where('id', $attachment->id)->where('general_submission_id', $submission->id)->exists() || !$this->storage->exists($relativePath)) {
            $this->storage->delete($relativePath);
            try {
                $attachment->delete();
            } catch (\Throwable $e) {
            }
            throw new AttachmentUploadException('فشل التحقق من حفظ المرفق.', stage: 'verification', originalName: $file->getClientOriginalName(), filesReceived: 1, attachmentsSaved: 0);
        }

        return $attachment;
    }

    public function removeAttachment(User $user, GeneralSubmissionAttachment $attachment): void
    {
        $submission = $attachment->submission;
        if ($submission->user_id !== $user->id) {
            throw new InvalidArgumentException('غير مصرح لك بحذف هذا المرفق');
        }
        if (!$submission->isDraft()) {
            throw new InvalidArgumentException('يمكن حذف المرفقات للمسودات فقط');
        }
        $path = (string) $attachment->file_path;
        DB::transaction(function () use ($attachment, $path) {
            $this->storage->delete($path);
            $attachment->delete();
        });
    }

    public function addReportWriter(GeneralSubmission $submission, User $writer): GeneralSubmission
    {
        if ($submission->status !== GeneralSubmission::STATUS_DRAFT) {
            throw new InvalidArgumentException('يمكن إضافة كتّاب فقط للإرسالات المسودة');
        }

        if ($submission->reportWriters->contains('id', $writer->id)) {
            throw new InvalidArgumentException('هذا الكاتب已被 assigned لهذه الإرسال');
        }

        $submission->reportWriters()->attach($writer->id);

        return $submission->fresh();
    }

    public function removeReportWriter(GeneralSubmission $submission, User $writer): GeneralSubmission
    {
        if ($submission->status !== GeneralSubmission::STATUS_DRAFT) {
            throw new InvalidArgumentException('يمكن إزالة كتّاب فقط للإرسالات المسودة');
        }

        if (!$submission->reportWriters->contains('id', $writer->id)) {
            throw new InvalidArgumentException('هذا الكاتب غير assigned لهذه الإرسال');
        }

        $submission->reportWriters()->detach($writer->id);

        return $submission->fresh();
    }

    public function submit(GeneralSubmission $submission, User $submitter, array $reportWriterIds): GeneralSubmission
    {
        if (!$submitter->isMonitor()) {
            throw new InvalidArgumentException('يمكن إرسال الإرسالات العامة فقط من قبل المراقبين');
        }

        if ($submission->status !== GeneralSubmission::STATUS_DRAFT) {
            throw new InvalidArgumentException('يمكن إرسال الإرسالات المسودة فقط');
        }

        
        $writers = User::whereIn('id', $reportWriterIds)
            ->where('role', 'report_writer')
            ->get();

        if ($writers->count() !== count($reportWriterIds)) {
            throw new InvalidArgumentException('يجب أن يكون جميع المستلمين من كتاب التقارير');
        }

        
        $uniqueIds = array_unique($reportWriterIds);
        if (count($uniqueIds) !== count($reportWriterIds)) {
            throw new InvalidArgumentException('لا يمكن وجود مستلمين مكررين');
        }

        if (count($writers) < 1) {
            throw new InvalidArgumentException('يجب اختيار كاتب على الأقل');
        }

        DB::transaction(function () use ($submission, $writers) {
            
            $submission->reportWriters()->detach();
            $submission->reportWriters()->attach($writers->pluck('id')->toArray());

            $submission->update([
                'status' => GeneralSubmission::STATUS_PENDING,
                'sent_at' => now(),
                'rejection_reason' => null,
                'processed_by' => null,
                'processed_at' => null,
            ]);
        });

        $fresh = $submission->fresh(['reportWriters', 'owner']);

        
        try {
            foreach ($writers as $writer) {
                if (!$this->hasNotification($writer, DispatchSentNotification::class, $fresh->id)) {
                    $writer->notify(new DispatchSentNotification($fresh, $submitter->name));
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('فشل إرسال إشعارات الإرسال: '.$e->getMessage());
        }

        return $fresh;
    }

    /**
     * Accept a submission. Submissions are NOT notes: acceptance only flips
     * the submission status — no Note record is created.
     */
    public function accept(GeneralSubmission $submission, User $writer): GeneralSubmission
    {
        if (!$writer->isReportWriter()) {
            throw new InvalidArgumentException('يمكن قبول الإرسال فقط من قبل كتاب التقارير');
        }

        if ($submission->status !== GeneralSubmission::STATUS_PENDING) {
            throw new InvalidArgumentException('يمكن قبول الإرسالات قيد المراجعة فقط');
        }

        if (!$submission->reportWriters()->where('users.id', $writer->id)->exists()) {
            throw new InvalidArgumentException('غير مصرح لك بقبول هذه الإرسالية — لست من ضمن الكتّاب المعنيين');
        }

        $submission->update([
            'status' => GeneralSubmission::STATUS_ACCEPTED,
            'processed_by' => $writer->id,
            'processed_at' => now(),
        ]);

        $fresh = $submission->fresh(['owner']);

        try {
            $owner = $fresh->owner;
            if ($owner && $owner->id !== $writer->id && !$this->hasNotification($owner, DispatchAcceptedNotification::class, $fresh->id)) {
                $owner->notify(new DispatchAcceptedNotification($fresh, $writer->name));
            }
        } catch (\Throwable $e) {
            Log::warning('فشل إرسال إشعار قبول الإرسالية: '.$e->getMessage());
        }

        return $fresh;
    }

    public function reject(GeneralSubmission $submission, User $writer, string $reason): GeneralSubmission
    {
        if (!$writer->isReportWriter()) {
            throw new InvalidArgumentException('يمكن رفض الإرسال فقط من قبل كتاب التقارير');
        }

        if ($submission->status !== GeneralSubmission::STATUS_PENDING) {
            throw new InvalidArgumentException('يمكن رفض الإرسالات قيد المراجعة فقط');
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('سبب الرفض مطلوب');
        }

        if (!$submission->reportWriters()->where('users.id', $writer->id)->exists()) {
            throw new InvalidArgumentException('غير مصرح لك برفض هذه الإرسالية — لست من ضمن الكتّاب المعنيين');
        }

        $submission->update([
            'status' => GeneralSubmission::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_by' => $writer->id,
            'processed_at' => now(),
        ]);

        $fresh = $submission->fresh(['owner']);

        try {
            $owner = $fresh->owner;
            if ($owner && $owner->id !== $writer->id && !$this->hasNotification($owner, DispatchRejectedNotification::class, $fresh->id)) {
                $owner->notify(new DispatchRejectedNotification($fresh, $reason, $writer->name));
            }
        } catch (\Throwable $e) {
            Log::warning('فشل إرسال إشعار رفض الإرسالية: '.$e->getMessage());
        }

        return $fresh;
    }

    public function getVisibleSubmissions(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return GeneralSubmission::where(function ($query) use ($user) {
            $query->whereHas('reportWriters', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
                ->orWhere('user_id', $user->id);
        });
    }

    public function validateFile(UploadedFile $file): void
    {
        
        $helper = app(NoteService::class);
        $helper->validateFile($file);
    }

    private function hasNotification(User $user, string $type, int $id): bool
    {
        try {
            $existing = $user->notifications()->where('type', $type)->get();
            foreach ($existing as $n) {
                $data = $n->data;
                if (is_string($data)) {
                    $data = json_decode($data, true);
                }
                if (($data['submission_id'] ?? null) == $id || ($data['general_submission_id'] ?? null) == $id) {
                    return true;
                }
            }
        } catch (\Throwable $e) {}
        return false;
    }
}
