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
            throw new InvalidArgumentException(__('api.sub_unauthorized_create'));
        }

        $allowed = array_intersect_key($data, array_flip([
            'floor_number',
            'camera_number',
            'observed_at',
            'observed_end_at',
            'description',
        ]));
        $allowed = NoteService::normalizeObservedRange($allowed);

        $allowed['user_id'] = $user->id;
        $allowed['status'] = GeneralSubmission::STATUS_DRAFT;

        return GeneralSubmission::create($allowed);
    }

    
    public function createSubmissionWithAttachments(User $user, array $data, array $files, int $clientFilesCount, array $writerIds): array
    {
        if (!$user->isMonitor()) {
            throw new InvalidArgumentException(__('api.sub_unauthorized_create'));
        }

        $received = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $filesReceived = count($received);
        $clientFilesCount = max(0, $clientFilesCount);

        if ($clientFilesCount > 0 && $filesReceived < $clientFilesCount) {
            $msg = $filesReceived === 0
                ? __('api.sub_files_announced_none', ['count' => $clientFilesCount])
                : __('api.sub_files_partial', ['received' => $filesReceived, 'count' => $clientFilesCount]);
            throw new AttachmentUploadException($msg, stage: 'transport_loss', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: [$msg]);
        }

        
        $writers = User::whereIn('id', $writerIds)->where('role', 'report_writer')->get();
        if ($writers->isEmpty() || $writers->count() !== count($writerIds)) {
            throw new InvalidArgumentException(__('api.sub_recipients_must_writers'));
        }
        if (count(array_unique($writerIds)) !== count($writerIds)) {
            throw new InvalidArgumentException(__('api.sub_no_duplicate_writers'));
        }

        $max = (int) config('attachments.max_per_submission', config('attachments.max_per_note', 5));
        if ($filesReceived > $max) {
            $msg = __('api.sub_attach_max', ['max' => $max]);
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
                    throw new AttachmentUploadException(__('api.sub_attach_failed'), stage: $e->stage, originalName: $file->getClientOriginalName(), filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: $attachmentErrors, previous: $e);
                } catch (InvalidArgumentException $e) {
                    $attachmentErrors[] = $file->getClientOriginalName().': '.$e->getMessage();
                    throw new AttachmentUploadException(__('api.sub_attach_failed'), stage: 'validation', originalName: $file->getClientOriginalName(), filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: $attachmentErrors, previous: $e);
                }
            }

            if ($filesReceived !== count($newAttachments)) {
                throw new AttachmentUploadException(__('api.sub_attach_failed'), stage: 'verification', filesReceived: $filesReceived, attachmentsSaved: count($newAttachments), attachmentErrors: $attachmentErrors ?: [__('api.note_attach_count_mismatch_short')]);
            }

            foreach ($newAttachments as $att) {
                if (!$this->storage->exists($att->file_path)) {
                    throw new AttachmentUploadException(__('api.note_attach_verify_before_save'), stage: 'verification', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: [__('api.note_attach_files_missing_verify_short')]);
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
                \App\Jobs\FanoutDispatchNotifications::dispatch($fresh->id, $user->name, $writers->pluck('id')->all())->afterResponse();
            } catch (\Throwable $e) {
                Log::warning('فشل جدولة إشعارات الإرسال: '.$e->getMessage());
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
            throw new AttachmentUploadException(__('api.sub_attach_create_failed'), stage: 'db', filesReceived: $filesReceived, attachmentsSaved: 0, attachmentErrors: [__('api.sub_create_failed'), $e->getMessage()], previous: $e);
        }
    }

    
    public function addAttachment(User $user, GeneralSubmission $submission, UploadedFile $file): GeneralSubmissionAttachment
    {
        return $this->storeSingleAttachment($user, $submission, $file);
    }

    private function storeSingleAttachment(User $user, GeneralSubmission $submission, UploadedFile $file): GeneralSubmissionAttachment
    {
        if ($submission->user_id !== $user->id) {
            throw new InvalidArgumentException(__('api.sub_unauthorized_attach'));
        }
        if (!$submission->isDraft()) {
            throw new InvalidArgumentException(__('api.sub_attach_draft_only'));
        }
        $max = (int) config('attachments.max_per_submission', config('attachments.max_per_note', 5));
        if ($submission->attachments()->count() >= $max) {
            throw new InvalidArgumentException(__('api.sub_attach_max', ['max' => $max]));
        }

        if (!$file->isValid()) {
            throw new AttachmentUploadException(__('api.upload_receive_failed'), stage: 'php_upload', originalName: $file->getClientOriginalName(), filesReceived: 1, attachmentsSaved: 0);
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
            throw new AttachmentUploadException(__('api.note_attach_verify_db'), stage: 'verification', originalName: $file->getClientOriginalName(), filesReceived: 1, attachmentsSaved: 0);
        }

        return $attachment;
    }

    public function removeAttachment(User $user, GeneralSubmissionAttachment $attachment): void
    {
        $submission = $attachment->submission;
        if ($submission->user_id !== $user->id) {
            throw new InvalidArgumentException(__('api.sub_unauthorized_detach'));
        }
        if (!$submission->isDraft()) {
            throw new InvalidArgumentException(__('api.sub_detach_draft_only'));
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
            throw new InvalidArgumentException(__('api.sub_writers_draft_only'));
        }

        if ($submission->reportWriters->contains('id', $writer->id)) {
            throw new InvalidArgumentException(__('api.sub_writer_already_added'));
        }

        $submission->reportWriters()->attach($writer->id);

        return $submission->fresh();
    }

    public function removeReportWriter(GeneralSubmission $submission, User $writer): GeneralSubmission
    {
        if ($submission->status !== GeneralSubmission::STATUS_DRAFT) {
            throw new InvalidArgumentException(__('api.sub_unwriters_draft_only'));
        }

        if (!$submission->reportWriters->contains('id', $writer->id)) {
            throw new InvalidArgumentException(__('api.sub_writer_not_added'));
        }

        $submission->reportWriters()->detach($writer->id);

        return $submission->fresh();
    }

    public function submit(GeneralSubmission $submission, User $submitter, array $reportWriterIds): GeneralSubmission
    {
        if (!$submitter->isMonitor()) {
            throw new InvalidArgumentException(__('api.sub_send_monitors_only'));
        }

        if ($submission->status !== GeneralSubmission::STATUS_DRAFT) {
            throw new InvalidArgumentException(__('api.sub_send_draft_only'));
        }

        
        $writers = User::whereIn('id', $reportWriterIds)
            ->where('role', 'report_writer')
            ->get();

        if ($writers->count() !== count($reportWriterIds)) {
            throw new InvalidArgumentException(__('api.sub_recipients_must_writers'));
        }

        
        $uniqueIds = array_unique($reportWriterIds);
        if (count($uniqueIds) !== count($reportWriterIds)) {
            throw new InvalidArgumentException(__('api.sub_no_duplicate_recipients'));
        }

        if (count($writers) < 1) {
            throw new InvalidArgumentException(__('api.sub_need_writer'));
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
            \App\Jobs\FanoutDispatchNotifications::dispatch($fresh->id, $submitter->name, $writers->pluck('id')->all())->afterResponse();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('فشل جدولة إشعارات الإرسال: '.$e->getMessage());
        }

        return $fresh;
    }


    public function accept(GeneralSubmission $submission, User $writer): GeneralSubmission
    {
        if (!$writer->isReportWriter()) {
            throw new InvalidArgumentException(__('api.sub_accept_writers_only'));
        }

        if ($submission->status !== GeneralSubmission::STATUS_PENDING) {
            throw new InvalidArgumentException(__('api.sub_accept_pending_only'));
        }

        if (!$submission->reportWriters()->where('users.id', $writer->id)->exists()) {
            throw new InvalidArgumentException(__('api.sub_accept_not_assigned'));
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
            throw new InvalidArgumentException(__('api.sub_reject_writers_only'));
        }

        if ($submission->status !== GeneralSubmission::STATUS_PENDING) {
            throw new InvalidArgumentException(__('api.sub_reject_pending_only'));
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException(__('api.note_reject_reason_required'));
        }

        if (!$submission->reportWriters()->where('users.id', $writer->id)->exists()) {
            throw new InvalidArgumentException(__('api.sub_reject_not_assigned'));
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
            $base = $user->notifications()->where('type', $type);
            if ($base->clone()->where('data->submission_id', $id)->exists()) {
                return true;
            }

            return $base->clone()->where('data->general_submission_id', $id)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
